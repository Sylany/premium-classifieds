<?php
/**
 * My Listings Template
 *
 * Displays user's listings with edit/delete actions
 *
 * @package    PremiumClassifieds
 * @subpackage Templates/Dashboard
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();

// Query user's listings
$args = [
    'post_type' => 'listing',
    'author' => $user_id,
    'posts_per_page' => 20,
    'post_status' => ['publish', 'pending', 'draft'],
    'orderby' => 'date',
    'order' => 'DESC',
];

$listings_query = new WP_Query($args);
?>

<div class="pc-section">
    <div class="pc-section-header">
        <h1 class="pc-section-title">
            <span class="dashicons dashicons-megaphone"></span>
            <?php esc_html_e('My Listings', 'premium-classifieds'); ?>
        </h1>
        <a href="#" class="pc-btn pc-btn-primary" data-tab="add-listing">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e('Add New Listing', 'premium-classifieds'); ?>
        </a>
    </div>

    <?php if ($listings_query->have_posts()): ?>
        <div class="pc-listings-table-wrapper">
            <table class="pc-listings-table">
                <thead>
                    <tr>
                        <th class="pc-col-image"><?php esc_html_e('Image', 'premium-classifieds'); ?></th>
                        <th class="pc-col-title"><?php esc_html_e('Title', 'premium-classifieds'); ?></th>
                        <th class="pc-col-status"><?php esc_html_e('Status', 'premium-classifieds'); ?></th>
                        <th class="pc-col-views"><?php esc_html_e('Views', 'premium-classifieds'); ?></th>
                        <th class="pc-col-date"><?php esc_html_e('Date', 'premium-classifieds'); ?></th>
                        <th class="pc-col-actions"><?php esc_html_e('Actions', 'premium-classifieds'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($listings_query->have_posts()): $listings_query->the_post(); 
                        $listing_id = get_the_ID();
                        $status = get_post_status();
                        $views = get_post_meta($listing_id, '_pc_views', true) ?: 0;
                        $featured = get_post_meta($listing_id, '_pc_featured', true);
                    ?>
                        <tr class="pc-listing-row <?php echo $featured ? 'featured' : ''; ?>" data-listing-id="<?php echo esc_attr($listing_id); ?>">
                            <td class="pc-col-image">
                                <div class="pc-listing-thumbnail">
                                    <?php if (has_post_thumbnail()): ?>
                                        <?php the_post_thumbnail('thumbnail'); ?>
                                    <?php else: ?>
                                        <div class="pc-no-image">
                                            <span class="dashicons dashicons-format-image"></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($featured): ?>
                                        <span class="pc-featured-badge" title="<?php esc_attr_e('Featured', 'premium-classifieds'); ?>">
                                            <span class="dashicons dashicons-star-filled"></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="pc-col-title">
                                <div class="pc-listing-title-cell">
                                    <a href="<?php the_permalink(); ?>" target="_blank" class="pc-listing-title">
                                        <?php the_title(); ?>
                                    </a>
                                    <?php
                                    $categories = get_the_terms($listing_id, 'listing_category');
                                    if ($categories && !is_wp_error($categories)):
                                    ?>
                                        <div class="pc-listing-meta">
                                            <?php foreach ($categories as $cat): ?>
                                                <span class="pc-category-tag"><?php echo esc_html($cat->name); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="pc-col-status">
                                <?php
                                $status_labels = [
                                    'publish' => ['label' => __('Published', 'premium-classifieds'), 'class' => 'success'],
                                    'pending' => ['label' => __('Pending Review', 'premium-classifieds'), 'class' => 'warning'],
                                    'draft' => ['label' => __('Draft', 'premium-classifieds'), 'class' => 'secondary'],
                                ];
                                $status_info = $status_labels[$status] ?? ['label' => $status, 'class' => 'secondary'];
                                ?>
                                <span class="pc-status-badge pc-status-<?php echo esc_attr($status_info['class']); ?>">
                                    <?php echo esc_html($status_info['label']); ?>
                                </span>
                            </td>
                            <td class="pc-col-views">
                                <div class="pc-views-count">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php echo esc_html($views); ?>
                                </div>
                            </td>
                            <td class="pc-col-date">
                                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                    <?php echo esc_html(get_the_date()); ?>
                                </time>
                            </td>
                            <td class="pc-col-actions">
                                <div class="pc-action-buttons">
                                    <a href="<?php the_permalink(); ?>" 
                                       target="_blank" 
                                       class="pc-btn-icon" 
                                       title="<?php esc_attr_e('View', 'premium-classifieds'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </a>
                                    <button type="button" 
                                            class="pc-btn-icon pc-edit-listing" 
                                            data-listing-id="<?php echo esc_attr($listing_id); ?>"
                                            title="<?php esc_attr_e('Edit', 'premium-classifieds'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" 
                                            class="pc-btn-icon pc-delete-listing" 
                                            data-listing-id="<?php echo esc_attr($listing_id); ?>"
                                            title="<?php esc_attr_e('Delete', 'premium-classifieds'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                    <?php if (!$featured && $status === 'publish'): ?>
                                        <button type="button" 
                                                class="pc-btn-icon pc-boost-listing" 
                                                data-listing-id="<?php echo esc_attr($listing_id); ?>"
                                                title="<?php esc_attr_e('Boost (Featured)', 'premium-classifieds'); ?>">
                                            <span class="dashicons dashicons-star-filled"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="pc-empty-state">
            <div class="pc-empty-icon">
                <span class="dashicons dashicons-megaphone"></span>
            </div>
            <h3><?php esc_html_e('No listings yet', 'premium-classifieds'); ?></h3>
            <p><?php esc_html_e('Create your first listing to get started!', 'premium-classifieds'); ?></p>
            <a href="#" class="pc-btn pc-btn-primary" data-tab="add-listing">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e('Add New Listing', 'premium-classifieds'); ?>
            </a>
        </div>
    <?php endif; ?>
</div>
