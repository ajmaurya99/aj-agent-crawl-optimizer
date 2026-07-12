/**
 * AJ Agent Crawl Optimizer — llms.txt curation screen.
 *
 * Serializes the `ajaco_llms_config[...]` form fields into the nested config
 * object the REST endpoint expects, POSTs it to /ajaco/v1/llms/preview, and
 * renders the returned Markdown into the preview pane. The config is never
 * saved by this file — the form still posts to options.php the normal way, so
 * the preview always shows UNSAVED state.
 */

(function () {
    'use strict';

    var L = window.AjacoLlms || {};
    var form = document.getElementById('ajaco-llms-form');
    var preview = document.getElementById('ajaco-llms-preview');

    // Not our screen (or the localized payload never landed) — do nothing.
    if (!form || !preview || !L.restUrl) {
        return;
    }

    var i18n = L.i18n || {};
    var statusEl = document.getElementById('ajaco-llms-status');
    var entriesEl = document.getElementById('ajaco-llms-entries');
    var bytesEl = document.getElementById('ajaco-llms-bytes');
    var tokensEl = document.getElementById('ajaco-llms-tokens');
    var refreshBtn = document.getElementById('ajaco-llms-refresh');

    var DEBOUNCE_MS = 400;

    // Matches ajaco_llms_config[intro] / ajaco_llms_config[sections][post][count].
    // Anything else in the form (the nonce, option_page, _wp_http_referer) is
    // deliberately not part of the config and is skipped.
    var FIELD_NAME = /^ajaco_llms_config((?:\[[^\]]*\])+)$/;

    var timer = null;
    var latestRequest = 0;

    /**
     * ajaco_llms_config[sections][post][count] -> ['sections', 'post', 'count'].
     */
    function fieldPath(name) {
        var match = FIELD_NAME.exec(name || '');
        if (!match) {
            return null;
        }

        var keys = match[1].match(/\[[^\]]*\]/g) || [];
        return keys.map(function (key) {
            return key.slice(1, -1);
        });
    }

    function assign(target, path, value) {
        var node = target;

        for (var i = 0; i < path.length - 1; i++) {
            var key = path[i];
            if (!node[key] || 'object' !== typeof node[key]) {
                node[key] = {};
            }
            node = node[key];
        }

        node[path[path.length - 1]] = value;
    }

    /**
     * Build the config object from the live form.
     *
     * Mirrors how PHP would read the POST: an unchecked checkbox contributes
     * nothing, leaving the hidden "0" input that precedes it in the DOM as the
     * value — so every key always exists.
     */
    function collectConfig() {
        var config = {};
        var fields = form.querySelectorAll('input[name], textarea[name], select[name]');

        Array.prototype.forEach.call(fields, function (field) {
            var path = fieldPath(field.name);
            if (!path || !path.length) {
                return;
            }

            if (('checkbox' === field.type || 'radio' === field.type) && !field.checked) {
                return;
            }

            var value = field.value;
            if ('number' === field.type) {
                var parsed = parseInt(value, 10);
                value = isNaN(parsed) ? 1 : parsed;
            }

            assign(config, path, value);
        });

        return config;
    }

    function setStatus(message, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message || '';
        statusEl.classList.toggle('is-error', !!isError);
    }

    function setBusy(busy) {
        if (refreshBtn) {
            refreshBtn.disabled = busy;
        }
        preview.classList.toggle('is-loading', busy);
    }

    function formatNumber(value) {
        var number = Number(value);
        if (!isFinite(number)) {
            return '—';
        }
        return number.toLocaleString();
    }

    function render(result) {
        // textContent, never innerHTML: this is site content (post titles,
        // excerpts, the owner's Markdown) and must never be parsed as HTML.
        preview.textContent = result.markdown;

        if (entriesEl) {
            entriesEl.textContent = formatNumber(result.entryCount);
        }
        if (bytesEl) {
            bytesEl.textContent = formatNumber(result.bytes);
        }
        if (tokensEl) {
            tokensEl.textContent = formatNumber(result.tokens);
        }

        setStatus('', false);
    }

    function refresh() {
        var requestId = ++latestRequest;

        setBusy(true);
        setStatus(i18n.loading || 'Refreshing preview…', false);

        window.fetch(L.restUrl + '/llms/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': L.nonce
            },
            credentials: 'same-origin',
            body: JSON.stringify({ config: collectConfig() })
        }).then(function (response) {
            // Guarded parse: gateway timeouts, WAFs, and PHP fatals all answer
            // with HTML, and a raw JSON.parse error tells the admin nothing.
            return response.json().catch(function () {
                return null;
            }).then(function (json) {
                if (!response.ok) {
                    if (403 === response.status) {
                        throw new Error(i18n.expired || 'Your session expired — reload this page and try again.');
                    }
                    if (json && json.message) {
                        throw new Error(json.message);
                    }
                    throw new Error((i18n.failed || 'Could not build the preview.') + ' (HTTP ' + response.status + ')');
                }

                if (!json || 'string' !== typeof json.markdown) {
                    throw new Error(i18n.badResponse || 'The server returned an unexpected response.');
                }

                return json;
            });
        }).then(function (json) {
            if (requestId !== latestRequest) {
                return; // A newer keystroke already fired; drop this result.
            }
            render(json);
        }).catch(function (error) {
            if (requestId !== latestRequest) {
                return;
            }
            setStatus(error && error.message ? error.message : (i18n.failed || 'Could not build the preview.'), true);
        }).then(function () {
            if (requestId === latestRequest) {
                setBusy(false);
            }
        });
    }

    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(refresh, DEBOUNCE_MS);
    }

    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            window.clearTimeout(timer);
            refresh();
        });
    }

    refresh();
})();
