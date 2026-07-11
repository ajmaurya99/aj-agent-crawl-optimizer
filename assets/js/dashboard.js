/**
 * Agent Ready dashboard app.
 *
 * Vanilla JS over the ajaco/v1 REST API: run scans, render the Level 0-5
 * ladder + evidence-backed check cards, apply one-click fixes, and re-verify
 * the fixed check inline (scan → fix → prove).
 *
 * @package Ajaco
 */
( function () {
	'use strict';

	var D = window.AjacoDash || {};
	var root = document.getElementById( 'ajaco-dashboard' );
	if ( ! root || ! D.restUrl ) {
		return;
	}

	var state = {
		scan: D.scan || null,
		busyScan: false,
		busyCheck: {}, // checkId => 'fixing'|'verifying'
		openEvidence: {}, // checkId => bool
		openCards: {}, // checkId => bool (manual overrides)
		flash: {}, // checkId => 'pass'|'fail' one-shot animation
		sheetOpen: false,
		error: ''
	};

	var STATUS_META = {
		pass: { label: D.i18n.pass, cls: 'pass' },
		fail: { label: D.i18n.fail, cls: 'fail' },
		neutral: { label: D.i18n.neutral, cls: 'neutral' },
		unableToCheck: { label: D.i18n.unable, cls: 'neutral' }
	};

	var LEVEL_COLORS = [ '#d63638', '#d97706', '#dba617', '#2271b1', '#00a32a', '#008a20' ];

	var CATEGORY_ORDER = [ 'discoverability', 'contentAccessibility', 'botAccessControl', 'discovery', 'commerce' ];

	function esc( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	function api( method, path, body ) {
		return window.fetch( D.restUrl + path, {
			method: method,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': D.nonce
			},
			credentials: 'same-origin',
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( res ) {
			// Guarded parse: gateway timeouts / WAF pages return HTML, and a
			// raw JSON.parse error is meaningless to the admin.
			return res.json().catch( function () {
				return null;
			} ).then( function ( json ) {
				if ( ! res.ok ) {
					if ( 403 === res.status ) {
						throw new Error( 'Your session expired — reload this page and try again.' );
					}
					throw new Error( json && json.message ? json.message : 'Request failed (HTTP ' + res.status + ')' );
				}
				return json;
			} );
		} );
	}

	function scoreColor( score ) {
		if ( score >= 90 ) {
			return '#00a32a';
		}
		return score >= 50 ? '#dba617' : '#d63638';
	}

	/* ------------------------------------------------------------------ *
	 * Gauge
	 * ------------------------------------------------------------------ */

	function polar( deg ) {
		var rad = ( deg * Math.PI ) / 180;
		return { x: 120 + 95 * Math.cos( rad ), y: 125 - 95 * Math.sin( rad ) };
	}

	function arcPath( from, to ) {
		if ( Math.abs( from - to ) < 0.5 ) {
			return '';
		}
		var a = polar( from );
		var b = polar( to );
		return 'M ' + a.x.toFixed( 2 ) + ' ' + a.y.toFixed( 2 ) + ' A 95 95 0 0 1 ' + b.x.toFixed( 2 ) + ' ' + b.y.toFixed( 2 );
	}

	function gaugeSvg( scan ) {
		var cats = CATEGORY_ORDER.filter( function ( c ) {
			return c !== 'commerce' && scan.scores.categories[ c ];
		} );
		var gap = 12;
		var span = 180 - gap * ( cats.length - 1 );
		var totalChecks = cats.reduce( function ( sum, c ) {
			return sum + scan.scores.categories[ c ].checkCount;
		}, 0 );

		var angle = 180;
		var parts = '';
		cats.forEach( function ( c ) {
			var cat = scan.scores.categories[ c ];
			var width = span * ( totalChecks > 0 ? cat.checkCount / totalChecks : 1 / cats.length );
			var from = angle;
			var to = angle - width;
			var skipped = 0 === cat.total && cat.checkCount > 0;

			parts += '<path d="' + arcPath( from, to ) + '" fill="none" stroke="var(--ajaco-track)" stroke-width="14" stroke-linecap="round"' +
				( skipped ? ' stroke-dasharray="5 5"' : '' ) + '/>';

			if ( ! skipped && cat.score > 2 ) {
				var fillTo = from - width * ( cat.score / 100 );
				parts += '<path d="' + arcPath( from, fillTo ) + '" fill="none" stroke="' + scoreColor( cat.score ) + '" stroke-width="14" stroke-linecap="round"/>';
			}
			angle = to - gap;
		} );

		return '<svg width="240" height="140" viewBox="0 0 240 140" role="img" aria-label="' +
			esc( 'Level ' + scan.level + ' of 5 — ' + scan.levelName ) + '">' + parts + '</svg>';
	}

	/* ------------------------------------------------------------------ *
	 * Prompt composition (Goal / Issue / Fix / Skill / Docs)
	 * ------------------------------------------------------------------ */

	function buildPrompt( id, result ) {
		var meta = D.checkMeta[ id ] || {};
		var parts = [];
		if ( meta.description ) {
			parts.push( 'Goal: ' + meta.description );
		}
		if ( result && result.message ) {
			parts.push( ( 'fail' === result.status ? 'Issue: ' : 'Note: ' ) + result.message );
		}
		if ( meta.prompt ) {
			parts.push( 'Fix: ' + meta.prompt );
		}
		if ( meta.skillUrl ) {
			parts.push( 'Skill: ' + meta.skillUrl );
		}
		if ( meta.specUrls && meta.specUrls.length ) {
			parts.push( 'Docs: ' + meta.specUrls.join( ', ' ) );
		}
		return parts.join( '\n\n' );
	}

	function failingChecks() {
		var out = [];
		if ( ! state.scan ) {
			return out;
		}
		CATEGORY_ORDER.forEach( function ( cat ) {
			var checks = state.scan.checks[ cat ] || {};
			Object.keys( checks ).forEach( function ( id ) {
				if ( 'fail' === checks[ id ].status ) {
					out.push( { id: id, result: checks[ id ] } );
				}
			} );
		} );
		return out;
	}

	/* ------------------------------------------------------------------ *
	 * Rendering
	 * ------------------------------------------------------------------ */

	function render() {
		var html = '';
		html += renderHeader();
		if ( state.error ) {
			html += '<div class="notice notice-error inline ajaco-error"><p>' + esc( state.error ) + '</p></div>';
		}
		if ( ! state.scan ) {
			html += renderEmpty();
		} else {
			html += '<div class="ajaco-hero-row">' + renderGaugeCard() + renderNextLevel() + '</div>';
			html += renderCategories();
			html += renderFab();
			html += state.sheetOpen ? renderSheet() : '';
		}
		root.innerHTML = html;
		bind();
	}

	function renderHeader() {
		var scanBtn;
		if ( state.busyScan ) {
			scanBtn = '<button class="button button-primary" disabled><span class="ajaco-spin"></span>' + esc( D.i18n.scanning ) + '</button>';
		} else {
			scanBtn = '<button class="button button-primary" data-act="scan">' + esc( state.scan ? D.i18n.rescan : D.i18n.scan ) + '</button>';
		}
		var when = '';
		if ( state.scan && state.scan.scannedAt ) {
			when = '<span class="ajaco-meta">' + esc( new Date( state.scan.scannedAt ).toLocaleString() ) + '</span>';
		}
		return '<div class="ajaco-head">' +
			'<h2>Agent Ready</h2>' +
			'<span class="ajaco-origin">' + esc( D.homeUrl ) + '</span>' +
			when +
			'<span class="ajaco-grow"></span>' +
			'<a class="button" href="' + esc( D.settingsUrl ) + '">Settings</a>' +
			scanBtn +
			'</div>';
	}

	function renderEmpty() {
		return '<div class="ajaco-card ajaco-empty">' +
			'<h3>' + esc( 'Is this site agent-ready?' ) + '</h3>' +
			'<p>' + esc( 'Run the 19-check readiness scan — the same checks Cloudflare’s isitagentready.com runs — against this site, with full request/response evidence and one-click fixes.' ) + '</p>' +
			( state.busyScan
				? '<button class="button button-primary button-hero" disabled><span class="ajaco-spin"></span>' + esc( D.i18n.scanning ) + '</button>'
				: '<button class="button button-primary button-hero" data-act="scan">' + esc( 'Run your first scan' ) + '</button>' ) +
			'</div>';
	}

	function renderGaugeCard() {
		var scan = state.scan;
		var chips = '';
		CATEGORY_ORDER.forEach( function ( c ) {
			var cat = scan.scores.categories[ c ];
			if ( ! cat ) {
				return;
			}
			var label = D.categoryLabels[ c ] || c;
			if ( 'commerce' === c ) {
				var commerceTxt = cat.total > 0 ? cat.passed + '/' + cat.total : esc( 'not checked' );
				chips += '<span class="ajaco-chip ajaco-chip-skip">' + esc( label ) + ' — ' + commerceTxt + '</span>';
				return;
			}
			var dot = 0 === cat.total ? 'var(--ajaco-track)' : scoreColor( cat.score );
			chips += '<span class="ajaco-chip"><i style="background:' + dot + '"></i>' + esc( label ) + ' ' + cat.passed + '/' + cat.total + '</span>';
		} );

		return '<div class="ajaco-card ajaco-gauge-card">' +
			'<div class="ajaco-gauge-wrap">' + gaugeSvg( scan ) +
			'<div class="ajaco-gauge-center">' +
			'<span class="ajaco-lvl" style="color:' + LEVEL_COLORS[ scan.level ] + '">Level ' + scan.level + '</span>' +
			'<span class="ajaco-lvl-name">' + esc( scan.levelName ) + '</span>' +
			'</div></div>' +
			'<p class="ajaco-meta">' + esc( scan.scores.passed + ' of ' + scan.scores.total + ' scored checks verified' ) + '</p>' +
			'<div class="ajaco-chips">' + chips + '</div>' +
			'</div>';
	}

	function renderNextLevel() {
		var next = state.scan.nextLevel;
		if ( ! next ) {
			return '<div class="ajaco-card ajaco-next"><p class="ajaco-next-target">' + esc( 'Top level' ) + '</p>' +
				'<h3>' + esc( 'Level 5 — ' + ( D.levelNames[ 5 ] || '' ) ) + '</h3>' +
				'<p class="ajaco-meta">' + esc( 'This site passes every ladder requirement. Keep an eye on spec churn — standards in this space move fast.' ) + '</p></div>';
		}
		var rows = '';
		next.requirements.forEach( function ( req ) {
			var meta = D.checkMeta[ req.check ] || {};
			var btn;
			if ( meta.fixable ) {
				btn = state.busyCheck[ req.check ]
					? '<button class="button button-primary button-small" disabled><span class="ajaco-spin"></span></button>'
					: '<button class="button button-primary button-small" data-act="fix" data-check="' + esc( req.check ) + '">' + esc( D.i18n.fixNow ) + '</button>';
			} else {
				btn = '<button class="button button-small" data-act="copy" data-check="' + esc( req.check ) + '">' + esc( D.i18n.copyPrompt ) + '</button>';
			}
			rows += '<div class="ajaco-req"><span class="ajaco-req-name">' + esc( meta.name || req.check ) +
				'<small>' + esc( req.description || '' ) + '</small></span>' + btn + '</div>';
		} );
		return '<div class="ajaco-card ajaco-next">' +
			'<p class="ajaco-next-target">' + esc( 'Next level' ) + '</p>' +
			'<h3>Level ' + next.target + ' — ' + esc( next.name ) + '</h3>' +
			( next.note ? '<p class="ajaco-meta">' + esc( next.note ) + '</p>' : '' ) +
			rows + '</div>';
	}

	function renderCategories() {
		var html = '';
		CATEGORY_ORDER.forEach( function ( cat ) {
			var checks = state.scan.checks[ cat ] || {};
			var ids = Object.keys( checks );
			if ( ! ids.length ) {
				return;
			}
			// pass → neutral → fail ordering (fails auto-expanded at the bottom
			// of the eye path? No — external scanner sorts pass first; we sort
			// fail-first so work is on top).
			var order = { fail: 0, unableToCheck: 1, neutral: 2, pass: 3 };
			ids.sort( function ( a, b ) {
				return ( order[ checks[ a ].status ] || 2 ) - ( order[ checks[ b ].status ] || 2 );
			} );

			var scoreInfo = state.scan.scores.categories[ cat ];
			var scoreTxt = scoreInfo ? scoreInfo.passed + '/' + scoreInfo.total : '';
			var optional = 'commerce' === cat ? '<span class="ajaco-opt">' + esc( 'Optional' ) + '</span>' : '';

			html += '<section class="ajaco-cat"><div class="ajaco-cat-head"><h3>' +
				esc( D.categoryLabels[ cat ] || cat ) + optional + '</h3>' +
				( scoreInfo && scoreInfo.total > 0 ? '<span class="ajaco-cat-score" style="color:' + scoreColor( scoreInfo.score ) + '">' + scoreTxt + '</span>' : '' ) +
				'</div>';

			if ( 'commerce' === cat ) {
				html += '<p class="ajaco-meta ajaco-commerce-note">' + esc( state.scan.isCommerce
					? 'Commerce protocols are emerging standards — informational, never counted in the score.'
					: 'No e-commerce signals detected on this site. Shown for information only; does not affect the score.' ) + '</p>';
			}

			ids.forEach( function ( id ) {
				html += renderCheck( id, checks[ id ] );
			} );
			html += '</section>';
		} );
		return html;
	}

	function renderCheck( id, result ) {
		var meta = D.checkMeta[ id ] || {};
		var status = STATUS_META[ result.status ] || STATUS_META.neutral;
		var isOpen = ( id in state.openCards ) ? state.openCards[ id ] : 'fail' === result.status;
		var flash = state.flash[ id ] ? ' ajaco-flash-' + state.flash[ id ] : '';
		var busy = state.busyCheck[ id ];

		var body = '';
		if ( isOpen ) {
			var actions = '';
			if ( 'pass' !== result.status ) {
				if ( meta.fixable ) {
					actions += busy
						? '<button class="button button-primary button-small" disabled><span class="ajaco-spin"></span>' + esc( 'fixing' === busy ? D.i18n.fixing : D.i18n.verifying ) + '</button>'
						: '<button class="button button-primary button-small" data-act="fix" data-check="' + esc( id ) + '">' + esc( D.i18n.fixNow ) + '</button>';
				}
				actions += '<button class="button button-small" data-act="copy" data-check="' + esc( id ) + '">' + esc( D.i18n.copyPrompt ) + '</button>';
			}
			if ( result.evidence && result.evidence.length ) {
				actions += '<button class="button-link ajaco-ev-btn" data-act="evidence" data-check="' + esc( id ) + '">' +
					esc( state.openEvidence[ id ] ? D.i18n.hideDetails : D.i18n.evidence ) + '</button>';
			}

			var rows = '';
			if ( meta.description ) {
				rows += '<div class="ajaco-kv"><dt>' + esc( 'Goal' ) + '</dt><dd>' + esc( meta.description ) + '</dd></div>';
			}
			rows += '<div class="ajaco-kv"><dt>' + esc( 'pass' === result.status ? 'Result' : ( 'fail' === result.status ? 'Issue' : 'Note' ) ) + '</dt>' +
				'<dd class="ajaco-kv-' + status.cls + '">' + esc( result.message ) + '</dd></div>';
			if ( meta.fixable && 'pass' !== result.status && meta.fixNote ) {
				rows += '<div class="ajaco-kv"><dt>' + esc( 'Fix' ) + '</dt><dd>' + esc( meta.fixNote ) + '</dd></div>';
			}

			body = '<div class="ajaco-check-body"><dl>' + rows + '</dl>' +
				'<div class="ajaco-actions">' + actions + '</div>' +
				( state.openEvidence[ id ] ? renderEvidence( result ) : '' ) +
				'</div>';
		}

		return '<div class="ajaco-check ajaco-check-' + status.cls + flash + '" data-id="' + esc( id ) + '">' +
			'<button class="ajaco-check-row" data-act="toggle" data-check="' + esc( id ) + '" aria-expanded="' + ( isOpen ? 'true' : 'false' ) + '">' +
			'<span class="ajaco-sicon ajaco-sicon-' + status.cls + '" title="' + esc( status.label ) + '"></span>' +
			'<span class="ajaco-check-name">' + esc( meta.name || id ) + '</span>' +
			( isOpen ? '' : '<span class="ajaco-check-msg">' + esc( result.message ) + '</span>' ) +
			'<span class="ajaco-chevron" aria-hidden="true"></span>' +
			'</button>' + body + '</div>';
	}

	function renderEvidence( result ) {
		var steps = '';
		( result.evidence || [] ).forEach( function ( step ) {
			var badge = '';
			if ( step.response && step.response.status ) {
				var code = step.response.status;
				var cls = code < 300 ? 'ok' : ( code < 400 ? 'redir' : 'err' );
				badge = '<span class="ajaco-http ajaco-http-' + cls + '">' + code + '</span>';
			}
			var finding = '';
			if ( step.finding ) {
				finding = '<span class="ajaco-finding ajaco-finding-' + esc( step.finding.outcome ) + '">' + esc( step.finding.summary ) + '</span>';
			}
			steps += '<div class="ajaco-ev-step">' +
				'<span class="ajaco-ev-act ajaco-ev-' + esc( step.action ) + '">' + esc( step.action ) + '</span>' +
				'<span class="ajaco-ev-label">' + esc( step.label ) + finding + '</span>' +
				badge + '</div>';
		} );
		var dur = 'number' === typeof result.durationMs ? '<p class="ajaco-meta">' + esc( 'Completed in ' + result.durationMs + ' ms' ) + '</p>' : '';
		return '<div class="ajaco-evidence">' + steps + dur + '</div>';
	}

	function renderFab() {
		var failing = failingChecks();
		if ( ! failing.length ) {
			return '';
		}
		return '<button class="ajaco-fab" data-act="sheet">' + esc( 'Improve readiness' ) +
			'<span class="ajaco-fab-count">' + failing.length + '</span></button>';
	}

	function renderSheet() {
		var failing = failingChecks();
		var fixable = failing.filter( function ( f ) {
			return ( D.checkMeta[ f.id ] || {} ).fixable;
		} );
		var items = '';
		failing.forEach( function ( f ) {
			var meta = D.checkMeta[ f.id ] || {};
			items += '<li>' + esc( meta.name || f.id ) + ( meta.fixable ? ' <em>' + esc( '(one-click fix)' ) + '</em>' : '' ) + '</li>';
		} );
		return '<div class="ajaco-sheet-backdrop" data-act="sheet-close"></div>' +
			'<div class="ajaco-sheet" role="dialog" aria-label="' + esc( 'Improve readiness' ) + '">' +
			'<div class="ajaco-sheet-head"><strong>' + failing.length + esc( ' issue' + ( 1 === failing.length ? '' : 's' ) + ' found' ) + '</strong>' +
			'<button class="button-link" data-act="sheet-close">✕</button></div>' +
			'<ul class="ajaco-sheet-list">' + items + '</ul>' +
			'<div class="ajaco-sheet-actions">' +
			( fixable.length ? '<button class="button button-primary" data-act="fix-all">' + esc( 'Fix all safe items (' + fixable.length + ')' ) + '</button>' : '' ) +
			'<button class="button" data-act="copy-all">' + esc( 'Copy all agent prompts' ) + '</button>' +
			'</div>' +
			'<p class="ajaco-meta">' + esc( 'Copied prompts paste into any coding agent (Cursor, Claude Code, Windsurf, Copilot). Fixes needing DNS or server access always go the prompt route.' ) + '</p>' +
			'</div>';
	}

	/* ------------------------------------------------------------------ *
	 * Actions
	 * ------------------------------------------------------------------ */

	function runScan() {
		state.busyScan = true;
		state.error = '';
		render();
		api( 'POST', '/scan' ).then( function ( json ) {
			state.scan = json.scan;
			state.openCards = {};
			state.openEvidence = {};
		} ).catch( function ( err ) {
			state.error = err.message;
		} ).then( function () {
			state.busyScan = false;
			render();
		} );
	}

	function fixCheck( id ) {
		if ( state.busyScan || state.busyCheck[ id ] ) {
			return;
		}
		state.busyCheck[ id ] = 'fixing';
		state.error = '';
		render();
		api( 'POST', '/fix', { check: id } ).then( function ( json ) {
			if ( json.scan ) {
				state.scan = json.scan;
			}
			var passed = json.check && 'pass' === json.check.status;
			state.flash[ id ] = passed ? 'pass' : 'fail';
			state.openCards[ id ] = ! passed;
			window.setTimeout( function () {
				delete state.flash[ id ];
				render();
			}, 1600 );
		} ).catch( function ( err ) {
			state.error = err.message;
		} ).then( function () {
			delete state.busyCheck[ id ];
			render();
		} );
	}

	function fixAll() {
		if ( state.busyScan ) {
			return;
		}
		var fixable = failingChecks().filter( function ( f ) {
			return ( D.checkMeta[ f.id ] || {} ).fixable;
		} );
		state.sheetOpen = false;
		// Mark everything busy up front and render immediately — the sheet
		// must close now, and no other fix/scan button may start a
		// concurrent chain.
		state.busyScan = true;
		fixable.forEach( function ( f ) {
			state.busyCheck[ f.id ] = 'fixing';
		} );
		render();

		var chain = Promise.resolve();
		fixable.forEach( function ( f ) {
			chain = chain.then( function () {
				return api( 'POST', '/fix', { check: f.id } ).then( function ( json ) {
					if ( json.scan ) {
						state.scan = json.scan;
					}
				} ).catch( function ( err ) {
					state.error = err.message;
				} ).then( function () {
					delete state.busyCheck[ f.id ];
					render();
				} );
			} );
		} );
		chain.then( function () {
			state.busyScan = false;
			render();
		} );
	}

	function copyText( text, btn ) {
		var done = function () {
			if ( btn ) {
				var prev = btn.textContent;
				btn.textContent = D.i18n.copied;
				window.setTimeout( function () {
					btn.textContent = prev;
				}, 1500 );
			}
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		document.body.appendChild( ta );
		ta.select();
		try {
			document.execCommand( 'copy' );
		} catch ( e ) {
			// Ignore — nothing else we can do without clipboard access.
		}
		document.body.removeChild( ta );
		done();
	}

	function findResult( id ) {
		var found = null;
		CATEGORY_ORDER.forEach( function ( cat ) {
			var checks = ( state.scan && state.scan.checks[ cat ] ) || {};
			if ( checks[ id ] ) {
				found = checks[ id ];
			}
		} );
		return found;
	}

	function bind() {
		root.querySelectorAll( '[data-act]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				var act = el.getAttribute( 'data-act' );
				var id = el.getAttribute( 'data-check' );
				switch ( act ) {
					case 'scan':
						runScan();
						break;
					case 'fix':
						fixCheck( id );
						break;
					case 'copy':
						copyText( buildPrompt( id, findResult( id ) ), el );
						break;
					case 'toggle':
						state.openCards[ id ] = ! ( ( id in state.openCards ) ? state.openCards[ id ] : 'fail' === ( findResult( id ) || {} ).status );
						render();
						break;
					case 'evidence':
						state.openEvidence[ id ] = ! state.openEvidence[ id ];
						render();
						break;
					case 'sheet':
						state.sheetOpen = true;
						render();
						break;
					case 'sheet-close':
						state.sheetOpen = false;
						render();
						break;
					case 'fix-all':
						fixAll();
						break;
					case 'copy-all':
						copyText( failingChecks().map( function ( f ) {
							return buildPrompt( f.id, f.result );
						} ).join( '\n\n---\n\n' ), el );
						break;
				}
			} );
		} );
	}

	render();
}() );
