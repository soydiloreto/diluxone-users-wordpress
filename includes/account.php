<?php
/**
 * The account area: the section registry and the URLs.
 *
 * "My account" belongs neither to this site nor to this design: it belongs to
 * any WordPress with people in it. That is why it lives here and not in the
 * site plugin, and why the sections are a registry and not a `switch` —
 * LifterLMS adds its own, the site adds its own, and neither has to edit this
 * file.
 *
 * What decides what is seen and in what order is the sum of three things:
 *   1. what each party registers with diluxone_users_register_section(),
 *   2. what whoever administers turned on, turned off, renamed or reordered,
 *   3. the site's own sections, added from the admin.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** The rewrite endpoint: /account/<section>/ */
const DILUXONE_USERS_ACCOUNT_VAR = 'diluxone_users_section';

/**
 * Registers one section of the account area.
 *
 * @param string               $id   Identifier. It is what goes in the URL.
 * @param array<string, mixed> $args label, render, position, capability, source.
 */
function diluxone_users_register_section( string $id, array $args ): void {
	global $diluxone_users_sections;

	$diluxone_users_sections = is_array( $diluxone_users_sections ) ? $diluxone_users_sections : array();

	$diluxone_users_sections[ $id ] = wp_parse_args(
		$args,
		array(
			'label'      => $id,
			// Function that draws the section. It receives the WP_User.
			'render'     => '',
			// Lower goes first. Leaves gaps of 10 so things can be slotted in.
			'position'   => 50,
			// Empty: anybody with a session sees it.
			'capability' => '',
			// Where it came from, so the admin screen can say so.
			'source'     => __( 'System', 'diluxone-users' ),
			// The site's own can be deleted; the ones from code cannot.
			'custom'     => false,
			// Content written from the admin. In a section of the site's own it is
			// all there is; in a system one it adds to what the code draws.
			'content'    => '',
			// Where that content goes relative to what the code draws:
			// 'before', 'after' or 'replace'. With no code of its own it makes no difference.
			'placement'  => 'after',
			// Roles that see it. Empty: anybody with a session sees it.
			'roles'      => array(),
			// Optional: a function saying whether the section makes sense today.
			// "Linked accounts" with no network turned on has nothing to show,
			// and an empty section is worse than a section that is not there.
			'available'  => '',
			// Why it is not being shown, in plain words, for the admin.
			'why'        => '',
			// Optional: a function returning a card for the account front page.
			// That way each section builds the summary out of what it knows,
			// instead of a front page that has to know about all of them.
			'summary'    => '',
			// What goes in the URL. The identifier by default, but it is changed
			// from the admin: the identifier belongs to the code and is in
			// English; the address is read by people and is in the site language.
			'slug'       => $id,
		)
	);
}

/**
 * What whoever administers saved for one section.
 *
 * @return array<string, mixed>
 */
function diluxone_users_section_config( string $id ): array {
	$all = (array) diluxone_users_option( 'diluxone_users_account_sections' );

	return isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? $all[ $id ] : array();
}

/**
 * Every section, sorted and carrying what whoever administers decided.
 *
 * @param bool $all true to include the ones turned off (the admin needs it).
 * @return array<string, array<string, mixed>>
 */
function diluxone_users_sections( bool $all = false ): array {
	global $diluxone_users_sections;

	// The registry is filled on a hook so that whoever adds a section does not
	// depend on the order the plugins loaded in.
	do_action( 'diluxone_users_register_sections' );

	$sections = is_array( $diluxone_users_sections ) ? $diluxone_users_sections : array();

	// The site's own sections are in no code: they live in the option.
	foreach ( (array) diluxone_users_option( 'diluxone_users_account_sections' ) as $id => $config ) {
		if ( ! isset( $sections[ $id ] ) && ! empty( $config['custom'] ) ) {
			diluxone_users_register_section(
				(string) $id,
				array(
					'label'   => (string) ( $config['label'] ?? $id ),
					'source'  => __( 'Yours', 'diluxone-users' ),
					'custom'  => true,
					'content' => (string) ( $config['content'] ?? '' ),
				)
			);
		}
	}

	$sections = is_array( $diluxone_users_sections ) ? $diluxone_users_sections : array();

	foreach ( $sections as $id => $section ) {
		$config = diluxone_users_section_config( $id );

		if ( isset( $config['label'] ) && '' !== $config['label'] ) {
			$sections[ $id ]['label'] = (string) $config['label'];
		}

		if ( isset( $config['position'] ) && '' !== $config['position'] ) {
			$sections[ $id ]['position'] = (int) $config['position'];
		}

		if ( isset( $config['content'] ) ) {
			$sections[ $id ]['content'] = (string) $config['content'];
		}

		if ( isset( $config['placement'] ) && in_array( $config['placement'], array( 'before', 'after', 'replace' ), true ) ) {
			$sections[ $id ]['placement'] = (string) $config['placement'];
		}

		if ( isset( $config['roles'] ) ) {
			$sections[ $id ]['roles'] = array_values( array_filter( array_map( 'sanitize_key', (array) $config['roles'] ) ) );
		}

		if ( isset( $config['slug'] ) && '' !== $config['slug'] ) {
			$sections[ $id ]['slug'] = sanitize_title( (string) $config['slug'] );
		}

		$sections[ $id ]['enabled'] = ! isset( $config['enabled'] ) || (bool) $config['enabled'];
	}

	if ( ! $all ) {
		$sections = array_filter(
			$sections,
			static fn( array $s ): bool => $s['enabled']
				&& diluxone_users_section_available( $s )
				&& ( '' === $s['capability'] || current_user_can( $s['capability'] ) )
				&& diluxone_users_section_role_ok( $s )
		);
	}

	uasort( $sections, static fn( array $a, array $b ): int => $a['position'] <=> $b['position'] );

	/**
	 * Filters the account-area sections, already sorted.
	 *
	 * @param array<string, array<string, mixed>> $sections
	 * @param bool                                $all
	 */
	return apply_filters( 'diluxone_users_sections', $sections, $all );
}

/**
 * Does this section have anything to show today?
 *
 * It is the rule that makes what is turned off in the dashboard disappear
 * from the front end without anyone having to remember to turn the section
 * off too: if the site has no social network enabled, "Linked accounts" does
 * not exist.
 *
 * @param array<string, mixed> $section
 */
function diluxone_users_section_available( array $section ): bool {
	$check = $section['available'] ?? '';

	return '' === $check || ! is_callable( $check ) || (bool) call_user_func( $check );
}

/**
 * Is the role of whoever is looking enough to see this section?
 *
 * The empty list means "anybody with a session sees it": that is right for
 * almost everything in an account — your details, your security — and it
 * makes choosing roles a decision rather than a setup chore.
 *
 * @param array<string, mixed> $section
 */
function diluxone_users_section_role_ok( array $section ): bool {
	$roles = (array) ( $section['roles'] ?? array() );

	if ( array() === $roles ) {
		return true;
	}

	$user = wp_get_current_user();

	return $user instanceof WP_User && array() !== array_intersect( $roles, (array) $user->roles );
}

/**
 * Does the code draw this section, or is it only what was written in the admin?
 *
 * Whether it makes sense to ask where the site's own content goes depends on
 * that: if there is nothing from the code, there is no "before" or "after" of
 * anything.
 *
 * @param array<string, mixed> $section
 */
function diluxone_users_section_has_code( array $section ): bool {
	return is_callable( $section['render'] ?? '' );
}

/**
 * What gets drawn inside a section.
 *
 * Two things can live together: what the code knows how to draw — the
 * details, the security, another plugin's courses — and what was written from
 * the admin. The second used to overwrite the first, which is the one
 * combination nobody asks for: whoever writes a paragraph above their details
 * does not want to lose the details. Now it is a choice, and replacing is
 * still there for whoever wants it.
 *
 * @param array<string, mixed> $section
 */
function diluxone_users_account_section_html( array $section, WP_User $user ): string {
	$code = '';

	if ( diluxone_users_section_has_code( $section ) ) {
		ob_start();
		call_user_func( $section['render'], $user );
		$code = (string) ob_get_clean();
	}

	$own = '' === (string) $section['content']
		? ''
		: do_shortcode( wp_kses_post( (string) $section['content'] ) );

	if ( '' === $code || '' === $own ) {
		return $code . $own;
	}

	switch ( (string) $section['placement'] ) {
		case 'before':
			return $own . $code;

		case 'replace':
			return $own;

		default:
			return $code . $own;
	}
}

/** The first section shown when arriving without asking for one. */
function diluxone_users_default_section(): string {
	$sections = diluxone_users_sections();

	return (string) ( array_key_first( $sections ) ?? '' );
}

/** The section open right now. */
function diluxone_users_current_section(): string {
	$sections = diluxone_users_sections();

	$asked = (string) get_query_var( DILUXONE_USERS_ACCOUNT_VAR, '' );

	if ( '' === $asked ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- it only picks what to draw.
		$asked = isset( $_GET['section'] ) ? sanitize_title( wp_unslash( $_GET['section'] ) ) : '';
	}

	if ( '' === $asked ) {
		return diluxone_users_default_section();
	}

	// The lookup is by address, which is what the browser typed. The
	// identifier works just as well: it is the default address.
	foreach ( $sections as $id => $section ) {
		if ( $section['slug'] === $asked || $id === $asked ) {
			return (string) $id;
		}
	}

	return diluxone_users_default_section();
}

/** The account page, or 0 when none has been chosen yet. */
function diluxone_users_account_page_id(): int {
	return (int) diluxone_users_option( 'diluxone_users_account_page' );
}

/**
 * The URL of one section.
 *
 * With permalinks on it comes out as /account/security/; without them, the
 * usual parameter. There is nothing to configure: it looks at what is there.
 */
function diluxone_users_account_url( string $section = '' ): string {
	$page = diluxone_users_account_page_id();

	if ( $page <= 0 ) {
		return home_url( '/' );
	}

	$base = (string) get_permalink( $page );

	if ( '' === $section || $section === diluxone_users_default_section() ) {
		return $base;
	}

	$sections = diluxone_users_sections( true );
	$slug     = isset( $sections[ $section ] ) ? (string) $sections[ $section ]['slug'] : $section;

	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		return add_query_arg( 'section', $slug, $base );
	}

	return trailingslashit( $base ) . $slug . '/';
}

/**
 * The rule that makes /account/<section>/ possible.
 *
 * A rule of our own and not add_rewrite_endpoint(): an endpoint puts its name
 * in the URL — it would come out as /account/diluxone_users_section/security/
 * — and with one per section the number of them would have to be declared up
 * front, which is exactly what this registry avoids.
 */
function diluxone_users_account_rule(): void {
	$page = diluxone_users_account_page_id();

	if ( $page <= 0 ) {
		return;
	}

	$uri = get_page_uri( $page );

	if ( ! is_string( $uri ) || '' === $uri ) {
		return;
	}

	add_rewrite_rule(
		'^' . preg_quote( $uri, '/' ) . '/([^/]+)/?$',
		'index.php?page_id=' . $page . '&' . DILUXONE_USERS_ACCOUNT_VAR . '=$matches[1]',
		'top'
	);
}
add_action( 'init', 'diluxone_users_account_rule' );

/**
 * Without this WordPress throws away the value the rule captured.
 *
 * @param array<int, string> $vars
 * @return array<int, string>
 */
function diluxone_users_account_query_var( array $vars ): array {
	$vars[] = DILUXONE_USERS_ACCOUNT_VAR;

	return $vars;
}
add_filter( 'query_vars', 'diluxone_users_account_query_var' );

/**
 * The rewrite rules are saved once, not on every request.
 *
 * They are regenerated when the account page changes or when the plugin
 * changes version, the two moments at which the endpoint may have gone stale.
 */
function diluxone_users_account_flush_rules(): void {
	if ( get_option( 'diluxone_users_rewrite_version' ) === DILUXONE_USERS_VERSION ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'diluxone_users_rewrite_version', DILUXONE_USERS_VERSION );
}
add_action( 'wp_loaded', 'diluxone_users_account_flush_rules' );

/**
 * The heading of the section being viewed.
 *
 * The account area draws it and not each template, so that there are not two
 * ways of writing the same thing: a section bringing its own <h2> ends up in
 * a different typeface and size from the ones next to it, and that has
 * happened. The text is the section label — the same one read in the menu,
 * and the one changed from the admin — and whatever wants to say something
 * else says it through the filter.
 *
 * @param array<string, mixed> $section
 */
function diluxone_users_account_heading( array $section, string $id, WP_User $user ): string {
	/**
	 * Filters the heading of an account-area section.
	 *
	 * Returning '' removes it.
	 *
	 * @param string  $heading Heading text.
	 * @param string  $id      Section identifier.
	 * @param WP_User $user    Who is looking at it.
	 */
	return (string) apply_filters( 'diluxone_users_account_heading', (string) $section['label'], $id, $user );
}

/**
 * The heading, ready to print. Empty when the section carries none.
 *
 * @param array<string, mixed> $section
 */
function diluxone_users_account_heading_html( array $section, string $id, WP_User $user ): string {
	$heading = diluxone_users_account_heading( $section, $id, $user );

	return '' === $heading
		? ''
		: sprintf( '<h2 class="diluxone-users-account__title">%s</h2>', esc_html( $heading ) );
}

/** Are we in the account area? */
function diluxone_users_is_account(): bool {
	$page = diluxone_users_account_page_id();

	return $page > 0 && is_page( $page );
}

/* ── Pintado ───────────────────────────────────────────────────────── */

/** Somebody's visible name: the one they wrote, or whatever there is. */
function diluxone_users_display_name( WP_User $user ): string {
	$full = trim( $user->first_name . ' ' . $user->last_name );

	return '' !== $full ? $full : $user->display_name;
}

/** The first name, for greeting them. */
function diluxone_users_first_name( WP_User $user ): string {
	return '' !== $user->first_name ? $user->first_name : diluxone_users_display_name( $user );
}

/** The initials, for the letter avatar. */
function diluxone_users_initials( WP_User $user ): string {
	$parts = preg_split( '/\s+/', diluxone_users_display_name( $user ), -1, PREG_SPLIT_NO_EMPTY );
	$parts = is_array( $parts ) ? $parts : array();

	$first = isset( $parts[0] ) ? mb_substr( $parts[0], 0, 1 ) : '';
	$last  = count( $parts ) > 1 ? mb_substr( (string) end( $parts ), 0, 1 ) : '';

	return mb_strtoupper( $first . $last );
}

/**
 * The navigation, separate from the area: the site may want it elsewhere.
 *
 * @param array<string, array<string, mixed>>|null $sections
 */
function diluxone_users_account_nav( ?array $sections = null, string $current = '' ): string {
	$sections = null === $sections ? diluxone_users_sections() : $sections;
	$current  = '' === $current ? diluxone_users_current_section() : $current;

	if ( array() === $sections ) {
		return '';
	}

	return diluxone_users_render(
		'account-nav',
		array(
			'sections' => $sections,
			'current'  => $current,
		)
	);
}

/** The whole account area. Shortcode: [diluxone_users_account] */
function diluxone_users_shortcode_account(): string {
	if ( ! is_user_logged_in() ) {
		return diluxone_users_render( 'account-guest', array( 'url' => diluxone_users_login_url() ) );
	}

	$sections = diluxone_users_sections();

	if ( array() === $sections ) {
		return '';
	}

	diluxone_users_enqueue_styles();

	$current = diluxone_users_current_section();

	return diluxone_users_render(
		'account',
		array(
			'user'     => wp_get_current_user(),
			'sections' => $sections,
			'current'  => $current,
			'layout'   => (string) diluxone_users_option( 'diluxone_users_account_layout' ),
			'header'   => (bool) diluxone_users_option( 'diluxone_users_account_header' ),
		)
	);
}
add_shortcode( 'diluxone_users_account', 'diluxone_users_shortcode_account' );

/** The navigation alone. Shortcode: [diluxone_users_account_nav] */
function diluxone_users_shortcode_account_nav(): string {
	return is_user_logged_in() ? diluxone_users_account_nav() : '';
}
add_shortcode( 'diluxone_users_account_nav', 'diluxone_users_shortcode_account_nav' );
