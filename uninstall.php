<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$page_id = get_option( 'wp_booking_catalog_page_id' );
if ( $page_id ) {
    wp_delete_post( $page_id, true );
}
delete_option( 'wp_booking_catalog_page_id' );

