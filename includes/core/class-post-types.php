<?php
/**
 * Custom Post Types Registration
 *
 * Registers the 'listing' post type with all necessary configurations
 * for the Premium Classifieds platform.
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
 * Post Types Registration Class
 *
 * @since 2.1.0
 */
class PC_Post_Types {
    
    /**
     * Post type slug
     *
     * @var string
     */
    private const POST_TYPE = 'listing';
    
    /**
     * Constructor - Register hooks
     *
     * @since 2.1.0
     */
    public function __construct() {
        add_action('init', [$this, 'register_post_types'], 5);
        add_action('init', [$this, 'register_post_statuses'], 6);
        add_filter('post_updated_messages', [$this, 'updated_messages']);
        add_filter('bulk_post_updated_messages', [$this, 'bulk_updated_messages'], 10, 2);
        add_action('admin_head', [$this, 'add_post_type_icon_styles']);
        add_filter('enter_title_here', [$this, 'change_title_placeholder'], 10, 2);
        add_filter('manage_listing_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_listing_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_filter('manage_edit-listing_sortable_columns', [$this, 'sortable_columns']);
    }
    
    /**
     * Register the listing post type
     *
     * @since 2.1.0
     * @return void
     */
    public function register_post_types(): void {
        $labels = [
            'name'                  => _x('Listings', 'Post Type General Name', 'premium-classifieds'),
            'singular_name'         => _x('Listing', 'Post Type Singular Name', 'premium-classifieds'),
            'menu_name'             => __('Listings', 'premium-classifieds'),
            'name_admin_bar'        => __('Listing', 'premium-classifieds'),
            'archives'              => __('Listing Archives', 'premium-classifieds'),
            'attributes'            => __('Listing Attributes', 'premium-classifieds'),
            'parent_item_colon'     => __('Parent Listing:', 'premium-classifieds'),
            'all_items'             => __('All Listings', 'premium-classifieds'),
            'add_new_item'          => __('Add New Listing', 'premium-classifieds'),
            'add_new'               => __('Add New', 'premium-classifieds'),
            'new_item'              => __('New Listing', 'premium-classifieds'),
            'edit_item'             => __('Edit Listing', 'premium-classifieds'),
            'update_item'           => __('Update Listing', 'premium-classifieds'),
            'view_item'             => __('View Listing', 'premium-classifieds'),
            'view_items'            => __('View Listings', 'premium-classifieds'),
            'search_items'          => __('Search Listings', 'premium-classifieds'),
            'not_found'             => __('No listings found', 'premium-classifieds'),
            'not_found_in_trash'    => __('No listings found in Trash', 'premium-classifieds'),
            'featured_image'        => __('Featured Image', 'premium-classifieds'),
            'set_featured_image'    => __('Set featured image', 'premium-classifieds'),
            'remove_featured_image' => __('Remove featured image', 'premium-classifieds'),
            'use_featured_image'    => __('Use as featured image', 'premium-classifieds'),
            'insert_into_item'      => __('Insert into listing', 'premium-classifieds'),
            'uploaded_to_this_item' => __('Uploaded to this listing', 'premium-classifieds'),
            'items_list'            => __('Listings list', 'premium-classifieds'),
            'items_list_navigation' => __('Listings list navigation', 'premium-classifieds'),
            'filter_items_list'     => __('Filter listings list', 'premium-classifieds'),
        ];
        
        $args = [
            'label'               => __('Listing', 'premium-classifieds'),
            'description'         => __('Premium classified listings', 'premium-classifieds'),
            'labels'              => $labels,
            'supports'            => ['title', 'editor', 'thumbnail', 'author', 'excerpt', 'comments'],
            'taxonomies'          => ['listing_category', 'listing_tag'],
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 20,
            'menu_icon'           => 'dashicons-megaphone',
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'listing',
            'map_meta_cap'        => true,
            'show_in_rest'        => true, // Enable Gutenberg editor
            'rewrite'             => [
                'slug'       => 'ogloszenia',
                'with_front' => false,
                'feeds'      => true,
            ],
        ];
        
        register_post_type(self::POST_TYPE, $args);
    }
    
    /**
     * Register custom post statuses
     *
     * @since 2.1.0
     * @return void
     */
    public function register_post_statuses(): void {
        // Expired status for listings past their expiration date
        register_post_status('expired', [
            'label'                     => _x('Expired', 'post status', 'premium-classifieds'),
            'public'                    => true,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Expired <span class="count">(%s)</span>',
                'Expired <span class="count">(%s)</span>',
                'premium-classifieds'
            ),
        ]);
    }
    
    /**
     * Customize post updated messages
     *
     * @since 2.1.0
     * @param array $messages Existing messages
     * @return array Modified messages
     */
    public function updated_messages(array $messages): array {
        global $post;
        
        $permalink = get_permalink($post);
        $preview_url = add_query_arg('preview', 'true', $permalink);
        
        $messages[self::POST_TYPE] = [
            0  => '', // Unused. Messages start at index 1.
            1  => sprintf(
                __('Listing updated. <a href="%s">View listing</a>', 'premium-classifieds'),
                esc_url($permalink)
            ),
            2  => __('Custom field updated.', 'premium-classifieds'),
            3  => __('Custom field deleted.', 'premium-classifieds'),
            4  => __('Listing updated.', 'premium-classifieds'),
            5  => isset($_GET['revision']) ? sprintf(
                __('Listing restored to revision from %s', 'premium-classifieds'),
                wp_post_revision_title((int) $_GET['revision'], false)
            ) : false,
            6  => sprintf(
                __('Listing published. <a href="%s">View listing</a>', 'premium-classifieds'),
                esc_url($permalink)
            ),
            7  => __('Listing saved.', 'premium-classifieds'),
            8  => sprintf(
                __('Listing submitted. <a target="_blank" href="%s">Preview listing</a>', 'premium-classifieds'),
                esc_url($preview_url)
            ),
            9  => sprintf(
                __('Listing scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview listing</a>', 'premium-classifieds'),
                date_i18n(__('M j, Y @ G:i', 'premium-classifieds'), strtotime($post->post_date)),
                esc_url($permalink)
            ),
            10 => sprintf(
                __('Listing draft updated. <a target="_blank" href="%s">Preview listing</a>', 'premium-classifieds'),
                esc_url($preview_url)
            ),
        ];
        
        return $messages;
    }
    
    /**
     * Customize bulk action messages
     *
     * @since 2.1.0
     * @param array $bulk_messages Arrays of messages
     * @param array $bulk_counts Array of item counts
     * @return array Modified messages
     */
    public function bulk_updated_messages(array $bulk_messages, array $bulk_counts): array {
        $bulk_messages[self::POST_TYPE] = [
            'updated'   => _n(
                '%s listing updated.',
                '%s listings updated.',
                $bulk_counts['updated'],
                'premium-classifieds'
            ),
            'locked'    => _n(
                '%s listing not updated, somebody is editing it.',
                '%s listings not updated, somebody is editing them.',
                $bulk_counts['locked'],
                'premium-classifieds'
            ),
            'deleted'   => _n(
                '%s listing permanently deleted.',
                '%s listings permanently deleted.',
                $bulk_counts['deleted'],
                'premium-classifieds'
            ),
            'trashed'   => _n(
                '%s listing moved to the Trash.',
                '%s listings moved to the Trash.',
                $bulk_counts['trashed'],
                'premium-classifieds'
            ),
            'untrashed' => _n(
                '%s listing restored from the Trash.',
                '%s listings restored from the Trash.',
                $bulk_counts['untrashed'],
                'premium-classifieds'
            ),
        ];
        
        return $bulk_messages;
    }
    
    /**
     * Add custom icon styles for post type
     *
     * @since 2.1.0
     * @return void
     */
    public function add_post_type_icon_styles(): void {
        global $post_type;
        
        if (self::POST_TYPE !== $post_type) {
            return;
        }
        
        ?>
        <style>
            #icon-edit.icon32-posts-listing {
                background: transparent url('<?php echo esc_url(admin_url('images/generic.png')); ?>') no-repeat;
            }
        </style>
        <?php
    }
    
    /**
     * Change title placeholder text
     *
     * @since 2.1.0
     * @param string $title Current placeholder
     * @param WP_Post $post Post object
     * @return string Modified placeholder
     */
    public function change_title_placeholder(string $title, \WP_Post $post): string {
        if (self::POST_TYPE === $post->post_type) {
            $title = __('Enter listing title (e.g., "Professional Web Developer Available")', 'premium-classifieds');
        }
        
        return $title;
    }
    
    /**
     * Add custom columns to admin list table
     *
     * @since 2.1.0
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_custom_columns(array $columns): array {
        // Remove date column temporarily
        $date = $columns['date'];
        unset($columns['date']);
        
        // Add custom columns
        $columns['thumbnail'] = __('Image', 'premium-classifieds');
        $columns['listing_category'] = __('Category', 'premium-classifieds');
        $columns['price'] = __('Price', 'premium-classifieds');
        $columns['views'] = __('Views', 'premium-classifieds');
        $columns['featured'] = '<span class="dashicons dashicons-star-filled" title="' . esc_attr__('Featured', 'premium-classifieds') . '"></span>';
        $columns['expires'] = __('Expires', 'premium-classifieds');
        
        // Re-add date column at the end
        $columns['date'] = $date;
        
        return $columns;
    }
    
    /**
     * Render custom column content
     *
     * @since 2.1.0
     * @param string $column Column name
     * @param int $post_id Post ID
     * @return void
     */
    public function render_custom_columns(string $column, int $post_id): void {
        switch ($column) {
            case 'thumbnail':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, [50, 50]);
                } else {
                    echo '<span class="dashicons dashicons-format-image" style="font-size: 50px; color: #ddd;"></span>';
                }
                break;
                
            case 'listing_category':
                $terms = get_the_terms($post_id, 'listing_category');
                if ($terms && !is_wp_error($terms)) {
                    $term_links = [];
                    foreach ($terms as $term) {
                        $term_links[] = sprintf(
                            '<a href="%s">%s</a>',
                            esc_url(add_query_arg(['listing_category' => $term->slug], 'edit.php?post_type=listing')),
                            esc_html($term->name)
                        );
                    }
                    echo implode(', ', $term_links);
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;
                
            case 'price':
                $price = get_post_meta($post_id, '_pc_price', true);
                if ($price) {
                    echo '<strong>$' . esc_html($price) . '</strong>';
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;
                
            case 'views':
                $views = get_post_meta($post_id, '_pc_views', true) ?: 0;
                echo '<span class="dashicons dashicons-visibility"></span> ' . esc_html($views);
                break;
                
            case 'featured':
                $featured = get_post_meta($post_id, '_pc_featured', true);
                if ($featured) {
                    echo '<span class="dashicons dashicons-star-filled" style="color: #f59e0b;" title="' . esc_attr__('Featured', 'premium-classifieds') . '"></span>';
                } else {
                    echo '<span style="color: #ddd;">—</span>';
                }
                break;
                
            case 'expires':
                $expires = get_post_meta($post_id, '_pc_expires_at', true);
                if ($expires) {
                    $timestamp = strtotime($expires);
                    $now = current_time('timestamp');
                    
                    if ($timestamp < $now) {
                        echo '<span style="color: #dc2626;">' . esc_html__('Expired', 'premium-classifieds') . '</span>';
                    } else {
                        $days_left = ceil(($timestamp - $now) / DAY_IN_SECONDS);
                        if ($days_left <= 7) {
                            echo '<span style="color: #f59e0b;">' . sprintf(esc_html__('%d days', 'premium-classifieds'), $days_left) . '</span>';
                        } else {
                            echo sprintf(esc_html__('%d days', 'premium-classifieds'), $days_left);
                        }
                    }
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;
        }
    }
    
    /**
     * Make custom columns sortable
     *
     * @since 2.1.0
     * @param array $columns Sortable columns
     * @return array Modified columns
     */
    public function sortable_columns(array $columns): array {
        $columns['price'] = 'price';
        $columns['views'] = 'views';
        $columns['featured'] = 'featured';
        $columns['expires'] = 'expires';
        
        return $columns;
    }
    
    /**
     * Get post type slug
     *
     * @since 2.1.0
     * @return string Post type slug
     */
    public static function get_post_type(): string {
        return self::POST_TYPE;
    }
}
