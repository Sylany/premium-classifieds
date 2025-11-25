<?php
/**
 * Data Exporter
 *
 * Exports plugin data to CSV/JSON formats for backup or analysis.
 * Exports:
 * - Listings
 * - Transactions
 * - Users
 * - Access Grants
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
 * Data Exporter Class
 *
 * @since 2.1.0
 */
class PC_Data_Exporter {
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_export_page'], 100);
    }
    
    /**
     * Add export submenu page
     *
     * @since 2.1.0
     * @return void
     */
    public function add_export_page(): void {
        add_submenu_page(
            'premium-classifieds',
            __('Export Data', 'premium-classifieds'),
            __('Export Data', 'premium-classifieds'),
            'manage_options',
            'pc-export-data',
            [$this, 'render_export_page']
        );
    }
    
    /**
     * Render export page
     *
     * @since 2.1.0
     * @return void
     */
    public function render_export_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'premium-classifieds'));
        }
        ?>
        
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export Data', 'premium-classifieds'); ?>
            </h1>
            
            <p class="description">
                <?php esc_html_e('Export plugin data for backup or analysis. All exports include timestamps and are ready for Excel or data analysis tools.', 'premium-classifieds'); ?>
            </p>
            
            <div class="pc-export-grid">
                
                <!-- Export Listings -->
                <div class="pc-export-card">
                    <div class="pc-export-icon">
                        <span class="dashicons dashicons-megaphone"></span>
                    </div>
                    <h2><?php esc_html_e('Listings', 'premium-classifieds'); ?></h2>
                    <p><?php esc_html_e('Export all listings with metadata, categories, and custom fields.', 'premium-classifieds'); ?></p>
                    <div class="pc-export-actions">
                        <button class="button button-primary pc-export-btn" data-type="listings" data-format="csv">
                            <?php esc_html_e('Export CSV', 'premium-classifieds'); ?>
                        </button>
                        <button class="button button-secondary pc-export-btn" data-type="listings" data-format="json">
                            <?php esc_html_e('Export JSON', 'premium-classifieds'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Export Transactions -->
                <div class="pc-export-card">
                    <div class="pc-export-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <h2><?php esc_html_e('Transactions', 'premium-classifieds'); ?></h2>
                    <p><?php esc_html_e('Export payment history with user details and Stripe IDs.', 'premium-classifieds'); ?></p>
                    <div class="pc-export-actions">
                        <button class="button button-primary pc-export-btn" data-type="transactions" data-format="csv">
                            <?php esc_html_e('Export CSV', 'premium-classifieds'); ?>
                        </button>
                        <button class="button button-secondary pc-export-btn" data-type="transactions" data-format="json">
                            <?php esc_html_e('Export JSON', 'premium-classifieds'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Export Access Grants -->
                <div class="pc-export-card">
                    <div class="pc-export-icon">
                        <span class="dashicons dashicons-unlock"></span>
                    </div>
                    <h2><?php esc_html_e('Access Grants', 'premium-classifieds'); ?></h2>
                    <p><?php esc_html_e('Export contact reveal data showing who has access to which listings.', 'premium-classifieds'); ?></p>
                    <div class="pc-export-actions">
                        <button class="button button-primary pc-export-btn" data-type="access" data-format="csv">
                            <?php esc_html_e('Export CSV', 'premium-classifieds'); ?>
                        </button>
                        <button class="button button-secondary pc-export-btn" data-type="access" data-format="json">
                            <?php esc_html_e('Export JSON', 'premium-classifieds'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Export Users -->
                <div class="pc-export-card">
                    <div class="pc-export-icon">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <h2><?php esc_html_e('Users', 'premium-classifieds'); ?></h2>
                    <p><?php esc_html_e('Export user data including listing creators and their activity.', 'premium-classifieds'); ?></p>
                    <div class="pc-export-actions">
                        <button class="button button-primary pc-export-btn" data-type="users" data-format="csv">
                            <?php esc_html_e('Export CSV', 'premium-classifieds'); ?>
                        </button>
                        <button class="button button-secondary pc-export-btn" data-type="users" data-format="json">
                            <?php esc_html_e('Export JSON', 'premium-classifieds'); ?>
                        </button>
                    </div>
                </div>
                
            </div>
        </div>
        
        <style>
        .pc-export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .pc-export-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .pc-export-icon {
            width: 60px;
            height: 60px;
            background: #2271b1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        .pc-export-icon .dashicons {
            color: #fff;
            font-size: 32px;
            width: 32px;
            height: 32px;
        }
        .pc-export-card h2 {
            margin: 0 0 10px;
            font-size: 18px;
        }
        .pc-export-card p {
            color: #646970;
            margin-bottom: 15px;
        }
        .pc-export-actions {
            display: flex;
            gap: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Export data
     *
     * @since 2.1.0
     * @return void
     */
    public static function export(): void {
        PC_Ajax_Handler::validate_required(['type', 'format']);
        
        $type = PC_Ajax_Handler::get_post('type', '', 'text');
        $format = PC_Ajax_Handler::get_post('format', 'csv', 'text');
        
        $instance = new self();
        
        switch ($type) {
            case 'listings':
                $data = $instance->export_listings();
                break;
            case 'transactions':
                $data = $instance->export_transactions();
                break;
            case 'access':
                $data = $instance->export_access_grants();
                break;
            case 'users':
                $data = $instance->export_users();
                break;
            default:
                PC_Ajax_Handler::send_error(__('Invalid export type.', 'premium-classifieds'), 400);
        }
        
        if ($format === 'csv') {
            $csv = $instance->array_to_csv($data);
            $filename = 'pc_' . $type . '_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $csv;
            exit;
        } else {
            $filename = 'pc_' . $type . '_' . date('Y-m-d_H-i-s') . '.json';
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo wp_json_encode($data, JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    /**
     * Export listings
     *
     * @since 2.1.0
     * @return array Listings data
     */
    private function export_listings(): array {
        $args = [
            'post_type'      => 'listing',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ];
        
        $listings = get_posts($args);
        $data = [];
        
        foreach ($listings as $listing) {
            $categories = wp_get_post_terms($listing->ID, 'listing_category', ['fields' => 'names']);
            $tags = wp_get_post_terms($listing->ID, 'listing_tag', ['fields' => 'names']);
            
            $data[] = [
                'ID'           => $listing->ID,
                'Title'        => $listing->post_title,
                'Status'       => $listing->post_status,
                'Author'       => get_the_author_meta('display_name', $listing->post_author),
                'Categories'   => implode(', ', $categories),
                'Tags'         => implode(', ', $tags),
                'Price'        => get_post_meta($listing->ID, '_pc_price', true),
                'Location'     => get_post_meta($listing->ID, '_pc_location', true),
                'Phone'        => get_post_meta($listing->ID, '_pc_phone', true),
                'Email'        => get_post_meta($listing->ID, '_pc_email', true),
                'Views'        => get_post_meta($listing->ID, '_pc_views', true),
                'Featured'     => get_post_meta($listing->ID, '_pc_featured', true) ? 'Yes' : 'No',
                'Created'      => $listing->post_date,
                'Modified'     => $listing->post_modified,
            ];
        }
        
        return $data;
    }
    
    /**
     * Export transactions
     *
     * @since 2.1.0
     * @return array Transactions data
     */
    private function export_transactions(): array {
        $transactions_db = new PC_DB_Transactions();
        return $transactions_db->export_to_csv();
    }
    
    /**
     * Export access grants
     *
     * @since 2.1.0
     * @return array Access grants data
     */
    private function export_access_grants(): array {
        $access_db = new PC_DB_Access_Grants();
        return $access_db->export_to_csv();
    }
    
    /**
     * Export users
     *
     * @since 2.1.0
     * @return array Users data
     */
    private function export_users(): array {
        $users = get_users(['role' => 'listing_creator']);
        $data = [];
        
        foreach ($users as $user) {
            $listing_count = count_user_posts($user->ID, 'listing');
            
            $data[] = [
                'ID'            => $user->ID,
                'Username'      => $user->user_login,
                'Display Name'  => $user->display_name,
                'Email'         => $user->user_email,
                'Role'          => implode(', ', $user->roles),
                'Listings'      => $listing_count,
                'Registered'    => $user->user_registered,
            ];
        }
        
        return $data;
    }
    
    /**
     * Convert array to CSV string
     *
     * @since 2.1.0
     * @param array $data Data array
     * @return string CSV string
     */
    private function array_to_csv(array $data): string {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($output, array_keys($data[0]));
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}
