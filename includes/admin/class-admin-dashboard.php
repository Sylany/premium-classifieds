<?php
/**
 * Admin Dashboard Controller
 *
 * Main dashboard for administrators showing:
 * - Revenue statistics and charts
 * - Recent transactions
 * - Top performing listings
 * - Quick stats overview
 * - Recent activities
 *
 * @package    PremiumClassifieds
 * @subpackage Admin
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Dashboard Class
 *
 * @since 2.1.0
 */
class PC_Admin_Dashboard {
    
    /**
     * Transactions database
     *
     * @var PC_DB_Transactions
     */
    private PC_DB_Transactions $transactions_db;
    
    /**
     * Access grants database
     *
     * @var PC_DB_Access_Grants
     */
    private PC_DB_Access_Grants $access_db;
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        $this->transactions_db = new PC_DB_Transactions();
        $this->access_db = new PC_DB_Access_Grants();
    }
    
    /**
     * Render dashboard page
     *
     * @since 2.1.0
     * @return void
     */
    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'premium-classifieds'));
        }
        
        // Get period from URL (default: month)
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
        
        // Get statistics
        $stats = $this->get_dashboard_stats($period);
        $revenue_stats = $this->transactions_db->get_revenue_stats($period);
        $access_stats = $this->access_db->get_statistics($period);
        
        ?>
        <div class="wrap pc-admin-dashboard">
            <h1>
                <span class="dashicons dashicons-chart-bar"></span>
                <?php esc_html_e('Premium Classifieds Dashboard', 'premium-classifieds'); ?>
            </h1>
            
            <!-- Period Selector -->
            <div class="pc-period-selector">
                <?php
                $periods = [
                    'today' => __('Today', 'premium-classifieds'),
                    'week'  => __('Last 7 Days', 'premium-classifieds'),
                    'month' => __('Last 30 Days', 'premium-classifieds'),
                    'year'  => __('Last Year', 'premium-classifieds'),
                    'all'   => __('All Time', 'premium-classifieds'),
                ];
                
                foreach ($periods as $key => $label) {
                    $active = $period === $key ? 'active' : '';
                    $url = add_query_arg(['page' => 'premium-classifieds', 'period' => $key], admin_url('admin.php'));
                    printf(
                        '<a href="%s" class="pc-period-btn %s">%s</a>',
                        esc_url($url),
                        esc_attr($active),
                        esc_html($label)
                    );
                }
                ?>
            </div>
            
            <!-- Stats Grid -->
            <div class="pc-stats-grid">
                
                <!-- Total Revenue -->
                <div class="pc-stat-card pc-stat-revenue">
                    <div class="pc-stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="pc-stat-content">
                        <h3><?php esc_html_e('Total Revenue', 'premium-classifieds'); ?></h3>
                        <p class="pc-stat-value">$<?php echo esc_html(number_format($revenue_stats['total'], 2)); ?></p>
                        <p class="pc-stat-label"><?php echo esc_html($revenue_stats['count']); ?> <?php esc_html_e('transactions', 'premium-classifieds'); ?></p>
                    </div>
                </div>
                
                <!-- Contact Reveals -->
                <div class="pc-stat-card pc-stat-access">
                    <div class="pc-stat-icon">
                        <span class="dashicons dashicons-unlock"></span>
                    </div>
                    <div class="pc-stat-content">
                        <h3><?php esc_html_e('Contact Reveals', 'premium-classifieds'); ?></h3>
                        <p class="pc-stat-value"><?php echo esc_html(number_format($access_stats['total_grants'])); ?></p>
                        <p class="pc-stat-label"><?php echo esc_html($access_stats['unique_buyers']); ?> <?php esc_html_e('unique buyers', 'premium-classifieds'); ?></p>
                    </div>
                </div>
                
                <!-- Active Listings -->
                <div class="pc-stat-card pc-stat-listings">
                    <div class="pc-stat-icon">
                        <span class="dashicons dashicons-megaphone"></span>
                    </div>
                    <div class="pc-stat-content">
                        <h3><?php esc_html_e('Active Listings', 'premium-classifieds'); ?></h3>
                        <p class="pc-stat-value"><?php echo esc_html(number_format($stats['active_listings'])); ?></p>
                        <p class="pc-stat-label"><?php echo esc_html($stats['pending_listings']); ?> <?php esc_html_e('pending approval', 'premium-classifieds'); ?></p>
                    </div>
                </div>
                
                <!-- Average Order Value -->
                <div class="pc-stat-card pc-stat-aov">
                    <div class="pc-stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="pc-stat-content">
                        <h3><?php esc_html_e('Avg. Order Value', 'premium-classifieds'); ?></h3>
                        <p class="pc-stat-value">$<?php echo esc_html(number_format($revenue_stats['average'], 2)); ?></p>
                        <p class="pc-stat-label"><?php esc_html_e('per transaction', 'premium-classifieds'); ?></p>
                    </div>
                </div>
                
            </div>
            
            <!-- Charts Row -->
            <div class="pc-charts-row">
                
                <!-- Revenue Chart -->
                <div class="pc-chart-container pc-chart-large">
                    <h2><?php esc_html_e('Revenue Over Time', 'premium-classifieds'); ?></h2>
                    <canvas id="pc-revenue-chart"></canvas>
                </div>
                
                <!-- Transaction Types -->
                <div class="pc-chart-container pc-chart-small">
                    <h2><?php esc_html_e('Transaction Types', 'premium-classifieds'); ?></h2>
                    <canvas id="pc-types-chart"></canvas>
                </div>
                
            </div>
            
            <!-- Tables Row -->
            <div class="pc-tables-row">
                
                <!-- Recent Transactions -->
                <div class="pc-table-container">
                    <h2><?php esc_html_e('Recent Transactions', 'premium-classifieds'); ?></h2>
                    <?php $this->render_recent_transactions(); ?>
                </div>
                
                <!-- Top Listings -->
                <div class="pc-table-container">
                    <h2><?php esc_html_e('Top Performing Listings', 'premium-classifieds'); ?></h2>
                    <?php $this->render_top_listings(); ?>
                </div>
                
            </div>
            
        </div>
        
        <!-- Pass data to JavaScript -->
        <script>
        var pcDashboardData = {
            revenueByDate: <?php echo wp_json_encode($revenue_stats['by_date']); ?>,
            revenueByType: <?php echo wp_json_encode($revenue_stats['by_type']); ?>,
            period: <?php echo wp_json_encode($period); ?>
        };
        </script>
        <?php
    }
    
    /**
     * Get dashboard statistics
     *
     * @since 2.1.0
     * @param string $period Time period
     * @return array Statistics data
     */
    private function get_dashboard_stats(string $period): array {
        global $wpdb;
        
        // Active listings
        $active_listings = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'listing' 
             AND post_status = 'publish'"
        );
        
        // Pending listings
        $pending_listings = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'listing' 
             AND post_status = 'pending'"
        );
        
        // Total users
        $total_users = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users}"
        );
        
        return [
            'active_listings'  => (int) $active_listings,
            'pending_listings' => (int) $pending_listings,
            'total_users'      => (int) $total_users,
        ];
    }
    
    /**
     * Render recent transactions table
     *
     * @since 2.1.0
     * @return void
     */
    private function render_recent_transactions(): void {
        $transactions = $this->transactions_db->get_recent_transactions(10);
        
        if (empty($transactions)) {
            echo '<p>' . esc_html__('No transactions yet.', 'premium-classifieds') . '</p>';
            return;
        }
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'premium-classifieds'); ?></th>
                    <th><?php esc_html_e('User', 'premium-classifieds'); ?></th>
                    <th><?php esc_html_e('Amount', 'premium-classifieds'); ?></th>
                    <th><?php esc_html_e('Type', 'premium-classifieds'); ?></th>
                    <th><?php esc_html_e('Status', 'premium-classifieds'); ?></th>
                    <th><?php esc_html_e('Date', 'premium-classifieds'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><code>#<?php echo esc_html($transaction->id); ?></code></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $transaction->user_id)); ?>">
                                <?php echo esc_html($transaction->display_name); ?>
                            </a>
                        </td>
                        <td><strong>$<?php echo esc_html(number_format($transaction->amount, 2)); ?></strong></td>
                        <td>
                            <?php
                            $type_labels = [
                                'contact_reveal' => __('Contact Reveal', 'premium-classifieds'),
                                'listing_boost'  => __('Listing Boost', 'premium-classifieds'),
                                'subscription'   => __('Subscription', 'premium-classifieds'),
                            ];
                            echo esc_html($type_labels[$transaction->type] ?? $transaction->type);
                            ?>
                        </td>
                        <td>
                            <?php
                            $status_colors = [
                                'completed' => 'green',
                                'pending'   => 'orange',
                                'failed'    => 'red',
                                'refunded'  => 'gray',
                            ];
                            $color = $status_colors[$transaction->status] ?? 'gray';
                            ?>
                            <span style="color: <?php echo esc_attr($color); ?>; font-weight: 600;">
                                <?php echo esc_html(ucfirst($transaction->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($transaction->created_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render top performing listings
     *
     * @since 2.1.0
     * @return void
     */
    private function render_top_listings(): void {
        global $wpdb;
        
        $listings = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_author,
                    COUNT(a.id) as reveals,
                    SUM(t.amount) as revenue
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->prefix}pc_contact_access a ON p.ID = a.listing_id
             LEFT JOIN {$wpdb->prefix}pc_transactions t ON a.transaction_id = t.id
             WHERE p.post_type = 'listing' 
             AND p.post_status = 'publish'
             GROUP BY p.ID
             ORDER BY reveals DESC
             LIMIT 10"
        );
        
        if (empty($listings)) {
            echo '<p>' . esc_html__('No listings yet.', 'premium-classifieds') . '</p>';
            return;
        }
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Listing', 'premium-classifieds'); ?></th>
                    <th><?php esc_html_e('Reveals', 'premium-classifieds'); ?></th>
                    <th><?php esc_html_e('Revenue', 'premium-classifieds'); ?></th>
                    <th><?php esc_html_e('Actions', 'premium-classifieds'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listings as $listing): ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url(get_permalink($listing->ID)); ?>" target="_blank">
                                <strong><?php echo esc_html($listing->post_title); ?></strong>
                            </a>
                        </td>
                        <td><strong><?php echo esc_html($listing->reveals); ?></strong></td>
                        <td>$<?php echo esc_html(number_format($listing->revenue ?? 0, 2)); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $listing->ID . '&action=edit')); ?>" class="button button-small">
                                <?php esc_html_e('Edit', 'premium-classifieds'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * AJAX: Get revenue statistics
     *
     * @since 2.1.0
     * @return void
     */
    public static function get_stats(): void {
        PC_Ajax_Handler::validate_required(['period']);
        
        $period = PC_Ajax_Handler::get_post('period', 'month', 'text');
        
        $instance = new self();
        $revenue_stats = $instance->transactions_db->get_revenue_stats($period);
        
        PC_Ajax_Handler::send_success([
            'revenue' => $revenue_stats,
        ]);
    }
}
