<?php
/**
 * The account-area screen: which tabs exist and how they are ordered.
 *
 * Everything seen in "my account" is administered from here: the ones the
 * plugin ships, the ones other plugins add (LifterLMS and company) and the
 * ones the site adds with its own text or another plugin's shortcode. They
 * are turned on, turned off, renamed and reordered without touching a line of
 * code.
 *
 * The ones from code can be turned off but not deleted: the code that draws
 * them is still there, and deleting them from the option would bring them
 * back on the next request.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Saves one section's configuration, merging it with what was already there.
 *
 * @param array<string, mixed> $config
 */
function diluxone_users_section_config_save( string $id, array $config ): void {
	$all = (array) diluxone_users_option( 'diluxone_users_account_sections' );

	$all[ $id ] = array_merge( (array) ( $all[ $id ] ?? array() ), $config );

	update_option( 'diluxone_users_account_sections', $all );
}

/** Removes one of the site's own sections. The ones from code are left alone. */
function diluxone_users_section_delete( string $id ): void {
	$all = (array) diluxone_users_option( 'diluxone_users_account_sections' );

	if ( empty( $all[ $id ]['custom'] ) ) {
		return;
	}

	unset( $all[ $id ] );

	update_option( 'diluxone_users_account_sections', $all );
}

/**
 * Reorders the sections with the list that arrived from the drag.
 *
 * Every position is rewritten at once, in gaps of 10, instead of touching
 * only the ones that moved: if two registered sections share a position —
 * which happens when a plugin does not declare one — rearranging just a pair
 * would move nothing and the drag would look broken.
 *
 * @param array<int, string> $ids
 */
function diluxone_users_section_reorder( array $ids ): void {
	$known = diluxone_users_sections( true );
	$order = 0;

	foreach ( $ids as $id ) {
		if ( ! isset( $known[ $id ] ) ) {
			continue;
		}

		++$order;
		diluxone_users_section_config_save( $id, array( 'position' => $order * 10 ) );
	}
}

/**
 * Saves a section from the detail form.
 *
 * It serves both purposes — creating and editing — because they are the same
 * one: a section is a name, an address and something to show. It returns the
 * identifier, or '' when it could not be done.
 *
 * @param array<string, mixed> $input
 */
function diluxone_users_section_save( array $input ): string {
	$id     = sanitize_key( (string) ( $input['id'] ?? '' ) );
	$exists = diluxone_users_sections( true );
	$fresh  = '' === $id || ! isset( $exists[ $id ] );
	$label  = sanitize_text_field( (string) ( $input['label'] ?? '' ) );

	if ( '' === $label ) {
		return '';
	}

	$slug = sanitize_title( (string) ( $input['slug'] ?? '' ) );
	$slug = '' === $slug ? sanitize_title( $label ) : $slug;

	if ( $fresh ) {
		$id = '' === $id ? $slug : $id;

			// One of the site's own sections cannot tread on one from code: there
			// would be two with the same address and either could win.
		if ( '' === $id || isset( $exists[ $id ] ) ) {
			return '';
		}
	}

	$config = array(
		'label'      => $label,
		'slug'       => $slug,
		'intro'      => sanitize_text_field( (string) ( $input['intro'] ?? '' ) ),
		'content'    => wp_kses_post( (string) ( $input['content'] ?? '' ) ),
		'placement'  => in_array( $input['placement'] ?? '', array( 'before', 'after', 'replace' ), true )
			? (string) $input['placement']
			: 'after',
		'roles'      => array_values( array_filter( array_map( 'sanitize_key', (array) ( $input['roles'] ?? array() ) ) ) ),
		// Kept even when it is shown to everybody: switching to "only some"
		// and back should not throw away what was ticked.
		'visibility' => 'some' === ( $input['visibility'] ?? '' ) ? 'some' : 'all',
	);

	if ( $fresh ) {
		$config['custom']   = true;
		$config['enabled']  = 1;
		$config['position'] = 900;
	}

	diluxone_users_section_config_save( $id, $config );

	/*
	 * The cards are not part of the section: they belong to the front page as
	 * a whole, and a card can come from a section that is not this one. So
	 * they are saved as their own option, and only from the form that draws
	 * them — otherwise editing any other section would wipe the list.
	 */
	if ( 'home' === $id && ! empty( $input['cards_shown'] ) ) {
		$shown = array_map( 'sanitize_key', (array) ( $input['cards'] ?? array() ) );
		$off   = array();

		foreach ( diluxone_users_summary_cards() as $card ) {
			if ( ! in_array( (string) $card['id'], $shown, true ) ) {
				$off[] = (string) $card['id'];
			}
		}

		update_option( 'diluxone_users_home_cards_off', $off );
	}

	return $id;
}

/**
 * Turning a section on, off, moving it or deleting it.
 *
 * It goes on admin_init and not inside the screen: by the time WordPress
 * calls the callback of an admin page it has already printed the headers, and
 * there a wp_safe_redirect() can do nothing but a "headers already sent"
 * notice in the log.
 */
function diluxone_users_account_actions(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'diluxone-users-account' !== sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) || ! isset( $_GET['diluxone_users_action'], $_GET['section'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'diluxone_users_section_action' );

	$id     = sanitize_key( wp_unslash( $_GET['section'] ) );
	$action = sanitize_key( wp_unslash( $_GET['diluxone_users_action'] ) );

	$back = array( 'section' => $id );

	if ( 'delete' === $action ) {
		diluxone_users_section_delete( $id );
		$back = array();
	} elseif ( 'on' === $action || 'off' === $action ) {
		diluxone_users_section_config_save( $id, array( 'enabled' => 'on' === $action ? 1 : 0 ) );
	}

	// It comes back to the same section: whoever turns one on and gets sent
	// back to the first of the list has to find it again every time.
	wp_safe_redirect( diluxone_users_admin_url( 'diluxone-users-account', $back ) );
	exit;
}
add_action( 'admin_init', 'diluxone_users_account_actions' );

/** The account-area screen. */
function diluxone_users_screen_account(): void {
	diluxone_users_account_notice();
	diluxone_users_screen_panels( 'diluxone-users-account', diluxone_users_screens()['diluxone-users-account'] );
}

/**
 * Its tabs, by the registry, so an add-on can add one of its own.
 *
 * The sections tab keeps its own forms — a list to reorder and a detail to
 * edit are not one settings form — and posts them to admin_init as before;
 * the registry only draws the tab around it.
 */
function diluxone_users_account_panels(): void {
	diluxone_users_register_panel(
		'diluxone-users-account',
		'summary',
		array(
			'label'    => __( 'Summary', 'diluxone-users' ),
			'position' => 0,
			'render'   => 'diluxone_users_screen_account_summary',
			'form'     => false,
		)
	);

	diluxone_users_register_panel(
		'diluxone-users-account',
		'page',
		array(
			'label'    => __( 'The page', 'diluxone-users' ),
			'position' => 10,
			'render'   => 'diluxone_users_screen_account_page',
			'save'     => 'diluxone_users_account_page_save',
		)
	);

	diluxone_users_register_panel(
		'diluxone-users-account',
		'sections',
		array(
			'label'    => __( 'Sections', 'diluxone-users' ),
			'position' => 20,
			'render'   => 'diluxone_users_screen_account_sections',
			'form'     => false,
		)
	);

	diluxone_users_register_panel(
		'diluxone-users-account',
		'handle',
		array(
			'label'    => __( 'Public name', 'diluxone-users' ),
			'position' => 30,
			'render'   => 'diluxone_users_screen_account_handle',
			'save'     => 'diluxone_users_account_handle_save',
		)
	);

	diluxone_users_register_panel(
		'diluxone-users-account',
		'dashboard',
		array(
			'label'    => __( 'The WordPress dashboard', 'diluxone-users' ),
			'position' => 40,
			'render'   => 'diluxone_users_screen_account_dashboard',
			'save'     => 'diluxone_users_account_dashboard_save',
		)
	);

	diluxone_users_register_panel(
		'diluxone-users-account',
		'privacy',
		array(
			'label'    => __( 'Their data', 'diluxone-users' ),
			'position' => 50,
			'render'   => 'diluxone_users_screen_account_privacy',
			'save'     => 'diluxone_users_account_privacy_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_account_panels' );

/** The page that is "my account". */
function diluxone_users_account_page_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_account_page' => absint( wp_unslash( $_POST['diluxone_users_account_page'] ?? 0 ) ),
		)
	);
	// phpcs:enable

	// The page changed: the /account/<section>/ rules have to be rebuilt.
	delete_option( 'diluxone_users_rewrite_version' );
}

/** The two things WordPress shows a signed-in person that the site may not want. */
function diluxone_users_account_dashboard_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	$bar = sanitize_key( wp_unslash( $_POST['diluxone_users_admin_bar'] ?? 'wp' ) );

	diluxone_users_save_options(
		array(
			'diluxone_users_wp_profile'            => sanitize_key( wp_unslash( $_POST['diluxone_users_wp_profile'] ?? 'allow' ) ),
			// One radio with three answers on the screen, two options
			// underneath: whether it is hidden, and from whom.
			'diluxone_users_admin_bar'             => 'wp' === $bar ? 'wp' : 'hide',
			'diluxone_users_admin_bar_scope'       => 'hide-some' === $bar ? 'some' : 'all',
			'diluxone_users_admin_bar_roles'       => array_map( 'sanitize_key', (array) wp_unslash( $_POST['diluxone_users_admin_bar_roles'] ?? array() ) ),
			'diluxone_users_admin_bar_keep_admins' => isset( $_POST['diluxone_users_admin_bar_keep_admins'] ) ? 1 : 0,
			'diluxone_users_bar_account'           => isset( $_POST['diluxone_users_bar_account'] ) ? 1 : 0,
		) + diluxone_users_scope_posted( 'diluxone_users_wp_profile' )
	);
	// phpcs:enable
}

/** What each person can do with their own data. */
function diluxone_users_account_privacy_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_privacy_export' => isset( $_POST['diluxone_users_privacy_export'] ) ? 1 : 0,
			'diluxone_users_privacy_delete' => isset( $_POST['diluxone_users_privacy_delete'] ) ? 1 : 0,
		)
	);
	// phpcs:enable
}

/**
 * Saves whatever was submitted from the screen.
 *
 * It goes on admin_init, like the one-click actions, so it can redirect: by
 * the time WordPress calls a page callback it has already printed the
 * headers. And redirecting is needed — it is not only hygiene — because after
 * creating a section it has to be opened, and because reloading must not
 * submit the form again.
 */
function diluxone_users_account_post(): void {
	// phpcs:disable WordPress.Security.NonceVerification -- each branch verifies its own.
	if ( 'diluxone-users-account' !== sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$open_box = sanitize_key( wp_unslash( $_GET['section'] ?? '' ) );

	if ( isset( $_POST['diluxone_users_orden_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_orden_nonce'] ) ), 'diluxone_users_orden' ) ) {
		diluxone_users_section_reorder( array_map( 'sanitize_key', (array) wp_unslash( $_POST['diluxone_users_orden'] ?? array() ) ) );

		diluxone_users_account_back( 'order', $open_box );
	}

	if ( isset( $_POST['diluxone_users_seccion_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_seccion_nonce'] ) ), 'diluxone_users_seccion' ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- diluxone_users_section_save() sanitises it field by field.
		$saved = diluxone_users_section_save( (array) wp_unslash( $_POST['diluxone_users_seccion'] ?? array() ) );

		diluxone_users_account_back(
			'' === $saved ? 'error' : 'guardada',
			'' === $saved ? $open_box : $saved
		);
	}

	// phpcs:enable
}
add_action( 'admin_init', 'diluxone_users_account_post' );

/** Back to the screen, on the right section, with the notice in place. */
function diluxone_users_account_back( string $msg, string $section = '' ): void {
	$args = array( 'diluxone_users_msg' => $msg );

	if ( '' !== $section ) {
		$args['section'] = $section;
	}

	wp_safe_redirect( diluxone_users_admin_url( 'diluxone-users-account', $args ) );
	exit;
}

/** The notice about what just happened. */
function diluxone_users_account_notice(): void {
	$notices = array(
		'order'    => array( 'success', __( 'New order saved.', 'diluxone-users' ) ),
		'guardada' => array( 'success', __( 'Section saved.', 'diluxone-users' ) ),
		'borrada'  => array( 'success', __( 'Section removed.', 'diluxone-users' ) ),
		'error'    => array( 'error', __( 'That section needs a name, and an address that is not taken.', 'diluxone-users' ) ),
	);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- it only picks the message.
	$msg = isset( $_GET['diluxone_users_msg'] ) ? sanitize_key( wp_unslash( $_GET['diluxone_users_msg'] ) ) : '';

	if ( isset( $notices[ $msg ] ) ) {
		diluxone_users_notice( $notices[ $msg ][1], $notices[ $msg ][0] );
	}
}

/**
 * The sections: the list on the left and the detail of one on the right.
 *
 * It is a two-panel screen and not a table because they are two different
 * things: the order, which is taken in at a glance and dragged, and what a
 * section is, which is eight fields and an editor. Putting the eight fields
 * inside a row warped the table and hid the order.
 */
function diluxone_users_screen_account_sections(): void {
	$sections = diluxone_users_sections( true );
	$page     = diluxone_users_account_page_id();
	$actual   = diluxone_users_screen_account_current( $sections );
	?>
	<?php if ( $page <= 0 ) : ?>
		<div class="notice notice-warning inline diluxone-users-state-box">
			<p><strong><?php esc_html_e( 'There is no account page yet', 'diluxone-users' ); ?></strong></p>
			<p><?php esc_html_e( 'Pick the page that has the [diluxone_users_account] shortcode, under “Where it lives”. Until then the links below go nowhere.', 'diluxone-users' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="diluxone-users-endpoints">
		<div class="diluxone-users-endpoints__head">
			<h2><?php esc_html_e( 'The sections of “my account”', 'diluxone-users' ); ?></h2>
			<a class="button button-primary" href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-account', array( 'section' => 'diluxone-users-new' ) ) ); ?>"><?php esc_html_e( 'Add section', 'diluxone-users' ); ?></a>
		</div>

		<div class="diluxone-users-endpoints__body">
			<form class="diluxone-users-endpoints__order" method="post">
				<?php wp_nonce_field( 'diluxone_users_orden', 'diluxone_users_orden_nonce' ); ?>

				<ul class="diluxone-users-endpoints__list" data-diluxone-users-sortable>
					<?php foreach ( $sections as $id => $section ) : ?>
						<li class="diluxone-users-endpoint <?php echo $id === $actual ? 'is-current' : ''; ?> <?php echo $section['enabled'] && diluxone_users_section_available( $section ) ? '' : 'is-off'; ?>">
							<input type="hidden" name="diluxone_users_orden[]" value="<?php echo esc_attr( $id ); ?>">
							<a class="diluxone-users-endpoint__name" href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-account', array( 'section' => $id ) ) ); ?>"><?php echo esc_html( $section['label'] ); ?></a>
							<span class="diluxone-users-endpoint__grip" aria-hidden="true"></span>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php
				/*
				 * Without JavaScript there is no dragging, so the button stays: a
				 * screen that can only be used by dragging cannot be used with
				 * the keyboard.
				 */
				?>
				<p class="diluxone-users-endpoints__save-order">
					<button type="submit" class="button"><?php esc_html_e( 'Save the order', 'diluxone-users' ); ?></button>
				</p>
			</form>

			<div class="diluxone-users-endpoints__detail">
				<?php diluxone_users_screen_account_section( $actual, $sections, $page ); ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Which section is being looked at.
 *
 * With nothing asked for, the first one: a two-panel screen with a blank
 * right-hand side looks broken.
 *
 * @param array<string, array<string, mixed>> $sections
 */
function diluxone_users_screen_account_current( array $sections ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- it only picks what to draw.
	$requested = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';

	if ( 'diluxone-users-new' === $requested || isset( $sections[ $requested ] ) ) {
		return $requested;
	}

	return (string) ( array_key_first( $sections ) ?? 'diluxone-users-new' );
}

/**
 * The detail of one section.
 *
 * @param array<string, array<string, mixed>> $sections
 */
function diluxone_users_screen_account_section( string $id, array $sections, int $page ): void {
	$fresh   = ! isset( $sections[ $id ] );
	$section = $fresh
		? array(
			'label'     => '',
			'slug'      => '',
			'content'   => '',
			'placement' => 'after',
			'roles'     => array(),
			'source'    => '',
			'custom'    => true,
			'enabled'   => true,
			'render'    => '',
			'available' => '',
			'why'       => '',
		)
		: $sections[ $id ];

	$with_code = ! $fresh && diluxone_users_section_has_code( $section );
	?>
	<form method="post" class="diluxone-users-endpoint-form">
		<?php wp_nonce_field( 'diluxone_users_seccion', 'diluxone_users_seccion_nonce' ); ?>
		<input type="hidden" name="diluxone_users_seccion[id]" value="<?php echo esc_attr( $fresh ? '' : $id ); ?>">

		<div class="diluxone-users-endpoint-form__head">
			<h3><?php echo esc_html( $fresh ? __( 'New section', 'diluxone-users' ) : $section['label'] ); ?></h3>

			<?php if ( ! $fresh ) : ?>
				<a class="diluxone-users-toggle <?php echo $section['enabled'] ? 'is-on' : ''; ?>"
					href="
					<?php
					echo esc_url(
						wp_nonce_url(
							diluxone_users_admin_url(
								'diluxone-users-account',
								array(
									'section' => $id,
									'diluxone_users_action' => $section['enabled'] ? 'off' : 'on',
								)
							),
							'diluxone_users_section_action'
						)
					);
					?>
							">
					<span class="diluxone-users-toggle__knob" aria-hidden="true"></span>
					<?php echo $section['enabled'] ? esc_html__( 'Showing', 'diluxone-users' ) : esc_html__( 'Hidden', 'diluxone-users' ); ?>
				</a>

				<?php if ( ! empty( $section['custom'] ) ) : ?>
					<a class="button diluxone-users-danger"
						href="
						<?php
						echo esc_url(
							wp_nonce_url(
								diluxone_users_admin_url(
									'diluxone-users-account',
									array(
										'section' => $id,
										'diluxone_users_action' => 'delete',
									)
								),
								'diluxone_users_section_action'
							)
						);
						?>
								"
						onclick="return confirm(<?php echo esc_attr( (string) (string) wp_json_encode( __( 'Delete this section?', 'diluxone-users' ) ) ); ?>);">
						<?php esc_html_e( 'Remove', 'diluxone-users' ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<?php if ( ! $fresh && $section['enabled'] && ! diluxone_users_section_available( $section ) ) : ?>
			<div class="notice notice-info inline diluxone-users-state-box">
				<p><strong><?php esc_html_e( 'It is on, but it is not showing', 'diluxone-users' ); ?></strong></p>
				<p><?php echo esc_html( (string) $section['why'] ); ?></p>
			</div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="diluxone-users-section-label"><?php esc_html_e( 'Name', 'diluxone-users' ); ?></label></th>
				<td>
					<input type="text" id="diluxone-users-section-label" class="regular-text" name="diluxone_users_seccion[label]" value="<?php echo esc_attr( $section['label'] ); ?>" required>
					<p class="description"><?php esc_html_e( 'What people read in the menu, and the title of the section.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="diluxone-users-section-slug"><?php esc_html_e( 'Address', 'diluxone-users' ); ?></label></th>
				<td>
					<input type="text" id="diluxone-users-section-slug" class="regular-text code" name="diluxone_users_seccion[slug]" value="<?php echo esc_attr( $section['slug'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'made from the name', 'diluxone-users' ); ?>">
					<?php if ( ! $fresh && $page > 0 ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( diluxone_users_account_url( $id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( str_replace( home_url(), '', diluxone_users_account_url( $id ) ) ); ?></a>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( 'home' === $id ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'The cards it shows', 'diluxone-users' ); ?></th>
					<td>
						<?php
						/*
						 * What is stored is the list of cards turned OFF, so a
						 * card that turns up tomorrow because a plugin was
						 * installed appears by itself. The screen asks the
						 * question the other way round because "show this" is
						 * what somebody is actually deciding.
						 */
						$diluxone_users_off = diluxone_users_summaries_hidden();
						$diluxone_users_all = diluxone_users_summary_cards();
						?>

						<?php
						// A marker, because a form with every box unticked
						// sends no "cards" at all — and without something
						// saying the list was on screen, "show none" would be
						// indistinguishable from "this form never had it".
						?>
						<input type="hidden" name="diluxone_users_seccion[cards_shown]" value="1">

						<?php if ( array() === $diluxone_users_all ) : ?>
							<p class="description"><?php esc_html_e( 'No section offers a card yet, so the front page is empty. Sections bring their own, and so does anything else installed on the site.', 'diluxone-users' ); ?></p>
						<?php endif; ?>

						<?php foreach ( $diluxone_users_all as $diluxone_users_card ) : ?>
							<label class="diluxone-users-roles__item">
								<input type="checkbox" name="diluxone_users_seccion[cards][]" value="<?php echo esc_attr( (string) $diluxone_users_card['id'] ); ?>" <?php checked( ! in_array( (string) $diluxone_users_card['id'], $diluxone_users_off, true ) ); ?>>
								<?php echo esc_html( (string) $diluxone_users_card['label'] ); ?>
								<?php if ( '' === (string) $diluxone_users_card['value'] ) : ?>
									<span class="description">— <?php esc_html_e( 'nothing to show right now', 'diluxone-users' ); ?></span>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>

						<p class="description"><?php esc_html_e( 'The front page is a summary: each section offers one card and the site can add its own. Untick one and it stops being shown — the section itself is untouched, and a card that has nothing to say today is left out on its own anyway.', 'diluxone-users' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>

			<tr>
				<th scope="row"><label for="diluxone-users-section-intro"><?php esc_html_e( 'The line under the title', 'diluxone-users' ); ?></label></th>
				<td>
					<input type="text" id="diluxone-users-section-intro" class="large-text" name="diluxone_users_seccion[intro]" value="<?php echo esc_attr( (string) ( $section['intro'] ?? '' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Optional. One sentence saying what this section is for, under its title.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Who sees it', 'diluxone-users' ); ?></th>
				<td>
					<?php
					$roles = (array) ( $section['roles'] ?? array() );

					diluxone_users_roles_picker(
						'diluxone_users_seccion[visibility]',
						'diluxone_users_seccion[roles][]',
						diluxone_users_section_visibility( $section ),
						$roles,
						__( 'Everybody with an account, or only the roles ticked. Nobody who is not signed in reaches the account area at all.', 'diluxone-users' )
					);
					?>
				</td>
			</tr>

			<?php if ( $with_code ) : ?>
				<tr>
					<th scope="row"><label for="diluxone-users-section-placement"><?php esc_html_e( 'Where your content goes', 'diluxone-users' ); ?></label></th>
					<td>
						<select id="diluxone-users-section-placement" name="diluxone_users_seccion[placement]">
							<?php
							$where = array(
								'after'   => __( 'After what the plugin shows', 'diluxone-users' ),
								'before'  => __( 'Before what the plugin shows', 'diluxone-users' ),
								'replace' => __( 'Instead of it — your content replaces the section', 'diluxone-users' ),
							);

							foreach ( $where as $key => $label ) {
								printf(
									'<option value="%1$s"%2$s>%3$s</option>',
									esc_attr( $key ),
									selected( $section['placement'], $key, false ),
									esc_html( $label )
								);
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'This section is drawn by code. What you write below is added to it — unless you say it replaces it.', 'diluxone-users' ); ?></p>
					</td>
				</tr>
			<?php else : ?>
				<input type="hidden" name="diluxone_users_seccion[placement]" value="replace">
			<?php endif; ?>

			<tr>
				<th scope="row"><label for="diluxone-users-section-content"><?php esc_html_e( 'Your content', 'diluxone-users' ); ?></label></th>
				<td>
					<?php
					wp_editor(
						(string) $section['content'],
						'diluxone-users-section-content',
						array(
							'textarea_name' => 'diluxone_users_seccion[content]',
							'textarea_rows' => 10,
							'media_buttons' => true,
						)
					);
					?>
					<p class="description">
						<?php
						echo $with_code
							? esc_html__( 'Text, HTML, or the shortcode of another plugin. Leave it empty and the section stays as the plugin draws it.', 'diluxone-users' )
							: esc_html__( 'Text, HTML, or the shortcode of another plugin — a course plugin, a membership, a support desk. This is the whole section.', 'diluxone-users' );
						?>
					</p>
				</td>
			</tr>

			<?php if ( ! $fresh && '' !== (string) $section['source'] ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Comes from', 'diluxone-users' ); ?></th>
					<td>
						<p><?php echo esc_html( $section['source'] ); ?> <code><?php echo esc_html( $id ); ?></code></p>
						<?php if ( empty( $section['custom'] ) ) : ?>
							<p class="description"><?php esc_html_e( 'It comes from code, so it cannot be deleted —the code that draws it is still there and it would come back— but it can be hidden.', 'diluxone-users' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
		</table>

		<?php submit_button( $fresh ? __( 'Add section', 'diluxone-users' ) : __( 'Save section', 'diluxone-users' ) ); ?>
	</form>
	<?php
}

/** The state of the account area. Nothing is edited here. */
function diluxone_users_screen_account_summary(): void {
	$page     = diluxone_users_account_page_id();
	$sections = diluxone_users_sections( true );
	$on       = array_filter( $sections, static fn( array $s ): bool => ! empty( $s['enabled'] ) );
	$profile  = (string) diluxone_users_option( 'diluxone_users_wp_profile' );
	$bar      = (string) diluxone_users_option( 'diluxone_users_admin_bar' );
	$tab      = static fn( string $id ): string => diluxone_users_admin_url( 'diluxone-users-account', array( 'tab' => $id ) );

	$profiles = array(
		'allow'    => __( 'Left as WordPress ships it.', 'diluxone-users' ),
		'redirect' => __( 'Sends people to their account on the site.', 'diluxone-users' ),
		'block'    => __( 'Closed: details are edited on the site only.', 'diluxone-users' ),
	);

	diluxone_users_intro( __( 'What the account area is and where it lives, read from the settings the other tabs write. Nothing is edited here.', 'diluxone-users' ) );

	diluxone_users_summary_table(
		array(
			array(
				'label'  => __( 'The page', 'diluxone-users' ),
				'state'  => $page > 0 ? 'active' : 'off',
				'detail' => $page > 0
					? sprintf( '<a href="%s">%s</a>', esc_url( (string) get_permalink( $page ) ), esc_html( (string) get_the_title( $page ) ) )
					: esc_html__( 'This site has no account area.', 'diluxone-users' ),
				'url'    => $tab( 'page' ),
			),
			array(
				'label'  => __( 'Sections', 'diluxone-users' ),
				'state'  => array() !== $on ? 'active' : 'off',
				'detail' => esc_html(
					sprintf(
					/* translators: 1: sections shown, 2: sections there are */
						__( '%1$d of %2$d shown.', 'diluxone-users' ),
						count( $on ),
						count( $sections )
					)
				),
				'url'    => $tab( 'sections' ),
			),
			array(
				'label'  => __( 'Public name', 'diluxone-users' ),
				'state'  => diluxone_users_option( 'diluxone_users_handle_enabled' ) ? 'active' : 'off',
				'detail' => diluxone_users_option( 'diluxone_users_handle_enabled' )
					? esc_html__( 'People choose the short name they appear under.', 'diluxone-users' )
					: esc_html__( 'The name comes from their first and last name.', 'diluxone-users' ),
				'url'    => $tab( 'handle' ),
			),
			array(
				'label'  => __( 'The dashboard profile', 'diluxone-users' ),
				'state'  => 'allow' === $profile ? 'off' : 'active',
				'detail' => esc_html( $profiles[ $profile ] ?? $profiles['allow'] ),
				'url'    => $tab( 'dashboard' ),
			),
			array(
				'label'  => __( 'The WordPress toolbar', 'diluxone-users' ),
				'state'  => 'hide' === $bar ? 'active' : 'off',
				'detail' => 'hide' === $bar
					? ( 'some' === (string) diluxone_users_option( 'diluxone_users_admin_bar_scope' ) ? esc_html__( 'Hidden on the site for some roles.', 'diluxone-users' ) : esc_html__( 'Hidden on the site for everybody.', 'diluxone-users' ) )
					: esc_html__( 'Shown, as WordPress does.', 'diluxone-users' ),
				'url'    => $tab( 'dashboard' ),
			),
			array(
				'label'  => __( 'Their data', 'diluxone-users' ),
				'state'  => ( diluxone_users_option( 'diluxone_users_privacy_export' ) || diluxone_users_option( 'diluxone_users_privacy_delete' ) ) ? 'active' : 'off',
				'detail' => esc_html(
					implode(
						' · ',
						array_filter(
							array(
								diluxone_users_option( 'diluxone_users_privacy_export' ) ? __( 'can ask for a copy', 'diluxone-users' ) : '',
								diluxone_users_option( 'diluxone_users_privacy_delete' ) ? __( 'can ask to be deleted', 'diluxone-users' ) : '',
							)
						)
					)
				),
				'url'    => $tab( 'privacy' ),
			),
		)
	);
}

/** The page that is "my account" — or none, which is a state and says so. */
function diluxone_users_screen_account_page(): void {
	$page = (int) diluxone_users_account_page_id();

	diluxone_users_intro( __( 'Which page is “my account”. Declaring it here is what lets everything else on the site — a course, a forum, a certificate — send people to the right place.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="diluxone_users_account_page"><?php esc_html_e( 'The account page', 'diluxone-users' ); ?></label></th>
			<td>
				<?php
				/** @var array<string, mixed> $diluxone_users_dropdown */
				$diluxone_users_dropdown = array(
					'name'              => 'diluxone_users_account_page',
					'id'                => 'diluxone_users_account_page',
					'selected'          => $page,
					'show_option_none'  => __( '— None: this site has no account area —', 'diluxone-users' ),
					'option_none_value' => 0,
				);

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapes its own and prints it.
				wp_dropdown_pages( $diluxone_users_dropdown );
				?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: the shortcode, literal */
						esc_html__( 'The page with %s in it.', 'diluxone-users' ),
						'<code>[diluxone_users_account]</code>'
					);
					?>
				</p>
				<?php if ( '' === (string) get_option( 'permalink_structure' ) ) : ?>
					<p class="description"><?php esc_html_e( 'With plain permalinks the sections go as ?seccion=…; turn on pretty permalinks in Settings → Permalinks and they become /page/section/ on their own.', 'diluxone-users' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( $page <= 0 ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'With none', 'diluxone-users' ); ?></th>
				<td>
					<p><?php echo diluxone_users_state_pill( 'off' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?> <?php esc_html_e( 'This site has no account area. What that means:', 'diluxone-users' ); ?></p>
					<ul class="diluxone-users-list">
						<li><?php esc_html_e( 'Links to “my account” from the rest of the site go to the front page.', 'diluxone-users' ); ?></li>
						<li><?php esc_html_e( 'The toolbar’s user menu is not redirected, and “send people to their account” on the next tab has nowhere to send them.', 'diluxone-users' ); ?></li>
						<li><?php esc_html_e( 'The shortcode still works wherever somebody puts it.', 'diluxone-users' ); ?></li>
					</ul>
				</td>
			</tr>
		<?php endif; ?>
	</table>
	<?php
}

/** The two things WordPress shows a signed-in person that the site may not want. */
function diluxone_users_screen_account_dashboard(): void {
	$page = (int) diluxone_users_account_page_id();
	$bar  = 'hide' === (string) diluxone_users_option( 'diluxone_users_admin_bar' )
		? ( 'some' === (string) diluxone_users_option( 'diluxone_users_admin_bar_scope' ) ? 'hide-some' : 'hide-all' )
		: 'wp';

	diluxone_users_intro( __( 'Two things WordPress shows to anybody signed in — its own profile screen and its toolbar — that a site with an account area of its own may not want.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'The dashboard profile (wp-admin/profile.php)', 'diluxone-users' ); ?></th>
			<td>
				<?php if ( $page <= 0 ) : ?>
					<?php diluxone_users_not_now( __( 'There is no account area to send anybody to, so the second answer does nothing yet.', 'diluxone-users' ), diluxone_users_admin_url( 'diluxone-users-account', array( 'tab' => 'page' ) ), __( 'Choose the page →', 'diluxone-users' ) ); ?>
				<?php endif; ?>
				<?php
				$profile = array(
					'allow'    => __( 'Leave it as WordPress ships it', 'diluxone-users' ),
					'redirect' => __( 'Send people to their account on the site instead', 'diluxone-users' ),
					'block'    => __( 'Close it — their details are edited on the site only', 'diluxone-users' ),
				);

				foreach ( $profile as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_wp_profile" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_wp_profile' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'The dashboard profile does not know about the required fields or the edit limits set up here. Two screens for the same data is how a person ends up editing their name in one and their phone in the other, under different rules.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'For whom', 'diluxone-users' ); ?></th>
			<td>
				<?php
				diluxone_users_scope_control(
					'diluxone_users_wp_profile',
					'',
					__( 'Whoever can edit users is never reached: they are the person who has to be able to fix what broke, and the dashboard profile is where it gets fixed.', 'diluxone-users' )
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'The WordPress toolbar', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$bars = array(
					'wp'        => __( 'Show it to everybody, as WordPress does', 'diluxone-users' ),
					'hide-all'  => __( 'Hide it on the site for everybody', 'diluxone-users' ),
					'hide-some' => __( 'Hide it on the site only for some roles', 'diluxone-users' ),
				);

				foreach ( $bars as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_admin_bar" value="<?php echo esc_attr( $key ); ?>" <?php checked( $bar, $key ); ?> data-diluxone-users-bar>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>

				<div class="diluxone-users-scope__roles" data-diluxone-users-bar-roles <?php echo 'hide-some' === $bar ? '' : 'hidden'; ?>>
					<?php
					$diluxone_users_fixed = diluxone_users_option( 'diluxone_users_admin_bar_keep_admins' ) ? diluxone_users_roles_that_edit_users() : array();
					$diluxone_users_roles = (array) diluxone_users_option( 'diluxone_users_admin_bar_roles' );

					foreach ( wp_roles()->get_names() as $diluxone_users_role => $diluxone_users_label ) :
						?>
						<?php if ( in_array( (string) $diluxone_users_role, $diluxone_users_fixed, true ) ) : ?>
							<label class="diluxone-users-roles__item diluxone-users-roles__item--fixed">
								<input type="checkbox" disabled>
								<?php echo esc_html( translate_user_role( $diluxone_users_label ) ); ?>
								<span class="description"><?php esc_html_e( '— always keeps it', 'diluxone-users' ); ?></span>
							</label>
						<?php else : ?>
							<label class="diluxone-users-roles__item">
								<input type="checkbox" name="diluxone_users_admin_bar_roles[]" value="<?php echo esc_attr( (string) $diluxone_users_role ); ?>" <?php checked( in_array( (string) $diluxone_users_role, $diluxone_users_roles, true ) ); ?>>
								<?php echo esc_html( translate_user_role( $diluxone_users_label ) ); ?>
							</label>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>

				<label class="diluxone-users-roles__item">
					<input type="checkbox" name="diluxone_users_admin_bar_keep_admins" value="1" <?php checked( diluxone_users_option( 'diluxone_users_admin_bar_keep_admins' ), 1 ); ?>>
					<?php esc_html_e( 'Whoever can edit users always keeps it, whatever is chosen above', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Hiding the toolbar locks nobody out — /wp-admin stays open — which is why this is a choice and not a rule. It is on because the person who administers the site is the one who most needs the way back.', 'diluxone-users' ); ?></p>

				<label class="diluxone-users-roles__item">
					<input type="checkbox" name="diluxone_users_bar_account" value="1" <?php checked( diluxone_users_option( 'diluxone_users_bar_account' ), 1 ); ?>>
					<?php esc_html_e( 'While it is shown, its user menu points at the account area', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Their name, their picture and “Edit profile” lead to the account page instead of the dashboard.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/** The public name and its rules. */
function diluxone_users_account_handle_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_handle_enabled'  => isset( $_POST['diluxone_users_handle_enabled'] ) ? 1 : 0,
			'diluxone_users_handle_min'      => absint( wp_unslash( $_POST['diluxone_users_handle_min'] ?? 3 ) ),
			'diluxone_users_handle_max'      => absint( wp_unslash( $_POST['diluxone_users_handle_max'] ?? 30 ) ),
			'diluxone_users_handle_charset'  => sanitize_key( wp_unslash( $_POST['diluxone_users_handle_charset'] ?? 'strict' ) ),
			'diluxone_users_handle_spaces'   => sanitize_key( wp_unslash( $_POST['diluxone_users_handle_spaces'] ?? 'dash' ) ),
			'diluxone_users_handle_cooldown' => absint( wp_unslash( $_POST['diluxone_users_handle_cooldown'] ?? 30 ) ),
			'diluxone_users_handle_reserved' => sanitize_textarea_field( wp_unslash( $_POST['diluxone_users_handle_reserved'] ?? '' ) ),
		)
	);
	// phpcs:enable
}

/**
 * The public name: whether it is offered, under which rules, and whether it
 * also works for requesting the sign-in link.
 */
function diluxone_users_screen_account_handle(): void {
	diluxone_users_intro( __( 'The email is the identity and nobody chooses it. This is the short name people see, the one that goes in the address of their profile.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Offer it', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_handle_enabled" value="1" <?php checked( diluxone_users_option( 'diluxone_users_handle_enabled' ), 1 ); ?>>
					<?php esc_html_e( 'Let people choose their public name', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Turned off, the name comes from what they wrote as their first and last name, and the profile address is made from that.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Length', 'diluxone-users' ); ?></th>
			<td>
				<input type="number" name="diluxone_users_handle_min" class="small-text" min="1" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_handle_min' ) ); ?>">
				<?php esc_html_e( 'to', 'diluxone-users' ); ?>
				<input type="number" name="diluxone_users_handle_max" class="small-text" min="1" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_handle_max' ) ); ?>">
				<?php esc_html_e( 'characters', 'diluxone-users' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Letters', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$sets = array(
					'strict'  => __( 'Plain: a–z, digits, dot, dash and underscore', 'diluxone-users' ),
					'unicode' => __( 'Also accents and ñ', 'diluxone-users' ),
				);

				foreach ( $sets as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_handle_charset" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_handle_charset' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Whatever is typed is turned into the same thing WordPress would put in a URL, so what passes here is exactly what ends up in the address. Anything that does not fit —punctuation, symbols, emoji— is dropped, and the person sees what it turned into before saving.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Spaces', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$spaces = array(
					'dash'   => __( 'Turn them into dashes: “Ana Gómez” becomes ana-gomez', 'diluxone-users' ),
					'reject' => __( 'Refuse them and say so', 'diluxone-users' ),
				);

				foreach ( $spaces as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_handle_spaces" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_handle_spaces' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'A web address cannot have spaces, so one of the two has to happen. The first is what almost everybody expects; the second is for a site that would rather nobody ends up with a name they did not type.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Taken names', 'diluxone-users' ); ?></th>
			<td>
				<p class="description">
					<?php
					printf(
						/* translators: 1: user_nicename, 2: user_login */
						esc_html__( 'Always checked, and against two things: the public names already in use (%1$s) and the usernames that came with the accounts (%2$s). The second one matters because a site that lets people sign in by public name would otherwise have two people answering to the same text, and the link would go to the wrong account.', 'diluxone-users' ),
						'<code>user_nicename</code>',
						'<code>user_login</code>'
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'How often it can change', 'diluxone-users' ); ?></th>
			<td>
				<input type="number" name="diluxone_users_handle_cooldown" class="small-text" min="0" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_handle_cooldown' ) ); ?>">
				<?php esc_html_e( 'days between one change and the next', 'diluxone-users' ); ?>
				<p class="description"><?php esc_html_e( '0 means whenever they like. A name that changes every day does not identify anybody, and the old address stops working each time.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_handle_reserved"><?php esc_html_e( 'Names nobody can take', 'diluxone-users' ); ?></label></th>
			<td>
				<textarea id="diluxone_users_handle_reserved" name="diluxone_users_handle_reserved" rows="3" class="large-text code"><?php echo esc_textarea( (string) diluxone_users_option( 'diluxone_users_handle_reserved' ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One per line, or separated by commas. The obvious ones —admin, support, api, login— are already blocked; these are yours to add.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * What each person can do with their own data, without asking anyone.
 *
 * Both come turned on because that is what is right. Turning them off is not
 * hiding the obligation: it is saying those requests are handled by hand, and
 * in that case the whole section disappears from the front end instead of
 * offering buttons that lead nowhere.
 */
function diluxone_users_screen_account_privacy(): void {
	diluxone_users_intro( __( 'What each person can do with their own data from the site, without asking anybody.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Their data', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_privacy_export" value="1" <?php checked( diluxone_users_option( 'diluxone_users_privacy_export' ), 1 ); ?>>
					<?php esc_html_e( 'They can ask for a copy of everything and download it', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'It is the export WordPress already knows how to make: it asks for confirmation by email and leaves the file ready.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Their account', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_privacy_delete" value="1" <?php checked( diluxone_users_option( 'diluxone_users_privacy_delete' ), 1 ); ?>>
					<?php esc_html_e( 'They can ask for their account to be deleted', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Also confirmed by email, and never for an account that administers the site: it would leave the site with nobody in charge.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="description"><?php esc_html_e( 'With both off, the “Your data” section stops showing: an empty section is worse than no section.', 'diluxone-users' ); ?></p>
	<?php
}
