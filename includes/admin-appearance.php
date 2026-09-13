<?php
/**
 * Appearance: how everything the plugin draws looks.
 *
 * A screen of its own and not a sign-in tab, which is where it used to be:
 * the stylesheet, the colour and the profile picture hold for the account
 * area, for the fields, for the sessions and for signing in. A setting that
 * rules over the whole plugin cannot live inside one of its parts.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** Screen appearance. */
function diluxone_users_screen_appearance(): void {
	$tabs = array(
		'styles' => __( 'Styles', 'diluxone-users' ),
		'photo'  => __( 'Profile photo', 'diluxone-users' ),
	);

	$current = diluxone_users_tab( $tabs );

	if ( isset( $_POST['diluxone_users_appearance_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_appearance_nonce'] ) ), 'diluxone_users_appearance' ) ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado arriba.
		if ( 'photo' === $current ) {
			diluxone_users_save_options(
				array(
					'diluxone_users_avatar_upload'   => isset( $_POST['diluxone_users_avatar_upload'] ) ? 1 : 0,
					'diluxone_users_avatar_gravatar' => isset( $_POST['diluxone_users_avatar_gravatar'] ) ? 1 : 0,
					'diluxone_users_avatar_initials' => isset( $_POST['diluxone_users_avatar_initials'] ) ? 1 : 0,
					'diluxone_users_avatar_max_kb'   => absint( wp_unslash( $_POST['diluxone_users_avatar_max_kb'] ?? 2048 ) ),
				)
			);
		} else {
			diluxone_users_save_options(
				array(
					'diluxone_users_styles'       => isset( $_POST['diluxone_users_styles'] ) ? 1 : 0,
					'diluxone_users_style_accent' => sanitize_hex_color( wp_unslash( $_POST['diluxone_users_style_accent'] ?? '' ) ) ?? '',
					'diluxone_users_style_radius' => sanitize_text_field( wp_unslash( $_POST['diluxone_users_style_radius'] ?? '' ) ),
				)
			);
		}
		// phpcs:enable

		diluxone_users_notice( __( 'Saved.', 'diluxone-users' ) );
	}

	diluxone_users_screen_open( __( 'Appearance', 'diluxone-users' ), 'diluxone-users-appearance', $tabs, $current );

	echo '<form method="post">';
	wp_nonce_field( 'diluxone_users_appearance', 'diluxone_users_appearance_nonce' );

	if ( 'photo' === $current ) {
		diluxone_users_screen_appearance_photo();
	} else {
		diluxone_users_screen_appearance_styles();
	}

	submit_button();
	echo '</form>';

	diluxone_users_screen_close();
}

/** The stylesheet, the colour and the corners. */
function diluxone_users_screen_appearance_styles(): void {
	diluxone_users_intro( __( 'Everything the plugin draws —panels, forms, lists, buttons— takes its colours and its corners from a handful of CSS properties. Change those two below and everything follows; a site with its own design can point them at its own tokens from its stylesheet, without copying anything from here.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Styles', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_styles" value="1" <?php checked( diluxone_users_option( 'diluxone_users_styles' ), 1 ); ?>>
					<?php esc_html_e( 'Load the plugin stylesheet', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Only turn it off if the site is going to style every diluxone-users-* class itself. Turned off, its panels and buttons come out bare and the site has to draw them; re-pointing the properties is almost always enough, and it survives the plugin adding a new component.', 'diluxone-users' ); ?></p>
				<p class="description"><code>--diluxone-users-accent</code> <code>--diluxone-users-surface</code> <code>--diluxone-users-border</code> <code>--diluxone-users-text</code> <code>--diluxone-users-muted</code> <code>--diluxone-users-radius</code> <code>--diluxone-users-control-h</code></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Dark mode', 'diluxone-users' ); ?></th>
			<td>
				<p class="description"><?php esc_html_e( 'There is none here, on purpose. Dark mode belongs to the site: a light site seen from a dark system used to end up with a light page and black panels. If the site has a dark mode, its own tokens change and these follow.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_style_accent"><?php esc_html_e( 'Accent colour', 'diluxone-users' ); ?></label></th>
			<td>
				<input type="color" id="diluxone_users_style_accent" name="diluxone_users_style_accent" value="<?php echo esc_attr( diluxone_users_style_accent() ); ?>">
				<p class="description"><?php esc_html_e( 'Buttons, the open tab, links and the drawn avatars.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_style_radius"><?php esc_html_e( 'Corners', 'diluxone-users' ); ?></label></th>
			<td>
				<input type="number" id="diluxone_users_style_radius" name="diluxone_users_style_radius" class="small-text" min="0" max="40" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_style_radius' ) ); ?>">
				<?php esc_html_e( 'pixels — empty for the default', 'diluxone-users' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Templates', 'diluxone-users' ); ?></th>
			<td>
				<p class="description"><?php esc_html_e( 'Copy any file from the plugin’s templates/ folder to your theme and edit it there:', 'diluxone-users' ); ?></p>
				<p><code><?php echo esc_html( 'wp-content/themes/' . get_stylesheet() . '/diluxone-users/' ); ?></code></p>
				<p class="description"><code>account.php</code> · <code>account-nav.php</code> · <code>account/*.php</code> · <code>login.php</code> · <code>fields.php</code> · <code>accounts.php</code> · <code>sessions.php</code></p>
			</td>
		</tr>
	</table>
	<?php
}

/** The three layers of the profile picture. */
function diluxone_users_screen_appearance_photo(): void {
	diluxone_users_intro( __( 'Three layers, in this order: the photo the person uploaded, then Gravatar, then their initials drawn on the accent colour. Turn off the ones you do not want.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Where it comes from', 'diluxone-users' ); ?></th>
			<td>
				<label class="diluxone-users-roles__item">
					<input type="checkbox" name="diluxone_users_avatar_upload" value="1" <?php checked( diluxone_users_option( 'diluxone_users_avatar_upload' ), 1 ); ?>>
					<?php esc_html_e( 'Let people upload their own', 'diluxone-users' ); ?>
				</label>
				<label class="diluxone-users-roles__item">
					<input type="checkbox" name="diluxone_users_avatar_gravatar" value="1" <?php checked( diluxone_users_option( 'diluxone_users_avatar_gravatar' ), 1 ); ?>>
					<?php esc_html_e( 'Fall back to Gravatar when there is none', 'diluxone-users' ); ?>
				</label>
				<label class="diluxone-users-roles__item">
					<input type="checkbox" name="diluxone_users_avatar_initials" value="1" <?php checked( diluxone_users_option( 'diluxone_users_avatar_initials' ), 1 ); ?>>
					<?php esc_html_e( 'Otherwise, draw their initials', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Gravatar means sending a hash of every visitor’s email address to a third party. With it off and initials on, nothing leaves the site.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_avatar_max_kb"><?php esc_html_e( 'Largest photo accepted', 'diluxone-users' ); ?></label></th>
			<td>
				<input type="number" id="diluxone_users_avatar_max_kb" name="diluxone_users_avatar_max_kb" class="small-text" min="64" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_avatar_max_kb' ) ); ?>"> KB
			</td>
		</tr>
	</table>
	<?php
}
