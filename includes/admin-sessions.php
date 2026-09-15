<?php
/**
 * The sessions screen: who is in and how long theirs lasts.
 *
 * The list is searched and paginated against the database. A dropdown with
 * every user would be half a megabyte of HTML on each load on a site with
 * twenty-five thousand accounts, and it would be no use anyway: what is
 * needed is to find one person, not to scroll the list.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** Its two tabs on the security screen. */
function diluxone_users_sessions_panels(): void {
	diluxone_users_register_panel(
		DILUXONE_USERS_SECURITY,
		'sessions',
		array(
			'label'    => __( 'Open sessions', 'diluxone-users' ),
			'position' => 30,
			'render'   => 'diluxone_users_screen_sessions_list',
			// The list posts to admin-post and comes back: it is not a
			// settings form and must not be wrapped in one.
			'form'     => false,
		)
	);

	diluxone_users_register_panel(
		DILUXONE_USERS_SECURITY,
		'duration',
		array(
			'label'    => __( 'How long they last', 'diluxone-users' ),
			'position' => 40,
			'render'   => 'diluxone_users_screen_sessions_duration',
			'save'     => 'diluxone_users_sessions_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_sessions_panels' );

/**
 * Behind a proxy: which header carries the visitor's address.
 *
 * Every limit the plugin keeps per address — how often a link may be asked
 * for, how many accounts an address may create — is only as good as the
 * address. Behind a proxy the connection comes from the proxy and the
 * visitor is in a header, and a header anybody can type is not an address
 * unless the connection it came on is trusted. So: one header, and the
 * proxies it is believed from.
 */
function diluxone_users_proxy_panels(): void {
	diluxone_users_register_panel(
		DILUXONE_USERS_SECURITY,
		'proxy',
		array(
			'label'    => __( 'Behind a proxy', 'diluxone-users' ),
			'position' => 50,
			'render'   => 'diluxone_users_screen_proxy',
			'save'     => 'diluxone_users_proxy_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_proxy_panels' );

/** Saves the header and the proxies. */
function diluxone_users_proxy_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	$header = strtoupper( sanitize_key( wp_unslash( $_POST['diluxone_users_ip_header'] ?? '' ) ) );

	diluxone_users_save_options(
		array(
			'diluxone_users_ip_header'       => isset( diluxone_users_ip_headers()[ $header ] ) ? $header : '',
			'diluxone_users_trusted_proxies' => sanitize_textarea_field( wp_unslash( $_POST['diluxone_users_trusted_proxies'] ?? '' ) ),
		)
	);
	// phpcs:enable
}

/** The header and the proxies. */
function diluxone_users_screen_proxy(): void {
	diluxone_users_intro( __( 'Every limit this plugin keeps per address — how often a link may be asked for, how many accounts an address may create — is only as good as the address. Behind a proxy or a CDN the visitor’s address travels in a header, and a header is only believed when the connection it came on is from a proxy this site trusts.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="diluxone_users_ip_header"><?php esc_html_e( 'The header with the visitor’s address', 'diluxone-users' ); ?></label></th>
			<td>
				<select id="diluxone_users_ip_header" name="diluxone_users_ip_header">
					<?php foreach ( diluxone_users_ip_headers() as $diluxone_users_key => $diluxone_users_name ) : ?>
						<option value="<?php echo esc_attr( $diluxone_users_key ); ?>" <?php selected( diluxone_users_ip_header(), $diluxone_users_key ); ?>><?php echo esc_html( $diluxone_users_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php
					printf(
						/* translators: %s: the address the plugin sees for this request */
						esc_html__( 'What this site sees for you right now: %s. If that is your own address, it is set right.', 'diluxone-users' ),
						'<code>' . esc_html( diluxone_users_client_ip() ) . '</code>'
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_trusted_proxies"><?php esc_html_e( 'Trusted proxies', 'diluxone-users' ); ?></label></th>
			<td>
				<textarea id="diluxone_users_trusted_proxies" name="diluxone_users_trusted_proxies" rows="4" class="large-text code"><?php echo esc_textarea( (string) diluxone_users_option( 'diluxone_users_trusted_proxies' ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One address or range per line, such as 203.0.113.0/24. Private and local ranges are always trusted, so a proxy on the same machine or network needs nothing here. A CDN’s edges do.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/** Saves how long a session lasts. */
function diluxone_users_sessions_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_session_long_days'  => absint( wp_unslash( $_POST['diluxone_users_session_long_days'] ?? 30 ) ),
			'diluxone_users_session_short_days' => absint( wp_unslash( $_POST['diluxone_users_session_short_days'] ?? 2 ) ),
			'diluxone_users_sessions_show'      => isset( $_POST['diluxone_users_sessions_show'] ) ? 1 : 0,
		)
	);
	// phpcs:enable
}

/** Screen sessions duration. */
function diluxone_users_screen_sessions_duration(): void {
	diluxone_users_intro( __( 'By default WordPress ends the session after 2 days, or 14 with “remember me”. On a passwordless site that means going through the email again and again.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="diluxone_users_session_long_days"><?php esc_html_e( 'With “remember me”', 'diluxone-users' ); ?></label></th>
				<td>
					<input type="number" id="diluxone_users_session_long_days" name="diluxone_users_session_long_days" min="1" class="small-text" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_session_long_days' ) ); ?>">
					<?php esc_html_e( 'days', 'diluxone-users' ); ?>
					<p class="description"><?php esc_html_e( 'The sign-in link always counts as “remember me”: there is no password to type again.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="diluxone_users_session_short_days"><?php esc_html_e( 'Without “remember me”', 'diluxone-users' ); ?></label></th>
				<td>
					<input type="number" id="diluxone_users_session_short_days" name="diluxone_users_session_short_days" min="1" class="small-text" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_session_short_days' ) ); ?>">
					<?php esc_html_e( 'days', 'diluxone-users' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'In their account', 'diluxone-users' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="diluxone_users_sessions_show" value="1" <?php checked( diluxone_users_option( 'diluxone_users_sessions_show' ), 1 ); ?>>
						<?php esc_html_e( 'Each person sees where they have a session open, and can close them', 'diluxone-users' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'It shows up under Security. With this off, that box is not there —and closing sessions stays an administrator job, from here.', 'diluxone-users' ); ?></p>
				</td>
			</tr>
	</table>
	<?php
}

/** Screen sessions list. */
function diluxone_users_screen_sessions_list(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- it is a read-only search.
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per    = isset( $_GET['per'] ) ? max( 5, min( 200, absint( $_GET['per'] ) ) ) : 20;
	// phpcs:enable

	$result = diluxone_users_sessions_search( $search, $page, $per );
	$total  = $result['total'];
	$pages  = (int) max( 1, ceil( $total / $per ) );
	?>
	<form method="get" class="diluxone-users-search">
		<input type="hidden" name="page" value="<?php echo esc_attr( DILUXONE_USERS_SECURITY ); ?>">
		<input type="hidden" name="tab" value="sessions">
		<label class="screen-reader-text" for="diluxone-users-s"><?php esc_html_e( 'Search', 'diluxone-users' ); ?></label>
		<input type="search" id="diluxone-users-s" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Email, username or name…', 'diluxone-users' ); ?>">
		<?php submit_button( __( 'Search', 'diluxone-users' ), 'secondary', '', false ); ?>

		<a class="button" href="
		<?php
		echo esc_url(
			diluxone_users_admin_url(
				DILUXONE_USERS_SECURITY,
				array(
					'tab' => 'sessions',
					'per' => $per,
				)
			)
		);
		?>
			"><?php esc_html_e( 'Clear', 'diluxone-users' ); ?></a>

		<label class="diluxone-users-search__by">
			<?php esc_html_e( 'Show', 'diluxone-users' ); ?>
			<select name="per" onchange="this.form.submit()">
				<?php foreach ( array( 10, 20, 50, 100 ) as $option ) : ?>
					<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $per, $option ); ?>><?php echo esc_html( number_format_i18n( $option ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php esc_html_e( 'per page', 'diluxone-users' ); ?>
		</label>

		<?php
		$diluxone_users_refresh = diluxone_users_admin_url(
			DILUXONE_USERS_SECURITY,
			array(
				's'     => $search,
				'per'   => $per,
				'paged' => $page,
			)
		);
		?>
		<a class="button diluxone-users-search__refresh" href="<?php echo esc_url( $diluxone_users_refresh ); ?>">
			<span class="dashicons dashicons-update" aria-hidden="true"></span>
			<?php esc_html_e( 'Refresh', 'diluxone-users' ); ?>
		</a>

		<span class="diluxone-users-search__account">
			<?php
			printf(
					/* translators: 1: number of people, 2: current page, 3: total pages */
				esc_html__( '%1$s people with an open session · page %2$d of %3$d', 'diluxone-users' ),
				esc_html( number_format_i18n( $total ) ),
				(int) $page,
				(int) $pages
			);
			?>
		</span>
	</form>

	<table class="wp-list-table widefat fixed striped diluxone-users-list">
		<thead>
			<tr>
				<th class="diluxone-users-list__name"><?php esc_html_e( 'Person', 'diluxone-users' ); ?></th>
				<th><?php esc_html_e( 'Last sign-in', 'diluxone-users' ); ?></th>
				<th><?php esc_html_e( 'Expires', 'diluxone-users' ); ?></th>
				<th><?php esc_html_e( 'Status', 'diluxone-users' ); ?></th>
				<th><?php esc_html_e( 'IP', 'diluxone-users' ); ?></th>
				<th><?php esc_html_e( 'Device', 'diluxone-users' ); ?></th>
				<th class="diluxone-users-list__num"><?php esc_html_e( 'Sessions', 'diluxone-users' ); ?></th>
				<th class="diluxone-users-list__order"><?php esc_html_e( 'Actions', 'diluxone-users' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( array() === $result['rows'] ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'Nobody matches that.', 'diluxone-users' ); ?></td></tr>
			<?php endif; ?>

			<?php
			foreach ( $result['rows'] as $row ) :
				$current = $row['expires'] > time();
				?>
				<tr>
					<td class="diluxone-users-list__name">
						<strong><a href="<?php echo esc_url( get_edit_user_link( $row['user_id'] ) ); ?>"><?php echo esc_html( '' !== $row['name'] ? $row['name'] : $row['login'] ); ?></a></strong>
						<span class="diluxone-users-list__mail"><?php echo esc_html( $row['email'] ); ?></span>
					</td>
					<td><?php echo esc_html( $row['started'] ? (string) wp_date( 'j M Y, H:i', $row['started'] ) : '—' ); ?></td>
					<td><?php echo esc_html( $row['expires'] ? (string) wp_date( 'j M Y, H:i', $row['expires'] ) : '—' ); ?></td>
					<td>
						<span class="diluxone-users-pill diluxone-users-pill--<?php echo $current ? 'on' : 'off'; ?>">
							<?php echo $current ? esc_html__( 'Active', 'diluxone-users' ) : esc_html__( 'Expired', 'diluxone-users' ); ?>
						</span>
					</td>
					<td><code><?php echo esc_html( $row['ip'] ); ?></code></td>
					<td><?php echo esc_html( trim( $row['browser'] . ( '' !== $row['os'] ? ' · ' . $row['os'] : '' ) ) ); ?></td>
					<td class="diluxone-users-list__num"><?php echo esc_html( number_format_i18n( $row['sessions'] ) ); ?></td>
					<td class="diluxone-users-list__order">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="diluxone_users_sessions_admin">
							<input type="hidden" name="diluxone_users_user" value="<?php echo esc_attr( (string) $row['user_id'] ); ?>">
							<?php wp_nonce_field( 'diluxone_users_sessions_admin' ); ?>
							<button type="submit" class="button button-small"><?php esc_html_e( 'Close sessions', 'diluxone-users' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			$diluxone_users_base = diluxone_users_admin_url(
				DILUXONE_USERS_SECURITY,
				array(
					's'   => $search,
					'per' => $per,
				)
			);

			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => $diluxone_users_base . '&paged=%#%',
						'format'    => '',
						'current'   => $page,
						'total'     => $pages,
						'prev_text' => '&lsaquo;',
						'next_text' => '&rsaquo;',
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>
	<?php
}
