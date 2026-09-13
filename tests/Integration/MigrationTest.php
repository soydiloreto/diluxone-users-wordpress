<?php
/**
 * The rename migration, end to end.
 *
 * This exists because the migration broke in a way nothing caught: a site was
 * left with its options and its user meta moved and the shortcodes inside its
 * pages still written the old way, and the mark said the job was done. Three
 * separate faults lined up — a loop that overwrote the prefix it was given, a
 * mark that awarded itself half way through, and an early return that skipped
 * every step after the options. A test that moves real data through it would
 * have caught all three.
 */

namespace Tests\Integration;

class MigrationTest extends IntegrationTestCase {

	private const OLD = 'users_dlx_plus_';

	protected function setUp(): void {
		parent::setUp();

		delete_option( DILUXONE_USERS_MIGRATED );

		foreach ( array( 'fields', 'login_page', 'account_page' ) as $key ) {
			delete_option( self::OLD . $key );
			delete_option( DILUXONE_USERS_PREFIX . $key );
		}
	}

	public function test_the_options_move_to_the_new_prefix(): void {
		update_option( self::OLD . 'login_page', 42 );

		diluxone_users_migrate();

		$this->assertSame( 42, (int) get_option( DILUXONE_USERS_PREFIX . 'login_page' ) );
		$this->assertFalse( get_option( self::OLD . 'login_page' ) );
	}

	public function test_the_user_meta_moves_with_them(): void {
		$user = $this->make_user();
		update_user_meta( $user, self::OLD . 'handle', 'pablo' );

		diluxone_users_migrate();

		$this->assertSame( 'pablo', get_user_meta( $user, DILUXONE_USERS_PREFIX . 'handle', true ) );
		$this->assertSame( '', get_user_meta( $user, self::OLD . 'handle', true ) );
	}

	public function test_the_shortcodes_written_in_pages_move_too(): void {
		$page = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Sign in',
				'post_content' => '<p>[' . self::OLD . 'login]</p>',
			)
		);

		update_option( self::OLD . 'login_page', $page );

		diluxone_users_migrate();

		$content = (string) get_post_field( 'post_content', $page, 'raw' );

		$this->assertStringContainsString( '[' . DILUXONE_USERS_PREFIX . 'login]', $content );
		$this->assertStringNotContainsString( '[' . self::OLD, $content );

		wp_delete_post( $page, true );
	}

	/**
	 * The one that was actually broken: options already moved, everything else
	 * still to do. A migration that stops at the first step it finds nothing
	 * to do in leaves the site half moved for good.
	 */
	public function test_it_finishes_a_run_that_stopped_after_the_options(): void {
		$user = $this->make_user();
		update_user_meta( $user, self::OLD . 'handle', 'half-way' );

		$page = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Half way',
				'post_content' => '[' . self::OLD . 'account]',
			)
		);

		// No option carries the old prefix: the previous run moved them all.
		diluxone_users_migrate();

		$this->assertSame( 'half-way', get_user_meta( $user, DILUXONE_USERS_PREFIX . 'handle', true ) );
		$this->assertStringContainsString(
			'[' . DILUXONE_USERS_PREFIX . 'account]',
			(string) get_post_field( 'post_content', $page, 'raw' )
		);

		wp_delete_post( $page, true );
	}

	/** The mark is the version that migrated, and it is written at the end. */
	public function test_the_mark_holds_the_version_and_not_a_yes(): void {
		update_option( self::OLD . 'login_page', 7 );

		diluxone_users_migrate();

		$this->assertSame( DILUXONE_USERS_VERSION, get_option( DILUXONE_USERS_MIGRATED ) );
	}

	/**
	 * An old mark must not become the new one on its way past: that is what
	 * let a half-finished run call itself finished.
	 */
	public function test_an_old_mark_does_not_become_the_new_one(): void {
		update_option( self::OLD . 'migrated', 1 );
		update_option( self::OLD . 'login_page', 9 );
		delete_option( DILUXONE_USERS_MIGRATED );

		diluxone_users_migrate_prefix( self::OLD );

		$this->assertNotSame( '1', get_option( DILUXONE_USERS_MIGRATED ) );

		delete_option( self::OLD . 'migrated' );
	}
}
