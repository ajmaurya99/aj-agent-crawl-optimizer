/**
 * AJ Agent Crawl Optimizer — admin settings page JS.
 *
 * Two concerns, both scoped to the Agent Ready → Settings screen:
 *   1. Move the WordPress "Settings saved." notice to below the Save button.
 *      (Notices persist until the user dismisses them — no auto-dismiss.)
 *   2. AI bot policy preset buttons (Allow all / Block training / Block all).
 */

(function () {
    'use strict';

    // --- Notice relocation --------------------------------------------------
    //
    // wp-admin/js/common.js moves admin notices to right after the first
    // h1/h2 in jQuery.ready; we run on `load` so we move *after* WP's mover.
    // `.notice.settings-error` covers WP's "Settings saved." and our reset
    // notice; other admin notices are intentionally left where WP put them.
    window.addEventListener('load', function () {
        var notices = document.querySelectorAll('.notice.settings-error');
        var submitP = document.querySelector('.wrap form p.submit');

        notices.forEach(function (notice) {
            if (submitP && submitP.parentNode) {
                submitP.parentNode.insertBefore(notice, submitP.nextSibling);
            }
        });
    });

    // --- "Open the feature guide" --------------------------------------------
    //
    // WordPress collapses the contextual Help panel by default, so the feature
    // documentation is invisible unless you know the Help tab exists. This
    // link opens the panel, selects the Features tab, and scrolls to it.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ajaco-open-help').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();

                var toggle = document.getElementById('contextual-help-link');
                var wrap = document.getElementById('contextual-help-wrap');
                if (!toggle || !wrap) {
                    return;
                }

                // Open the panel if it's closed (WP toggles aria-expanded).
                if ('true' !== toggle.getAttribute('aria-expanded')) {
                    toggle.click();
                }

                // Select the requested tab.
                var tabId = link.getAttribute('data-tab');
                var tabLink = tabId && document.querySelector('#tab-link-' + tabId + ' a');
                if (tabLink) {
                    tabLink.click();
                }

                wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });

                // Deep-link: scroll the panel to this feature's notes and
                // flash them so the eye lands in the right place.
                var feature = link.getAttribute('data-feature');
                var target = feature && document.getElementById('ajaco-help-' + feature);
                if (!target) {
                    return;
                }

                window.setTimeout(function () {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    target.classList.add('ajaco-help-flash');
                    window.setTimeout(function () {
                        target.classList.remove('ajaco-help-flash');
                    }, 1600);
                }, 250);
            });
        });
    });

    // --- AI bot policy presets ----------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ajaco-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var preset = btn.getAttribute('data-ajaco-preset');
                document.querySelectorAll('select[data-ajaco-purpose]').forEach(function (select) {
                    var purpose = select.getAttribute('data-ajaco-purpose');
                    if ('allow-all' === preset) {
                        select.value = 'allow';
                    } else if ('block-all' === preset) {
                        select.value = 'block';
                    } else if ('block-training' === preset) {
                        select.value = ('training' === purpose) ? 'block' : 'allow';
                    }
                });
            });
        });
    });
})();
