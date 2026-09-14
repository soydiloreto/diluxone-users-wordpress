<?php
/**
 * The account-area navigation.
 *
 * The look and the direction are classes on the menu itself and not on
 * whatever contains it: the menu can be placed anywhere on the site with
 * [diluxone_users_account_nav], and a menu that only looks right inside the
 * account area is not one that can be placed anywhere.
 *
 * @var string                              $current
 * @var array<string, array<string, mixed>> $sections
 * @var string                              $style   'pills', 'underline' or 'plain'.
 * @var bool                                $column  Down the side rather than across.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

$diluxone_users_nav = array(
	'diluxone-users-account__nav',
	'diluxone-users-account__nav--' . $style,
	'diluxone-users-account__nav--' . ( $column ? 'column' : 'row' ),
);
?>
<nav class="<?php echo esc_attr( implode( ' ', $diluxone_users_nav ) ); ?>" aria-label="<?php esc_attr_e( 'Account sections', 'diluxone-users' ); ?>">
	<?php foreach ( $sections as $diluxone_users_id => $diluxone_users_section ) : ?>
		<a class="diluxone-users-account__tab <?php echo $diluxone_users_id === $current ? 'is-current' : ''; ?>"
			href="<?php echo esc_url( diluxone_users_account_url( $diluxone_users_id ) ); ?>"
			<?php echo $diluxone_users_id === $current ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $diluxone_users_section['label'] ); ?></a>
	<?php endforeach; ?>
</nav>
