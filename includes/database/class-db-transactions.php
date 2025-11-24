<?php
/**
 * Transactions Database Model
 *
 * Handles all database operations for payment transactions.
 * Logs Stripe payments, manages transaction history, and calculates revenue.
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
 * Transactions Database Class
 *
 * @since 2.1.0
 */
class PC_DB_Transactions {
    
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
        $this->table_name = $wpdb->prefix . 'pc_transactions';
    }
    
    /**
     * Log a payment transaction
     *
     * @since 2.1.0
     * @param array $args {
     *     Transaction data
     *     @type int    $user_id           User ID making the payment
     *     @type int    $listing_id        Listing ID (optional for subscriptions)
     *     @type float  $amount            Payment amount
     *     @type string $currency          Currency code (default: USD)
     *     @type string $type              Transaction type (contact_reveal, listing_boost, subscription)
     *     @type string $stripe_payment_id Stripe payment intent ID
     *     @type string $stripe_customer_id Stripe customer ID
     *     @type string $status            Payment status (pending, completed, refunded, failed)
     *     @type array  $metadata          Additional metadata (optional)
     * }
     * @return int|false Transaction ID on success, false on failure
     */
    public function log_payment(array $args) {
        // Validate required fields
        $required = ['user_id', 'amount', 'type'];
        foreach ($required as $field) {
            if (empty($args[$field])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("PC_DB_Transactions: Missing required field '{$field}'");
                }
                return false;
            }
        }
        
        // Prepare data
        $data = [
            'user_id'             => absint($args['user_id']),
            'listing_id'          => isset($args['listing_id']) ? absint($args['listing_id']) : null,
            'amount'              => number_format((float) $args['amount'], 2, '.', ''),
            'currency'            => isset($args['currency']) ? strtoupper(sanitize_text_field($args['currency'])) : 'USD',
            'type'                => sanitize_text_field($args['type']),
            'stripe_payment_id'   => isset($args['stripe_payment_id']) ? sanitize_text_field($args['stripe_payment_id']) : null,
            'stripe_customer_id'  => isset($args['stripe_customer_id']) ? sanitize_text_field($args['stripe_customer_id']) : null,
            'status'              => isset($args['status']) ? sanitize_text_field($args['status']) : 'pending',
            'metadata'            => isset($args['metadata']) ? wp_json_encode($args['metadata']) : null,
            'created_at'          => current_time('mysql'),
        ];
        
        // Validate transaction type
        $valid_types = ['contact_reveal', 'listing_boost', 'subscription'];
        if (!in_array($data['type'], $valid_types)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("PC_DB_Transactions: Invalid transaction type '{$data['type']}'");
            }
            return false;
        }
        
        // Validate status
        $valid_statuses = ['pending', 'completed', 'refunded', 'failed'];
        if (!in_array($data['status'], $valid_statuses)) {
            $data['status'] = 'pending';
        }
        
        // Insert transaction
        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            [
                '%d', // user_id
                '%d', // listing_id
                '%s', // amount
                '%s', // currency
                '%s', // type
                '%s', // stripe_payment_id
                '%s', // stripe_customer_id
                '%s', // status
                '%s', // metadata
                '%s', // created_at
            ]
        );
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PC_DB_Transactions: Insert failed - ' . $this->wpdb->last_error);
            }
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get transaction by ID
     *
     * @since 2.1.0
     * @param int $transaction_id Transaction ID
     * @return object|null Transaction object or null if not found
     */
    public function get_transaction(int $transaction_id): ?object {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $transaction_id
            )
        );
        
        if ($result && !empty($result->metadata)) {
            $result->metadata = json_decode($result->metadata, true);
        }
        
        return $result ?: null;
    }
    
    /**
     * Get transaction by Stripe payment ID
     *
     * @since 2.1.0
     * @param string $stripe_payment_id Stripe payment intent ID
     * @return object|null Transaction object or null if not found
     */
    public function get_by_stripe_id(string $stripe_payment_id): ?object {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE stripe_payment_id = %s",
                $stripe_payment_id
            )
        );
        
        if ($result && !empty($result->metadata)) {
            $result->metadata = json_decode($result->metadata, true);
        }
        
        return $result ?: null;
    }
    
    /**
     * Get user's transaction history
     *
     * @since 2.1.0
     * @param int $user_id User ID
     * @param array $args {
     *     Optional query arguments
     *     @type string $type      Transaction type filter
     *     @type string $status    Status filter
     *     @type int    $limit     Number of results (default: 20)
     *     @type int    $offset    Query offset (default: 0)
     *     @type string $order_by  Order by field (default: created_at)
     *     @type string $order     Sort direction (ASC|DESC, default: DESC)
     * }
     * @return array Array of transaction objects
     */
    public function get_user_transactions(int $user_id, array $args = []): array {
        $defaults = [
            'type'     => '',
            'status'   => '',
            'limit'    => 20,
            'offset'   => 0,
            'order_by' => 'created_at',
            'order'    => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = $this->wpdb->prepare('user_id = %d', $user_id);
        
        if (!empty($args['type'])) {
            $where .= $this->wpdb->prepare(' AND type = %s', $args['type']);
        }
        
        if (!empty($args['status'])) {
            $where .= $this->wpdb->prepare(' AND status = %s', $args['status']);
        }
        
        // Validate order
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Validate order_by (prevent SQL injection)
        $valid_order_fields = ['id', 'created_at', 'amount', 'type', 'status'];
        $order_by = in_array($args['order_by'], $valid_order_fields) ? $args['order_by'] : 'created_at';
        
        $sql = "SELECT * FROM {$this->table_name} 
                WHERE {$where} 
                ORDER BY {$order_by} {$order} 
                LIMIT %d OFFSET %d";
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $args['limit'], $args['offset'])
        );
        
        // Decode metadata for each transaction
        foreach ($results as $transaction) {
            if (!empty($transaction->metadata)) {
                $transaction->metadata = json_decode($transaction->metadata, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get revenue statistics
     *
     * @since 2.1.0
     * @param string $period Time period (today, week, month, year, all)
     * @param string $type Transaction type filter (optional)
     * @return array {
     *     Revenue statistics
     *     @type float $total       Total revenue
     *     @type int   $count       Number of transactions
     *     @type float $average     Average transaction amount
     *     @type array $by_type     Breakdown by transaction type
     *     @type array $by_date     Daily revenue breakdown
     * }
     */
    public function get_revenue_stats(string $period = 'month', string $type = ''): array {
        // Determine date range
        $date_query = $this->get_date_range_query($period);
        
        // Build WHERE clause
        $where = "status = 'completed'";
        
        if (!empty($date_query)) {
            $where .= " AND {$date_query}";
        }
        
        if (!empty($type)) {
            $where .= $this->wpdb->prepare(' AND type = %s', $type);
        }
        
        // Get totals
        $totals = $this->wpdb->get_row(
            "SELECT 
                SUM(amount) as total,
                COUNT(*) as count,
                AVG(amount) as average
             FROM {$this->table_name}
             WHERE {$where}"
        );
        
        // Get breakdown by type
        $by_type = $this->wpdb->get_results(
            "SELECT 
                type,
                SUM(amount) as total,
                COUNT(*) as count
             FROM {$this->table_name}
             WHERE {$where}
             GROUP BY type
             ORDER BY total DESC"
        );
        
        // Get daily breakdown
        $by_date = $this->wpdb->get_results(
            "SELECT 
                DATE(created_at) as date,
                SUM(amount) as total,
                COUNT(*) as count
             FROM {$this->table_name}
             WHERE {$where}
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
        
        return [
            'total'   => (float) ($totals->total ?? 0),
            'count'   => (int) ($totals->count ?? 0),
            'average' => (float) ($totals->average ?? 0),
            'by_type' => $by_type,
            'by_date' => $by_date,
        ];
    }
    
    /**
     * Update transaction status
     *
     * @since 2.1.0
     * @param int $transaction_id Transaction ID
     * @param string $status New status (pending, completed, refunded, failed)
     * @return bool True on success, false on failure
     */
    public function update_status(int $transaction_id, string $status): bool {
        $valid_statuses = ['pending', 'completed', 'refunded', 'failed'];
        
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->table_name,
            [
                'status'     => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $transaction_id],
            ['%s', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get total revenue for a user's listings
     *
     * @since 2.1.0
     * @param int $user_id User ID
     * @return float Total revenue
     */
    public function get_user_earnings(int $user_id): float {
        // Get all listings by this user
        $listings = get_posts([
            'post_type'      => 'listing',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        
        if (empty($listings)) {
            return 0.0;
        }
        
        $listing_ids = implode(',', array_map('absint', $listings));
        
        $total = $this->wpdb->get_var(
            "SELECT SUM(amount) 
             FROM {$this->table_name} 
             WHERE listing_id IN ({$listing_ids}) 
             AND status = 'completed'"
        );
        
        return (float) ($total ?? 0);
    }
    
    /**
     * Get recent transactions (admin dashboard)
     *
     * @since 2.1.0
     * @param int $limit Number of transactions to retrieve
     * @return array Array of transaction objects with user data
     */
    public function get_recent_transactions(int $limit = 10): array {
        $sql = "SELECT t.*, u.display_name, u.user_email
                FROM {$this->table_name} t
                LEFT JOIN {$this->wpdb->users} u ON t.user_id = u.ID
                ORDER BY t.created_at DESC
                LIMIT %d";
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $limit)
        );
        
        foreach ($results as $transaction) {
            if (!empty($transaction->metadata)) {
                $transaction->metadata = json_decode($transaction->metadata, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Delete old transactions (cleanup)
     *
     * @since 2.1.0
     * @param int $days Delete transactions older than X days
     * @return int Number of deleted rows
     */
    public function cleanup_old_transactions(int $days = 365): int {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} 
                 WHERE created_at < %s 
                 AND status IN ('failed', 'refunded')",
                $date
            )
        );
        
        return (int) $result;
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
                return "DATE(created_at) = CURDATE()";
                
            case 'week':
                return "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                
            case 'month':
                return "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                
            case 'year':
                return "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                
            case 'all':
            default:
                return '';
        }
    }
    
    /**
     * Get count of transactions by status
     *
     * @since 2.1.0
     * @return array Associative array of status => count
     */
    public function get_status_counts(): array {
        $results = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM {$this->table_name} 
             GROUP BY status"
        );
        
        $counts = [
            'pending'   => 0,
            'completed' => 0,
            'refunded'  => 0,
            'failed'    => 0,
        ];
        
        foreach ($results as $row) {
            $counts[$row->status] = (int) $row->count;
        }
        
        return $counts;
    }
    
    /**
     * Export transactions to CSV
     *
     * @since 2.1.0
     * @param array $args Query arguments (same as get_user_transactions)
     * @return string CSV data
     */
    public function export_to_csv(array $args = []): string {
        $transactions = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC"
        );
        
        $csv = "ID,User ID,Listing ID,Amount,Currency,Type,Status,Stripe Payment ID,Created At\n";
        
        foreach ($transactions as $t) {
            $csv .= sprintf(
                "%d,%d,%d,%s,%s,%s,%s,%s,%s\n",
                $t->id,
                $t->user_id,
                $t->listing_id ?? 0,
                $t->amount,
                $t->currency,
                $t->type,
                $t->status,
                $t->stripe_payment_id ?? '',
                $t->created_at
            );
        }
        
        return $csv;
    }
}
