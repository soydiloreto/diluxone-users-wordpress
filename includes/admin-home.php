<?php
/**
 * The plugin front page: what state all of this is in right now.
 *
 * It is not a welcome screen with a fixed text. It is the real numbers and
 * the real state: how many people there are, how many are in, what they are
 * asked for, how they get in and what is left to configure. What you would
 * want to know before touching any other screen.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The front-page numbers.
 *
 * How many accounts there are is counted with a COUNT(*) over the users table
 * and not with count_users(), which also groups by role by walking the
 * metadata table: with twenty-five thousand accounts that took five seconds
 * and the per-role number is not used here.
 *
 * It is cached all the same: they are two queries that do not change from one
 * minute to the next.
 *
 * @return array<string, int>
 */
function diluxone_users_home_numbers(): array {
	$cached = get_transient( 'diluxone_users_home_numbers' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;

	// Two counts for the summary screen. `count_users()` walks every role to
	// return one of these numbers, and on a site of 25,000 people that takes a
	// while; the other has no API at all. They are not cached because the
	// screen exists to show how the site is right now.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$numbers = array(
		'users'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
		'sessions' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'session_tokens'" ),
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	set_transient( 'diluxone_users_home_numbers', $numbers, 15 * MINUTE_IN_SECONDS );

	return $numbers;
}

/** A tile with a number and its label. */
function diluxone_users_tile( string $value, string $label, string $link = '', string $link_label = '' ): void {
	?>
	<div class="diluxone-users-tile">
		<b><?php echo esc_html( $value ); ?></b>
		<span><?php echo esc_html( $label ); ?></span>
		<?php if ( '' !== $link ) : ?>
			<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $link_label ); ?> &rarr;</a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * A status row: what it is, how it is doing and what to do.
 *
 * The state has three values and not two. "To be confirmed" exists because
 * there are things that cannot be known from here — whether the mail really
 * goes out, for instance — and saying "ready" without being sure is worse
 * than saying nothing.
 *
 * @param string $state ok | pending | unknown
 */
function diluxone_users_status_row( string $what, string $state, string $detail, string $link = '', string $link_label = '' ): void {
	$pills = array(
		'ok'      => array( 'on', __( 'Ready', 'diluxone-users' ) ),
		'pending' => array( 'blank', __( 'Pending', 'diluxone-users' ) ),
		'unknown' => array( 'off', __( 'Cannot tell', 'diluxone-users' ) ),
	);

	[ $tone, $label ] = $pills[ $state ] ?? $pills['unknown'];
	?>
	<tr>
		<th scope="row"><?php echo esc_html( $what ); ?></th>
		<td>
			<span class="diluxone-users-pill diluxone-users-pill--<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $label ); ?></span>
			<?php echo esc_html( $detail ); ?>
			<?php if ( '' !== $link ) : ?>
				<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $link_label ); ?></a>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/** Screen home. */
function diluxone_users_screen_home(): void {
	$numbers   = diluxone_users_home_numbers();
	$fields    = diluxone_users_fields( '', false );
	$active    = array_filter( $fields, static fn( array $f ): bool => (bool) $f['active'] );
	$providers = diluxone_users_sso_providers();
	$ready     = diluxone_users_sso_available();
	$setup     = array_filter( array_keys( $providers ), 'diluxone_users_sso_configured' );
	$page      = (int) diluxone_users_option( 'diluxone_users_login_page' );

	diluxone_users_screen_open( diluxone_users_screens()[ DILUXONE_USERS_MENU ] );

	diluxone_users_intro( __( 'Who is in this site, what is asked of them and how they get in.', 'diluxone-users' ) );
	?>

	<div class="diluxone-users-tiles">
		<?php
		diluxone_users_tile(
			number_format_i18n( $numbers['users'] ),
			__( 'accounts', 'diluxone-users' ),
			admin_url( 'users.php' ),
			__( 'All users', 'diluxone-users' )
		);

		diluxone_users_tile(
			number_format_i18n( $numbers['sessions'] ),
			__( 'with an open session', 'diluxone-users' ),
			diluxone_users_admin_url( 'diluxone-users-sessions' ),
			__( 'See who', 'diluxone-users' )
		);

		diluxone_users_tile(
			number_format_i18n( count( $active ) ) . ' / ' . number_format_i18n( count( $fields ) ),
			__( 'fields in use', 'diluxone-users' ),
			diluxone_users_admin_url( 'diluxone-users-fields' ),
			__( 'Manage them', 'diluxone-users' )
		);

		diluxone_users_tile(
			number_format_i18n( count( $ready ) ) . ' / ' . number_format_i18n( count( $providers ) ),
			__( 'social providers on', 'diluxone-users' ),
			diluxone_users_admin_url( 'diluxone-users-social' ),
			__( 'Set them up', 'diluxone-users' )
		);
		?>
	</div>

	<h2><?php esc_html_e( 'How people get in', 'diluxone-users' ); ?></h2>
	<table class="widefat striped diluxone-users-state">
		<tbody>
			<?php
			diluxone_users_status_row(
				__( 'Sign-in page', 'diluxone-users' ),
				$page > 0 ? 'ok' : 'pending',
				$page > 0
					? (string) get_the_title( $page )
					: __( 'Not chosen yet: wp-login.php is doing the job.', 'diluxone-users' ),
				diluxone_users_admin_url( 'diluxone-users-login' ),
				__( 'Choose it', 'diluxone-users' )
			);

			diluxone_users_status_row(
				__( 'Link by email', 'diluxone-users' ),
				'ok',
				diluxone_users_option( 'diluxone_users_login_register' )
					? __( 'On. If the email does not exist, the account is created in the same step.', 'diluxone-users' )
					: __( 'On, for accounts that already exist. New ones are not created from here.', 'diluxone-users' )
			);

			$methods = array(
				'link'     => __( 'Only the email link: wp-login.php sends people to the sign-in page.', 'diluxone-users' ),
				'password' => __( 'Only username and password, the WordPress one.', 'diluxone-users' ),
				'both'     => __( 'The email link and the password, both.', 'diluxone-users' ),
			);

			diluxone_users_status_row(
				__( 'How people get in', 'diluxone-users' ),
				'ok',
				$methods[ diluxone_users_login_method() ]
			);

			$two_step = array(
				'off'      => __( 'Off: nobody is asked for a second step.', 'diluxone-users' ),
				'optional' => __( 'Optional: whoever wants it turns it on from their profile.', 'diluxone-users' ),
				'required' => __( 'Required for everybody who can use it.', 'diluxone-users' ),
			);

			diluxone_users_status_row(
				__( 'Two-step verification', 'diluxone-users' ),
				'off' === (string) diluxone_users_option( 'diluxone_users_2fa_mode' ) ? 'pending' : 'ok',
				$two_step[ (string) diluxone_users_option( 'diluxone_users_2fa_mode' ) ] ?? ''
			);

			diluxone_users_status_row(
				__( 'Passkeys', 'diluxone-users' ),
				diluxone_users_option( 'diluxone_users_passkey_enabled' ) ? 'ok' : 'pending',
				diluxone_users_option( 'diluxone_users_passkey_enabled' )
					? sprintf(
							/* translators: %s: the domain they end up tied to */
						__( 'On, tied to %s.', 'diluxone-users' ),
						diluxone_users_passkey_rp_id()
					)
					: __( 'Off. It is the only way in that cannot be phished.', 'diluxone-users' )
			);

			diluxone_users_status_row(
				__( 'Social login', 'diluxone-users' ),
				array() !== $ready ? 'ok' : 'pending',
				array() !== $ready
					/* translators: %s: list of providers */
					? sprintf( __( 'Working: %s', 'diluxone-users' ), implode( ', ', wp_list_pluck( $ready, 'name' ) ) )
					: (
						array() !== $setup
							? __( 'There are providers with credentials, but none is verified and enabled yet.', 'diluxone-users' )
							: __( 'No provider set up yet: only the email link works.', 'diluxone-users' )
					),
				diluxone_users_admin_url( 'diluxone-users-social' ),
				__( 'Providers', 'diluxone-users' )
			);

			// Whether an e-mail goes out cannot be known without sending one. The
			// only checkable thing is whether anything is hooked to the sending,
			// and that is not enough to say it works.
			$has_mailer = (bool) has_filter( 'phpmailer_init' );

			diluxone_users_status_row(
				__( 'Outgoing email', 'diluxone-users' ),
				$has_mailer ? 'unknown' : 'pending',
				$has_mailer
					? __( 'Something is hooked into delivery, but whether mail actually leaves cannot be known from here. Send yourself a link to find out.', 'diluxone-users' )
					: __( 'Nothing is hooked into delivery: WordPress will try the server’s mail() and that usually fails. Without email there is no sign-in link.', 'diluxone-users' )
			);

			diluxone_users_status_row(
				__( 'Session length', 'diluxone-users' ),
				'ok',
				sprintf(
						/* translators: 1: days with remember-me, 2: days without remember-me */
					__( '%1$d days with “remember me”, %2$d without.', 'diluxone-users' ),
					(int) diluxone_users_option( 'diluxone_users_session_long_days' ),
					(int) diluxone_users_option( 'diluxone_users_session_short_days' )
				),
				diluxone_users_admin_url( 'diluxone-users-sessions', array( 'tab' => 'duration' ) ),
				__( 'Change it', 'diluxone-users' )
			);
			?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'What is asked of people', 'diluxone-users' ); ?></h2>
	<?php if ( array() === $active ) : ?>
		<p class="diluxone-users-admin__intro"><?php esc_html_e( 'Nothing beyond the email address.', 'diluxone-users' ); ?></p>
	<?php else : ?>
		<table class="widefat striped diluxone-users-state">
			<tbody>
				<?php
				foreach ( diluxone_users_groups() as $group => $group_label ) :
					$in_group = array_filter( $active, static fn( array $f ): bool => $f['group'] === $group );

					if ( array() === $in_group ) {
						continue;
					}
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $group_label ); ?></th>
						<td><?php echo esc_html( implode( ' · ', wp_list_pluck( $in_group, 'label' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'If you get locked out', 'diluxone-users' ); ?></h2>
	<p class="diluxone-users-admin__intro">
		<?php esc_html_e( 'On a site without passwords and without outgoing email, an expired session leaves you outside. With access to the server:', 'diluxone-users' ); ?>
	</p>
	<p><code>wp diluxone-users login <?php echo esc_html( wp_get_current_user()->user_email ); ?></code></p>
	<?php

	diluxone_users_screen_close();
}
