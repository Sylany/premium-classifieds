<?php
/**
 * Add/Edit Listing Template
 *
 * Form for creating and editing listings
 *
 * @package    PremiumClassifieds
 * @subpackage Templates/Dashboard
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get categories
$categories = get_terms([
    'taxonomy' => 'listing_category',
    'hide_empty' => false,
]);

// Check if editing
$editing = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$listing = null;

if ($editing) {
    $listing = get_post($editing);
    // Verify ownership
    if (!$listing || $listing->post_author != get_current_user_id()) {
        $editing = 0;
        $listing = null;
    }
}
?>

<div class="pc-section">
    <div class="pc-section-header">
        <h1 class="pc-section-title">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php echo $editing ? esc_html__('Edit Listing', 'premium-classifieds') : esc_html__('Add New Listing', 'premium-classifieds'); ?>
        </h1>
    </div>

    <form id="pc-listing-form" class="pc-listing-form" enctype="multipart/form-data">
        <input type="hidden" name="listing_id" value="<?php echo esc_attr($editing); ?>">
        
        <div class="pc-form-grid">
            <!-- Left Column -->
            <div class="pc-form-main">
                <!-- Title -->
                <div class="pc-form-group">
                    <label for="listing_title" class="pc-label pc-required">
                        <?php esc_html_e('Title', 'premium-classifieds'); ?>
                    </label>
                    <input type="text" 
                           id="listing_title" 
                           name="title" 
                           class="pc-input pc-input-lg" 
                           value="<?php echo $listing ? esc_attr($listing->post_title) : ''; ?>"
                           placeholder="<?php esc_attr_e('Enter a descriptive title (min. 5 characters)', 'premium-classifieds'); ?>"
                           required
                           minlength="5"
                           maxlength="200">
                    <p class="pc-help-text">
                        <?php esc_html_e('Make your title clear and specific to attract the right audience.', 'premium-classifieds'); ?>
                    </p>
                </div>

                <!-- Description -->
                <div class="pc-form-group">
                    <label for="listing_content" class="pc-label pc-required">
                        <?php esc_html_e('Description', 'premium-classifieds'); ?>
                    </label>
                    <?php
                    $content = $listing ? $listing->post_content : '';
                    wp_editor($content, 'listing_content', [
                        'textarea_name' => 'content',
                        'textarea_rows' => 10,
                        'media_buttons' => false,
                        'teeny' => true,
                        'quicktags' => false,
                    ]);
                    ?>
                    <p class="pc-help-text">
                        <?php esc_html_e('Minimum 50 characters. Describe what makes your listing unique.', 'premium-classifieds'); ?>
                    </p>
                </div>

                <!-- Images Upload -->
                <div class="pc-form-group">
                    <label class="pc-label">
                        <?php esc_html_e('Images', 'premium-classifieds'); ?>
                    </label>
                    <div class="pc-image-upload-area" id="pc-image-dropzone">
                        <div class="pc-upload-placeholder">
                            <span class="dashicons dashicons-cloud-upload"></span>
                            <p><?php esc_html_e('Drop images here or click to browse', 'premium-classifieds'); ?></p>
                            <p class="pc-help-text">
                                <?php printf(
                                    esc_html__('Maximum %d images, up to 5MB each. Formats: JPG, PNG, GIF', 'premium-classifieds'),
                                    get_option('pc_max_images_per_listing', 10)
                                ); ?>
                            </p>
                        </div>
                        <input type="file" 
                               id="listing_images" 
                               name="images[]" 
                               accept="image/jpeg,image/png,image/gif" 
                               multiple 
                               style="display: none;">
                    </div>
                    
                    <!-- Image Preview Grid -->
                    <div id="pc-image-preview" class="pc-image-preview-grid">
                        <?php
                        if ($listing) {
                            $gallery = get_post_meta($editing, '_pc_gallery_images', true) ?: [];
                            foreach ($gallery as $img_id) {
                                $img_url = wp_get_attachment_image_url($img_id, 'thumbnail');
                                if ($img_url) {
                                    echo '<div class="pc-preview-item" data-attachment-id="' . esc_attr($img_id) . '">';
                                    echo '<img src="' . esc_url($img_url) . '" alt="">';
                                    echo '<button type="button" class="pc-remove-image" data-id="' . esc_attr($img_id) . '">';
                                    echo '<span class="dashicons dashicons-no"></span>';
                                    echo '</button>';
                                    echo '</div>';
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="pc-form-sidebar">
                <!-- Category -->
                <div class="pc-form-group">
                    <label for="listing_category" class="pc-label pc-required">
                        <?php esc_html_e('Category', 'premium-classifieds'); ?>
                    </label>
                    <select id="listing_category" name="category" class="pc-select" required>
                        <option value=""><?php esc_html_e('Select Category', 'premium-classifieds'); ?></option>
                        <?php
                        $current_cat = $listing ? wp_get_object_terms($editing, 'listing_category', ['fields' => 'ids']) : [];
                        $current_cat_id = !empty($current_cat) ? $current_cat[0] : 0;
                        
                        foreach ($categories as $cat):
                        ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>" 
                                    <?php selected($current_cat_id, $cat->term_id); ?>>
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Price -->
                <div class="pc-form-group">
                    <label for="listing_price" class="pc-label">
                        <?php esc_html_e('Price', 'premium-classifieds'); ?>
                    </label>
                    <div class="pc-input-group">
                        <span class="pc-input-prefix">$</span>
                        <input type="text" 
                               id="listing_price" 
                               name="price" 
                               class="pc-input" 
                               value="<?php echo $listing ? esc_attr(get_post_meta($editing, '_pc_price', true)) : ''; ?>"
                               placeholder="0.00"
                               pattern="^\d+(\.\d{1,2})?$">
                    </div>
                    <p class="pc-help-text">
                        <?php esc_html_e('Optional. Leave empty if not applicable.', 'premium-classifieds'); ?>
                    </p>
                </div>

                <!-- Location -->
                <div class="pc-form-group">
                    <label for="listing_location" class="pc-label">
                        <?php esc_html_e('Location', 'premium-classifieds'); ?>
                    </label>
                    <input type="text" 
                           id="listing_location" 
                           name="location" 
                           class="pc-input" 
                           value="<?php echo $listing ? esc_attr(get_post_meta($editing, '_pc_location', true)) : ''; ?>"
                           placeholder="<?php esc_attr_e('City, State', 'premium-classifieds'); ?>">
                </div>

                <!-- Contact Phone -->
                <div class="pc-form-group">
                    <label for="listing_phone" class="pc-label">
                        <?php esc_html_e('Phone Number', 'premium-classifieds'); ?>
                    </label>
                    <input type="tel" 
                           id="listing_phone" 
                           name="phone" 
                           class="pc-input" 
                           value="<?php echo $listing ? esc_attr(get_post_meta($editing, '_pc_phone', true)) : ''; ?>"
                           placeholder="<?php esc_attr_e('+1 (555) 123-4567', 'premium-classifieds'); ?>">
                    <p class="pc-help-text">
                        <span class="dashicons dashicons-lock"></span>
                        <?php esc_html_e('Hidden until buyer pays to reveal', 'premium-classifieds'); ?>
                    </p>
                </div>

                <!-- Contact Email -->
                <div class="pc-form-group">
                    <label for="listing_email" class="pc-label">
                        <?php esc_html_e('Email Address', 'premium-classifieds'); ?>
                    </label>
                    <input type="email" 
                           id="listing_email" 
                           name="email" 
                           class="pc-input" 
                           value="<?php echo $listing ? esc_attr(get_post_meta($editing, '_pc_email', true)) : get_user_meta(get_current_user_id(), 'user_email', true); ?>"
                           placeholder="<?php esc_attr_e('your@email.com', 'premium-classifieds'); ?>">
                    <p class="pc-help-text">
                        <span class="dashicons dashicons-lock"></span>
                        <?php esc_html_e('Hidden until buyer pays to reveal', 'premium-classifieds'); ?>
                    </p>
                </div>

                <!-- Submit Button -->
                <div class="pc-form-actions">
                    <button type="submit" class="pc-btn pc-btn-primary pc-btn-block" id="pc-submit-listing">
                        <span class="dashicons dashicons-yes"></span>
                        <?php echo $editing ? esc_html__('Update Listing', 'premium-classifieds') : esc_html__('Submit Listing', 'premium-classifieds'); ?>
                    </button>
                    
                    <?php if (get_option('pc_require_approval', '1') === '1' && !$editing): ?>
                        <p class="pc-help-text" style="text-align: center; margin-top: 1rem;">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('Your listing will be reviewed before publishing.', 'premium-classifieds'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>
