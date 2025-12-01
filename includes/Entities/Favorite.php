<?php
// File: includes/Entities/Favorite.php
namespace Premium\Classifieds\Entities;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Favorites model — wrapper around pc_favorites table
 */
class Favorite {

    /**
     * Add favorite
     *
     * @param int $user_id
     * @param int $listing_id
     * @return int|WP_Error favorite id or WP_Error
     */
    public static function add( int $user_id, int $listing_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_favorites';
        $user_id = absint( $user_id );
        $listing_id = absint( $listing_id );

        if ( $user_id <= 0 || $listing_id <= 0 ) {
            return new WP_Error( 'invalid_args', __( 'Invalid args', 'premium-classifieds' ) );
        }

        // Respect unique constraint
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d AND listing_id = %d", $user_id, $listing_id ) );
        if ( $exists ) {
            return (int) $exists;
        }

        $ok = $wpdb->insert( $table, [ 'user_id' => $user_id, 'listing_id' => $listing_id, 'created_at' => current_time( 'mysql' ) ], [ '%d', '%d', '%s' ] );
        if ( $ok === false ) {
            return new WP_Error( 'db_insert_failed', __( 'Failed to add favorite', 'premium-classifieds' ) );
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * Remove favorite
     *
     * @param int $user_id
     * @param int $listing_id
     * @return bool
     */
    public static function remove( int $user_id, int $listing_id ) : bool {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_favorites';
        $user_id = absint( $user_id );
        $listing_id = absint( $listing_id );
        if ( $user_id <= 0 || $listing_id <= 0 ) {
            return false;
        }
        $deleted = $wpdb->delete( $table, [ 'user_id' => $user_id, 'listing_id' => $listing_id ], [ '%d', '%d' ] );
        return ( $deleted !== false );
    }

    /**
     * Toggle favorite (returns true if added, false if removed)
     *
     * @param int $user_id
     * @param int $listing_id
     * @return bool
     */
    public static function toggle( int $user_id, int $listing_id ) : bool {
        $exists = self::exists( $user_id, $listing_id );
        if ( $exists ) {
            return self::remove( $user_id, $listing_id ) ? false : true;
        }
        $res = self::add( $user_id, $listing_id );
        return ! is_wp_error( $res );
    }

    /**
     * Check exists
     */
    public static function exists( int $user_id, int $listing_id ) : bool {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_favorites';
        $user_id = absint( $user_id );
        $listing_id = absint( $listing_id );
        if ( $user_id <= 0 || $listing_id <= 0 ) {
            return false;
        }
        $res = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$table} WHERE user_id = %d AND listing_id = %d", $user_id, $listing_id ) );
        return (int) $res > 0;
    }

    /**
     * Get user's favorites
     *
     * @param int $user_id
     * @param int $limit
     * @return array listing ids
     */
    public static function get_user_favorites( int $user_id, int $limit = 100 ) : array {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_favorites';
        $user_id = absint( $user_id );
        $limit = absint( $limit );
        if ( $user_id <= 0 ) {
            return [];
        }
        $rows = $wpdb->get_col( $wpdb->prepare( "SELECT listing_id FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d", $user_id, $limit ) );
        return $rows ?: [];
    }
}
