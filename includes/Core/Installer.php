<?php
// File: includes/Core/Installer.php
namespace Premium\Classifieds\Core;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Entities\Transaction;

class Installer {

    /**
     * Activation hook: create DB tables, roles, default options, pages.
     */
    public static function activate(): void {
        self::create_tables();
        self::create_roles();
        self::create_default_options();
        self::create_pages();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        // keep data by default; clear rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create custom DB tables used by plugin
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [];

        $tables['pc_transactions'] = "CREATE TABLE {$wpdb->prefix}pc_transactions (
            id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) unsigned NOT NULL DEFAULT 0,
            listing_id BIGINT(20) unsigned DEFAULT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) DEFAULT 'PLN',
            provider VARCHAR(50) DEFAULT '',
            provider_id VARCHAR(255) DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            meta LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY listing_id (listing_id),
            KEY provider (provider)
        ) $charset_collate;";

        $tables['pc_messages'] = "CREATE TABLE {$wpdb->prefix}pc_messages (
            id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
            from_user BIGINT(20) unsigned NOT NULL,
            to_user BIGINT(20) unsigned NOT NULL,
            listing_id BIGINT(20) unsigned DEFAULT NULL,
            body LONGTEXT NOT NULL,
            is_contact_revealed TINYINT(1) DEFAULT 0,
            is_paid TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY from_user (from_user),
            KEY to_user (to_user),
            KEY listing_id (listing_id)
        ) $charset_collate;";

        $tables['pc_favorites'] = "CREATE TABLE {$wpdb->prefix}pc_favorites (
            id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) unsigned NOT NULL,
            listing_id BIGINT(20) unsigned NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_listing (user_id, listing_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $tables as $name => $ddl ) {
            dbDelta( $ddl );
        }

        // Log creation
        Helpers::log( 'pc tables created/updated' );
    }

    /**
     * Create plugin-specific roles
     */
    private static function create_roles(): void {
        // pc_seller: can create listings
        add_role( 'pc_seller', 'PC Seller', [
            'read' => true,
            'edit_posts' => true,
            'publish_posts' => true,
            'upload_files' => true,
        ] );

        // pc_buyer: basic reader
        add_role( 'pc_buyer', 'PC Buyer', [
            'read' => true,
        ] );
    }

    /**
     * Default options
     */
    private static function create_default_options(): void {
        $defaults = [
            'pc_currency' => 'PLN',
            'pc_price_reveal_contact' => 19.00,
            'pc_price_feature' => 9.00,
            'pc_paypal_email' => '',
            'pc_stripe_publishable_key' => '',
            'pc_stripe_secret_key' => '',
            'pc_stripe_webhook_secret' => '',
            'pc_auto_approve' => 'yes',
            'pc_max_listings_per_user' => 5,
            'pc_max_images_per_listing' => 5,
            'pc_listing_expiry_days' => 90,
        ];
        foreach ( $defaults as $k => $v ) {
            if ( get_option( $k ) === false ) {
                update_option( $k, $v );
            }
        }
    }

    /**
     * Create necessary frontend pages with shortcodes if not exist
     */
    private static function create_pages(): void {
        $pages = [
            'znajdz-towarzysza' => [
                'post_title' => 'Znajdź towarzysza',
                'post_content' => '[png_listings_archive]',
            ],
            'dodaj-ogloszenie' => [
                'post_title' => 'Dodaj ogłoszenie',
                'post_content' => '[png_listing_form]',
            ],
            'moje-ogloszenia' => [
                'post_title' => 'Moje ogłoszenia',
                'post_content' => '[premium_dashboard]',
            ],
            'wiadomosci' => [
                'post_title' => 'Wiadomości',
                'post_content' => '[png_messages]',
            ],
            'platnosc' => [
                'post_title' => 'Płatność',
                'post_content' => '[png_payment_checkout]',
            ],
            'ulubione' => [
                'post_title' => 'Ulubione',
                'post_content' => '[png_favorites]',
            ],
        ];

        foreach ( $pages as $slug => $args ) {
            $existing = get_page_by_path( $slug );
            if ( ! $existing ) {
                wp_insert_post( [
                    'post_title' => $args['post_title'],
                    'post_content' => $args['post_content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug,
                ] );
            }
        }
    }
}
