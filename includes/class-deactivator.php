<?php
/**
 * Plugin Deactivation Handler
 *
 * Handles cleanup operations when the plugin is deactivated.
 * NOTE: This does NOT delete user data or database tables.
 * Use uninstall.php for complete removal.
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
 * Deactivator Class
 *
 * @since 2.1.0
 */
class PC_Deactivator {
    
    /**
     * Deactivate the plugin
     *
     * Performs cleanup that's safe to do on deactivation without
     * destroying user data. Database tables are preserved.
     *
     * @since 2.1.0
     * @return void
     */
    public static function deactivate(): void {
        // Flush rewrite rules to remove custom permalinks
        flush_rewrite_rules();
        
        // Clear scheduled cron jobs
        self::clear_scheduled_events();
        
        // Clear transient cache
        self::clear_transients();
        
        // Log deactivation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Premium Classifieds v' . PC_VERSION . ' deactivated');
        }
    }
    
    /**
     * Clear all scheduled WP-Cron events
     *
     * @since 2.1.0
     * @return void
     */
    private static function clear_scheduled_events(): void {
        // Clear listing expiration cron
        $timestamp = wp_next_scheduled('pc_expire_listings');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'pc_expire_listings');
        }
        
        // Clear subscription renewal cron
        $timestamp = wp_next_scheduled('pc_process_subscriptions');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'pc_process_subscriptions');
        }
        
        // Clear cleanup cron
        $timestamp = wp_next_scheduled('pc_cleanup_old_data');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'pc_cleanup_old_data');
        }
    }
    
    /**
     * Clear all plugin transients
     *
     * Removes cached data to prevent stale information
     *
     * @since 2.1.0
     * @return void
     */
    private static function clear_transients(): void {
        global $wpdb;
        
        // Delete all transients with our prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_pc_%' 
             OR option_name LIKE '_transient_timeout_pc_%'"
        );
    }
}
