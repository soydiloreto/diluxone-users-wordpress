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
 * The answer has two halves. The site decides the rule for each notice; where
 * the rule leaves a switch, the person sets it from their own account area.
 * The first tab shows the whole picture, the second is where the rules are
 * written, and the third is the wording of the one e-mail nobody can refuse.
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
		'summary',
		array(
			'label'    => __( 'Summary', 'diluxone-users' ),
			'position' => 0,
			'render'   => 'diluxone_users_screen_notices_summary',
			// Nothing to save: it is the picture, and each row says where to
			// go to change it.
			'form'     => false,
		)
	);

	diluxone_users_register_panel(
		DILUXONE_USERS_NOTICES,
		'rules',
		array(
			'label'    => __( 'Notices', 'diluxone-users' ),
			'position' => 10,
			'render'   => 'diluxone_users_screen_notices_rules',
			'save'     => 'diluxone_users_notices_rules_save',
		)
	);

	diluxone_users_register_panel(
		DILUXONE_USERS_NOTICES,
		'link',
		array(
			'label'    => __( 'The sign-in e-mail', 'diluxone-users' ),
			'position' => 20,
			'render'   => 'diluxone_users_screen_login_email',
			'save'     => 'diluxone_users_login_email_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_notices_panels' );

/* ── Resumen ───────────────────────────────────────────────────────── */

/**
 * Who a notice belongs to, in words.
 *
 * On a site with three plugins adding notices, "where did this e-mail come
 * from" is the question actually asked, so every row answers it.
 */
function diluxone_users_notice_origin( string $key ): string {
	return isset( diluxone_users_default_notifications()[ $key ] )
		? __( 'comes from: this plugin', 'diluxone-users' )
		: __( 'comes from: something else on this site', 'diluxone-users' );
}

/**
 * Everything that leaves this site by e-mail, one row each.
 *
 * First the notices with a rule, then the ones nobody can refuse. The latter
 * go out because they were asked for — somebody who presses "send me the
 * link" and gets nothing has no way in — so their state is whether the
 * feature is in use, not whether anybody wants them. When the last e-mail
 * this site sent failed, none of them can be counted on, and the row says so
 * instead of showing a green pill over an empty inbox.
 */
function diluxone_users_screen_notices_summary(): void {
	$policies = diluxone_users_notice_policies();
	$rules    = diluxone_users_admin_url( DILUXONE_USERS_NOTICES, array( 'tab' => 'rules' ) );
	$rows     = array();

	foreach ( diluxone_users_notification_prefs() as $key => $notice ) {
		$policy = diluxone_users_notice_policy( (string) $key );

		$rows[] = array(
			'label'  => (string) $notice['label'],
			'state'  => 'never' === $policy ? 'off' : 'active',
			'detail' => $policies[ $policy ] . ' · ' . diluxone_users_notice_origin( (string) $key ),
			'url'    => $rules,
		);
	}

	// Sent by the plugin, with no switch. A failed delivery is the one thing
	// that turns these from "it goes out" to "nobody can tell".
	$delivery = diluxone_users_mail_works();
	$status   = diluxone_users_admin_url( 'diluxone-users-status' );
	$why      = __( 'the last e-mail this site sent did not go out', 'diluxone-users' );

	$link_on = diluxone_users_login_has_link();
	$rows[]  = array(
		'label'  => __( 'The sign-in link', 'diluxone-users' ),
		'state'  => $link_on ? ( $delivery ? 'active' : 'unknown' ) : 'off',
		'why'    => $link_on && ! $delivery ? $why : '',
		'detail' => $link_on
			? __( 'Sent when somebody asks to get in. Nobody can turn it off: without it there is no way in.', 'diluxone-users' )
			: __( 'Not offered: people sign in to this site with a password only.', 'diluxone-users' ),
		'url'    => $link_on
			? ( $delivery ? diluxone_users_admin_url( DILUXONE_USERS_NOTICES, array( 'tab' => 'link' ) ) : $status )
			: diluxone_users_admin_url( 'diluxone-users-login', array( 'tab' => 'doors' ) ),
	);

	$code_on = diluxone_users_notice_2fa_email();
	$rows[]  = array(
		'label'  => __( 'The second-step code', 'diluxone-users' ),
		'state'  => $code_on ? ( $delivery ? 'active' : 'unknown' ) : 'off',
		'why'    => $code_on && ! $delivery ? $why : '',
		'detail' => $code_on
			? __( 'Sent when somebody halfway through signing in is waiting for it. Nobody can turn it off: a code that does not arrive locks them out.', 'diluxone-users' )
			: __( 'Not in use: a code by e-mail is not one of the second steps this site offers.', 'diluxone-users' ),
		'url'    => $code_on && ! $delivery ? $status : diluxone_users_admin_url( DILUXONE_USERS_SECURITY, array( 'tab' => '2fa' ) ),
	);

	// WordPress sends this one, not the plugin: the plugin only opens the
	// door. Whether it is delivered is beyond what can be told from here.
	$data_on = diluxone_users_privacy_any();
	$rows[]  = array(
		'label'  => __( 'Their own data', 'diluxone-users' ),
		'state'  => $data_on ? 'unknown' : 'off',
		'detail' => $data_on
			? __( 'The confirmation WordPress sends when somebody asks to download their data or delete their account. It is WordPress that sends it, so this screen cannot tell whether it arrives.', 'diluxone-users' )
			: __( 'Not in use: neither downloading their data nor deleting their account is offered.', 'diluxone-users' ),
	);

	diluxone_users_summary_table( $rows );

	diluxone_users_panel_actions(
		array(
			__( 'Send myself a test', 'diluxone-users' ) => diluxone_users_admin_url( 'diluxone-users-status', array( 'tab' => 'tools' ) ),
		)
	);
}

/* ── Las reglas ────────────────────────────────────────────────────── */

/**
 * One rule per notice.
 *
 * The four choices are written as outcomes and not as settings — what
 * happens to the person, not what the option is called — because the
 * question being answered is "who decides", and "default_off" answers a
 * different one.
 */
function diluxone_users_screen_notices_rules(): void {
	$prefs    = diluxone_users_notification_prefs();
	$policies = diluxone_users_notice_policies();

	diluxone_users_intro( __( 'The site decides the rule for each notice; where the rule leaves a switch, each person sets it from their own account area.', 'diluxone-users' ) );

	if ( array() === $prefs ) {
		echo '<p>' . esc_html__( 'Nothing is registered: beyond the sign-in e-mail, this site tells nobody anything.', 'diluxone-users' ) . '</p>';

		return;
	}
	?>
	<div class="diluxone-users-rules">
		<?php
		/*
		 * Each notice is one block and not a row of a two-column table: what it
		 * is called and what it is were in different cells, so the sentence
		 * explaining a notice sat beside the rule of the one above it. Name,
		 * rule and explanation now belong to the same piece, in that order.
		 */
		foreach ( $prefs as $key => $notice ) :
			$id     = 'diluxone_users_notice_rules_' . (string) $key;
			$policy = diluxone_users_notice_policy( (string) $key );

			diluxone_users_ui_field_open( (string) $notice['label'], $id );
			?>
			<select id="<?php echo esc_attr( $id ); ?>" name="diluxone_users_notice_rules[<?php echo esc_attr( (string) $key ); ?>]">
				<?php foreach ( $policies as $value => $words ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $policy, $value ); ?>><?php echo esc_html( $words ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			diluxone_users_ui_field_close(
				trim( (string) ( $notice['help'] ?? '' ) . '<br><code>' . esc_html( (string) $key ) . '</code> · ' . esc_html( diluxone_users_notice_origin( (string) $key ) ) )
			);
		endforeach;
		?>
	</div>
	<?php
}

/**
 * Writes the rules down.
 *
 * What arrives is merged over what was stored and not put in its place: the
 * form only carries the notices registered right now, and a rule written for
 * an add-on's notice should survive the add-on being switched off for a week.
 * A word that is not one of the four policies is dropped, not stored.
 */
function diluxone_users_notices_rules_save(): void {
	$policies = diluxone_users_notice_policies();
	$rules    = diluxone_users_notice_rules();

	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the panel verifies the nonce; every key and value is sanitised one at a time inside the loop.
	foreach ( (array) wp_unslash( $_POST['diluxone_users_notice_rules'] ?? array() ) as $key => $policy ) {
		$key    = sanitize_key( (string) $key );
		$policy = sanitize_key( (string) $policy );

		if ( '' === $key || ! isset( $policies[ $policy ] ) ) {
			continue;
		}

		$rules[ $key ] = $policy;
	}

	diluxone_users_save_options( array( 'diluxone_users_notice_rules' => $rules ) );
}
