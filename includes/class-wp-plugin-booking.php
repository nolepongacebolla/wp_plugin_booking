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

        register_post_meta( 'wpb_service', '_wpb_items', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => array( $this, 'sanitize_items_meta' ),
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

    /**
     * Sanitize items meta value stored as JSON.
     */
    public function sanitize_items_meta( $value, $meta_key = '', $object_type = '', $object_subtype = '' ) {
        $items = json_decode( $value, true );
        if ( ! is_array( $items ) ) {
            return '';
        }
        $clean = array();
        foreach ( $items as $item ) {
            if ( empty( $item['name'] ) ) {
                continue;
            }
            $clean[] = array(
                'name'  => sanitize_text_field( $item['name'] ),
                'price' => floatval( $item['price'] ),
                'stock' => isset( $item['stock'] ) ? absint( $item['stock'] ) : 0,
                'type'  => in_array( $item['type'], array( 'limited', 'unlimited', 'included' ), true ) ? $item['type'] : 'limited',
            );
        }
        return wp_json_encode( $clean );
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

        add_meta_box(
            'wpb_items_meta',
            __( 'Artículos/Productos', 'wp-plugin-booking' ),
            array( $this, 'render_items_meta_box' ),
            'wpb_service'
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

    public function render_items_meta_box( $post ) {
        $json = get_post_meta( $post->ID, '_wpb_items', true );
        $items = $json ? json_decode( $json, true ) : array();
        echo '<table class="widefat wpb-items-table"><thead><tr><th>' . esc_html__( 'Nombre', 'wp-plugin-booking' ) . '</th><th>' . esc_html__( 'Precio', 'wp-plugin-booking' ) . '</th><th>' . esc_html__( 'Cantidad', 'wp-plugin-booking' ) . '</th><th>' . esc_html__( 'Tipo', 'wp-plugin-booking' ) . '</th><th></th></tr></thead><tbody>';
        if ( $items ) {
            foreach ( $items as $i => $item ) {
                $this->render_item_row( $i, $item );
            }
        }
        echo '</tbody></table>';
        echo '<p><button type="button" class="button" id="wpb-add-item">' . esc_html__( 'Añadir artículo', 'wp-plugin-booking' ) . '</button></p>';
        echo '<script type="text/html" id="tmpl-wpb-item-row">';
        ob_start();
        $this->render_item_row( '{{{data.i}}}', array( 'name' => '', 'price' => '', 'stock' => '', 'type' => 'limited' ) );
        $row = ob_get_clean();
        echo str_replace( array( '\n', '\r' ), '', $row );
        echo '</script>';
    }

    private function render_item_row( $i, $item ) {
        $name  = isset( $item['name'] ) ? $item['name'] : '';
        $price = isset( $item['price'] ) ? $item['price'] : '';
        $stock = isset( $item['stock'] ) ? $item['stock'] : '';
        $type  = isset( $item['type'] ) ? $item['type'] : 'limited';
        echo '<tr>';
        echo '<td><input type="text" name="wpb_items[name][' . esc_attr( $i ) . ']" value="' . esc_attr( $name ) . '" /></td>';
        echo '<td><input type="number" step="0.01" name="wpb_items[price][' . esc_attr( $i ) . ']" value="' . esc_attr( $price ) . '" /></td>';
        echo '<td><input type="number" min="0" name="wpb_items[stock][' . esc_attr( $i ) . ']" value="' . esc_attr( $stock ) . '" /></td>';
        echo '<td><select name="wpb_items[type][' . esc_attr( $i ) . ']">';
        $options = array(
            'limited'   => __( 'Stock limitado', 'wp-plugin-booking' ),
            'unlimited' => __( 'Ilimitado', 'wp-plugin-booking' ),
            'included'  => __( 'Incluido', 'wp-plugin-booking' ),
        );
        foreach ( $options as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '"' . selected( $type, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td>';
        echo '<td><button type="button" class="button wpb-remove-item">&times;</button></td>';
        echo '</tr>';
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
        if ( isset( $_POST['wpb_items'] ) && is_array( $_POST['wpb_items'] ) ) {
            $items = array();
            $names  = isset( $_POST['wpb_items']['name'] ) ? (array) $_POST['wpb_items']['name'] : array();
            $prices = isset( $_POST['wpb_items']['price'] ) ? (array) $_POST['wpb_items']['price'] : array();
            $stocks = isset( $_POST['wpb_items']['stock'] ) ? (array) $_POST['wpb_items']['stock'] : array();
            $types  = isset( $_POST['wpb_items']['type'] ) ? (array) $_POST['wpb_items']['type'] : array();
            $count = max( count( $names ), count( $prices ) );
            for ( $i = 0; $i < $count; $i++ ) {
                if ( empty( $names[ $i ] ) ) {
                    continue;
                }
                $items[] = array(
                    'name'  => sanitize_text_field( $names[ $i ] ),
                    'price' => floatval( $prices[ $i ] ?? 0 ),
                    'stock' => absint( $stocks[ $i ] ?? 0 ),
                    'type'  => in_array( $types[ $i ] ?? 'limited', array( 'limited', 'unlimited', 'included' ), true ) ? $types[ $i ] : 'limited',
                );
            }
            update_post_meta( $post_id, '_wpb_items', wp_json_encode( $items ) );
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

    /**
     * Get remaining stock for each item in a service.
     *
     * @param int $service_id Service ID.
     * @return array Associative array of item index => remaining quantity.
     */
    public function get_remaining_items_stock( $service_id ) {
        $items_meta = get_post_meta( $service_id, '_wpb_items', true );
        $items      = $items_meta ? json_decode( $items_meta, true ) : array();
        $remaining  = array();

        foreach ( $items as $index => $item ) {
            if ( 'limited' === $item['type'] ) {
                $remaining[ $index ] = absint( $item['stock'] );
            }
        }

        if ( ! $remaining ) {
            return $remaining;
        }

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

        foreach ( $bookings as $booking ) {
            $data = get_post_meta( $booking->ID, '_wpb_items_data', true );
            if ( $data && is_array( $data ) ) {
                foreach ( $data as $idx => $qty ) {
                    if ( isset( $remaining[ $idx ] ) ) {
                        $remaining[ $idx ] -= absint( $qty );
                    }
                }
            }
        }

        foreach ( $remaining as $idx => $qty ) {
            if ( $qty < 0 ) {
                $remaining[ $idx ] = 0;
            }
        }

        return $remaining;
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
            wp_send_json_error( array( 'message' => sprintf( __( 'Solo quedan %d cupos disponibles', 'wp-plugin-booking' ), $remaining ) ) );
        }

        $price    = floatval( get_post_meta( $service_id, '_wpb_price_per_person', true ) );
        $discount = floatval( get_post_meta( $service_id, '_wpb_discount_percent', true ) );
        $min_disc = absint( get_post_meta( $service_id, '_wpb_discount_min', true ) );
        $total    = $price * $persons;
        if ( $discount && $persons >= $min_disc ) {
            $total = $total * ( 1 - $discount / 100 );
        }

        $items_meta      = get_post_meta( $service_id, '_wpb_items', true );
        $items_def       = $items_meta ? json_decode( $items_meta, true ) : array();
        $items_remaining = $this->get_remaining_items_stock( $service_id );
        $items_sel       = array();

        if ( isset( $_POST['items_qty'] ) && is_array( $_POST['items_qty'] ) ) {
            foreach ( $_POST['items_qty'] as $index => $qty ) {
                $qty = absint( $qty );
                if ( isset( $items_def[ $index ] ) && $qty > 0 ) {
                    $item = $items_def[ $index ];
                    if ( 'limited' === $item['type'] ) {
                        $avail = isset( $items_remaining[ $index ] ) ? $items_remaining[ $index ] : 0;
                        if ( $qty > $avail ) {
                            wp_send_json_error( array( 'message' => sprintf( __( 'Stock insuficiente para %s. Quedan %d.', 'wp-plugin-booking' ), $item['name'], $avail ) ) );
                        }
                    }

                    $items_sel[ $index ] = $qty;
                    if ( 'included' !== $item['type'] ) {
                        $total += $qty * floatval( $item['price'] );
                    }
                }
            }
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
            if ( $items_sel ) {
                update_post_meta( $booking_id, '_wpb_items_data', $items_sel );
            }

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
        $custom_css  = $this->generate_modal_css();
        $custom_css .= "\n" . $this->generate_catalog_css();
        if ( $custom_css ) {
            wp_add_inline_style( 'wpb-catalog', $custom_css );
        }

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
        $logo = get_option( 'wpb_front_title', 'Aventura Tours' );
        echo '<header class="wpb-header"><nav><div class="logo">' . esc_html( $logo ) . '</div></nav></header>';
        echo '<div class="floating-elements"><div class="floating-circle circle1"></div><div class="floating-circle circle2"></div><div class="floating-circle circle3"></div></div>';
        echo '<main class="wpb-main">';
        $hero_title    = get_option( 'wpb_cat_title_text', get_option( 'wpb_front_title', 'Descubre el Mundo' ) );
        $hero_subtitle = get_option( 'wpb_cat_subtitle_text', get_option( 'wpb_front_subtitle', 'Experiencias inolvidables te esperan' ) );

        echo '<section class="hero">';
        echo '<h1 class="hero-title">' . esc_html( $hero_title ) . '</h1>';
        echo '<p class="hero-subtitle">' . esc_html( $hero_subtitle ) . '</p>';
        echo '</section>';
        echo '<section class="catalog" id="tours">';
        echo '<div class="wpb-catalog-search text-center mb-4">';
        echo '<form class="row row-cols-sm-auto g-2 justify-content-center" method="get">';

        $terms = get_terms( array( 'taxonomy' => 'wpb_service_category', 'hide_empty' => false ) );
        echo '<div class="col">';
        echo '<select name="category" class="form-select"><option value="">' . esc_html__( 'Todas las categorías', 'wp-plugin-booking' ) . '</option>';
        foreach ( $terms as $term ) {
            $selected = selected( isset( $_GET['category'] ) ? absint( $_GET['category'] ) : '', $term->term_id, false );
            echo '<option value="' . esc_attr( $term->term_id ) . '" ' . $selected . '>' . esc_html( $term->name ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        $btn_text = get_option( 'wpb_cat_btn_text', __( 'Filtrar', 'wp-plugin-booking' ) );
        echo '<div class="col-auto">';
        echo '<button type="submit" class="btn btn-danger">' . esc_html( $btn_text ) . '</button>';

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
            $desc_header = get_option( 'wpb_desc_header', 'Descripción' );
            echo '<h5 class="wpb-desc-header mb-2">' . esc_html( $desc_header ) . ':</h5>';

            echo '<div class="wpb-description mb-3">' . apply_filters( 'the_content', get_the_content() ) . '</div>';
            if ( $includes ) {
                $inc_header = get_option( 'wpb_include_header', 'Incluye' );
                echo '<div class="mb-3 wpb-includes">';
                echo '<h5 class="fw-bold mb-2">' . esc_html( $inc_header ) . '</h5>';
                echo wpautop( wp_kses_post( $includes ) );
                echo '</div>';

            }
            if ( $terms_txt ) {
                echo '<details class="wpb-terms mb-3"><summary>' . esc_html__( 'Términos y condiciones', 'wp-plugin-booking' ) . '</summary>' . wpautop( wp_kses_post( $terms_txt ) ) . '</details>';
                echo '<div class="terms-acceptance">';
                $cid = 'wpb_accept_' . $id;
                echo '<label class="checkbox-container">';
                echo '<input type="checkbox" id="' . esc_attr( $cid ) . '" required />';
                $chk_txt = get_option( 'wpb_checkbox_text', __( 'He leído y acepto los términos y condiciones', 'wp-plugin-booking' ) );
                echo '<span class="checkmark"></span>' . esc_html( $chk_txt );

                echo '</label>';
                echo '<div class="error-message wpb-terms-error">' . esc_html__( 'Debes aceptar los términos y condiciones', 'wp-plugin-booking' ) . '</div>';
                echo '</div>';
            }
            echo '<button type="button" class="btn btn-danger wpb-next mt-3">' . esc_html__( 'Siguiente', 'wp-plugin-booking' ) . '</button>';

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
            echo '<button type="button" class="btn btn-secondary wpb-prev me-2">' . esc_html__( 'Atrás', 'wp-plugin-booking' ) . '</button>';
            echo '<button type="button" class="btn btn-danger wpb-next">' . esc_html__( 'Siguiente', 'wp-plugin-booking' ) . '</button>';

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

            $items_json = get_post_meta( $id, '_wpb_items', true );
            $items      = $items_json ? json_decode( $items_json, true ) : array();
            $stock_rem  = $this->get_remaining_items_stock( $id );

            if ( $items ) {
                echo '<div class="mb-3">';
                echo '<label class="form-label">' . esc_html__( 'Artículos', 'wp-plugin-booking' ) . '</label>';
                foreach ( $items as $idx => $item ) {
                    $price = floatval( $item['price'] );
                    $label = esc_html( $item['name'] ) . ( $price ? ' - RD$ ' . number_format_i18n( $price, 2 ) : '' );
                    echo '<div class="d-flex align-items-center mb-2" data-price="' . esc_attr( $price ) . '">';
                    echo '<span class="me-2" style="min-width:150px;">' . $label . '</span>';
                    if ( 'included' === $item['type'] ) {
                        $qty = absint( $item['stock'] ? $item['stock'] : 1 );
                        echo '<span class="me-2">' . esc_html__( 'Incluido', 'wp-plugin-booking' ) . '</span>';
                        echo '<input type="hidden" name="items_qty[' . esc_attr( $idx ) . ']" value="' . $qty . '" />';
                    } else {
                        $remaining = isset( $stock_rem[ $idx ] ) ? $stock_rem[ $idx ] : ( 'unlimited' === $item['type'] ? '' : absint( $item['stock'] ) );
                        $max_attr = 'unlimited' === $item['type'] ? '' : ' max="' . $remaining . '"';
                        if ( 'limited' === $item['type'] ) {
                            echo '<small class="text-muted me-2">' . sprintf( __( 'Quedan %d', 'wp-plugin-booking' ), $remaining ) . '</small>';
                        }

                        echo '<input type="number" class="form-control" style="width:80px;" min="0"' . $max_attr . ' name="items_qty[' . esc_attr( $idx ) . ']" value="0" />';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }

            echo '<button type="button" class="btn btn-secondary wpb-prev me-2">' . esc_html__( 'Atrás', 'wp-plugin-booking' ) . '</button>';
            echo '<button type="button" class="btn btn-danger wpb-next">' . esc_html__( 'Siguiente', 'wp-plugin-booking' ) . '</button>';

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
            echo '<button type="button" class="btn btn-secondary wpb-prev me-2">' . esc_html__( 'Atrás', 'wp-plugin-booking' ) . '</button>';
            echo '<button type="button" class="btn btn-danger wpb-next">' . esc_html__( 'Siguiente', 'wp-plugin-booking' ) . '</button>';

            echo '</div>';

            echo '<div class="wpb-step wpb-summary-step">';
            echo '<p><strong>' . esc_html__( 'Servicio:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-service"></span></p>';
            echo '<p><strong>' . esc_html__( 'Fecha de inicio:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-date"></span></p>';
            echo '<p><strong>' . esc_html__( 'Nombre:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-name"></span></p>';
            echo '<p><strong>' . esc_html__( 'Email:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-email"></span></p>';
            echo '<p><strong>' . esc_html__( 'Personas:', 'wp-plugin-booking' ) . '</strong> <span class="wpb-summary-persons"></span></p>';
            echo '<div class="wpb-summary-items"></div>';
            echo '<p><strong>' . esc_html__( 'Total:', 'wp-plugin-booking' ) . '</strong> RD$ <span class="wpb-summary-total"></span></p>';
            echo '<div class="wpb-error text-danger mb-2"></div>';
            echo '<button type="button" class="btn btn-secondary wpb-prev me-2">' . esc_html__( 'Atrás', 'wp-plugin-booking' ) . '</button>';
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
        echo '</section>';

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
        echo '</main>';

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

        /* Catalog page design settings */
        register_setting( 'wpb_catalog_group', 'wpb_cat_title_text', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '🌴 Paraíso Turístico',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_title_font', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Poppins',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_title_size', array(
            'sanitize_callback' => 'absint',
            'default'           => 48,
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_title_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#000000',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_subtitle_text', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Experiencias inolvidables te esperan',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_subtitle_size', array(
            'sanitize_callback' => 'absint',
            'default'           => 18,
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_subtitle_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#333333',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_subtitle_align', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'center',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_bg_type', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'white',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_bg_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#ffffff',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_bg_image', array(
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_btn_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#dc3545',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_btn_text', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Filtrar',
        ) );
        register_setting( 'wpb_catalog_group', 'wpb_cat_btn_radius', array(
            'sanitize_callback' => 'absint',
            'default'           => 4,
        ) );


        /* Modal design settings */
        register_setting( 'wpb_modal_group', 'wpb_modal_font', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Poppins',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_modal_font_size', array(
            'sanitize_callback' => 'absint',
            'default'           => 16,
        ) );
        register_setting( 'wpb_modal_group', 'wpb_modal_text_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#444444',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_modal_title_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#000000',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_modal_bg_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#ffffff',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_modal_border_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#dc3545',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_modal_next_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#dc3545',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_modal_close_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#000000',
        ) );

        register_setting( 'wpb_modal_group', 'wpb_include_show', array(
            'sanitize_callback' => 'absint',
            'default'           => 1,
        ) );
        register_setting( 'wpb_modal_group', 'wpb_include_header', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Incluye',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_include_bg_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#f9f9f9',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_include_text_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#444444',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_include_padding', array(
            'sanitize_callback' => 'absint',
            'default'           => 15,
        ) );

        register_setting( 'wpb_modal_group', 'wpb_desc_bg_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#f9f9f9',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_desc_text_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#444444',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_desc_align', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'left',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_desc_line_height', array(
            'sanitize_callback' => 'floatval',
            'default'           => 1.6,
        ) );

        register_setting( 'wpb_modal_group', 'wpb_desc_header', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Descripción',
        ) );

        register_setting( 'wpb_modal_group', 'wpb_img_size', array(
            'sanitize_callback' => 'absint',
            'default'           => 150,
        ) );
        register_setting( 'wpb_modal_group', 'wpb_img_spacing', array(
            'sanitize_callback' => 'absint',
            'default'           => 5,
        ) );
        register_setting( 'wpb_modal_group', 'wpb_img_radius', array(
            'sanitize_callback' => 'absint',
            'default'           => 8,
        ) );

        register_setting( 'wpb_modal_group', 'wpb_checkbox_text', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'He leído y acepto los términos y condiciones',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_checkbox_bg', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#ffffff',
        ) );
        register_setting( 'wpb_modal_group', 'wpb_checkbox_style', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'normal',
        ) );

        register_setting( 'wpb_modal_group', 'wpb_modal_custom_css', array(
            'sanitize_callback' => 'wp_kses_post',
            'default'           => '',
        ) );

        add_settings_section( 'wpb_main', __( 'Ajustes Generales', 'wp-plugin-booking' ), null, 'wpb-settings' );
        add_settings_section( 'wpb_email', __( 'Plantilla de Correo', 'wp-plugin-booking' ), null, 'wpb-email' );
        add_settings_section( 'wpb_frontpage', __( 'Textos de Portada', 'wp-plugin-booking' ), null, 'wpb-frontpage' );
        add_settings_section( 'wpb_modal', __( 'Diseño del Modal de Reserva', 'wp-plugin-booking' ), null, 'wpb-modal' );
        add_settings_section( 'wpb_catalog', __( 'Diseño de la Página de Catálogo', 'wp-plugin-booking' ), null, 'wpb-catalog' );


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

        /* Modal tab fields */
        add_settings_field( 'wpb_modal_font', __( 'Fuente', 'wp-plugin-booking' ), array( $this, 'modal_font_field' ), 'wpb-modal', 'wpb_modal' );
        add_settings_field( 'wpb_modal_font_size', __( 'Tamaño de fuente', 'wp-plugin-booking' ), array( $this, 'modal_font_size_field' ), 'wpb-modal', 'wpb_modal' );
        add_settings_field( 'wpb_modal_text_color', __( 'Color de texto', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_modal_text_color', 'option' => 'wpb_modal_text_color' ) );
        add_settings_field( 'wpb_modal_title_color', __( 'Color del título', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_modal_title_color', 'option' => 'wpb_modal_title_color' ) );
        add_settings_field( 'wpb_modal_bg_color', __( 'Fondo del modal', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_modal_bg_color', 'option' => 'wpb_modal_bg_color' ) );
        add_settings_field( 'wpb_modal_border_color', __( 'Borde del modal', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_modal_border_color', 'option' => 'wpb_modal_border_color' ) );
        add_settings_field( 'wpb_modal_next_color', __( 'Color botón Siguiente', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_modal_next_color', 'option' => 'wpb_modal_next_color' ) );
        add_settings_field( 'wpb_modal_close_color', __( 'Color botón cerrar', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_modal_close_color', 'option' => 'wpb_modal_close_color' ) );

        add_settings_field( 'wpb_include_show', __( 'Mostrar recuadro Incluye', 'wp-plugin-booking' ), array( $this, 'modal_checkbox_field' ), 'wpb-modal', 'wpb_modal' );
        add_settings_field( 'wpb_include_header', __( 'Título del recuadro', 'wp-plugin-booking' ), array( $this, 'modal_include_header_field' ), 'wpb-modal', 'wpb_modal' );
        add_settings_field( 'wpb_include_bg_color', __( 'Fondo recuadro', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_include_bg_color', 'option' => 'wpb_include_bg_color' ) );
        add_settings_field( 'wpb_include_text_color', __( 'Color de texto recuadro', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_include_text_color', 'option' => 'wpb_include_text_color' ) );
        add_settings_field( 'wpb_include_padding', __( 'Padding recuadro', 'wp-plugin-booking' ), array( $this, 'modal_padding_field' ), 'wpb-modal', 'wpb_modal' );

        add_settings_field( 'wpb_desc_bg_color', __( 'Fondo descripción', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_desc_bg_color', 'option' => 'wpb_desc_bg_color' ) );
        add_settings_field( 'wpb_desc_text_color', __( 'Color texto descripción', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_desc_text_color', 'option' => 'wpb_desc_text_color' ) );
        add_settings_field( 'wpb_desc_align', __( 'Alineación del texto', 'wp-plugin-booking' ), array( $this, 'modal_align_field' ), 'wpb-modal', 'wpb_modal' );
        add_settings_field( 'wpb_desc_line_height', __( 'Espaciado entre líneas', 'wp-plugin-booking' ), array( $this, 'modal_line_height_field' ), 'wpb-modal', 'wpb_modal' );

        add_settings_field( 'wpb_desc_header', __( 'Título descripción', 'wp-plugin-booking' ), array( $this, 'modal_desc_header_field' ), 'wpb-modal', 'wpb_modal' );

        add_settings_field( 'wpb_img_size', __( 'Tamaño de imágenes', 'wp-plugin-booking' ), array( $this, 'modal_img_size_field' ), 'wpb-modal', 'wpb_modal' );
        add_settings_field( 'wpb_img_spacing', __( 'Espaciado entre imágenes', 'wp-plugin-booking' ), array( $this, 'modal_img_spacing_field' ), 'wpb-modal', 'wpb_modal' );
        add_settings_field( 'wpb_img_radius', __( 'Bordes redondeados', 'wp-plugin-booking' ), array( $this, 'modal_img_radius_field' ), 'wpb-modal', 'wpb_modal' );

        add_settings_field( 'wpb_checkbox_text', __( 'Texto del checkbox', 'wp-plugin-booking' ), array( $this, 'modal_checkbox_text_field' ), 'wpb-modal', 'wpb_modal' );
        add_settings_field( 'wpb_checkbox_bg', __( 'Color fondo checkbox', 'wp-plugin-booking' ), array( $this, 'modal_color_field' ), 'wpb-modal', 'wpb_modal', array( 'label_for' => 'wpb_checkbox_bg', 'option' => 'wpb_checkbox_bg' ) );
        add_settings_field( 'wpb_checkbox_style', __( 'Estilo del texto', 'wp-plugin-booking' ), array( $this, 'modal_style_field' ), 'wpb-modal', 'wpb_modal' );

        add_settings_field( 'wpb_modal_custom_css', __( 'CSS adicional', 'wp-plugin-booking' ), array( $this, 'modal_custom_css_field' ), 'wpb-modal', 'wpb_modal' );

        /* Catalog design tab fields */
        add_settings_field( 'wpb_cat_title_text', __( 'Título', 'wp-plugin-booking' ), array( $this, 'cat_title_text_field' ), 'wpb-catalog', 'wpb_catalog' );
        add_settings_field( 'wpb_cat_title_font', __( 'Fuente del título', 'wp-plugin-booking' ), array( $this, 'cat_title_font_field' ), 'wpb-catalog', 'wpb_catalog' );
        add_settings_field( 'wpb_cat_title_size', __( 'Tamaño del título', 'wp-plugin-booking' ), array( $this, 'cat_title_size_field' ), 'wpb-catalog', 'wpb_catalog' );
        add_settings_field( 'wpb_cat_title_color', __( 'Color del título', 'wp-plugin-booking' ), array( $this, 'cat_color_field' ), 'wpb-catalog', 'wpb_catalog', array( 'label_for' => 'wpb_cat_title_color', 'option' => 'wpb_cat_title_color' ) );
        add_settings_field( 'wpb_cat_subtitle_text', __( 'Subtítulo', 'wp-plugin-booking' ), array( $this, 'cat_subtitle_text_field' ), 'wpb-catalog', 'wpb_catalog' );
        add_settings_field( 'wpb_cat_subtitle_size', __( 'Tamaño subtítulo', 'wp-plugin-booking' ), array( $this, 'cat_subtitle_size_field' ), 'wpb-catalog', 'wpb_catalog' );
        add_settings_field( 'wpb_cat_subtitle_color', __( 'Color subtítulo', 'wp-plugin-booking' ), array( $this, 'cat_color_field' ), 'wpb-catalog', 'wpb_catalog', array( 'label_for' => 'wpb_cat_subtitle_color', 'option' => 'wpb_cat_subtitle_color' ) );
        add_settings_field( 'wpb_cat_subtitle_align', __( 'Alineación subtítulo', 'wp-plugin-booking' ), array( $this, 'cat_subtitle_align_field' ), 'wpb-catalog', 'wpb_catalog' );
        add_settings_field( 'wpb_cat_bg_type', __( 'Fondo de página', 'wp-plugin-booking' ), array( $this, 'cat_bg_type_field' ), 'wpb-catalog', 'wpb_catalog' );
        add_settings_field( 'wpb_cat_bg_color', __( 'Color de fondo', 'wp-plugin-booking' ), array( $this, 'cat_color_field' ), 'wpb-catalog', 'wpb_catalog', array( 'label_for' => 'wpb_cat_bg_color', 'option' => 'wpb_cat_bg_color' ) );
        add_settings_field( 'wpb_cat_bg_image', __( 'Imagen de fondo', 'wp-plugin-booking' ), array( $this, 'cat_bg_image_field' ), 'wpb-catalog', 'wpb_catalog' );
        add_settings_field( 'wpb_cat_btn_text', __( 'Texto del botón', 'wp-plugin-booking' ), array( $this, 'cat_btn_text_field' ), 'wpb-catalog', 'wpb_catalog' );
        add_settings_field( 'wpb_cat_btn_color', __( 'Color del botón', 'wp-plugin-booking' ), array( $this, 'cat_color_field' ), 'wpb-catalog', 'wpb_catalog', array( 'label_for' => 'wpb_cat_btn_color', 'option' => 'wpb_cat_btn_color' ) );
        add_settings_field( 'wpb_cat_btn_radius', __( 'Bordes del botón', 'wp-plugin-booking' ), array( $this, 'cat_btn_radius_field' ), 'wpb-catalog', 'wpb_catalog' );

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

    /* Modal design fields */
    public function modal_font_field() {
        $value = get_option( 'wpb_modal_font', 'Poppins' );
        echo '<select name="wpb_modal_font"><option value="Poppins"' . selected( $value, 'Poppins', false ) . '>Poppins</option><option value="Roboto"' . selected( $value, 'Roboto', false ) . '>Roboto</option><option value="Arial"' . selected( $value, 'Arial', false ) . '>Arial</option></select>';
    }

    public function modal_font_size_field() {
        $value = absint( get_option( 'wpb_modal_font_size', 16 ) );
        echo '<input type="number" name="wpb_modal_font_size" value="' . esc_attr( $value ) . '" class="small-text" /> px';
    }

    public function modal_color_field( $args ) {
        $option = isset( $args['option'] ) ? $args['option'] : '';
        $value  = get_option( $option, '' );
        echo '<input type="text" class="wp-color-picker-field" data-default-color="' . esc_attr( $value ) . '" name="' . esc_attr( $option ) . '" value="' . esc_attr( $value ) . '" />';
    }

    public function modal_checkbox_field() {
        $value = get_option( 'wpb_include_show', 1 );
        echo '<label><input type="checkbox" name="wpb_include_show" value="1"' . checked( $value, 1, false ) . ' /> ' . esc_html__( 'Mostrar recuadro', 'wp-plugin-booking' ) . '</label>';
    }

    public function modal_include_header_field() {
        $value = get_option( 'wpb_include_header', 'Incluye' );
        echo '<input type="text" name="wpb_include_header" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function modal_padding_field() {
        $value = absint( get_option( 'wpb_include_padding', 15 ) );
        echo '<input type="number" name="wpb_include_padding" value="' . esc_attr( $value ) . '" class="small-text" /> px';
    }

    public function modal_align_field() {
        $value = get_option( 'wpb_desc_align', 'left' );
        echo '<select name="wpb_desc_align"><option value="left"' . selected( $value, 'left', false ) . '>' . esc_html__( 'Izquierda', 'wp-plugin-booking' ) . '</option><option value="center"' . selected( $value, 'center', false ) . '>' . esc_html__( 'Centrado', 'wp-plugin-booking' ) . '</option><option value="justify"' . selected( $value, 'justify', false ) . '>' . esc_html__( 'Justificado', 'wp-plugin-booking' ) . '</option></select>';
    }

    public function modal_line_height_field() {
        $value = get_option( 'wpb_desc_line_height', 1.6 );
        echo '<input type="number" step="0.1" name="wpb_desc_line_height" value="' . esc_attr( $value ) . '" class="small-text" />';
    }

    public function modal_img_size_field() {
        $value = absint( get_option( 'wpb_img_size', 150 ) );
        echo '<input type="number" name="wpb_img_size" value="' . esc_attr( $value ) . '" class="small-text" /> px';
    }

    public function modal_img_spacing_field() {
        $value = absint( get_option( 'wpb_img_spacing', 5 ) );
        echo '<input type="number" name="wpb_img_spacing" value="' . esc_attr( $value ) . '" class="small-text" /> px';
    }

    public function modal_img_radius_field() {
        $value = absint( get_option( 'wpb_img_radius', 8 ) );
        echo '<input type="number" name="wpb_img_radius" value="' . esc_attr( $value ) . '" class="small-text" /> px';
    }

    public function modal_desc_header_field() {
        $value = get_option( 'wpb_desc_header', 'Descripción' );
        echo '<input type="text" name="wpb_desc_header" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function modal_checkbox_text_field() {
        $value = get_option( 'wpb_checkbox_text', 'He leído y acepto los términos y condiciones' );
        echo '<input type="text" name="wpb_checkbox_text" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function modal_style_field() {
        $value = get_option( 'wpb_checkbox_style', 'normal' );
        echo '<select name="wpb_checkbox_style"><option value="normal"' . selected( $value, 'normal', false ) . '>' . esc_html__( 'Normal', 'wp-plugin-booking' ) . '</option><option value="italic"' . selected( $value, 'italic', false ) . '>Italic</option><option value="bold"' . selected( $value, 'bold', false ) . '>Bold</option></select>';
    }

    public function modal_custom_css_field() {
        $value = get_option( 'wpb_modal_custom_css', '' );
        echo '<textarea name="wpb_modal_custom_css" rows="5" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
    }

    /* Catalog design fields */
    public function cat_title_text_field() {
        $value = get_option( 'wpb_cat_title_text', '🌴 Paraíso Turístico' );
        echo '<input type="text" id="wpb_cat_title_text" name="wpb_cat_title_text" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function cat_title_font_field() {
        $value = get_option( 'wpb_cat_title_font', 'Poppins' );
        echo '<select id="wpb_cat_title_font" name="wpb_cat_title_font"><option value="Poppins"' . selected( $value, 'Poppins', false ) . '>Poppins</option><option value="Roboto"' . selected( $value, 'Roboto', false ) . '>Roboto</option><option value="Arial"' . selected( $value, 'Arial', false ) . '>Arial</option></select>';
    }

    public function cat_title_size_field() {
        $value = absint( get_option( 'wpb_cat_title_size', 48 ) );
        echo '<input type="number" id="wpb_cat_title_size" name="wpb_cat_title_size" value="' . esc_attr( $value ) . '" class="small-text" /> px';
    }

    public function cat_color_field( $args ) {
        $option = isset( $args['option'] ) ? $args['option'] : '';
        $value  = get_option( $option, '' );
        echo '<input type="text" class="wp-color-picker-field" data-default-color="' . esc_attr( $value ) . '" id="' . esc_attr( $option ) . '" name="' . esc_attr( $option ) . '" value="' . esc_attr( $value ) . '" />';
    }

    public function cat_subtitle_text_field() {
        $value = get_option( 'wpb_cat_subtitle_text', '' );
        echo '<input type="text" id="wpb_cat_subtitle_text" name="wpb_cat_subtitle_text" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function cat_subtitle_size_field() {
        $value = absint( get_option( 'wpb_cat_subtitle_size', 18 ) );
        echo '<input type="number" id="wpb_cat_subtitle_size" name="wpb_cat_subtitle_size" value="' . esc_attr( $value ) . '" class="small-text" /> px';
    }

    public function cat_subtitle_align_field() {
        $value = get_option( 'wpb_cat_subtitle_align', 'center' );
        echo '<select id="wpb_cat_subtitle_align" name="wpb_cat_subtitle_align"><option value="left"' . selected( $value, 'left', false ) . '>' . esc_html__( 'Izquierda', 'wp-plugin-booking' ) . '</option><option value="center"' . selected( $value, 'center', false ) . '>' . esc_html__( 'Centrado', 'wp-plugin-booking' ) . '</option><option value="right"' . selected( $value, 'right', false ) . '>' . esc_html__( 'Derecha', 'wp-plugin-booking' ) . '</option></select>';
    }

    public function cat_bg_type_field() {
        $value = get_option( 'wpb_cat_bg_type', 'white' );
        echo '<select id="wpb_cat_bg_type" name="wpb_cat_bg_type"><option value="white"' . selected( $value, 'white', false ) . '>' . esc_html__( 'Blanco', 'wp-plugin-booking' ) . '</option><option value="color"' . selected( $value, 'color', false ) . '>' . esc_html__( 'Color personalizado', 'wp-plugin-booking' ) . '</option><option value="image"' . selected( $value, 'image', false ) . '>' . esc_html__( 'Imagen', 'wp-plugin-booking' ) . '</option></select>';
    }

    public function cat_bg_image_field() {
        $value = get_option( 'wpb_cat_bg_image', '' );
        echo '<input type="text" id="wpb_cat_bg_image" name="wpb_cat_bg_image" value="' . esc_attr( $value ) . '" class="regular-text" /> <button type="button" class="button wpb-select-image" data-target="wpb_cat_bg_image">' . esc_html__( 'Seleccionar', 'wp-plugin-booking' ) . '</button>';
    }

    public function cat_btn_text_field() {
        $value = get_option( 'wpb_cat_btn_text', 'Filtrar' );
        echo '<input type="text" id="wpb_cat_btn_text" name="wpb_cat_btn_text" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function cat_btn_radius_field() {
        $value = absint( get_option( 'wpb_cat_btn_radius', 4 ) );
        echo '<input type="number" id="wpb_cat_btn_radius" name="wpb_cat_btn_radius" value="' . esc_attr( $value ) . '" class="small-text" /> px';
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
        echo '<a href="?page=wpb-settings&tab=modal" class="nav-tab' . ( 'modal' === $tab ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Diseño del Modal', 'wp-plugin-booking' ) . '</a>';
        echo '<a href="?page=wpb-settings&tab=catalog" class="nav-tab' . ( 'catalog' === $tab ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Diseño del Catálogo', 'wp-plugin-booking' ) . '</a>';

        echo '</h2>';
        echo '<form method="post" action="options.php">';
        if ( 'email' === $tab ) {
            settings_fields( 'wpb_email_group' );
            do_settings_sections( 'wpb-email' );
        } elseif ( 'frontpage' === $tab ) {
            settings_fields( 'wpb_frontpage_group' );
            do_settings_sections( 'wpb-frontpage' );
        } elseif ( 'modal' === $tab ) {
            settings_fields( 'wpb_modal_group' );
            do_settings_sections( 'wpb-modal' );
        } elseif ( 'catalog' === $tab ) {
            settings_fields( 'wpb_catalog_group' );
            $default_btn = esc_html__( 'Filtrar', 'wp-plugin-booking' );
            echo '<div id="wpb-catalog-preview" style="padding:20px;border:1px solid #ddd;margin-bottom:20px;">'
                . '<h2 class="preview-title" style="margin:0 0 10px;"></h2>'
                . '<p class="preview-subtitle" style="margin:0 0 10px;"></p>'
                . '<button type="button" class="button preview-btn">' . $default_btn . '</button>'
                . '</div>';
            do_settings_sections( 'wpb-catalog' );

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

        add_submenu_page(
            'wpbookingstandar',
            __( 'Categorías', 'wp-plugin-booking' ),
            __( 'Categorías', 'wp-plugin-booking' ),
            'manage_options',
            'edit-tags.php?taxonomy=wpb_service_category&post_type=wpb_service'
        );

    }

    /**
     * Display simple booking statistics.
     */
    public function render_stats_page() {
        $from  = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
        $to    = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
        $srv   = isset( $_GET['service'] ) ? absint( $_GET['service'] ) : 0;

        $args = array(
            'post_type'   => 'wpb_booking',
            'numberposts' => -1,
        );
        if ( $from || $to ) {
            $range = array();
            if ( $from ) { $range['after'] = $from; }
            if ( $to ) { $range['before'] = $to; }
            $args['date_query'] = array( $range );
        }
        if ( $srv ) {
            $args['meta_query'][] = array(
                'key'   => '_wpb_service_id',
                'value' => $srv,
            );
        }

        $bookings = get_posts( $args );

        $total         = 0;
        $status_totals = array();
        $monthly_rev   = array();

        foreach ( $bookings as $booking ) {
            $price  = floatval( get_post_meta( $booking->ID, '_wpb_total_price', true ) );
            $status = get_post_meta( $booking->ID, '_wpb_status', true );
            $date   = $booking->post_date;
            $month  = date_i18n( 'Y-m', strtotime( $date ) );
            $total += $price;

            if ( ! isset( $status_totals[ $status ] ) ) {
                $status_totals[ $status ] = 0;
            }
            $status_totals[ $status ]++;

            if ( ! isset( $monthly_rev[ $month ] ) ) {
                $monthly_rev[ $month ] = 0;
            }
            $monthly_rev[ $month ] += $price;
        }

        ksort( $monthly_rev );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Estadisticas', 'wp-plugin-booking' ) . '</h1>';

        echo '<form method="get" class="wpb-stats-filter">';
        echo '<input type="hidden" name="page" value="wpb-stats" />';
        echo '<label>' . esc_html__( 'Desde', 'wp-plugin-booking' ) . ' <input type="date" name="from" value="' . esc_attr( $from ) . '" /></label> ';
        echo '<label>' . esc_html__( 'Hasta', 'wp-plugin-booking' ) . ' <input type="date" name="to" value="' . esc_attr( $to ) . '" /></label> ';
        echo '<label>' . esc_html__( 'Servicio', 'wp-plugin-booking' ) . ' '; 
        wp_dropdown_pages( array(
            'post_type'        => 'wpb_service',
            'name'             => 'service',
            'show_option_all'  => __( 'Todos', 'wp-plugin-booking' ),
            'selected'         => $srv,
        ) );
        echo '</label> '; 
        submit_button( __( 'Filtrar', 'wp-plugin-booking' ), 'secondary', '', false );
        echo '</form>';

        echo '<div class="wpb-stats-summary">';
        echo '<p>' . sprintf( esc_html__( 'Reservas totales: %d', 'wp-plugin-booking' ), count( $bookings ) ) . '</p>';
        $price_html = function_exists( 'wc_price' ) ? wc_price( $total, array( 'currency' => 'DOP' ) ) : number_format_i18n( $total, 2 ) . ' DOP';
        echo '<p>' . sprintf( esc_html__( 'Ganancias totales: %s', 'wp-plugin-booking' ), $price_html ) . '</p>';
        echo '</div>';

        echo '<div class="wpb-stats-charts" style="display:flex;gap:40px;flex-wrap:wrap;margin-top:20px;">';
        echo '<canvas id="wpb-status-chart" width="300" height="300"></canvas>';
        echo '<canvas id="wpb-revenue-chart" width="500" height="300"></canvas>';
        echo '</div>';

        echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Estatus', 'wp-plugin-booking' ) . '</th><th>' . esc_html__( 'Cantidad', 'wp-plugin-booking' ) . '</th></tr></thead><tbody>';
        foreach ( $status_totals as $st => $count ) {
            echo '<tr><td>' . esc_html( ucfirst( $st ) ) . '</td><td>' . esc_html( $count ) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        $status_labels = array_map( 'ucfirst', array_keys( $status_totals ) );
        $status_counts = array_values( $status_totals );
        $month_labels  = array_keys( $monthly_rev );
        $month_values  = array_values( $monthly_rev );

        wp_localize_script( 'wpb-stats', 'wpbStats', array(
            'statusLabels'  => array_values( $status_labels ),
            'statusCounts'  => array_values( $status_counts ),
            'monthLabels'   => array_values( $month_labels ),
            'monthRevenue'  => array_values( $month_values ),
            'revenueLabel'  => __( 'Ingresos', 'wp-plugin-booking' ),
        ) );
    }

    /**
     * Enqueue scripts for media selection on service edit screens.
     */
    public function admin_enqueue_scripts( $hook ) {
        $screen = get_current_screen();

        // Media selector for services and settings preview.
        if ( ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && 'wpb_service' === $screen->post_type ) ||
            ( $screen && 'wpbookingstandar_page_wpb-settings' === $screen->id ) ) {

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

        // Statistics page scripts.
        if ( $screen && 'wpbookingstandar_page_wpb-stats' === $screen->id ) {
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
            wp_enqueue_script(
                'wpb-stats',
                WP_PLUGIN_BOOKING_URL . 'assets/js/stats.js',
                array( 'jquery', 'chart-js' ),
                WP_PLUGIN_BOOKING_VERSION,
                true
            );
        }

        if ( $screen && 'wpbookingstandar_page_wpb-settings' === $screen->id ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".wp-color-picker-field").wpColorPicker();});' );
        }
    }

    /**
     * Generate inline CSS for modal customization based on settings.
     *
     * @return string CSS rules.
     */
    private function generate_modal_css() {
        $font     = get_option( 'wpb_modal_font', 'Poppins' );
        $size     = absint( get_option( 'wpb_modal_font_size', 16 ) );
        $txt      = sanitize_hex_color( get_option( 'wpb_modal_text_color', '#444444' ) );
        $title    = sanitize_hex_color( get_option( 'wpb_modal_title_color', '#000000' ) );
        $bg       = sanitize_hex_color( get_option( 'wpb_modal_bg_color', '#ffffff' ) );
        $border   = sanitize_hex_color( get_option( 'wpb_modal_border_color', '#dc3545' ) );
        $next     = sanitize_hex_color( get_option( 'wpb_modal_next_color', '#dc3545' ) );
        $close    = sanitize_hex_color( get_option( 'wpb_modal_close_color', '#000000' ) );

        $inc_bg   = sanitize_hex_color( get_option( 'wpb_include_bg_color', '#f9f9f9' ) );
        $inc_txt  = sanitize_hex_color( get_option( 'wpb_include_text_color', '#444444' ) );
        $inc_pad  = absint( get_option( 'wpb_include_padding', 15 ) );
        $inc_disp = get_option( 'wpb_include_show', 1 ) ? 'block' : 'none';

        $desc_bg  = sanitize_hex_color( get_option( 'wpb_desc_bg_color', '#f9f9f9' ) );
        $desc_txt = sanitize_hex_color( get_option( 'wpb_desc_text_color', '#444444' ) );
        $desc_al  = sanitize_text_field( get_option( 'wpb_desc_align', 'left' ) );
        $desc_lh  = floatval( get_option( 'wpb_desc_line_height', 1.6 ) );

        $img_size = absint( get_option( 'wpb_img_size', 150 ) );
        $img_sp   = absint( get_option( 'wpb_img_spacing', 5 ) );
        $img_rad  = absint( get_option( 'wpb_img_radius', 8 ) );

        $chk_bg   = sanitize_hex_color( get_option( 'wpb_checkbox_bg', '#ffffff' ) );
        $chk_style= sanitize_text_field( get_option( 'wpb_checkbox_style', 'normal' ) );
        $chk_weight = 'bold' === $chk_style ? 'bold' : 'normal';
        $chk_style_css = 'italic' === $chk_style ? 'italic' : 'normal';

        $extra_css = get_option( 'wpb_modal_custom_css', '' );

        $css  = ":root{";
        $css .= "--wpb-modal-font-family:'" . esc_attr( $font ) . "',sans-serif;";
        $css .= "--wpb-modal-font-size:" . $size . "px;";
        $css .= "--wpb-modal-text-color:$txt;";
        $css .= "--wpb-modal-title-color:$title;";
        $css .= "--wpb-modal-bg:$bg;";
        $css .= "--wpb-modal-border:$border;";
        $css .= "--wpb-modal-next-bg:$next;";
        $css .= "--wpb-modal-close-color:$close;";
        $css .= "--wpb-include-display:$inc_disp;";
        $css .= "--wpb-include-bg:$inc_bg;";
        $css .= "--wpb-include-text-color:$inc_txt;";
        $css .= "--wpb-include-padding:" . $inc_pad . "px;";
        $css .= "--wpb-desc-bg:$desc_bg;";
        $css .= "--wpb-desc-text-color:$desc_txt;";
        $css .= "--wpb-desc-align:$desc_al;";
        $css .= "--wpb-desc-line-height:$desc_lh;";
        $css .= "--wpb-img-size:" . $img_size . "px;";
        $css .= "--wpb-img-spacing:" . $img_sp . "px;";
        $css .= "--wpb-img-radius:" . $img_rad . "px;";
        $css .= "--wpb-checkbox-bg:$chk_bg;";
        $css .= "--wpb-checkbox-font-style:$chk_style_css;";
        $css .= "--wpb-checkbox-font-weight:$chk_weight;";
        $css .= "}";
        $css .= "\n" . wp_kses_post( $extra_css );
        return $css;
    }

    /**
     * Generate inline CSS for catalog design customization.
     */
    private function generate_catalog_css() {
        $title_font = get_option( 'wpb_cat_title_font', 'Poppins' );
        $title_size = absint( get_option( 'wpb_cat_title_size', 48 ) );
        $title_color= sanitize_hex_color( get_option( 'wpb_cat_title_color', '#000000' ) );
        $sub_size   = absint( get_option( 'wpb_cat_subtitle_size', 18 ) );
        $sub_color  = sanitize_hex_color( get_option( 'wpb_cat_subtitle_color', '#333333' ) );
        $sub_align  = sanitize_text_field( get_option( 'wpb_cat_subtitle_align', 'center' ) );
        $bg_type    = get_option( 'wpb_cat_bg_type', 'white' );
        $bg_color   = sanitize_hex_color( get_option( 'wpb_cat_bg_color', '#ffffff' ) );
        $bg_image   = esc_url_raw( get_option( 'wpb_cat_bg_image', '' ) );
        $btn_color  = sanitize_hex_color( get_option( 'wpb_cat_btn_color', '#dc3545' ) );
        $btn_radius = absint( get_option( 'wpb_cat_btn_radius', 4 ) );

        $css  = ":root{";
        $css .= "--wpb-cat-title-font:'" . esc_attr( $title_font ) . "',sans-serif;";
        $css .= "--wpb-cat-title-size:" . $title_size . "px;";
        $css .= "--wpb-cat-title-color:$title_color;";
        $css .= "--wpb-cat-sub-size:" . $sub_size . "px;";
        $css .= "--wpb-cat-sub-color:$sub_color;";
        $css .= "--wpb-cat-sub-align:$sub_align;";
        $css .= "--wpb-cat-btn-color:$btn_color;";
        $css .= "--wpb-cat-btn-radius:" . $btn_radius . "px;";
        if ( 'color' === $bg_type ) {
            $css .= "--wpb-cat-bg:$bg_color;";
        } elseif ( 'image' === $bg_type && $bg_image ) {
            $css .= "--wpb-cat-bg:url($bg_image);";
        } else {
            $css .= "--wpb-cat-bg:#ffffff;";
        }
        $css .= "}";
        return $css;
    }

    /**
     * Redirect top level menu to services list.
     */
    public function menu_redirect() {
        wp_safe_redirect( admin_url( 'edit.php?post_type=wpb_service' ) );
        exit;
    }
}
