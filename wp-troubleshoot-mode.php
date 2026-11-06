<?php
/**
 * Plugin Name: WP Troubleshoot Mode
 * Plugin URI:  https://github.com/Azanumer/wp-troubleshoot-mode
 * Description: One-click safe mode for diagnosing plugin conflicts — loads WordPress with every plugin disabled except this one, for your browser only. No more renaming plugin folders over FTP.
 * Version:     1.0.0
 * Author:      Azan Umer
 * Author URI:  https://azanumer.cv
 * License:     MIT
 * Text Domain: wtsm
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WTSM_VERSION', '1.0.0' );
define( 'WTSM_COOKIE', 'wtsm_safe' );
define( 'WTSM_COOKIE_TTL', 2 * HOUR_IN_SECONDS );

/**
 * Secret used to sign the safe-mode cookie. NONCE_KEY is defined in
 * wp-config.php and is available before plugins load, so the signature can
 * be verified even during the early option_active_plugins filter.
 *
 * @return string
 */
function wtsm_signing_secret() {
    if ( defined( 'NONCE_KEY' ) && NONCE_KEY ) {
        return NONCE_KEY . '|wtsm';
    }
    if ( function_exists( 'wp_salt' ) ) {
        return wp_salt( 'secure_auth' ) . '|wtsm';
    }
    return 'wtsm-fallback';
}

/**
 * Build a signed safe-mode token for a user ID.
 *
 * @param int $user_id User ID.
 * @return string user_id|hmac
 */
function wtsm_make_token( $user_id ) {
    $user_id = (int) $user_id;
    return $user_id . '|' . hash_hmac( 'sha256', (string) $user_id, wtsm_signing_secret() );
}

/**
 * Check whether the current request is in safe mode.
 *
 * Verifies the signed cookie — no user functions are needed, so this is
 * safe to call during plugin loading.
 *
 * @return bool
 */
function wtsm_is_safe_mode() {
    if ( empty( $_COOKIE[ WTSM_COOKIE ] ) ) {
        return false;
    }
    $parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE[ WTSM_COOKIE ] ) ), 2 );
    if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
        return false;
    }
    return hash_equals( wtsm_make_token( (int) $parts[0] ), $parts[0] . '|' . $parts[1] );
}

/**
 * When safe mode is on, load only this plugin.
 *
 * @param string[] $plugins Active plugin basenames.
 * @return string[]
 */
function wtsm_filter_active_plugins( $plugins ) {
    if ( ! wtsm_is_safe_mode() ) {
        return $plugins;
    }
    $allowed = apply_filters( 'wtsm_safe_mode_plugins', array( plugin_basename( __FILE__ ) ) );
    return array_values( array_intersect( (array) $plugins, (array) $allowed ) );
}
add_filter( 'option_active_plugins', 'wtsm_filter_active_plugins' );

/**
 * Handle ?wtsm_safe=1 / ?wtsm_safe=0 toggle links (admin only, nonce-checked).
 */
function wtsm_handle_toggle() {
    if ( ! isset( $_GET['wtsm_safe'] ) || ! isset( $_GET['_wpnonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'wtsm_toggle' ) ) {
        wp_die( esc_html__( 'Security check failed.', 'wtsm' ) );
    }
    if ( ! current_user_can( 'activate_plugins' ) ) {
        wp_die( esc_html__( 'You do not have permission to toggle Troubleshoot Mode.', 'wtsm' ) );
    }

    $enable = '1' === $_GET['wtsm_safe'];
    if ( $enable ) {
        setcookie( WTSM_COOKIE, wtsm_make_token( get_current_user_id() ), time() + WTSM_COOKIE_TTL, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        $_COOKIE[ WTSM_COOKIE ] = wtsm_make_token( get_current_user_id() );
    } else {
        setcookie( WTSM_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        unset( $_COOKIE[ WTSM_COOKIE ] );
    }

    $redirect = remove_query_arg( array( 'wtsm_safe', '_wpnonce' ) );
    wp_safe_redirect( $redirect ? $redirect : admin_url() );
    exit;
}
add_action( 'init', 'wtsm_handle_toggle' );

/**
 * Admin bar toggle so safe mode is one click away anywhere.
 *
 * @param WP_Admin_Bar $admin_bar Admin bar object.
 */
function wtsm_admin_bar_toggle( $admin_bar ) {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    $safe = wtsm_is_safe_mode();
    $url  = add_query_arg(
        array(
            'wtsm_safe' => $safe ? '0' : '1',
            '_wpnonce'  => wp_create_nonce( 'wtsm_toggle' ),
        ),
        admin_url( 'tools.php?page=wtsm-troubleshoot' )
    );
    $admin_bar->add_node(
        array(
            'id'    => 'wtsm-toggle',
            'title' => $safe ? __( 'Safe Mode: ON — click to exit', 'wtsm' ) : __( 'Safe Mode: OFF — click to enable', 'wtsm' ),
            'href'  => $url,
            'meta'  => array(
                'class' => $safe ? 'wtsm-on' : 'wtsm-off',
            ),
        )
    );
}
add_action( 'admin_bar_menu', 'wtsm_admin_bar_toggle', 100 );

/**
 * Register the Tools page.
 */
function wtsm_register_menu() {
    add_management_page(
        __( 'Troubleshoot Mode', 'wtsm' ),
        __( 'Troubleshoot Mode', 'wtsm' ),
        'activate_plugins',
        'wtsm-troubleshoot',
        'wtsm_render_page'
    );
}
add_action( 'admin_menu', 'wtsm_register_menu' );

/**
 * Render the Tools page.
 */
function wtsm_render_page() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        wp_die( esc_html__( 'You do not have permission to use this page.', 'wtsm' ) );
    }
    $safe      = wtsm_is_safe_mode();
    $toggle_url = add_query_arg(
        array(
            'wtsm_safe' => $safe ? '0' : '1',
            '_wpnonce'  => wp_create_nonce( 'wtsm_toggle' ),
        ),
        admin_url( 'tools.php?page=wtsm-troubleshoot' )
    );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Troubleshoot Mode', 'wtsm' ); ?></h1>
        <?php if ( $safe ) : ?>
            <div class="notice notice-warning"><p><strong><?php esc_html_e( 'Safe mode is ON for your browser.', 'wtsm' ); ?></strong> <?php esc_html_e( 'All plugins except this one are disabled for you only — other visitors still see the normal site. It turns off automatically after 2 hours, or when you exit below.', 'wtsm' ); ?></p></div>
        <?php else : ?>
            <div class="notice notice-info"><p><?php esc_html_e( 'Safe mode is OFF. Your site is running with all active plugins.', 'wtsm' ); ?></p></div>
        <?php endif; ?>
        <p>
            <a class="button <?php echo $safe ? 'button-secondary' : 'button-primary'; ?>" href="<?php echo esc_url( $toggle_url ); ?>">
                <?php echo $safe ? esc_html__( 'Exit safe mode', 'wtsm' ) : esc_html__( 'Enable safe mode', 'wtsm' ); ?>
            </a>
        </p>
        <h2><?php esc_html_e( 'How to find a conflicting plugin', 'wtsm' ); ?></h2>
        <ol>
            <li><?php esc_html_e( 'Enable safe mode and confirm the problem is gone — that proves a plugin (not the theme or core) is the cause.', 'wtsm' ); ?></li>
            <li><?php esc_html_e( 'Exit safe mode, then re-enable plugins one at a time from the Plugins screen until the problem returns.', 'wtsm' ); ?></li>
            <li><?php esc_html_e( 'The last plugin you enabled is the culprit — update, replace, or report it.', 'wtsm' ); ?></li>
        </ol>
        <p><em><?php esc_html_e( 'Developers: the wtsm_safe_mode_plugins filter lets you keep extra plugins (e.g. a debugging plugin) active during safe mode.', 'wtsm' ); ?></em></p>
    </div>
    <?php
}
