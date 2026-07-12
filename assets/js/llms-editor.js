/**
 * AJ Agent Crawl Optimizer — llms.txt curation panel for the block editor.
 *
 * Adds a document sidebar panel that lets an author steer how a single entry
 * appears in the agent indexes, writing the two meta fields registered in
 * includes/features/llms-config.php:
 *
 *   _ajaco_llms_exclude  (bool)   — keep this entry out of the agent indexes
 *   _ajaco_llms_summary  (string) — one line that overrides the excerpt
 *
 * Vanilla JS, no JSX and no build step: wp.element.createElement is called
 * directly. The whole registration is guarded, so this file no-ops on the
 * classic editor (a PHP metabox covers that screen) and anywhere the
 * block-editor globals are missing.
 */

(function (wp, config) {
    'use strict';

    if (!wp || !wp.plugins || !wp.editor || !wp.element || !wp.components || !wp.data || !wp.i18n) {
        return;
    }

    // WP 6.6+ exports the panel from @wordpress/editor. Older releases only
    // have it on @wordpress/edit-post, whose export is now deprecated — so it
    // is a fallback, never the first choice.
    var PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel ||
        (wp.editPost && wp.editPost.PluginDocumentSettingPanel);

    if (!PluginDocumentSettingPanel || !wp.plugins.registerPlugin) {
        return;
    }

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var registerPlugin = wp.plugins.registerPlugin;
    var ToggleControl = wp.components.ToggleControl;
    var TextareaControl = wp.components.TextareaControl;
    var ExternalLink = wp.components.ExternalLink;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var __ = wp.i18n.__;
    var sprintf = wp.i18n.sprintf;

    var PLUGIN_NAME = 'ajaco-llms-editor';
    var META_EXCLUDE = '_ajaco_llms_exclude';
    var META_SUMMARY = '_ajaco_llms_summary';

    // Mirrors the 300-character cap enforced by Ajaco\sanitize_llms_summary_meta().
    var SUMMARY_MAX = 300;

    var settings = config || {};

    /**
     * The sidebar panel.
     *
     * @return {Object|null} Panel element, or null when the post has no
     *                       curation meta (i.e. an uncurated post type).
     */
    function LlmsPanel() {
        var meta = useSelect(function (select) {
            var editor = select('core/editor');

            if (!editor || typeof editor.getEditedPostAttribute !== 'function') {
                return null;
            }

            return editor.getEditedPostAttribute('meta') || null;
        }, []);

        var editPost = useDispatch('core/editor').editPost;

        // The meta is only registered for curated post types; without it there
        // is nothing to edit (and writing would be rejected by the REST API).
        if (!meta || 'object' !== typeof meta || !(META_EXCLUDE in meta) || !editPost) {
            return null;
        }

        var excluded = !!meta[META_EXCLUDE];
        var summary = 'string' === typeof meta[META_SUMMARY] ? meta[META_SUMMARY] : '';

        // Spread the current meta so a change to one field doesn't drop edits
        // other plugins have staged on theirs.
        function updateMeta(changes) {
            editPost({ meta: Object.assign({}, meta, changes) });
        }

        function setExcluded(value) {
            var changes = {};
            changes[META_EXCLUDE] = value;
            updateMeta(changes);
        }

        function setSummary(value) {
            var changes = {};
            changes[META_SUMMARY] = String(value).slice(0, SUMMARY_MAX);
            updateMeta(changes);
        }

        var includeToggle = el(ToggleControl, {
            __nextHasNoMarginBottom: true,
            label: __('Include in llms.txt', 'aj-agent-crawl-optimizer'),
            checked: !excluded,
            help: excluded
                ? __('Excluded — this entry stays out of both /llms.txt and /llms-full.txt.', 'aj-agent-crawl-optimizer')
                : __('Included — this entry can appear in /llms.txt and /llms-full.txt.', 'aj-agent-crawl-optimizer'),
            onChange: function (included) {
                setExcluded(!included);
            }
        });

        var summaryHelp = el(
            Fragment,
            null,
            '' === summary.trim()
                ? __('Empty — the post excerpt will be used. Anything you write here overrides it.', 'aj-agent-crawl-optimizer')
                : __('This overrides the excerpt in llms.txt.', 'aj-agent-crawl-optimizer'),
            ' ',
            __('Write it for a model deciding whether to fetch the page, not for a human skimming.', 'aj-agent-crawl-optimizer'),
            el('br'),
            sprintf(
                /* translators: 1: number of characters used. 2: maximum number of characters allowed. */
                __('%1$d / %2$d characters', 'aj-agent-crawl-optimizer'),
                summary.length,
                SUMMARY_MAX
            )
        );

        // Hidden rather than disabled: an excluded entry has no summary to
        // write, and the stored value is kept for when it's included again.
        var summaryField = excluded
            ? el(
                'p',
                { className: 'components-base-control__help' },
                __('Agents never see this entry, so it needs no summary. Turn inclusion back on to write one.', 'aj-agent-crawl-optimizer')
            )
            : el(TextareaControl, {
                __nextHasNoMarginBottom: true,
                label: __('Summary for AI agents', 'aj-agent-crawl-optimizer'),
                value: summary,
                rows: 4,
                maxLength: SUMMARY_MAX,
                help: summaryHelp,
                onChange: setSummary
            });

        // Only when the feature is known to be off — an absent payload (stale
        // cached script, say) must not raise a false alarm.
        var disabledNotice = (false === settings.enabled)
            ? el(
                'p',
                { className: 'components-base-control__help' },
                __('llms.txt is turned off right now, so nothing is published yet. Turn it on under Agent Ready → Settings.', 'aj-agent-crawl-optimizer')
            )
            : null;

        var viewLink = settings.llmsUrl
            ? el(
                'p',
                null,
                el(
                    ExternalLink,
                    { href: settings.llmsUrl },
                    __('View /llms.txt', 'aj-agent-crawl-optimizer')
                )
            )
            : null;

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'ajaco-llms',
                title: __('Agent Ready (llms.txt)', 'aj-agent-crawl-optimizer'),
                className: 'ajaco-llms-panel'
            },
            disabledNotice,
            includeToggle,
            summaryField,
            viewLink
        );
    }

    // Re-registering the same plugin name warns in the console; be idempotent
    // in case the script is enqueued twice.
    if (wp.plugins.getPlugin && wp.plugins.getPlugin(PLUGIN_NAME)) {
        return;
    }

    registerPlugin(PLUGIN_NAME, { render: LlmsPanel });
}(window.wp, window.AjacoLlmsEditor));
