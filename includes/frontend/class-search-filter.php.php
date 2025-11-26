<?php
/**
 * Search and Filter Handler
 *
 * Handles listing search, filtering, and sorting functionality.
 * Modifies WP_Query for archive pages based on user filters.
 *
 * @package    PremiumClassifieds
 * @subpackage Frontend
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Search Filter Class
 *
 * @since 2.1.0
 */
class PC_Search_Filter {
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        add_action('pre_get_posts', [$this, 'modify_archive_query']);
        add_action('wp_ajax_pc_filter_listings', [$this, 'ajax_filter_listings']);
        add_action('wp_ajax_nopriv_pc_filter_listings', [$this, 'ajax_filter_listings']);
    }
    
    /**
     * Modify main query on archive pages
     *
     * @since 2.1.0
     * @param WP_Query $query Main query object
     * @return void
     */
    public function modify_archive_query(\WP_Query $query): void {
        // Only modify main query on listing archive
        if (!is_admin() && $query->is_main_query() && is_post_type_archive('listing')) {
            
            // Posts per page
            $query->set('posts_per_page', get_option('pc_listings_per_page', 12));
            
            // Meta query array
            $meta_query = ['relation' => 'AND'];
            
            // Price range filter
            if (!empty($_GET['min_price'])) {
                $meta_query[] = [
                    'key' => '_pc_price',
                    'value' => floatval($_GET['min_price']),
                    'type' => 'NUMERIC',
                    'compare' => '>=',
                ];
            }
            
            if (!empty($_GET['max_price'])) {
                $meta_query[] = [
                    'key' => '_pc_price',
                    'value' => floatval($_GET['max_price']),
                    'type' => 'NUMERIC',
                    'compare' => '<=',
                ];
            }
            
            // Location filter
            if (!empty($_GET['location'])) {
                $meta_query[] = [
                    'key' => '_pc_location',
                    'value' => sanitize_text_field($_GET['location']),
                    'compare' => 'LIKE',
                ];
            }
            
            // Featured filter
            if (!empty($_GET['featured'])) {
                $meta_query[] = [
                    'key' => '_pc_featured',
                    'value' => '1',
                    'compare' => '=',
                ];
            }
            
            // Apply meta query if not empty
            if (count($meta_query) > 1) {
                $query->set('meta_query', $meta_query);
            }
            
            // Sorting
            $this->apply_sorting($query);
        }
    }
    
    /**
     * Apply sorting to query
     *
     * @since 2.1.0
     * @param WP_Query $query Query object
     * @return void
     */
    private function apply_sorting(\WP_Query $query): void {
        if (isset($_GET['orderby'])) {
            $orderby = sanitize_text_field($_GET['orderby']);
            $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';
            
            // Validate order
            if (!in_array($order, ['ASC', 'DESC'])) {
                $order = 'DESC';
            }
            
            switch ($orderby) {
                case 'price':
                case 'price-asc':
                case 'price-desc':
                    $query->set('orderby', 'meta_value_num');
                    $query->set('meta_key', '_pc_price');
                    $query->set('order', strpos($orderby, 'asc') !== false ? 'ASC' : 'DESC');
                    break;
                    
                case 'views':
                case 'views-desc':
                    $query->set('orderby', 'meta_value_num');
                    $query->set('meta_key', '_pc_views');
                    $query->set('order', 'DESC');
                    break;
                    
                case 'title':
                case 'title-asc':
                    $query->set('orderby', 'title');
                    $query->set('order', 'ASC');
                    break;
                    
                case 'date':
                case 'date-desc':
                    $query->set('orderby', 'date');
                    $query->set('order', 'DESC');
                    break;
                    
                case 'date-asc':
                    $query->set('orderby', 'date');
                    $query->set('order', 'ASC');
                    break;
                    
                default:
                    $query->set('orderby', 'date');
                    $query->set('order', 'DESC');
            }
        } else {
            // Default: Featured first, then by date
            $query->set('orderby', ['meta_value_num' => 'DESC', 'date' => 'DESC']);
            $query->set('meta_key', '_pc_featured');
        }
    }
    
    /**
     * AJAX: Filter listings
     *
     * Returns filtered listings HTML for AJAX requests
     *
     * @since 2.1.0
     * @return void
     */
    public function ajax_filter_listings(): void {
        check_ajax_referer('pc_filter_nonce', 'nonce');
        
        // Build query args
        $args = [
            'post_type' => 'listing',
            'post_status' => 'publish',
            'posts_per_page' => get_option('pc_listings_per_page', 12),
            'paged' => isset($_POST['page']) ? absint($_POST['page']) : 1,
        ];
        
        // Category filter
        if (!empty($_POST['category'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'listing_category',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_POST['category']),
                ],
            ];
        }
        
        // Meta query
        $meta_query = ['relation' => 'AND'];
        
        // Price range
        if (!empty($_POST['min_price'])) {
            $meta_query[] = [
                'key' => '_pc_price',
                'value' => floatval($_POST['min_price']),
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }
        
        if (!empty($_POST['max_price'])) {
            $meta_query[] = [
                'key' => '_pc_price',
                'value' => floatval($_POST['max_price']),
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }
        
        // Location
        if (!empty($_POST['location'])) {
            $meta_query[] = [
                'key' => '_pc_location',
                'value' => sanitize_text_field($_POST['location']),
                'compare' => 'LIKE',
            ];
        }
        
        // Featured
        if (!empty($_POST['featured'])) {
            $meta_query[] = [
                'key' => '_pc_featured',
                'value' => '1',
                'compare' => '=',
            ];
        }
        
        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }
        
        // Search
        if (!empty($_POST['search'])) {
            $args['s'] = sanitize_text_field($_POST['search']);
        }
        
        // Sorting
        if (!empty($_POST['orderby'])) {
            $orderby = sanitize_text_field($_POST['orderby']);
            
            switch ($orderby) {
                case 'price-asc':
                    $args['orderby'] = 'meta_value_num';
                    $args['meta_key'] = '_pc_price';
                    $args['order'] = 'ASC';
                    break;
                    
                case 'price-desc':
                    $args['orderby'] = 'meta_value_num';
                    $args['meta_key'] = '_pc_price';
                    $args['order'] = 'DESC';
                    break;
                    
                case 'views-desc':
                    $args['orderby'] = 'meta_value_num';
                    $args['meta_key'] = '_pc_views';
                    $args['order'] = 'DESC';
                    break;
                    
                case 'title-asc':
                    $args['orderby'] = 'title';
                    $args['order'] = 'ASC';
                    break;
                    
                case 'date-asc':
                    $args['orderby'] = 'date';
                    $args['order'] = 'ASC';
                    break;
                    
                default:
                    $args['orderby'] = 'date';
                    $args['order'] = 'DESC';
            }
        }
        
        // Execute query
        $query = new WP_Query($args);
        
        ob_start();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                include PC_TEMPLATES_DIR . 'partials/listing-card.php';
            }
            wp_reset_postdata();
            
            $html = ob_get_clean();
            
            wp_send_json_success([
                'html' => $html,
                'found' => $query->found_posts,
                'max_pages' => $query->max_num_pages,
                'current_page' => $args['paged'],
            ]);
        } else {
            ob_end_clean();
            
            wp_send_json_success([
                'html' => '<div class="pc-no-results">' . __('No listings found matching your criteria.', 'premium-classifieds') . '</div>',
                'found' => 0,
                'max_pages' => 0,
                'current_page' => 1,
            ]);
        }
    }
    
    /**
     * Get available price ranges
     *
     * @since 2.1.0
     * @return array Price ranges
     */
    public static function get_price_ranges(): array {
        global $wpdb;
        
        $min = $wpdb->get_var(
            "SELECT MIN(CAST(meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_pc_price' 
             AND meta_value != ''"
        );
        
        $max = $wpdb->get_var(
            "SELECT MAX(CAST(meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_pc_price' 
             AND meta_value != ''"
        );
        
        return [
            'min' => $min ? floatval($min) : 0,
            'max' => $max ? floatval($max) : 1000,
        ];
    }
}
