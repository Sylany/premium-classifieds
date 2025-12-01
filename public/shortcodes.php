<?php
// File: public/shortcodes.php
if ( ! defined( 'ABSPATH' ) ) exit;

use PremiumClassifieds\Entities\Listing;
use PremiumClassifieds\Entities\User;

// Register shortcodes
add_action( 'init', function() {
    add_shortcode( 'png_listing_form', 'pc_shortcode_listing_form' );
    add_shortcode( 'png_listings_archive', 'pc_shortcode_listings_archive' );
    add_shortcode( 'png_my_listings', 'pc_shortcode_my_listings' );
    add_shortcode( 'premium_dashboard', 'pc_shortcode_premium_dashboard' );
} );

/**
 * Listing form (add/edit)
 */
function pc_shortcode_listing_form( $atts = [] ) {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'Zaloguj się aby dodać ogłoszenie.', 'premium-classifieds' ) . '</p>';
    }
    $nonce = wp_create_nonce( 'pc_frontend_nonce' );
    ob_start();
    include PC_PLUGIN_DIR . 'includes/Views/frontend/listing-form.php';
    return ob_get_clean();
}

/**
 * Listings archive
 * usage: [png_listings_archive per_page="12"]
 */
function pc_shortcode_listings_archive( $atts = [] ) {
    $atts = shortcode_atts( [ 'per_page' => 12 ], $atts, 'png_listings_archive' );
    $listings = \PremiumClassifieds\Entities\Listing::query( [
        'posts_per_page' => intval( $atts['per_page'] ),
        'post_status' => 'publish',
    ] );

    ob_start();
    include PC_PLUGIN_DIR . 'includes/Views/frontend/listings-archive.php';
    return ob_get_clean();
}

/**
 * My Listings
 */
function pc_shortcode_my_listings() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'Zaloguj się aby zarządzać ogłoszeniami.', 'premium-classifieds' ) . '</p>';
    }
    $user_id = get_current_user_id();
    $args = [
        'post_type' => \PremiumClassifieds\Entities\Listing::POST_TYPE ?? 'pc_listing',
        'author' => $user_id,
        'post_status' => 'any',
        'posts_per_page' => 50,
    ];
    $query = new WP_Query( $args );
    ob_start();
    include PC_PLUGIN_DIR . 'includes/Views/frontend/my-listings.php';
    return ob_get_clean();
}

/**
 * Premium Dashboard - simple wrapper page that loads frontend assets and shows a view
 */
function pc_shortcode_premium_dashboard() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'Zaloguj się aby zobaczyć panel.', 'premium-classifieds' ) . '</p>';
    }
    // Enqueue frontend assets (script/style)
    wp_enqueue_style( 'pc-frontend-style', PC_PLUGIN_URL . 'assets/css/pc-dashboard.css', [], PC_VERSION );
    wp_enqueue_script( 'pc-frontend-js', PC_PLUGIN_URL . 'assets/js/pc-frontend.js', ['jquery'], PC_VERSION, true );

    // localize ajax + nonce
    wp_localize_script( 'pc-frontend-js', 'pc_frontend', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'pc_frontend_nonce' ),
    ] );

    ob_start();
    include PC_PLUGIN_DIR . 'includes/Views/frontend/dashboard.php';
    return ob_get_clean();
}
