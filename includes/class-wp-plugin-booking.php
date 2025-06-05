<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Plugin_Booking {

    public function __construct() {
        add_action( 'init', array( $this, 'register_service_cpt' ) );
        add_action( 'init', array( $this, 'register_service_meta' ) );
        add_action( 'init', array( $this, 'register_booking_cpt' ) );
        add_shortcode( 'booking_catalog', array( $this, 'booking_catalog_shortcode' ) );
        add_filter( 'template_include', array( $this, 'catalog_template' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_price_meta_box' ) );
        add_action( 'save_post_wpb_service', array( $this, 'save_price_meta' ) );
        add_action( 'wp_ajax_wpb_create_booking', array( $this, 'handle_create_booking' ) );
        add_action( 'wp_ajax_nopriv_wpb_create_booking', array( $this, 'handle_create_booking' ) );
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

    public function register_booking_cpt() {
        register_post_type( 'wpb_booking', array(
            'label'       => __( 'Reserva', 'wp-plugin-booking' ),
            'public'      => false,
            'show_ui'     => true,
            'show_in_menu'=> true,
            'supports'    => array( 'title' ),
        ) );
    }

    /**
     * Sanitize the price meta value.
     *
     * WordPress passes up to four arguments to the sanitize callback when using
     * register_post_meta(), so we allow additional parameters.
     *
     * @param mixed  $value         Meta value to sanitize.
     * @param string $meta_key      Meta key.
     * @param string $object_type   Object type.
     * @param string $object_subtype Optional subtype such as post type.
     *
     * @return float Sanitized value as float.
     */
    public function sanitize_price_meta( $value, $meta_key = '', $object_type = '', $object_subtype = '' ) {
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

    public function handle_create_booking() {
        check_ajax_referer( 'wpb_booking_nonce', 'nonce' );

        $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
        $name       = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $email      = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $persons    = isset( $_POST['persons'] ) ? absint( $_POST['persons'] ) : 1;

        if ( ! $service_id || ! $name || ! $email ) {
            wp_send_json_error();
        }

        $booking_id = wp_insert_post( array(
            'post_type'   => 'wpb_booking',
            'post_title'  => $name,
            'post_status' => 'publish',
        ) );

        if ( $booking_id ) {
            update_post_meta( $booking_id, '_wpb_service_id', $service_id );
            update_post_meta( $booking_id, '_wpb_customer_email', $email );
            update_post_meta( $booking_id, '_wpb_persons', $persons );
            wp_send_json_success();
        }

        wp_send_json_error();
    }

    public function booking_catalog_shortcode() {
        wp_enqueue_style( 'wpb-catalog', WP_PLUGIN_BOOKING_URL . 'assets/css/catalog.css', array(), WP_PLUGIN_BOOKING_VERSION );
        wp_enqueue_script( 'wpb-catalog', WP_PLUGIN_BOOKING_URL . 'assets/js/catalog.js', array( 'jquery' ), WP_PLUGIN_BOOKING_VERSION, true );
        wp_localize_script( 'wpb-catalog', 'wpbCatalog', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpb_booking_nonce' ),
        ) );

        $query = new WP_Query( array(
            'post_type'      => 'wpb_service',
            'posts_per_page' => -1,
        ) );

        ob_start();
        echo '<div class="wpb-catalog">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $price = get_post_meta( get_the_ID(), '_wpb_price_per_person', true );
            $id    = get_the_ID();
            echo '<div class="wpb-service">';
            echo '<div class="wpb-thumbnail">' . get_the_post_thumbnail( $id, 'medium' ) . '</div>';
            echo '<h2>' . esc_html( get_the_title() ) . '</h2>';
            if ( $price ) {
                echo '<div class="wpb-price">' . esc_html( wc_price( $price, array( 'currency' => 'DOP' ) ) ) . '</div>';
            }
            echo '<button class="wpb-book-button" data-service-id="' . esc_attr( $id ) . '">' . esc_html__( 'Reservar', 'wp-plugin-booking' ) . '</button>';
            echo '<div class="wpb-modal" id="wpb-modal-' . esc_attr( $id ) . '">';
            echo '<div class="wpb-modal-content">';
            echo '<span class="wpb-modal-close">&times;</span>';
            echo '<form class="wpb-booking-form">';
            echo '<input type="hidden" name="action" value="wpb_create_booking" />';
            echo '<input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( 'wpb_booking_nonce' ) ) . '" />';
            echo '<input type="hidden" name="service_id" value="' . esc_attr( $id ) . '" />';
            echo '<label>' . esc_html__( 'Nombre', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="text" name="name" required />';
            echo '<label>' . esc_html__( 'Email', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="email" name="email" required />';
            echo '<label>' . esc_html__( 'Personas', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="number" name="persons" value="1" min="1" required />';
            echo '<button type="submit">' . esc_html__( 'Enviar Reserva', 'wp-plugin-booking' ) . '</button>';
            echo '</form>';
            echo '</div></div>';
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
