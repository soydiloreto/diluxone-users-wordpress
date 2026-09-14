<?php
/**
 * The social-login screen: the provider grid and each one's detail.
 *
 * It is built like Nextend's on purpose: whoever has configured networks on a
 * WordPress before recognises the road — one card per network, and inside it
 * "getting started", the settings and the usage — and does not have to learn
 * another one.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** Screen social. */
function diluxone_users_screen_social(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$id        = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';
	$providers = diluxone_users_sso_providers();

	if ( '' !== $id && isset( $providers[ $id ] ) ) {
		diluxone_users_screen_provider( $id, $providers[ $id ] );
		return;
	}

	// Turning on or off from the grid, without going into the detail.
	if ( isset( $_GET['diluxone_users_action'], $_GET['red'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'diluxone_users_social_toggle' );

		$network = sanitize_key( wp_unslash( $_GET['red'] ) );

		if ( isset( $providers[ $network ] ) && diluxone_users_sso_tested( $network ) ) {
			$all = (array) get_option( 'diluxone_users_sso', array() );

			$all[ $network ]['active'] = 'on' === sanitize_key( wp_unslash( $_GET['diluxone_users_action'] ) ) ? 1 : 0;

			update_option( 'diluxone_users_sso', $all );
		}

		wp_safe_redirect( diluxone_users_admin_url( 'diluxone-users-social' ) );
		exit;
	}

	// What the buttons look like is not here: it is on the Design screen with
	// everything else the plugin draws. This screen answers who can get in
	// with a network and how the accounts are joined up.
	$tabs = array(
		'providers' => __( 'Providers', 'diluxone-users' ),
		'general'   => __( 'Global settings', 'diluxone-users' ),
	);

	$current = diluxone_users_tab( $tabs );

	if ( isset( $_POST['diluxone_users_social_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_social_nonce'] ) ), 'diluxone_users_social' ) ) {
		diluxone_users_save_options(
			array(
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
				'diluxone_users_sso_link_by_email' => isset( $_POST['diluxone_users_sso_link_by_email'] ) ? 1 : 0,
				'diluxone_users_sso_register'      => isset( $_POST['diluxone_users_sso_register'] ) ? 1 : 0,
				'diluxone_users_sso_verified_only' => isset( $_POST['diluxone_users_sso_verified_only'] ) ? 1 : 0,
				'diluxone_users_sso_blocked_roles' => array_map( 'sanitize_key', (array) wp_unslash( $_POST['diluxone_users_sso_blocked_roles'] ?? array() ) ),
				// phpcs:enable
			)
		);

		diluxone_users_notice( __( 'Settings saved.', 'diluxone-users' ) );
	}

	diluxone_users_screen_open( __( 'Social login', 'diluxone-users' ), 'diluxone-users-social', $tabs, $current );

	if ( 'general' === $current ) {
		diluxone_users_screen_social_general();
		diluxone_users_screen_close();
		return;
	}

	diluxone_users_intro( __( 'One app per network: create it in the provider’s developer console, paste the client ID and the secret, and copy the redirect URL that each card shows. Then run the live test — a provider cannot be enabled until the round trip actually works.', 'diluxone-users' ) );
	?>
	<div class="diluxone-users-cards">
		<?php
		foreach ( $providers as $slug => $provider ) :
			$state = diluxone_users_sso_state( $slug );
			?>
			<div class="diluxone-users-card">
				<div class="diluxone-users-card__top" style="background: <?php echo esc_attr( $provider['color'] ); ?>">
					<span class="diluxone-users-card__mark">
						<?php
						// The network's own logo when there is one; its initial is only the fallback.
						$logo = diluxone_users_sso_icon( $slug );

						echo '' !== $logo
							? $logo // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own SVG, no data from outside.
							: esc_html( mb_substr( $provider['name'], 0, 1 ) );
						?>
					</span>
					<span class="diluxone-users-card__name"><?php echo esc_html( $provider['name'] ); ?></span>
				</div>
				<div class="diluxone-users-card__foot">
					<?php diluxone_users_sso_state_pill( $state ); ?>

					<span class="diluxone-users-card__actions">
						<?php if ( 'enabled' === $state || 'disabled' === $state ) : ?>
							<a class="button button-small"
								<?php
								$diluxone_users_toggle = diluxone_users_admin_url(
									'diluxone-users-social',
									array(
										'red' => $slug,
										'diluxone_users_action' => 'enabled' === $state ? 'off' : 'on',
									)
								);
								?>
								href="<?php echo esc_url( wp_nonce_url( $diluxone_users_toggle, 'diluxone_users_social_toggle' ) ); ?>">
								<?php echo 'enabled' === $state ? esc_html__( 'Disable', 'diluxone-users' ) : esc_html__( 'Enable', 'diluxone-users' ); ?>
							</a>
						<?php endif; ?>

						<a class="button button-small button-primary" href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-social', array( 'provider' => $slug ) ) ); ?>">
							<?php echo 'not-configured' === $state ? esc_html__( 'Get started', 'diluxone-users' ) : esc_html__( 'Settings', 'diluxone-users' ); ?>
						</a>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<h2><?php esc_html_e( 'Not included, on purpose', 'diluxone-users' ); ?></h2>
	<p class="diluxone-users-admin__intro">
		<?php esc_html_e( 'Apple signs its client secret with a JWT that has to be regenerated every six months and answers by POST; Steam does not use OAuth 2 at all and never returns an email address. Both need their own flow, so they are not here yet: a button that does not work is worse than no button.', 'diluxone-users' ); ?>
	</p>
	<?php
	diluxone_users_screen_close();
}

/** A provider's state pill. */
function diluxone_users_sso_state_pill( string $state ): void {
	$labels = array(
		'not-configured' => array( 'blank', __( 'Not set up', 'diluxone-users' ) ),
		'not-tested'     => array( 'blank', __( 'Needs testing', 'diluxone-users' ) ),
		'disabled'       => array( 'off', __( 'Disabled', 'diluxone-users' ) ),
		'enabled'        => array( 'on', __( 'Active', 'diluxone-users' ) ),
	);

	[ $tone, $label ] = $labels[ $state ] ?? $labels['not-configured'];

	printf( '<span class="diluxone-users-pill diluxone-users-pill--%1$s">%2$s</span>', esc_attr( $tone ), esc_html( $label ) );
}

/**
 * How the buttons look.
 *
 * The preview is drawn with the real markup and the real stylesheet — not
 * with an imitation — and the admin script swaps its classes while choices
 * are made, so what is seen here is what people are going to see.
 *
 * @param array<string, array<string, mixed>> $providers Every provider.
 */
/**
 * What the buttons read back out of the form that draws them.
 *
 * @return array<string, mixed>
 */
function diluxone_users_sso_buttons_posted(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the caller verifies it.
	return array(
		'diluxone_users_sso_button_skin'    => sanitize_key( wp_unslash( $_POST['diluxone_users_sso_button_skin'] ?? 'brand' ) ),
		'diluxone_users_sso_button_shape'   => sanitize_key( wp_unslash( $_POST['diluxone_users_sso_button_shape'] ?? 'rounded' ) ),
		'diluxone_users_sso_button_show'    => sanitize_key( wp_unslash( $_POST['diluxone_users_sso_button_show'] ?? 'icon-text' ) ),
		'diluxone_users_sso_button_text'    => sanitize_text_field( wp_unslash( $_POST['diluxone_users_sso_button_text'] ?? '' ) ),
		'diluxone_users_sso_button_columns' => absint( wp_unslash( $_POST['diluxone_users_sso_button_columns'] ?? 2 ) ),
	);
	// phpcs:enable
}

/**
 * How the buttons look, on the Design screen.
 *
 * @param array<string, array<string, mixed>> $providers Every network, for the preview.
 */
function diluxone_users_screen_social_buttons( array $providers ): void {
	diluxone_users_intro( __( 'How the buttons look on the sign-in page. It is the same markup and the same stylesheet the site uses, so the preview is the real thing.', 'diluxone-users' ) );

	// A few are enough for the preview: the idea is to see the finish, not to
	// go over the whole list.
	$preview = array_slice( $providers, 0, 4, true );
	?>
	<?php // No form of its own: the Design screen it lives on provides one. ?>
	<div class="diluxone-users-buttons">

		<div class="diluxone-users-buttons__fields">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="diluxone_users_sso_button_skin"><?php esc_html_e( 'Finish', 'diluxone-users' ); ?></label></th>
					<td>
						<select id="diluxone_users_sso_button_skin" name="diluxone_users_sso_button_skin" data-diluxone-users-preview="skin">
							<?php foreach ( diluxone_users_sso_button_skins() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( diluxone_users_option( 'diluxone_users_sso_button_skin' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Google and Microsoft always stay white with their own logo: their brand guidelines ask for it, and a four-colour logo on a coloured background does not read.', 'diluxone-users' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="diluxone_users_sso_button_shape"><?php esc_html_e( 'Shape', 'diluxone-users' ); ?></label></th>
					<td>
						<select id="diluxone_users_sso_button_shape" name="diluxone_users_sso_button_shape" data-diluxone-users-preview="shape">
							<?php foreach ( diluxone_users_sso_button_shapes() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( diluxone_users_option( 'diluxone_users_sso_button_shape' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="diluxone_users_sso_button_show"><?php esc_html_e( 'What it shows', 'diluxone-users' ); ?></label></th>
					<td>
						<select id="diluxone_users_sso_button_show" name="diluxone_users_sso_button_show" data-diluxone-users-preview="show">
							<?php foreach ( diluxone_users_sso_button_contents() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( diluxone_users_option( 'diluxone_users_sso_button_show' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'With logo only, the name is still there for screen readers.', 'diluxone-users' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="diluxone_users_sso_button_columns"><?php esc_html_e( 'Layout', 'diluxone-users' ); ?></label></th>
					<td>
						<select id="diluxone_users_sso_button_columns" name="diluxone_users_sso_button_columns" data-diluxone-users-preview="cols">
							<?php foreach ( diluxone_users_sso_button_columns() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $key ); ?>" <?php selected( (int) diluxone_users_option( 'diluxone_users_sso_button_columns' ), (int) $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="diluxone_users_sso_button_text"><?php esc_html_e( 'Text', 'diluxone-users' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="diluxone_users_sso_button_text" name="diluxone_users_sso_button_text"
							value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_sso_button_text' ) ); ?>"
							<?php /* translators: %s: name of the social network */ ?>
							placeholder="<?php echo esc_attr( __( 'Continue with %s', 'diluxone-users' ) ); ?>">
						<p class="description">
							<?php
							printf(
								/* translators: %s: the %s placeholder, literal */
								esc_html__( 'Where %s goes, the name of the network goes. Leave it empty to use the default, which is already translated.', 'diluxone-users' ),
								'<code>%s</code>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</div>

		<div class="diluxone-users-buttons__preview">
			<h2><?php esc_html_e( 'Preview', 'diluxone-users' ); ?></h2>

			<div class="diluxone-users-buttons__backgrounds">
				<button type="button" class="diluxone-users-buttons__background" data-diluxone-users-background="light" aria-pressed="true"><?php esc_html_e( 'On white', 'diluxone-users' ); ?></button>
				<button type="button" class="diluxone-users-buttons__background" data-diluxone-users-background="dark" aria-pressed="false"><?php esc_html_e( 'On dark', 'diluxone-users' ); ?></button>
			</div>

			<div class="diluxone-users-buttons__canvas">
				<?php echo diluxone_users_sso_buttons( $preview, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own markup, already escaped. ?>
			</div>
			<p class="description"><?php esc_html_e( 'These buttons do nothing: they are here to be looked at.', 'diluxone-users' ); ?></p>
		</div>
	</div>
	<?php
}

/** The settings that hold for every network. */
function diluxone_users_screen_social_general(): void {
	diluxone_users_intro( __( 'These apply to every provider. They decide what happens when someone comes back from a social network.', 'diluxone-users' ) );
	?>
	<form method="post">
		<?php wp_nonce_field( 'diluxone_users_social', 'diluxone_users_social_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Recognise people by email', 'diluxone-users' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="diluxone_users_sso_link_by_email" value="1" <?php checked( diluxone_users_option( 'diluxone_users_sso_link_by_email' ), 1 ); ?>>
						<?php esc_html_e( 'If the email already exists, link the network to that account', 'diluxone-users' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'This is what makes signing in with Google today and with GitHub tomorrow land on the same account instead of creating two. Each network the person uses gets added to their account, and any of them works from then on.', 'diluxone-users' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Turned off, someone whose email is already registered simply cannot get in with a social network. Only turn it off if you do not trust the provider to verify its own users’ email addresses.', 'diluxone-users' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Create accounts', 'diluxone-users' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="diluxone_users_sso_register" value="1" <?php checked( diluxone_users_option( 'diluxone_users_sso_register' ), 1 ); ?>>
						<?php esc_html_e( 'If the email does not exist yet, create the account', 'diluxone-users' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Turned off, only people who already have an account can use the social buttons.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Verified email only', 'diluxone-users' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="diluxone_users_sso_verified_only" value="1" <?php checked( diluxone_users_option( 'diluxone_users_sso_verified_only' ), 1 ); ?>>
						<?php esc_html_e( 'Refuse the sign-in when the provider does not say the email is verified', 'diluxone-users' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Stricter, and a few providers never send that flag: with this on, those stop working.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Roles that cannot use it', 'diluxone-users' ); ?></th>
				<td>
					<?php
					$blocked = (array) diluxone_users_option( 'diluxone_users_sso_blocked_roles' );

					foreach ( wp_roles()->get_names() as $role => $label ) :
						?>
						<label class="diluxone-users-roles__item">
							<input type="checkbox" name="diluxone_users_sso_blocked_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $blocked, true ) ); ?>>
							<?php echo esc_html( translate_user_role( $label ) ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description">
						<?php esc_html_e( 'People with one of these roles have to use the email link. It is the account with the most power that is worth protecting: an administrator who signs in with Google depends on that Google account never being taken over.', 'diluxone-users' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Nobody is locked out by this: the email link is always there.', 'diluxone-users' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>

	<div class="diluxone-users-where">
		<p><strong><?php esc_html_e( 'How an account ends up, and how one person ends up with several networks', 'diluxone-users' ); ?></strong></p>
		<p>
			<?php
			printf(
					/* translators: 1: name of the role accounts are created with, 2: name of the screen where it is chosen */
				esc_html__( 'There is nothing to choose here, and that is on purpose: a new account gets the email address as its username, no password at all —not even one nobody knows how to use— and the role %1$s, which is set once for the whole site on the %2$s screen, because a role per provider would be a quiet way of handing out privileges. The name comes from the provider and only fills in what the person has not written themselves. Each linked network is stored on the person, so anybody can add a second and a third from their profile and unlink them again, and from then on any of them opens the same account.', 'diluxone-users' ),
				esc_html( translate_user_role( wp_roles()->get_names()[ (string) diluxone_users_option( 'diluxone_users_login_role' ) ] ?? (string) diluxone_users_option( 'diluxone_users_login_role' ) ) ),
				'«' . esc_html__( 'Registration', 'diluxone-users' ) . '»'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * A provider's detail, with tabs of its own.
 *
 * @param array<string, mixed> $provider
 */
function diluxone_users_screen_provider( string $id, array $provider ): void {
	$tabs = array(
		'start'    => __( 'Getting started', 'diluxone-users' ),
		'settings' => __( 'Settings', 'diluxone-users' ),
		'usage'    => __( 'Usage', 'diluxone-users' ),
	);

	$current = diluxone_users_tab( $tabs );

	if ( isset( $_POST['diluxone_users_provider_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_provider_nonce'] ) ), 'diluxone_users_provider' ) ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
		diluxone_users_sso_save_credentials(
			$id,
			array(
				'active' => isset( $_POST['diluxone_users_active'] ) ? 1 : 0,
				'id'     => sanitize_text_field( wp_unslash( $_POST['diluxone_users_client_id'] ?? '' ) ),
				'secret' => sanitize_text_field( wp_unslash( $_POST['diluxone_users_client_secret'] ?? '' ) ),
			)
		);
		// phpcs:enable

		diluxone_users_notice( __( 'Provider saved.', 'diluxone-users' ) );
	}

	$credentials = diluxone_users_sso_credentials( $id );
	$state       = diluxone_users_sso_state( $id );

	diluxone_users_screen_open( $provider['name'], 'diluxone-users-social', $tabs, $current, array( 'provider' => $id ) );
	?>
	<p><a href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-social' ) ); ?>">&larr; <?php esc_html_e( 'Back to all providers', 'diluxone-users' ); ?></a></p>
	<?php

	diluxone_users_screen_provider_state( $id, $provider, $state );

	if ( 'settings' === $current ) {
		?>
		<form method="post">
			<?php wp_nonce_field( 'diluxone_users_provider', 'diluxone_users_provider_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="diluxone_users_client_id"><?php esc_html_e( 'Client ID', 'diluxone-users' ); ?></label></th>
					<td><input type="text" class="large-text code" id="diluxone_users_client_id" name="diluxone_users_client_id" value="<?php echo esc_attr( $credentials['id'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="diluxone_users_client_secret"><?php esc_html_e( 'Secret', 'diluxone-users' ); ?></label></th>
					<td><input type="password" class="large-text code" id="diluxone_users_client_secret" name="diluxone_users_client_secret" value="<?php echo esc_attr( $credentials['secret'] ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'diluxone-users' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="diluxone_users_active" value="1" <?php checked( $credentials['active'] ); ?>>
							<?php esc_html_e( 'Show the button', 'diluxone-users' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	} elseif ( 'usage' === $current ) {
		diluxone_users_intro( __( 'The buttons are drawn by the sign-in shortcode, together with the email form. There is nothing else to place.', 'diluxone-users' ) );
		?>
		<table class="widefat striped diluxone-users-shortcodes">
			<tbody>
				<tr><td><code>[diluxone_users_login]</code></td><td><?php esc_html_e( 'The email sign-in form and the social buttons.', 'diluxone-users' ); ?></td></tr>
				<tr><td><code>[diluxone_users_accounts]</code></td><td><?php esc_html_e( 'Linked providers, to link or unlink.', 'diluxone-users' ); ?></td></tr>
			</tbody>
		</table>
		<p class="diluxone-users-admin__intro">
			<?php
			printf(
					/* translators: %s: provider name */
				esc_html__( 'A direct link to sign in with %s, if you want it somewhere else:', 'diluxone-users' ),
				esc_html( $provider['name'] )
			);
			?>
		</p>
		<p><code><?php echo esc_html( diluxone_users_sso_login_url( $id ) ); ?></code></p>
		<?php
	} else {
		diluxone_users_screen_provider_start( $id, $provider );
	}

	diluxone_users_screen_close();
}

/**
 * "Getting started": what to create, where, and which URL to paste.
 *
 * @param array<string, mixed> $provider
 */
function diluxone_users_screen_provider_start( string $id, array $provider ): void {
	$guide = diluxone_users_sso_guide( $id );
	?>
	<h2><?php esc_html_e( 'Getting started', 'diluxone-users' ); ?></h2>
	<p class="diluxone-users-admin__intro">
		<?php
		printf(
				/* translators: %s: provider name */
			esc_html__( 'To let people sign in with their %s account you have to create an app there. Below is the whole thing, click by click.', 'diluxone-users' ),
			esc_html( $provider['name'] )
		);
		?>
	</p>

	<div class="diluxone-users-url-redirect">
		<h3><?php esc_html_e( 'The URL they are going to ask you for', 'diluxone-users' ); ?></h3>
		<p><?php esc_html_e( 'Keep it at hand: one of the steps below asks for it, and it has to be pasted exactly as it is.', 'diluxone-users' ); ?></p>
		<input type="text" class="large-text code" readonly value="<?php echo esc_attr( diluxone_users_sso_redirect_uri( $id ) ); ?>" onclick="this.select();">
		<p class="description"><?php esc_html_e( 'Depending on the provider it is called redirect URI, callback URL, return URL or authorized redirect URL.', 'diluxone-users' ); ?></p>
	</div>

	<h3>
		<?php
		printf(
				/* translators: %s: provider name */
			esc_html__( 'Step by step in %s', 'diluxone-users' ),
			esc_html( $provider['name'] )
		);
		?>
	</h3>

	<p>
		<a class="button" href="<?php echo esc_url( $provider['console'] ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Open the console', 'diluxone-users' ); ?>
			<span class="dashicons dashicons-external" aria-hidden="true"></span>
		</a>
		<span class="description"><?php echo esc_html( $provider['console'] ); ?></span>
	</p>

	<ol class="diluxone-users-steps diluxone-users-steps--numbers">
		<?php foreach ( $guide['steps'] as $step ) : ?>
			<li><?php echo esc_html( $step ); ?></li>
		<?php endforeach; ?>
	</ol>

	<?php if ( '' !== $guide['gotcha'] ) : ?>
		<p class="diluxone-users-eye">
			<strong><?php esc_html_e( 'Watch out:', 'diluxone-users' ); ?></strong>
			<?php echo esc_html( $guide['gotcha'] ); ?>
		</p>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'The provider’s own documentation:', 'diluxone-users' ); ?>
		<a href="<?php echo esc_url( $provider['guide'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $provider['guide'] ); ?></a>
	</p>

	<h3><?php esc_html_e( 'And then, here', 'diluxone-users' ); ?></h3>
	<p><?php esc_html_e( 'Paste the client ID and the secret in Settings, run the live test, and turn the button on.', 'diluxone-users' ); ?></p>
	<p>
		<?php
		$diluxone_users_ajustes = diluxone_users_admin_url(
			'diluxone-users-social',
			array(
				'provider' => $id,
				'tab'      => 'settings',
			)
		);
		?>
		<a class="button button-primary" href="<?php echo esc_url( $diluxone_users_ajustes ); ?>">
			<?php esc_html_e( 'I already created the app', 'diluxone-users' ); ?>
		</a>
	</p>

	<h3><?php esc_html_e( 'What this provider asks for', 'diluxone-users' ); ?></h3>
	<table class="widefat striped diluxone-users-detail">
		<tbody>
			<tr><th><?php esc_html_e( 'Permissions requested', 'diluxone-users' ); ?></th><td><code><?php echo esc_html( $provider['scope'] ); ?></code></td></tr>
			<tr><th><?php esc_html_e( 'Authorization URL', 'diluxone-users' ); ?></th><td><code><?php echo esc_html( $provider['authorize'] ); ?></code></td></tr>
			<?php if ( ! empty( $provider['pkce'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'PKCE', 'diluxone-users' ); ?></th>
					<td><?php esc_html_e( 'Required by this provider. The plugin handles it; nothing to configure.', 'diluxone-users' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
	<?php
}

/**
 * The button that opens the live test.
 *
 * It is a real link, with a `target`: if the admin JavaScript did not load,
 * the test still opens in a tab. The window size is set by the script reading
 * `data-diluxone-users-popup`; an `onclick` in the markup would be no use,
 * because `wp_kses_post()` strips event handlers and the button was left
 * doing nothing.
 */
function diluxone_users_sso_test_button( string $id, string $state ): void {
	?>
	<a class="button <?php echo 'not-tested' === $state ? 'button-primary' : 'button-secondary'; ?>"
		href="<?php echo esc_url( diluxone_users_sso_test_url( $id ) ); ?>"
		target="diluxone-users-test"
		data-diluxone-users-popup="600x740">
		<?php
		echo 'not-tested' === $state
			? esc_html__( 'Run the live test', 'diluxone-users' )
			: esc_html__( 'Test it again', 'diluxone-users' );
		?>
	</a>
	<?php
}

/**
 * A provider's status box, with the live test.
 *
 * The test is the real round trip against the provider, in a separate window:
 * it is the only way of knowing that the ID, the secret and the callback URL
 * are right before the first person who cannot get in finds out. That is why
 * a provider cannot be turned on without having been tested.
 *
 * @param array<string, mixed> $provider
 */
function diluxone_users_screen_provider_state( string $id, array $provider, string $state ): void {
	if ( 'not-configured' === $state ) {
		?>
		<div class="notice notice-info inline diluxone-users-state-box">
			<p><strong><?php esc_html_e( 'Nothing loaded yet', 'diluxone-users' ); ?></strong></p>
			<p><?php esc_html_e( 'Create the app, load the redirect URL and paste the client ID and the secret in Settings.', 'diluxone-users' ); ?></p>
		</div>
		<?php
		return;
	}

	if ( 'not-tested' === $state ) {
		?>
		<div class="notice notice-warning inline diluxone-users-state-box">
			<p><strong><?php esc_html_e( 'This needs to be tested', 'diluxone-users' ); ?></strong></p>
			<p>
				<?php
				printf(
						/* translators: %s: provider name */
					esc_html__( 'A window opens, %s asks you to authorise, and it comes back here. Nobody is signed in and nothing is saved to your account — it only checks that the round trip works. Until it does, the button cannot be enabled.', 'diluxone-users' ),
					esc_html( $provider['name'] )
				);
				?>
			</p>
			<p><?php diluxone_users_sso_test_button( $id, $state ); ?></p>
		</div>
		<?php
		return;
	}
	?>
	<div class="notice notice-<?php echo 'enabled' === $state ? 'success' : 'info'; ?> inline diluxone-users-state-box">
		<p>
			<strong><?php esc_html_e( 'Tested and working', 'diluxone-users' ); ?></strong> —
			<?php
			echo 'enabled' === $state
				? esc_html__( 'the button is showing on the sign-in page.', 'diluxone-users' )
				: esc_html__( 'the button is not showing: it is disabled.', 'diluxone-users' );
			?>
		</p>
		<p>
			<?php diluxone_users_sso_test_button( $id, $state ); ?>
			<a class="button <?php echo 'enabled' === $state ? '' : 'button-primary'; ?>"
				<?php
				$diluxone_users_toggle = diluxone_users_admin_url(
					'diluxone-users-social',
					array(
						'red'                   => $id,
						'diluxone_users_action' => 'enabled' === $state ? 'off' : 'on',
					)
				);
				?>
				href="<?php echo esc_url( wp_nonce_url( $diluxone_users_toggle, 'diluxone_users_social_toggle' ) ); ?>">
				<?php echo 'enabled' === $state ? esc_html__( 'Disable', 'diluxone-users' ) : esc_html__( 'Enable', 'diluxone-users' ); ?>
			</a>
		</p>
	</div>
	<?php
}
