<?php
/**
 * Datos personales.
 *
 * Variables: $user.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;
?>
<?php echo do_shortcode( '[diluxone_users_avatar]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php echo do_shortcode( '[diluxone_users_handle]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. ?>
<?php
echo do_shortcode( '[diluxone_users_fields]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode propio. 
