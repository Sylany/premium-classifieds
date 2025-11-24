<?php
/**
 * Custom Taxonomies Registration
 *
 * Registers taxonomies for the listing post type:
 * - listing_category (hierarchical, like categories)
 * - listing_tag (non-hierarchical, like tags)
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
 * Taxonomies Registration Class
 *
 * @since 2.1.0
 */
class PC_Taxonomies {
    
    /**
     * Category taxonomy slug
     *
     * @var string
     */
    private const CATEGORY_TAX = 'listing_category';
    
    /**
     * Tag taxonomy slug
     *
     * @var string
     */
    private const TAG_TAX = 'listing_tag';
    
    /**
     * Constructor - Register hooks
     *
     * @since 2.1.0
     */
    public function __construct() {
        add_action('init', [$this, 'register_taxonomies'], 6);
        add_action('listing_category_add_form_fields', [$this, 'add_category_icon_field']);
        add_action('listing_category_edit_form_fields', [$this, 'edit_category_icon_field'], 10, 2);
        add_action('created_listing_category', [$this, 'save_category_icon'], 10, 2);
        add_action('edited_listing_category', [$this, 'save_category_icon'], 10, 2);
        add_filter('manage_edit-listing_category_columns', [$this, 'add_category_columns']);
        add_filter('manage_listing_category_custom_column', [$this, 'render_category_columns'], 10, 3);
    }
    
    /**
     * Register custom taxonomies
     *
     * @since 2.1.0
     * @return void
     */
    public function register_taxonomies(): void {
        $this->register_category_taxonomy();
        $this->register_tag_taxonomy();
    }
    
    /**
     * Register category taxonomy (hierarchical)
     *
     * @since 2.1.0
     * @return void
     */
    private function register_category_taxonomy(): void {
        $labels = [
            'name'                       => _x('Categories', 'Taxonomy General Name', 'premium-classifieds'),
            'singular_name'              => _x('Category', 'Taxonomy Singular Name', 'premium-classifieds'),
            'menu_name'                  => __('Categories', 'premium-classifieds'),
            'all_items'                  => __('All Categories', 'premium-classifieds'),
            'parent_item'                => __('Parent Category', 'premium-classifieds'),
            'parent_item_colon'          => __('Parent Category:', 'premium-classifieds'),
            'new_item_name'              => __('New Category Name', 'premium-classifieds'),
            'add_new_item'               => __('Add New Category', 'premium-classifieds'),
            'edit_item'                  => __('Edit Category', 'premium-classifieds'),
            'update_item'                => __('Update Category', 'premium-classifieds'),
            'view_item'                  => __('View Category', 'premium-classifieds'),
            'separate_items_with_commas' => __('Separate categories with commas', 'premium-classifieds'),
            'add_or_remove_items'        => __('Add or remove categories', 'premium-classifieds'),
            'choose_from_most_used'      => __('Choose from the most used', 'premium-classifieds'),
            'popular_items'              => __('Popular Categories', 'premium-classifieds'),
            'search_items'               => __('Search Categories', 'premium-classifieds'),
            'not_found'                  => __('No categories found', 'premium-classifieds'),
            'no_terms'                   => __('No categories', 'premium-classifieds'),
            'items_list'                 => __('Categories list', 'premium-classifieds'),
            'items_list_navigation'      => __('Categories list navigation', 'premium-classifieds'),
        ];
        
        $args = [
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug'         => 'kategoria',
                'with_front'   => false,
                'hierarchical' => true,
            ],
            'capabilities'      => [
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'create_listings',
            ],
        ];
        
        register_taxonomy(self::CATEGORY_TAX, ['listing'], $args);
        
        // Create default categories on first activation
        $this->create_default_categories();
    }
    
    /**
     * Register tag taxonomy (non-hierarchical)
     *
     * @since 2.1.0
     * @return void
     */
    private function register_tag_taxonomy(): void {
        $labels = [
            'name'                       => _x('Tags', 'Taxonomy General Name', 'premium-classifieds'),
            'singular_name'              => _x('Tag', 'Taxonomy Singular Name', 'premium-classifieds'),
            'menu_name'                  => __('Tags', 'premium-classifieds'),
            'all_items'                  => __('All Tags', 'premium-classifieds'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'new_item_name'              => __('New Tag Name', 'premium-classifieds'),
            'add_new_item'               => __('Add New Tag', 'premium-classifieds'),
            'edit_item'                  => __('Edit Tag', 'premium-classifieds'),
            'update_item'                => __('Update Tag', 'premium-classifieds'),
            'view_item'                  => __('View Tag', 'premium-classifieds'),
            'separate_items_with_commas' => __('Separate tags with commas', 'premium-classifieds'),
            'add_or_remove_items'        => __('Add or remove tags', 'premium-classifieds'),
            'choose_from_most_used'      => __('Choose from the most used', 'premium-classifieds'),
            'popular_items'              => __('Popular Tags', 'premium-classifieds'),
            'search_items'               => __('Search Tags', 'premium-classifieds'),
            'not_found'                  => __('No tags found', 'premium-classifieds'),
            'no_terms'                   => __('No tags', 'premium-classifieds'),
            'items_list'                 => __('Tags list', 'premium-classifieds'),
            'items_list_navigation'      => __('Tags list navigation', 'premium-classifieds'),
        ];
        
        $args = [
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug'       => 'tag',
                'with_front' => false,
            ],
            'capabilities'      => [
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'create_listings',
            ],
        ];
        
        register_taxonomy(self::TAG_TAX, ['listing'], $args);
    }
    
    /**
     * Create default categories on plugin activation
     *
     * @since 2.1.0
     * @return void
     */
    private function create_default_categories(): void {
        // Only create if no categories exist
        $existing = get_terms([
            'taxonomy'   => self::CATEGORY_TAX,
            'hide_empty' => false,
            'number'     => 1,
        ]);
        
        if (!empty($existing)) {
            return;
        }
        
        // Default categories based on RentAFriend-like platform
        $default_categories = [
            [
                'name'        => __('Friendship & Companionship', 'premium-classifieds'),
                'slug'        => 'friendship-companionship',
                'description' => __('Find friends for activities and social events', 'premium-classifieds'),
            ],
            [
                'name'        => __('Tour Guides & Travel', 'premium-classifieds'),
                'slug'        => 'tour-guides-travel',
                'description' => __('Local guides and travel companions', 'premium-classifieds'),
            ],
            [
                'name'        => __('Events & Activities', 'premium-classifieds'),
                'slug'        => 'events-activities',
                'description' => __('Companions for concerts, sports, and events', 'premium-classifieds'),
            ],
            [
                'name'        => __('Language Exchange', 'premium-classifieds'),
                'slug'        => 'language-exchange',
                'description' => __('Practice languages with native speakers', 'premium-classifieds'),
            ],
            [
                'name'        => __('Professional Services', 'premium-classifieds'),
                'slug'        => 'professional-services',
                'description' => __('Consulting, coaching, and expertise', 'premium-classifieds'),
            ],
            [
                'name'        => __('Hobbies & Interests', 'premium-classifieds'),
                'slug'        => 'hobbies-interests',
                'description' => __('Connect over shared hobbies and interests', 'premium-classifieds'),
            ],
        ];
        
        foreach ($default_categories as $category) {
            if (!term_exists($category['slug'], self::CATEGORY_TAX)) {
                wp_insert_term(
                    $category['name'],
                    self::CATEGORY_TAX,
                    [
                        'slug'        => $category['slug'],
                        'description' => $category['description'],
                    ]
                );
            }
        }
    }
    
    /**
     * Add custom icon field to category add form
     *
     * @since 2.1.0
     * @param string $taxonomy Current taxonomy slug
     * @return void
     */
    public function add_category_icon_field(string $taxonomy): void {
        ?>
        <div class="form-field term-icon-wrap">
            <label for="category-icon"><?php esc_html_e('Icon (Dashicon)', 'premium-classifieds'); ?></label>
            <input type="text" 
                   name="category_icon" 
                   id="category-icon" 
                   value="" 
                   placeholder="dashicons-heart">
            <p class="description">
                <?php esc_html_e('Enter a Dashicon class (e.g., dashicons-heart). View available icons at:', 'premium-classifieds'); ?>
                <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">Dashicons</a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Add custom icon field to category edit form
     *
     * @since 2.1.0
     * @param WP_Term $term Current term object
     * @param string $taxonomy Current taxonomy slug
     * @return void
     */
    public function edit_category_icon_field(\WP_Term $term, string $taxonomy): void {
        $icon = get_term_meta($term->term_id, 'category_icon', true);
        ?>
        <tr class="form-field term-icon-wrap">
            <th scope="row">
                <label for="category-icon"><?php esc_html_e('Icon (Dashicon)', 'premium-classifieds'); ?></label>
            </th>
            <td>
                <input type="text" 
                       name="category_icon" 
                       id="category-icon" 
                       value="<?php echo esc_attr($icon); ?>" 
                       placeholder="dashicons-heart">
                <p class="description">
                    <?php esc_html_e('Enter a Dashicon class (e.g., dashicons-heart). View available icons at:', 'premium-classifieds'); ?>
                    <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">Dashicons</a>
                </p>
                <?php if ($icon): ?>
                    <p>
                        <span class="dashicons <?php echo esc_attr($icon); ?>" style="font-size: 32px;"></span>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Save category icon meta
     *
     * @since 2.1.0
     * @param int $term_id Term ID
     * @param int $tt_id Term taxonomy ID
     * @return void
     */
    public function save_category_icon(int $term_id, int $tt_id): void {
        if (isset($_POST['category_icon'])) {
            $icon = sanitize_text_field($_POST['category_icon']);
            update_term_meta($term_id, 'category_icon', $icon);
        }
    }
    
    /**
     * Add custom columns to category admin table
     *
     * @since 2.1.0
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_category_columns(array $columns): array {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            if ($key === 'description') {
                $new_columns['icon'] = __('Icon', 'premium-classifieds');
            }
            $new_columns[$key] = $value;
        }
        
        return $new_columns;
    }
    
    /**
     * Render custom column content for categories
     *
     * @since 2.1.0
     * @param string $content Column content
     * @param string $column_name Column name
     * @param int $term_id Term ID
     * @return string Modified content
     */
    public function render_category_columns(string $content, string $column_name, int $term_id): string {
        if ($column_name === 'icon') {
            $icon = get_term_meta($term_id, 'category_icon', true);
            if ($icon) {
                $content = '<span class="dashicons ' . esc_attr($icon) . '" style="font-size: 24px;"></span>';
            } else {
                $content = '<span style="color: #ddd;">—</span>';
            }
        }
        
        return $content;
    }
    
    /**
     * Get category taxonomy slug
     *
     * @since 2.1.0
     * @return string Category taxonomy slug
     */
    public static function get_category_taxonomy(): string {
        return self::CATEGORY_TAX;
    }
    
    /**
     * Get tag taxonomy slug
     *
     * @since 2.1.0
     * @return string Tag taxonomy slug
     */
    public static function get_tag_taxonomy(): string {
        return self::TAG_TAX;
    }
}
