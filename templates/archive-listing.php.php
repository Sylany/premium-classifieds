<?php
/**
 * Archive Listing Template
 *
 * Main template for displaying listings archive with filters and pagination.
 * Shows listing grid with category filters, search, and sorting options.
 *
 * @package    PremiumClassifieds
 * @subpackage Templates
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Get current query
global $wp_query;

// Get filter parameters
$current_category = get_query_var('listing_category');
$search_query = get_query_var('s');
$orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
$order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

// Get categories for filter
$categories = get_terms([
    'taxonomy' => 'listing_category',
    'hide_empty' => true,
]);

// Build filter URL
$base_url = get_post_type_archive_link('listing');
?>

<div class="pc-archive-wrapper">
    <div class="pc-archive-container">
        
        <!-- Archive Header -->
        <div class="pc-archive-header">
            <div class="pc-header-content">
                <h1 class="pc-archive-title">
                    <?php
                    if ($search_query) {
                        printf(
                            esc_html__('Search Results for: %s', 'premium-classifieds'),
                            '<span>' . esc_html($search_query) . '</span>'
                        );
                    } elseif ($current_category) {
                        $term = get_term_by('slug', $current_category, 'listing_category');
                        if ($term) {
                            echo esc_html($term->name);
                        }
                    } else {
                        esc_html_e('Browse Listings', 'premium-classifieds');
                    }
                    ?>
                </h1>
                
                <div class="pc-results-count">
                    <?php
                    printf(
                        esc_html(_n('%s listing found', '%s listings found', $wp_query->found_posts, 'premium-classifieds')),
                        '<strong>' . number_format_i18n($wp_query->found_posts) . '</strong>'
                    );
                    ?>
                </div>
            </div>
            
            <!-- Add Listing Button -->
            <?php if (is_user_logged_in() && current_user_can('create_listings')): ?>
                <a href="<?php echo esc_url(home_url('/dashboard/?tab=add-listing')); ?>" class="pc-btn pc-btn-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Add Listing', 'premium-classifieds'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Breadcrumbs -->
        <nav class="pc-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'premium-classifieds'); ?></a>
            <span class="pc-breadcrumb-separator">/</span>
            <?php if ($current_category && isset($term)): ?>
                <a href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('Listings', 'premium-classifieds'); ?></a>
                <span class="pc-breadcrumb-separator">/</span>
                <span class="pc-breadcrumb-current"><?php echo esc_html($term->name); ?></span>
            <?php else: ?>
                <span class="pc-breadcrumb-current"><?php esc_html_e('Listings', 'premium-classifieds'); ?></span>
            <?php endif; ?>
        </nav>

        <div class="pc-archive-grid">
            
            <!-- Sidebar Filters -->
            <aside class="pc-archive-sidebar">
                <?php include PC_TEMPLATES_DIR . 'partials/search-filters.php'; ?>
            </aside>

            <!-- Main Content -->
            <main class="pc-archive-main">
                
                <!-- Toolbar -->
                <div class="pc-archive-toolbar">
                    <div class="pc-view-mode">
                        <button class="pc-view-btn active" data-view="grid" title="<?php esc_attr_e('Grid View', 'premium-classifieds'); ?>">
                            <span class="dashicons dashicons-grid-view"></span>
                        </button>
                        <button class="pc-view-btn" data-view="list" title="<?php esc_attr_e('List View', 'premium-classifieds'); ?>">
                            <span class="dashicons dashicons-list-view"></span>
                        </button>
                    </div>

                    <div class="pc-sort-options">
                        <label for="pc-sort-select"><?php esc_html_e('Sort by:', 'premium-classifieds'); ?></label>
                        <select id="pc-sort-select" class="pc-select">
                            <option value="date-desc" <?php selected($orderby === 'date' && $order === 'DESC'); ?>>
                                <?php esc_html_e('Newest First', 'premium-classifieds'); ?>
                            </option>
                            <option value="date-asc" <?php selected($orderby === 'date' && $order === 'ASC'); ?>>
                                <?php esc_html_e('Oldest First', 'premium-classifieds'); ?>
                            </option>
                            <option value="price-asc" <?php selected($orderby === 'meta_value_num' && $order === 'ASC'); ?>>
                                <?php esc_html_e('Price: Low to High', 'premium-classifieds'); ?>
                            </option>
                            <option value="price-desc" <?php selected($orderby === 'meta_value_num' && $order === 'DESC'); ?>>
                                <?php esc_html_e('Price: High to Low', 'premium-classifieds'); ?>
                            </option>
                            <option value="title-asc" <?php selected($orderby === 'title' && $order === 'ASC'); ?>>
                                <?php esc_html_e('Title: A to Z', 'premium-classifieds'); ?>
                            </option>
                            <option value="views-desc" <?php selected($orderby === 'meta_value_num' && $order === 'DESC'); ?>>
                                <?php esc_html_e('Most Viewed', 'premium-classifieds'); ?>
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Active Filters -->
                <?php
                $active_filters = [];
                
                if ($current_category && isset($term)) {
                    $active_filters[] = [
                        'label' => $term->name,
                        'url' => $base_url,
                    ];
                }
                
                if (isset($_GET['min_price']) && !empty($_GET['min_price'])) {
                    $active_filters[] = [
                        'label' => sprintf(__('Min: $%s', 'premium-classifieds'), sanitize_text_field($_GET['min_price'])),
                        'url' => remove_query_arg('min_price'),
                    ];
                }
                
                if (isset($_GET['max_price']) && !empty($_GET['max_price'])) {
                    $active_filters[] = [
                        'label' => sprintf(__('Max: $%s', 'premium-classifieds'), sanitize_text_field($_GET['max_price'])),
                        'url' => remove_query_arg('max_price'),
                    ];
                }
                
                if (isset($_GET['location']) && !empty($_GET['location'])) {
                    $active_filters[] = [
                        'label' => sanitize_text_field($_GET['location']),
                        'url' => remove_query_arg('location'),
                    ];
                }
                
                if (!empty($active_filters)):
                ?>
                    <div class="pc-active-filters">
                        <span class="pc-filters-label"><?php esc_html_e('Active Filters:', 'premium-classifieds'); ?></span>
                        <?php foreach ($active_filters as $filter): ?>
                            <a href="<?php echo esc_url($filter['url']); ?>" class="pc-filter-tag">
                                <?php echo esc_html($filter['label']); ?>
                                <span class="dashicons dashicons-no-alt"></span>
                            </a>
                        <?php endforeach; ?>
                        <a href="<?php echo esc_url($base_url); ?>" class="pc-clear-filters">
                            <?php esc_html_e('Clear All', 'premium-classifieds'); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Listings Grid -->
                <?php if (have_posts()): ?>
                    <div class="pc-listings-grid" data-view="grid">
                        <?php while (have_posts()): the_post(); ?>
                            <?php include PC_TEMPLATES_DIR . 'partials/listing-card.php'; ?>
                        <?php endwhile; ?>
                    </div>

                    <!-- Pagination -->
                    <nav class="pc-pagination">
                        <?php
                        echo paginate_links([
                            'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                            'format' => '?paged=%#%',
                            'current' => max(1, get_query_var('paged')),
                            'total' => $wp_query->max_num_pages,
                            'prev_text' => '<span class="dashicons dashicons-arrow-left-alt2"></span> ' . __('Previous', 'premium-classifieds'),
                            'next_text' => __('Next', 'premium-classifieds') . ' <span class="dashicons dashicons-arrow-right-alt2"></span>',
                            'type' => 'list',
                            'mid_size' => 2,
                        ]);
                        ?>
                    </nav>

                <?php else: ?>
                    
                    <!-- Empty State -->
                    <div class="pc-empty-state">
                        <div class="pc-empty-icon">
                            <span class="dashicons dashicons-search"></span>
                        </div>
                        <h3><?php esc_html_e('No listings found', 'premium-classifieds'); ?></h3>
                        <p>
                            <?php 
                            if (!empty($active_filters)) {
                                esc_html_e('Try adjusting your filters or search terms.', 'premium-classifieds');
                            } else {
                                esc_html_e('Be the first to create a listing!', 'premium-classifieds');
                            }
                            ?>
                        </p>
                        
                        <?php if (!empty($active_filters)): ?>
                            <a href="<?php echo esc_url($base_url); ?>" class="pc-btn pc-btn-primary">
                                <?php esc_html_e('Clear Filters', 'premium-classifieds'); ?>
                            </a>
                        <?php elseif (is_user_logged_in() && current_user_can('create_listings')): ?>
                            <a href="<?php echo esc_url(home_url('/dashboard/?tab=add-listing')); ?>" class="pc-btn pc-btn-primary">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php esc_html_e('Add Listing', 'premium-classifieds'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>

            </main>

        </div>

    </div>
</div>

<?php get_footer(); ?>
