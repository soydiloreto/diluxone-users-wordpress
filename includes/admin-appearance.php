<?php
/**
 * How everything the plugin draws looks.
 *
 * These two tabs live inside the Account area screen. What they change reaches
 * further than that — the sign-in form, the fields, the sessions list — but the
 * account area is where nearly all of it is seen, and it is where somebody
 * changing a colour wants to be looking while they change it. A setting nobody
 * can find is worse than one filed slightly too far down.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Saves either of the two tabs.
 *
 * @param string $tab 'appearance' or 'photo'.
 */
function diluxone_users_screen_appearance_save( string $tab ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the caller verifies it.
	if ( 'photo' === $tab ) {
		diluxone_users_save_options(
			array(
				'diluxone_users_avatar_upload'   => isset( $_POST['diluxone_users_avatar_upload'] ) ? 1 : 0,
				'diluxone_users_avatar_gravatar' => isset( $_POST['diluxone_users_avatar_gravatar'] ) ? 1 : 0,
				'diluxone_users_avatar_initials' => isset( $_POST['diluxone_users_avatar_initials'] ) ? 1 : 0,
				'diluxone_users_avatar_max_kb'   => absint( wp_unslash( $_POST['diluxone_users_avatar_max_kb'] ?? 2048 ) ),
			)
		);

		return;
	}

	diluxone_users_save_options(
		array(
			'diluxone_users_styles'       => isset( $_POST['diluxone_users_styles'] ) ? 1 : 0,
			'diluxone_users_style_accent' => sanitize_hex_color( wp_unslash( $_POST['diluxone_users_style_accent'] ?? '' ) ) ?? '',
			'diluxone_users_style_radius' => sanitize_text_field( wp_unslash( $_POST['diluxone_users_style_radius'] ?? '' ) ),
		)
	);
	// phpcs:enable
}

/**
 * The ready-made looks.
 *
 * A preset is not a setting of its own — it writes the three that already
 * exist and then gets out of the way. Storing "which preset" alongside the
 * values it wrote would be two sources of truth for one thing, and the day
 * somebody nudges the colour by hand the stored answer starts lying.
 *
 * @return array<string, array{label: string, help: string, styles: int, accent: string, radius: string}>
 */
function diluxone_users_style_presets(): array {
	return array(
		'plain'   => array(
			'label'  => __( 'As it comes', 'diluxone-users' ),
			'help'   => __( 'The plugin stylesheet, untouched. A sober grey-and-blue that sits quietly in most themes.', 'diluxone-users' ),
			'styles' => 1,
			'accent' => '#2271b1',
			'radius' => '4',
		),
		// An empty accent means "leave the colour alone". A preset that carried
		// a colour of its own would be this plugin's taste walking into
		// somebody else's site, and the one thing a site always has already is
		// a colour.
		'rounded' => array(
			'label'  => __( 'Rounded', 'diluxone-users' ),
			'help'   => __( 'Your colour, with bigger corners — for a site whose own cards and buttons are round.', 'diluxone-users' ),
			'styles' => 1,
			'accent' => '',
			'radius' => '14',
		),
		'square'  => array(
			'label'  => __( 'Square', 'diluxone-users' ),
			'help'   => __( 'Your colour, with no corners at all — for a site with straight edges.', 'diluxone-users' ),
			'styles' => 1,
			'accent' => '',
			'radius' => '0',
		),
		'theme'   => array(
			'label'  => __( 'Leave it to the theme', 'diluxone-users' ),
			'help'   => __( 'The stylesheet is not loaded at all. The markup comes out bare and your theme dresses it — the most work, and the only way to match a design exactly.', 'diluxone-users' ),
			'styles' => 0,
			'accent' => '',
			'radius' => '',
		),
	);
}

/**
 * What the account area looks like with the values on this screen.
 *
 * The real markup and the real classes, so the preview cannot drift from the
 * thing it previews — the same rule the social buttons preview follows. The
 * colours travel as inline custom properties, which is exactly how they travel
 * on the front end, and the admin script rewrites them as the fields change.
 */
function diluxone_users_style_preview(): void {
	?>
	<div class="diluxone-users-preview" data-diluxone-users-preview-box>
		<p class="description"><?php esc_html_e( 'Your account area, with what is chosen above:', 'diluxone-users' ); ?></p>

		<div class="diluxone-users-preview__frame">
			<div class="diluxone-users-account" data-diluxone-users-preview-skin>
				<div class="diluxone-users-account__header">
					<div class="diluxone-users-account__avatar" aria-hidden="true">PD</div>
					<div>
						<div class="diluxone-users-account__name"><?php echo esc_html( wp_get_current_user()->display_name ); ?></div>
						<div class="diluxone-users-account__since"><?php esc_html_e( 'Member since March 2026', 'diluxone-users' ); ?></div>
					</div>
				</div>

				<div class="diluxone-users-account__nav">
					<span class="diluxone-users-account__tab is-current"><?php esc_html_e( 'Home', 'diluxone-users' ); ?></span>
					<span class="diluxone-users-account__tab"><?php esc_html_e( 'Your details', 'diluxone-users' ); ?></span>
					<span class="diluxone-users-account__tab"><?php esc_html_e( 'Security', 'diluxone-users' ); ?></span>
				</div>

				<div class="diluxone-users-account__body">
					<div class="diluxone-users-field">
						<label class="diluxone-users-label"><?php esc_html_e( 'First name', 'diluxone-users' ); ?></label>
						<input type="text" value="<?php echo esc_attr( wp_get_current_user()->first_name ); ?>" readonly>
					</div>
					<p><button type="button" class="diluxone-users-button"><?php esc_html_e( 'Save', 'diluxone-users' ); ?></button></p>
				</div>
			</div>
		</div>

		<p class="description" data-diluxone-users-preview-bare hidden>
			<?php esc_html_e( 'With the stylesheet off this is what your theme receives: plain markup with the diluxone-users-* classes on it, and nothing else.', 'diluxone-users' ); ?>
		</p>
	</div>
	<?php
}

/** The stylesheet, the colour and the corners. */
function diluxone_users_screen_appearance_styles(): void {
	diluxone_users_intro( __( 'Everything the plugin draws —panels, forms, lists, buttons— takes its colours and its corners from a handful of CSS properties. Change those and everything follows; a site with its own design can point them at its own tokens from its stylesheet, without copying anything from here. It reaches further than this screen: the sign-in form and the fields follow the same properties.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Start from', 'diluxone-users' ); ?></th>
			<td>
				<div class="diluxone-users-presets">
					<?php foreach ( diluxone_users_style_presets() as $id => $preset ) : ?>
						<button
							type="button"
							class="button diluxone-users-presets__one"
							data-diluxone-users-preset="<?php echo esc_attr( $id ); ?>"
							data-diluxone-users-preset-styles="<?php echo esc_attr( (string) $preset['styles'] ); ?>"
							data-diluxone-users-preset-accent="<?php echo esc_attr( $preset['accent'] ); ?>"
							data-diluxone-users-preset-radius="<?php echo esc_attr( $preset['radius'] ); ?>"
							title="<?php echo esc_attr( $preset['help'] ); ?>"
						>
							<?php
							// The swatch shows the colour that preset would end up with:
							// its own when it carries one, and the site's when it does not.
							$chip = '' !== $preset['accent'] ? $preset['accent'] : diluxone_users_style_accent();
							?>
							<span class="diluxone-users-presets__chip" style="background: <?php echo esc_attr( 0 === $preset['styles'] ? '#f0f0f1' : $chip ); ?>; border-radius: <?php echo esc_attr( ( '' !== $preset['radius'] ? $preset['radius'] : '4' ) . 'px' ); ?>;" aria-hidden="true"></span>
							<?php echo esc_html( $preset['label'] ); ?>
						</button>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'A starting point, not a setting: pressing one fills in the three below, and from there everything is yours to change.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
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
	diluxone_users_style_preview();
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
