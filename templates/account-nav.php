<?php
/**
 * The account-area navigation.
 *
 * @var string                              $current
 * @var array<string, array<string, mixed>> $sections
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="diluxone-users-account__nav" aria-label="<?php esc_attr_e( 'Account sections', 'diluxone-users' ); ?>">
	<?php foreach ( $sections as $diluxone_users_id => $diluxone_users_section ) : ?>
		<a class="diluxone-users-account__tab <?php echo $diluxone_users_id === $current ? 'is-current' : ''; ?>"
			href="<?php echo esc_url( diluxone_users_account_url( $diluxone_users_id ) ); ?>"
			<?php echo $diluxone_users_id === $current ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $diluxone_users_section['label'] ); ?></a>
	<?php endforeach; ?>
</nav>
