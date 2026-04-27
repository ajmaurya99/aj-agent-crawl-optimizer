/**
 * Agent-Ready — admin settings page JS.
 *
 * Three concerns, all scoped to the Settings → Agent-Ready screen:
 *   1. Smooth-scroll the in-page Read More links to their Detail anchors.
 *   2. Move the WordPress "Settings saved." notice from above the score card
 *      to right below the Save Changes button.
 *   3. Wire up the per-test-block copy buttons.
 *
 * Localized strings (e.g. "Copied!") come in via window.AgentReadyAdmin
 * which is set by wp_localize_script() in includes/admin/enqueue.php.
 */

(function () {
    'use strict';

    var i18n = (window.AgentReadyAdmin && window.AgentReadyAdmin.i18n) || {};

    // --- Notice relocation + auto-dismiss ---------------------------------
    //
    // wp-admin/js/common.js moves admin notices to right after the first
    // h1/h2 in jQuery.ready. Our first h2 is "Excellent" inside the score
    // card, so the notice ends up squashed against the dark gradient. We
    // run on `load` (not DOMContentLoaded) so we move *after* WP's mover.
    //
    // We match `.notice.settings-error` only — it's set by both the WP
    // "Settings saved." notice and our own "Reset to defaults" notice. Other
    // admin notices (network upgrade nags, etc.) are intentionally left
    // where WP put them.
    window.addEventListener('load', function () {
        var notices = document.querySelectorAll('.notice.settings-error');
        var submitP = document.querySelector('.wrap form p.submit');

        notices.forEach(function (notice) {
            if (submitP && submitP.parentNode) {
                submitP.parentNode.insertBefore(notice, submitP.nextSibling);
            }

            // Auto-dismiss after 3 seconds with a brief fade.
            setTimeout(function () {
                notice.style.transition = 'opacity 0.4s ease-out';
                notice.style.opacity = '0';
                setTimeout(function () {
                    if (notice.parentNode) {
                        notice.parentNode.removeChild(notice);
                    }
                }, 400);
            }, 3000);
        });
    });

    // --- Read More smooth scroll + copy buttons ----------------------------
    document.addEventListener('DOMContentLoaded', function () {
        // Smooth-scroll the in-page Read More links instead of jumping.
        document.querySelectorAll('.agent-ready-read-more').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    // Update the URL hash for back-button / share-link behaviour.
                    history.replaceState(null, '', this.getAttribute('href'));
                }
            });
        });

        // Per-test-block copy-to-clipboard.
        var copiedLabel = i18n.copied || 'Copied!';

        // One shared, polite aria-live region announces copy results to
        // assistive tech without changing focus or visual layout.
        var liveRegion = document.createElement('div');
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.className = 'agent-ready-screen-reader-text';
        document.body.appendChild(liveRegion);

        document.querySelectorAll('.agent-ready-copy-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = this.getAttribute('data-target');
                var codeEl = document.getElementById(targetId);
                if (!codeEl) {
                    return;
                }
                var text = codeEl.textContent || codeEl.innerText;
                navigator.clipboard.writeText(text).then(function () {
                    var originalText = btn.textContent;
                    btn.textContent = copiedLabel;
                    btn.classList.add('copied');
                    // Announce to screen readers.
                    liveRegion.textContent = copiedLabel;
                    setTimeout(function () {
                        btn.textContent = originalText;
                        btn.classList.remove('copied');
                        liveRegion.textContent = '';
                    }, 2000);
                }).catch(function (err) {
                    console.error('Copy failed:', err);
                });
            });
        });
    });
})();
