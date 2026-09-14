<?php
/**
 * The sign-in form.
 *
 * Overridable from the theme at:
 *   wp-content/themes/<your-theme>/diluxone-users/login.php
 *
 * @var string                             $state     What happened ('sent', 'expired', 'email', 'error', 'social').
 * @var string                             $email     Address the link was sent to.
 * @var array<string, array<string,mixed>> $providers Available networks.
 * @var int                                $minutes   How long the link is good for.
 * @var bool                               $title     Whether to draw the "Sign in" heading.
 *
 * The shape of the page around this — the card, the split, the background —
 * is not in here: it wraps both steps of signing in, so it is printed by
 * diluxone_users_login_frame_open() and a theme replacing this file still
 * gets it.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="diluxone-users diluxone-users-login">

	<?php diluxone_users_login_logo(); ?>

	<?php if ( 'sent' === $state ) : ?>

		<p class="diluxone-users-login__icon"><?php echo diluxone_users_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own markup. ?></p>
		<h2 class="diluxone-users-login__title"><?php echo esc_html( diluxone_users_text( 'diluxone_users_sent_title', __( 'Check your email', 'diluxone-users' ) ) ); ?></h2>
		<p><?php esc_html_e( 'We sent a sign-in link to', 'diluxone-users' ); ?></p>
		<p class="diluxone-users-login__email"><strong><?php echo esc_html( $email ); ?></strong></p>
		<p class="diluxone-users-note">
			<?php
			printf(
				/* translators: %d: how many minutes the link lasts */
				esc_html__( 'Click the link and you are in. It expires in %d minutes and works once.', 'diluxone-users' ),
				(int) $minutes
			);
			?>
		</p>

		<?php
		/*
		 * The way out of this screen. Somebody who typed one letter wrong is
		 * looking at a page telling them to check an inbox that will never
		 * have anything in it, and without this the only way back is the
		 * browser's back button — or waiting for a link that is not coming.
		 */
		?>
		<p class="diluxone-users-login__again">
			<a class="diluxone-users-button diluxone-users-button--soft diluxone-users-button--wide" href="<?php echo esc_url( diluxone_users_login_url() ); ?>">
				<?php esc_html_e( 'Use a different address', 'diluxone-users' ); ?>
			</a>
		</p>

		<p class="diluxone-users-note diluxone-users-note--icon">
			<?php echo diluxone_users_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own markup. ?>
			<span><?php echo esc_html( diluxone_users_text( 'diluxone_users_sent_note', __( 'Did not arrive? Check your spam or promotions folder.', 'diluxone-users' ) ) ); ?></span>
		</p>

	<?php else : ?>

		<?php
		// The heading shows when the shortcode was asked for one, and also
		// whenever the site wrote its own: somebody who takes the trouble to
		// name this screen means it to be read.
		$diluxone_users_heading = diluxone_users_text( 'diluxone_users_login_title' );
		$diluxone_users_intro   = diluxone_users_text( 'diluxone_users_login_intro' );
		?>

		<?php if ( $title || '' !== $diluxone_users_heading ) : ?>
			<h2 class="diluxone-users-login__title">
				<?php echo esc_html( '' !== $diluxone_users_heading ? $diluxone_users_heading : __( 'Sign in', 'diluxone-users' ) ); ?>
			</h2>
		<?php endif; ?>

		<?php if ( '' !== $diluxone_users_intro ) : ?>
			<p class="diluxone-users-login__intro"><?php echo esc_html( $diluxone_users_intro ); ?></p>
		<?php endif; ?>

		<?php if ( 'changed' === $state ) : ?>
			<p class="diluxone-users-notice diluxone-users-notice--ok"><?php esc_html_e( 'Your password is changed. You can sign in with it now.', 'diluxone-users' ); ?></p>
		<?php elseif ( 'expired' === $state ) : ?>
			<p class="diluxone-users-notice diluxone-users-notice--error"><?php esc_html_e( 'That link expired or was already used. Ask for a new one.', 'diluxone-users' ); ?></p>
		<?php elseif ( 'email' === $state ) : ?>
			<p class="diluxone-users-notice diluxone-users-notice--error"><?php esc_html_e( 'That email address does not look valid.', 'diluxone-users' ); ?></p>
		<?php elseif ( 'social' === $state ) : ?>
			<p class="diluxone-users-notice diluxone-users-notice--error"><?php esc_html_e( 'We could not finish signing you in with that provider. Try again or use your email.', 'diluxone-users' ); ?></p>
		<?php elseif ( 'error' === $state ) : ?>
			<p class="diluxone-users-notice diluxone-users-notice--error"><?php esc_html_e( 'Something went wrong. Try again.', 'diluxone-users' ); ?></p>
		<?php endif; ?>

		<?php
		// Guarded: passkeys is one feature in two files, and a site running
		// without them should get a sign-in form, not a fatal error.
		?>
		<?php if ( diluxone_users_has_passkeys() ) : ?>
			<?php diluxone_users_passkeys_enqueue(); ?>
			<p class="diluxone-users-notice" data-diluxone-users-passkey-notice hidden></p>
			<p><button type="button" class="diluxone-users-button diluxone-users-button--wide" data-diluxone-users-passkey="login"><?php esc_html_e( 'Sign in with a passkey', 'diluxone-users' ); ?></button></p>
			<p class="diluxone-users-divider"><span><?php esc_html_e( 'or', 'diluxone-users' ); ?></span></p>
		<?php endif; ?>

		<?php if ( array() !== $providers ) : ?>
			<?php echo diluxone_users_sso_buttons( $providers ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own markup, already escaped. ?>

			<p class="diluxone-users-divider">
				<span>
					<?php
					echo diluxone_users_login_has_link()
						? esc_html__( 'or with your email', 'diluxone-users' )
						: esc_html__( 'or with your password', 'diluxone-users' );
					?>
				</span>
			</p>
		<?php endif; ?>

		<?php if ( diluxone_users_login_has_link() ) : ?>
			<form class="diluxone-users-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="diluxone_users_acceso">
				<?php wp_nonce_field( 'diluxone_users_login', 'diluxone_users_nonce' ); ?>

				<label for="diluxone-users-email"><?php esc_html_e( 'Email address', 'diluxone-users' ); ?></label>
				<input type="email" id="diluxone-users-email" name="diluxone_users_email" required autocomplete="email" placeholder="<?php echo esc_attr_x( 'you@example.com', 'placeholder for the e-mail field', 'diluxone-users' ); ?>">

				<button type="submit" class="diluxone-users-button"><?php esc_html_e( 'Send me the sign-in link', 'diluxone-users' ); ?></button>
			</form>

			<p class="diluxone-users-note diluxone-users-note--icon">
				<?php echo diluxone_users_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own markup. ?>
				<span><?php esc_html_e( 'You get an email with a link. Click it and you are in: no password to choose or type.', 'diluxone-users' ); ?></span>
			</p>
		<?php endif; ?>

		<?php if ( diluxone_users_login_has_password() ) : ?>
			<?php if ( diluxone_users_login_has_link() ) : ?>
				<p class="diluxone-users-divider"><span><?php esc_html_e( 'or with your password', 'diluxone-users' ); ?></span></p>
			<?php endif; ?>

			<?php
			// WordPress's own form, not one of ours: it already brings the
			// "remember me", the redirect and the nonce, and it is the part that
			// least needs touching.
			wp_login_form(
				array(
					'redirect'       => (string) apply_filters( 'diluxone_users_login_redirect', home_url( '/' ), 0 ),
					'label_username' => __( 'Email or username', 'diluxone-users' ),
					'label_password' => __( 'Password', 'diluxone-users' ),
					'label_log_in'   => __( 'Sign in', 'diluxone-users' ),
				)
			);
			?>

			<p class="diluxone-users-note">
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'I forgot my password', 'diluxone-users' ); ?></a>
			</p>
		<?php endif; ?>

		<?php
		/*
		 * The line about the terms goes last and inside the "else": on the
		 * screen that only says an e-mail was sent there is nothing left to
		 * accept. It allows links because that is what it is for — the terms
		 * and the privacy policy are pages, and a legal line that cannot link
		 * to them is not one.
		 */
		$diluxone_users_legal = diluxone_users_text( 'diluxone_users_login_legal' );
		?>

		<?php if ( '' !== $diluxone_users_legal ) : ?>
			<p class="diluxone-users-login__legal"><?php echo wp_kses_post( $diluxone_users_legal ); ?></p>
		<?php endif; ?>

	<?php endif; ?>
</div>
