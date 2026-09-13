<?php
/**
 * PHPStan analysis bootstrap.
 *
 * Defines plugin constants that are normally created at runtime by the
 * main plugin file (diluxone-users.php). PHPStan analyzes the
 * codebase statically without executing anything, so it never sees the
 * `define()` calls there. Without these stubs, every reference to
 * `DILUXONE_USERS_DIR` and friends produces "Constant not found".
 *
 * This file is referenced from phpstan.neon's `bootstrapFiles:` list.
 * It is excluded from the wp.org deploy via .distignore. It is NOT
 * loaded at plugin runtime — only by PHPStan during analysis.
 *
 * @package DiluxOneUsers
 */

if ( ! defined( 'DILUXONE_USERS_VERSION' ) ) {
	define( 'DILUXONE_USERS_VERSION', '0.0.0-phpstan-stub' );
}
if ( ! defined( 'DILUXONE_USERS_DIR' ) ) {
	define( 'DILUXONE_USERS_DIR', __DIR__ . '/' );
}
if ( ! defined( 'DILUXONE_USERS_URL' ) ) {
	define( 'DILUXONE_USERS_URL', 'https://example.test/wp-content/plugins/diluxone-users/' );
}
if ( ! defined( 'DILUXONE_USERS_FILE' ) ) {
	define( 'DILUXONE_USERS_FILE', __DIR__ . '/diluxone-users.php' );
}

// Constants WordPress defines at run time and that static analysis does not
// see because they come from wp-includes/default-constants.php.
if ( ! defined( 'COOKIEHASH' ) ) {
	define( 'COOKIEHASH', 'phpstan' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
