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
        add_action( 'add_meta_boxes', array( $this, 'add_service_meta_boxes' ) );
        add_action( 'save_post_wpb_service', array( $this, 'save_service_meta' ) );
        add_action( 'wp_ajax_wpb_create_booking', array( $this, 'handle_create_booking' ) );
        add_action( 'wp_ajax_nopriv_wpb_create_booking', array( $this, 'handle_create_booking' ) );
        add_filter( 'manage_wpb_booking_posts_columns', array( $this, 'booking_columns' ) );
        add_action( 'manage_wpb_booking_posts_custom_column', array( $this, 'render_booking_columns' ), 10, 2 );

        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'add_meta_boxes', array( $this, 'add_booking_meta_box' ) );
            add_action( 'save_post_wpb_booking', array( $this, 'save_booking_meta' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        }
    }

    public function register_service_cpt() {
        register_post_type( 'wpb_service', array(
            'label'       => __( 'Servicio', 'wp-plugin-booking' ),
            'public'      => true,
            'show_in_menu'=> 'wpbookingstandar',
            'supports'    => array( 'title', 'editor', 'thumbnail' ),
            'rewrite'     => array( 'slug' => 'servicio' ),
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

        register_post_meta( 'wpb_service', '_wpb_gallery', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ) );

        register_post_meta( 'wpb_service', '_wpb_start_date', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ) );

        register_post_meta( 'wpb_service', '_wpb_terms', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'wp_kses_post',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ) );

        register_post_meta( 'wpb_service', '_wpb_includes', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'wp_kses_post',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ) );

        register_post_meta( 'wpb_service', '_wpb_video_url', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ) );

        register_post_meta( 'wpb_service', '_wpb_discount_percent', array(
            'type'              => 'number',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => array( $this, 'sanitize_float_meta' ),
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ) );

        register_post_meta( 'wpb_service', '_wpb_discount_min', array(
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
            'show_in_menu'=> 'wpbookingstandar',
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

    /**
     * Generic float sanitizer accepting additional arguments.
     */
    public function sanitize_float_meta( $value, $meta_key = '', $object_type = '', $object_subtype = '' ) {
        return floatval( $value );
    }

    public function add_service_meta_boxes() {
        add_meta_box(
            'wpb_price_meta',
            __( 'Precio por Persona', 'wp-plugin-booking' ),
            array( $this, 'render_price_meta_box' ),
            'wpb_service',
            'side'
        );

        add_meta_box(
            'wpb_capacity_meta',
            __( 'Capacidad Máxima', 'wp-plugin-booking' ),
            array( $this, 'render_capacity_meta_box' ),
            'wpb_service',
            'side'
        );

        add_meta_box(
            'wpb_gallery_meta',
            __( 'Galería de Imágenes', 'wp-plugin-booking' ),
            array( $this, 'render_gallery_meta_box' ),
            'wpb_service'
        );

        add_meta_box(
            'wpb_start_date_meta',
            __( 'Fecha de Inicio', 'wp-plugin-booking' ),
            array( $this, 'render_start_date_meta_box' ),
            'wpb_service',
            'side'
        );

        add_meta_box(
            'wpb_includes_meta',
            __( 'Incluye', 'wp-plugin-booking' ),
            array( $this, 'render_includes_meta_box' ),
            'wpb_service'
        );

        add_meta_box(
            'wpb_terms_meta',
            __( 'Términos y Condiciones', 'wp-plugin-booking' ),
            array( $this, 'render_terms_meta_box' ),
            'wpb_service'
        );

        add_meta_box(
            'wpb_video_meta',
            __( 'Enlace de Video', 'wp-plugin-booking' ),
            array( $this, 'render_video_meta_box' ),
            'wpb_service'
        );

        add_meta_box(
            'wpb_discount_meta',
            __( 'Descuento', 'wp-plugin-booking' ),
            array( $this, 'render_discount_meta_box' ),
            'wpb_service',
            'side'
        );
    }

    public function render_price_meta_box( $post ) {
        $value = get_post_meta( $post->ID, '_wpb_price_per_person', true );
        echo '<input type="number" step="0.01" name="wpb_price_per_person" id="wpb_price_per_person" value="' . esc_attr( $value ) . '" style="width:100%;" />';
    }

    public function render_capacity_meta_box( $post ) {
        $cap = get_post_meta( $post->ID, '_wpb_capacity', true );
        echo '<input type="number" min="1" name="wpb_capacity" id="wpb_capacity" value="' . esc_attr( $cap ) . '" style="width:100%;" />';
    }

    public function render_gallery_meta_box( $post ) {
        $gallery = get_post_meta( $post->ID, '_wpb_gallery', true );
        echo '<div id="wpb_gallery_preview">';
        if ( $gallery ) {
            $ids = array_filter( array_map( 'absint', explode( ',', $gallery ) ) );
            foreach ( $ids as $img_id ) {
                echo wp_get_attachment_image( $img_id, 'thumbnail', false, array( 'style' => 'margin-right:5px;' ) );
            }
        }
        echo '</div>';
        echo '<input type="hidden" name="wpb_gallery" id="wpb_gallery" value="' . esc_attr( $gallery ) . '" />';
        echo '<button type="button" class="button" id="wpb_gallery_button">' . esc_html__( 'Seleccionar imágenes', 'wp-plugin-booking' ) . '</button>';
    }

    public function render_video_meta_box( $post ) {
        $video = get_post_meta( $post->ID, '_wpb_video_url', true );
        echo '<input type="url" name="wpb_video_url" id="wpb_video_url" value="' . esc_attr( $video ) . '" style="width:100%;" />';
    }

    public function render_discount_meta_box( $post ) {
        $disc = get_post_meta( $post->ID, '_wpb_discount_percent', true );
        $min  = get_post_meta( $post->ID, '_wpb_discount_min', true );
        echo '<p><label>' . esc_html__( 'Descuento (%)', 'wp-plugin-booking' ) . '<br />';
        echo '<input type="number" step="0.1" name="wpb_discount_percent" value="' . esc_attr( $disc ) . '" style="width:100%;" /></label></p>';
        echo '<p><label>' . esc_html__( 'Personas mínimas para descuento', 'wp-plugin-booking' ) . '<br />';
        echo '<input type="number" name="wpb_discount_min" value="' . esc_attr( $min ) . '" style="width:100%;" /></label></p>';
    }

    public function render_start_date_meta_box( $post ) {
        $date = get_post_meta( $post->ID, '_wpb_start_date', true );
        echo '<input type="date" name="wpb_start_date" value="' . esc_attr( $date ) . '" style="width:100%;" />';
    }

    public function render_includes_meta_box( $post ) {
        $inc = get_post_meta( $post->ID, '_wpb_includes', true );
        echo '<textarea name="wpb_includes" style="width:100%;height:80px;">' . esc_textarea( $inc ) . '</textarea>';
    }

    public function render_terms_meta_box( $post ) {
        $terms = get_post_meta( $post->ID, '_wpb_terms', true );
        echo '<textarea name="wpb_terms" style="width:100%;height:120px;">' . esc_textarea( $terms ) . '</textarea>';
    }

    public function save_service_meta( $post_id ) {
        if ( isset( $_POST['wpb_price_per_person'] ) ) {
            update_post_meta( $post_id, '_wpb_price_per_person', floatval( $_POST['wpb_price_per_person'] ) );
        }
        if ( isset( $_POST['wpb_capacity'] ) ) {
            update_post_meta( $post_id, '_wpb_capacity', absint( $_POST['wpb_capacity'] ) );
        }
        if ( isset( $_POST['wpb_gallery'] ) ) {
            update_post_meta( $post_id, '_wpb_gallery', sanitize_text_field( $_POST['wpb_gallery'] ) );
        }
        if ( isset( $_POST['wpb_video_url'] ) ) {
            update_post_meta( $post_id, '_wpb_video_url', esc_url_raw( $_POST['wpb_video_url'] ) );
        }
        if ( isset( $_POST['wpb_discount_percent'] ) ) {
            update_post_meta( $post_id, '_wpb_discount_percent', floatval( $_POST['wpb_discount_percent'] ) );
        }
        if ( isset( $_POST['wpb_discount_min'] ) ) {
            update_post_meta( $post_id, '_wpb_discount_min', absint( $_POST['wpb_discount_min'] ) );
        }
        if ( isset( $_POST['wpb_start_date'] ) ) {
            update_post_meta( $post_id, '_wpb_start_date', sanitize_text_field( $_POST['wpb_start_date'] ) );
        }
        if ( isset( $_POST['wpb_terms'] ) ) {
            update_post_meta( $post_id, '_wpb_terms', wp_kses_post( $_POST['wpb_terms'] ) );
        }
        if ( isset( $_POST['wpb_includes'] ) ) {
            update_post_meta( $post_id, '_wpb_includes', wp_kses_post( $_POST['wpb_includes'] ) );
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
        $phone      = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
        $id_card    = isset( $_POST['id_card'] ) ? sanitize_text_field( $_POST['id_card'] ) : '';
        $persons    = isset( $_POST['persons'] ) ? absint( $_POST['persons'] ) : 1;
        $payment    = isset( $_POST['payment'] ) ? sanitize_text_field( $_POST['payment'] ) : '';

        if ( ! $service_id || ! $name || ! $email || ! $phone || ! $payment ) {
            wp_send_json_error();
        }

        $remaining = $this->get_remaining_capacity( $service_id );
        if ( $remaining < $persons ) {
            wp_send_json_error( array( 'message' => __( 'No hay cupos suficientes', 'wp-plugin-booking' ) ) );
        }

        $price    = floatval( get_post_meta( $service_id, '_wpb_price_per_person', true ) );
        $discount = floatval( get_post_meta( $service_id, '_wpb_discount_percent', true ) );
        $min_disc = absint( get_post_meta( $service_id, '_wpb_discount_min', true ) );
        $total    = $price * $persons;
        if ( $discount && $persons >= $min_disc ) {
            $total = $total * ( 1 - $discount / 100 );
        }

        $booking_id = wp_insert_post(
            array(
                'post_type'   => 'wpb_booking',
                'post_title'  => $name,
                'post_status' => 'publish',
            ),
            true
        );

        if ( ! is_wp_error( $booking_id ) && $booking_id ) {
            update_post_meta( $booking_id, '_wpb_service_id', $service_id );
            update_post_meta( $booking_id, '_wpb_customer_name', $name );
            update_post_meta( $booking_id, '_wpb_customer_email', $email );
            update_post_meta( $booking_id, '_wpb_customer_phone', $phone );
            update_post_meta( $booking_id, '_wpb_customer_id_card', $id_card );
            update_post_meta( $booking_id, '_wpb_persons', $persons );
            update_post_meta( $booking_id, '_wpb_total_price', $total );
            update_post_meta( $booking_id, '_wpb_status', 'pendiente' );
            update_post_meta( $booking_id, '_wpb_payment_method', $payment );
            update_post_meta( $booking_id, '_wpb_booking_uid', uniqid( 'resv_' ) );
            $this->send_status_email( $booking_id, 'pendiente' );
            wp_send_json_success();
        }

        $message = is_wp_error( $booking_id ) ? $booking_id->get_error_message() : __( 'Error al procesar la reserva', 'wp-plugin-booking' );
        wp_send_json_error( array( 'message' => $message ) );
    }

    /**
     * Send an email to the customer when the booking status changes.
     *
     * @param int    $booking_id Booking post ID.
     * @param string $status     New status.
     */
    public function send_status_email( $booking_id, $status ) {
        $email   = get_post_meta( $booking_id, '_wpb_customer_email', true );
        if ( ! $email ) {
            return;
        }
        $name        = get_post_meta( $booking_id, '_wpb_customer_name', true );
        $service_id  = get_post_meta( $booking_id, '_wpb_service_id', true );
        $service     = $service_id ? get_the_title( $service_id ) : '';
        $subject = sprintf( __( 'Estado de tu reserva: %s', 'wp-plugin-booking' ), ucfirst( $status ) );
        $total   = get_post_meta( $booking_id, '_wpb_total_price', true );
        $template = get_option( 'wpb_email_template', '' );
        if ( $template ) {
            $replacements = array(
                '{name}'    => $name,
                '{service}' => $service,
                '{status}'  => $status,
                '{total}'   => $total,
            );
            $message = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
        } else {
            $message = sprintf( __( "Hola %s,\n\nTu reserva para %s ahora está %s.", 'wp-plugin-booking' ), $name, $service, $status );
        }
        wp_mail( $email, $subject, $message );
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
        echo '<div class="floating-elements"><div class="floating-circle circle1"></div><div class="floating-circle circle2"></div><div class="floating-circle circle3"></div></div>';
        echo '<div class="container my-5">';
        echo '<header class="hero-header py-5 text-center">';
        echo '<div class="hero-content">';
        $hero_title    = get_option( 'wpb_front_title', '🌴 Paraíso Turístico' );
        $hero_subtitle = get_option( 'wpb_front_subtitle', 'Experiencias inolvidables te esperan' );
        echo '<h1 class="hero-title mb-3">' . esc_html( $hero_title ) . '</h1>';
        echo '<p class="hero-subtitle mb-4">' . esc_html( $hero_subtitle ) . '</p>';
        echo '</div></header>';
        echo '<div class="d-flex justify-content-between align-items-center mb-4 wpb-catalog-search">';
        echo '<form class="row g-2" method="get">';
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
        echo '<button type="submit" class="btn btn-danger">' . esc_html__( 'Filtrar', 'wp-plugin-booking' ) . '</button>';
        echo '</div>';
        echo '</form>';
        echo '<div class="text-end mt-3"><a href="' . esc_url( home_url() ) . '" class="btn btn-outline-dark">' . esc_html__( 'Inicio', 'wp-plugin-booking' ) . '</a></div>';
        echo '</div>';

        echo '<div class="row wpb-catalog">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $price     = get_post_meta( get_the_ID(), '_wpb_price_per_person', true );
            $id        = get_the_ID();
            $remaining = $this->get_remaining_capacity( $id );
            $cats      = get_the_terms( $id, 'wpb_service_category' );
           $gallery   = get_post_meta( $id, '_wpb_gallery', true );
           $video     = get_post_meta( $id, '_wpb_video_url', true );
            $start     = get_post_meta( $id, '_wpb_start_date', true );
            $includes  = get_post_meta( $id, '_wpb_includes', true );
            $terms_txt = get_post_meta( $id, '_wpb_terms', true );
            $discount  = floatval( get_post_meta( $id, '_wpb_discount_percent', true ) );
            $disc_min  = absint( get_post_meta( $id, '_wpb_discount_min', true ) );
            echo '<div class="col-md-6 col-lg-4 mb-4 wpb-service">';
            echo '<div class="card service-card rounded-4 h-100">';
            echo get_the_post_thumbnail( $id, 'medium', array( 'class' => 'card-img-top' ) );
            echo '<div class="card-body d-flex flex-column">';
            if ( $cats && ! is_wp_error( $cats ) ) {
                $first = $cats[0];
                echo '<span class="badge bg-secondary mb-2">' . esc_html( $first->name ) . '</span>';
            }
            echo '<h5 class="card-title">' . esc_html( get_the_title() ) . '</h5>';
            if ( $price ) {
                $price_html = function_exists( 'wc_price' )
                    ? wc_price( $price, array( 'currency' => 'DOP' ) )
                    : number_format_i18n( $price, 2 ) . ' DOP';
                echo '<p class="wpb-price mb-1">' . wp_kses_post( $price_html ) . '</p>';
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
            echo '<div class="modal-dialog modal-dialog-centered modal-lg">';
            echo '<div class="modal-content">';
            echo '<div class="modal-header">';
            echo '<h5 class="modal-title">' . esc_html__( 'Reserva', 'wp-plugin-booking' ) . '</h5>';
            echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . esc_attr__( 'Cerrar', 'wp-plugin-booking' ) . '"></button>';
            echo '</div>';
            echo '<div class="modal-body">';
            echo '<form class="wpb-booking-form" data-price="' . esc_attr( $price ) . '" data-discount="' . esc_attr( $discount ) . '" data-discountmin="' . esc_attr( $disc_min ) . '">';
            echo '<input type="hidden" name="action" value="wpb_create_booking" />';
            echo '<input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( 'wpb_booking_nonce' ) ) . '" />';
            echo '<input type="hidden" name="service_id" value="' . esc_attr( $id ) . '" />';

            echo '<div class="wpb-step">';
            echo '<h4 class="wpb-modal-service-title mb-3">' . esc_html( get_the_title() ) . '</h4>';
            if ( $start ) {
                echo '<p class="mb-2"><strong>' . esc_html__( 'Fecha de inicio:', 'wp-plugin-booking' ) . '</strong> ' . esc_html( $start ) . '</p>';
            }
            if ( $gallery ) {
                $ids = array_filter( array_map( 'absint', explode( ',', $gallery ) ) );
                foreach ( $ids as $img_id ) {
                    $thumb = wp_get_attachment_image_src( $img_id, 'medium' );
                    $full  = wp_get_attachment_image_src( $img_id, 'large' );
                    if ( $thumb ) {
                        $full_url  = $full ? $full[0] : $thumb[0];
                        echo '<img src="' . esc_url( $thumb[0] ) . '" data-full="' . esc_url( $full_url ) . '" class="wpb-gallery-thumb wpb-expand-image" />';
                    }
                }
            }
            if ( $video ) {
                $embed = wp_oembed_get( esc_url( $video ) );
                if ( $embed ) {
                    echo '<div class="ratio ratio-16x9 mb-3">' . $embed . '</div>';
                }
            }
            echo apply_filters( 'the_content', get_the_content() );
            if ( $includes ) {
                echo '<div class="mb-3 wpb-includes">' . wpautop( wp_kses_post( $includes ) ) . '</div>';
            }
            if ( $terms_txt ) {
                echo '<details class="wpb-terms mb-3"><summary>' . esc_html__( 'Términos y condiciones', 'wp-plugin-booking' ) . '</summary>' . wpautop( wp_kses_post( $terms_txt ) ) . '</details>';
            }
            echo '<button class="btn btn-danger wpb-next mt-3">' . esc_html__( 'Siguiente', 'wp-plugin-booking' ) . '</button>';
            echo '</div>';

            echo '<div class="wpb-step">';
            echo '<div class="mb-3">';
            echo '<label class="form-label">' . esc_html__( 'Nombre', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="text" class="form-control" name="name" required />';
            echo '</div>';
            echo '<div class="mb-3">';
            echo '<label class="form-label">' . esc_html__( 'Cédula', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="text" class="form-control" name="id_card" />';
            echo '</div>';
            echo '<div class="mb-3">';
            echo '<label class="form-label">' . esc_html__( 'Teléfono', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="text" class="form-control" name="phone" required />';
            echo '</div>';
            echo '<div class="mb-3">';
            echo '<label class="form-label">' . esc_html__( 'Email', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="email" class="form-control" name="email" required />';
            echo '</div>';
            echo '<button class="btn btn-secondary wpb-prev me-2">' . esc_html__( 'Atrás', 'wp-plugin-booking' ) . '</button>';
            echo '<button class="btn btn-danger wpb-next">' . esc_html__( 'Siguiente', 'wp-plugin-booking' ) . '</button>';
            echo '</div>';

            echo '<div class="wpb-step">';
            echo '<div class="mb-3">';
            echo '<label class="form-label">' . esc_html__( 'Personas', 'wp-plugin-booking' ) . '</label>';
            $max = min( 10, $remaining );
            echo '<select name="persons" class="form-select" required>';
            for ( $i = 1; $i <= $max; $i++ ) {
                echo '<option value="' . $i . '">' . $i . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '<button class="btn btn-secondary wpb-prev me-2">' . esc_html__( 'Atrás', 'wp-plugin-booking' ) . '</button>';
            echo '<button class="btn btn-danger wpb-next">' . esc_html__( 'Siguiente', 'wp-plugin-booking' ) . '</button>';
            echo '</div>';

            echo '<div class="wpb-step">';
            echo '<div class="mb-3">';
            echo '<label class="form-label">' . esc_html__( 'Método de Pago', 'wp-plugin-booking' ) . '</label>';
            $methods = get_option( 'wpb_payment_methods', 'transferencia' );
            $methods = array_map( 'trim', explode( ',', $methods ) );
            foreach ( $methods as $index => $method ) {
                $mid     = sanitize_title( $method ) . '-' . $index . '-' . $id;
                $checked = 0 === $index ? ' checked' : '';
                echo '<div class="form-check">';
                echo '<input class="form-check-input" type="radio" name="payment" value="' . esc_attr( $method ) . '" id="' . esc_attr( $mid ) . '"' . $checked . ' />';
                echo '<label class="form-check-label" for="' . esc_attr( $mid ) . '">' . esc_html( $method ) . '</label>';
                echo '</div>';
            }
            echo '</div>';
            echo '<button class="btn btn-secondary wpb-prev me-2">' . esc_html__( 'Atrás', 'wp-plugin-booking' ) . '</button>';
            echo '<button class="btn btn-danger wpb-next">' . esc_html__( 'Siguiente', 'wp-plugin-booking' ) . '</button>';
            echo '</div>';

            echo '<div class="wpb-step wpb-summary-step">';
            echo '<p><strong>' . esc_html__( 'Servicio:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-service"></span></p>';
            echo '<p><strong>' . esc_html__( 'Fecha de inicio:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-date"></span></p>';
            echo '<p><strong>' . esc_html__( 'Nombre:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-name"></span></p>';
            echo '<p><strong>' . esc_html__( 'Email:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-email"></span></p>';
            echo '<p><strong>' . esc_html__( 'Personas:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-persons"></span></p>';
            echo '<p><strong>' . esc_html__( 'Total:', 'wp-plugin-booking' ) . '</strong> RD$ <span class="wpb-summary-total"></span></p>';
            echo '<div class="wpb-error text-danger mb-2"></div>';
            echo '<button class="btn btn-secondary wpb-prev me-2">' . esc_html__( 'Atrás', 'wp-plugin-booking' ) . '</button>';
            echo '<button type="submit" class="btn btn-danger wpb-confirm">' . esc_html__( 'Confirmar Reserva', 'wp-plugin-booking' ) . '</button>';
            echo '<div class="wpb-processing mt-3"><div class="spinner-border text-danger" role="status"><span class="visually-hidden">' . esc_html__( 'Procesando...', 'wp-plugin-booking' ) . '</span></div><span class="ms-2">' . esc_html__( 'Procesando, por favor espere...', 'wp-plugin-booking' ) . '</span></div>';
            echo '</div>';

            echo '<div class="wpb-step wpb-success">';
            echo '<p class="text-success fw-bold">' . esc_html__( '¡Reserva realizada con éxito!', 'wp-plugin-booking' ) . '</p>';
            echo '</div>';

            echo '</form>';
            echo '</div>';
            echo '</div></div></div>';
            echo '</div>';
        }
        wp_reset_postdata();
        echo '</div>';
        $premium_title = get_option( 'wpb_premium_title', '✨ Servicios Premium ✨' );
        $premium_text  = get_option( 'wpb_premium_text', '¿Buscas algo completamente personalizado? Nuestro equipo diseña experiencias únicas para ti.' );
        $phone  = get_option( 'wpb_contact_phone', '+1 (555) 123-4567' );
        $email  = get_option( 'wpb_contact_email', 'info@paraisoturistico.com' );
        $url    = get_option( 'wpb_contact_url', 'https://www.paraisoturistico.com' );
        echo '<div class="premium-banner p-5 text-center">';
        echo '<h2 class="premium-title mb-3">' . esc_html( $premium_title ) . '</h2>';
        echo '<p class="premium-text mb-4">' . esc_html( $premium_text ) . '</p>';
        echo '<div class="row justify-content-center g-3">';
        echo '<div class="col-md-4"><div class="contact-item"><i class="fas fa-phone"></i><span>' . esc_html( $phone ) . '</span></div></div>';
        echo '<div class="col-md-4"><div class="contact-item"><i class="fas fa-envelope"></i><span>' . esc_html( $email ) . '</span></div></div>';
        echo '<div class="col-md-4"><div class="contact-item"><i class="fas fa-globe"></i><span>' . esc_html( $url ) . '</span></div></div>';
        echo '</div></div>';
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
        $columns['payment'] = __( 'Pago', 'wp-plugin-booking' );
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
                    $price_html = function_exists( 'wc_price' )
                        ? wc_price( $total, array( 'currency' => 'DOP' ) )
                        : number_format_i18n( $total, 2 ) . ' DOP';
                    echo wp_kses_post( $price_html );
                }
                break;
            case 'payment':
                echo esc_html( get_post_meta( $post_id, '_wpb_payment_method', true ) );
                break;
            case 'status':
                echo esc_html( get_post_meta( $post_id, '_wpb_status', true ) );
                break;
            case 'uid':
                echo esc_html( get_post_meta( $post_id, '_wpb_booking_uid', true ) );
                break;
        }
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting( 'wpb_settings_group', 'wpb_payment_methods', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'transferencia',
        ) );

        register_setting( 'wpb_email_group', 'wpb_email_template', array(
            'sanitize_callback' => array( $this, 'sanitize_email_template' ),
            'default'           => '',
        ) );

        register_setting( 'wpb_frontpage_group', 'wpb_front_title', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '🌴 Paraíso Turístico',
        ) );

        register_setting( 'wpb_frontpage_group', 'wpb_front_subtitle', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Experiencias inolvidables te esperan',
        ) );

        register_setting( 'wpb_frontpage_group', 'wpb_premium_title', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '✨ Servicios Premium ✨',
        ) );

        register_setting( 'wpb_frontpage_group', 'wpb_premium_text', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '¿Buscas algo completamente personalizado? Nuestro equipo diseña experiencias únicas para ti.',
        ) );

        register_setting( 'wpb_frontpage_group', 'wpb_contact_phone', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '+1 (555) 123-4567',
        ) );

        register_setting( 'wpb_frontpage_group', 'wpb_contact_email', array(
            'sanitize_callback' => 'sanitize_email',
            'default'           => 'info@paraisoturistico.com',
        ) );

        register_setting( 'wpb_frontpage_group', 'wpb_contact_url', array(
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'https://www.paraisoturistico.com',
        ) );

        add_settings_section( 'wpb_main', __( 'Ajustes Generales', 'wp-plugin-booking' ), null, 'wpb-settings' );
        add_settings_section( 'wpb_email', __( 'Plantilla de Correo', 'wp-plugin-booking' ), null, 'wpb-email' );
        add_settings_section( 'wpb_frontpage', __( 'Textos de Portada', 'wp-plugin-booking' ), null, 'wpb-frontpage' );

        add_settings_field(
            'wpb_payment_methods',
            __( 'Métodos de pago (separados por coma)', 'wp-plugin-booking' ),
            array( $this, 'payment_methods_field' ),
            'wpb-settings',
            'wpb_main'
        );

        add_settings_field(
            'wpb_email_template',
            __( 'Plantilla de correo', 'wp-plugin-booking' ),
            array( $this, 'email_template_field' ),
            'wpb-email',
            'wpb_email'
        );

        add_settings_field(
            'wpb_front_title',
            __( 'Título principal', 'wp-plugin-booking' ),
            array( $this, 'front_title_field' ),
            'wpb-frontpage',
            'wpb_frontpage'
        );

        add_settings_field(
            'wpb_front_subtitle',
            __( 'Subtítulo', 'wp-plugin-booking' ),
            array( $this, 'front_subtitle_field' ),
            'wpb-frontpage',
            'wpb_frontpage'
        );

        add_settings_field(
            'wpb_premium_title',
            __( 'Título sección premium', 'wp-plugin-booking' ),
            array( $this, 'premium_title_field' ),
            'wpb-frontpage',
            'wpb_frontpage'
        );

        add_settings_field(
            'wpb_premium_text',
            __( 'Texto sección premium', 'wp-plugin-booking' ),
            array( $this, 'premium_text_field' ),
            'wpb-frontpage',
            'wpb_frontpage'
        );

        add_settings_field(
            'wpb_contact_phone',
            __( 'Teléfono', 'wp-plugin-booking' ),
            array( $this, 'contact_phone_field' ),
            'wpb-frontpage',
            'wpb_frontpage'
        );

        add_settings_field(
            'wpb_contact_email',
            __( 'Correo', 'wp-plugin-booking' ),
            array( $this, 'contact_email_field' ),
            'wpb-frontpage',
            'wpb_frontpage'
        );

        add_settings_field(
            'wpb_contact_url',
            __( 'URL Web', 'wp-plugin-booking' ),
            array( $this, 'contact_url_field' ),
            'wpb-frontpage',
            'wpb_frontpage'
        );
    }

    /**
     * Render payment methods field.
     */
    public function payment_methods_field() {
        $value = get_option( 'wpb_payment_methods', 'transferencia' );
        echo '<input type="text" name="wpb_payment_methods" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function email_template_field() {
        $value = get_option( 'wpb_email_template', '' );
        echo '<textarea name="wpb_email_template" rows="10" cols="50" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Usa {name}, {service}, {status} y {total} para insertar datos de la reserva.', 'wp-plugin-booking' ) . '</p>';
    }

    public function sanitize_email_template( $html ) {
        return wp_kses_post( $html );
    }

    public function front_title_field() {
        $value = get_option( 'wpb_front_title', '🌴 Paraíso Turístico' );
        echo '<input type="text" name="wpb_front_title" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function front_subtitle_field() {
        $value = get_option( 'wpb_front_subtitle', 'Experiencias inolvidables te esperan' );
        echo '<input type="text" name="wpb_front_subtitle" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function premium_title_field() {
        $value = get_option( 'wpb_premium_title', '✨ Servicios Premium ✨' );
        echo '<input type="text" name="wpb_premium_title" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function premium_text_field() {
        $value = get_option( 'wpb_premium_text', '¿Buscas algo completamente personalizado? Nuestro equipo diseña experiencias únicas para ti.' );
        echo '<textarea name="wpb_premium_text" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';
    }

    public function contact_phone_field() {
        $value = get_option( 'wpb_contact_phone', '+1 (555) 123-4567' );
        echo '<input type="text" name="wpb_contact_phone" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function contact_email_field() {
        $value = get_option( 'wpb_contact_email', 'info@paraisoturistico.com' );
        echo '<input type="email" name="wpb_contact_email" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function contact_url_field() {
        $value = get_option( 'wpb_contact_url', 'https://www.paraisoturistico.com' );
        echo '<input type="url" name="wpb_contact_url" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    /**
     * Output settings page markup.
     */
    public function render_settings_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Ajustes de Booking', 'wp-plugin-booking' ) . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="?page=wpb-settings&tab=general" class="nav-tab' . ( 'general' === $tab ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Generales', 'wp-plugin-booking' ) . '</a>';
        echo '<a href="?page=wpb-settings&tab=email" class="nav-tab' . ( 'email' === $tab ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Plantilla de Correo', 'wp-plugin-booking' ) . '</a>';
        echo '<a href="?page=wpb-settings&tab=frontpage" class="nav-tab' . ( 'frontpage' === $tab ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'FrontPage', 'wp-plugin-booking' ) . '</a>';
        echo '</h2>';
        echo '<form method="post" action="options.php">';
        if ( 'email' === $tab ) {
            settings_fields( 'wpb_email_group' );
            do_settings_sections( 'wpb-email' );
        } elseif ( 'frontpage' === $tab ) {
            settings_fields( 'wpb_frontpage_group' );
            do_settings_sections( 'wpb-frontpage' );
        } else {
            settings_fields( 'wpb_settings_group' );
            do_settings_sections( 'wpb-settings' );
        }
        submit_button();
        echo '</form></div>';
    }

    /**
     * Add meta box to edit bookings.
     */
    public function add_booking_meta_box() {
        add_meta_box(
            'wpb_booking_details',
            __( 'Detalles de la Reserva', 'wp-plugin-booking' ),
            array( $this, 'render_booking_meta_box' ),
            'wpb_booking',
            'normal',
            'default'
        );
    }

    /**
     * Render booking meta box fields.
     */
    public function render_booking_meta_box( $post ) {
        wp_nonce_field( 'wpb_save_booking_meta', 'wpb_booking_nonce' );
        $service_id = get_post_meta( $post->ID, '_wpb_service_id', true );
        $name       = get_post_meta( $post->ID, '_wpb_customer_name', true );
        $email      = get_post_meta( $post->ID, '_wpb_customer_email', true );
        $phone      = get_post_meta( $post->ID, '_wpb_customer_phone', true );
        $id_card    = get_post_meta( $post->ID, '_wpb_customer_id_card', true );
        $persons    = get_post_meta( $post->ID, '_wpb_persons', true );
        $total      = get_post_meta( $post->ID, '_wpb_total_price', true );
        $payment    = get_post_meta( $post->ID, '_wpb_payment_method', true );
        $status     = get_post_meta( $post->ID, '_wpb_status', true );

        echo '<p><label>' . esc_html__( 'Servicio', 'wp-plugin-booking' ) . '</label><br />';
        wp_dropdown_pages( array(
            'post_type'        => 'wpb_service',
            'selected'         => $service_id,
            'name'            => 'wpb_service_id',
            'show_option_none' => __( 'Seleccionar', 'wp-plugin-booking' ),
        ) );
        echo '</p>';

        echo '<p><label>' . esc_html__( 'Nombre', 'wp-plugin-booking' ) . '</label><br />';
        echo '<input type="text" name="wpb_customer_name" value="' . esc_attr( $name ) . '" class="regular-text" /></p>';

        echo '<p><label>' . esc_html__( 'Email', 'wp-plugin-booking' ) . '</label><br />';
        echo '<input type="email" name="wpb_customer_email" value="' . esc_attr( $email ) . '" class="regular-text" /></p>';

        echo '<p><label>' . esc_html__( 'Teléfono', 'wp-plugin-booking' ) . '</label><br />';
        echo '<input type="text" name="wpb_customer_phone" value="' . esc_attr( $phone ) . '" class="regular-text" /></p>';

        echo '<p><label>' . esc_html__( 'Cédula', 'wp-plugin-booking' ) . '</label><br />';
        echo '<input type="text" name="wpb_customer_id_card" value="' . esc_attr( $id_card ) . '" class="regular-text" /></p>';

        echo '<p><label>' . esc_html__( 'Personas', 'wp-plugin-booking' ) . '</label><br />';
        echo '<input type="number" name="wpb_persons" value="' . esc_attr( $persons ) . '" /></p>';

        echo '<p><label>' . esc_html__( 'Total', 'wp-plugin-booking' ) . '</label><br />';
        echo '<input type="number" step="0.01" name="wpb_total_price" value="' . esc_attr( $total ) . '" /></p>';

        $methods = get_option( 'wpb_payment_methods', 'transferencia' );
        $methods = array_map( 'trim', explode( ',', $methods ) );
        echo '<p><label>' . esc_html__( 'Pago', 'wp-plugin-booking' ) . '</label><br />';
        echo '<select name="wpb_payment_method">';
        foreach ( $methods as $method ) {
            echo '<option value="' . esc_attr( $method ) . '"' . selected( $payment, $method, false ) . '>' . esc_html( $method ) . '</option>';
        }
        echo '</select></p>';

        $statuses = array( 'pendiente', 'confirmado', 'cancelado' );
        echo '<p><label>' . esc_html__( 'Estatus', 'wp-plugin-booking' ) . '</label><br />';
        echo '<select name="wpb_status">';
        foreach ( $statuses as $st ) {
            echo '<option value="' . esc_attr( $st ) . '"' . selected( $status, $st, false ) . '>' . esc_html( ucfirst( $st ) ) . '</option>';
        }
        echo '</select></p>';
    }

    /**
     * Save booking meta when editing in admin.
     */
    public function save_booking_meta( $post_id ) {
        if ( ! isset( $_POST['wpb_booking_nonce'] ) || ! wp_verify_nonce( $_POST['wpb_booking_nonce'], 'wpb_save_booking_meta' ) ) {
            return;
        }

        if ( isset( $_POST['wpb_service_id'] ) ) {
            update_post_meta( $post_id, '_wpb_service_id', absint( $_POST['wpb_service_id'] ) );
        }
        if ( isset( $_POST['wpb_customer_name'] ) ) {
            update_post_meta( $post_id, '_wpb_customer_name', sanitize_text_field( $_POST['wpb_customer_name'] ) );
        }
        if ( isset( $_POST['wpb_customer_email'] ) ) {
            update_post_meta( $post_id, '_wpb_customer_email', sanitize_email( $_POST['wpb_customer_email'] ) );
        }
        if ( isset( $_POST['wpb_customer_phone'] ) ) {
            update_post_meta( $post_id, '_wpb_customer_phone', sanitize_text_field( $_POST['wpb_customer_phone'] ) );
        }
        if ( isset( $_POST['wpb_customer_id_card'] ) ) {
            update_post_meta( $post_id, '_wpb_customer_id_card', sanitize_text_field( $_POST['wpb_customer_id_card'] ) );
        }
        if ( isset( $_POST['wpb_persons'] ) ) {
            update_post_meta( $post_id, '_wpb_persons', absint( $_POST['wpb_persons'] ) );
        }
        if ( isset( $_POST['wpb_total_price'] ) ) {
            update_post_meta( $post_id, '_wpb_total_price', floatval( $_POST['wpb_total_price'] ) );
        }
        if ( isset( $_POST['wpb_payment_method'] ) ) {
            update_post_meta( $post_id, '_wpb_payment_method', sanitize_text_field( $_POST['wpb_payment_method'] ) );
        }
        if ( isset( $_POST['wpb_status'] ) ) {
            $old_status = get_post_meta( $post_id, '_wpb_status', true );
            $new_status = sanitize_text_field( $_POST['wpb_status'] );
            update_post_meta( $post_id, '_wpb_status', $new_status );
            if ( $old_status !== $new_status ) {
                $this->send_status_email( $post_id, $new_status );
            }
        }
    }

    /**
     * Register top level admin menu and settings submenu.
     */
    public function register_admin_menu() {
        add_menu_page(
            __( 'WPBookingStandar', 'wp-plugin-booking' ),
            'WPBookingStandar',
            'manage_options',
            'wpbookingstandar',
            array( $this, 'menu_redirect' ),
            'dashicons-calendar-alt'
        );

        add_submenu_page(
            'wpbookingstandar',
            __( 'Ajustes', 'wp-plugin-booking' ),
            __( 'Ajustes', 'wp-plugin-booking' ),
            'manage_options',
            'wpb-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'wpbookingstandar',
            __( 'Estadisticas', 'wp-plugin-booking' ),
            __( 'Estadisticas', 'wp-plugin-booking' ),
            'manage_options',
            'wpb-stats',
            array( $this, 'render_stats_page' )
        );
    }

    /**
     * Display simple booking statistics.
     */
    public function render_stats_page() {
        $bookings = get_posts( array(
            'post_type'   => 'wpb_booking',
            'numberposts' => -1,
        ) );

        $total       = 0;
        $status_totals = array();
        foreach ( $bookings as $booking ) {
            $price  = floatval( get_post_meta( $booking->ID, '_wpb_total_price', true ) );
            $status = get_post_meta( $booking->ID, '_wpb_status', true );
            $total += $price;
            if ( ! isset( $status_totals[ $status ] ) ) {
                $status_totals[ $status ] = 0;
            }
            $status_totals[ $status ]++;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Estadisticas', 'wp-plugin-booking' ) . '</h1>';
        echo '<p>' . sprintf( esc_html__( 'Reservas totales: %d', 'wp-plugin-booking' ), count( $bookings ) ) . '</p>';
        $price_html = function_exists( 'wc_price' )
            ? wc_price( $total, array( 'currency' => 'DOP' ) )
            : number_format_i18n( $total, 2 ) . ' DOP';
        echo '<p>' . sprintf( esc_html__( 'Ganancias totales: %s', 'wp-plugin-booking' ), $price_html ) . '</p>';
        echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Estatus', 'wp-plugin-booking' ) . '</th><th>' . esc_html__( 'Cantidad', 'wp-plugin-booking' ) . '</th></tr></thead><tbody>';
        foreach ( $status_totals as $st => $count ) {
            echo '<tr><td>' . esc_html( ucfirst( $st ) ) . '</td><td>' . esc_html( $count ) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Enqueue scripts for media selection on service edit screens.
     */
    public function admin_enqueue_scripts( $hook ) {
        if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            $screen = get_current_screen();
            if ( $screen && 'wpb_service' === $screen->post_type ) {
                wp_enqueue_media();
                wp_enqueue_script(
                    'wpb-admin',
                    WP_PLUGIN_BOOKING_URL . 'assets/js/admin.js',
                    array( 'jquery' ),
                    WP_PLUGIN_BOOKING_VERSION,
                    true
                );
                wp_localize_script(
                    'wpb-admin',
                    'wpbGallery',
                    array(
                        'select' => __( 'Seleccionar imágenes', 'wp-plugin-booking' ),
                        'use'    => __( 'Usar imágenes', 'wp-plugin-booking' ),
                    )
                );
            }
        }
    }

    /**
     * Redirect top level menu to services list.
     */
    public function menu_redirect() {
        wp_safe_redirect( admin_url( 'edit.php?post_type=wpb_service' ) );
        exit;
    }
}
