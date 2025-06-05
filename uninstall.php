<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$page_id = get_option( 'wp_booking_catalog_page_id' );
if ( $page_id ) {
    wp_delete_post( $page_id, true );
}
delete_option( 'wp_booking_catalog_page_id' );

$services = get_posts( array(
    'post_type'   => 'wpb_service',
    'numberposts' => -1,
) );
foreach ( $services as $service ) {
    wp_delete_post( $service->ID, true );
}

$bookings = get_posts( array(
    'post_type'   => 'wpb_booking',
    'numberposts' => -1,
) );
foreach ( $bookings as $booking ) {
    wp_delete_post( $booking->ID, true );
}
