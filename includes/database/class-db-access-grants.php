<?php
/**
 * Access Grants Database Model
 *
 * Manages paid access to listing contact information.
 * Tracks which buyers have purchased access to which listings.
 * Access is lifetime by default (no expiration).
 *
 * @package    PremiumClassifieds
 * @subpackage Database
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Access Grants Database Class
 *
 * @since 2.1.0
 */
class PC_DB_Access_Grants {
    
    /**
     * Table name (without prefix)
     *
     * @var string
     */
    private string $table_name;
    
    /**
     * WordPress database object
     *
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'pc_contact_access';
    }
    
    /**
     * Grant access to a listing's contact information
     *
     * @since 2.1.0
     * @param int $buyer_id User ID purchasing access
     * @param int $listing_id Listing ID
     * @param array $args {
     *     Optional arguments
     *     @type int    $transaction_id Transaction ID from payment
     *     @type string $access_type    Access type (contact_reveal, subscription)
     *     @type string $expires_at     Expiration date (null for lifetime)
     * }
     * @return int|false Access grant ID on success, false on failure
     */
    public function grant_access(int $buyer_id, int $listing_id, array $args = []) {
        // Validate buyer exists
        $buyer = get_userdata($buyer_id);
        if (!$buyer) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PC_DB_Access_Grants: Invalid buyer ID');
            }
            return false;
        }
        
        // Validate listing exists
        $listing = get_post($listing_id);
        if (!$listing || $listing->post_type !== 'listing') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PC_DB_Access_Grants: Invalid listing ID');
            }
            return false;
        }
        
        // Check if buyer is the listing owner (they always have access)
        if ($buyer_id === (int) $listing->post_author) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PC_DB_Access_Grants: Cannot grant access to own listing');
            }
            return false;
        }
        
        // Check if access already exists
        $existing = $this->check_access($buyer_id, $listing_id);
        if ($existing) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PC_DB_Access_Grants: Access already granted');
            }
            return $this->get_access_id($buyer_id, $listing_id);
        }
        
        // Prepare data
        $data = [
            'buyer_id'       => $buyer_id,
            'listing_id'     => $listing_id,
            'transaction_id' => isset($args['transaction_id']) ? absint($args['transaction_id']) : null,
            'access_type'    => isset($args['access_type']) ? sanitize_text_field($args['access_type']) : 'contact_reveal',
            'expires_at'     => isset($args['expires_at']) ? sanitize_text_field($args['expires_at']) : null,
            'granted_at'     => current_time('mysql'),
        ];
        
        // Validate access type
        $valid_types = ['contact_reveal', 'subscription'];
        if (!in_array($data['access_type'], $valid_types)) {
            $data['access_type'] = 'contact_reveal';
        }
        
        // Insert access grant
        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            ['%d', '%d', '%d', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PC_DB_Access_Grants: Insert failed - ' . $this->wpdb->last_error);
            }
            return false;
        }
        
        // Log event
        PC_Security::log_security_event('Access granted', [
            'buyer_id'   => $buyer_id,
            'listing_id' => $listing_id,
            'type'       => $data['access_type']
        ]);
        
        // Send notification to seller
        $this->notify_seller($listing_id, $buyer_id);
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Check if user has access to listing contact info
     *
     * @since 2.1.0
     * @param int $buyer_id User ID
     * @param int $listing_id Listing ID
     * @return bool True if user has valid access, false otherwise
     */
    public function check_access(int $buyer_id, int $listing_id): bool {
        // Listing owner always has access
        $listing = get_post($listing_id);
        if ($listing && $buyer_id === (int) $listing->post_author) {
            return true;
        }
        
        // Admins always have access
        if (user_can($buyer_id, 'manage_options')) {
            return true;
        }
        
        // Check database for paid access
        $access = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE buyer_id = %d 
                 AND listing_id = %d
                 AND (expires_at IS NULL OR expires_at > NOW())",
                $buyer_id,
                $listing_id
            )
        );
        
        return $access !== null;
    }
    
    /**
     * Get access grant ID
     *
     * @since 2.1.0
     * @param int $buyer_id User ID
     * @param int $listing_id Listing ID
     * @return int|null Access ID or null if not found
     */
    public function get_access_id(int $buyer_id, int $listing_id): ?int {
        $id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_name} 
                 WHERE buyer_id = %d AND listing_id = %d",
                $buyer_id,
                $listing_id
            )
        );
        
        return $id ? (int) $id : null;
    }
    
    /**
     * Revoke access
     *
     * @since 2.1.0
     * @param int $access_id Access grant ID
     * @return bool True on success, false on failure
     */
    public function revoke_access(int $access_id): bool {
        $result = $this->wpdb->delete(
            $this->table_name,
            ['id' => $access_id],
            ['%d']
        );
        
        if ($result !== false) {
            PC_Security::log_security_event('Access revoked', [
                'access_id' => $access_id
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Revoke all access for a user to a listing
     *
     * @since 2.1.0
     * @param int $buyer_id User ID
     * @param int $listing_id Listing ID
     * @return bool True on success, false on failure
     */
    public function revoke_user_access(int $buyer_id, int $listing_id): bool {
        $result = $this->wpdb->delete(
            $this->table_name,
            [
                'buyer_id'   => $buyer_id,
                'listing_id' => $listing_id,
            ],
            ['%d', '%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get all users who have access to a listing
     *
     * @since 2.1.0
     * @param int $listing_id Listing ID
     * @return array Array of access grant objects with user data
     */
    public function get_listing_access_users(int $listing_id): array {
        $sql = "SELECT a.*, 
                       u.display_name,
                       u.user_email,
                       t.amount as payment_amount
                FROM {$this->table_name} a
                LEFT JOIN {$this->wpdb->users} u ON a.buyer_id = u.ID
                LEFT JOIN {$this->wpdb->prefix}pc_transactions t ON a.transaction_id = t.id
                WHERE a.listing_id = %d
                ORDER BY a.granted_at DESC";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $listing_id)
        );
    }
    
    /**
     * Get all listings a user has access to
     *
     * @since 2.1.0
     * @param int $buyer_id User ID
     * @param bool $active_only Only return non-expired access
     * @return array Array of access grant objects with listing data
     */
    public function get_user_access_listings(int $buyer_id, bool $active_only = true): array {
        $where = $this->wpdb->prepare('a.buyer_id = %d', $buyer_id);
        
        if ($active_only) {
            $where .= ' AND (a.expires_at IS NULL OR a.expires_at > NOW())';
        }
        
        $sql = "SELECT a.*, 
                       p.post_title as listing_title,
                       p.post_author as seller_id,
                       u.display_name as seller_name
                FROM {$this->table_name} a
                LEFT JOIN {$this->wpdb->posts} p ON a.listing_id = p.ID
                LEFT JOIN {$this->wpdb->users} u ON p.post_author = u.ID
                WHERE {$where}
                ORDER BY a.granted_at DESC";
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Count total access grants for a listing
     *
     * @since 2.1.0
     * @param int $listing_id Listing ID
     * @return int Number of users with access
     */
    public function count_listing_access(int $listing_id): int {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE listing_id = %d",
                $listing_id
            )
        );
        
        return (int) $count;
    }
    
    /**
     * Count total listings a user has access to
     *
     * @since 2.1.0
     * @param int $buyer_id User ID
     * @return int Number of listings
     */
    public function count_user_access(int $buyer_id): int {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE buyer_id = %d 
                 AND (expires_at IS NULL OR expires_at > NOW())",
                $buyer_id
            )
        );
        
        return (int) $count;
    }
    
    /**
     * Get access statistics
     *
     * @since 2.1.0
     * @param string $period Time period (today, week, month, year, all)
     * @return array {
     *     Access statistics
     *     @type int   $total_grants     Total access grants
     *     @type int   $unique_buyers    Unique buyers
     *     @type int   $unique_listings  Unique listings
     *     @type array $by_type          Breakdown by access type
     * }
     */
    public function get_statistics(string $period = 'month'): array {
        $date_query = $this->get_date_range_query($period);
        $where = $date_query ? "WHERE {$date_query}" : '';
        
        // Total grants
        $total = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} {$where}"
        );
        
        // Unique buyers
        $unique_buyers = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT buyer_id) FROM {$this->table_name} {$where}"
        );
        
        // Unique listings
        $unique_listings = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT listing_id) FROM {$this->table_name} {$where}"
        );
        
        // By type
        $by_type = $this->wpdb->get_results(
            "SELECT access_type, COUNT(*) as count 
             FROM {$this->table_name} 
             {$where}
             GROUP BY access_type"
        );
        
        return [
            'total_grants'    => (int) $total,
            'unique_buyers'   => (int) $unique_buyers,
            'unique_listings' => (int) $unique_listings,
            'by_type'         => $by_type,
        ];
    }
    
    /**
     * Expire old subscriptions
     *
     * @since 2.1.0
     * @return int Number of expired grants
     */
    public function expire_old_access(): int {
        $result = $this->wpdb->query(
            "DELETE FROM {$this->table_name} 
             WHERE expires_at IS NOT NULL 
             AND expires_at < NOW()"
        );
        
        return (int) $result;
    }
    
    /**
     * Get recent access grants (admin dashboard)
     *
     * @since 2.1.0
     * @param int $limit Number of results
     * @return array Array of access objects with user and listing data
     */
    public function get_recent_grants(int $limit = 10): array {
        $sql = "SELECT a.*, 
                       u.display_name as buyer_name,
                       p.post_title as listing_title,
                       seller.display_name as seller_name
                FROM {$this->table_name} a
                LEFT JOIN {$this->wpdb->users} u ON a.buyer_id = u.ID
                LEFT JOIN {$this->wpdb->posts} p ON a.listing_id = p.ID
                LEFT JOIN {$this->wpdb->users} seller ON p.post_author = seller.ID
                ORDER BY a.granted_at DESC
                LIMIT %d";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $limit)
        );
    }
    
    /**
     * Notify seller that someone purchased access
     *
     * @since 2.1.0
     * @param int $listing_id Listing ID
     * @param int $buyer_id Buyer user ID
     * @return void
     */
    private function notify_seller(int $listing_id, int $buyer_id): void {
        if (get_option('pc_notify_payment_received', '1') !== '1') {
            return;
        }
        
        $listing = get_post($listing_id);
        $seller = get_userdata($listing->post_author);
        $buyer = get_userdata($buyer_id);
        
        if (!$seller || !$buyer) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] Someone purchased access to your listing', 'premium-classifieds'),
            get_bloginfo('name')
        );
        
        $message = sprintf(
            __("Good news! %s has purchased access to your listing '%s'.\n\nThey can now see your contact information and send you messages.\n\nView listing: %s\nView messages: %s", 'premium-classifieds'),
            $buyer->display_name,
            $listing->post_title,
            get_permalink($listing_id),
            add_query_arg('tab', 'inbox', home_url('/dashboard/'))
        );
        
        wp_mail($seller->user_email, $subject, $message);
    }
    
    /**
     * Get date range SQL query for period
     *
     * @since 2.1.0
     * @param string $period Period identifier
     * @return string SQL WHERE clause fragment
     */
    private function get_date_range_query(string $period): string {
        switch ($period) {
            case 'today':
                return "DATE(granted_at) = CURDATE()";
                
            case 'week':
                return "granted_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                
            case 'month':
                return "granted_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                
            case 'year':
                return "granted_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                
            case 'all':
            default:
                return '';
        }
    }
    
    /**
     * Get access grant by ID
     *
     * @since 2.1.0
     * @param int $access_id Access ID
     * @return object|null Access object or null if not found
     */
    public function get_access(int $access_id): ?object {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT a.*, 
                        u.display_name as buyer_name,
                        p.post_title as listing_title
                 FROM {$this->table_name} a
                 LEFT JOIN {$this->wpdb->users} u ON a.buyer_id = u.ID
                 LEFT JOIN {$this->wpdb->posts} p ON a.listing_id = p.ID
                 WHERE a.id = %d",
                $access_id
            )
        ) ?: null;
    }
    
    /**
     * Export access grants to CSV
     *
     * @since 2.1.0
     * @return string CSV data
     */
    public function export_to_csv(): string {
        $grants = $this->wpdb->get_results(
            "SELECT a.*, 
                    u.display_name as buyer_name,
                    p.post_title as listing_title
             FROM {$this->table_name} a
             LEFT JOIN {$this->wpdb->users} u ON a.buyer_id = u.ID
             LEFT JOIN {$this->wpdb->posts} p ON a.listing_id = p.ID
             ORDER BY a.granted_at DESC"
        );
        
        $csv = "ID,Buyer ID,Buyer Name,Listing ID,Listing Title,Access Type,Granted At,Expires At\n";
        
        foreach ($grants as $grant) {
            $csv .= sprintf(
                "%d,%d,%s,%d,%s,%s,%s,%s\n",
                $grant->id,
                $grant->buyer_id,
                $grant->buyer_name,
                $grant->listing_id,
                $grant->listing_title,
                $grant->access_type,
                $grant->granted_at,
                $grant->expires_at ?? 'Never'
            );
        }
        
        return $csv;
    }
}
