<?php
// File: uninstall.php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Safe uninstall:
 * Only perform destructive cleanup if option 'pc_purge_on_uninstall' === 'yes'
 */
if ( get_option( 'pc_purge_on_uninstall', 'no' ) !== 'yes' ) {
    // Remove only roles and plugin options, keep data by default
    remove_role( 'pc_seller' );
    remove_role( 'pc_buyer' );

    // Remove basic options
    $opts = [
        'pc_currency',
        'pc_price_reveal_contact',
        'pc_price_feature',
        'pc_paypal_email',
        'pc_stripe_publishable_key',
        'pc_stripe_secret_key',
        'pc_stripe_webhook_secret',
        'pc_auto_approve',
        'pc_max_listings_per_user',
    ];
    foreach ( $opts as $o ) {
        delete_option( $o );
    }

    return;
}

// Purge option is yes -> delete tables and posts
global $wpdb;

// Delete custom tables
$tables = [
    $wpdb->prefix . 'pc_transactions',
    $wpdb->prefix . 'pc_messages',
    $wpdb->prefix . 'pc_favorites',
];
foreach ( $tables as $t ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$t}" );
}

// Delete CPT posts
$args = [ 'post_type' => 'pc_listing', 'posts_per_page' => -1, 'post_status' => 'any' ];
$q = new WP_Query( $args );
if ( $q->have_posts() ) {
    foreach ( $q->posts as $p ) {
        wp_delete_post( $p->ID, true );
    }
}
wp_reset_postdata();

// Remove roles
remove_role( 'pc_seller' );
remove_role( 'pc_buyer' );

// Remove all plugin options
$all_opts = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", 'pc_%' ) );
if ( $all_opts ) {
    foreach ( $all_opts as $opt ) {
        delete_option( $opt );
    }
}
