<?php
/**
 * Listing Card Component
 *
 * Reusable card component for displaying listings in grids.
 * Used in archive, related listings, and dashboard.
 *
 * @package    PremiumClassifieds
 * @subpackage Templates/Partials
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$card_listing_id = get_the_ID();
$card_price = get_post_meta($card_listing_id, '_pc_price', true);
$card_location = get_post_meta($card_listing_id, '_pc_location', true);
$card_featured = get_post_meta($card_listing_id, '_pc_featured', true);
$card_categories = get_the_terms($card_listing_id, 'listing_category');
?>

<div class="pc-listing-card <?php echo $card_featured ? 'pc-featured' : ''; ?>">
    
    <!-- Card Image -->
    <a href="<?php the_permalink(); ?>" class="pc-card-image-link">
        <div class="pc-card-image">
            <?php if (has_post_thumbnail()): ?>
                <?php the_post_thumbnail('medium'); ?>
            <?php else: ?>
                <div class="pc-card-placeholder">
                    <span class="dashicons dashicons-format-image"></span>
                </div>
            <?php endif; ?>
            
            <?php if ($card_featured): ?>
                <div class="pc-featured-badge">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e('Featured', 'premium-classifieds'); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($card_price): ?>
                <div class="pc-card-price-overlay">
                    $<?php echo esc_html($card_price); ?>
                </div>
            <?php endif; ?>
        </div>
    </a>
    
    <!-- Card Content -->
    <div class="pc-card-content">
        
        <?php if ($card_categories && !is_wp_error($card_categories)): ?>
            <div class="pc-card-category">
                <?php 
                $card_category = $card_categories[0];
                $card_icon = get_term_meta($card_category->term_id, 'category_icon', true);
                ?>
                <a href="<?php echo esc_url(get_term_link($card_category)); ?>">
                    <?php if ($card_icon): ?>
                        <span class="dashicons <?php echo esc_attr($card_icon); ?>"></span>
                    <?php endif; ?>
                    <?php echo esc_html($card_category->name); ?>
                </a>
            </div>
        <?php endif; ?>
        
        <h3 class="pc-card-title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>
        
        <div class="pc-card-excerpt">
            <?php echo wp_trim_words(get_the_excerpt(), 20, '...'); ?>
        </div>
        
        <div class="pc-card-footer">
            <?php if ($card_location): ?>
                <div class="pc-card-location">
                    <span class="dashicons dashicons-location"></span>
                    <?php echo esc_html($card_location); ?>
                </div>
            <?php endif; ?>
            
            <div class="pc-card-meta">
                <span class="dashicons dashicons-clock"></span>
                <?php echo esc_html(human_time_diff(get_the_time('U'), current_time('timestamp'))); ?>
            </div>
        </div>
        
        <a href="<?php the_permalink(); ?>" class="pc-card-button">
            <?php esc_html_e('View Details', 'premium-classifieds'); ?>
            <span class="dashicons dashicons-arrow-right-alt2"></span>
        </a>
    </div>
</div>
