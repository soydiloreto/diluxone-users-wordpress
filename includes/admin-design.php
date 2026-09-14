<?php
/**
 * Everything the plugin draws, in one place, with what it draws beside it.
 *
 * The look used to live wherever the setting it belonged to lived: the shape
 * of the sign-in page on the sign-in screen, the account template on the
 * account screen, the buttons on the social screen, the colours on whichever
 * of them you opened first. Seven places for one question, and no way to see
 * the result as a whole — which is how a site ends up with a blue sign-in
 * page and a green account area.
 *
 * Its tabs are registered, not listed, so a tab exists because something
 * registered it. That is what lets a feature live in its own file and take
 * its tab with it when it goes.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

const DILUXONE_USERS_DESIGN = 'diluxone-users-design';

/** The design screen. */
function diluxone_users_screen_design(): void {
	diluxone_users_screen_panels( DILUXONE_USERS_DESIGN, diluxone_users_screens()[ DILUXONE_USERS_DESIGN ] );
}

/* ── Your brand ────────────────────────────────────────────────────── */


/** Saves it. */
function diluxone_users_design_brand_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_styles'       => isset( $_POST['diluxone_users_styles'] ) ? 1 : 0,
			'diluxone_users_style_accent' => sanitize_hex_color( wp_unslash( $_POST['diluxone_users_style_accent'] ?? '' ) ) ?? '',
			'diluxone_users_style_radius' => sanitize_text_field( wp_unslash( $_POST['diluxone_users_style_radius'] ?? '' ) ),
			'diluxone_users_login_logo'   => absint( wp_unslash( $_POST['diluxone_users_login_logo'] ?? 0 ) ),
		)
	);
	// phpcs:enable
}

/* ── Sign in ───────────────────────────────────────────────────────── */


/** The shape of the sign-in page and the words on it. */
function diluxone_users_design_login(): void {
	diluxone_users_screen_login_shape();
	diluxone_users_screen_login_words();
}

/** Saves it. */
function diluxone_users_design_login_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_login_template' => sanitize_key( wp_unslash( $_POST['diluxone_users_login_template'] ?? 'plain' ) ),
			'diluxone_users_login_side'     => 'right' === sanitize_key( wp_unslash( $_POST['diluxone_users_login_side'] ?? 'left' ) ) ? 'right' : 'left',
			'diluxone_users_login_image'    => absint( wp_unslash( $_POST['diluxone_users_login_image'] ?? 0 ) ),
			'diluxone_users_login_title'    => sanitize_text_field( wp_unslash( $_POST['diluxone_users_login_title'] ?? '' ) ),
			'diluxone_users_login_intro'    => sanitize_text_field( wp_unslash( $_POST['diluxone_users_login_intro'] ?? '' ) ),
			// Links allowed, and only links: the terms and the privacy policy
			// are pages, and a legal line that cannot point at them is not one.
			'diluxone_users_login_legal'    => wp_kses_post( wp_unslash( $_POST['diluxone_users_login_legal'] ?? '' ) ),
			'diluxone_users_sent_title'     => sanitize_text_field( wp_unslash( $_POST['diluxone_users_sent_title'] ?? '' ) ),
			'diluxone_users_sent_note'      => sanitize_text_field( wp_unslash( $_POST['diluxone_users_sent_note'] ?? '' ) ),
		)
	);
	// phpcs:enable
}

/* ── The account area ──────────────────────────────────────────────── */


/** Saves it. */
function diluxone_users_design_account_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_account_template' => 'cover' === sanitize_key( wp_unslash( $_POST['diluxone_users_account_template'] ?? '' ) ) ? 'cover' : 'plain',
			'diluxone_users_account_layout'   => sanitize_key( wp_unslash( $_POST['diluxone_users_account_layout'] ?? 'tabs' ) ),
			'diluxone_users_account_width'    => 'full' === sanitize_key( wp_unslash( $_POST['diluxone_users_account_width'] ?? '' ) ) ? 'full' : 'contained',
			'diluxone_users_account_header'   => isset( $_POST['diluxone_users_account_header'] ) ? 1 : 0,
			'diluxone_users_account_avatar'   => isset( $_POST['diluxone_users_account_avatar'] ) ? 1 : 0,
			'diluxone_users_account_since'    => isset( $_POST['diluxone_users_account_since'] ) ? 1 : 0,
			'diluxone_users_account_action'   => isset( $_POST['diluxone_users_account_action'] ) ? 1 : 0,
			'diluxone_users_account_cover'    => sanitize_hex_color( wp_unslash( $_POST['diluxone_users_account_cover'] ?? '' ) ) ?? '',
		)
	);
	// phpcs:enable
}

/* ── Social buttons ────────────────────────────────────────────────── */


/** How the buttons look. It brings its own two columns. */
function diluxone_users_design_social(): void {
	diluxone_users_screen_social_buttons( diluxone_users_sso_providers() );
}

/** Saves them. */
function diluxone_users_design_social_save(): void {
	diluxone_users_save_options( diluxone_users_sso_buttons_posted() );
}

/* ── Profile photo ─────────────────────────────────────────────────── */


/** Saves it. */
function diluxone_users_design_photo_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_avatar_upload'   => isset( $_POST['diluxone_users_avatar_upload'] ) ? 1 : 0,
			'diluxone_users_avatar_gravatar' => isset( $_POST['diluxone_users_avatar_gravatar'] ) ? 1 : 0,
			'diluxone_users_avatar_initials' => isset( $_POST['diluxone_users_avatar_initials'] ) ? 1 : 0,
			'diluxone_users_avatar_max_kb'   => absint( wp_unslash( $_POST['diluxone_users_avatar_max_kb'] ?? 2048 ) ),
		)
	);
	// phpcs:enable
}

/* ── WordPress's own screen ────────────────────────────────────────── */


/** Saves it. */
function diluxone_users_design_wp_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_wp_login_brand' => isset( $_POST['diluxone_users_wp_login_brand'] ) ? 1 : 0,
			'diluxone_users_wp_login_logo'  => absint( wp_unslash( $_POST['diluxone_users_wp_login_logo'] ?? 0 ) ),
			'diluxone_users_wp_login_bg'    => sanitize_hex_color( wp_unslash( $_POST['diluxone_users_wp_login_bg'] ?? '' ) ) ?? '',
		)
	);
	// phpcs:enable
}

/**
 * Its tabs, registered when the menu is being built.
 *
 * All of them in one place and on the hook, so an add-on adding a seventh is
 * doing exactly what the plugin does for its own six.
 */
function diluxone_users_design_panels(): void {
	diluxone_users_register_panel(
		DILUXONE_USERS_DESIGN,
		'brand',
		array(
			'label'    => __( 'Your brand', 'diluxone-users' ),
			'position' => 10,
			'render'   => 'diluxone_users_screen_design_brand',
			'preview'  => 'diluxone_users_style_preview',
			'save'     => 'diluxone_users_design_brand_save',
		)
	);
	diluxone_users_register_panel(
		DILUXONE_USERS_DESIGN,
		'login',
		array(
			// The tabs are named after the screens whose look they hold, so
			// "the sign-in design" and "the sign-in settings" are obviously
			// the same thing seen twice.
			'label'    => diluxone_users_screens()['diluxone-users-login'],
			'position' => 20,
			'render'   => 'diluxone_users_design_login',
			'preview'  => 'diluxone_users_login_preview',
			'save'     => 'diluxone_users_design_login_save',
		)
	);
	diluxone_users_register_panel(
		DILUXONE_USERS_DESIGN,
		'account',
		array(
			'label'    => diluxone_users_screens()['diluxone-users-account'],
			'position' => 30,
			'render'   => 'diluxone_users_screen_appearance_template',
			'preview'  => 'diluxone_users_style_preview',
			'save'     => 'diluxone_users_design_account_save',
		)
	);
	diluxone_users_register_panel(
		DILUXONE_USERS_DESIGN,
		'social',
		array(
			'label'    => diluxone_users_screens()['diluxone-users-social'],
			'position' => 40,
			'render'   => 'diluxone_users_design_social',
			'save'     => 'diluxone_users_design_social_save',
		)
	);
	diluxone_users_register_panel(
		DILUXONE_USERS_DESIGN,
		'photo',
		array(
			'label'    => __( 'Profile photo', 'diluxone-users' ),
			'position' => 50,
			'render'   => 'diluxone_users_screen_appearance_photo',
			'save'     => 'diluxone_users_design_photo_save',
		)
	);
	diluxone_users_register_panel(
		DILUXONE_USERS_DESIGN,
		'wp',
		array(
			'label'    => __( 'The WordPress screen', 'diluxone-users' ),
			'position' => 60,
			'render'   => 'diluxone_users_screen_login_wp',
			'preview'  => 'diluxone_users_wp_login_preview',
			'save'     => 'diluxone_users_design_wp_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_design_panels' );
