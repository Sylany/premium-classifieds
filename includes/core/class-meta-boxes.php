<?php
/**
 * Meta Boxes Registration and Handling
 *
 * Registers custom meta boxes for the listing post type in wp-admin.
 * Handles saving of all custom fields with proper nonce verification.
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
 * Meta Boxes Class
 *
 * @since 2.1.0
 */
class PC_Meta_Boxes {
    
    /**
     * Nonce name for security
     *
     * @var string
     */
    private const NONCE_NAME = 'pc_listing_meta_nonce';
    
    /**
     * Nonce action
     *
     * @var string
     */
    private const NONCE_ACTION = 'pc_save_listing_meta';
    
    /**
     * Constructor - Register hooks
     *
     * @since 2.1.0
     */
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_listing', [$this, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }
    
    /**
     * Register meta boxes
     *
     * @since 2.1.0
     * @return void
     */
    public function add_meta_boxes(): void {
        // Listing Details
        add_meta_box(
            'pc_listing_details',
            __('Listing Details', 'premium-classifieds'),
            [$this, 'render_details_meta_box'],
            'listing',
            'normal',
            'high'
        );
        
        // Contact Information (Hidden from buyers)
        add_meta_box(
            'pc_contact_info',
            __('Contact Information (Pay-to-Reveal)', 'premium-classifieds'),
            [$this, 'render_contact_meta_box'],
            'listing',
            'normal',
            'high'
        );
        
        // Gallery Images
        add_meta_box(
            'pc_gallery',
            __('Gallery Images', 'premium-classifieds'),
            [$this, 'render_gallery_meta_box'],
            'listing',
            'side',
            'default'
        );
        
        // Listing Settings
        add_meta_box(
            'pc_listing_settings',
            __('Listing Settings', 'premium-classifieds'),
            [$this, 'render_settings_meta_box'],
            'listing',
            'side',
            'default'
        );
        
        // Statistics
        add_meta_box(
            'pc_listing_stats',
            __('Statistics', 'premium-classifieds'),
            [$this, 'render_stats_meta_box'],
            'listing',
            'side',
            'low'
        );
    }
    
    /**
     * Render listing details meta box
     *
     * @since 2.1.0
     * @param WP_Post $post Post object
     * @return void
     */
    public function render_details_meta_box(\WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        
        $price = get_post_meta($post->ID, '_pc_price', true);
        $location = get_post_meta($post->ID, '_pc_location', true);
        $website = get_post_meta($post->ID, '_pc_website', true);
        ?>
        
        <div class="pc-meta-box-content">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pc_price"><?php esc_html_e('Price', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px; font-weight: 600;">$</span>
                            <input type="text" 
                                   id="pc_price" 
                                   name="pc_price" 
                                   value="<?php echo esc_attr($price); ?>" 
                                   class="regular-text"
                                   placeholder="0.00"
                                   pattern="^\d+(\.\d{1,2})?$">
                        </div>
                        <p class="description">
                            <?php esc_html_e('Price per hour/session (leave empty if not applicable)', 'premium-classifieds'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pc_location"><?php esc_html_e('Location', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="pc_location" 
                               name="pc_location" 
                               value="<?php echo esc_attr($location); ?>" 
                               class="regular-text"
                               placeholder="<?php esc_attr_e('City, State or Region', 'premium-classifieds'); ?>">
                        <p class="description">
                            <?php esc_html_e('Where is this service/person located?', 'premium-classifieds'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pc_website"><?php esc_html_e('Website (Optional)', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <input type="url" 
                               id="pc_website" 
                               name="pc_website" 
                               value="<?php echo esc_url($website); ?>" 
                               class="regular-text"
                               placeholder="https://example.com">
                        <p class="description">
                            <?php esc_html_e('Public website or portfolio (not hidden)', 'premium-classifieds'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <style>
            .pc-meta-box-content .form-table th { width: 200px; }
            .pc-meta-box-content .form-table td { padding-bottom: 20px; }
            .pc-meta-box-content .description { margin-top: 5px; color: #666; }
        </style>
        <?php
    }
    
    /**
     * Render contact information meta box
     *
     * @since 2.1.0
     * @param WP_Post $post Post object
     * @return void
     */
    public function render_contact_meta_box(\WP_Post $post): void {
        $phone = get_post_meta($post->ID, '_pc_phone', true);
        $email = get_post_meta($post->ID, '_pc_email', true);
        $whatsapp = get_post_meta($post->ID, '_pc_whatsapp', true);
        ?>
        
        <div class="pc-contact-notice" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px;">
            <p style="margin: 0; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-lock" style="color: #856404;"></span>
                <strong><?php esc_html_e('Protected Information', 'premium-classifieds'); ?></strong>
            </p>
            <p style="margin: 5px 0 0 0; font-size: 13px; color: #856404;">
                <?php esc_html_e('This contact information is hidden from buyers until they pay to reveal it.', 'premium-classifieds'); ?>
            </p>
        </div>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="pc_phone"><?php esc_html_e('Phone Number', 'premium-classifieds'); ?></label>
                </th>
                <td>
                    <input type="tel" 
                           id="pc_phone" 
                           name="pc_phone" 
                           value="<?php echo esc_attr($phone); ?>" 
                           class="regular-text"
                           placeholder="+1 (555) 123-4567">
                    <p class="description">
                        <?php esc_html_e('Primary contact phone number', 'premium-classifieds'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="pc_email"><?php esc_html_e('Email Address', 'premium-classifieds'); ?></label>
                </th>
                <td>
                    <input type="email" 
                           id="pc_email" 
                           name="pc_email" 
                           value="<?php echo esc_attr($email); ?>" 
                           class="regular-text"
                           placeholder="contact@example.com">
                    <p class="description">
                        <?php esc_html_e('Contact email (can be different from WordPress account)', 'premium-classifieds'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="pc_whatsapp"><?php esc_html_e('WhatsApp (Optional)', 'premium-classifieds'); ?></label>
                </th>
                <td>
                    <input type="tel" 
                           id="pc_whatsapp" 
                           name="pc_whatsapp" 
                           value="<?php echo esc_attr($whatsapp); ?>" 
                           class="regular-text"
                           placeholder="+1234567890">
                    <p class="description">
                        <?php esc_html_e('WhatsApp number with country code (no spaces)', 'premium-classifieds'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render gallery meta box
     *
     * @since 2.1.0
     * @param WP_Post $post Post object
     * @return void
     */
    public function render_gallery_meta_box(\WP_Post $post): void {
        $gallery_images = get_post_meta($post->ID, '_pc_gallery_images', true) ?: [];
        ?>
        
        <div class="pc-gallery-container">
            <div id="pc-gallery-images" class="pc-gallery-grid">
                <?php foreach ($gallery_images as $image_id): 
                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                    if ($image_url):
                ?>
                    <div class="pc-gallery-item" data-attachment-id="<?php echo esc_attr($image_id); ?>">
                        <img src="<?php echo esc_url($image_url); ?>" alt="">
                        <button type="button" class="pc-remove-gallery-image" data-id="<?php echo esc_attr($image_id); ?>">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                <?php endif; endforeach; ?>
            </div>
            
            <input type="hidden" id="pc_gallery_ids" name="pc_gallery_images" value="<?php echo esc_attr(implode(',', $gallery_images)); ?>">
            
            <p>
                <button type="button" class="button button-secondary" id="pc-add-gallery-images">
                    <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                    <?php esc_html_e('Add Images', 'premium-classifieds'); ?>
                </button>
            </p>
            
            <p class="description">
                <?php printf(
                    esc_html__('Maximum %d images. Click and drag to reorder.', 'premium-classifieds'),
                    get_option('pc_max_images_per_listing', 10)
                ); ?>
            </p>
        </div>
        
        <style>
            .pc-gallery-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                margin-bottom: 15px;
            }
            .pc-gallery-item {
                position: relative;
                aspect-ratio: 1;
                border: 2px solid #ddd;
                border-radius: 4px;
                overflow: hidden;
                cursor: move;
            }
            .pc-gallery-item img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .pc-remove-gallery-image {
                position: absolute;
                top: 4px;
                right: 4px;
                background: #dc3545;
                color: white;
                border: none;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                padding: 0;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.2s;
            }
            .pc-gallery-item:hover .pc-remove-gallery-image {
                opacity: 1;
            }
            .pc-remove-gallery-image .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
        </style>
        <?php
    }
    
    /**
     * Render listing settings meta box
     *
     * @since 2.1.0
     * @param WP_Post $post Post object
     * @return void
     */
    public function render_settings_meta_box(\WP_Post $post): void {
        $featured = get_post_meta($post->ID, '_pc_featured', true);
        $expires_at = get_post_meta($post->ID, '_pc_expires_at', true);
        
        // Format date for input
        if ($expires_at) {
            $expires_date = date('Y-m-d', strtotime($expires_at));
        } else {
            $expires_date = date('Y-m-d', strtotime('+30 days'));
        }
        ?>
        
        <table class="form-table">
            <tr>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="pc_featured" 
                               value="1" 
                               <?php checked($featured, '1'); ?>>
                        <strong><?php esc_html_e('Featured Listing', 'premium-classifieds'); ?></strong>
                    </label>
                    <p class="description" style="margin-left: 22px;">
                        <?php esc_html_e('Show this listing prominently in search results', 'premium-classifieds'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="pc_expires_at"><?php esc_html_e('Expires On', 'premium-classifieds'); ?></label>
                </th>
            </tr>
            <tr>
                <td>
                    <input type="date" 
                           id="pc_expires_at" 
                           name="pc_expires_at" 
                           value="<?php echo esc_attr($expires_date); ?>" 
                           min="<?php echo date('Y-m-d'); ?>"
                           style="width: 100%;">
                    <p class="description">
                        <?php esc_html_e('Listing will be hidden after this date', 'premium-classifieds'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render statistics meta box
     *
     * @since 2.1.0
     * @param WP_Post $post Post object
     * @return void
     */
    public function render_stats_meta_box(\WP_Post $post): void {
        global $wpdb;
        
        $views = get_post_meta($post->ID, '_pc_views', true) ?: 0;
        $created = get_the_date('F j, Y', $post);
        
        // Count contacts revealed
        $contacts_revealed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pc_contact_access WHERE listing_id = %d",
            $post->ID
        ));
        
        // Count messages received
        $messages_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pc_messages WHERE listing_id = %d",
            $post->ID
        ));
        ?>
        
        <div class="pc-stats-grid" style="display: grid; gap: 15px;">
            <div class="pc-stat-item">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                    <span class="dashicons dashicons-visibility" style="color: #2563eb;"></span>
                    <strong><?php esc_html_e('Views', 'premium-classifieds'); ?></strong>
                </div>
                <div style="font-size: 24px; font-weight: 700; color: #1e293b;">
                    <?php echo esc_html(number_format($views)); ?>
                </div>
            </div>
            
            <div class="pc-stat-item">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                    <span class="dashicons dashicons-lock" style="color: #10b981;"></span>
                    <strong><?php esc_html_e('Contacts Revealed', 'premium-classifieds'); ?></strong>
                </div>
                <div style="font-size: 24px; font-weight: 700; color: #1e293b;">
                    <?php echo esc_html(number_format($contacts_revealed)); ?>
                </div>
                <p class="description" style="margin: 5px 0 0 0;">
                    <?php printf(
                        esc_html__('Revenue: $%s', 'premium-classifieds'),
                        number_format($contacts_revealed * get_option('pc_price_contact_reveal', 5), 2)
                    ); ?>
                </p>
            </div>
            
            <div class="pc-stat-item">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                    <span class="dashicons dashicons-email" style="color: #f59e0b;"></span>
                    <strong><?php esc_html_e('Messages', 'premium-classifieds'); ?></strong>
                </div>
                <div style="font-size: 24px; font-weight: 700; color: #1e293b;">
                    <?php echo esc_html(number_format($messages_count)); ?>
                </div>
            </div>
            
            <div class="pc-stat-item">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                    <span class="dashicons dashicons-calendar" style="color: #64748b;"></span>
                    <strong><?php esc_html_e('Created', 'premium-classifieds'); ?></strong>
                </div>
                <div style="font-size: 14px; color: #64748b;">
                    <?php echo esc_html($created); ?>
                </div>
            </div>
        </div>
        
        <style>
            .pc-stat-item {
                padding: 12px;
                background: #f8fafc;
                border-radius: 6px;
                border: 1px solid #e2e8f0;
            }
        </style>
        <?php
    }
    
    /**
     * Save meta box data
     *
     * @since 2.1.0
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @return void
     */
    public function save(int $post_id, \WP_Post $post): void {
        // Verify nonce
        if (!isset($_POST[self::NONCE_NAME]) || 
            !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save listing details
        $this->save_field($post_id, 'pc_price', 'sanitize_text_field');
        $this->save_field($post_id, 'pc_location', 'sanitize_text_field');
        $this->save_field($post_id, 'pc_website', 'esc_url_raw');
        
        // Save contact information
        $this->save_field($post_id, 'pc_phone', 'sanitize_text_field');
        $this->save_field($post_id, 'pc_email', 'sanitize_email');
        $this->save_field($post_id, 'pc_whatsapp', 'sanitize_text_field');
        
        // Save gallery images
        if (isset($_POST['pc_gallery_images'])) {
            $gallery_ids = array_filter(array_map('absint', explode(',', $_POST['pc_gallery_images'])));
            update_post_meta($post_id, '_pc_gallery_images', $gallery_ids);
        }
        
        // Save featured status
        $featured = isset($_POST['pc_featured']) ? '1' : '0';
        update_post_meta($post_id, '_pc_featured', $featured);
        
        // Save expiration date
        if (isset($_POST['pc_expires_at'])) {
            $expires_date = sanitize_text_field($_POST['pc_expires_at']);
            $expires_timestamp = strtotime($expires_date . ' 23:59:59');
            update_post_meta($post_id, '_pc_expires_at', date('Y-m-d H:i:s', $expires_timestamp));
        }
        
        // Initialize views if new post
        if (get_post_status($post_id) === 'publish' && !get_post_meta($post_id, '_pc_views', true)) {
            update_post_meta($post_id, '_pc_views', 0);
        }
    }
    
    /**
     * Save individual field with sanitization
     *
     * @since 2.1.0
     * @param int $post_id Post ID
     * @param string $field_name Field name
     * @param callable $sanitize_callback Sanitization function
     * @return void
     */
    private function save_field(int $post_id, string $field_name, callable $sanitize_callback): void {
        if (isset($_POST[$field_name])) {
            $value = call_user_func($sanitize_callback, $_POST[$field_name]);
            update_post_meta($post_id, '_' . $field_name, $value);
        }
    }
    
    /**
     * Enqueue admin scripts for gallery management
     *
     * @since 2.1.0
     * @param string $hook Current admin page
     * @return void
     */
    public function enqueue_admin_scripts(string $hook): void {
        global $post_type;
        
        if (('post.php' === $hook || 'post-new.php' === $hook) && 'listing' === $post_type) {
            wp_enqueue_media();
            wp_enqueue_script('jquery-ui-sortable');
            
            // Inline script for gallery management
            wp_add_inline_script('jquery', "
                jQuery(document).ready(function($) {
                    // Add images
                    $('#pc-add-gallery-images').on('click', function(e) {
                        e.preventDefault();
                        
                        var frame = wp.media({
                            title: 'Select Gallery Images',
                            button: { text: 'Add to Gallery' },
                            multiple: true
                        });
                        
                        frame.on('select', function() {
                            var attachments = frame.state().get('selection').toJSON();
                            var container = $('#pc-gallery-images');
                            var ids = $('#pc_gallery_ids').val().split(',').filter(Boolean);
                            
                            attachments.forEach(function(attachment) {
                                if (ids.indexOf(attachment.id.toString()) === -1) {
                                    ids.push(attachment.id);
                                    container.append(
                                        '<div class=\"pc-gallery-item\" data-attachment-id=\"' + attachment.id + '\">' +
                                        '<img src=\"' + attachment.sizes.thumbnail.url + '\" alt=\"\">' +
                                        '<button type=\"button\" class=\"pc-remove-gallery-image\" data-id=\"' + attachment.id + '\">' +
                                        '<span class=\"dashicons dashicons-no\"></span>' +
                                        '</button>' +
                                        '</div>'
                                    );
                                }
                            });
                            
                            $('#pc_gallery_ids').val(ids.join(','));
                        });
                        
                        frame.open();
                    });
                    
                    // Remove image
                    $(document).on('click', '.pc-remove-gallery-image', function(e) {
                        e.preventDefault();
                        var id = $(this).data('id');
                        $(this).closest('.pc-gallery-item').remove();
                        
                        var ids = $('#pc_gallery_ids').val().split(',').filter(function(val) {
                            return val != id;
                        });
                        $('#pc_gallery_ids').val(ids.join(','));
                    });
                    
                    // Sortable gallery
                    $('#pc-gallery-images').sortable({
                        update: function() {
                            var ids = [];
                            $('.pc-gallery-item').each(function() {
                                ids.push($(this).data('attachment-id'));
                            });
                            $('#pc_gallery_ids').val(ids.join(','));
                        }
                    });
                });
            ");
        }
    }
}
