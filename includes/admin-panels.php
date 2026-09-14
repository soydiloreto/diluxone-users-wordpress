<?php
/**
 * The tabs of a screen, registered instead of written down.
 *
 * A screen used to carry its tabs in an array in its own file, which meant
 * the file had to know about every feature that might appear on it. Passkeys
 * lived inside the sign-in screen; the social buttons lived inside the social
 * screen; and taking one out meant editing a file that had nothing to do with
 * it.
 *
 * Now a feature registers its own panel from its own file. The screen asks
 * what there is and draws that. Two things follow, and the second is the
 * reason for the first:
 *
 *   - A feature that is not installed leaves no gap. Its file is not there,
 *     so it never registered, so the tab does not exist — rather than a tab
 *     that exists and apologises.
 *   - Anything can add one from outside: an add-on, a site's own plugin, a
 *     theme. The registry is the seam, and it is the same call the plugin
 *     uses for its own.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The registry itself.
 *
 * A static array and not an option: panels are code, and code that is not
 * loaded has no panel. Kept private — everything else goes through the two
 * functions below.
 *
 * @param string                    $screen Screen slug, or '' to read everything.
 * @param string                    $id     Panel id when writing.
 * @param array<string, mixed>|null $panel  The panel when writing, null when reading.
 * @return array<string, array<string, mixed>>
 */
function diluxone_users_panel_registry( string $screen = '', string $id = '', ?array $panel = null ): array {
	static $panels = array();

	if ( null !== $panel ) {
		$panels[ $screen ][ $id ] = $panel;

		return array();
	}

	return $panels[ $screen ] ?? array();
}

/**
 * Adds one tab to one screen.
 *
 * Call it on `admin_menu` at an early priority, which is what
 * `diluxone_users_panels_ready()` is for. Not at load time: the plugin loads
 * its files with a glob, so load time means alphabetical order, and a file
 * that sorts before this one would be calling a function that does not exist
 * yet. It did, and it took the whole site down with it.
 *
 * @param string                                                                                                   $screen Which screen, e.g. 'diluxone-users-login'.
 * @param string                                                                                                   $id     The tab's slug, which ends up in the URL.
 * @param array{label: string, render: callable, save?: callable, preview?: callable, position?: int, form?: bool} $panel
 */
function diluxone_users_register_panel( string $screen, string $id, array $panel ): void {
	diluxone_users_panel_registry(
		$screen,
		$id,
		wp_parse_args(
			$panel,
			array(
				'label'    => $id,
				'render'   => '',
				// A panel with nothing to save — a summary, a preview — says
				// so, and the screen leaves out the form and the button.
				'save'     => '',
				// What goes in the column beside the fields. A panel without
				// one runs the full width.
				'preview'  => '',
				'form'     => true,
				'position' => 50,
			)
		)
	);
}

/**
 * Every panel on a screen, in order.
 *
 * @param string $screen
 * @return array<string, array<string, mixed>>
 */
function diluxone_users_panels( string $screen ): array {
	$panels = diluxone_users_panel_registry( $screen );

	uasort( $panels, static fn( array $a, array $b ): int => $a['position'] <=> $b['position'] );

	/**
	 * Filters the panels of one screen, once they are in order.
	 *
	 * Registering is the way in; this is for taking one out or moving it.
	 *
	 * @param array<string, array<string, mixed>> $panels
	 * @param string                              $screen
	 */
	return (array) apply_filters( 'diluxone_users_panels', $panels, $screen );
}

/**
 * Draws a whole screen out of its panels.
 *
 * Every screen built this way behaves the same: the tabs, the current one,
 * its form, its own saving, its notice. A screen with one panel shows no
 * tabs — one tab is not navigation, it is furniture.
 *
 * @param string $screen Screen slug.
 * @param string $title  What the heading says.
 */
function diluxone_users_screen_panels( string $screen, string $title ): void {
	$panels = diluxone_users_panels( $screen );

	if ( array() === $panels ) {
		return;
	}

	$labels  = wp_list_pluck( $panels, 'label' );
	$current = diluxone_users_tab( $labels );
	$panel   = $panels[ $current ];

	if (
		is_callable( $panel['save'] )
		&& isset( $_POST['diluxone_users_panel_nonce'] )
		&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_panel_nonce'] ) ), 'diluxone_users_panel_' . $screen )
	) {
		call_user_func( $panel['save'] );
		diluxone_users_notice( __( 'Saved.', 'diluxone-users' ) );

		// Read again: what is on screen has to be what was just written.
		$panels = diluxone_users_panels( $screen );
		$panel  = $panels[ $current ];
	}

	diluxone_users_screen_open( $title, $screen, count( $labels ) > 1 ? $labels : array(), $current );

	$form    = ! empty( $panel['form'] ) && is_callable( $panel['save'] );
	$preview = is_callable( $panel['preview'] );

	/*
	 * With a preview, the two columns wrap the form rather than the other way
	 * round: the form lives inside the left-hand column and the preview sits
	 * outside it. That is what lets a preview of the account area — which is
	 * a form itself — sit beside the settings instead of underneath them. A
	 * form inside a form is thrown away by the browser; two siblings are not.
	 */
	if ( $preview ) {
		echo '<div class="diluxone-users-studio"><div class="diluxone-users-studio__fields">';
	}

	if ( $form ) {
		echo '<form method="post">';
		wp_nonce_field( 'diluxone_users_panel_' . $screen, 'diluxone_users_panel_nonce' );
	}

	if ( is_callable( $panel['render'] ) ) {
		call_user_func( $panel['render'] );
	}

	if ( $form ) {
		submit_button();
		echo '</form>';
	}

	if ( $preview ) {
		echo '</div><div class="diluxone-users-studio__preview">';
		call_user_func( $panel['preview'] );
		echo '</div></div>';
	}

	/**
	 * Fires after a panel has been drawn, inside the screen's wrapper.
	 *
	 * @param string $screen
	 */
	do_action( 'diluxone_users_after_panel_' . $current, $screen );

	diluxone_users_screen_close();
}

/**
 * The moment to register a panel.
 *
 * Every panel — the plugin's own and an add-on's — is registered here, on
 * `admin_menu` before the menu is built. By then every plugin has loaded, so
 * nothing depends on which file came first.
 */
function diluxone_users_panels_ready(): void {
	/**
	 * Fires when panels can be registered.
	 *
	 * @since 1.0.0
	 */
	do_action( 'diluxone_users_register_panels' );
}
add_action( 'admin_menu', 'diluxone_users_panels_ready', 1 );
