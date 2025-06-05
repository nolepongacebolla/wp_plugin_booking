<?php
/*
Plugin Name: WP Plugin Booking
Description: Sistema de reservas integrado con WooCommerce.
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

function wp_plugin_booking_activate() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( 'Se requiere WooCommerce para activar este plugin.', 'wp-plugin-booking' ) );
    }
    $page_id = get_option( 'wp_booking_catalog_page_id' );
    if ( ! $page_id || ! get_post( $page_id ) ) {
        $page_id = wp_insert_post( array(
            'post_title'   => 'Catálogo de Reservas',
            'post_name'    => 'booking-catalog',
            'post_content' => '[booking_catalog]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
        update_option( 'wp_booking_catalog_page_id', $page_id );
    }
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wp_plugin_booking_activate' );

function wp_plugin_booking_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wp_plugin_booking_deactivate' );

function wp_plugin_booking_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wp_plugin_booking_missing_wc' );
        return;
    }

    require_once WP_PLUGIN_BOOKING_PATH . 'includes/class-wp-plugin-booking.php';
    new WP_Plugin_Booking();
}
add_action( 'plugins_loaded', 'wp_plugin_booking_init' );

function wp_plugin_booking_missing_wc() {
    echo '<div class="error"><p>' . esc_html__( 'Se requiere WooCommerce para usar WP Plugin Booking.', 'wp-plugin-booking' ) . '</p></div>';
}
