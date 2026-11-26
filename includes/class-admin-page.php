<?php
/**
 * The "New Donation Form" admin page: what GiftKit makes, whether the site
 * is ready for it, the create form, what to do next, and the site's
 * existing donation forms.
 *
 * @package GiftKit
 */

namespace GiftKit;

use GFAPI;
use GFCommon;
use GFForms;
use GFFormsModel;
use RGCurrency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Page {

	/**
	 * Page slug, also the admin-post action that creates a form.
	 */
	const SLUG = 'giftkit_new_form';

	/**
	 * Where to read about GiftKit.
	 */
	const HELP_URL = 'https://giftkit.org/docs/';

	/**
	 * URL of this page.
	 *
	 * @param array $args Extra query arguments.
	 *
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/*
	 * Creating
	 */

	/**
	 * Creates the form (and optionally a draft page), then returns here.
	 */
	public static function handle_create() {
		if ( ! GFCommon::current_user_can_any( 'gravityforms_create_form' ) ) {
			wp_die( esc_html__( 'You are not allowed to create forms.', 'giftkit' ), 403 );
		}
		check_admin_referer( self::SLUG );

		$title = sanitize_text_field( wp_unslash( rgpost( 'giftkit_title' ) ) );
		if ( '' === $title ) {
			$title = __( 'Donate', 'giftkit' );
		}
		$base = $title;
		for ( $n = 2; ! GFFormsModel::is_unique_title( $title ); $n++ ) {
			$title = $base . ' (' . $n . ')';
		}

		$form_id = Form_Builder::create( $title );
		if ( is_wp_error( $form_id ) ) {
			wp_die( esc_html( $form_id->get_error_message() ) );
		}

		if ( rgpost( 'giftkit_create_page' ) && current_user_can( 'edit_pages' ) ) {
			$page_title = sanitize_text_field( wp_unslash( rgpost( 'giftkit_page_title' ) ) );
			$page_id    = self::create_page( $form_id, '' !== $page_title ? $page_title : $title );
			if ( $page_id ) {
				$form                      = GFAPI::get_form( $form_id );
				$form['giftkit']['pageId'] = $page_id;
				GFAPI::update_form( $form );
			}
		}

		wp_safe_redirect( self::url( array( 'created' => $form_id ) ) );
		exit;
	}

	/**
	 * Creates a draft page holding the form.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $title   Page title.
	 *
	 * @return int Page ID, or 0.
	 */
	private static function create_page( $form_id, $title ) {
		$content = use_block_editor_for_post_type( 'page' )
			? sprintf( '<!-- wp:gravityforms/form {"formId":"%d","title":false,"description":false,"ajax":true} /-->', $form_id )
			: self::shortcode( $form_id );

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => $content,
			),
			true
		);

		return is_wp_error( $page_id ) ? 0 : (int) $page_id;
	}

	/**
	 * The embed shortcode for a form.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string
	 */
	public static function shortcode( $form_id ) {
		return sprintf( '[gravityform id="%d" title="false" ajax="true"]', $form_id );
	}

	/**
	 * GF's preview URL for a form.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string
	 */
	public static function preview_url( $form_id ) {
		return add_query_arg( array( 'gf_page' => 'preview', 'id' => absint( $form_id ) ), trailingslashit( site_url() ) );
	}

	/*
	 * Rendering
	 */

	/**
	 * The page.
	 */
	public static function render() {
		$checks  = self::readiness_checks();
		$created = absint( rgget( 'created' ) );
		$created = $created ? GFAPI::get_form( $created ) : null;

		GFForms::admin_header( array(), false );
		?>
		<div class="gform-settings__content giftkit-admin">
			<div class="giftkit-admin__head">
				<h2><span class="dashicons dashicons-heart" aria-hidden="true"></span> <?php esc_html_e( 'New Donation Form', 'giftkit' ); ?></h2>
				<a class="giftkit-admin__help" href="<?php echo esc_url( self::HELP_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'How GiftKit works', 'giftkit' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'giftkit' ); ?></span></a>
			</div>

			<?php
			if ( Form_Builder::is_giftkit_form( $created ) ) {
				self::render_next_steps( $created );
			}
			?>

			<div class="giftkit-admin__columns">
				<div class="giftkit-admin__main">
					<?php self::render_preview(); ?>
					<?php self::render_create_form(); ?>
				</div>
				<div class="giftkit-admin__side">
					<?php self::render_checks( $checks ); ?>
					<?php self::render_included(); ?>
				</div>
			</div>

			<?php self::render_forms_list(); ?>
		<?php
		// Closes the content div and the wrappers admin_header() opened.
		GFForms::admin_footer();
	}

	/**
	 * Opens a GF settings panel.
	 *
	 * @param string $title   Panel title.
	 * @param string $classes Extra classes.
	 */
	private static function panel_open( $title, $classes = '' ) {
		?>
		<div class="gform-settings-panel giftkit-panel <?php echo esc_attr( $classes ); ?>">
			<header class="gform-settings-panel__header"><h4 class="gform-settings-panel__title"><?php echo esc_html( $title ); ?></h4></header>
			<div class="gform-settings-panel__content">
		<?php
	}

	/**
	 * Closes a GF settings panel.
	 */
	private static function panel_close() {
		echo '</div></div>';
	}

	/**
	 * The three pages, as illustrations.
	 */
	private static function render_preview() {
		$steps = array(
			array(
				'image' => 'step-1.svg',
				'title' => __( '1. The gift', 'giftkit' ),
				'text'  => __( 'One-time or monthly, preset amount buttons or another amount, an optional note, and the option to cover the processing fee.', 'giftkit' ),
			),
			array(
				'image' => 'step-2.svg',
				'title' => __( '2. The donor', 'giftkit' ),
				'text'  => __( 'Name, email, phone, billing address, and permission to send updates.', 'giftkit' ),
			),
			array(
				'image' => 'step-3.svg',
				'title' => __( '3. Payment', 'giftkit' ),
				'text'  => __( 'Card payment through Stripe, and a total that says whether it\'s charged once or every month.', 'giftkit' ),
			),
		);

		self::panel_open( __( 'What donors will see', 'giftkit' ) );
		echo '<ol class="giftkit-steps">';
		foreach ( $steps as $step ) {
			printf(
				'<li class="giftkit-step"><img src="%1$s" alt="" width="220" height="260" loading="lazy"><h3>%2$s</h3><p>%3$s</p></li>',
				esc_url( plugins_url( 'assets/images/' . $step['image'], GIFTKIT_FILE ) ),
				esc_html( $step['title'] ),
				esc_html( $step['text'] )
			);
		}
		echo '</ol>';
		echo '<p class="giftkit-muted">' . esc_html__( 'Colours follow your form styling in Gravity Forms. Everything shown can be adjusted after the form is created.', 'giftkit' ) . '</p>';
		self::panel_close();
	}

	/**
	 * The create form.
	 */
	private static function render_create_form() {
		self::panel_open( __( 'Create the form', 'giftkit' ) );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="giftkit-create">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SLUG ); ?>">
			<?php wp_nonce_field( self::SLUG ); ?>
			<p>
				<label for="giftkit-title"><?php esc_html_e( 'Form title', 'giftkit' ); ?></label>
				<input type="text" class="regular-text" id="giftkit-title" name="giftkit_title" value="<?php esc_attr_e( 'Donate', 'giftkit' ); ?>" required>
			</p>
			<?php if ( current_user_can( 'edit_pages' ) ) : ?>
				<p class="giftkit-create__page-toggle">
					<input type="checkbox" id="giftkit-create-page" name="giftkit_create_page" value="1" checked>
					<label for="giftkit-create-page"><?php esc_html_e( 'Also create a page for the form', 'giftkit' ); ?></label>
				</p>
				<p class="giftkit-create__page-title">
					<label for="giftkit-page-title"><?php esc_html_e( 'Page title', 'giftkit' ); ?></label>
					<input type="text" class="regular-text" id="giftkit-page-title" name="giftkit_page_title" value="<?php esc_attr_e( 'Donate', 'giftkit' ); ?>">
					<span class="giftkit-muted"><?php esc_html_e( 'Saved as a draft, so nothing is published until you choose to.', 'giftkit' ); ?></span>
				</p>
			<?php endif; ?>
			<p><button type="submit" class="button primary large"><?php esc_html_e( 'Create Donation Form', 'giftkit' ); ?></button></p>
		</form>
		<?php
		self::panel_close();
	}

	/**
	 * What the site needs before the form can take gifts.
	 *
	 * @return array[] Each: status (ok|warning|error), label, detail, link, link_text.
	 */
	public static function readiness_checks() {
		$checks = array();

		$checks[] = array(
			'status' => 'ok',
			'label'  => __( 'Gravity Forms', 'giftkit' ),
			/* translators: %s: version number */
			'detail' => sprintf( __( 'Version %s', 'giftkit' ), GFForms::$version ),
		);

		$stripe_settings_url = admin_url( 'admin.php?page=gf_settings&subview=gravityformsstripe' );

		if ( ! function_exists( 'gf_stripe' ) ) {
			$checks[] = array(
				'status' => 'error',
				'label'  => __( 'Stripe Add-On', 'giftkit' ),
				'detail' => __( 'Not active. GiftKit takes payments through the Gravity Forms Stripe Add-On.', 'giftkit' ),
			);

			return $checks;
		}

		$checks[] = array(
			'status' => 'ok',
			'label'  => __( 'Stripe Add-On', 'giftkit' ),
			/* translators: %s: version number */
			'detail' => sprintf( __( 'Version %s', 'giftkit' ), gf_stripe()->get_version() ),
		);

		$stripe = self::stripe_status();
		if ( ! $stripe['connected'] ) {
			$checks[] = array(
				'status'    => 'warning',
				'label'     => __( 'Stripe account', 'giftkit' ),
				'detail'    => __( 'Not connected. You can create the form now, but it can\'t take gifts until Stripe is connected.', 'giftkit' ),
				'link'      => $stripe_settings_url,
				'link_text' => __( 'Connect Stripe', 'giftkit' ),
			);
		} elseif ( 'live' === $stripe['mode'] ) {
			$checks[] = array(
				'status' => 'ok',
				'label'  => __( 'Stripe account', 'giftkit' ),
				'detail' => __( 'Connected in live mode: gifts are real charges.', 'giftkit' ),
			);
		} else {
			$checks[] = array(
				'status'    => 'warning',
				'label'     => __( 'Stripe account', 'giftkit' ),
				'detail'    => __( 'Connected in test mode: no real charges. Switch to live mode before taking real gifts.', 'giftkit' ),
				'link'      => $stripe_settings_url,
				'link_text' => __( 'Stripe settings', 'giftkit' ),
			);
		}

		$code     = GFCommon::get_currency();
		$currency = RGCurrency::get_currency( $code );
		$checks[] = array(
			'status'    => 'ok',
			'label'     => __( 'Currency', 'giftkit' ),
			/* translators: 1: currency name, 2: currency code */
			'detail'    => sprintf( __( 'Gifts are in %1$s (%2$s).', 'giftkit' ), rgar( $currency, 'name', $code ), $code ),
			'link'      => admin_url( 'admin.php?page=gf_settings' ),
			'link_text' => __( 'Change', 'giftkit' ),
		);

		return $checks;
	}

	/**
	 * The Stripe add-on's connection state.
	 *
	 * @return array connected (bool) and mode ('test' or 'live').
	 */
	public static function stripe_status() {
		if ( ! function_exists( 'gf_stripe' ) ) {
			return array( 'connected' => false, 'mode' => 'test' );
		}
		$settings = gf_stripe()->get_plugin_settings();
		$mode     = 'live' === rgar( $settings, 'api_mode' ) ? 'live' : 'test';

		return array(
			'connected' => (bool) rgar( $settings, $mode . '_publishable_key' ),
			'mode'      => $mode,
		);
	}

	/**
	 * The readiness checklist.
	 *
	 * @param array $checks Readiness checks.
	 */
	private static function render_checks( $checks ) {
		$icons  = array( 'ok' => 'yes-alt', 'warning' => 'warning', 'error' => 'dismiss' );
		$labels = array(
			'ok'      => __( 'Ready', 'giftkit' ),
			'warning' => __( 'Needs attention', 'giftkit' ),
			'error'   => __( 'Missing', 'giftkit' ),
		);

		self::panel_open( __( 'Before you start', 'giftkit' ) );
		echo '<ul class="giftkit-checks">';
		foreach ( $checks as $check ) {
			printf(
				'<li class="giftkit-check giftkit-check--%1$s"><span class="dashicons dashicons-%2$s" aria-hidden="true"></span><span class="screen-reader-text">%3$s:</span><div><strong>%4$s</strong><br>%5$s%6$s</div></li>',
				esc_attr( $check['status'] ),
				esc_attr( $icons[ $check['status'] ] ),
				esc_html( $labels[ $check['status'] ] ),
				esc_html( $check['label'] ),
				esc_html( $check['detail'] ),
				! empty( $check['link'] ) ? ' <a href="' . esc_url( $check['link'] ) . '">' . esc_html( $check['link_text'] ) . '</a>' : ''
			);
		}
		echo '</ul>';
		self::panel_close();
	}

	/**
	 * What's included.
	 */
	private static function render_included() {
		$items = array(
			__( 'One-time and monthly gifts', 'giftkit' ),
			__( 'Preset amount buttons, plus another amount', 'giftkit' ),
			__( 'An optional "cover the processing fee" choice', 'giftkit' ),
			__( 'Stripe feeds set up for both kinds of gift', 'giftkit' ),
			__( 'A minimum gift and spam protection', 'giftkit' ),
			__( 'An admin notification and a thank-you message', 'giftkit' ),
			__( 'One Donation settings tab for what you\'ll change', 'giftkit' ),
		);

		self::panel_open( __( 'What\'s included', 'giftkit' ) );
		echo '<ul class="giftkit-included">';
		foreach ( $items as $item ) {
			echo '<li><span class="dashicons dashicons-yes" aria-hidden="true"></span>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
		self::panel_close();
	}

	/**
	 * Shown after a form is created.
	 *
	 * @param array $form The new form.
	 */
	private static function render_next_steps( $form ) {
		$form_id = absint( $form['id'] );
		$page_id = absint( rgars( $form, 'giftkit/pageId' ) );
		$page    = $page_id ? get_post( $page_id ) : null;

		/* translators: %s: form title */
		self::panel_open( sprintf( __( '"%s" is ready', 'giftkit' ), $form['title'] ), 'giftkit-panel--success' );
		?>
		<ol class="giftkit-next">
			<?php if ( ! self::stripe_status()['connected'] ) : ?>
				<li>
					<strong><?php esc_html_e( 'Connect Stripe', 'giftkit' ); ?></strong>
					<span><?php esc_html_e( 'The form can\'t take gifts until Stripe is connected.', 'giftkit' ); ?></span>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gf_settings&subview=gravityformsstripe' ) ); ?>"><?php esc_html_e( 'Stripe settings', 'giftkit' ); ?></a>
				</li>
			<?php endif; ?>
			<li>
				<strong><?php esc_html_e( 'Check the settings', 'giftkit' ); ?></strong>
				<span><?php esc_html_e( 'Amounts, the processing fee, monthly gifts and the thank-you message.', 'giftkit' ); ?></span>
				<a class="button primary" href="<?php echo esc_url( Addon::settings_url( $form_id ) ); ?>"><?php esc_html_e( 'Donation settings', 'giftkit' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Try it', 'giftkit' ); ?></strong>
				<span><?php esc_html_e( 'Step through the form as a donor would.', 'giftkit' ); ?></span>
				<a class="button" href="<?php echo esc_url( self::preview_url( $form_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview form', 'giftkit' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Put it on your site', 'giftkit' ); ?></strong>
				<?php if ( $page ) : ?>
					<span>
						<?php
						/* translators: %s: page title */
						printf( esc_html__( 'The draft page "%s" holds the form. Publish it when you\'re ready.', 'giftkit' ), esc_html( get_the_title( $page ) ) );
						?>
					</span>
					<a class="button" href="<?php echo esc_url( get_edit_post_link( $page ) ); ?>"><?php esc_html_e( 'Edit page', 'giftkit' ); ?></a>
				<?php else : ?>
					<span><?php esc_html_e( 'Add this shortcode to any page, or use the Gravity Forms block.', 'giftkit' ); ?></span>
				<?php endif; ?>
				<span class="giftkit-embed">
					<input type="text" readonly value="<?php echo esc_attr( self::shortcode( $form_id ) ); ?>" aria-label="<?php esc_attr_e( 'Embed shortcode', 'giftkit' ); ?>">
					<button type="button" class="button giftkit-copy" data-copied="<?php esc_attr_e( 'Copied', 'giftkit' ); ?>"><?php esc_html_e( 'Copy', 'giftkit' ); ?></button>
				</span>
			</li>
		</ol>
		<?php
		self::panel_close();
	}

	/**
	 * The site's GiftKit forms.
	 */
	private static function render_forms_list() {
		$forms = array_filter( GFAPI::get_forms( null, false ), array( Form_Builder::class, 'is_giftkit_form' ) );
		if ( ! $forms ) {
			return;
		}

		self::panel_open( __( 'Your donation forms', 'giftkit' ) );

		$updated = absint( rgget( 'giftkit_updated' ) );
		if ( $updated ) {
			printf(
				'<p class="giftkit-updated" role="status"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> %s</p>',
				/* translators: %d: number of forms */
				esc_html( sprintf( _n( 'GiftKit updated %d donation form.', 'GiftKit updated %d donation forms.', $updated, 'giftkit' ), $updated ) )
			);
		}
		?>
		<table class="widefat striped giftkit-forms">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Form', 'giftkit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'giftkit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Gifts', 'giftkit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Page', 'giftkit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Setup', 'giftkit' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'giftkit' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $forms as $form ) :
					$form_id = absint( $form['id'] );
					$page_id = absint( rgars( $form, 'giftkit/pageId' ) );
					$page    = $page_id ? get_post( $page_id ) : null;
					$changes = Form_Builder::pending_changes( $form, Addon::get_instance()->get_form_settings( $form ) );
					?>
					<tr>
						<td><a href="<?php echo esc_url( Addon::settings_url( $form_id ) ); ?>"><strong><?php echo esc_html( $form['title'] ); ?></strong></a></td>
						<td><?php echo rgar( $form, 'is_active' ) ? esc_html__( 'Active', 'giftkit' ) : esc_html__( 'Inactive', 'giftkit' ); ?></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=gf_entries&id=' . $form_id ) ); ?>"><?php echo esc_html( number_format_i18n( GFAPI::count_entries( $form_id ) ) ); ?></a></td>
						<td>
							<?php
							if ( $page && 'trash' !== $page->post_status ) {
								$status = get_post_status_object( $page->post_status );
								printf(
									'<a href="%1$s">%2$s</a> <span class="giftkit-muted">(%3$s)</span>',
									esc_url( get_edit_post_link( $page ) ),
									esc_html( get_the_title( $page ) ),
									esc_html( $status ? $status->label : $page->post_status )
								);
							} else {
								echo '<span class="giftkit-muted">' . esc_html__( 'None', 'giftkit' ) . '</span>';
							}
							?>
						</td>
						<td class="giftkit-forms__setup">
							<?php if ( $changes ) : ?>
								<?php
								$confirm = sprintf(
									/* translators: 1: form title, 2: list of changes, one per line */
									__( "Update \"%1\$s\"? This changes:\n\n%2\$s\n\nChanges made to these parts in the form editor will be replaced.", 'giftkit' ),
									$form['title'],
									'• ' . implode( "\n• ", $changes )
								);
								?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm(<?php echo esc_attr( wp_json_encode( $confirm ) ); ?>);">
									<input type="hidden" name="action" value="<?php echo esc_attr( Addon::UPDATE_ACTION ); ?>">
									<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
									<?php wp_nonce_field( Addon::UPDATE_ACTION ); ?>
									<button type="submit" class="button button-small"><?php esc_html_e( 'Update', 'giftkit' ); ?></button>
									<a href="<?php echo esc_url( Addon::settings_url( $form_id ) ); ?>"><?php esc_html_e( 'Review', 'giftkit' ); ?><span class="screen-reader-text"> <?php esc_html_e( 'the changes on the Donation tab', 'giftkit' ); ?></span></a>
								</form>
							<?php else : ?>
								<span class="giftkit-muted"><?php esc_html_e( 'Up to date', 'giftkit' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="giftkit-forms__actions">
							<a href="<?php echo esc_url( Addon::settings_url( $form_id ) ); ?>"><?php esc_html_e( 'Settings', 'giftkit' ); ?></a> |
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=gf_edit_forms&id=' . $form_id ) ); ?>"><?php esc_html_e( 'Edit fields', 'giftkit' ); ?></a> |
							<a href="<?php echo esc_url( self::preview_url( $form_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview', 'giftkit' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		self::panel_close();
	}
}
