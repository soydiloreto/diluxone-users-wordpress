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
		'content'    => wp_kses_post( (string) ( $input['content'] ?? '' ) ),
		'placement'  => in_array( $input['placement'] ?? '', array( 'before', 'after', 'replace', 'aside' ), true )
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

/** The account-area screen: its two tabs. */
function diluxone_users_screen_account(): void {
	$tabs = array(
		'sections'   => __( 'Sections', 'diluxone-users' ),
		'layout'     => __( 'Where it lives', 'diluxone-users' ),
		'appearance' => __( 'How it looks', 'diluxone-users' ),
		'photo'      => __( 'Profile photo', 'diluxone-users' ),
	);

	$current = diluxone_users_tab( $tabs );

	diluxone_users_account_notice();
	diluxone_users_screen_open( __( 'Account area', 'diluxone-users' ), 'diluxone-users-account', $tabs, $current );

	switch ( $current ) {
		case 'layout':
			diluxone_users_screen_account_layout();
			break;

		case 'appearance':
		case 'photo':
			// These two carry their own form and their own nonce: the sections
			// tab posts to admin_post and redirects, and mixing the two ways of
			// saving in one form is how a screen ends up saving twice.
			if ( isset( $_POST['diluxone_users_appearance_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_appearance_nonce'] ) ), 'diluxone_users_appearance' ) ) {
				diluxone_users_screen_appearance_save( $current );
				diluxone_users_notice( __( 'Saved.', 'diluxone-users' ) );
			}

			echo '<form method="post">';
			wp_nonce_field( 'diluxone_users_appearance', 'diluxone_users_appearance_nonce' );

			if ( 'photo' === $current ) {
				diluxone_users_screen_appearance_photo();
			} else {
				diluxone_users_screen_appearance_styles();
			}

			submit_button();
			echo '</form>';

			// Outside the form: the account area has forms of its own, and a
			// form inside a form is thrown away by the browser.
			if ( 'photo' !== $current ) {
				diluxone_users_style_preview();
			}

			break;

		default:
			diluxone_users_screen_account_sections();
	}

	diluxone_users_screen_close();
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

	if ( isset( $_POST['diluxone_users_layout_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_layout_nonce'] ) ), 'diluxone_users_layout' ) ) {
		diluxone_users_save_options(
			array(
				'diluxone_users_account_page' => absint( wp_unslash( $_POST['diluxone_users_account_page'] ?? 0 ) ),
				'diluxone_users_wp_profile'   => sanitize_key( wp_unslash( $_POST['diluxone_users_wp_profile'] ?? 'allow' ) ),
				'diluxone_users_admin_bar'    => 'hide' === sanitize_key( wp_unslash( $_POST['diluxone_users_admin_bar'] ?? 'wp' ) ) ? 'hide' : 'wp',
				'diluxone_users_bar_account'  => isset( $_POST['diluxone_users_bar_account'] ) ? 1 : 0,
			) + diluxone_users_scope_posted( 'diluxone_users_wp_profile' )
				+ diluxone_users_scope_posted( 'diluxone_users_admin_bar' )
		);

			// The page changed: the /account/<section>/ rules have to be rebuilt.
		delete_option( 'diluxone_users_rewrite_version' );

		wp_safe_redirect(
			diluxone_users_admin_url(
				'diluxone-users-account',
				array(
					'tab'                => 'layout',
					'diluxone_users_msg' => 'guardada',
				)
			)
		);
		exit;
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
								'aside'   => __( 'In a column at the side, next to it', 'diluxone-users' ),
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
						<p class="description"><?php esc_html_e( 'The column at the side appears only when there is something in it, and drops under the content on a narrow screen. It is the place for what goes with the section without being it: a summary, an activity panel, a reminder.', 'diluxone-users' ); ?></p>
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

/** Where the account area lives and how it is navigated. */
function diluxone_users_screen_account_layout(): void {
	diluxone_users_intro( __( 'Which page is “my account”, and whether the dashboard is still a second place to edit the same data.', 'diluxone-users' ) );
	?>
	<form method="post">
		<?php wp_nonce_field( 'diluxone_users_layout', 'diluxone_users_layout_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="diluxone_users_account_page"><?php esc_html_e( 'The account page', 'diluxone-users' ); ?></label></th>
				<td>
					<?php
					/*
					 * The array goes through a variable because the typing of
					 * wp_dropdown_pages() does not include `option_none_value`,
					 * which WordPress does accept and which is what makes "none"
					 * worth 0 instead of -1.
					 */
					/** @var array<string, mixed> $diluxone_users_dropdown */
					$diluxone_users_dropdown = array(
						'name'              => 'diluxone_users_account_page',
						'id'                => 'diluxone_users_account_page',
						'selected'          => (int) diluxone_users_account_page_id(),
						'show_option_none'  => __( '— none —', 'diluxone-users' ),
						'option_none_value' => 0,
					);

						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapes its own and prints it.
					wp_dropdown_pages( $diluxone_users_dropdown );
					?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: the shortcode, literal */
							esc_html__( 'The page with %s in it. Declaring it here is what lets everything else —a certificate, a course, a forum— send people to the right place.', 'diluxone-users' ),
							'<code>[diluxone_users_account]</code>'
						);
						?>
					</p>
					<?php if ( '' === (string) get_option( 'permalink_structure' ) ) : ?>
						<p class="description"><?php esc_html_e( 'With plain permalinks the sections go as ?seccion=…; turn on pretty permalinks in Settings → Permalinks and they become /page/section/ on their own.', 'diluxone-users' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'The dashboard profile', 'diluxone-users' ); ?></th>
				<td>
					<?php
					/*
					 * WordPress has its own screen for the same data, at
					 * /wp-admin/profile.php. With an account area on the front
					 * end the site has two of them, and the dashboard one does
					 * not know about the required fields or the edit limits
					 * set up here. This is what happens to that screen.
					 */
					$profile = array(
						'allow'    => __( 'Leave it as WordPress ships it', 'diluxone-users' ),
						'redirect' => __( 'Send people to their account on the site instead', 'diluxone-users' ),
						'block'    => __( 'Close it — their details are only edited on the site', 'diluxone-users' ),
					);

					foreach ( $profile as $key => $label ) :
						?>
						<label class="diluxone-users-roles__item">
							<input type="radio" name="diluxone_users_wp_profile" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_wp_profile' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Two screens for the same data is how a site ends up with a person editing their name in one place and their phone in another, under different rules: what is required here is not required there, and a field that can only be changed twice can be changed for ever there.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'For whom', 'diluxone-users' ); ?></th>
				<td>
					<?php
					diluxone_users_scope_control(
						'diluxone_users_wp_profile',
						__( 'Anybody left out keeps the dashboard profile exactly as WordPress ships it.', 'diluxone-users' ),
						__( 'Whoever can edit users is never reached by this and is not on the list: they are the person who has to be able to fix what broke, and the dashboard profile is where it gets fixed. On a network, the super administrator.', 'diluxone-users' )
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'The black bar on top', 'diluxone-users' ); ?></th>
				<td>
					<?php
					/*
					 * The other half of the same question. The dashboard
					 * profile is where somebody edits their data; this is the
					 * bar that takes them there — and the last thing on the
					 * page that says "this is a WordPress install" to a person
					 * who came to read a course.
					 */
					$bar = array(
						'wp'   => __( 'Show it, as WordPress does', 'diluxone-users' ),
						'hide' => __( 'Hide it on the front of the site', 'diluxone-users' ),
					);

					foreach ( $bar as $key => $label ) :
						?>
						<label class="diluxone-users-roles__item">
							<input type="radio" name="diluxone_users_admin_bar" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_admin_bar' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>

					<div class="diluxone-users-pieces">
						<?php
						diluxone_users_scope_control(
							'diluxone_users_admin_bar',
							__( 'Who stops seeing it. Anybody left out keeps it.', 'diluxone-users' ),
							__( 'Whoever can edit users keeps it whatever is chosen, and is not on the list: taking the way into the dashboard off the screen of the person who administers the site is a setting that gets turned on once and puzzled over for an hour.', 'diluxone-users' )
						);
						?>
					</div>

					<label>
						<input type="checkbox" name="diluxone_users_bar_account" value="1" <?php checked( diluxone_users_option( 'diluxone_users_bar_account' ), 1 ); ?>>
						<?php esc_html_e( 'While it is shown, its user menu points at the account area', 'diluxone-users' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Their name, their picture and “Edit profile” lead to the account page instead of to the dashboard. Only those: the rest of that menu is the dashboard’s business.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<?php
}
