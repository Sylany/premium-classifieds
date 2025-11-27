<?php
/**
 * Plugin Activation Handler
 *
 * Handles all operations required when the plugin is activated:
 * - Creates custom database tables
 * - Sets default options
 * - Flushes rewrite rules
 * - Creates custom user roles
 *
 * @package    PremiumClassifieds
 * @subpackage Core
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activator Class
 *
 * @since 2.1.0
 */
class PC_Activator {
    
    /**
     * Activate the plugin
     *
     * This method is called once when the plugin is activated.
     * It sets up the database schema and initial configuration.
     *
     * @since 2.1.0
     * @return void
     */
    public static function activate(): void {
        global $wpdb;
        
        // Store plugin version for migration tracking
        update_option('pc_version', PC_VERSION);
        
        // Create custom database tables
        self::create_transactions_table();
        self::create_messages_table();
        self::create_access_grants_table();
        
        // Set default options if not already set
        self::set_default_options();
        
        // Create custom user role
        self::create_custom_roles();
        
        // Schedule cron jobs
        self::schedule_cron_jobs();
        
        // Register post types and taxonomies (needed for flush_rewrite_rules)
        self::register_post_types_temp();
        
        // Flush rewrite rules to ensure permalinks work
        flush_rewrite_rules();
        
        // Log activation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Premium Classifieds v' . PC_VERSION . ' activated successfully');
        }
    }
    
    /**
     * Create transactions table
     *
     * Stores all payment records from Stripe
     *
     * @since 2.1.0
     * @return void
     */
    private static function create_transactions_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pc_transactions';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            listing_id BIGINT UNSIGNED NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
            type ENUM('contact_reveal', 'listing_boost', 'subscription') NOT NULL,
            stripe_payment_id VARCHAR(255) UNIQUE,
            stripe_customer_id VARCHAR(255),
            status ENUM('pending', 'completed', 'refunded', 'failed') DEFAULT 'pending',
            metadata TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX user_idx (user_id),
            INDEX listing_idx (listing_id),
            INDEX status_idx (status),
            INDEX created_idx (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create messages table
     *
     * Internal messaging system between buyers and sellers
     *
     * @since 2.1.0
     * @return void
     */
    private static function create_messages_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pc_messages';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_user_id BIGINT UNSIGNED NOT NULL,
            to_user_id BIGINT UNSIGNED NOT NULL,
            listing_id BIGINT UNSIGNED NOT NULL,
            subject VARCHAR(255) DEFAULT '',
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL,
            INDEX from_idx (from_user_id),
            INDEX to_idx (to_user_id),
            INDEX listing_idx (listing_id),
            INDEX read_idx (is_read),
            INDEX created_idx (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create contact access grants table
     *
     * Tracks which users have paid to access which listings
     *
     * @since 2.1.0
     * @return void
     */
    private static function create_access_grants_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pc_contact_access';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            buyer_id BIGINT UNSIGNED NOT NULL,
            listing_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED,
            granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            access_type ENUM('contact_reveal', 'subscription') DEFAULT 'contact_reveal',
            UNIQUE KEY unique_access (buyer_id, listing_id),
            INDEX buyer_idx (buyer_id),
            INDEX listing_idx (listing_id),
            INDEX expires_idx (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     *
     * Only sets options if they don't already exist (respects existing settings)
     *
     * @since 2.1.0
     * @return void
     */
    private static function set_default_options(): void {
        $defaults = [
            // Stripe Settings (Test mode by default)
            'pc_stripe_mode' => 'test',
            'pc_stripe_test_public_key' => '',
            'pc_stripe_test_secret_key' => '',
            'pc_stripe_live_public_key' => '',
            'pc_stripe_live_secret_key' => '',
            'pc_stripe_webhook_secret' => '',
            
            // Pricing Settings (as per requirements)
            'pc_price_contact_reveal' => '5.00',
            'pc_price_listing_boost' => '10.00',
            'pc_price_subscription' => '49.00',
            'pc_currency' => 'USD',
            
            // Feature Flags
            'pc_enable_messaging' => '1',
            'pc_enable_favorites' => '1',
            'pc_enable_subscriptions' => '1',
            'pc_require_approval' => '1', // Moderate listings before publish
            
            // Email Notifications
            'pc_notify_new_listing' => '1',
            'pc_notify_new_message' => '1',
            'pc_notify_payment_received' => '1',
            'pc_notify_boost_activated' => '1',
            'pc_notify_refunds' => '1',
            'pc_admin_email' => get_option('admin_email'),
            
            // Display Settings
            'pc_listings_per_page' => '12',
            'pc_max_images_per_listing' => '10',
            'pc_listing_duration_days' => '30', // Auto-expire after 30 days
            
            // Security
            'pc_enable_recaptcha' => '0',
            'pc_recaptcha_site_key' => '',
            'pc_recaptcha_secret_key' => '',
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Create custom user roles
     *
     * Creates "Listing Creator" role with specific capabilities
     *
     * @since 2.1.0
     * @return void
     */
    private static function create_custom_roles(): void {
        // Remove role if it exists (for clean reinstall)
        remove_role('listing_creator');
        
        // Add custom role with capabilities
        add_role(
            'listing_creator',
            __('Listing Creator', 'premium-classifieds'),
            [
                'read' => true,
                'upload_files' => true,
                'edit_posts' => false, // No access to regular posts
                'delete_posts' => false,
                
                // Custom capabilities for listings
                'create_listings' => true,
                'edit_listings' => true,
                'edit_published_listings' => true,
                'delete_listings' => true,
                'delete_published_listings' => true,
                'publish_listings' => true, // Can publish (subject to moderation setting)
            ]
        );
        
        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('create_listings');
            $admin_role->add_cap('edit_listings');
            $admin_role->add_cap('edit_others_listings');
            $admin_role->add_cap('edit_published_listings');
            $admin_role->add_cap('delete_listings');
            $admin_role->add_cap('delete_others_listings');
            $admin_role->add_cap('delete_published_listings');
            $admin_role->add_cap('publish_listings');
            $admin_role->add_cap('moderate_listings');
        }
    }
    
    /**
     * Schedule cron jobs for plugin
     *
     * @since 2.1.0
     * @return void
     */
    private static function schedule_cron_jobs(): void {
        // Schedule expired boosts check
        if (!wp_next_scheduled('pc_check_expired_boosts')) {
            wp_schedule_event(time(), 'daily', 'pc_check_expired_boosts');
        }
        
        // Schedule expired listings check
        if (!wp_next_scheduled('pc_check_expired_listings')) {
            wp_schedule_event(time(), 'daily', 'pc_check_expired_listings');
        }
        
        // Schedule expired access grants check
        if (!wp_next_scheduled('pc_check_expired_access')) {
            wp_schedule_event(time(), 'daily', 'pc_check_expired_access');
        }
    }
    
    /**
     * Temporarily register post types for rewrite flush
     *
     * This is needed because flush_rewrite_rules() requires post types
     * to be registered first. We do a minimal registration here.
     *
     * @since 2.1.0
     * @return void
     */
    private static function register_post_types_temp(): void {
        register_post_type('listing', [
            'public' => true,
            'rewrite' => ['slug' => 'listings'],
        ]);
        
        register_taxonomy('listing_category', 'listing', [
            'public' => true,
            'rewrite' => ['slug' => 'category'],
        ]);
    }
}