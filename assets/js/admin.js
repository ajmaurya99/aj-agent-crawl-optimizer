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
