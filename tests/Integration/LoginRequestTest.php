<?php
/**
 * H-04: the sign-in form creates accounts, and creating accounts is counted
 * per machine — the same count the registration form keeps.
 *
 * Before, only the address was throttled, and a script with a new address
 * each time was never throttled at all: unlimited accounts, and a sign-in
 * link mailed from the site to every address it typed.
 */

namespace Tests\Integration;

class LoginRequestTest extends IntegrationTestCase {

	/** Addresses are the test's own: accounts outlive the test that made them. */
	private string $run;

	protected function setUp(): void {
		parent::setUp();

		update_option( 'diluxone_users_login_register', 1 );

		$this->run = wp_generate_password( 8, false );
	}

	private function email( string $name ): string {
		return $name . '-' . $this->run . '@example.test';
	}

	/** Asks for a link as this address, from this machine, and returns where it went. */
	private function ask( string $email, string $ip = '203.0.113.7' ): string {
		$_SERVER['REMOTE_ADDR'] = $ip;

		$this->postAs(
			0,
			array(
				'diluxone_users_nonce' => wp_create_nonce( 'diluxone_users_login' ),
				'diluxone_users_email' => $email,
			)
		);

		return $this->expectRedirect( 'diluxone_users_login_request' );
	}

	public function test_one_machine_creates_at_most_the_burst_of_accounts_and_the_answer_never_changes(): void {
		$burst = diluxone_users_register_burst();

		for ( $i = 1; $i <= $burst + 1; $i++ ) {
			$url = $this->ask( $this->email( "new-$i" ) );

			$this->assertSame( 'sent', $this->redirectState( $url ), 'The screen says the same thing whatever happened' );
		}

		$this->assertGreaterThan( 0, email_exists( $this->email( "new-$burst" ) ) );
		$this->assertFalse( email_exists( $this->email( 'new-' . ( $burst + 1 ) ) ), 'The one past the burst is not created' );
		$this->assertCount( $burst, self::$mail, 'And no link goes out for it' );
	}

	public function test_another_machine_has_its_own_count(): void {
		for ( $i = 1; $i <= diluxone_users_register_burst(); $i++ ) {
			$this->ask( $this->email( "first-$i" ), '203.0.113.7' );
		}

		$this->ask( $this->email( 'elsewhere' ), '198.51.100.9' );

		$this->assertGreaterThan( 0, email_exists( $this->email( 'elsewhere' ) ) );
	}

	public function test_an_existing_account_neither_spends_nor_needs_the_count(): void {
		$existing = get_userdata( $this->make_user() )->user_email;

		for ( $i = 1; $i <= diluxone_users_register_burst(); $i++ ) {
			$this->ask( $this->email( "spend-$i" ) );
		}

		$before = count( self::$mail );
		$this->ask( $existing );

		$this->assertCount( $before + 1, self::$mail, 'Whoever already has an account still gets their link' );
	}

	public function test_with_the_link_not_creating_accounts_nothing_is_created_and_nothing_is_counted(): void {
		update_option( 'diluxone_users_login_register', 0 );

		$this->ask( $this->email( 'nobody' ) );

		$this->assertFalse( email_exists( $this->email( 'nobody' ) ) );
		$this->assertCount( 0, self::$mail );
		$this->assertTrue( diluxone_users_register_allowed(), 'The count was not spent on an account that could not be made' );
	}

	public function test_without_the_nonce_nothing_happens(): void {
		$this->postAs( 0, array( 'diluxone_users_email' => $this->email( 'x' ) ) );

		$this->assertSame( 'error', $this->redirectState( $this->expectRedirect( 'diluxone_users_login_request' ) ) );
		$this->assertFalse( email_exists( $this->email( 'x' ) ) );
	}
}
