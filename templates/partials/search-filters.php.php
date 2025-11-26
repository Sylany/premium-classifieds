<?php
/**
 * Search Filters Sidebar
 *
 * Filter sidebar for archive page with category, price, location filters.
 *
 * @package    PremiumClassifieds
 * @subpackage Templates/Partials
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current filter values
$current_category = get_query_var('listing_category');
$min_price = isset($_GET['min_price']) ? sanitize_text_field($_GET['min_price']) : '';
$max_price = isset($_GET['max_price']) ? sanitize_text_field($_GET['max_price']) : '';
$location = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '';
$search = get_query_var('s');

// Get all categories
$categories = get_terms([
    'taxonomy' => 'listing_category',
    'hide_empty' => true,
]);

// Get unique locations from listings
global $wpdb;
$locations = $wpdb->get_col(
    "SELECT DISTINCT meta_value 
     FROM {$wpdb->postmeta} 
     WHERE meta_key = '_pc_location' 
     AND meta_value != '' 
     ORDER BY meta_value ASC
     LIMIT 50"
);
?>

<div class="pc-filters-sidebar">
    
    <!-- Search Box -->
    <div class="pc-filter-section">
        <h3 class="pc-filter-title">
            <span class="dashicons dashicons-search"></span>
            <?php esc_html_e('Search', 'premium-classifieds'); ?>
        </h3>
        <form method="get" action="<?php echo esc_url(get_post_type_archive_link('listing')); ?>" class="pc-search-form">
            <div class="pc-search-input-group">
                <input type="text" 
                       name="s" 
                       class="pc-search-input" 
                       placeholder="<?php esc_attr_e('Search listings...', 'premium-classifieds'); ?>"
                       value="<?php echo esc_attr($search); ?>">
                <button type="submit" class="pc-search-btn">
                    <span class="dashicons dashicons-search"></span>
                </button>
            </div>
        </form>
    </div>

    <!-- Category Filter -->
    <?php if (!empty($categories)): ?>
        <div class="pc-filter-section">
            <h3 class="pc-filter-title">
                <span class="dashicons dashicons-category"></span>
                <?php esc_html_e('Categories', 'premium-classifieds'); ?>
            </h3>
            <ul class="pc-category-list">
                <li>
                    <a href="<?php echo esc_url(get_post_type_archive_link('listing')); ?>" 
                       class="pc-category-link <?php echo empty($current_category) ? 'active' : ''; ?>">
                        <?php esc_html_e('All Categories', 'premium-classifieds'); ?>
                        <span class="pc-category-count"><?php echo wp_count_posts('listing')->publish; ?></span>
                    </a>
                </li>
                <?php foreach ($categories as $category): 
                    $icon = get_term_meta($category->term_id, 'category_icon', true);
                    $is_active = $current_category === $category->slug;
                ?>
                    <li>
                        <a href="<?php echo esc_url(get_term_link($category)); ?>" 
                           class="pc-category-link <?php echo $is_active ? 'active' : ''; ?>">
                            <?php if ($icon): ?>
                                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                            <?php endif; ?>
                            <?php echo esc_html($category->name); ?>
                            <span class="pc-category-count"><?php echo esc_html($category->count); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Price Range Filter -->
    <div class="pc-filter-section">
        <h3 class="pc-filter-title">
            <span class="dashicons dashicons-money-alt"></span>
            <?php esc_html_e('Price Range', 'premium-classifieds'); ?>
        </h3>
        <form id="pc-price-filter" class="pc-filter-form">
            <div class="pc-price-inputs">
                <div class="pc-input-group">
                    <span class="pc-input-prefix">$</span>
                    <input type="number" 
                           name="min_price" 
                           class="pc-price-input" 
                           placeholder="<?php esc_attr_e('Min', 'premium-classifieds'); ?>"
                           value="<?php echo esc_attr($min_price); ?>"
                           min="0"
                           step="1">
                </div>
                <span class="pc-price-separator">-</span>
                <div class="pc-input-group">
                    <span class="pc-input-prefix">$</span>
                    <input type="number" 
                           name="max_price" 
                           class="pc-price-input" 
                           placeholder="<?php esc_attr_e('Max', 'premium-classifieds'); ?>"
                           value="<?php echo esc_attr($max_price); ?>"
                           min="0"
                           step="1">
                </div>
            </div>
            <button type="submit" class="pc-btn pc-btn-secondary pc-btn-block">
                <?php esc_html_e('Apply', 'premium-classifieds'); ?>
            </button>
        </form>
    </div>

    <!-- Location Filter -->
    <?php if (!empty($locations)): ?>
        <div class="pc-filter-section">
            <h3 class="pc-filter-title">
                <span class="dashicons dashicons-location"></span>
                <?php esc_html_e('Location', 'premium-classifieds'); ?>
            </h3>
            <select id="pc-location-filter" class="pc-select">
                <option value=""><?php esc_html_e('All Locations', 'premium-classifieds'); ?></option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo esc_attr($loc); ?>" <?php selected($location, $loc); ?>>
                        <?php echo esc_html($loc); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <!-- Featured Listings -->
    <div class="pc-filter-section pc-featured-box">
        <h3 class="pc-filter-title">
            <span class="dashicons dashicons-star-filled"></span>
            <?php esc_html_e('Featured', 'premium-classifieds'); ?>
        </h3>
        <label class="pc-checkbox-label">
            <input type="checkbox" 
                   id="pc-featured-filter" 
                   name="featured" 
                   value="1"
                   <?php checked(isset($_GET['featured'])); ?>>
            <span><?php esc_html_e('Show only featured', 'premium-classifieds'); ?></span>
        </label>
    </div>

    <!-- Reset Filters -->
    <div class="pc-filter-section">
        <a href="<?php echo esc_url(get_post_type_archive_link('listing')); ?>" 
           class="pc-btn pc-btn-secondary pc-btn-block">
            <span class="dashicons dashicons-image-rotate"></span>
            <?php esc_html_e('Reset Filters', 'premium-classifieds'); ?>
        </a>
    </div>

</div>

<style>
.pc-filters-sidebar {
    background: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    position: sticky;
    top: 2rem;
}

.pc-filter-section {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.pc-filter-section:last-child {
    border-bottom: none;
}

.pc-filter-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 1rem;
    color: #1e293b;
}

.pc-filter-title .dashicons {
    color: #2563eb;
    font-size: 20px;
    width: 20px;
    height: 20px;
}

/* Search Form */
.pc-search-input-group {
    display: flex;
    gap: 0.5rem;
}

.pc-search-input {
    flex: 1;
    padding: 0.625rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.pc-search-btn {
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 0.375rem;
    padding: 0.625rem 1rem;
    cursor: pointer;
}

.pc-search-btn:hover {
    background: #1d4ed8;
}

/* Category List */
.pc-category-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.pc-category-list li {
    margin-bottom: 0.5rem;
}

.pc-category-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    color: #64748b;
    text-decoration: none;
    border-radius: 0.375rem;
    transition: all 0.2s;
}

.pc-category-link:hover {
    background: #f1f5f9;
    color: #2563eb;
}

.pc-category-link.active {
    background: #dbeafe;
    color: #2563eb;
    font-weight: 600;
}

.pc-category-count {
    margin-left: auto;
    background: #f1f5f9;
    color: #64748b;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.pc-category-link.active .pc-category-count {
    background: #2563eb;
    color: #fff;
}

/* Price Inputs */
.pc-price-inputs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.pc-input-group {
    position: relative;
    flex: 1;
}

.pc-input-prefix {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-weight: 600;
}

.pc-price-input {
    width: 100%;
    padding: 0.625rem 1rem 0.625rem 2rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.pc-price-separator {
    color: #94a3b8;
    font-weight: 600;
}

/* Checkbox */
.pc-checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.pc-checkbox-label input[type="checkbox"] {
    width: 1.125rem;
    height: 1.125rem;
    cursor: pointer;
}

/* Featured Box */
.pc-featured-box {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-color: #fbbf24;
}

/* Responsive */
@media (max-width: 1024px) {
    .pc-filters-sidebar {
        position: static;
    }
}
</style>
