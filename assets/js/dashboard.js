/**
 * Agent Ready dashboard app.
 *
 * Vanilla JS over the ajaco/v1 REST API: run scans, render the 0-100 score +
 * grade, the Level 0-5 ladder, per-layer bars, and evidence-backed check
 * cards ranked by impact; apply one-click fixes and re-verify the fixed check
 * inline (scan → fix → prove).
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
		history: D.history || [],
		busyScan: false,
		busyCheck: {}, // checkId => 'fixing'|'verifying'
		openEvidence: {}, // checkId => bool
		openCards: {}, // checkId => bool (manual overrides)
		flash: {}, // checkId => 'pass'|'fail' one-shot animation
		sheetOpen: false,
		filter: 'all', // all | fail | fix | bonus
		external: null, // null | { loading: true } | REST payload
		error: ''
	};

	var STATUS_META = {
		pass: { label: D.i18n.pass, cls: 'pass' },
		fail: { label: D.i18n.fail, cls: 'fail' },
		neutral: { label: D.i18n.neutral, cls: 'neutral' },
		unableToCheck: { label: D.i18n.unable, cls: 'neutral' }
	};

	var LEVEL_COLORS = [ '#d63638', '#d97706', '#dba617', '#2271b1', '#00a32a', '#008a20' ];

	var GRADE_COLORS = { 'A+': '#008a20', A: '#00a32a', B: '#2271b1', C: '#dba617', D: '#d97706', F: '#d63638' };

	var CATEGORY_ORDER = [ 'discoverability', 'contentAccessibility', 'botAccessControl', 'discovery', 'commerce' ];

	function esc( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	// Localized UI strings come from the AjacoDash.i18n payload (built with
	// __()/_n() in includes/admin/enqueue.php, so they land in the .pot).
	var t = D.i18n || {};

	// Minimal printf supporting both sequential (%s, %d) and numbered
	// (%1$s, %2$d) placeholders — WordPress uses numbered args for strings
	// with more than one substitution so translators can reorder them.
	function fmt( template ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( template == null ? '' : template ).replace( /%(\d+\$)?[sd]/g, function ( match, num ) {
			var idx = num ? parseInt( num, 10 ) - 1 : i++;
			return idx < args.length && null != args[ idx ] ? args[ idx ] : '';
		} );
	}

	// Pick singular/plural from an "singular|plural" i18n string by count.
	function plural( template, n ) {
		var parts = String( template == null ? '' : template ).split( '|' );
		return ( 1 === n || parts.length < 2 ) ? parts[ 0 ] : parts[ 1 ];
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
						throw new Error( t.sessionExpired );
					}
					throw new Error( json && json.message ? json.message : fmt( t.requestFailed, res.status ) );
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

	function gradeColor( grade ) {
		return GRADE_COLORS[ grade ] || '#8c8f94';
	}

	/* ------------------------------------------------------------------ *
	 * Score helpers
	 * ------------------------------------------------------------------ */

	// Points a check adds to the 0-100 score (its weight scaled to the
	// current denominator); bonus checks report their raw bonus points.
	function impact( id ) {
		var meta = D.checkMeta[ id ] || {};
		var pts = meta.points || 0;
		if ( meta.bonus ) {
			return pts;
		}
		var available = state.scan && state.scan.score ? state.scan.score.available : 0;
		return available > 0 ? Math.round( ( pts / available ) * 100 ) : pts;
	}

	// The full scan to compare the live score against. History records full
	// scans only; after a fix the live scan carries a newer timestamp than
	// the last entry, so that entry is the baseline. Right after a full scan
	// the last entry IS the live scan, so the one before it is.
	function previousEntry() {
		var h = state.history || [];
		if ( ! h.length ) {
			return null;
		}
		var last = h[ h.length - 1 ];
		if ( state.scan && last.scannedAt === state.scan.scannedAt ) {
			return h.length >= 2 ? h[ h.length - 2 ] : null;
		}
		return last;
	}

	function sparklineSvg() {
		var h = ( state.history || [] ).slice( -12 );
		if ( h.length < 2 ) {
			return '';
		}
		var w = 140;
		var ht = 36;
		var pts = h.map( function ( e, i ) {
			var x = ( i / ( h.length - 1 ) ) * ( w - 6 ) + 3;
			var y = ht - 3 - ( Math.max( 0, Math.min( 100, e.score || 0 ) ) / 100 ) * ( ht - 6 );
			return x.toFixed( 1 ) + ',' + y.toFixed( 1 );
		} );
		var last = pts[ pts.length - 1 ].split( ',' );
		return '<svg class="ajaco-spark" width="' + w + '" height="' + ht + '" viewBox="0 0 ' + w + ' ' + ht + '" aria-hidden="true">' +
			'<polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points="' + pts.join( ' ' ) + '"/>' +
			'<circle cx="' + last[ 0 ] + '" cy="' + last[ 1 ] + '" r="3" fill="currentColor"/>' +
			'</svg>';
	}

	/* ------------------------------------------------------------------ *
	 * Score ring
	 * ------------------------------------------------------------------ */

	function ringSvg( score, color, label ) {
		var r = 62;
		var c = 2 * Math.PI * r;
		var filled = Math.max( 0, Math.min( 100, score ) ) / 100 * c;
		return '<svg width="150" height="150" viewBox="0 0 150 150" role="img" aria-label="' + esc( label ) + '">' +
			'<circle cx="75" cy="75" r="' + r + '" fill="none" stroke="var(--ajaco-track)" stroke-width="12"/>' +
			'<circle cx="75" cy="75" r="' + r + '" fill="none" stroke="' + color + '" stroke-width="12" stroke-linecap="round"' +
			' stroke-dasharray="' + filled.toFixed( 2 ) + ' ' + c.toFixed( 2 ) + '" transform="rotate(-90 75 75)" class="ajaco-ring-fill"/>' +
			'</svg>';
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

	// Failing checks, highest impact first, one-click fixes before prompts.
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
		out.sort( function ( a, b ) {
			var fa = ( D.checkMeta[ a.id ] || {} ).fixable ? 0 : 1;
			var fb = ( D.checkMeta[ b.id ] || {} ).fixable ? 0 : 1;
			if ( fa !== fb ) {
				return fa - fb;
			}
			return impact( b.id ) - impact( a.id );
		} );
		return out;
	}

	function matchesFilter( id, result ) {
		var meta = D.checkMeta[ id ] || {};
		switch ( state.filter ) {
			case 'fail':
				return 'fail' === result.status;
			case 'fix':
				return 'pass' !== result.status && !! meta.fixable;
			case 'bonus':
				return !! meta.bonus;
			default:
				return true;
		}
	}

	function filterCounts() {
		var counts = { all: 0, fail: 0, fix: 0, bonus: 0 };
		if ( ! state.scan ) {
			return counts;
		}
		CATEGORY_ORDER.forEach( function ( cat ) {
			var checks = state.scan.checks[ cat ] || {};
			Object.keys( checks ).forEach( function ( id ) {
				var meta = D.checkMeta[ id ] || {};
				var r = checks[ id ];
				counts.all++;
				if ( 'fail' === r.status ) {
					counts.fail++;
				}
				if ( 'pass' !== r.status && meta.fixable ) {
					counts.fix++;
				}
				if ( meta.bonus ) {
					counts.bonus++;
				}
			} );
		} );
		return counts;
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
			html += '<div class="ajaco-hero-row">' + renderScoreCard() + renderLayers() + renderNextLevel() + '</div>';
			html += renderHosting();
			html += renderShare();
			html += renderFilters();
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
			'<h3>' + esc( t.emptyTitle ) + '</h3>' +
			'<p>' + esc( D.i18n.emptyBody ) + '</p>' +
			( state.busyScan
				? '<button class="button button-primary button-hero" disabled><span class="ajaco-spin"></span>' + esc( D.i18n.scanning ) + '</button>'
				: '<button class="button button-primary button-hero" data-act="scan">' + esc( t.runFirst ) + '</button>' ) +
			'</div>';
	}

	function renderScoreCard() {
		var scan = state.scan;
		var s = scan.score;
		var levelChip = '<span class="ajaco-level-chip" style="background:' + LEVEL_COLORS[ scan.level ] + '">Level ' + scan.level + '</span>' +
			'<span class="ajaco-lvl-name">' + esc( scan.levelName ) + '</span>';

		// Scans stored before the score model existed (or with no scored
		// checks) get an empty ring and a nudge, not a bogus zero.
		if ( ! s || ! s.grade ) {
			return '<div class="ajaco-card ajaco-score-card">' +
				'<p class="ajaco-next-target">' + esc( t.scoreTitle ) + '</p>' +
				'<div class="ajaco-ring-wrap">' + ringSvg( 0, 'var(--ajaco-track)', t.scoreTitle ) +
				'<div class="ajaco-ring-center"><span class="ajaco-score-num ajaco-score-none">—</span></div></div>' +
				'<p class="ajaco-meta">' + esc( s ? t.noScore : t.rescanScore ) + '</p>' +
				'<div class="ajaco-level-row">' + levelChip + '</div>' +
				'</div>';
		}

		var color = gradeColor( s.grade );
		var prev = previousEntry();
		var trend;
		if ( ! prev ) {
			trend = '<span class="ajaco-trend ajaco-trend-flat">' + esc( t.firstScan ) + '</span>';
		} else {
			var delta = s.score - ( prev.score || 0 );
			if ( 0 === delta ) {
				trend = '<span class="ajaco-trend ajaco-trend-flat">' + esc( t.noChange ) + '</span>';
			} else {
				trend = '<span class="ajaco-trend ' + ( delta > 0 ? 'ajaco-trend-up' : 'ajaco-trend-down' ) + '">' +
					esc( fmt( t.sinceLast, ( delta > 0 ? '+' : '−' ) + Math.abs( delta ) ) ) + '</span>';
			}
		}
		var bonus = s.bonus > 0 ? '<span class="ajaco-bonus-chip">' + esc( fmt( t.bonusPts, Math.round( s.bonus ) ) ) + '</span>' : '';

		return '<div class="ajaco-card ajaco-score-card">' +
			'<p class="ajaco-next-target">' + esc( t.scoreTitle ) + '</p>' +
			'<div class="ajaco-ring-wrap">' + ringSvg( s.score, color, fmt( t.scoreOf100, s.score, s.grade ) ) +
			'<div class="ajaco-ring-center">' +
			'<span class="ajaco-score-num">' + s.score + '</span>' +
			'<span class="ajaco-score-grade" style="color:' + color + '">' + esc( s.grade ) + '</span>' +
			'</div></div>' +
			'<p class="ajaco-grade-label" style="color:' + color + '">' + esc( s.gradeLabel ) + bonus + '</p>' +
			'<div class="ajaco-level-row">' + levelChip + '</div>' +
			'<div class="ajaco-trend-row">' + trend + sparklineSvg() + '</div>' +
			'</div>';
	}

	function renderLayers() {
		var scan = state.scan;
		var layers = ( scan.score && scan.score.layers ) || {};
		var rows = '';
		CATEGORY_ORDER.forEach( function ( c ) {
			var layer = layers[ c ];
			var label = D.categoryLabels[ c ] || c;
			if ( ! layer ) {
				return;
			}
			var right;
			var fill = 0;
			var color = 'var(--ajaco-track)';
			if ( layer.available > 0 ) {
				fill = ( layer.earned / layer.available ) * 100;
				color = scoreColor( layer.score );
				right = fmt( t.ptsOf, Math.round( layer.earned ), Math.round( layer.available ) );
			} else if ( layer.bonusMax > 0 ) {
				fill = layer.bonusEarned > 0 ? ( layer.bonusEarned / layer.bonusMax ) * 100 : 0;
				color = layer.bonusEarned > 0 ? '#2271b1' : 'var(--ajaco-track)';
				right = fmt( t.bonusPts, Math.round( layer.bonusEarned ) );
			} else {
				right = t.layerNa;
			}
			var extra = '';
			if ( layer.available > 0 && layer.bonusEarned > 0 ) {
				extra = ' <span class="ajaco-layer-bonus">' + esc( fmt( t.bonusPts, Math.round( layer.bonusEarned ) ) ) + '</span>';
			}
			rows += '<div class="ajaco-layer">' +
				'<div class="ajaco-layer-head"><span class="ajaco-layer-name">' + esc( label ) + '</span>' +
				'<span class="ajaco-layer-pts">' + esc( right ) + extra + '</span></div>' +
				'<div class="ajaco-bar"><div class="ajaco-bar-fill" style="width:' + fill.toFixed( 1 ) + '%;background:' + color + '"></div></div>' +
				'</div>';
		} );
		return '<div class="ajaco-card ajaco-layers">' +
			'<p class="ajaco-next-target">' + esc( t.layersTitle ) + '</p>' +
			rows +
			'<p class="ajaco-meta">' + esc( scan.scores.passed + ' of ' + scan.scores.total + ' scored checks verified' ) + '</p>' +
			'</div>';
	}

	function renderNextLevel() {
		var next = state.scan.nextLevel;
		if ( ! next ) {
			return '<div class="ajaco-card ajaco-next"><p class="ajaco-next-target">' + esc( t.topLevel ) + '</p>' +
				'<h3>' + esc( fmt( t.levelDashName, 5, ( D.levelNames[ 5 ] || '' ) ) ) + '</h3>' +
				'<p class="ajaco-meta">' + esc( t.topLevelBody ) + '</p></div>';
		}
		var rows = '';
		next.requirements.forEach( function ( req ) {
			var meta = D.checkMeta[ req.check ] || {};
			var btn;
			if ( meta.fixable ) {
				btn = state.busyCheck[ req.check ]
					? '<button class="button button-primary button-small" disabled><span class="ajaco-spin"></span></button>'
					: '<button class="button button-primary button-small" data-act="fix" data-check="' + esc( req.check ) + '">' + esc( D.i18n.fixNow ) + '</button>';
			} else if ( meta.fixLink ) {
				btn = '<a class="button button-primary button-small" href="' + esc( meta.fixLink.url ) + '">' + esc( meta.fixLink.label ) + '</a>';
			} else {
				btn = '<button class="button button-small" data-act="copy" data-check="' + esc( req.check ) + '">' + esc( D.i18n.copyPrompt ) + '</button>';
			}
			rows += '<div class="ajaco-req"><span class="ajaco-req-name">' + esc( meta.name || req.check ) +
				'<small>' + esc( req.description || '' ) + '</small></span>' + btn + '</div>';
		} );
		return '<div class="ajaco-card ajaco-next">' +
			'<p class="ajaco-next-target">' + esc( t.nextLevel ) + '</p>' +
			'<h3>Level ' + next.target + ' — ' + esc( next.name ) + '</h3>' +
			( next.note ? '<p class="ajaco-meta">' + esc( next.note ) + '</p>' : '' ) +
			rows + '</div>';
	}

	function renderHosting() {
		var hosting = state.scan.hosting;
		if ( ! hosting || ! hosting.issues || ! hosting.issues.length ) {
			return '';
		}
		var items = '';
		hosting.issues.forEach( function ( issue ) {
			items += '<li>' + esc( issue.summary ) + '</li>';
		} );
		return '<div class="ajaco-card ajaco-hosting">' +
			'<h3>' + esc( fmt( plural( t.hostingTitle, hosting.issues.length ), hosting.issues.length ) ) + '</h3>' +
			'<p class="ajaco-meta">' + esc( t.hostingBody ) + '</p>' +
			'<ul class="ajaco-hosting-list">' + items + '</ul>' +
			'<div class="ajaco-actions">' +
			'<button class="button button-small" data-act="copy-snippet" data-snippet="nginx">' + esc( t.copyNginx ) + '</button>' +
			'<button class="button button-small" data-act="copy-snippet" data-snippet="apache">' + esc( t.copyApache ) + '</button>' +
			'</div></div>';
	}

	function isLocalHost( host ) {
		return /(^localhost$|\.local$|\.test$|\.localhost$|^127\.|^10\.|^192\.168\.)/i.test( host || '' );
	}

	// Only ever render https links taken from an external payload.
	function safeHttps( url ) {
		return /^https:\/\//i.test( url || '' ) ? url : '';
	}

	function renderShare() {
		var scan = state.scan;
		var badgeSrc = D.badgeUrl + '?v=' + encodeURIComponent( scan.scannedAt || '' );
		var embed = '<a href="' + D.homeUrl + '"><img src="' + D.badgeUrl + '" width="236" height="76" alt="' + t.badgeAlt + '"></a>';

		var badge = '<div class="ajaco-card ajaco-share-badge">' +
			'<p class="ajaco-next-target">' + esc( t.badgeTitle ) + '</p>' +
			'<img class="ajaco-badge-img" src="' + esc( badgeSrc ) + '" width="236" height="76" alt="' + esc( t.badgeAlt ) + '">' +
			'<p class="ajaco-meta">' + esc( t.badgeBody ) + '</p>' +
			'<div class="ajaco-actions">' +
			'<button class="button button-small" data-act="copy-text" data-text="[agent_ready_badge]">' + esc( t.copyShortcode ) + '</button>' +
			( D.badgePublic
				? '<button class="button button-small" data-act="copy-text" data-text="' + esc( embed ) + '">' + esc( t.copyEmbed ) + '</button>'
				: '' ) +
			'</div>' +
			( D.badgePublic ? '' : '<p class="ajaco-meta">' + esc( t.badgePrivate ) + ' <a href="' + esc( D.settingsUrl ) + '">Settings</a></p>' ) +
			'</div>';

		var ext = state.external;
		var rows = '';
		var btn;
		if ( ext && ext.loading ) {
			btn = '<button class="button button-small" disabled><span class="ajaco-spin"></span>' + esc( t.externalChecking ) + '</button>';
		} else {
			btn = '<button class="button button-small' + ( ext ? '' : ' button-primary' ) + '" data-act="external">' + esc( ext ? t.externalRefresh : t.externalCheck ) + '</button>';
		}
		if ( ext && ext.providers ) {
			[ 'ora', 'isAgentic' ].forEach( function ( key ) {
				var p = ext.providers[ key ];
				if ( ! p ) {
					return;
				}
				var result;
				var link;
				if ( 'ok' === p.status ) {
					result = '<span class="ajaco-ext-score">' + p.score + '<small>/100</small></span>' +
						( p.grade ? '<span class="ajaco-ext-grade" style="color:' + gradeColor( p.grade ) + '">' + esc( p.grade ) + '</span>' : '' ) +
						( p.label ? '<span class="ajaco-meta">' + esc( p.label ) + '</span>' : '' );
					link = safeHttps( p.reportUrl )
						? '<a class="button button-small" href="' + esc( p.reportUrl ) + '" target="_blank" rel="noopener">' + esc( t.externalOpen ) + ' ↗</a>'
						: '';
				} else if ( 'none' === p.status ) {
					result = '<span class="ajaco-meta">' + esc( t.externalNone ) + '</span>';
					link = safeHttps( p.scanUrl )
						? '<a class="button button-small" href="' + esc( p.scanUrl ) + '" target="_blank" rel="noopener">' + esc( fmt( t.externalScanOn, p.name ) ) + ' ↗</a>'
						: '';
				} else {
					result = '<span class="ajaco-meta ajaco-kv-fail">' + esc( t.externalError + ' ' + ( p.message || '' ) ) + '</span>';
					link = '';
				}
				rows += '<div class="ajaco-ext-row"><span class="ajaco-ext-name">' + esc( p.name ) + '</span>' +
					'<span class="ajaco-ext-result">' + result + '</span>' + link + '</div>';
			} );
			if ( ext.checkedAt ) {
				rows += '<p class="ajaco-meta">' + esc( fmt( t.externalChecked, new Date( ext.checkedAt ).toLocaleString() ) ) +
					( ext.host && isLocalHost( ext.host ) ? ' · ' + esc( t.externalLocal ) : '' ) + '</p>';
			}
		}

		var external = '<div class="ajaco-card ajaco-share-ext">' +
			'<p class="ajaco-next-target">' + esc( t.externalTitle ) + '</p>' +
			'<p class="ajaco-meta">' + esc( t.externalBody ) + '</p>' +
			rows +
			'<div class="ajaco-actions">' + btn + '</div>' +
			'</div>';

		return '<div class="ajaco-share-row">' + badge + external + '</div>';
	}

	function checkExternal() {
		var refresh = !! ( state.external && state.external.providers );
		state.external = { loading: true };
		render();
		api( 'GET', '/external' + ( refresh ? '?refresh=1' : '' ) ).then( function ( json ) {
			state.external = json;
		} ).catch( function ( err ) {
			state.external = null;
			state.error = err.message;
		} ).then( render );
	}

	function renderFilters() {
		var counts = filterCounts();
		var defs = [
			{ key: 'all', label: t.filterAll, n: counts.all },
			{ key: 'fail', label: t.filterFail, n: counts.fail },
			{ key: 'fix', label: t.filterFix, n: counts.fix },
			{ key: 'bonus', label: t.filterBonus, n: counts.bonus }
		];
		var btns = '';
		defs.forEach( function ( d ) {
			btns += '<button class="ajaco-filter' + ( state.filter === d.key ? ' is-active' : '' ) + '" data-act="filter" data-filter="' + d.key + '"' +
				' aria-pressed="' + ( state.filter === d.key ? 'true' : 'false' ) + '">' + esc( d.label ) +
				'<span class="ajaco-filter-n">' + d.n + '</span></button>';
		} );
		return '<div class="ajaco-filters" role="group">' + btns +
			'<span class="ajaco-grow"></span><span class="ajaco-meta">' + esc( t.sortedImpact ) + '</span></div>';
	}

	function renderCategories() {
		var html = '';
		var any = false;
		var order = { fail: 0, unableToCheck: 1, neutral: 2, pass: 3 };
		CATEGORY_ORDER.forEach( function ( cat ) {
			var checks = state.scan.checks[ cat ] || {};
			var ids = Object.keys( checks ).filter( function ( id ) {
				return matchesFilter( id, checks[ id ] );
			} );
			if ( ! ids.length ) {
				return;
			}
			any = true;
			// Work on top: failing first, highest impact first within each
			// status band (the external scanners' "ranked by impact").
			ids.sort( function ( a, b ) {
				var oa = order[ checks[ a ].status ];
				var ob = order[ checks[ b ].status ];
				oa = null == oa ? 2 : oa;
				ob = null == ob ? 2 : ob;
				if ( oa !== ob ) {
					return oa - ob;
				}
				return impact( b ) - impact( a );
			} );

			var scoreInfo = state.scan.scores.categories[ cat ];
			var scoreTxt = scoreInfo ? scoreInfo.passed + '/' + scoreInfo.total : '';
			var optional = 'commerce' === cat ? '<span class="ajaco-opt">' + esc( t.optional ) + '</span>' : '';

			html += '<section class="ajaco-cat"><div class="ajaco-cat-head"><h3>' +
				esc( D.categoryLabels[ cat ] || cat ) + optional + '</h3>' +
				( scoreInfo && scoreInfo.total > 0 ? '<span class="ajaco-cat-score" style="color:' + scoreColor( scoreInfo.score ) + '">' + scoreTxt + '</span>' : '' ) +
				'</div>';

			if ( 'commerce' === cat ) {
				html += '<p class="ajaco-meta ajaco-commerce-note">' + esc( state.scan.isCommerce
					? t.commerceNote
					: t.commerceNoteNone ) + '</p>';
			}

			ids.forEach( function ( id ) {
				html += renderCheck( id, checks[ id ] );
			} );
			html += '</section>';
		} );
		if ( ! any ) {
			html += '<div class="ajaco-card ajaco-filter-empty"><p class="ajaco-meta">' + esc( t.filterEmpty ) + '</p></div>';
		}
		return html;
	}

	function pointsChip( id, result ) {
		var meta = D.checkMeta[ id ] || {};
		if ( meta.bonus ) {
			if ( 'pass' === result.status ) {
				return '<span class="ajaco-pts ajaco-pts-bonus">' + esc( fmt( t.bonusPts, meta.points ) ) + '</span>';
			}
			return '<span class="ajaco-pts ajaco-pts-muted">' + esc( t.bonusTag ) + '</span>';
		}
		// Chips show the raw weight (same unit as the layer bars); impact()
		// only drives sort order.
		var n = meta.points || 0;
		if ( 'fail' === result.status ) {
			return '<span class="ajaco-pts ajaco-pts-gain">' + esc( fmt( t.plusPts, n ) ) + '</span>';
		}
		if ( 'pass' === result.status ) {
			return '<span class="ajaco-pts ajaco-pts-muted">' + esc( fmt( t.pts, n ) ) + '</span>';
		}
		return '';
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
				} else if ( meta.fixLink ) {
					actions += '<a class="button button-primary button-small" href="' + esc( meta.fixLink.url ) + '">' + esc( meta.fixLink.label ) + '</a>';
				}
				actions += '<button class="button button-small" data-act="copy" data-check="' + esc( id ) + '">' + esc( D.i18n.copyPrompt ) + '</button>';
			}
			if ( result.evidence && result.evidence.length ) {
				actions += '<button class="button-link ajaco-ev-btn" data-act="evidence" data-check="' + esc( id ) + '">' +
					esc( state.openEvidence[ id ] ? D.i18n.hideDetails : D.i18n.evidence ) + '</button>';
			}

			var rows = '';
			if ( meta.description ) {
				rows += '<div class="ajaco-kv"><dt>' + esc( t.goalLabel ) + '</dt><dd>' + esc( meta.description ) + '</dd></div>';
			}
			var resultLabel = 'pass' === result.status ? t.resultLabel : ( 'fail' === result.status ? t.issueLabel : t.noteLabel );
			rows += '<div class="ajaco-kv"><dt>' + esc( resultLabel ) + '</dt>' +
				'<dd class="ajaco-kv-' + status.cls + '">' + esc( result.message ) + '</dd></div>';
			if ( meta.fixable && 'pass' !== result.status && meta.fixNote ) {
				rows += '<div class="ajaco-kv"><dt>' + esc( t.fixLabel ) + '</dt><dd>' + esc( meta.fixNote ) + '</dd></div>';
			} else if ( meta.fixLink && 'pass' !== result.status ) {
				rows += '<div class="ajaco-kv"><dt>' + esc( t.fixLabel ) + '</dt><dd>' + esc( meta.fixLink.note ) + '</dd></div>';
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
			pointsChip( id, result ) +
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
		var dur = 'number' === typeof result.durationMs ? '<p class="ajaco-meta">' + esc( fmt( t.completedIn, result.durationMs ) ) + '</p>' : '';
		return '<div class="ajaco-evidence">' + steps + dur + '</div>';
	}

	function renderFab() {
		var failing = failingChecks();
		if ( ! failing.length ) {
			return '';
		}
		return '<button class="ajaco-fab" data-act="sheet">' + esc( t.improveReadiness ) +
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
			items += '<li><span class="ajaco-sheet-name">' + esc( meta.name || f.id ) +
				( meta.fixable ? ' <em>' + esc( t.oneClickFix ) + '</em>' : ( meta.fixLink ? ' <em>' + esc( meta.fixLink.label ) + '</em>' : '' ) ) + '</span>' +
				pointsChip( f.id, f.result ) + '</li>';
		} );
		return '<div class="ajaco-sheet-backdrop" data-act="sheet-close"></div>' +
			'<div class="ajaco-sheet" role="dialog" aria-label="' + esc( t.improveReadiness ) + '">' +
			'<div class="ajaco-sheet-head"><strong>' + esc( fmt( plural( t.issuesFound, failing.length ), failing.length ) ) + '</strong>' +
			'<span class="ajaco-meta">' + esc( t.sortedImpact ) + '</span>' +
			'<button class="button-link" data-act="sheet-close">✕</button></div>' +
			'<ul class="ajaco-sheet-list">' + items + '</ul>' +
			'<div class="ajaco-sheet-actions">' +
			( fixable.length ? '<button class="button button-primary" data-act="fix-all">' + esc( fmt( t.fixAllSafe, fixable.length ) ) + '</button>' : '' ) +
			'<button class="button" data-act="copy-all">' + esc( t.copyAllPrompts ) + '</button>' +
			'</div>' +
			'<p class="ajaco-meta">' + esc( t.sheetIntro ) + '</p>' +
			'</div>';
	}

	/* ------------------------------------------------------------------ *
	 * Actions
	 * ------------------------------------------------------------------ */

	function absorb( json ) {
		if ( json && json.scan ) {
			state.scan = json.scan;
		}
		if ( json && json.history ) {
			state.history = json.history;
		}
	}

	function runScan() {
		state.busyScan = true;
		state.error = '';
		render();
		api( 'POST', '/scan' ).then( function ( json ) {
			absorb( json );
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
			absorb( json );
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
					absorb( json );
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
					case 'filter':
						state.filter = el.getAttribute( 'data-filter' ) || 'all';
						render();
						break;
					case 'external':
						checkExternal();
						break;
					case 'copy-text':
						copyText( el.getAttribute( 'data-text' ) || '', el );
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
					case 'copy-snippet':
						var snippets = ( state.scan && state.scan.hosting && state.scan.hosting.snippets ) || {};
						copyText( snippets[ el.getAttribute( 'data-snippet' ) ] || '', el );
						break;
				}
			} );
		} );
	}

	render();
}() );
