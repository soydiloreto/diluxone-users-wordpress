<?php
/**
 * The account area.
 *
 * Two shapes come out of here and they are the same markup with a different
 * class on the outside: 'plain' is a panel sitting in the page, the way a
 * settings screen looks, and 'cover' puts the person on a coloured band the
 * full width of the window with the menu in a bar of its own underneath, the
 * way a profile looks. Which pieces the header is made of — the picture, the
 * date they joined, the button — are asked separately, because a site that
 * wants the big cover without the join date should not have to copy this file
 * to get it.
 *
 * @var string                              $current
 * @var bool                                $header
 * @var bool                                $avatar
 * @var bool                                $since
 * @var bool                                $action
 * @var string                              $cover
 * @var string                              $layout
 * @var string                              $template
 * @var string                              $width
 * @var array<string, array<string, mixed>> $sections
 * @var WP_User                             $user
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

$diluxone_users_classes = array(
	'diluxone-users-account',
	'diluxone-users-account--' . $layout,
	'diluxone-users-account--' . $template,
	'diluxone-users-account--' . $width,
);
?>
<div class="<?php echo esc_attr( implode( ' ', $diluxone_users_classes ) ); ?>"
	<?php echo 'cover' === $template && '' !== $cover ? 'style="--diluxone-users-cover: ' . esc_attr( $cover ) . '"' : ''; ?>>

	<?php if ( $header ) : ?>
		<div class="diluxone-users-account__header">
			<div class="diluxone-users-account__header-inner">
				<?php if ( $avatar ) : ?>
					<span class="diluxone-users-account__avatar"><?php echo get_avatar( $user->ID, 'cover' === $template ? 96 : 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress markup. ?></span>
				<?php endif; ?>

				<div class="diluxone-users-account__who">
					<h1 class="diluxone-users-account__name"><?php echo esc_html( diluxone_users_display_name( $user ) ); ?></h1>

					<?php if ( $since ) : ?>
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
					<?php endif; ?>
				</div>

				<?php if ( $action && isset( $sections['details'] ) ) : ?>
					<a class="diluxone-users-button diluxone-users-button--line diluxone-users-account__action" href="<?php echo esc_url( diluxone_users_account_url( 'details' ) ); ?>">
						<?php esc_html_e( 'Edit profile', 'diluxone-users' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php
	// With tabs, the bar is its own strip across the area so it can run the
	// full width under a cover; down the side it belongs inside the body, next
	// to what it is navigating.
	?>
	<?php if ( 'tabs' === $layout ) : ?>
		<div class="diluxone-users-account__bar">
			<?php echo diluxone_users_account_nav( $sections, $current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own markup, already escaped. ?>
		</div>
	<?php endif; ?>

	<div class="diluxone-users-account__body">
		<?php if ( 'side' === $layout ) : ?>
			<?php echo diluxone_users_account_nav( $sections, $current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own markup, already escaped. ?>
		<?php endif; ?>

		<div class="diluxone-users-account__section">
			<?php
			$diluxone_users_section = $sections[ $current ];

			echo diluxone_users_account_heading_html( $diluxone_users_section, $current, $user ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			echo diluxone_users_account_section_html( $diluxone_users_section, $user ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised inside.
			?>
		</div>
	</div>
</div>
