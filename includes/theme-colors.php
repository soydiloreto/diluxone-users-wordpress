<?php
/**
 * The colours of the active theme, borrowed.
 *
 * A users plugin lands inside somebody else's design, and until now the only
 * way for it to match was for the site to write CSS repointing every token by
 * hand. That is a stylesheet a site has to maintain, and it is the first
 * thing that goes stale.
 *
 * Themes already publish their palette: `theme.json` declares it and
 * WordPress hands it over through wp_get_global_settings(). So the plugin can
 * ask. Which colour of that palette plays which part is the one thing nobody
 * can know for certain — `primary` means the brand in most themes and
 * something else in some — so it is guessed where the slug says so and asked
 * where it does not, on a screen that shows the palette it found.
 *
 * Several themes, Astra among them, publish values that are themselves custom
 * properties: `var(--ast-global-color-0)`. Those are kept as they are rather
 * than resolved, and that is the better answer — the plugin then follows the
 * theme live, including whatever the theme does in dark mode.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The parts of the plugin a colour can play, and what each one is for.
 *
 * @return array<string, string>
 */
function diluxone_users_color_roles(): array {
	return array(
		'accent'      => __( 'Accent — buttons, links, the open section', 'diluxone-users' ),
		'accent-ink'  => __( 'On top of the accent — the text inside a filled button', 'diluxone-users' ),
		'text'        => __( 'Text', 'diluxone-users' ),
		'muted'       => __( 'Quiet text — captions and help', 'diluxone-users' ),
		'surface'     => __( 'Surface — the ground a panel is drawn on', 'diluxone-users' ),
		'surface-alt' => __( 'Second surface — a row that stands out from it', 'diluxone-users' ),
		'border'      => __( 'Lines', 'diluxone-users' ),
	);
}

/**
 * The active theme's palette, as WordPress hands it over.
 *
 * Only the theme's own. The twelve colours WordPress ships with are not the
 * theme's answer to anything, and offering them would be offering a palette
 * nobody chose.
 *
 * @return array<string, array<string, string>> Keyed by slug.
 */
function diluxone_users_theme_palette(): array {
	if ( ! function_exists( 'wp_get_global_settings' ) ) {
		return array();
	}

	$settings = wp_get_global_settings( array( 'color' ) );
	$palette  = array();

	foreach ( (array) ( $settings['palette']['theme'] ?? array() ) as $colour ) {
		$slug = sanitize_key( (string) ( $colour['slug'] ?? '' ) );
		$css  = diluxone_users_color_value( (string) ( $colour['color'] ?? '' ) );

		if ( '' === $slug || '' === $css ) {
			continue;
		}

		$palette[ $slug ] = array(
			'name'  => (string) ( $colour['name'] ?? $slug ),
			'color' => $css,
		);
	}

	/**
	 * Filters the palette the plugin borrows from.
	 *
	 * A theme that publishes no theme.json, or one that keeps its colours
	 * somewhere of its own, adds them here.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, string>> $palette Keyed by slug.
	 */
	return (array) apply_filters( 'diluxone_users_theme_palette', $palette );
}

/**
 * A colour value that is safe to print into a stylesheet.
 *
 * Four shapes and nothing else: a hex, an rgb/rgba, an hsl/hsla, and a custom
 * property. The last one is the one that matters — it is how a theme says
 * "whatever this is right now" — and it is also the one that would carry
 * anything at all if it were not checked.
 */
function diluxone_users_color_value( string $value ): string {
	$value = trim( $value );

	if ( '' === $value ) {
		return '';
	}

	if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
		return $value;
	}

	if ( preg_match( '/^(rgb|rgba|hsl|hsla)\(\s*[0-9a-z%.,\s\/-]+\)$/i', $value ) ) {
		return $value;
	}

	if ( preg_match( '/^var\(\s*--[a-z0-9-]+\s*\)$/i', $value ) ) {
		return $value;
	}

	return '';
}

/**
 * Which colour of the palette plays which part, guessed.
 *
 * By the slug, and only where the slug says so. Themes that follow the names
 * WordPress suggests — base, contrast, primary, accent — are mapped without
 * anybody being asked. Themes that number their colours are not guessed at:
 * an accent chosen by counting would be wrong on half the sites and there
 * would be no way to tell which half.
 *
 * @param array<string, array<string, string>> $palette
 * @return array<string, string> Role => slug.
 */
function diluxone_users_color_guess( array $palette ): array {
	$by_role = array(
		'accent'      => array( 'primary', 'accent', 'accent-1', 'brand', 'link' ),
		'accent-ink'  => array( 'base', 'background', 'white' ),
		'text'        => array( 'contrast', 'foreground', 'text', 'body' ),
		'muted'       => array( 'contrast-2', 'contrast-3', 'muted', 'secondary' ),
		'surface'     => array( 'base', 'background', 'white' ),
		'surface-alt' => array( 'base-2', 'background-alt', 'surface', 'light' ),
		'border'      => array( 'border', 'contrast-3', 'base-3' ),
	);

	$map = array();

	foreach ( $by_role as $role => $slugs ) {
		foreach ( $slugs as $slug ) {
			if ( isset( $palette[ $slug ] ) ) {
				$map[ $role ] = $slug;
				break;
			}
		}
	}

	return $map;
}

/**
 * The mapping in force: what the site chose, over what was guessed.
 *
 * @return array<string, string> Role => slug.
 */
function diluxone_users_color_map(): array {
	$palette = diluxone_users_theme_palette();
	$stored  = (array) diluxone_users_option( 'diluxone_users_color_map' );
	$map     = diluxone_users_color_guess( $palette );

	foreach ( $stored as $role => $slug ) {
		$role = sanitize_key( (string) $role );
		$slug = sanitize_key( (string) $slug );

		if ( ! isset( diluxone_users_color_roles()[ $role ] ) ) {
			continue;
		}

		// An empty answer is an answer: it means "leave this one to the
		// plugin", and it has to be able to beat the guess.
		$map[ $role ] = isset( $palette[ $slug ] ) ? $slug : '';
	}

	return array_filter( $map );
}

/** Is the plugin taking its colours from the theme? */
function diluxone_users_colors_from_theme(): bool {
	return 'theme' === (string) diluxone_users_option( 'diluxone_users_colors' )
		&& array() !== diluxone_users_theme_palette();
}

/**
 * The theme's colours, as the plugin's properties.
 *
 * A part nobody mapped is left alone rather than guessed at twice: the
 * plugin's own colour is a reasonable colour, and a wrong one taken from the
 * theme is worse than a neutral one that was never claimed to match.
 */
function diluxone_users_theme_colors_css(): string {
	if ( ! diluxone_users_colors_from_theme() ) {
		return '';
	}

	$palette = diluxone_users_theme_palette();
	$tokens  = '';

	foreach ( diluxone_users_color_map() as $role => $slug ) {
		if ( ! isset( $palette[ $slug ] ) ) {
			continue;
		}

		$tokens .= '--diluxone-users-' . $role . ':' . $palette[ $slug ]['color'] . ';';

		// The filled button takes the same colour as the accent unless the
		// site said otherwise, which is what the sheet does on its own.
		if ( 'accent' === $role ) {
			$tokens .= '--diluxone-users-accent-bg:' . $palette[ $slug ]['color'] . ';';
		}
	}

	return '' === $tokens ? '' : ':root{' . $tokens . '}';
}
