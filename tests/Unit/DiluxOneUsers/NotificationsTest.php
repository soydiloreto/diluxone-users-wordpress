<?php
/**
 * The notices the plugin brings on its own account.
 *
 * The notifications section existed and arrived empty on a clean install:
 * whatever was offered there had to be registered by the site. This is the
 * guarantee that it does not happen again.
 */

namespace Tests\Unit\DiluxOneUsers;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class NotificationsTest extends TestCase {

	private const USER_ID = 11;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once DILUXONE_USERS_DIR . 'includes/options.php';
		require_once DILUXONE_USERS_DIR . 'includes/notify.php';
		require_once DILUXONE_USERS_DIR . 'includes/account-sections.php';

		$GLOBALS['diluxone_users_test_user_meta'] = array();
		$GLOBALS['_test_wp_options']   = array();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_the_plugin_ships_its_own_notices(): void {
		$defaults = diluxone_users_default_notifications();

		$this->assertArrayHasKey( 'diluxone_users_notify_login', $defaults );
		$this->assertArrayHasKey( 'diluxone_users_notify_security', $defaults );
	}

	public function test_the_plugins_own_notices_come_turned_on(): void {
		// A security notice you have to go and turn on is turned on by nobody,
		// and whoever needs it is precisely the one who never went to look.
		foreach ( diluxone_users_default_notifications() as $pref ) {
			$this->assertSame( '1', $pref['default'] );
		}
	}

	public function test_with_nothing_chosen_the_default_value_is_taken(): void {
		$this->assertTrue( diluxone_users_wants( self::USER_ID, 'diluxone_users_notify_login' ) );
	}

	public function test_whoever_turned_it_off_stops_receiving_it(): void {
		update_user_meta( self::USER_ID, 'diluxone_users_notify_login', '0' );

		$this->assertFalse( diluxone_users_wants( self::USER_ID, 'diluxone_users_notify_login' ) );
	}

	public function test_a_notice_nobody_registered_is_not_sent(): void {
		$this->assertFalse( diluxone_users_wants( self::USER_ID, 'diluxone_users_notify_inventado' ) );
	}

	/* ── The site's rule, and what the person's switch is worth under it ── */

	private const LOGIN = 'diluxone_users_notify_login';

	/** Writes one rule the way the admin screen stores it. */
	private function rule( string $policy ): void {
		$GLOBALS['_test_wp_options']['diluxone_users_notice_rules'] = array( self::LOGIN => $policy );
	}

	public function test_with_no_rule_written_the_policy_is_on_by_default(): void {
		$this->assertSame( 'default_on', diluxone_users_notice_policy( self::LOGIN ) );
	}

	public function test_a_rule_that_is_not_a_map_counts_as_no_rules(): void {
		// A broken option must not silence a security notice.
		$GLOBALS['_test_wp_options']['diluxone_users_notice_rules'] = 'never';

		$this->assertSame( 'default_on', diluxone_users_notice_policy( self::LOGIN ) );
		$this->assertTrue( diluxone_users_wants( self::USER_ID, self::LOGIN ) );
	}

	public function test_a_rule_with_a_word_that_is_not_a_policy_is_dropped(): void {
		$this->rule( 'sometimes' );

		$this->assertSame( 'default_on', diluxone_users_notice_policy( self::LOGIN ) );
	}

	public function test_on_by_default_sends_until_the_person_turns_it_off(): void {
		$this->rule( 'default_on' );
		$this->assertTrue( diluxone_users_wants( self::USER_ID, self::LOGIN ) );

		update_user_meta( self::USER_ID, self::LOGIN, '0' );
		$this->assertFalse( diluxone_users_wants( self::USER_ID, self::LOGIN ) );
	}

	public function test_off_by_default_stays_quiet_until_the_person_turns_it_on(): void {
		$this->rule( 'default_off' );
		$this->assertFalse( diluxone_users_wants( self::USER_ID, self::LOGIN ) );

		update_user_meta( self::USER_ID, self::LOGIN, '1' );
		$this->assertTrue( diluxone_users_wants( self::USER_ID, self::LOGIN ) );
	}

	public function test_always_is_sent_whatever_the_person_chose(): void {
		$this->rule( 'always' );
		$this->assertTrue( diluxone_users_wants( self::USER_ID, self::LOGIN ) );

		update_user_meta( self::USER_ID, self::LOGIN, '0' );
		$this->assertTrue( diluxone_users_wants( self::USER_ID, self::LOGIN ) );
	}

	public function test_never_is_not_sent_whatever_the_person_chose(): void {
		$this->rule( 'never' );
		$this->assertFalse( diluxone_users_wants( self::USER_ID, self::LOGIN ) );

		update_user_meta( self::USER_ID, self::LOGIN, '1' );
		$this->assertFalse( diluxone_users_wants( self::USER_ID, self::LOGIN ) );
	}

	public function test_a_rule_does_not_leak_onto_other_notices(): void {
		$this->rule( 'never' );

		$this->assertTrue( diluxone_users_wants( self::USER_ID, 'diluxone_users_notify_security' ) );
	}

	/* ── What the account area offers a switch for ───────────────────── */

	public function test_only_the_notices_with_a_switch_reach_the_account_area(): void {
		$this->rule( 'always' );
		$choices = diluxone_users_notification_choices();

		$this->assertArrayNotHasKey( self::LOGIN, $choices );
		$this->assertArrayHasKey( 'diluxone_users_notify_security', $choices );
	}

	public function test_the_switch_starts_where_the_policy_says(): void {
		$this->rule( 'default_off' );

		$this->assertSame( '', diluxone_users_notification_choices()[ self::LOGIN ]['default'] );
	}

	public function test_with_every_notice_decided_by_the_site_the_section_goes_away(): void {
		$GLOBALS['_test_wp_options']['diluxone_users_notice_rules'] = array(
			self::LOGIN                       => 'always',
			'diluxone_users_notify_security' => 'never',
		);

		$this->assertFalse( diluxone_users_notifications_any() );
	}

	/* ── What the admin screen writes down ───────────────────────────── */

	public function test_saving_keeps_the_four_policies_and_drops_the_rest(): void {
		require_once DILUXONE_USERS_DIR . 'includes/admin-notices.php';

		// A rule for an add-on's notice, written last month, survives the
		// add-on not being on the form today.
		$GLOBALS['_test_wp_options']['diluxone_users_notice_rules'] = array( 'lms_notify_course' => 'never' );

		$_POST['diluxone_users_notice_rules'] = array(
			self::LOGIN                       => 'always',
			'diluxone_users_notify_security' => 'sometimes',
		);

		diluxone_users_notices_rules_save();

		$this->assertSame(
			array(
				'lms_notify_course' => 'never',
				self::LOGIN         => 'always',
			),
			$GLOBALS['_test_wp_options']['diluxone_users_notice_rules']
		);

		unset( $_POST['diluxone_users_notice_rules'] );
	}

	public function test_the_same_browser_gives_the_same_identifier(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Chrome/140';
		$first = diluxone_users_device_id();

		$this->assertSame( $first, diluxone_users_device_id() );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone) Safari/605';
		$this->assertNotSame( $first, diluxone_users_device_id() );
	}
}
