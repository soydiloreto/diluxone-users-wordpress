<?php
/**
 * The account area.
 *
 * @var string                              $current
 * @var bool                                $header
 * @var string                              $layout
 * @var array<string, array<string, mixed>> $sections
 * @var WP_User                             $user
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="diluxone-users-account diluxone-users-account--<?php echo esc_attr( $layout ); ?>">

	<?php if ( $header ) : ?>
		<div class="diluxone-users-account__header">
			<span class="diluxone-users-account__avatar"><?php echo get_avatar( $user->ID, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado de WordPress. ?></span>
			<div>
				<h1 class="diluxone-users-account__name"><?php echo esc_html( diluxone_users_display_name( $user ) ); ?></h1>
				<p class="diluxone-users-account__since">
					<?php
					echo esc_html(
						sprintf(
						/* translators: %s: month and year they joined */
							__( 'Member since %s', 'diluxone-users' ),
							wp_date( 'F Y', (int) strtotime( $user->user_registered ) )
						)
					);
					?>
				</p>
			</div>
		</div>
	<?php endif; ?>

	<div class="diluxone-users-account__body">
		<?php if ( 'none' !== $layout ) : ?>
			<?php echo diluxone_users_account_nav( $sections, $current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>
		<?php endif; ?>

		<div class="diluxone-users-account__section">
			<?php
			$diluxone_users_section = $sections[ $current ];

			echo diluxone_users_account_heading_html( $diluxone_users_section, $current, $user ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado adentro.
			echo diluxone_users_account_section_html( $diluxone_users_section, $user ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- saneado adentro.
			?>
		</div>
	</div>
</div>
