<?php
/**
 * Single Listing Template
 *
 * Displays individual listing with paywall for contact information.
 * Shows gallery, description, price, and payment options.
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

// Get listing data
$listing_id = get_the_ID();
$user_id = get_current_user_id();
$is_owner = $user_id && get_post_field('post_author', $listing_id) == $user_id;

// Get meta data
$price = get_post_meta($listing_id, '_pc_price', true);
$location = get_post_meta($listing_id, '_pc_location', true);
$website = get_post_meta($listing_id, '_pc_website', true);
$phone = get_post_meta($listing_id, '_pc_phone', true);
$email = get_post_meta($listing_id, '_pc_email', true);
$whatsapp = get_post_meta($listing_id, '_pc_whatsapp', true);
$views = get_post_meta($listing_id, '_pc_views', true) ?: 0;
$featured = get_post_meta($listing_id, '_pc_featured', true);
$gallery_images = get_post_meta($listing_id, '_pc_gallery_images', true) ?: [];

// Update view count
if (!$is_owner) {
    update_post_meta($listing_id, '_pc_views', $views + 1);
}

// Check access
$access_db = new PC_DB_Access_Grants();
$has_access = $access_db->check_access($user_id, $listing_id);

// Get categories
$categories = get_the_terms($listing_id, 'listing_category');
$tags = get_the_terms($listing_id, 'listing_tag');

// Get author
$author_id = get_post_field('post_author', $listing_id);
$author = get_userdata($author_id);
?>

<div class="pc-single-listing-wrapper">
    <div class="pc-single-container">
        
        <!-- Breadcrumbs -->
        <nav class="pc-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'premium-classifieds'); ?></a>
            <span class="pc-breadcrumb-separator">/</span>
            <a href="<?php echo esc_url(get_post_type_archive_link('listing')); ?>"><?php esc_html_e('Listings', 'premium-classifieds'); ?></a>
            <?php if ($categories && !is_wp_error($categories)): ?>
                <span class="pc-breadcrumb-separator">/</span>
                <a href="<?php echo esc_url(get_term_link($categories[0])); ?>"><?php echo esc_html($categories[0]->name); ?></a>
            <?php endif; ?>
            <span class="pc-breadcrumb-separator">/</span>
            <span class="pc-breadcrumb-current"><?php the_title(); ?></span>
        </nav>

        <div class="pc-single-grid">
            
            <!-- Main Content -->
            <main class="pc-single-main">
                
                <!-- Gallery Section -->
                <div class="pc-gallery-section">
                    <?php if (!empty($gallery_images)): ?>
                        <div class="pc-gallery-main">
                            <?php 
                            $main_image_id = $gallery_images[0];
                            $main_image_url = wp_get_attachment_image_url($main_image_id, 'large');
                            ?>
                            <img id="pc-gallery-main-image" 
                                 src="<?php echo esc_url($main_image_url); ?>" 
                                 alt="<?php echo esc_attr(get_the_title()); ?>">
                        </div>
                        
                        <?php if (count($gallery_images) > 1): ?>
                            <div class="pc-gallery-thumbnails">
                                <?php foreach ($gallery_images as $index => $image_id): 
                                    $thumb_url = wp_get_attachment_image_url($image_id, 'medium');
                                    $full_url = wp_get_attachment_image_url($image_id, 'large');
                                ?>
                                    <div class="pc-gallery-thumb <?php echo $index === 0 ? 'active' : ''; ?>" 
                                         data-full="<?php echo esc_url($full_url); ?>">
                                        <img src="<?php echo esc_url($thumb_url); ?>" alt="">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="pc-gallery-placeholder">
                            <span class="dashicons dashicons-format-image"></span>
                            <p><?php esc_html_e('No images available', 'premium-classifieds'); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($featured): ?>
                        <div class="pc-featured-badge-overlay">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e('Featured', 'premium-classifieds'); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Listing Header -->
                <div class="pc-listing-header">
                    <div class="pc-listing-title-row">
                        <h1 class="pc-listing-title"><?php the_title(); ?></h1>
                        
                        <div class="pc-listing-actions">
                            <?php if ($is_owner): ?>
                                <a href="<?php echo esc_url(add_query_arg(['tab' => 'add-listing', 'edit' => $listing_id], home_url('/dashboard/'))); ?>" 
                                   class="pc-btn pc-btn-secondary">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php esc_html_e('Edit', 'premium-classifieds'); ?>
                                </a>
                            <?php else: ?>
                                <button class="pc-btn-icon pc-add-favorite-btn" 
                                        data-listing-id="<?php echo esc_attr($listing_id); ?>"
                                        title="<?php esc_attr_e('Add to favorites', 'premium-classifieds'); ?>">
                                    <span class="dashicons dashicons-heart"></span>
                                </button>
                                <button class="pc-btn-icon pc-share-btn" 
                                        title="<?php esc_attr_e('Share', 'premium-classifieds'); ?>">
                                    <span class="dashicons dashicons-share"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="pc-listing-meta-row">
                        <?php if ($categories && !is_wp_error($categories)): ?>
                            <div class="pc-listing-categories">
                                <?php foreach ($categories as $category): 
                                    $icon = get_term_meta($category->term_id, 'category_icon', true);
                                ?>
                                    <a href="<?php echo esc_url(get_term_link($category)); ?>" class="pc-category-badge">
                                        <?php if ($icon): ?>
                                            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                                        <?php endif; ?>
                                        <?php echo esc_html($category->name); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="pc-listing-stats">
                            <span class="pc-stat-item">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php echo esc_html(number_format($views)); ?> <?php esc_html_e('views', 'premium-classifieds'); ?>
                            </span>
                            <span class="pc-stat-item">
                                <span class="dashicons dashicons-clock"></span>
                                <?php echo esc_html(human_time_diff(get_the_time('U'), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'premium-classifieds'); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($price): ?>
                        <div class="pc-listing-price-banner">
                            <span class="pc-price-label"><?php esc_html_e('Price:', 'premium-classifieds'); ?></span>
                            <span class="pc-price-amount">$<?php echo esc_html($price); ?></span>
                            <span class="pc-price-period"><?php esc_html_e('per hour', 'premium-classifieds'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="pc-listing-content">
                    <h2><?php esc_html_e('About', 'premium-classifieds'); ?></h2>
                    <?php the_content(); ?>
                </div>

                <!-- Tags -->
                <?php if ($tags && !is_wp_error($tags)): ?>
                    <div class="pc-listing-tags">
                        <h3><?php esc_html_e('Tags', 'premium-classifieds'); ?></h3>
                        <div class="pc-tags-list">
                            <?php foreach ($tags as $tag): ?>
                                <a href="<?php echo esc_url(get_term_link($tag)); ?>" class="pc-tag">
                                    <?php echo esc_html($tag->name); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Contact Section with Paywall -->
                <div class="pc-contact-section">
                    <?php if ($is_owner): ?>
                        <!-- Owner sees their own contact info -->
                        <div class="pc-owner-notice">
                            <span class="dashicons dashicons-info"></span>
                            <div>
                                <strong><?php esc_html_e('This is your listing', 'premium-classifieds'); ?></strong>
                                <p><?php esc_html_e('Other users need to pay to see your contact information.', 'premium-classifieds'); ?></p>
                            </div>
                        </div>
                        
                        <?php include PC_TEMPLATES_DIR . 'partials/contact-info-display.php'; ?>
                        
                    <?php elseif ($has_access): ?>
                        <!-- Buyer with access sees contact info -->
                        <?php include PC_TEMPLATES_DIR . 'partials/contact-info-display.php'; ?>
                        
                    <?php else: ?>
                        <!-- Paywall for non-buyers -->
                        <?php include PC_TEMPLATES_DIR . 'partials/paywall.php'; ?>
                    <?php endif; ?>
                </div>

                <!-- Author Info -->
                <div class="pc-author-section">
                    <h3><?php esc_html_e('About the Seller', 'premium-classifieds'); ?></h3>
                    <div class="pc-author-card">
                        <div class="pc-author-avatar">
                            <?php echo get_avatar($author_id, 80); ?>
                        </div>
                        <div class="pc-author-info">
                            <h4><?php echo esc_html($author->display_name); ?></h4>
                            <p class="pc-author-meta">
                                <?php 
                                printf(
                                    esc_html__('Member since %s', 'premium-classifieds'),
                                    date_i18n(get_option('date_format'), strtotime($author->user_registered))
                                );
                                ?>
                            </p>
                            <a href="<?php echo esc_url(get_author_posts_url($author_id)); ?>" class="pc-btn pc-btn-secondary">
                                <?php esc_html_e('View all listings', 'premium-classifieds'); ?>
                            </a>
                        </div>
                    </div>
                </div>

            </main>

            <!-- Sidebar -->
            <aside class="pc-single-sidebar">
                
                <!-- Quick Info Card -->
                <div class="pc-info-card">
                    <h3><?php esc_html_e('Quick Info', 'premium-classifieds'); ?></h3>
                    
                    <?php if ($location): ?>
                        <div class="pc-info-item">
                            <span class="dashicons dashicons-location"></span>
                            <div>
                                <strong><?php esc_html_e('Location', 'premium-classifieds'); ?></strong>
                                <p><?php echo esc_html($location); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($website): ?>
                        <div class="pc-info-item">
                            <span class="dashicons dashicons-admin-links"></span>
                            <div>
                                <strong><?php esc_html_e('Website', 'premium-classifieds'); ?></strong>
                                <p><a href="<?php echo esc_url($website); ?>" target="_blank" rel="nofollow"><?php echo esc_html(parse_url($website, PHP_URL_HOST)); ?></a></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="pc-info-item">
                        <span class="dashicons dashicons-calendar"></span>
                        <div>
                            <strong><?php esc_html_e('Posted', 'premium-classifieds'); ?></strong>
                            <p><?php echo get_the_date(); ?></p>
                        </div>
                    </div>

                    <div class="pc-info-item">
                        <span class="dashicons dashicons-admin-post"></span>
                        <div>
                            <strong><?php esc_html_e('ID', 'premium-classifieds'); ?></strong>
                            <p>#<?php echo esc_html($listing_id); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Safety Tips -->
                <div class="pc-safety-card">
                    <h3>
                        <span class="dashicons dashicons-shield"></span>
                        <?php esc_html_e('Safety Tips', 'premium-classifieds'); ?>
                    </h3>
                    <ul>
                        <li><?php esc_html_e('Meet in a public place', 'premium-classifieds'); ?></li>
                        <li><?php esc_html_e('Check the item before you buy', 'premium-classifieds'); ?></li>
                        <li><?php esc_html_e('Pay only after collecting item', 'premium-classifieds'); ?></li>
                        <li><?php esc_html_e('Never send money in advance', 'premium-classifieds'); ?></li>
                    </ul>
                </div>

                <!-- Report Listing -->
                <div class="pc-report-section">
                    <button class="pc-btn pc-btn-text pc-report-btn">
                        <span class="dashicons dashicons-flag"></span>
                        <?php esc_html_e('Report this listing', 'premium-classifieds'); ?>
                    </button>
                </div>

            </aside>

        </div>

        <!-- Related Listings -->
        <?php 
        $related_args = [
            'post_type'      => 'listing',
            'posts_per_page' => 4,
            'post__not_in'   => [$listing_id],
            'post_status'    => 'publish',
        ];

        if ($categories && !is_wp_error($categories)) {
            $related_args['tax_query'] = [
                [
                    'taxonomy' => 'listing_category',
                    'field'    => 'term_id',
                    'terms'    => $categories[0]->term_id,
                ],
            ];
        }

        $related_query = new WP_Query($related_args);

        if ($related_query->have_posts()): ?>
            <div class="pc-related-listings">
                <h2><?php esc_html_e('Related Listings', 'premium-classifieds'); ?></h2>
                <div class="pc-listings-grid">
                    <?php while ($related_query->have_posts()): $related_query->the_post(); ?>
                        <?php include PC_TEMPLATES_DIR . 'partials/listing-card.php'; ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php get_footer(); ?>
