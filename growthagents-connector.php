<?php
/**
 * Plugin Name:       GrowthAgents Connector
 * Plugin URI:        https://growthagents.ai
 * Description:       Connects this WordPress site to GrowthAgents so it can publish through a dedicated, revocable connection instead of a shared application password.
 * Version:           0.1.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            GrowthAgents
 * Author URI:        https://growthagents.ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       growthagents-connector
 *
 * v0.1: connection only. No pixel, no publish namespace — GA talks to core
 * /wp/v2/* once this site is paired.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access is not allowed.
}

define( 'GROWTHAGENTS_CONNECTOR_VERSION', '0.1.0' );
define( 'GROWTHAGENTS_CONNECTOR_PAIR_URL', 'https://app.growthagents.ai/api/wordpress/pair' );
define( 'GROWTHAGENTS_CONNECTOR_TOKEN_OPTION', 'growthagents_connector_token' );
define( 'GROWTHAGENTS_CONNECTOR_USER_ID_OPTION', 'growthagents_connector_user_id' );

/**
 * ---------------------------------------------------------------------------
 * Admin menu + settings page.
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_menu', 'growthagents_connector_register_menu' );

function growthagents_connector_register_menu() {
	add_options_page(
		__( 'GrowthAgents', 'growthagents-connector' ),
		__( 'GrowthAgents', 'growthagents-connector' ),
		'manage_options',
		'growthagents-connector',
		'growthagents_connector_render_settings_page'
	);
}

function growthagents_connector_settings_url() {
	return admin_url( 'options-general.php?page=growthagents-connector' );
}

function growthagents_connector_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$token     = get_option( GROWTHAGENTS_CONNECTOR_TOKEN_OPTION );
	$user_id   = get_option( GROWTHAGENTS_CONNECTOR_USER_ID_OPTION );
	$connected = ! empty( $token ) && ! empty( $user_id );
	$notice    = growthagents_connector_take_notice();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'GrowthAgents', 'growthagents-connector' ); ?></h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( 'success' === $notice['type'] ? 'success' : 'error' ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $connected ) : ?>
			<p>
				<?php
				printf(
					/* translators: %d: WordPress user ID GrowthAgents publishes as. */
					esc_html__( 'Connected. GrowthAgents publishes to this site as user #%d.', 'growthagents-connector' ),
					(int) $user_id
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="growthagents_connector_disconnect" />
				<?php wp_nonce_field( 'growthagents_connector_disconnect' ); ?>
				<?php submit_button( __( 'Disconnect', 'growthagents-connector' ), 'delete' ); ?>
			</form>
			<p class="description">
				<?php esc_html_e( 'Disconnecting removes this connection only. The growthagents WordPress user is kept so already-published posts stay attributed.', 'growthagents-connector' ); ?>
			</p>
		<?php else : ?>
			<p>
				<?php esc_html_e( 'Paste the pairing code from your GrowthAgents workspace (Settings → Integrations → WordPress) to connect this site.', 'growthagents-connector' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="growthagents_connector_connect" />
				<?php wp_nonce_field( 'growthagents_connector_connect' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="growthagents_pairing_code"><?php esc_html_e( 'Pairing code', 'growthagents-connector' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="growthagents_pairing_code"
								name="growthagents_pairing_code"
								class="regular-text"
								autocomplete="off"
								spellcheck="false"
								required
							/>
							<p class="description">
								<?php esc_html_e( 'Single-use and short-lived — generate one from your GrowthAgents workspace right before pasting it here.', 'growthagents-connector' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Connect', 'growthagents-connector' ) ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * ---------------------------------------------------------------------------
 * One-shot admin notice, stored per-user so a redirect after admin-post.php
 * can still show it without smuggling arbitrary text through the query string.
 * ---------------------------------------------------------------------------
 */

function growthagents_connector_set_notice( $type, $message ) {
	set_transient( 'growthagents_connector_notice_' . get_current_user_id(), array(
		'type'    => $type,
		'message' => $message,
	), 60 );
}

function growthagents_connector_take_notice() {
	$key    = 'growthagents_connector_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	if ( $notice ) {
		delete_transient( $key );
	}
	return $notice;
}

/**
 * ---------------------------------------------------------------------------
 * Connect: pair this site with a GrowthAgents workspace.
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_post_growthagents_connector_connect', 'growthagents_connector_handle_connect' );

function growthagents_connector_handle_connect() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'growthagents-connector' ) );
	}
	check_admin_referer( 'growthagents_connector_connect' );

	$code = isset( $_POST['growthagents_pairing_code'] )
		? sanitize_text_field( wp_unslash( $_POST['growthagents_pairing_code'] ) )
		: '';

	if ( '' === $code ) {
		growthagents_connector_set_notice( 'error', __( 'Enter a pairing code.', 'growthagents-connector' ) );
		wp_safe_redirect( growthagents_connector_settings_url() );
		exit;
	}

	$user_id = growthagents_connector_get_or_create_user();
	if ( is_wp_error( $user_id ) ) {
		growthagents_connector_set_notice( 'error', $user_id->get_error_message() );
		wp_safe_redirect( growthagents_connector_settings_url() );
		exit;
	}

	// Never logged, never echoed — only ever sent over HTTPS to the pairing
	// endpoint and, on success, written to this site's own options table.
	$token = wp_generate_password( 64, false );

	$response = wp_remote_post(
		GROWTHAGENTS_CONNECTOR_PAIR_URL,
		array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'code'     => $code,
					'site_url' => home_url(),
					'token'    => $token,
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		growthagents_connector_set_notice( 'error', $response->get_error_message() );
		wp_safe_redirect( growthagents_connector_settings_url() );
		exit;
	}

	$status = wp_remote_retrieve_response_code( $response );

	if ( 200 === (int) $status ) {
		update_option( GROWTHAGENTS_CONNECTOR_TOKEN_OPTION, $token );
		update_option( GROWTHAGENTS_CONNECTOR_USER_ID_OPTION, $user_id );
		growthagents_connector_set_notice( 'success', __( 'Connected to GrowthAgents.', 'growthagents-connector' ) );
		wp_safe_redirect( growthagents_connector_settings_url() );
		exit;
	}

	// Non-200: show the API's own error message verbatim. Store nothing —
	// the user account created/found above is idempotent and left in place,
	// but no token or user id is persisted, so this site stays disconnected.
	$body    = json_decode( wp_remote_retrieve_body( $response ), true );
	$message = ( is_array( $body ) && ! empty( $body['error'] ) )
		? $body['error']
		: __( 'Pairing failed.', 'growthagents-connector' );

	growthagents_connector_set_notice( 'error', $message );
	wp_safe_redirect( growthagents_connector_settings_url() );
	exit;
}

/**
 * Find the 'growthagents' user, or create it with the Editor role.
 *
 * @return int|WP_Error
 */
function growthagents_connector_get_or_create_user() {
	$existing = get_user_by( 'login', 'growthagents' );
	if ( $existing ) {
		return (int) $existing->ID;
	}

	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! $host ) {
		$host = 'invalid.example';
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => 'growthagents',
			'user_pass'    => wp_generate_password( 32, true, true ),
			'user_email'   => 'growthagents-connector@' . $host,
			'display_name' => 'GrowthAgents',
			'role'         => 'editor',
		)
	);

	return $user_id; // int on success, WP_Error on failure — wp_insert_user's own contract.
}

/**
 * ---------------------------------------------------------------------------
 * Disconnect: remove the stored token and user id. The WP user itself is
 * left alone — deleting it would orphan every post it published.
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_post_growthagents_connector_disconnect', 'growthagents_connector_handle_disconnect' );

function growthagents_connector_handle_disconnect() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'growthagents-connector' ) );
	}
	check_admin_referer( 'growthagents_connector_disconnect' );

	delete_option( GROWTHAGENTS_CONNECTOR_TOKEN_OPTION );
	delete_option( GROWTHAGENTS_CONNECTOR_USER_ID_OPTION );

	growthagents_connector_set_notice( 'success', __( 'Disconnected.', 'growthagents-connector' ) );
	wp_safe_redirect( growthagents_connector_settings_url() );
	exit;
}

/**
 * ---------------------------------------------------------------------------
 * Auth: let a request carrying our bearer token authenticate as the stored
 * growthagents user, for core /wp/v2/* — no custom REST namespace is
 * registered or needed. Proven on intgr8.me 2026-09-17.
 * ---------------------------------------------------------------------------
 */

add_filter( 'determine_current_user', 'growthagents_connector_authenticate', 20 );

function growthagents_connector_authenticate( $user_id ) {
	// Core (cookie auth, an earlier filter, etc.) already resolved a user —
	// never override that.
	if ( $user_id ) {
		return $user_id;
	}

	$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] )
		? $_SERVER['HTTP_AUTHORIZATION']
		: ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : '' );

	if ( ! $auth_header || 0 !== stripos( $auth_header, 'Bearer ' ) ) {
		return $user_id;
	}

	$presented_token = trim( substr( $auth_header, 7 ) );
	if ( '' === $presented_token ) {
		return $user_id;
	}

	$stored_token   = get_option( GROWTHAGENTS_CONNECTOR_TOKEN_OPTION );
	$stored_user_id = get_option( GROWTHAGENTS_CONNECTOR_USER_ID_OPTION );

	if ( ! $stored_token || ! $stored_user_id ) {
		return $user_id;
	}

	if ( ! hash_equals( (string) $stored_token, $presented_token ) ) {
		return $user_id;
	}

	return (int) $stored_user_id;
}
