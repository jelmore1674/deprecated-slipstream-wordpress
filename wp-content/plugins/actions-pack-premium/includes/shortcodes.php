<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
 * Redirect user to a specific url after logout.
 *
 * Usage: [ap-redirect-logout url="/your-url"]
 */
function ap_redirect_after_logout_url( $atts ){

    $args = shortcode_atts( array(
        'url' => 'url',
    ), $atts );

    return wp_logout_url($args['url']);
}
add_shortcode( 'ap-redirect-logout', 'ap_redirect_after_logout_url' );