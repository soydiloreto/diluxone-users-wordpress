<?php
/**
 * The fields, dropped into the forms that already exist.
 *
 * The plugin does not know how people get into the site, and it does not have
 * to: the same fields appear in WordPress's own registration, in the sign-up
 * an administrator performs, in the dashboard profile and — through a
 * shortcode — on whatever screen the site has on the front end. A site with
 * open registration and one with SSO or an e-mail link end up with the same
 * data stored.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * A single field, in the table markup the dashboard uses.
 *
 * @param array<string, mixed> $field
 */
function diluxone_users_field_row( array $field, int $user_id ): void {
	$value = diluxone_users_value( $user_id, $field['key'] );
	$id    = 'diluxone-users-' . $field['key'];
	?>
	<tr>
		<th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
		<td>
			<?php diluxone_users_field_input( $field, $value, $id ); ?>
			<?php if ( '' !== $field['help'] ) : ?>
				<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/**
 * A field's control: the matching <input>, <select> or <textarea>.
 *
 * It is separated from the surrounding markup on purpose: it is the only part
 * that cannot change between the dashboard, the registration and the
 * front-end template, so it is written once and all three use it.
 *
 * @param array<string, mixed> $field
 */
function diluxone_users_field_input( array $field, string $value, string $id = '' ): void {
	$key              = $field['key'];
	$id               = '' === $id ? $key : $id;
	$required_attr    = $field['required'] ? ' required' : '';
	$placeholder_attr = '' === $field['placeholder'] ? '' : ' placeholder="' . esc_attr( $field['placeholder'] ) . '"';

	// A field that cannot be changed is shown all the same: the data belongs to
	// the person and they have a right to see it. On the ones you type into it
	// is `readonly`, which allows copying and still submits; on the ones you
	// pick from there is no `readonly` and `disabled` has to be used. What
	// rules either way is the server: this is so it is understood, not to
	// prevent anything.
	$editable = diluxone_users_field_editable( $field, get_current_user_id() );
	$lock     = $editable ? '' : ' readonly';
	$lock_sel = $editable ? '' : ' disabled';

	switch ( $field['type'] ) {
		case 'textarea':
			printf(
				'<textarea id="%1$s" name="%2$s" rows="4"%3$s%4$s>%5$s</textarea>',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $required_attr . $lock ),
				wp_kses_post( $placeholder_attr ),
				esc_textarea( $value )
			);
			return;

		case 'select':
			printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $key ), esc_attr( $required_attr . $lock_sel ) );
			printf( '<option value="">%s</option>', esc_html__( '— Choose —', 'diluxone-users' ) );

			foreach ( $field['options'] as $option ) {
				printf(
					'<option value="%1$s"%2$s>%1$s</option>',
					esc_attr( $option ),
					selected( $value, $option, false )
				);
			}

			echo '</select>';
			return;

		case 'checkbox':
			printf(
				'<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s> %5$s</label>',
				esc_attr( $id ),
				esc_attr( $key ),
				checked( $value, '1', false ),
				esc_attr( $lock_sel ),
				esc_html( $field['label'] )
			);
			return;

		case 'country':
			printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $key ), esc_attr( $required_attr . $lock_sel ) );
			printf( '<option value="">%s</option>', esc_html__( '— Choose —', 'diluxone-users' ) );

			$preferred = diluxone_users_countries_sorted( $field['options'] );
			$cut       = count( $field['options'] );
			$n         = 0;

			foreach ( $preferred as $iso => $country_name ) {
				// The preferred ones go on top and apart from the rest: otherwise
				// the site's own country is lost halfway down a list of 189.
				if ( $cut > 0 && $n === $cut ) {
					echo '<option value="" disabled>──────────</option>';
				}

				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $iso ),
					selected( $value, $iso, false ),
					esc_html( $country_name )
				);

				++$n;
			}

			echo '</select>';
			return;

		case 'phone':
			// Two controls and a single value: the dialling code is picked from a
			// list and the number is typed without it. What is stored is the sum.
			$dial_default = strtoupper( (string) ( $field['options'][0] ?? '' ) );
			$dial         = $dial_default;
			$national     = $value;

			// On reading a stored value back it has to be split again. It is tried
			// from the longest dialling code to the shortest because +1 and +1242
			// coexist.
			if ( '' !== $value ) {
				$digits = ltrim( $value, '+' );
				$best   = 0;

				foreach ( diluxone_users_countries() as $iso => $data ) {
					$len = strlen( $data[1] );

					if ( $len > $best && 0 === strpos( $digits, $data[1] ) ) {
						$best     = $len;
						$dial     = $iso;
						$national = substr( $digits, $len );
					}
				}
			}

			echo '<span class="diluxone-users-phone">';
			printf( '<select id="%1$s-dial" name="%2$s_dial" class="diluxone-users-phone__dial"%3$s>', esc_attr( $id ), esc_attr( $key ), esc_attr( $lock_sel ) );

			foreach ( diluxone_users_countries_sorted( $field['options'] ) as $iso => $country_name ) {
				printf(
					'<option value="%1$s"%2$s>%3$s +%4$s</option>',
					esc_attr( $iso ),
					selected( $dial, $iso, false ),
					esc_html( $country_name ),
					esc_html( diluxone_users_country_dial( $iso ) )
				);
			}

			echo '</select>';

			printf(
				'<input type="tel" id="%1$s" name="%2$s" value="%3$s" inputmode="tel" class="diluxone-users-phone__number" autocomplete="tel-national"%4$s%5$s>',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $national ),
				esc_attr( $required_attr . $lock ),
				wp_kses_post( $placeholder_attr )
			);

			echo '</span>';
			return;

		case 'datalist':
			$list = 'diluxone-users-list-' . $key;

			printf(
				'<input type="text" id="%1$s" name="%2$s" value="%3$s" list="%4$s" autocomplete="off"%5$s%6$s>',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $value ),
				esc_attr( $list ),
				esc_attr( $required_attr . $lock ),
				wp_kses_post( $placeholder_attr )
			);

			printf( '<datalist id="%s">', esc_attr( $list ) );

			foreach ( $field['options'] as $option ) {
				printf( '<option value="%s"></option>', esc_attr( $option ) );
			}

			echo '</datalist>';
			return;
	}

	$types = array(
		'email'  => 'email',
		'url'    => 'url',
		'number' => 'number',
		'date'   => 'date',
	);

	printf(
		'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s>',
		esc_attr( $types[ $field['type'] ] ?? 'text' ),
		esc_attr( $id ),
		esc_attr( $key ),
		esc_attr( $value ),
		esc_attr( $required_attr . $lock ),
		wp_kses_post( $placeholder_attr )
	);
}

/* ── Perfil del escritorio ─────────────────────────────────────────── */

/**
 * The plugin fields, in the dashboard profile.
 *
 * @param WP_User|string $user The person, or the string the sign-up hook passes.
 */
function diluxone_users_profile_fields( $user ): void {
	if ( ! $user instanceof WP_User ) {
		return;
	}

	$fields = diluxone_users_fields();

	if ( array() === $fields ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Additional details', 'diluxone-users' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php foreach ( $fields as $field ) : ?>
			<?php diluxone_users_field_row( $field, (int) $user->ID ); ?>
		<?php endforeach; ?>
	</table>
	<?php
}
add_action( 'show_user_profile', 'diluxone_users_profile_fields' );
add_action( 'edit_user_profile', 'diluxone_users_profile_fields' );

/** Profile save. */
function diluxone_users_profile_save( int $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress already verified the profile nonce before this hook.
	diluxone_users_save( $user_id, $_POST );
}
add_action( 'personal_options_update', 'diluxone_users_profile_save' );
add_action( 'edit_user_profile_update', 'diluxone_users_profile_save' );

/* ── Sign-up from the dashboard (Users → Add New) ──────────────────── */

/** The plugin fields, in the dashboard sign-up of a person. */
function diluxone_users_new_user_fields( string $type ): void {
	$fields = diluxone_users_fields();

	if ( 'add-new-user' !== $type || array() === $fields ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Additional details', 'diluxone-users' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php foreach ( $fields as $field ) : ?>
			<?php diluxone_users_field_row( $field, 0 ); ?>
		<?php endforeach; ?>
	</table>
	<?php
}
add_action( 'user_new_form', 'diluxone_users_new_user_fields' );

/* ── Registro nativo de WordPress ──────────────────────────────────── */

/**
 * The fields on wp-login.php?action=register.
 *
 * It is there for the site that does have open registration. On a site
 * without passwords this form is never used and this gets in nobody's way.
 */
function diluxone_users_register_form_fields(): void {
	foreach ( diluxone_users_fields() as $field ) {
		$id = 'diluxone-users-' . $field['key'];
		?>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
			<?php diluxone_users_field_input( $field, '', $id ); ?>
			<?php if ( '' !== $field['help'] ) : ?>
				<em class="description"><?php echo esc_html( $field['help'] ); ?></em>
			<?php endif; ?>
		</p>
		<?php
	}
}
add_action( 'register_form', 'diluxone_users_register_form_fields' );

/**
 * An empty required field does not let the registration go through.
 *
 * @param WP_Error $errors The errors WordPress has already gathered.
 * @param string   $login  The username being registered.
 * @param string   $email  Their e-mail.
 * @return WP_Error
 */
function diluxone_users_register_validate( $errors, $login, $email ) {
	foreach ( diluxone_users_fields() as $field ) {
		if ( ! $field['required'] ) {
			continue;
		}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WordPress registration verifies it; diluxone_users_sanitize() cleans the value according to the field type.
		$value = diluxone_users_sanitize( $field, (string) wp_unslash( $_POST[ $field['key'] ] ?? '' ) );

		if ( '' === $value ) {
			$errors->add(
				'diluxone_users_' . $field['key'],
				sprintf(
						/* translators: %s: field name */
					esc_html__( 'Error: “%s” is required.', 'diluxone-users' ),
					esc_html( $field['label'] )
				)
			);
		}
	}

	return $errors;
}
add_filter( 'registration_errors', 'diluxone_users_register_validate', 10, 3 );

/** Register save. */
function diluxone_users_register_save( int $user_id ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress registration verifies it itself.
	diluxone_users_save( $user_id, $_POST );
}
add_action( 'user_register', 'diluxone_users_register_save' );
