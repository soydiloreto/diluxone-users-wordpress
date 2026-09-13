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

	public function test_the_same_browser_gives_the_same_identifier(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Chrome/140';
		$first = diluxone_users_device_id();

		$this->assertSame( $first, diluxone_users_device_id() );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone) Safari/605';
		$this->assertNotSame( $first, diluxone_users_device_id() );
	}
}
