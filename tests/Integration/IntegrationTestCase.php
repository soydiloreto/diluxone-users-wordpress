<?php
/**
 * Base for the tests that need a real WordPress loaded.
 *
 * The plugin has no tables of its own — its data is options and user meta —
 * so the only things to isolate between tests are those two.
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class IntegrationTestCase extends TestCase {

	/**
	 * The plugin options cleaned up between tests.
	 *
	 * If one is added tomorrow, it goes here: otherwise a test sees what the
	 * previous one left behind and passes — or fails — for the wrong reason.
	 *
	 * @var array<int, string>
	 */
	protected static array $options = array(
		'diluxone_users_fields',
		'diluxone_users_account_sections',
		'diluxone_users_sso',
		'diluxone_users_mail_last',
		'diluxone_users_login_method',
		'diluxone_users_2fa_mode',
		'diluxone_users_passkey_enabled',
	);

	protected function setUp(): void {
		parent::setUp();

		foreach ( self::$options as $option ) {
			delete_option( $option );
		}
	}

	/** A fresh person, with whatever role is passed. */
	protected function alguien( string $role = 'subscriber' ): int {
		return (int) wp_insert_user(
			array(
				'user_login' => 'diluxone_users_' . wp_generate_password( 8, false ),
				'user_email' => wp_generate_password( 8, false ) . '@ejemplo.test',
				'user_pass'  => wp_generate_password( 16 ),
				'role'       => $role,
			)
		);
	}
}
