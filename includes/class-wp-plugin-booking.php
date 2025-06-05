<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Plugin_Booking {

    public function __construct() {
        add_action( 'init', array( $this, 'register_service_cpt' ) );
        add_action( 'init', array( $this, 'register_service_meta' ) );
        add_shortcode( 'booking_catalog', array( $this, 'booking_catalog_shortcode' ) );
        add_filter( 'template_include', array( $this, 'catalog_template' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_price_meta_box' ) );
        add_action( 'save_post_wpb_service', array( $this, 'save_price_meta' ) );
    }

    public function register_service_cpt() {
        register_post_type( 'wpb_service', array(
            'label' => __( 'Servicio', 'wp-plugin-booking' ),
            'public' => true,
            'show_in_menu' => true,
            'supports' => array( 'title', 'editor', 'thumbnail' ),
            'rewrite' => array( 'slug' => 'servicio' ),
        ) );

        register_taxonomy( 'wpb_service_category', 'wpb_service', array(
            'label' => __( 'Categorías de Servicio', 'wp-plugin-booking' ),
            'hierarchical' => true,
            'rewrite' => array( 'slug' => 'categoria-servicio' ),
        ) );
    }

    public function register_service_meta() {
        register_post_meta( 'wpb_service', '_wpb_price_per_person', array(
            'type'              => 'number',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => array( $this, 'sanitize_price_meta' ),
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ) );
    }

    public function sanitize_price_meta( $value, $meta_key = '', $object_type = '' ) {
        return floatval( $value );
    }

    public function add_price_meta_box() {
        add_meta_box(
            'wpb_service_price',
            __( 'Precio por Persona', 'wp-plugin-booking' ),
            array( $this, 'render_price_meta_box' ),
            'wpb_service',
            'side'
        );
    }

    public function render_price_meta_box( $post ) {
        $value = get_post_meta( $post->ID, '_wpb_price_per_person', true );
        echo '<label for="wpb_price_per_person">' . esc_html__( 'Precio', 'wp-plugin-booking' ) . '</label>';
        echo '<input type="number" step="0.01" name="wpb_price_per_person" id="wpb_price_per_person" value="' . esc_attr( $value ) . '" style="width:100%;" />';
    }

    public function save_price_meta( $post_id ) {
        if ( isset( $_POST['wpb_price_per_person'] ) ) {
            update_post_meta( $post_id, '_wpb_price_per_person', floatval( $_POST['wpb_price_per_person'] ) );
        }
    }

    public function booking_catalog_shortcode() {
        wp_enqueue_style( 'wpb-catalog', WP_PLUGIN_BOOKING_URL . 'assets/css/catalog.css', array(), WP_PLUGIN_BOOKING_VERSION );
        $query = new WP_Query( array(
            'post_type'      => 'wpb_service',
            'posts_per_page' => -1,
        ) );
        ob_start();
        echo '<div class="wpb-catalog">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $price = get_post_meta( get_the_ID(), '_wpb_price_per_person', true );
            echo '<div class="wpb-service">';
            echo '<h2>' . esc_html( get_the_title() ) . '</h2>';
            if ( $price ) {
                echo '<div class="wpb-price">' . esc_html( wc_price( $price ) ) . '</div>';
            }
            echo '<div class="wpb-content">' . apply_filters( 'the_content', get_the_content() ) . '</div>';
            echo '</div>';
        }
        wp_reset_postdata();
        echo '</div>';
        return ob_get_clean();
    }

    public function catalog_template( $template ) {
        if ( is_page( get_option( 'wp_booking_catalog_page_id' ) ) ) {
            return WP_PLUGIN_BOOKING_PATH . 'templates/catalog-template.php';
        }
        return $template;
    }

}
