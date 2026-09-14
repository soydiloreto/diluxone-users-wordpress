<?php
/**
 * What the plugin knows about one person, where WordPress already keeps people.
 *
 * Everything here could have been a screen of its own, and that would have been
 * the wrong place: whoever needs to look at somebody's passkeys is already in
 * Users looking at that person. So it goes in the two places WordPress gives
 * for exactly this — a column in the list, and a block on the profile — and
 * nothing has to be searched for twice.
 *
 * The list column reads user meta that WordPress has already loaded in one go
 * for the whole page, so a list of twenty costs no query per row.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * What this person has, in short.
 *
 * @return array{passkeys: int, totp: bool, social: array<int, string>, handle: string, second_step: bool}
 */
function diluxone_users_person( int $user_id ): array {
	return array(
		'passkeys'    => count( diluxone_users_passkeys( $user_id ) ),
		'totp'        => diluxone_users_totp_ready( $user_id ),
		'social'      => diluxone_users_sso_linked( $user_id ),
		'handle'      => diluxone_users_handle( $user_id ),
		'second_step' => diluxone_users_2fa_on( $user_id ),
	);
}

/**
 * The column header in Users.
 *
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function diluxone_users_users_column( array $columns ): array {
	$columns['diluxone_users'] = __( 'Access', 'diluxone-users' );

	return $columns;
}
add_filter( 'manage_users_columns', 'diluxone_users_users_column' );

/**
 * One row of that column.
 *
 * Chips and not sentences: this is a column read at a glance down a list, and
 * what it has to answer is "is there anything here", not the detail. The
 * detail is one click away on the profile.
 */
function diluxone_users_users_column_row( string $out, string $column, int $user_id ): string {
	if ( 'diluxone_users' !== $column ) {
		return $out;
	}

	$person = diluxone_users_person( $user_id );
	$chips  = array();

	if ( $person['second_step'] ) {
		$chips[] = array( 'on', __( '2FA', 'diluxone-users' ) );
	}

	if ( $person['totp'] ) {
		$chips[] = array( 'on', __( 'App', 'diluxone-users' ) );
	}

	if ( $person['passkeys'] > 0 ) {
		$chips[] = array(
			'on',
			sprintf(
				/* translators: %d: how many passkeys */
				_n( '%d passkey', '%d passkeys', $person['passkeys'], 'diluxone-users' ),
				$person['passkeys']
			),
		);
	}

	foreach ( $person['social'] as $id ) {
		$provider = diluxone_users_sso_providers()[ $id ] ?? null;
		$chips[]  = array( 'blank', (string) ( $provider['name'] ?? $id ) );
	}

	if ( array() === $chips ) {
		return '<span class="diluxone-users-muted">' . esc_html__( 'Only the e-mail link', 'diluxone-users' ) . '</span>';
	}

	$html = '';

	foreach ( $chips as $chip ) {
		$html .= sprintf(
			'<span class="diluxone-users-pill diluxone-users-pill--%1$s">%2$s</span> ',
			esc_attr( $chip[0] ),
			esc_html( $chip[1] )
		);
	}

	return $html;
}
add_filter( 'manage_users_custom_column', 'diluxone_users_users_column_row', 10, 3 );

/**
 * The block on somebody's profile.
 *
 * Read-only except for the three things an administrator is ever asked to do
 * for somebody else: take a lost passkey off, unlink a social account, and
 * turn off an authenticator app that person no longer has. Nothing here can
 * add anything: only the owner of an account can add a way into it.
 */
function diluxone_users_profile_block( WP_User $user ): void {
	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}

	$person   = diluxone_users_person( (int) $user->ID );
	$sessions = diluxone_users_sessions( (int) $user->ID );
	$last     = $sessions[0] ?? null;
	?>
	<h2><?php echo esc_html( diluxone_users_plugin_name() ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Public name', 'diluxone-users' ); ?></th>
			<td>
				<?php
				echo '' !== $person['handle']
					? '<code>' . esc_html( $person['handle'] ) . '</code>'
					: '<span class="diluxone-users-muted">' . esc_html__( 'None chosen', 'diluxone-users' ) . '</span>';
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Last seen', 'diluxone-users' ); ?></th>
			<td>
				<?php if ( null === $last ) : ?>
					<span class="diluxone-users-muted"><?php esc_html_e( 'No open session.', 'diluxone-users' ); ?></span>
				<?php else : ?>
					<?php
					printf(
						/* translators: 1: how long ago, 2: device and browser, 3: IP address */
						esc_html__( '%1$s ago, from %2$s (%3$s)', 'diluxone-users' ),
						esc_html( human_time_diff( (int) $last['started'] ) ),
						esc_html( trim( $last['device'] . ' · ' . $last['browser'] ) ),
						esc_html( $last['ip'] )
					);
					?>
					<br>
					<a href="
					<?php
					echo esc_url(
						diluxone_users_admin_url(
							DILUXONE_USERS_SECURITY,
							array(
								'tab' => 'sessions',
								's'   => $user->user_email,
							)
						)
					);
					?>
					">
						<?php esc_html_e( 'See every session', 'diluxone-users' ); ?>
					</a>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Two-step verification', 'diluxone-users' ); ?></th>
			<td>
				<?php if ( $person['totp'] ) : ?>
					<p><?php esc_html_e( 'An authenticator app is set up.', 'diluxone-users' ); ?></p>
					<label>
						<input type="checkbox" name="diluxone_users_forget_totp" value="1">
						<?php esc_html_e( 'Remove the authenticator app — for somebody who lost the phone it lived on', 'diluxone-users' ); ?>
					</label>
				<?php else : ?>
					<span class="diluxone-users-muted"><?php esc_html_e( 'No authenticator app.', 'diluxone-users' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Passkeys', 'diluxone-users' ); ?></th>
			<td>
				<?php if ( 0 === $person['passkeys'] ) : ?>
					<span class="diluxone-users-muted"><?php esc_html_e( 'None.', 'diluxone-users' ); ?></span>
				<?php else : ?>
					<?php foreach ( diluxone_users_passkeys( (int) $user->ID ) as $key ) : ?>
						<label class="diluxone-users-roles__item">
							<input type="checkbox" name="diluxone_users_forget_passkey[]" value="<?php echo esc_attr( (string) $key['id'] ); ?>">
							<?php
							printf(
								/* translators: 1: the name given to the passkey, 2: date it was added */
								esc_html__( 'Remove “%1$s”, added on %2$s', 'diluxone-users' ),
								esc_html( (string) ( $key['label'] ?? __( 'Passkey', 'diluxone-users' ) ) ),
								esc_html( date_i18n( (string) get_option( 'date_format' ), (int) ( $key['created'] ?? 0 ) ) )
							);
							?>
						</label>
					<?php endforeach; ?>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Linked accounts', 'diluxone-users' ); ?></th>
			<td>
				<?php if ( array() === $person['social'] ) : ?>
					<span class="diluxone-users-muted"><?php esc_html_e( 'None.', 'diluxone-users' ); ?></span>
				<?php else : ?>
					<?php foreach ( $person['social'] as $id ) : ?>
						<label class="diluxone-users-roles__item">
							<input type="checkbox" name="diluxone_users_unlink[]" value="<?php echo esc_attr( $id ); ?>">
							<?php
							printf(
								/* translators: %s: name of the social network */
								esc_html__( 'Unlink %s', 'diluxone-users' ),
								esc_html( (string) ( diluxone_users_sso_providers()[ $id ]['name'] ?? $id ) )
							);
							?>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Unlinking does not delete anything: that network simply stops opening this account.', 'diluxone-users' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'edit_user_profile', 'diluxone_users_profile_block' );

/**
 * Applies what was ticked on that block.
 *
 * Nothing here is destructive beyond the way in it takes away, and every one
 * of them is something the person can add again from their own account.
 */
function diluxone_users_profile_block_save( int $user_id ): void {
	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WordPress verifies the profile nonce before this hook.
	if ( isset( $_POST['diluxone_users_forget_totp'] ) ) {
		diluxone_users_totp_forget( $user_id );
	}

	$forget = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['diluxone_users_forget_passkey'] ?? array() ) );
	$unlink = array_map( 'sanitize_key', (array) wp_unslash( $_POST['diluxone_users_unlink'] ?? array() ) );
	// phpcs:enable

	foreach ( $forget as $id ) {
		diluxone_users_passkey_forget( $user_id, (string) $id );
	}

	foreach ( $unlink as $id ) {
		delete_user_meta( $user_id, 'diluxone_users_sso_' . (string) $id );
	}
}
add_action( 'edit_user_profile_update', 'diluxone_users_profile_block_save' );
