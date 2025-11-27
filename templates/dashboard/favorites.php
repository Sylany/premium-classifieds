<?php
/**
 * Dashboard Favorites Template
 *
 * @package Premium_Classifieds
 */

defined('ABSPATH') || exit;

$user_id = get_current_user_id();
$favorites = get_user_meta($user_id, '_pc_favorites', true) ?: [];
?>

<div class="pc-section">
    <div class="pc-section-header">
        <h2><?php esc_html_e('My Favorites', 'premium-classifieds'); ?></h2>
        <?php if (!empty($favorites)): ?>
            <div class="pc-section-actions">
                <span class="pc-count-badge">
                    <?php printf(esc_html__('%d saved', 'premium-classifieds'), count($favorites)); ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($favorites)): ?>
        <div class="pc-empty-state">
            <div class="pc-empty-icon">
                <span class="dashicons dashicons-heart"></span>
            </div>
            <h3><?php esc_html_e('No Favorites Yet', 'premium-classifieds'); ?></h3>
            <p><?php esc_html_e('Save listings you\'re interested in to easily find them later.', 'premium-classifieds'); ?></p>
            <p class="pc-empty-hint">
                <?php esc_html_e('Click the heart icon on any listing to add it to your favorites.', 'premium-classifieds'); ?>
            </p>
            <a href="<?php echo esc_url(home_url('/listings')); ?>" class="pc-btn pc-btn-primary">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e('Browse Listings', 'premium-classifieds'); ?>
            </a>
        </div>
    <?php else:
        $query = new WP_Query([
            'post_type' => 'listing',
            'post__in' => $favorites,
            'orderby' => 'post__in',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'pending', 'draft']
        ]);
        
        if ($query->have_posts()): ?>
            <div class="pc-favorites-controls">
                <div class="pc-view-toggle">
                    <button type="button" class="pc-view-btn active" data-view="grid" title="<?php esc_attr_e('Grid view', 'premium-classifieds'); ?>">
                        <span class="dashicons dashicons-grid-view"></span>
                    </button>
                    <button type="button" class="pc-view-btn" data-view="list" title="<?php esc_attr_e('List view', 'premium-classifieds'); ?>">
                        <span class="dashicons dashicons-list-view"></span>
                    </button>
                </div>
                
                <button type="button" class="pc-btn pc-btn-text" id="pc-clear-all-favorites">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Clear All', 'premium-classifieds'); ?>
                </button>
            </div>

            <div class="pc-listings-grid pc-favorites-grid">
                <?php while ($query->have_posts()): $query->the_post(); 
                    $listing_id = get_the_ID();
                    $price = get_post_meta($listing_id, '_pc_price', true);
                    $location = get_post_meta($listing_id, '_pc_location', true);
                    $featured = get_post_meta($listing_id, '_pc_featured', true);
                    $status = get_post_status();
                    
                    // Get categories
                    $categories = wp_get_post_terms($listing_id, 'listing_category');
                    $category = !empty($categories) ? $categories[0] : null;
                ?>
                    <div class="pc-listing-card pc-favorite-item" data-listing-id="<?php echo esc_attr($listing_id); ?>">
                        <?php if ($featured): ?>
                            <div class="pc-listing-badge pc-badge-featured">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php esc_html_e('Featured', 'premium-classifieds'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($status !== 'publish'): ?>
                            <div class="pc-listing-badge pc-badge-status pc-badge-<?php echo esc_attr($status); ?>">
                                <?php echo esc_html(ucfirst($status)); ?>
                            </div>
                        <?php endif; ?>

                        <button type="button" class="pc-favorite-btn active" data-listing-id="<?php echo esc_attr($listing_id); ?>" title="<?php esc_attr_e('Remove from favorites', 'premium-classifieds'); ?>">
                            <span class="dashicons dashicons-heart"></span>
                        </button>

                        <a href="<?php echo esc_url(get_permalink()); ?>" class="pc-listing-image-link">
                            <?php if (has_post_thumbnail()): ?>
                                <?php the_post_thumbnail('medium_large', ['class' => 'pc-listing-image']); ?>
                            <?php else: ?>
                                <div class="pc-listing-image pc-no-image">
                                    <span class="dashicons dashicons-format-image"></span>
                                </div>
                            <?php endif; ?>
                        </a>

                        <div class="pc-listing-content">
                            <?php if ($category): ?>
                                <div class="pc-listing-category">
                                    <span class="dashicons dashicons-<?php echo esc_attr($category->description ?: 'category'); ?>"></span>
                                    <?php echo esc_html($category->name); ?>
                                </div>
                            <?php endif; ?>

                            <h3 class="pc-listing-title">
                                <a href="<?php echo esc_url(get_permalink()); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </h3>

                            <div class="pc-listing-meta">
                                <?php if ($location): ?>
                                    <span class="pc-meta-item">
                                        <span class="dashicons dashicons-location"></span>
                                        <?php echo esc_html($location); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <span class="pc-meta-item">
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php echo esc_html(human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago'); ?>
                                </span>
                            </div>

                            <?php if (has_excerpt()): ?>
                                <p class="pc-listing-excerpt">
                                    <?php echo esc_html(wp_trim_words(get_the_excerpt(), 15)); ?>
                                </p>
                            <?php endif; ?>

                            <div class="pc-listing-footer">
                                <?php if ($price): ?>
                                    <div class="pc-listing-price">
                                        $<?php echo esc_html(number_format((float)$price, 2)); ?>
                                    </div>
                                <?php endif; ?>

                                <a href="<?php echo esc_url(get_permalink()); ?>" class="pc-btn pc-btn-sm pc-btn-primary">
                                    <?php esc_html_e('View Details', 'premium-classifieds'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>

            <div class="pc-favorites-summary">
                <p>
                    <?php printf(
                        esc_html__('You have %d listing%s saved in your favorites.', 'premium-classifieds'),
                        count($favorites),
                        count($favorites) === 1 ? '' : 's'
                    ); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="pc-notice pc-notice-info">
                <p><?php esc_html_e('Some of your favorited listings are no longer available.', 'premium-classifieds'); ?></p>
                <button type="button" class="pc-btn pc-btn-text" id="pc-clear-all-favorites">
                    <?php esc_html_e('Clear All Favorites', 'premium-classifieds'); ?>
                </button>
            </div>
        <?php endif;
    endif; ?>
</div>

<style>
.pc-favorites-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.pc-view-toggle {
    display: flex;
    gap: 4px;
    background: #f3f4f6;
    padding: 4px;
    border-radius: 6px;
}

.pc-view-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 4px;
    color: #6b7280;
    transition: all 0.2s;
}

.pc-view-btn:hover {
    color: #2196F3;
}

.pc-view-btn.active {
    background: #fff;
    color: #2196F3;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.pc-favorites-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
}

.pc-favorites-grid.list-view {
    grid-template-columns: 1fr;
}

.pc-favorites-grid.list-view .pc-listing-card {
    display: flex;
    flex-direction: row;
    align-items: stretch;
}

.pc-favorites-grid.list-view .pc-listing-image-link {
    width: 200px;
    flex-shrink: 0;
}

.pc-favorites-grid.list-view .pc-listing-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.pc-favorite-item {
    position: relative;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.pc-favorite-item.removing {
    animation: fadeOut 0.3s ease forwards;
}

@keyframes fadeOut {
    to {
        opacity: 0;
        transform: scale(0.9);
    }
}

.pc-favorites-summary {
    margin-top: 32px;
    padding: 16px;
    background: #f9fafb;
    border-radius: 8px;
    text-align: center;
    color: #6b7280;
}

.pc-favorite-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 10;
    background: rgba(255,255,255,0.95);
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s;
    color: #999;
}

.pc-favorite-btn:hover {
    background: #fff;
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.pc-favorite-btn.active {
    color: #e91e63;
}

.pc-favorite-btn .dashicons {
    width: 20px;
    height: 20px;
    font-size: 20px;
}

.pc-count-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: #e3f2fd;
    color: #1976d2;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

@media (max-width: 768px) {
    .pc-favorites-grid {
        grid-template-columns: 1fr;
    }
    
    .pc-favorites-controls {
        flex-direction: column;
        gap: 12px;
        align-items: stretch;
    }
    
    .pc-view-toggle {
        justify-content: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // View toggle
    $('.pc-view-btn').on('click', function() {
        const view = $(this).data('view');
        
        $('.pc-view-btn').removeClass('active');
        $(this).addClass('active');
        
        if (view === 'list') {
            $('.pc-favorites-grid').addClass('list-view');
        } else {
            $('.pc-favorites-grid').removeClass('list-view');
        }
    });
    
    // Toggle favorite
    $('.pc-favorite-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $btn = $(this);
        const $card = $btn.closest('.pc-favorite-item');
        const listingId = $btn.data('listing-id');
        
        $.ajax({
            url: pcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pc_toggle_favorite',
                nonce: pcData.nonce,
                listing_id: listingId
            },
            beforeSend: function() {
                $btn.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Animate removal
                    $card.addClass('removing');
                    
                    setTimeout(function() {
                        $card.remove();
                        
                        // Update count
                        const $countBadge = $('.pc-count-badge');
                        const currentCount = $('.pc-favorite-item').length;
                        
                        if (currentCount === 0) {
                            location.reload(); // Show empty state
                        } else {
                            $countBadge.text(currentCount + ' saved');
                        }
                    }, 300);
                    
                    pcUtils.showNotice('Removed from favorites', 'success');
                }
            },
            error: function() {
                pcUtils.showNotice('Failed to update favorites', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Clear all favorites
    $('#pc-clear-all-favorites').on('click', function() {
        if (!confirm('Are you sure you want to clear all favorites? This cannot be undone.')) {
            return;
        }
        
        $.ajax({
            url: pcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pc_clear_favorites',
                nonce: pcData.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            },
            error: function() {
                pcUtils.showNotice('Failed to clear favorites', 'error');
            }
        });
    });
});
</script>