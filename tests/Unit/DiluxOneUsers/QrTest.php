<?php
/**
 * The QR code generator.
 *
 * A badly built QR does not look wrong: it looks the same. That is why what
 * is tested here is the structure — size, finder patterns, timing patterns,
 * fixed module — and not that it "returns something". The proof that it also
 * scans is done by a real reader, outside this suite.
 */

namespace Tests\Unit\DiluxOneUsers;

use PHPUnit\Framework\TestCase;

class QrTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		require_once DILUXONE_USERS_DIR . 'includes/qr.php';
	}

	public function test_the_version_grows_with_the_text(): void {
		// The side is 17 + 4 × version.
		$this->assertCount( 21, diluxone_users_qr_matrix( 'hola' ) );
		$this->assertCount( 45, diluxone_users_qr_matrix( str_repeat( 'a', 150 ) ) );
		$this->assertCount( 57, diluxone_users_qr_matrix( str_repeat( 'a', 260 ) ) );
	}

	public function test_what_does_not_fit_returns_null(): void {
		$this->assertNull( diluxone_users_qr_matrix( str_repeat( 'a', 500 ) ) );
	}

	public function test_the_three_finder_patterns_are_there(): void {
		$m    = diluxone_users_qr_matrix( 'otpauth://totp/x?secret=ABCDEFGHIJKLMNOP' );
		$side = count( $m );

		foreach ( array( array( 0, 0 ), array( 0, $side - 7 ), array( $side - 7, 0 ) ) as [$row, $col] ) {
			// The outer ring is black and the inner border white.
			$this->assertTrue( $m[ $row ][ $col ] );
			$this->assertTrue( $m[ $row ][ $col + 6 ] );
			$this->assertTrue( $m[ $row + 6 ][ $col ] );
			$this->assertFalse( $m[ $row + 1 ][ $col + 1 ] );
			$this->assertTrue( $m[ $row + 3 ][ $col + 3 ] );
		}
	}

	public function test_the_timing_patterns_alternate(): void {
		$m    = diluxone_users_qr_matrix( 'hola' );
		$side = count( $m );

		for ( $i = 8; $i < $side - 8; $i++ ) {
			$this->assertSame( 0 === $i % 2, $m[6][ $i ] );
			$this->assertSame( 0 === $i % 2, $m[ $i ][6] );
		}
	}

	public function test_the_fixed_module_is_black(): void {
		$m = diluxone_users_qr_matrix( 'hola' );

		$this->assertTrue( $m[ count( $m ) - 8 ][8] );
	}

	public function test_the_svg_carries_the_quiet_zone_the_standard_asks_for(): void {
		$svg = diluxone_users_qr_svg( 'hola', 200 );

		// 21 modules + 4 of margin on each side.
		$this->assertStringContainsString( 'viewBox="0 0 29 29"', $svg );
		$this->assertStringContainsString( 'width="200"', $svg );
	}

	public function test_with_no_room_it_returns_no_svg(): void {
		$this->assertSame( '', diluxone_users_qr_svg( str_repeat( 'a', 500 ) ) );
	}
}
