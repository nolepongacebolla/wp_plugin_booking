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
        add_action( 'add_meta_boxes', array( $this, 'add_service_meta_box' ) );
        add_action( 'save_post_wpb_service', array( $this, 'save_service_meta' ) );
        add_action( 'wp_ajax_wpb_create_booking', array( $this, 'handle_create_booking' ) );
        add_action( 'wp_ajax_nopriv_wpb_create_booking', array( $this, 'handle_create_booking' ) );
        add_filter( 'manage_wpb_booking_posts_columns', array( $this, 'booking_columns' ) );
        add_action( 'manage_wpb_booking_posts_custom_column', array( $this, 'render_booking_columns' ), 10, 2 );
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
        register_post_meta( 'wpb_service', '_wpb_capacity', array(
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'absint',
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

    public function add_service_meta_box() {
        add_meta_box(
            'wpb_service_price',
            __( 'Precio por Persona', 'wp-plugin-booking' ),
            array( $this, 'render_service_meta_box' ),
            'wpb_service',
            'side'
        );
    }

    public function render_service_meta_box( $post ) {
        $value = get_post_meta( $post->ID, '_wpb_price_per_person', true );
        echo '<label for="wpb_price_per_person">' . esc_html__( 'Precio', 'wp-plugin-booking' ) . '</label>';
        echo '<input type="number" step="0.01" name="wpb_price_per_person" id="wpb_price_per_person" value="' . esc_attr( $value ) . '" style="width:100%;" />';
        $cap = get_post_meta( $post->ID, '_wpb_capacity', true );
        echo '<label for="wpb_capacity" style="margin-top:10px;display:block;">' . esc_html__( 'Capacidad', 'wp-plugin-booking' ) . '</label>';
        echo '<input type="number" min="1" name="wpb_capacity" id="wpb_capacity" value="' . esc_attr( $cap ) . '" style="width:100%;" />';
    }

    public function save_service_meta( $post_id ) {
        if ( isset( $_POST['wpb_price_per_person'] ) ) {
            update_post_meta( $post_id, '_wpb_price_per_person', floatval( $_POST['wpb_price_per_person'] ) );
        }
        if ( isset( $_POST['wpb_capacity'] ) ) {
            update_post_meta( $post_id, '_wpb_capacity', absint( $_POST['wpb_capacity'] ) );
        }
    }

    public function get_remaining_capacity( $service_id ) {
        $capacity = absint( get_post_meta( $service_id, '_wpb_capacity', true ) );
        $bookings = get_posts( array(
            'post_type'  => 'wpb_booking',
            'numberposts'=> -1,
            'meta_query' => array(
                array(
                    'key'   => '_wpb_service_id',
                    'value' => $service_id,
                ),
            ),
        ) );
        $used = 0;
        foreach ( $bookings as $booking ) {
            $used += absint( get_post_meta( $booking->ID, '_wpb_persons', true ) );
        }
        $remaining = $capacity - $used;
        return max( 0, $remaining );
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

        $remaining = $this->get_remaining_capacity( $service_id );
        if ( $remaining < $persons ) {
            wp_send_json_error( array( 'message' => __( 'No hay cupos suficientes', 'wp-plugin-booking' ) ) );
        }

        $price = floatval( get_post_meta( $service_id, '_wpb_price_per_person', true ) );
        $total = $price * $persons;

        $booking_id = wp_insert_post( array(
            'post_type'   => 'wpb_booking',
            'post_title'  => $name,
            'post_status' => 'publish',
        ) );

        if ( $booking_id ) {
            update_post_meta( $booking_id, '_wpb_service_id', $service_id );
            update_post_meta( $booking_id, '_wpb_customer_name', $name );
            update_post_meta( $booking_id, '_wpb_customer_email', $email );
            update_post_meta( $booking_id, '_wpb_persons', $persons );
            update_post_meta( $booking_id, '_wpb_total_price', $total );
            update_post_meta( $booking_id, '_wpb_status', 'pendiente' );
            update_post_meta( $booking_id, '_wpb_booking_uid', uniqid( 'resv_' ) );
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

        $args = array(
            'post_type'      => 'wpb_service',
            'posts_per_page' => -1,
            's'              => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '',
        );

        if ( ! empty( $_GET['category'] ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'wpb_service_category',
                    'field'    => 'term_id',
                    'terms'    => absint( $_GET['category'] ),
                ),
            );
        }

        $query = new WP_Query( $args );

        ob_start();
        echo '<div class="container my-4">';
        echo '<div class="d-flex justify-content-between align-items-center mb-4 wpb-catalog-search">';
        echo '<a href="' . esc_url( home_url() ) . '" class="btn btn-dark">' . esc_html__( 'Inicio', 'wp-plugin-booking' ) . '</a>';
        echo '<form class="row g-2" method="get">';
        echo '<div class="col">';
        echo '<input type="text" class="form-control" name="s" value="' . esc_attr( isset( $_GET['s'] ) ? $_GET['s'] : '' ) . '" placeholder="' . esc_attr__( 'Buscar servicio', 'wp-plugin-booking' ) . '" />';
        echo '</div>';
        $terms = get_terms( array( 'taxonomy' => 'wpb_service_category', 'hide_empty' => false ) );
        echo '<div class="col">';
        echo '<select name="category" class="form-select"><option value="">' . esc_html__( 'Todas las categorías', 'wp-plugin-booking' ) . '</option>';
        foreach ( $terms as $term ) {
            $selected = selected( isset( $_GET['category'] ) ? absint( $_GET['category'] ) : '', $term->term_id, false );
            echo '<option value="' . esc_attr( $term->term_id ) . '" ' . $selected . '>' . esc_html( $term->name ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '<div class="col-auto">';
        echo '<button type="submit" class="btn btn-danger">' . esc_html__( 'Buscar', 'wp-plugin-booking' ) . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<div class="row wpb-catalog">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $price     = get_post_meta( get_the_ID(), '_wpb_price_per_person', true );
            $id        = get_the_ID();
            $remaining = $this->get_remaining_capacity( $id );
            echo '<div class="col-md-4 mb-4 wpb-service">';
            echo '<div class="card h-100">';
            echo get_the_post_thumbnail( $id, 'medium', array( 'class' => 'card-img-top' ) );
            echo '<div class="card-body d-flex flex-column">';
            echo '<h5 class="card-title">' . esc_html( get_the_title() ) . '</h5>';
            if ( $price ) {
                echo '<p class="wpb-price mb-1">' . wp_kses_post( wc_price( $price, array( 'currency' => 'DOP' ) ) ) . '</p>';
            }
            if ( $remaining > 0 ) {
                echo '<p class="wpb-remaining">' . sprintf( esc_html__( 'Cupos: %d', 'wp-plugin-booking' ), $remaining ) . '</p>';
                echo '<button class="btn btn-danger mt-auto wpb-book-button" data-bs-toggle="modal" data-bs-target="#wpb-modal-' . esc_attr( $id ) . '" data-service-id="' . esc_attr( $id ) . '">' . esc_html__( 'Reservar', 'wp-plugin-booking' ) . '</button>';
            } else {
                echo '<span class="badge bg-danger wpb-soldout">' . esc_html__( 'AGOTADO', 'wp-plugin-booking' ) . '</span>';
            }
            echo '</div>'; // card-body
            echo '</div>'; // card
            echo '<div class="modal fade" id="wpb-modal-' . esc_attr( $id ) . '" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">';
            echo '<div class="modal-dialog modal-dialog-centered">';
            echo '<div class="modal-content">';
            echo '<div class="modal-header">';
            echo '<h5 class="modal-title">' . esc_html__( 'Reserva', 'wp-plugin-booking' ) . '</h5>';
            echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . esc_attr__( 'Cerrar', 'wp-plugin-booking' ) . '"></button>';
            echo '</div>';
            echo '<div class="modal-body">';
            echo '<form class="wpb-booking-form">';
            echo '<input type="hidden" name="action" value="wpb_create_booking" />';
            echo '<input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( 'wpb_booking_nonce' ) ) . '" />';
            echo '<input type="hidden" name="service_id" value="' . esc_attr( $id ) . '" />';
            echo '<div class="mb-3">';
            echo '<label class="form-label">' . esc_html__( 'Nombre', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="text" class="form-control" name="name" required />';
            echo '</div>';
            echo '<div class="mb-3">';
            echo '<label class="form-label">' . esc_html__( 'Email', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="email" class="form-control" name="email" required />';
            echo '</div>';
            echo '<div class="mb-3">';
            echo '<label class="form-label">' . esc_html__( 'Personas', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="number" class="form-control" name="persons" value="1" min="1" required />';
            echo '</div>';
            echo '<button type="submit" class="btn btn-danger">' . esc_html__( 'Enviar Reserva', 'wp-plugin-booking' ) . '</button>';
            echo '</form>';
            echo '</div>';
            echo '</div></div></div>';
            echo '</div>';
        }
        wp_reset_postdata();
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    public function catalog_template( $template ) {
        if ( is_page( get_option( 'wp_booking_catalog_page_id' ) ) ) {
            return WP_PLUGIN_BOOKING_PATH . 'templates/catalog-template.php';
        }
        return $template;
    }

    public function booking_columns( $columns ) {
        $columns['service'] = __( 'Servicio', 'wp-plugin-booking' );
        $columns['persons'] = __( 'Cantidad', 'wp-plugin-booking' );
        $columns['total']   = __( 'Precio Total', 'wp-plugin-booking' );
        $columns['status']  = __( 'Estatus', 'wp-plugin-booking' );
        $columns['uid']     = __( 'ID Único', 'wp-plugin-booking' );
        return $columns;
    }

    public function render_booking_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'service':
                $service_id = get_post_meta( $post_id, '_wpb_service_id', true );
                if ( $service_id ) {
                    echo esc_html( get_the_title( $service_id ) );
                }
                break;
            case 'persons':
                echo absint( get_post_meta( $post_id, '_wpb_persons', true ) );
                break;
            case 'total':
                $total = get_post_meta( $post_id, '_wpb_total_price', true );
                if ( $total ) {
                    echo wp_kses_post( wc_price( $total, array( 'currency' => 'DOP' ) ) );
                }
                break;
            case 'status':
                echo esc_html( get_post_meta( $post_id, '_wpb_status', true ) );
                break;
            case 'uid':
                echo esc_html( get_post_meta( $post_id, '_wpb_booking_uid', true ) );
                break;
        }
    }
}
