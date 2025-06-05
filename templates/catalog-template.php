<?php
/**
 * Template for Booking Catalog page.
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'wpb-catalog-page' ); ?>>
<?php echo do_shortcode( '[booking_catalog]' ); ?>
<?php wp_footer(); ?>
</body>
</html>
