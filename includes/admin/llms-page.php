<?php
/**
 * Admin: the llms.txt curation screen.
 *
 * Two columns: the config form (intro, per-post-type sections, a custom
 * Markdown block) on the left, and a live preview of the exact file the site
 * would serve on the right. The preview renders UNSAVED changes — you see what
 * an agent will read before you commit to it.
 *
 * Saves through options.php into its OWN settings group (`ajaco_llms_settings`,
 * one option: `ajaco_llms_config`). It must not share the `ajaco_settings`
 * group: options.php force-updates every registered option in a submitted
 * group, so a shared group would wipe the feature toggles from this form.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the llms.txt curation screen.
 *
 * @return void
 */
function render_llms_page(): void {
	if ( ! current_user_can( required_capability() ) ) {
		return;
	}

	$config       = llms_config();
	$types        = llms_curatable_post_types();
	$feature_on   = is_feature_enabled( 'llms_txt' );
	$order_labels = llms_order_labels();
	?>
	<div class="wrap ajaco-llms-wrap">
		<h1><?php esc_html_e( 'llms.txt', 'aj-agent-crawl-optimizer' ); ?></h1>
		<p class="description ajaco-llms-lede">
			<?php esc_html_e( 'Curate what an AI agent reads first. This file is a short, hand-picked index of your site — not a sitemap dump. Choose what belongs in it, tell agents what the site is for, and check the preview before you save.', 'aj-agent-crawl-optimizer' ); ?>
		</p>

		<?php if ( ! $feature_on ) : ?>
			<div class="notice notice-warning inline ajaco-llms-off-notice">
				<p>
					<?php
					printf(
						wp_kses(
							/* translators: %s: link to the Agent Ready settings screen. */
							__( 'The llms.txt feature is turned off, so nothing is being served at <code>/llms.txt</code> yet. You can still curate the file here — turn the feature on in %s to publish it.', 'aj-agent-crawl-optimizer' ),
							array(
								'code' => array(),
								'a'    => array( 'href' => array() ),
							)
						),
						'<a href="' . esc_url( settings_page_url() ) . '">' . esc_html__( 'Agent Ready → Settings', 'aj-agent-crawl-optimizer' ) . '</a>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div class="ajaco-llms-layout">
			<div class="ajaco-llms-col-form">
				<form method="post" action="options.php" id="ajaco-llms-form">
					<?php settings_fields( 'ajaco_llms_settings' ); ?>

					<h2 class="ajaco-section-heading"><?php esc_html_e( 'Introduction', 'aj-agent-crawl-optimizer' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="ajaco-llms-intro"><?php esc_html_e( 'Intro text', 'aj-agent-crawl-optimizer' ); ?></label>
							</th>
							<td>
								<textarea id="ajaco-llms-intro" name="ajaco_llms_config[intro]" rows="4" class="large-text code" maxlength="2000" placeholder="<?php esc_attr_e( 'e.g. Acme sells hand-built espresso machines. Product pages carry live pricing and stock; the guides explain maintenance and repair.', 'aj-agent-crawl-optimizer' ); ?>"><?php echo esc_textarea( $config['intro'] ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Replaces the generic boilerplate line under your site title. This is the one place you get to tell an agent, in plain language, what this site is for and what it should trust the site to answer. Leave it empty to keep the default line.', 'aj-agent-crawl-optimizer' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<h2 class="ajaco-section-heading"><?php esc_html_e( 'Sections', 'aj-agent-crawl-optimizer' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'One section per content type. Every public post type on this site is listed — including custom post types and products. Exclude an individual entry, or rewrite the one-line summary an agent sees for it, from that post’s editor sidebar.', 'aj-agent-crawl-optimizer' ); ?>
					</p>

					<table class="widefat striped ajaco-llms-sections">
						<thead>
							<tr>
								<th scope="col" class="ajaco-llms-col-enabled"><?php esc_html_e( 'Include', 'aj-agent-crawl-optimizer' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Content type', 'aj-agent-crawl-optimizer' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Heading', 'aj-agent-crawl-optimizer' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Max items', 'aj-agent-crawl-optimizer' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Order', 'aj-agent-crawl-optimizer' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Top level only', 'aj-agent-crawl-optimizer' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Show date', 'aj-agent-crawl-optimizer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $types as $type => $label ) :
								$section      = llms_section_config( $config, $type, $label );
								$base         = 'ajaco_llms_config[sections][' . $type . ']';
								$id           = 'ajaco-llms-' . $type;
								$hierarchical = is_post_type_hierarchical( $type );
								?>
								<tr>
									<td class="ajaco-llms-col-enabled">
										<input type="hidden" name="<?php echo esc_attr( $base ); ?>[enabled]" value="0" />
										<input type="checkbox" id="<?php echo esc_attr( $id ); ?>-enabled" name="<?php echo esc_attr( $base ); ?>[enabled]" value="1" <?php checked( ! empty( $section['enabled'] ) ); ?> />
										<label for="<?php echo esc_attr( $id ); ?>-enabled" class="screen-reader-text">
											<?php
											printf(
												/* translators: %s: post type label, e.g. "Posts". */
												esc_html__( 'Include %s in llms.txt', 'aj-agent-crawl-optimizer' ),
												esc_html( $label )
											);
											?>
										</label>
									</td>
									<td>
										<strong><?php echo esc_html( $label ); ?></strong><br />
										<code class="ajaco-llms-slug"><?php echo esc_html( $type ); ?></code>
									</td>
									<td>
										<label for="<?php echo esc_attr( $id ); ?>-heading" class="screen-reader-text"><?php esc_html_e( 'Section heading', 'aj-agent-crawl-optimizer' ); ?></label>
										<input type="text" id="<?php echo esc_attr( $id ); ?>-heading" name="<?php echo esc_attr( $base ); ?>[heading]" value="<?php echo esc_attr( $section['heading'] ); ?>" class="ajaco-llms-heading" maxlength="120" placeholder="<?php echo esc_attr( $label ); ?>" />
									</td>
									<td>
										<label for="<?php echo esc_attr( $id ); ?>-count" class="screen-reader-text"><?php esc_html_e( 'Maximum items', 'aj-agent-crawl-optimizer' ); ?></label>
										<input type="number" id="<?php echo esc_attr( $id ); ?>-count" name="<?php echo esc_attr( $base ); ?>[count]" value="<?php echo esc_attr( (string) $section['count'] ); ?>" class="small-text" min="1" max="200" step="1" />
									</td>
									<td>
										<label for="<?php echo esc_attr( $id ); ?>-order" class="screen-reader-text"><?php esc_html_e( 'Order', 'aj-agent-crawl-optimizer' ); ?></label>
										<select id="<?php echo esc_attr( $id ); ?>-order" name="<?php echo esc_attr( $base ); ?>[order]">
											<?php foreach ( $order_labels as $value => $order_label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $section['order'], $value ); ?>><?php echo esc_html( $order_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<?php // The key must always exist, even when the control isn't rendered — an unchecked box POSTs nothing. ?>
										<input type="hidden" name="<?php echo esc_attr( $base ); ?>[top_level]" value="0" />
										<?php if ( $hierarchical ) : ?>
											<input type="checkbox" id="<?php echo esc_attr( $id ); ?>-top-level" name="<?php echo esc_attr( $base ); ?>[top_level]" value="1" <?php checked( ! empty( $section['top_level'] ) ); ?> />
											<label for="<?php echo esc_attr( $id ); ?>-top-level" class="screen-reader-text"><?php esc_html_e( 'Only list top-level items (skip children)', 'aj-agent-crawl-optimizer' ); ?></label>
										<?php else : ?>
											<span class="ajaco-llms-na" aria-hidden="true">—</span>
											<span class="screen-reader-text"><?php esc_html_e( 'Not applicable — this content type is not hierarchical.', 'aj-agent-crawl-optimizer' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<input type="hidden" name="<?php echo esc_attr( $base ); ?>[show_date]" value="0" />
										<input type="checkbox" id="<?php echo esc_attr( $id ); ?>-show-date" name="<?php echo esc_attr( $base ); ?>[show_date]" value="1" <?php checked( ! empty( $section['show_date'] ) ); ?> />
										<label for="<?php echo esc_attr( $id ); ?>-show-date" class="screen-reader-text"><?php esc_html_e( 'Show the publish date next to each entry', 'aj-agent-crawl-optimizer' ); ?></label>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<h2 class="ajaco-section-heading"><?php esc_html_e( 'Custom Markdown block', 'aj-agent-crawl-optimizer' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="ajaco-llms-custom-md"><?php esc_html_e( 'Markdown', 'aj-agent-crawl-optimizer' ); ?></label>
							</th>
							<td>
								<textarea id="ajaco-llms-custom-md" name="ajaco_llms_config[custom_md]" rows="8" class="large-text code" maxlength="20000" placeholder="<?php esc_attr_e( 'e.g. ## Contact — [Support](https://example.com/support): replies within one business day.', 'aj-agent-crawl-optimizer' ); ?>"><?php echo esc_textarea( $config['custom_md'] ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Raw Markdown appended to the file — anything the generated sections can’t express: contact routes, licensing terms, an FAQ, a pointer to your API. HTML is stripped; write plain Markdown.', 'aj-agent-crawl-optimizer' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajaco-llms-custom-position"><?php esc_html_e( 'Position', 'aj-agent-crawl-optimizer' ); ?></label>
							</th>
							<td>
								<select id="ajaco-llms-custom-position" name="ajaco_llms_config[custom_position]">
									<option value="top" <?php selected( $config['custom_position'], 'top' ); ?>><?php esc_html_e( 'Top — before the generated sections', 'aj-agent-crawl-optimizer' ); ?></option>
									<option value="bottom" <?php selected( $config['custom_position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom — after the generated sections', 'aj-agent-crawl-optimizer' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Agents read top-down and may stop early. Put anything they must not miss at the top.', 'aj-agent-crawl-optimizer' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button(); ?>
				</form>
			</div>

			<div class="ajaco-llms-col-preview">
				<div class="ajaco-llms-preview-pane">
					<div class="ajaco-llms-preview-head">
						<h2><?php esc_html_e( 'Preview', 'aj-agent-crawl-optimizer' ); ?></h2>
						<button type="button" class="button button-secondary" id="ajaco-llms-refresh">
							<?php esc_html_e( 'Refresh preview', 'aj-agent-crawl-optimizer' ); ?>
						</button>
					</div>

					<p class="description">
						<?php esc_html_e( 'Exactly what /llms.txt would serve, including your unsaved changes on this page. Nothing here is published until you press Save Changes.', 'aj-agent-crawl-optimizer' ); ?>
					</p>

					<?php render_view_links( array( '/llms.txt', '/llms-full.txt' ), $feature_on ); ?>

					<ul class="ajaco-llms-counters">
						<li>
							<strong id="ajaco-llms-entries">—</strong>
							<span><?php esc_html_e( 'entries', 'aj-agent-crawl-optimizer' ); ?></span>
						</li>
						<li>
							<strong id="ajaco-llms-bytes">—</strong>
							<span><?php esc_html_e( 'bytes', 'aj-agent-crawl-optimizer' ); ?></span>
						</li>
						<li>
							<strong id="ajaco-llms-tokens">—</strong>
							<span><?php esc_html_e( 'tokens (approx.)', 'aj-agent-crawl-optimizer' ); ?></span>
						</li>
					</ul>

					<p class="ajaco-llms-status" id="ajaco-llms-status" role="status" aria-live="polite"></p>

					<pre class="ajaco-llms-preview" id="ajaco-llms-preview" tabindex="0"></pre>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Human labels for the section order modes (keys match the config schema).
 *
 * @return array<string, string>
 */
function llms_order_labels(): array {
	return array(
		'recent' => __( 'Newest first', 'aj-agent-crawl-optimizer' ),
		'menu'   => __( 'Menu order', 'aj-agent-crawl-optimizer' ),
		'title'  => __( 'Title (A–Z)', 'aj-agent-crawl-optimizer' ),
	);
}

/**
 * The stored section config for a post type, or a sane blank row for a type
 * the owner has never configured (a newly registered CPT, say).
 *
 * @param array  $config Normalized config.
 * @param string $type   Post type slug.
 * @param string $label  Post type label (used as the default heading).
 * @return array
 */
function llms_section_config( array $config, string $type, string $label ): array {
	if ( isset( $config['sections'][ $type ] ) && is_array( $config['sections'][ $type ] ) ) {
		return $config['sections'][ $type ];
	}

	return array(
		'enabled'   => false,
		'heading'   => $label,
		'count'     => 10,
		'order'     => 'recent',
		'top_level' => is_post_type_hierarchical( $type ),
		'show_date' => false,
	);
}
