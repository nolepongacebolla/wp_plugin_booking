<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Plugin_Booking {

    public function __construct() {
        add_action( 'init', array( $this, 'register_custom_post_type' ) );
    }

    public function register_custom_post_type() {
        register_post_type( 'booking', array(
            'label' => __( 'Reserva', 'wp-plugin-booking' ),
            'public' => true,
            'show_in_menu' => true,
            'supports' => array( 'title', 'editor' ),
        ) );
    }
}
