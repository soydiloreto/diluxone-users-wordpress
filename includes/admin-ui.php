<?php
/**
 * The admin's design system: where its colours come from, and its pieces.
 *
 * Every screen in here used to be a `form-table` with a grey paragraph under
 * each control. That is not a style, it is the absence of one: the title of
 * an option, the sentence explaining it and the title of the next option all
 * weigh the same, so nothing leads the eye and two options read as one. The
 * complaint was that it is not nice to look at. The cause was that nothing
 * had a rank.
 *
 * So: tokens, and pieces built out of them. Two rules govern both.
 *
 * The first is where the colour comes from. WordPress lets a person pick an
 * admin colour scheme — nine of them — and a plugin that paints itself blue
 * on somebody's midnight dashboard looks like a plugin that was pasted in.
 * The scheme is published (`$_wp_admin_css_colors`), so it is read and the
 * plugin follows it. A site that would rather fix its own says so through the
 * filter below, and nothing else changes.
 *
 * The second is restraint. Nothing here invents a second interface: the
 * screens keep WordPress's structure, its tabs, its buttons and its tables.
 * What is added is rank — a title that reads as a title, an option that reads
 * as one thing, and a state you can see without reading.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The colours of the admin colour scheme in use.
 *
 * Every scheme publishes four: two darks for the menu and two for the accent.
 * The third and fourth are the ones that carry meaning — WordPress uses them
 * for the current menu item and for what it wants noticed — so those are the
 * ones borrowed. "fresh", the default, is the fallback for a scheme that
 * publishes nothing, which some do.
 *
 * @return array{accent: string, accent_soft: string, ink: string}
 */
function diluxone_users_admin_scheme(): array {
	global $_wp_admin_css_colors;

	$name   = (string) get_user_option( 'admin_color' );
	$scheme = $_wp_admin_css_colors[ $name ] ?? null;
	$colors = is_object( $scheme ) && ! empty( $scheme->colors ) ? (array) $scheme->colors : array();

	// Third and fourth: the accent and its light companion. A scheme with
	// fewer is read as far as it goes and the rest is WordPress's own blue.
	$accent = (string) ( $colors[2] ?? '#2271b1' );
	$light  = (string) ( $colors[3] ?? '#72aee6' );

	/**
	 * Filters the colours the plugin's admin takes from the colour scheme.
	 *
	 * @since 1.0.0
	 *
	 * @param array{accent: string, accent_soft: string, ink: string} $colours
	 * @param string                                                  $name   The scheme in use.
	 */
	return (array) apply_filters(
		'diluxone_users_admin_scheme',
		array(
			'accent'      => $accent,
			'accent_soft' => $light,
			'ink'         => (string) ( $colors[0] ?? '#1d2327' ),
		),
		$name
	);
}

/**
 * Those colours, as the properties the stylesheet reads.
 *
 * Printed with the stylesheet rather than written into it: the scheme belongs
 * to the person looking, not to the site, and two administrators of the same
 * site can have picked different ones.
 */
function diluxone_users_admin_tokens(): string {
	$scheme = diluxone_users_admin_scheme();

	return ':root{'
		. '--du-accent:' . $scheme['accent'] . ';'
		. '--du-accent-light:' . $scheme['accent_soft'] . ';'
		// Washed down for the ground of something chosen, and a touch more
		// for its edge. Derived, so a scheme change carries them along.
		. '--du-accent-wash:color-mix(in srgb, ' . $scheme['accent'] . ' 8%, #fff);'
		. '--du-accent-edge:color-mix(in srgb, ' . $scheme['accent'] . ' 45%, #fff);'
		. '--du-ink:' . $scheme['ink'] . ';'
		. '}';
}

/**
 * One option of a group, drawn as a card.
 *
 * The card is the whole point. A radio with its title beside it and its
 * explanation in a grey paragraph underneath belongs to nothing: the eye
 * cannot tell whether that paragraph explains the option above it or the one
 * below, and on the registration screen it genuinely could not — four tick
 * boxes and four sentences, alternating, none of them joined. Inside a card
 * with a border, an option is one thing, and what is said about it is said
 * inside it.
 *
 * @param array<string, mixed> $choice name, value, title and optionally type,
 *                                     help, checked, disabled, state, note,
 *                                     piece and id.
 */
function diluxone_users_ui_choice( array $choice ): void {
	$type     = ( $choice['type'] ?? 'radio' ) === 'checkbox' ? 'checkbox' : 'radio';
	$checked  = ! empty( $choice['checked'] );
	$disabled = ! empty( $choice['disabled'] );
	$classes  = array( 'du-choice' );

	if ( $checked ) {
		$classes[] = 'is-on';
	}

	if ( $disabled ) {
		$classes[] = 'is-locked';
	}
	?>
	<label class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
		<input
			type="<?php echo esc_attr( $type ); ?>"
			name="<?php echo esc_attr( (string) $choice['name'] ); ?>"
			value="<?php echo esc_attr( (string) $choice['value'] ); ?>"
			<?php echo isset( $choice['id'] ) ? 'id="' . esc_attr( (string) $choice['id'] ) . '"' : ''; ?>
			<?php echo isset( $choice['piece'] ) ? 'data-diluxone-users-piece="' . esc_attr( (string) $choice['piece'] ) . '"' : ''; ?>
			<?php checked( $checked ); ?>
			<?php disabled( $disabled ); ?>>

		<span class="du-choice__text">
			<span class="du-choice__title">
				<?php echo esc_html( (string) $choice['title'] ); ?>
				<?php if ( ! empty( $choice['state'] ) ) : ?>
					<?php echo diluxone_users_state_pill( (string) $choice['state'], (string) ( $choice['note'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
				<?php endif; ?>
			</span>

			<?php if ( ! empty( $choice['help'] ) ) : ?>
				<span class="du-choice__help"><?php echo wp_kses_post( (string) $choice['help'] ); ?></span>
			<?php endif; ?>
		</span>
	</label>
	<?php
}

/**
 * A group of them, and whatever hangs off the one that is chosen.
 *
 * A choice carries its own `children`: a callable printed inside that card
 * and shown only while it is on. It goes on the choice and not in a list
 * keyed by value because four tick boxes all worth "1" cannot be told apart
 * by value — and because a setting that exists only because of one option
 * belongs to it, not to the group.
 *
 * Asking for the registration page in a row of its own, three rows below the
 * box that turns the form on, is how a screen ends up explaining itself.
 *
 * @param array<int, array<string, mixed>> $choices
 */
function diluxone_users_ui_choices( array $choices ): void {
	echo '<div class="du-choices">';

	foreach ( $choices as $choice ) {
		$children = $choice['children'] ?? null;

		if ( ! is_callable( $children ) ) {
			diluxone_users_ui_choice( $choice );
			continue;
		}

		/*
		 * The card and its children are wrapped together so the indent reads
		 * as "this belongs to that", and the wrapper carries whether it is
		 * open: with the script off everything is visible and the server
		 * still reads whatever was submitted.
		 */
		printf(
			'<div class="du-choice-group %s" data-diluxone-users-group>',
			empty( $choice['checked'] ) ? '' : 'is-open'
		);

		diluxone_users_ui_choice( $choice );

		echo '<div class="du-choice__children">';
		call_user_func( $children );
		echo '</div>';

		echo '</div>';
	}

	echo '</div>';
}

/**
 * A heading inside a tab, for the group of settings under it.
 *
 * With a line under it and air above, because the thing it is separating is
 * exactly what nothing was separating before.
 */
function diluxone_users_ui_section( string $title, string $intro = '' ): void {
	printf( '<h3 class="du-section">%s</h3>', esc_html( $title ) );

	if ( '' !== $intro ) {
		printf( '<p class="du-section__intro">%s</p>', esc_html( $intro ) );
	}
}

/**
 * A line that belongs to a control and is not a choice: a number, a page, a
 * colour. Label above, control, help under it.
 *
 * It exists so a screen can stop using `form-table` where the two-column
 * shape does not help — a group of four numbers reads better in a row than
 * as four rows of a table.
 */
function diluxone_users_ui_field_open( string $label, string $target = '' ): void {
	echo '<div class="du-field">';

	if ( '' !== $label ) {
		printf(
			'<%1$s class="du-field__label"%2$s>%3$s</%1$s>',
			'' === $target ? 'span' : 'label',
			'' === $target ? '' : ' for="' . esc_attr( $target ) . '"',
			esc_html( $label )
		);
	}
}

/** Closes it, with the help under the control. */
function diluxone_users_ui_field_close( string $help = '' ): void {
	if ( '' !== $help ) {
		printf( '<p class="du-field__help">%s</p>', wp_kses_post( $help ) );
	}

	echo '</div>';
}
