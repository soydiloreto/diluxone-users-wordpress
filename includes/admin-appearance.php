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
 * It is not a drawing of the account area: it is the account area. The same
 * shortcode the page runs is run here, so what is on this screen is what the
 * site serves — including a template the theme has replaced, which is the
 * thing a drawing could never show. A site that has put its own account.php
 * in its theme sees its own account.php here, and does not spend an evening
 * wondering why the site and this screen disagree.
 *
 * It renders into the preview's own document, which is where the colour and
 * the corners arrive as custom properties — the same way they arrive on the
 * front end.
 */
function diluxone_users_style_preview(): void {
	// The shortcode, not a copy of it. It renders for whoever is looking —
	// the sections they can see, their own name and picture — because an
	// account area shown with somebody else's data would be a different kind
	// of lie.
	echo do_shortcode( '[diluxone_users_account]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the shortcode escapes its own output.
}

/**
 * The two shapes the account area comes in.
 *
 * Like the style presets, a template is a starting point and not a lid:
 * pressing one fills in the pieces underneath and from there every one of
 * them is yours to change. Unlike the style presets, which of the two was
 * chosen IS stored — the shape is not the sum of its pieces, it is the
 * difference between a panel in the page and a band across the window.
 *
 * The shape of a row is documented rather than declared: it goes through a
 * filter, and what comes back from a filter is whatever somebody put there.
 * Every reader treats a missing key as absent.
 *
 * @return array<string, array<string, mixed>>
 */
function diluxone_users_account_templates(): array {
	$templates = array(
		'plain' => array(
			'label'     => __( 'Simple', 'diluxone-users' ),
			'help'      => __( 'A panel in the page, the way a settings screen looks. It sits quietly inside whatever the theme already draws.', 'diluxone-users' ),
			'layout'    => 'tabs',
			'nav_style' => 'pills',
			'header'    => 1,
			'avatar'    => 1,
			'since'     => 1,
			'action'    => 0,
			'width'     => 'contained',
		),
		'cover' => array(
			'label'     => __( 'With a cover', 'diluxone-users' ),
			'help'      => __( 'The person on a coloured band the full width of the window, with the menu in a bar of its own underneath. The way a profile looks.', 'diluxone-users' ),
			'layout'    => 'tabs',
			// Underlined and not filled, because a filled block directly under
			// a filled band is two blocks of colour fighting. It is filled in
			// here as a starting point, where it can be seen and changed —
			// not applied behind the back of the control that shows it.
			'nav_style' => 'underline',
			'header'    => 1,
			'avatar'    => 1,
			'since'     => 1,
			'action'    => 1,
			'width'     => 'contained',
		),
	);

	/**
	 * Filters the shapes the account area comes in.
	 *
	 * The id is printed as a class on the area — `diluxone-users-account--<id>`
	 * — and the rest of the row is what pressing it fills in. Anything that
	 * is not one of the plugin's own needs its own CSS.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $templates
	 */
	return (array) apply_filters( 'diluxone_users_account_templates', $templates );
}

/** The shape of the account area, and the pieces that shape is made of. */
function diluxone_users_screen_appearance_template(): void {
	$template = diluxone_users_account_template();
	?>
	<h2><?php esc_html_e( 'The account area', 'diluxone-users' ); ?></h2>
	<?php
	diluxone_users_intro( __( 'What the account area is shaped like, and what it is made of. The shape is a starting point: press one and the pieces underneath fill in, and from there each of them is yours.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Template', 'diluxone-users' ); ?></th>
			<td>
				<div class="diluxone-users-templates">
					<?php foreach ( diluxone_users_account_templates() as $id => $preset ) : ?>
						<label class="diluxone-users-templates__one <?php echo $id === $template ? 'is-chosen' : ''; ?>">
							<input type="radio" name="diluxone_users_account_template" value="<?php echo esc_attr( $id ); ?>" <?php checked( $id, $template ); ?>
								data-diluxone-users-template
								data-diluxone-users-template-pieces="<?php echo esc_attr( (string) wp_json_encode( $preset ) ); ?>">
							<span class="diluxone-users-templates__art diluxone-users-templates__art--<?php echo esc_attr( $id ); ?>" aria-hidden="true"></span>
							<span class="diluxone-users-templates__name"><?php echo esc_html( $preset['label'] ); ?></span>
							<span class="diluxone-users-templates__help"><?php echo esc_html( $preset['help'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'The header', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_account_header" value="1" <?php checked( diluxone_users_option( 'diluxone_users_account_header' ), 1 ); ?> data-diluxone-users-piece="header">
					<?php esc_html_e( 'Show it', 'diluxone-users' ); ?>
				</label>
				<div class="diluxone-users-pieces" data-diluxone-users-pieces>
					<label class="diluxone-users-roles__item">
						<input type="checkbox" name="diluxone_users_account_avatar" value="1" <?php checked( diluxone_users_option( 'diluxone_users_account_avatar' ), 1 ); ?> data-diluxone-users-piece="avatar">
						<?php esc_html_e( 'Their picture', 'diluxone-users' ); ?>
					</label>
					<label class="diluxone-users-roles__item">
						<input type="checkbox" name="diluxone_users_account_since" value="1" <?php checked( diluxone_users_option( 'diluxone_users_account_since' ), 1 ); ?> data-diluxone-users-piece="since">
						<?php esc_html_e( 'The month they joined', 'diluxone-users' ); ?>
					</label>
					<label class="diluxone-users-roles__item">
						<input type="checkbox" name="diluxone_users_account_action" value="1" <?php checked( diluxone_users_option( 'diluxone_users_account_action' ), 1 ); ?> data-diluxone-users-piece="action">
						<?php esc_html_e( 'A button to their details', 'diluxone-users' ); ?>
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'The name is always there: a header without it is a coloured band with a picture on it.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_account_cover"><?php esc_html_e( 'The cover colour', 'diluxone-users' ); ?></label></th>
			<td>
				<input type="color" id="diluxone_users_account_cover" name="diluxone_users_account_cover" value="<?php echo esc_attr( '' !== diluxone_users_account_cover() ? diluxone_users_account_cover() : diluxone_users_style_accent() ); ?>">
				<p class="description"><?php esc_html_e( 'Only the template with a cover uses it. Left as the accent colour it follows the accent, instead of becoming a second colour that drifts from the first.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'The menu', 'diluxone-users' ); ?></th>
			<td>
				<?php diluxone_users_forzado_aviso( 'diluxone_users_account_layout' ); ?>

				<?php
				$layouts = array(
					'tabs' => __( 'Tabs across the top', 'diluxone-users' ),
					'side' => __( 'A menu down the side', 'diluxone-users' ),
					'none' => __( 'No menu — the site places it with [diluxone_users_account_nav]', 'diluxone-users' ),
				);

				foreach ( $layouts as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_account_layout" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_account_layout' ), $key ); ?> data-diluxone-users-piece="layout">
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'What the menu looks like', 'diluxone-users' ); ?></th>
			<td>
				<?php
				/*
				 * Its own question, and not a consequence of the template.
				 * Until now the cover turned the menu into underlined tabs on
				 * its way past, so the same three sections changed shape
				 * because of a decision about the header.
				 */
				foreach ( diluxone_users_account_nav_styles() as $diluxone_users_key => $diluxone_users_style ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_account_nav_style" value="<?php echo esc_attr( $diluxone_users_key ); ?>" <?php checked( diluxone_users_account_nav_style(), $diluxone_users_key ); ?> data-diluxone-users-piece="nav_style">
						<strong><?php echo esc_html( $diluxone_users_style['label'] ); ?></strong>
						<span class="description"> — <?php echo esc_html( $diluxone_users_style['help'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'The content', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$widths = array(
					'contained' => __( 'Held to a reading column', 'diluxone-users' ),
					'full'      => __( 'As wide as the theme allows', 'diluxone-users' ),
				);

				foreach ( $widths as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_account_width" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_account_width(), $key ); ?> data-diluxone-users-piece="width">
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Forms are hard to read at full width; a list of courses is not. This holds the content only — a cover always runs edge to edge.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * The brand: what every screen the plugin draws takes its look from.
 *
 * It is not about one screen, which is why it is not on one: the sign-in
 * form, the account area, the fields and the buttons all read the same
 * properties, and a site that changes the accent here changes all of them.
 */
function diluxone_users_screen_design_brand(): void {
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
			<th scope="row"><?php esc_html_e( 'Your mark', 'diluxone-users' ); ?></th>
			<td>
				<?php
				diluxone_users_image_field(
					'diluxone_users_login_logo',
					__( 'Shown above the sign-in form. Somebody who arrived from an e-mail link should be able to tell whose site this is before typing their address into it.', 'diluxone-users' )
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_style_radius"><?php esc_html_e( 'Corners', 'diluxone-users' ); ?></label></th>
			<td>
				<input type="number" id="diluxone_users_style_radius" name="diluxone_users_style_radius" class="small-text" min="0" max="40" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_style_radius' ) ); ?>">
				<?php esc_html_e( 'pixels — empty for the default', 'diluxone-users' ); ?>
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
