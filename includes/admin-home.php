<?php
/**
 * The plugin front page: what state all of this is in right now.
 *
 * It is not a welcome screen with a fixed text. It is the real numbers and
 * the real state: how many people there are, how many are in, what they are
 * asked for and how they get in. What you would want to know before touching
 * any other screen.
 *
 * The screen has two halves and they answer different questions. The cards at
 * the top are the headline numbers — four of them, glanced at, not read. What
 * is under them is the detail, and it is split into three panels behind tabs
 * because reading all three at once is reading none: nobody arrives here
 * wanting to know at the same time what their people use, how they get in and
 * what is asked of them.
 *
 * None of the panels edits anything. They tell, and they carry the button to
 * the screen where the thing is actually changed. The panels are registered
 * the way every other screen's are, so an add-on can put one here too; the
 * screen draws them itself only because the cards and the first steps sit
 * above the tabs, and the shared drawing has no room above its tabs.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** The three panels, in the order they are asked about. */
function diluxone_users_home_panels(): void {
	diluxone_users_register_panel(
		DILUXONE_USERS_MENU,
		'usage',
		array(
			'label'    => __( 'What your people use', 'diluxone-users' ),
			'position' => 10,
			'render'   => 'diluxone_users_panel_usage',
			'form'     => false,
		)
	);

	diluxone_users_register_panel(
		DILUXONE_USERS_MENU,
		'doors',
		array(
			'label'    => __( 'How your people get in', 'diluxone-users' ),
			'position' => 20,
			'render'   => 'diluxone_users_panel_doors',
			'form'     => false,
		)
	);

	diluxone_users_register_panel(
		DILUXONE_USERS_MENU,
		'asked',
		array(
			'label'    => __( 'What is asked of them', 'diluxone-users' ),
			'position' => 30,
			'render'   => 'diluxone_users_panel_asked',
			'form'     => false,
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_home_panels' );

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

/**
 * A headline card: an icon, a number, what the number is, and where to go.
 *
 * The icon is a dashicon and it is decorative — it repeats what the label
 * already says, so it is hidden from screen readers rather than read out
 * twice.
 */
function diluxone_users_card( string $icon, string $value, string $label, string $detail = '', string $link = '', string $link_label = '' ): void {
	?>
	<div class="diluxone-users-card">
		<span class="diluxone-users-card__icon dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
		<div class="diluxone-users-card__body">
			<h3><?php echo esc_html( $label ); ?></h3>
			<b class="diluxone-users-card__value"><?php echo esc_html( $value ); ?></b>
			<?php if ( '' !== $detail ) : ?>
				<p class="diluxone-users-card__detail"><?php echo esc_html( $detail ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $link ) : ?>
				<a class="diluxone-users-card__link" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $link_label ); ?> &rarr;</a>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * The steps a fresh install still has to go through.
 *
 * Three things, and no more: the plugin works out of the box, but until the
 * site says which page holds the sign-in form and which one holds the account
 * area, those two live nowhere — and if the mail does not go out, nobody gets
 * in at all. Everything else has a sane default.
 *
 * Each step says whether it is done, so the screen can show the list while
 * something is missing and get out of the way once it is not.
 *
 * @return array<int, array{label: string, detail: string, done: bool, url: string, cta: string}>
 */
function diluxone_users_setup_steps(): array {
	$login   = (int) diluxone_users_option( 'diluxone_users_login_page' );
	$account = (int) diluxone_users_option( 'diluxone_users_account_page' );
	$mail    = diluxone_users_mail_status();

	return array(
		array(
			'label'  => __( 'Choose the sign-in page', 'diluxone-users' ),
			'detail' => __( 'The page holding the [diluxone_users_login] shortcode. Until there is one, people land on wp-login.php.', 'diluxone-users' ),
			'done'   => $login > 0,
			'url'    => diluxone_users_admin_url( 'diluxone-users-login' ),
			'cta'    => __( 'Go to Sign in', 'diluxone-users' ),
		),
		array(
			'label'  => __( 'Choose the account page', 'diluxone-users' ),
			'detail' => __( 'The page holding the [diluxone_users_account] shortcode. It is what every link to "my account" points at.', 'diluxone-users' ),
			'done'   => $account > 0,
			'url'    => diluxone_users_admin_url( 'diluxone-users-account' ),
			'cta'    => __( 'Go to Account area', 'diluxone-users' ),
		),
		array(
			'label'  => __( 'Check that e-mail goes out', 'diluxone-users' ),
			'detail' => __( 'The sign-in link, the second-step code and the data requests are all e-mail. Send yourself a test: it is the only way to know.', 'diluxone-users' ),
			'done'   => 'ok' === $mail['state'],
			'url'    => diluxone_users_admin_url( DILUXONE_USERS_STATUS, array( 'tab' => 'tools' ) ),
			'cta'    => __( 'Go to Tools', 'diluxone-users' ),
		),
	);
}

/**
 * The first steps, while there are any.
 *
 * The one still to do carries the button; the ones already done are ticked and
 * stay visible, because crossing something off is the point of a list like
 * this. Once the three are done the whole block goes and the panels below
 * take its place: a checklist of things already done is furniture.
 *
 * @param array<int, array{label: string, detail: string, done: bool, url: string, cta: string}> $steps
 */
function diluxone_users_steps( array $steps ): void {
	$next = true;

	echo '<ol class="diluxone-users-steps">';

	foreach ( $steps as $step ) {
		$show_cta = ! $step['done'] && $next;
		$next     = $next && $step['done'];

		printf(
			'<li class="diluxone-users-step%1$s"><div class="diluxone-users-step__body"><strong>%2$s</strong><p>%3$s</p>%4$s</div></li>',
			$step['done'] ? ' is-done' : '',
			esc_html( $step['label'] ),
			esc_html( $step['detail'] ),
			$show_cta
				? sprintf(
					'<a class="button button-primary" href="%1$s">%2$s</a>',
					esc_url( $step['url'] ),
					esc_html( $step['cta'] )
				)
				: ''
		);
	}

	echo '</ol>';
}

/**
 * The buttons at the foot of a panel: where what it just showed gets changed.
 *
 * @param array<string, string> $actions Label => URL.
 */
function diluxone_users_panel_actions( array $actions ): void {
	echo '<p class="diluxone-users-panel__actions">';

	foreach ( $actions as $label => $url ) {
		printf(
			'<a class="button" href="%1$s">%2$s</a> ',
			esc_url( (string) $url ),
			esc_html( (string) $label )
		);
	}

	echo '</p>';
}

/**
 * What people actually chose, out of the people there are.
 *
 * This panel is the one that is not configuration: every row is a count of
 * real accounts, and it is written as such — "40 of 120 accounts", not a bare
 * number that could be read as a setting. The bar is the same number drawn,
 * so the four rows can be compared with the eye; no chart library for four
 * numbers, and on paper it prints as the table it is.
 */
function diluxone_users_panel_usage(): void {
	$stats = diluxone_users_stats();
	$total = (int) $stats['users'];

	$rows = array(
		array(
			'label'  => __( 'Have a public name', 'diluxone-users' ),
			'detail' => __( 'The short name that goes in the address of their profile. Without one, the site falls back to their first and last name.', 'diluxone-users' ),
			'value'  => (int) $stats['handles'],
		),
		array(
			'label'  => __( 'Linked a social account', 'diluxone-users' ),
			'detail' => __( 'They can get in with Google, Facebook or whichever provider is enabled, instead of waiting for the e-mail.', 'diluxone-users' ),
			'value'  => (int) $stats['social'],
		),
		array(
			'label'  => __( 'Set up an authenticator app', 'diluxone-users' ),
			'detail' => __( 'The six-digit code as a second step, from an app on their phone.', 'diluxone-users' ),
			'value'  => (int) $stats['totp'],
		),
		array(
			'label'  => __( 'Registered a passkey', 'diluxone-users' ),
			'detail' => __( 'Their fingerprint or their face instead of a password. It is the only way in that cannot be phished.', 'diluxone-users' ),
			'value'  => (int) $stats['passkeys'],
		),
	);

	diluxone_users_intro(
		sprintf(
			/* translators: %s: number of accounts on the site */
			__( 'Out of the %s accounts on this site, how many chose each thing. These are people, not settings: nobody is counted here until they turn it on themselves.', 'diluxone-users' ),
			number_format_i18n( $total )
		)
	);

	if ( 0 === $total ) {
		diluxone_users_intro( __( 'There are no accounts yet, so there is nothing to count.', 'diluxone-users' ) );

		return;
	}

	echo '<ul class="diluxone-users-usage">';

	foreach ( $rows as $row ) {
		$share = round( $row['value'] / $total * 100 );

		printf(
			'<li class="diluxone-users-usage__row">
				<div class="diluxone-users-usage__head">
					<strong>%1$s</strong>
					<span class="diluxone-users-usage__count">%2$s <span class="diluxone-users-usage__share">(%3$s)</span></span>
				</div>
				<span class="diluxone-users-usage__track"><span class="diluxone-users-usage__fill" style="width:%4$s%%"></span></span>
				<p class="diluxone-users-usage__detail">%5$s</p>
			</li>',
			esc_html( (string) $row['label'] ),
			esc_html(
				sprintf(
					/* translators: 1: how many people, 2: how many accounts in total */
					__( '%1$s of %2$s', 'diluxone-users' ),
					number_format_i18n( (int) $row['value'] ),
					number_format_i18n( $total )
				)
			),
			esc_html(
				sprintf(
					/* translators: %s: a percentage, already rounded */
					__( '%s%%', 'diluxone-users' ),
					number_format_i18n( $share )
				)
			),
			esc_attr( (string) $share ),
			esc_html( (string) $row['detail'] )
		);
	}

	echo '</ul>';

	diluxone_users_panel_actions(
		array(
			__( 'Social login', 'diluxone-users' )     => diluxone_users_admin_url( 'diluxone-users-social' ),
			__( 'Sign in', 'diluxone-users' )          => diluxone_users_admin_url( 'diluxone-users-login' ),
			__( 'See every person', 'diluxone-users' ) => admin_url( 'users.php' ),
		)
	);
}

/**
 * How people get in, in four lines.
 *
 * The full table of doors — every method, every provider, the second step,
 * the sessions — lives on the Access screen, and it used to be repeated here
 * with a different rule for what counts as ready, which is how the two came
 * to disagree. This is the short version: the page, the method, the mail that
 * the method depends on, and one line that says whether any door is open at
 * all. The mail row is the very same check the maintenance screen runs, so
 * it cannot say something different here.
 */
function diluxone_users_panel_doors(): void {
	$page   = (int) diluxone_users_option( 'diluxone_users_login_page' );
	$post   = $page > 0 ? get_post( $page ) : null;
	$access = diluxone_users_admin_url( 'diluxone-users-login' );

	$methods = array(
		'link'     => __( 'The e-mail link only: no passwords on this site.', 'diluxone-users' ),
		'password' => __( 'Username and password only, the WordPress one.', 'diluxone-users' ),
		'both'     => __( 'The e-mail link, and username and password underneath.', 'diluxone-users' ),
	);

	$doors = diluxone_users_check_ways_in();

	diluxone_users_intro( __( 'The short version. Every door, with its own state, is on the Access screen.', 'diluxone-users' ) );

	diluxone_users_summary_table(
		array(
			array(
				'label'  => __( 'Sign-in page', 'diluxone-users' ),
				'state'  => $post instanceof WP_Post && 'publish' === $post->post_status ? 'active' : 'off',
				'detail' => $post instanceof WP_Post
					? $post->post_title
					: __( 'Not chosen yet: wp-login.php is doing the job.', 'diluxone-users' ),
				'url'    => $access,
				'change' => __( 'Choose it →', 'diluxone-users' ),
			),
			array(
				'label'  => __( 'Sign-in method', 'diluxone-users' ),
				'state'  => 'active',
				'detail' => $methods[ diluxone_users_login_method() ],
				'url'    => diluxone_users_admin_url( 'diluxone-users-login', array( 'tab' => 'link' ) ),
			),
			diluxone_users_check_mail(),
			array(
				'label'  => __( 'All the doors', 'diluxone-users' ),
				'state'  => $doors['state'],
				'detail' => $doors['detail'],
				'url'    => $access,
				'change' => __( 'Access › Summary →', 'diluxone-users' ),
			),
		)
	);
}

/** The fields in use, grouped the way the account area groups them. */
function diluxone_users_panel_asked(): void {
	$screens = diluxone_users_screens();
	$fields  = diluxone_users_fields( '', false );
	$active  = array_filter( $fields, static fn( array $f ): bool => (bool) $f['active'] );

	diluxone_users_intro( __( 'Besides the e-mail address, which is the identity and is never optional, this is what the account area asks people for.', 'diluxone-users' ) );

	if ( array() === $active ) {
		diluxone_users_intro( __( 'Nothing beyond the email address.', 'diluxone-users' ) );
	} else {
		?>
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
		<?php
	}

	diluxone_users_panel_actions(
		array(
			$screens['diluxone-users-fields']  => diluxone_users_admin_url( 'diluxone-users-fields' ),
			$screens['diluxone-users-account'] => diluxone_users_admin_url( 'diluxone-users-account' ),
		)
	);
}

/** Screen home. */
function diluxone_users_screen_home(): void {
	$numbers   = diluxone_users_home_numbers();
	$fields    = diluxone_users_fields( '', false );
	$active    = array_filter( $fields, static fn( array $f ): bool => (bool) $f['active'] );
	$providers = diluxone_users_sso_providers();
	$ready     = diluxone_users_sso_available();

	$panels  = diluxone_users_panels( DILUXONE_USERS_MENU );
	$labels  = wp_list_pluck( $panels, 'label' );
	$current = diluxone_users_tab( $labels );

	diluxone_users_screen_open( diluxone_users_screens()[ DILUXONE_USERS_MENU ] );

	$steps   = diluxone_users_setup_steps();
	$pending = array_filter( $steps, static fn( array $step ): bool => ! $step['done'] );
	?>

	<div class="diluxone-users-welcome">
		<h2>
			<?php
			/* translators: %s: plugin name */
			printf( esc_html__( 'Welcome to %s', 'diluxone-users' ), esc_html( diluxone_users_plugin_name() ) );
			?>
		</h2>
		<p><?php esc_html_e( 'Who is in this site, what is asked of them and how they get in.', 'diluxone-users' ); ?></p>
	</div>

	<div class="diluxone-users-cards">
		<?php
		diluxone_users_card(
			'dashicons-groups',
			number_format_i18n( $numbers['users'] ),
			__( 'Accounts', 'diluxone-users' ),
			__( 'People with an account on this site.', 'diluxone-users' ),
			admin_url( 'users.php' ),
			__( 'See them', 'diluxone-users' )
		);

		diluxone_users_card(
			'dashicons-clock',
			number_format_i18n( $numbers['sessions'] ),
			__( 'Open sessions', 'diluxone-users' ),
			__( 'Signed in right now, on at least one device.', 'diluxone-users' ),
			diluxone_users_admin_url( DILUXONE_USERS_SECURITY ),
			__( 'See who', 'diluxone-users' )
		);

		diluxone_users_card(
			'dashicons-forms',
			number_format_i18n( count( $active ) ) . ' / ' . number_format_i18n( count( $fields ) ),
			__( 'Fields in use', 'diluxone-users' ),
			__( 'Of the ones defined, how many people are actually asked for.', 'diluxone-users' ),
			diluxone_users_admin_url( 'diluxone-users-fields' ),
			__( 'Manage them', 'diluxone-users' )
		);

		diluxone_users_card(
			'dashicons-share',
			number_format_i18n( count( $ready ) ) . ' / ' . number_format_i18n( count( $providers ) ),
			__( 'Social providers', 'diluxone-users' ),
			__( 'Verified and working, of the ones the plugin brings.', 'diluxone-users' ),
			diluxone_users_admin_url( 'diluxone-users-social' ),
			__( 'Set them up', 'diluxone-users' )
		);
		?>
	</div>

	<?php if ( array() !== $pending ) : ?>
		<h2><?php esc_html_e( 'First steps', 'diluxone-users' ); ?></h2>
		<?php diluxone_users_steps( $steps ); ?>
	<?php endif; ?>

	<?php if ( array() !== $panels ) : ?>
		<?php diluxone_users_tabs( DILUXONE_USERS_MENU, $labels, $current ); ?>

		<div class="diluxone-users-panel">
			<h2><?php echo esc_html( (string) $labels[ $current ] ); ?></h2>
			<?php
			if ( is_callable( $panels[ $current ]['render'] ) ) {
				call_user_func( $panels[ $current ]['render'] );
			}
			?>
		</div>
	<?php endif; ?>
	<?php

	diluxone_users_screen_close();
}
