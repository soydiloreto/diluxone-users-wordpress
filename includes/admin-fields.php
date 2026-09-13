<?php
/**
 * The fields screen: a listing and, separately, each field's detail.
 *
 * It is the heart of the plugin — what people are asked for — and that is why
 * it is not an accordion of stacked forms: the list is taken in at a glance
 * and you go in to edit one alone. It is how any other WordPress list works.
 *
 * The key (`diluxone_users_something`) proposes itself from the name and
 * afterwards cannot be changed: it is the name the value was stored under on
 * every person, and renaming it would mean losing what is already there.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns a name into a valid and unique key.
 *
 * @param array<string, mixed> $used
 */
function diluxone_users_key_from( string $label, array $used ): string {
	$base = sanitize_key( remove_accents( $label ) );
	$base = 'diluxone_users_' . ( '' === $base ? 'field' : $base );

	$key = $base;
	$n   = 2;

	while ( in_array( $key, $used, true ) ) {
		$key = $base . '_' . $n;
		++$n;
	}

	return $key;
}

/**
 * Where a field's "options" come from, which changes with the type.
 *
 * In a closed list they are the values; in a country, the codes pinned to the
 * top; in a phone, the default country. In the rest, nothing: a date field
 * has no options and there is no sense in showing it the box.
 *
 * @param array<string, mixed> $input
 * @return array<int, string>
 */
function diluxone_users_field_options_from( string $type, array $input ): array {
	switch ( $type ) {
		case 'select':
		case 'datalist':
			return array_map( 'trim', explode( "\n", (string) ( $input['options'] ?? '' ) ) );

		case 'country':
			return array_map( 'strval', (array) ( $input['preferred'] ?? array() ) );

		case 'phone':
			$default = (string) ( $input['default_country'] ?? '' );

			return '' === $default ? array() : array( $default );

		default:
			return array();
	}
}

/**
 * Saves a field (new or existing) and returns its key.
 *
 * @param array<string, mixed> $input
 */
function diluxone_users_field_save( array $input ): string {
	$fields = diluxone_users_fields( '', false );
	$key    = sanitize_key( (string) ( $input['key'] ?? '' ) );
	$label  = sanitize_text_field( (string) ( $input['label'] ?? '' ) );

	if ( '' === $label ) {
		return '';
	}

	if ( '' === $key ) {
		$key = diluxone_users_key_from( $label, wp_list_pluck( $fields, 'key' ) );
	}

	$field = diluxone_users_normalize_field(
		array(
			'key'         => $key,
			'label'       => $label,
			'type'        => (string) ( $input['type'] ?? 'text' ),
			'edit'        => sanitize_key( (string) ( $input['edit'] ?? 'always' ) ),
			'edit_max'    => (int) ( $input['edit_max'] ?? 1 ),
			'help'        => (string) ( $input['help'] ?? '' ),
			'placeholder' => (string) ( $input['placeholder'] ?? '' ),
			'options'     => diluxone_users_field_options_from( (string) ( $input['type'] ?? 'text' ), $input ),
			'required'    => ! empty( $input['required'] ),
			'group'       => (string) ( $input['group'] ?? 'optional' ),
			'active'      => ! empty( $input['active'] ),
		)
	);

	$replaced = false;

	foreach ( $fields as $i => $existing ) {
		if ( $existing['key'] === $key ) {
			$fields[ $i ] = $field;
			$replaced     = true;
			break;
		}
	}

	if ( ! $replaced ) {
		$fields[] = $field;
	}

	update_option( 'diluxone_users_fields', $fields );

	return $key;
}

/**
 * Deletes a field definition.
 *
 * The values people have already filled in are NOT touched: if the field
 * comes back tomorrow, they come back with it. Deleting 25,000 rows over one
 * click on a settings screen would be an expensive surprise.
 */
function diluxone_users_field_delete( string $key ): void {
	$fields = array_values(
		array_filter(
			diluxone_users_fields( '', false ),
			static fn( array $f ): bool => $f['key'] !== $key
		)
	);

	update_option( 'diluxone_users_fields', $fields );
}

/**
 * Saving, deleting or moving a field.
 *
 * On admin_init, not inside the screen: by the time WordPress calls the
 * callback of an admin page it has already printed the headers, and the
 * redirect ends in a "headers already sent" notice in the log.
 */
function diluxone_users_fields_actions(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'diluxone-users-fields' !== sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['diluxone_users_field_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_field_nonce'] ) ), 'diluxone_users_field' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; diluxone_users_field_save() sanitises it field by field.
		$key = diluxone_users_field_save( (array) wp_unslash( $_POST['diluxone_users_field'] ?? array() ) );

		wp_safe_redirect( diluxone_users_admin_url( 'diluxone-users-fields', array( 'diluxone_users_done' => '' === $key ? 'nolabel' : 'saved' ) ) );
		exit;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['diluxone_users_action'], $_GET['field'] ) ) {
		return;
	}

	check_admin_referer( 'diluxone_users_field_action' );

	$key    = sanitize_key( wp_unslash( $_GET['field'] ) );
	$action = sanitize_key( wp_unslash( $_GET['diluxone_users_action'] ) );

	if ( 'delete' === $action && ! diluxone_users_field_is_native( $key ) ) {
		diluxone_users_field_delete( $key );
	} elseif ( 'up' === $action ) {
		diluxone_users_field_move( $key, -1 );
	} elseif ( 'down' === $action ) {
		diluxone_users_field_move( $key, 1 );
	}

	wp_safe_redirect( diluxone_users_admin_url( 'diluxone-users-fields', array( 'diluxone_users_done' => $action ) ) );
	exit;
}
add_action( 'admin_init', 'diluxone_users_fields_actions' );

/** Moves a field up or down in the order. */
function diluxone_users_field_move( string $key, int $dir ): void {
	$fields = diluxone_users_fields( '', false );
	$keys   = wp_list_pluck( $fields, 'key' );
	$i      = array_search( $key, $keys, true );

	if ( false === $i ) {
		return;
	}

	$j = (int) $i + $dir;

	if ( $j < 0 || $j >= count( $fields ) ) {
		return;
	}

	[ $fields[ $i ], $fields[ $j ] ] = array( $fields[ $j ], $fields[ $i ] );

	update_option( 'diluxone_users_fields', $fields );
}

/* ── The screen ────────────────────────────────────────────────────── */

/** The fields screen: the listing, or one field's detail. */
function diluxone_users_screen_fields(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$editing = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : '';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$done = isset( $_GET['diluxone_users_done'] ) ? sanitize_key( wp_unslash( $_GET['diluxone_users_done'] ) ) : '';

	if ( '' !== $editing || isset( $_GET['diluxone_users_new'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		diluxone_users_screen_field_edit( $editing );
		return;
	}

	$tabs    = array(
		'list'  => __( 'Fields', 'diluxone-users' ),
		'usage' => __( 'How to use them', 'diluxone-users' ),
	);
	$current = diluxone_users_tab( $tabs );

	diluxone_users_screen_open( __( 'User fields', 'diluxone-users' ), 'diluxone-users-fields', $tabs, $current );

	$notices = array(
		'saved'   => __( 'Field saved.', 'diluxone-users' ),
		'delete'  => __( 'Field deleted. The data already stored was left alone.', 'diluxone-users' ),
		'nolabel' => __( 'A field needs a name.', 'diluxone-users' ),
	);

	if ( isset( $notices[ $done ] ) ) {
		diluxone_users_notice( $notices[ $done ], 'nolabel' === $done ? 'error' : 'success' );
	}

	if ( 'usage' === $current ) {
		diluxone_users_screen_fields_usage();
	} else {
		diluxone_users_screen_fields_list();
	}

	diluxone_users_screen_close();
}

/** Screen fields list. */
function diluxone_users_screen_fields_list(): void {
	$fields = diluxone_users_fields( '', false );
	$types  = diluxone_users_field_types();
	$groups = diluxone_users_groups();
	?>
	<div class="diluxone-users-where">
		<p><strong><?php esc_html_e( 'Where all this lives', 'diluxone-users' ); ?></strong></p>
		<p>
			<?php esc_html_e( 'What each field IS —its name, type and behaviour— is one WordPress option. What each PERSON answered is user meta: one row per person and per field, with the field key as the name. No extra tables.', 'diluxone-users' ); ?>
		</p>
		<?php
		// The two names go on lines of their own and not inside the sentence
		// above. A <code> chip is an atom the browser cannot break, so in the
		// middle of a paragraph it jumps to the next line whole and leaves the
		// previous one short — which reads as a line break that nobody typed.
		?>
		<ul class="diluxone-users-where__names">
			<li>
				<?php esc_html_e( 'The definition:', 'diluxone-users' ); ?>
				<code>diluxone_users_fields</code>
			</li>
			<li>
				<?php esc_html_e( 'The answers:', 'diluxone-users' ); ?>
				<code><?php echo esc_html( $GLOBALS['wpdb']->usermeta ); ?></code>
			</li>
		</ul>
		<p>
			<?php esc_html_e( 'That is why the key cannot change once the field exists, and why deleting a field leaves the answers alone: they are two different things.', 'diluxone-users' ); ?>
		</p>
	</div>

	<p class="diluxone-users-admin__actions">
		<a class="button button-primary" href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-fields', array( 'diluxone_users_new' => 1 ) ) ); ?>">
			<?php esc_html_e( 'Add field', 'diluxone-users' ); ?>
		</a>
	</p>

	<table class="wp-list-table widefat fixed striped diluxone-users-list">
		<thead>
			<tr>
				<th class="diluxone-users-list__name"><?php esc_html_e( 'Name', 'diluxone-users' ); ?></th>
				<th><?php esc_html_e( 'Type', 'diluxone-users' ); ?></th>
				<th><?php esc_html_e( 'Where', 'diluxone-users' ); ?></th>
				<th><?php esc_html_e( 'Required', 'diluxone-users' ); ?></th>
				<th><?php esc_html_e( 'Status', 'diluxone-users' ); ?></th>
				<th class="diluxone-users-list__order"><?php esc_html_e( 'Order', 'diluxone-users' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( array() === $fields ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No fields yet.', 'diluxone-users' ); ?></td></tr>
			<?php endif; ?>

			<?php
			foreach ( $fields as $field ) :
				$edit = diluxone_users_admin_url( 'diluxone-users-fields', array( 'field' => $field['key'] ) );
				?>
				<tr>
					<td class="diluxone-users-list__name">
						<strong><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $field['label'] ); ?></a></strong>
						<code><?php echo esc_html( $field['key'] ); ?></code>
						<div class="row-actions">
							<span class="edit"><a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'diluxone-users' ); ?></a></span>

							<?php
							/*
							 * WordPress's own are not deleted: the value exists all
							 * the same and the dashboard and half the site's plugins
							 * use it. They are hidden.
							 */
							?>
							<?php if ( ! diluxone_users_field_is_native( $field['key'] ) ) : ?>
								<span class="trash"> |
									<a class="diluxone-users-danger"
										href="
										<?php
										echo esc_url(
											wp_nonce_url(
												diluxone_users_admin_url(
													'diluxone-users-fields',
													array(
														'field' => $field['key'],
														'diluxone_users_action' => 'delete',
													)
												),
												'diluxone_users_field_action'
											)
										);
										?>
												"
										onclick="return confirm(<?php echo esc_attr( (string) wp_json_encode( __( 'Delete this field? The data already stored is kept.', 'diluxone-users' ) ) ); ?>);">
										<?php esc_html_e( 'Delete', 'diluxone-users' ); ?>
									</a>
								</span>
							<?php endif; ?>
						</div>
					</td>
					<td><?php echo esc_html( $types[ $field['type'] ] ?? $field['type'] ); ?></td>
					<td><?php echo esc_html( $groups[ $field['group'] ] ?? $field['group'] ); ?></td>
					<td><?php echo $field['required'] ? esc_html__( 'Yes', 'diluxone-users' ) : '—'; ?></td>
					<td>
						<?php
						if ( 'never' === $field['edit'] ) {
							esc_html_e( 'Read only', 'diluxone-users' );
						} elseif ( 'limited' === $field['edit'] ) {
							printf(
									/* translators: %d: how many times it can be changed */
								esc_html( _n( '%d time', '%d times', (int) $field['edit_max'], 'diluxone-users' ) ),
								(int) $field['edit_max']
							);
						} else {
							echo '&mdash;';
						}
						?>
					</td>
					<td>
						<span class="diluxone-users-pill diluxone-users-pill--<?php echo $field['active'] ? 'on' : 'off'; ?>">
							<?php echo $field['active'] ? esc_html__( 'Active', 'diluxone-users' ) : esc_html__( 'Hidden', 'diluxone-users' ); ?>
						</span>
					</td>
					<td class="diluxone-users-list__order">
						<a class="button button-small" href="
						<?php
						echo esc_url(
							wp_nonce_url(
								diluxone_users_admin_url(
									'diluxone-users-fields',
									array(
										'field' => $field['key'],
										'diluxone_users_action' => 'up',
									)
								),
								'diluxone_users_field_action'
							)
						);
						?>
																" aria-label="<?php esc_attr_e( 'Move up', 'diluxone-users' ); ?>">&uarr;</a>
						<a class="button button-small" href="
						<?php
						echo esc_url(
							wp_nonce_url(
								diluxone_users_admin_url(
									'diluxone-users-fields',
									array(
										'field' => $field['key'],
										'diluxone_users_action' => 'down',
									)
								),
								'diluxone_users_field_action'
							)
						);
						?>
																" aria-label="<?php esc_attr_e( 'Move down', 'diluxone-users' ); ?>">&darr;</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/** A field's detail. With an empty key, it is a new one. */
function diluxone_users_screen_field_edit( string $key ): void {
	$field = '' === $key ? diluxone_users_normalize_field(
		array(
			'group'  => 'optional',
			'active' => 1,
		)
	) : diluxone_users_field( $key );

	if ( null === $field ) {
		diluxone_users_screen_open( __( 'User fields', 'diluxone-users' ) );
		diluxone_users_notice( __( 'That field does not exist.', 'diluxone-users' ), 'error' );
		diluxone_users_screen_close();
		return;
	}

	$fresh = '' === $key;

	diluxone_users_screen_open(
		$fresh
		? __( 'New field', 'diluxone-users' )
		: sprintf( /* translators: %s: field name */ __( 'Field: %s', 'diluxone-users' ), $field['label'] )
	);
	?>
	<p><a href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-fields' ) ); ?>">&larr; <?php esc_html_e( 'Back to the list', 'diluxone-users' ); ?></a></p>

	<form method="post" class="diluxone-users-form-admin">
		<?php wp_nonce_field( 'diluxone_users_field', 'diluxone_users_field_nonce' ); ?>
		<input type="hidden" name="diluxone_users_field[key]" value="<?php echo esc_attr( $field['key'] ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="diluxone-users-label"><?php esc_html_e( 'Name', 'diluxone-users' ); ?></label></th>
				<td>
					<input type="text" id="diluxone-users-label" name="diluxone_users_field[label]" class="regular-text" value="<?php echo esc_attr( $field['label'] ); ?>" required>
					<?php if ( ! $fresh ) : ?>
						<p class="description"><?php esc_html_e( 'Key:', 'diluxone-users' ); ?> <code><?php echo esc_html( $field['key'] ); ?></code> — <?php esc_html_e( 'it cannot change: it is the name the data is stored under.', 'diluxone-users' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'The key is generated from the name.', 'diluxone-users' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="diluxone-users-type"><?php esc_html_e( 'Type', 'diluxone-users' ); ?></label></th>
				<td>
					<?php
					/*
					 * WordPress's own come with their type set: changing the type
					 * of the first name does not improve it, and can break it.
					 */
					?>
					<select id="diluxone-users-type" name="diluxone_users_field[type]" <?php disabled( diluxone_users_field_is_native( $field['key'] ) ); ?>>
						<?php foreach ( diluxone_users_field_types() as $value => $name ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $field['type'], $value ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>

					<?php if ( diluxone_users_field_is_native( $field['key'] ) ) : ?>
						<input type="hidden" name="diluxone_users_field[type]" value="<?php echo esc_attr( $field['type'] ); ?>">
						<p class="description"><?php esc_html_e( 'This one is WordPress’s own: it can be renamed, reordered, made required or hidden, but it keeps its type and cannot be deleted.', 'diluxone-users' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( '“Country” shows the full list with its dial codes; “Phone with country code” splits the number in two and stores it in international format.', 'diluxone-users' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Who can change it', 'diluxone-users' ); ?></th>
				<td>
					<?php
					$modes = array(
						'always'  => __( 'Whenever they want', 'diluxone-users' ),
						'limited' => __( 'Only a few times, and then no more', 'diluxone-users' ),
						'never'   => __( 'Never — they can see it, only an administrator changes it', 'diluxone-users' ),
					);

					foreach ( $modes as $mode_key => $label ) :
						?>
						<label class="diluxone-users-roles__item">
							<input type="radio" name="diluxone_users_field[edit]" value="<?php echo esc_attr( $mode_key ); ?>" <?php checked( $field['edit'], $mode_key ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>

					<p class="diluxone-users-if-type-edit">
						<label for="diluxone-users-edit-max"><?php esc_html_e( 'How many times', 'diluxone-users' ); ?></label>
						<input type="number" id="diluxone-users-edit-max" name="diluxone_users_field[edit_max]" min="1" max="99" class="small-text" value="<?php echo esc_attr( (string) $field['edit_max'] ); ?>">
					</p>

					<p class="description"><?php esc_html_e( 'This is about the person who owns the data. An administrator can always change it, from the user’s profile: otherwise a one-change field turns into a typo nobody can fix.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="diluxone-users-group"><?php esc_html_e( 'Where it goes', 'diluxone-users' ); ?></label></th>
				<td>
					<select id="diluxone-users-group" name="diluxone_users_field[group]">
						<?php foreach ( diluxone_users_groups() as $g => $g_label ) : ?>
							<option value="<?php echo esc_attr( $g ); ?>" <?php selected( $field['group'], $g ); ?>><?php echo esc_html( $g_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The profile form shows two blocks: the essentials first, and underneath the optional ones with their own explanation. This decides which one the field lands in.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="diluxone-users-help"><?php esc_html_e( 'Help text', 'diluxone-users' ); ?></label></th>
				<td>
					<input type="text" id="diluxone-users-help" name="diluxone_users_field[help]" class="large-text" value="<?php echo esc_attr( $field['help'] ); ?>">
					<p class="description"><?php esc_html_e( 'Why we are asking. Shown under the field.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
			<tr class="diluxone-users-if-type" data-type="text textarea email url number datalist phone">
				<th scope="row"><label for="diluxone-users-placeholder"><?php esc_html_e( 'Placeholder text', 'diluxone-users' ); ?></label></th>
				<td>
					<input type="text" id="diluxone-users-placeholder" name="diluxone_users_field[placeholder]" class="regular-text" value="<?php echo esc_attr( $field['placeholder'] ); ?>">
					<p class="description"><?php esc_html_e( 'Shown in grey inside the empty field.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
			<tr class="diluxone-users-if-type" data-type="select datalist">
				<th scope="row"><label for="diluxone-users-options"><?php esc_html_e( 'Values', 'diluxone-users' ); ?></label></th>
				<td>
					<textarea id="diluxone-users-options" name="diluxone_users_field[options]" class="large-text code" rows="5"><?php echo esc_textarea( implode( "\n", 'country' === $field['type'] || 'phone' === $field['type'] ? array() : $field['options'] ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One per line. In a fixed list they are the only accepted values; in text with suggestions they are just hints and the person can write something else.', 'diluxone-users' ); ?></p>
				</td>
			</tr>

			<tr class="diluxone-users-if-type" data-type="country">
				<th scope="row"><label for="diluxone-users-preferred"><?php esc_html_e( 'Countries shown first', 'diluxone-users' ); ?></label></th>
				<td>
					<select id="diluxone-users-preferred" name="diluxone_users_field[preferred][]" multiple size="8" class="diluxone-users-multi">
						<?php foreach ( diluxone_users_countries_sorted() as $iso => $country_name ) : ?>
							<option value="<?php echo esc_attr( $iso ); ?>" <?php selected( in_array( $iso, $field['options'], true ) ); ?>>
								<?php echo esc_html( $country_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php
						printf(
								/* translators: %d: number of countries */
							esc_html__( 'Optional. The list of %d countries comes with the plugin — there is nothing to load. These ones go on top, separated from the rest, so nobody has to scroll to find the one next door.', 'diluxone-users' ),
							count( diluxone_users_countries() )
						);
						?>
					</p>
				</td>
			</tr>

			<tr class="diluxone-users-if-type" data-type="phone">
				<th scope="row"><label for="diluxone-users-default-country"><?php esc_html_e( 'Country selected by default', 'diluxone-users' ); ?></label></th>
				<td>
					<select id="diluxone-users-default-country" name="diluxone_users_field[default_country]">
						<option value=""><?php esc_html_e( '— None —', 'diluxone-users' ); ?></option>
						<?php foreach ( diluxone_users_countries_sorted() as $iso => $country_name ) : ?>
							<option value="<?php echo esc_attr( $iso ); ?>" <?php selected( ( $field['options'][0] ?? '' ), $iso ); ?>>
								<?php echo esc_html( $country_name . ' +' . diluxone_users_country_dial( $iso ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The dial code the field comes with. The person can change it: the full list is always there.', 'diluxone-users' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Behaviour', 'diluxone-users' ); ?></th>
				<td>
					<label><input type="checkbox" name="diluxone_users_field[required]" value="1" <?php checked( $field['required'], 1 ); ?>> <?php esc_html_e( 'Required', 'diluxone-users' ); ?></label><br>
					<label><input type="checkbox" name="diluxone_users_field[active]" value="1" <?php checked( $field['active'], 1 ); ?>> <?php esc_html_e( 'Active: show it in the forms', 'diluxone-users' ); ?></label>
				</td>
			</tr>
		</table>

		<?php submit_button( $fresh ? __( 'Add field', 'diluxone-users' ) : __( 'Save field', 'diluxone-users' ) ); ?>
	</form>
	<?php
	diluxone_users_screen_close();
}

/** Screen fields usage. */
function diluxone_users_screen_fields_usage(): void {
	diluxone_users_intro( __( 'The fields show up on their own in the dashboard profile, when adding a user and in the WordPress registration form. On the front end you place them with a shortcode.', 'diluxone-users' ) );
	?>
	<table class="widefat striped diluxone-users-shortcodes">
		<tbody>
			<tr><td><code>[diluxone_users_fields]</code></td><td><?php esc_html_e( 'Every field, for the person to edit.', 'diluxone-users' ); ?></td></tr>
			<tr><td><code>[diluxone_users_fields group="basic"]</code></td><td><?php esc_html_e( 'Only the basic ones. With group="optional", only the others.', 'diluxone-users' ); ?></td></tr>
			<tr><td><code>[diluxone_users_login]</code></td><td><?php esc_html_e( 'The email sign-in form and the social buttons.', 'diluxone-users' ); ?></td></tr>
			<tr><td><code>[diluxone_users_accounts]</code></td><td><?php esc_html_e( 'Linked providers, to link or unlink.', 'diluxone-users' ); ?></td></tr>
			<tr><td><code>[diluxone_users_sessions]</code></td><td><?php esc_html_e( 'Open sessions, with the button to close them.', 'diluxone-users' ); ?></td></tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Fitting them into your design', 'diluxone-users' ); ?></h2>
	<p class="diluxone-users-admin__intro">
		<?php esc_html_e( 'Copy any file from the plugin’s templates/ folder into your theme, inside a diluxone-users/ folder, and edit it there. The plugin will use yours. You can also turn off its stylesheet in Sign in → Presentation.', 'diluxone-users' ); ?>
	</p>
	<?php
}
