<?php
/**
 * Feature: per-post llms.txt curation UI (block editor + classic editor).
 *
 * The meta itself — `_ajaco_llms_exclude` and `_ajaco_llms_summary` — is owned
 * by includes/features/llms-config.php. This file only puts controls in front
 * of an author:
 *
 *   - Block editor: a document sidebar panel (assets/js/llms-editor.js), which
 *     writes the meta over the REST API.
 *   - Classic editor: a metabox that writes the same two keys through the same
 *     sanitizer, so a site running Classic Editor is not a second-class citizen.
 *
 * Both surfaces are limited to the post types Ajaco\llms_curatable_post_types()
 * offers, which is exactly the set the meta is registered for.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LLMS_METABOX_ID    = 'ajaco-llms';
const LLMS_METABOX_NONCE = 'ajaco_llms_meta_nonce';

add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_llms_editor_assets' );
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\add_llms_meta_box', 10, 2 );
add_action( 'save_post', __NAMESPACE__ . '\\save_llms_meta_box', 10, 2 );

/**
 * Enqueue the block editor sidebar panel — only on the post types we curate.
 *
 * @return void
 */
function enqueue_llms_editor_assets(): void {
	$types  = llms_curatable_post_types();
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	// The site editor and template/widget screens also fire this hook; their
	// screen has no curatable post type, so they fall out here.
	if ( $screen instanceof \WP_Screen && ! isset( $types[ (string) $screen->post_type ] ) ) {
		return;
	}

	$path = AJACO_DIR . 'assets/js/llms-editor.js';
	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'ajaco-llms-editor',
		AJACO_URL . 'assets/js/llms-editor.js',
		array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
		(string) filemtime( $path ),
		true
	);

	wp_set_script_translations( 'ajaco-llms-editor', 'aj-agent-crawl-optimizer', AJACO_DIR . 'languages' );

	// wp_localize_script() stringifies top-level scalars ('enabled' => false
	// would arrive as ""), and the panel needs a real boolean to tell "feature
	// is off" from "no payload" — so hand the data over as JSON instead.
	$data = array(
		'enabled' => is_feature_enabled( 'llms_txt' ),
		'llmsUrl' => esc_url_raw( home_url( '/llms.txt' ) ),
	);

	wp_add_inline_script(
		'ajaco-llms-editor',
		'window.AjacoLlmsEditor = ' . wp_json_encode( $data ) . ';',
		'before'
	);
}

/**
 * Register the classic-editor metabox.
 *
 * Skipped when the screen is running the block editor — the sidebar panel is
 * already there, and two sets of controls writing the same meta is a bug farm.
 *
 * @param string $post_type Post type of the post being edited.
 * @param mixed  $post      The post being edited (unused).
 * @return void
 */
function add_llms_meta_box( string $post_type, $post = null ): void {
	unset( $post );

	$types = llms_curatable_post_types();
	if ( ! isset( $types[ $post_type ] ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen instanceof \WP_Screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
		return;
	}

	add_meta_box(
		LLMS_METABOX_ID,
		__( 'Agent Ready (llms.txt)', 'aj-agent-crawl-optimizer' ),
		__NAMESPACE__ . '\\render_llms_meta_box',
		$post_type,
		'side',
		'default'
	);
}

/**
 * Render the classic-editor metabox: the same two controls as the block editor
 * sidebar panel.
 *
 * @param \WP_Post $post The post being edited.
 * @return void
 */
function render_llms_meta_box( \WP_Post $post ): void {
	wp_nonce_field( 'ajaco_llms_meta_' . $post->ID, LLMS_METABOX_NONCE );

	$excluded = (bool) get_post_meta( $post->ID, LLMS_META_EXCLUDE, true );
	$summary  = (string) get_post_meta( $post->ID, LLMS_META_SUMMARY, true );
	?>
	<p>
		<label for="ajaco-llms-include">
			<input
				type="checkbox"
				id="ajaco-llms-include"
				name="ajaco_llms_include"
				value="1"
				<?php checked( ! $excluded ); ?>
			/>
			<?php esc_html_e( 'Include in llms.txt', 'aj-agent-crawl-optimizer' ); ?>
		</label>
	</p>
	<p class="description">
		<?php esc_html_e( 'Excluded entries stay out of both /llms.txt and /llms-full.txt.', 'aj-agent-crawl-optimizer' ); ?>
	</p>

	<p>
		<label for="ajaco-llms-summary">
			<strong><?php esc_html_e( 'Summary for AI agents', 'aj-agent-crawl-optimizer' ); ?></strong>
		</label>
		<textarea
			id="ajaco-llms-summary"
			name="ajaco_llms_summary"
			class="widefat"
			rows="4"
			maxlength="300"
		><?php echo esc_textarea( $summary ); ?></textarea>
	</p>
	<p class="description">
		<?php
		printf(
			/* translators: %d: maximum number of characters allowed in the summary. */
			esc_html__( 'Overrides the excerpt in llms.txt (max %d characters). Write it for a model deciding whether to fetch the page, not for a human skimming. Leave it empty to use the post excerpt.', 'aj-agent-crawl-optimizer' ),
			300
		);
		?>
	</p>
	<?php
}

/**
 * Save the classic-editor metabox.
 *
 * The block editor writes the same meta over the REST API and never submits
 * this form, so the absent nonce field is the signal to stand down.
 *
 * @param int   $post_id Post ID.
 * @param mixed $post    Post object.
 * @return void
 */
function save_llms_meta_box( int $post_id, $post = null ): void {
	// Not our POST (block editor save, quick edit, programmatic wp_update_post).
	if ( ! isset( $_POST[ LLMS_METABOX_NONCE ] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST[ LLMS_METABOX_NONCE ] ) );
	if ( ! wp_verify_nonce( $nonce, 'ajaco_llms_meta_' . $post_id ) ) {
		return;
	}

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$types = llms_curatable_post_types();
	if ( ! $post instanceof \WP_Post || ! isset( $types[ $post->post_type ] ) ) {
		return;
	}

	// An unchecked checkbox is simply absent from the POST body.
	if ( empty( $_POST['ajaco_llms_include'] ) ) {
		update_post_meta( $post_id, LLMS_META_EXCLUDE, true );
	} else {
		// Deleting (rather than storing a falsy value) keeps the default —
		// false — as the single source of truth for "not excluded".
		delete_post_meta( $post_id, LLMS_META_EXCLUDE );
	}

	$raw     = isset( $_POST['ajaco_llms_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ajaco_llms_summary'] ) ) : '';
	$summary = sanitize_llms_summary_meta( $raw );

	if ( '' === $summary ) {
		delete_post_meta( $post_id, LLMS_META_SUMMARY );
	} else {
		update_post_meta( $post_id, LLMS_META_SUMMARY, $summary );
	}

	// The meta hooks in llms-config.php flush the llms.txt caches for us.
}
