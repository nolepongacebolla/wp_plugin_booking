<?php
/*
Plugin Name: WP Plugin Booking
Description: Estructura básica para un plugin de WooCommerce.
Version: 1.0.0
Author: Tu Nombre
License: GPL2
Text Domain: wp-plugin-booking
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'WP_PLUGIN_BOOKING_VERSION', '1.0.0' );
define( 'WP_PLUGIN_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_PLUGIN_BOOKING_URL', plugin_dir_url( __FILE__ ) );
/**
 * Activación del plugin.
 */
function wp_plugin_booking_activate() {
    // Código de activación.
}
register_activation_hook( __FILE__, 'wp_plugin_booking_activate' );
/**
 * Desactivación del plugin.
 */
function wp_plugin_booking_deactivate() {
    // Código de desactivación.
}
register_deactivation_hook( __FILE__, 'wp_plugin_booking_deactivate' );
/**
 * Inicializar el plugin.
 */
function wp_plugin_booking_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wp_plugin_booking_missing_wc' );
        return;
    }

    require_once WP_PLUGIN_BOOKING_PATH . 'includes/class-wp-plugin-booking.php';
    new WP_Plugin_Booking();
}
add_action( 'plugins_loaded', 'wp_plugin_booking_init' );
/**
 * Notificación si WooCommerce no está activo.
 */
function wp_plugin_booking_missing_wc() {
    echo '<div class="error"><p>' . esc_html__( 'Se requiere WooCommerce para usar WP Plugin Booking.', 'wp-plugin-booking' ) . '</p></div>';
}
?>
