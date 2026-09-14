<?php
/**
 * Everything the plugin puts in somebody's inbox, in one place.
 *
 * It was spread out the way the look used to be: the sign-in e-mail lived on
 * the sign-in screen, the second-step code had no screen at all, and what a
 * person can ask not to be told was only visible from their own account area.
 * Three different places for one question — what does this site send, and who
 * decides.
 *
 * Its tabs are registered like every other screen's, so a feature that sends
 * its own mail brings its own tab with it.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

const DILUXONE_USERS_NOTICES = 'diluxone-users-notices';

/** The notifications screen. */
function diluxone_users_screen_notices(): void {
	diluxone_users_screen_panels( DILUXONE_USERS_NOTICES, diluxone_users_screens()[ DILUXONE_USERS_NOTICES ] );
}

/** Its tabs. */
function diluxone_users_notices_panels(): void {
	diluxone_users_register_panel(
		DILUXONE_USERS_NOTICES,
		'link',
		array(
			'label'    => __( 'The sign-in email', 'diluxone-users' ),
			'position' => 10,
			'render'   => 'diluxone_users_screen_login_email',
			'save'     => 'diluxone_users_login_email_save',
		)
	);

	diluxone_users_register_panel(
		DILUXONE_USERS_NOTICES,
		'what',
		array(
			'label'    => __( 'What gets told', 'diluxone-users' ),
			'position' => 20,
			'render'   => 'diluxone_users_screen_notices_what',
			// Nothing to save: it is the list of what exists and who decides.
			'form'     => false,
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_notices_panels' );

/**
 * What this site tells people about, and who decides.
 *
 * Read-only on purpose. Each of these is a person's own decision, made from
 * their account area, and an administrator turning one off for everybody is
 * deciding on their behalf that they do not want to know their account was
 * used from a new device. The screen shows what exists, what it defaults to,
 * and where it comes from — which is the useful thing when a site has three
 * plugins adding notices and somebody asks where an e-mail came from.
 */
function diluxone_users_screen_notices_what(): void {
	$defaults = diluxone_users_default_notifications();
	$all      = diluxone_users_notification_prefs();

	diluxone_users_intro( __( 'What this site can tell somebody about, by e-mail. Each person chooses from their own account area — an administrator turning one off for everybody would be deciding, on their behalf, that they do not want to know their account was used from a device they have never used.', 'diluxone-users' ) );
	?>
	<table class="widefat striped diluxone-users-state">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'What it tells them', 'diluxone-users' ); ?></th>
				<th scope="col"><?php esc_html_e( 'On by default', 'diluxone-users' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Comes from', 'diluxone-users' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( array() === $all ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'Nothing: this site tells nobody anything.', 'diluxone-users' ); ?></td></tr>
			<?php endif; ?>

			<?php foreach ( $all as $diluxone_users_key => $diluxone_users_notice ) : ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( (string) $diluxone_users_notice['label'] ); ?>
						<?php if ( '' !== (string) ( $diluxone_users_notice['help'] ?? '' ) ) : ?>
							<p class="description"><?php echo esc_html( (string) $diluxone_users_notice['help'] ); ?></p>
						<?php endif; ?>
						<code><?php echo esc_html( (string) $diluxone_users_key ); ?></code>
					</th>
					<td>
						<?php echo '' === (string) ( $diluxone_users_notice['default'] ?? '' ) ? esc_html__( 'No', 'diluxone-users' ) : esc_html__( 'Yes', 'diluxone-users' ); ?>
					</td>
					<td>
						<?php
						echo isset( $defaults[ $diluxone_users_key ] )
							? esc_html( diluxone_users_plugin_name() )
							: esc_html__( 'Something else on this site', 'diluxone-users' );
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'The ones with no switch', 'diluxone-users' ); ?></h2>
	<?php
	diluxone_users_intro( __( 'These go out whatever anybody chose, because they were asked for: somebody who presses “send me the link” and gets nothing has no way in, and somebody halfway through a second step is waiting for the code. A preference that can stop them is a preference that locks people out.', 'diluxone-users' ) );
	?>
	<table class="widefat striped diluxone-users-state">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'The sign-in link', 'diluxone-users' ); ?></th>
				<td>
					<?php esc_html_e( 'Sent when somebody asks to get in. Its wording is on the previous tab.', 'diluxone-users' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'The second-step code', 'diluxone-users' ); ?></th>
				<td>
					<?php
					echo diluxone_users_option( 'diluxone_users_2fa_methods' ) && in_array( 'email', (array) diluxone_users_option( 'diluxone_users_2fa_methods' ), true )
						? esc_html__( 'Sent when a code by email is one of the second steps this site offers.', 'diluxone-users' )
						: esc_html__( 'Not in use: a code by email is not one of the second steps this site offers.', 'diluxone-users' );
					?>
					<a href="<?php echo esc_url( diluxone_users_admin_url( DILUXONE_USERS_SECURITY, array( 'tab' => '2fa' ) ) ); ?>"><?php esc_html_e( 'Two-step verification', 'diluxone-users' ); ?></a>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Their own data', 'diluxone-users' ); ?></th>
				<td><?php esc_html_e( 'The export and the deletion WordPress runs when somebody asks for their data. They confirm by email, and that confirmation is the request.', 'diluxone-users' ); ?></td>
			</tr>
		</tbody>
	</table>

	<?php
	diluxone_users_panel_actions(
		array(
			__( 'Send myself a test', 'diluxone-users' ) => diluxone_users_admin_url( 'diluxone-users-tools' ),
		)
	);
}
