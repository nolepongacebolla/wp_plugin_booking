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

        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'add_meta_boxes', array( $this, 'add_booking_meta_box' ) );
            add_action( 'save_post_wpb_booking', array( $this, 'save_booking_meta' ) );
        }
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
        $payment    = isset( $_POST['payment'] ) ? sanitize_text_field( $_POST['payment'] ) : '';

        if ( ! $service_id || ! $name || ! $email || ! $payment ) {
            wp_send_json_error();
        }

        $remaining = $this->get_remaining_capacity( $service_id );
        if ( $remaining < $persons ) {
            wp_send_json_error( array( 'message' => __( 'No hay cupos suficientes', 'wp-plugin-booking' ) ) );
        }

        $price = floatval( get_post_meta( $service_id, '_wpb_price_per_person', true ) );
        $total = $price * $persons;

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
            update_post_meta( $booking_id, '_wpb_persons', $persons );
            update_post_meta( $booking_id, '_wpb_total_price', $total );
            update_post_meta( $booking_id, '_wpb_status', 'pendiente' );
            update_post_meta( $booking_id, '_wpb_payment_method', $payment );
            update_post_meta( $booking_id, '_wpb_booking_uid', uniqid( 'resv_' ) );
            wp_send_json_success();
        }

        $message = is_wp_error( $booking_id ) ? $booking_id->get_error_message() : __( 'Error al procesar la reserva', 'wp-plugin-booking' );
        wp_send_json_error( array( 'message' => $message ) );
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
            $cats      = get_the_terms( $id, 'wpb_service_category' );
            $excerpt   = get_the_excerpt();
            echo '<div class="col-md-4 mb-4 wpb-service">';
            echo '<div class="card h-100">';
            echo get_the_post_thumbnail( $id, 'medium', array( 'class' => 'card-img-top' ) );
            echo '<div class="card-body d-flex flex-column">';
            if ( $cats && ! is_wp_error( $cats ) ) {
                $first = $cats[0];
                echo '<span class="badge bg-secondary mb-2">' . esc_html( $first->name ) . '</span>';
            }
            echo '<h5 class="card-title">' . esc_html( get_the_title() ) . '</h5>';
            if ( $excerpt ) {
                echo '<p class="card-text">' . esc_html( wp_trim_words( $excerpt, 15 ) ) . '</p>';
            }
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
            echo '<div class="modal-dialog modal-dialog-centered">';
            echo '<div class="modal-content">';
            echo '<div class="modal-header">';
            echo '<h5 class="modal-title">' . esc_html__( 'Reserva', 'wp-plugin-booking' ) . '</h5>';
            echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . esc_attr__( 'Cerrar', 'wp-plugin-booking' ) . '"></button>';
            echo '</div>';
            echo '<div class="modal-body">';
            echo '<form class="wpb-booking-form" data-price="' . esc_attr( $price ) . '">';
            echo '<input type="hidden" name="action" value="wpb_create_booking" />';
            echo '<input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( 'wpb_booking_nonce' ) ) . '" />';
            echo '<input type="hidden" name="service_id" value="' . esc_attr( $id ) . '" />';

            echo '<div class="wpb-step">';
            echo apply_filters( 'the_content', get_the_content() );
            echo '<button class="btn btn-danger wpb-next mt-3">' . esc_html__( 'Siguiente', 'wp-plugin-booking' ) . '</button>';
            echo '</div>';

            echo '<div class="wpb-step">';
            echo '<div class="mb-3">';
            echo '<label class="form-label">' . esc_html__( 'Nombre', 'wp-plugin-booking' ) . '</label>';
            echo '<input type="text" class="form-control" name="name" required />';
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
            echo '<input type="number" class="form-control" name="persons" value="1" min="1" max="' . esc_attr( $remaining ) . '" required />';
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
            echo '<p><strong>' . esc_html__( 'Nombre:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-name"></span></p>';
            echo '<p><strong>' . esc_html__( 'Email:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-email"></span></p>';
            echo '<p><strong>' . esc_html__( 'Personas:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-persons"></span></p>';
            echo '<p><strong>' . esc_html__( 'Total:', 'wp-plugin-booking' ) . '</strong> RD$ <span class="wpb-summary-total"></span></p>';
            echo '<div class="wpb-error text-danger mb-2"></div>';
            echo '<button class="btn btn-secondary wpb-prev me-2">' . esc_html__( 'Atrás', 'wp-plugin-booking' ) . '</button>';
            echo '<button type="submit" class="btn btn-danger">' . esc_html__( 'Confirmar Reserva', 'wp-plugin-booking' ) . '</button>';
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

        add_settings_section( 'wpb_main', __( 'Ajustes Generales', 'wp-plugin-booking' ), null, 'wpb-settings' );

        add_settings_field(
            'wpb_payment_methods',
            __( 'Métodos de pago (separados por coma)', 'wp-plugin-booking' ),
            array( $this, 'payment_methods_field' ),
            'wpb-settings',
            'wpb_main'
        );
    }

    /**
     * Render payment methods field.
     */
    public function payment_methods_field() {
        $value = get_option( 'wpb_payment_methods', 'transferencia' );
        echo '<input type="text" name="wpb_payment_methods" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    /**
     * Add settings page under Settings menu.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Ajustes de Booking', 'wp-plugin-booking' ),
            __( 'Booking', 'wp-plugin-booking' ),
            'manage_options',
            'wpb-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Output settings page markup.
     */
    public function render_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Ajustes de Booking', 'wp-plugin-booking' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'wpb_settings_group' );
        do_settings_sections( 'wpb-settings' );
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
            update_post_meta( $post_id, '_wpb_status', sanitize_text_field( $_POST['wpb_status'] ) );
        }
    }
}
