<?php
/**
 * Frontend Dashboard Controller
 *
 * Handles the user dashboard accessible via [premium_dashboard] shortcode.
 * Provides AJAX-powered interface for:
 * - Managing listings (add, edit, delete)
 * - Viewing messages
 * - Managing favorites
 * - Payment history
 *
 * @package    PremiumClassifieds
 * @subpackage Frontend
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard Class
 *
 * @since 2.1.0
 */
class PC_Dashboard {
    
    /**
     * Current active tab
     *
     * @var string
     */
    private string $active_tab = 'my-listings';
    
    /**
     * Available dashboard tabs
     *
     * @var array<string, array>
     */
    private array $tabs = [];
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        $this->define_tabs();
    }
    
    /**
     * Define dashboard tabs configuration
     *
     * @since 2.1.0
     * @return void
     */
    private function define_tabs(): void {
        $this->tabs = [
            'my-listings' => [
                'title' => __('My Listings', 'premium-classifieds'),
                'icon' => 'dashicons-megaphone',
                'template' => 'my-listings.php',
            ],
            'add-listing' => [
                'title' => __('Add New', 'premium-classifieds'),
                'icon' => 'dashicons-plus-alt',
                'template' => 'add-listing.php',
            ],
            'inbox' => [
                'title' => __('Messages', 'premium-classifieds'),
                'icon' => 'dashicons-email',
                'template' => 'inbox.php',
                'badge' => $this->get_unread_count(),
            ],
            'favorites' => [
                'title' => __('Favorites', 'premium-classifieds'),
                'icon' => 'dashicons-heart',
                'template' => 'favorites.php',
            ],
            'payments' => [
                'title' => __('Payments', 'premium-classifieds'),
                'icon' => 'dashicons-money-alt',
                'template' => 'payment-history.php',
            ],
            'settings' => [
                'title' => __('Settings', 'premium-classifieds'),
                'icon' => 'dashicons-admin-settings',
                'template' => 'settings.php',
            ],
        ];
    }
    
    /**
     * Render dashboard main layout
     *
     * @since 2.1.0
     * @return string HTML output
     */
    public function render(): string {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        ob_start();
        include PC_TEMPLATES_DIR . 'dashboard/main-layout.php';
        return ob_get_clean();
    }
    
    /**
     * Get template path for current tab
     *
     * @since 2.1.0
     * @param string $tab Tab identifier
     * @return string Template file path
     */
    private function get_tab_template(string $tab): string {
        if (!isset($this->tabs[$tab])) {
            $tab = 'my-listings';
        }
        
        return PC_TEMPLATES_DIR . 'dashboard/' . $this->tabs[$tab]['template'];
    }
    
    /**
     * AJAX: Load dashboard tab content
     *
     * @since 2.1.0
     * @return void
     */
    public static function load_tab(): void {
        // Validate request
        PC_Ajax_Handler::validate_required(['tab']);
        $tab = PC_Ajax_Handler::get_post('tab', 'my-listings', 'text');
        
        $instance = new self();
        $template = $instance->get_tab_template($tab);
        
        if (!file_exists($template)) {
            PC_Ajax_Handler::send_error('Template not found', 404);
        }
        
        ob_start();
        include $template;
        $html = ob_get_clean();
        
        PC_Ajax_Handler::send_success(['html' => $html]);
    }
    
    /**
     * AJAX: Add new listing
     *
     * @since 2.1.0
     * @return void
     */
    public static function add_listing(): void {
        // Validate required fields
        PC_Ajax_Handler::validate_required(['title', 'content', 'category']);
        
        $user_id = get_current_user_id();
        
        // Check if user can create listings
        if (!current_user_can('create_listings')) {
            PC_Ajax_Handler::send_error(__('You do not have permission to create listings.', 'premium-classifieds'), 403);
        }
        
        // Rate limiting check
        if (!PC_Security::check_rate_limit('create_listing_' . $user_id, 3, 3600)) {
            PC_Ajax_Handler::send_error(__('You can only create 3 listings per hour. Please try again later.', 'premium-classifieds'), 429);
        }
        
        // Sanitize inputs
        $title = PC_Ajax_Handler::get_post('title', '', 'text');
        $content = PC_Ajax_Handler::get_post('content', '', 'html');
        $category = PC_Ajax_Handler::get_post('category', 0, 'int');
        $tags = PC_Ajax_Handler::get_post('tags', [], 'array');
        $price = PC_Ajax_Handler::get_post('price', '', 'text');
        $location = PC_Ajax_Handler::get_post('location', '', 'text');
        $phone = PC_Ajax_Handler::get_post('phone', '', 'text');
        $email = PC_Ajax_Handler::get_post('email', '', 'email');
        
        // Validate
        if (empty($title) || strlen($title) < 5) {
            PC_Ajax_Handler::send_error(__('Title must be at least 5 characters long.', 'premium-classifieds'), 400);
        }
        
        if (empty($content) || strlen($content) < 50) {
            PC_Ajax_Handler::send_error(__('Description must be at least 50 characters long.', 'premium-classifieds'), 400);
        }
        
        // Check if moderation is required
        $require_approval = get_option('pc_require_approval', '1');
        $post_status = $require_approval === '1' ? 'pending' : 'publish';
        
        // Create post
        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $post_status,
            'post_author' => $user_id,
            'post_type' => 'listing',
        ];
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            PC_Ajax_Handler::send_error($post_id->get_error_message(), 500);
        }
        
        // Set category
        if ($category > 0) {
            wp_set_object_terms($post_id, [$category], 'listing_category');
        }
        
        // Set tags
        if (!empty($tags)) {
            wp_set_object_terms($post_id, $tags, 'listing_tag');
        }
        
        // Save meta fields
        update_post_meta($post_id, '_pc_price', sanitize_text_field($price));
        update_post_meta($post_id, '_pc_location', sanitize_text_field($location));
        update_post_meta($post_id, '_pc_phone', sanitize_text_field($phone));
        update_post_meta($post_id, '_pc_email', sanitize_email($email));
        update_post_meta($post_id, '_pc_views', 0);
        update_post_meta($post_id, '_pc_featured', 0);
        update_post_meta($post_id, '_pc_expires_at', date('Y-m-d H:i:s', strtotime('+30 days')));
        
        // Handle image uploads (if any)
        if (!empty($_FILES['images'])) {
            self::process_image_uploads($post_id, $_FILES['images']);
        }
        
        // Send notification email to admin
        if (get_option('pc_notify_new_listing', '1') === '1') {
            self::notify_admin_new_listing($post_id);
        }
        
        // Log event
        PC_Security::log_security_event('Listing created', [
            'listing_id' => $post_id,
            'status' => $post_status
        ]);
        
        PC_Ajax_Handler::send_success([
            'message' => $post_status === 'pending' 
                ? __('Listing submitted for review. You will be notified once approved.', 'premium-classifieds')
                : __('Listing published successfully!', 'premium-classifieds'),
            'listing_id' => $post_id,
            'redirect' => add_query_arg('tab', 'my-listings', get_permalink())
        ]);
    }
    
    /**
     * AJAX: Edit existing listing
     *
     * @since 2.1.0
     * @return void
     */
    public static function edit_listing(): void {
        PC_Ajax_Handler::validate_required(['listing_id', 'title', 'content']);
        
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        $user_id = get_current_user_id();
        
        // Verify ownership
        if (!PC_Security::user_owns_listing($listing_id, $user_id)) {
            PC_Ajax_Handler::send_error(__('You do not own this listing.', 'premium-classifieds'), 403);
        }
        
        // Sanitize inputs
        $title = PC_Ajax_Handler::get_post('title', '', 'text');
        $content = PC_Ajax_Handler::get_post('content', '', 'html');
        $category = PC_Ajax_Handler::get_post('category', 0, 'int');
        $price = PC_Ajax_Handler::get_post('price', '', 'text');
        $location = PC_Ajax_Handler::get_post('location', '', 'text');
        $phone = PC_Ajax_Handler::get_post('phone', '', 'text');
        $email = PC_Ajax_Handler::get_post('email', '', 'email');
        
        // Update post
        $result = wp_update_post([
            'ID' => $listing_id,
            'post_title' => $title,
            'post_content' => $content,
        ], true);
        
        if (is_wp_error($result)) {
            PC_Ajax_Handler::send_error($result->get_error_message(), 500);
        }
        
        // Update category
        if ($category > 0) {
            wp_set_object_terms($listing_id, [$category], 'listing_category');
        }
        
        // Update meta
        update_post_meta($listing_id, '_pc_price', sanitize_text_field($price));
        update_post_meta($listing_id, '_pc_location', sanitize_text_field($location));
        update_post_meta($listing_id, '_pc_phone', sanitize_text_field($phone));
        update_post_meta($listing_id, '_pc_email', sanitize_email($email));
        
        PC_Ajax_Handler::send_success([
            'message' => __('Listing updated successfully!', 'premium-classifieds'),
            'listing_id' => $listing_id
        ]);
    }
    
    /**
     * AJAX: Delete listing
     *
     * @since 2.1.0
     * @return void
     */
    public static function delete_listing(): void {
        PC_Ajax_Handler::validate_required(['listing_id']);
        
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        $user_id = get_current_user_id();
        
        // Verify ownership
        if (!PC_Security::user_owns_listing($listing_id, $user_id)) {
            PC_Ajax_Handler::send_error(__('You do not own this listing.', 'premium-classifieds'), 403);
        }
        
        // Move to trash (soft delete)
        $result = wp_trash_post($listing_id);
        
        if (!$result) {
            PC_Ajax_Handler::send_error(__('Failed to delete listing.', 'premium-classifieds'), 500);
        }
        
        PC_Security::log_security_event('Listing deleted', ['listing_id' => $listing_id]);
        
        PC_Ajax_Handler::send_success([
            'message' => __('Listing deleted successfully.', 'premium-classifieds')
        ]);
    }
    
    /**
     * AJAX: Upload images
     *
     * @since 2.1.0
     * @return void
     */
    public static function upload_image(): void {
        PC_Ajax_Handler::validate_required(['listing_id']);
        
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        $user_id = get_current_user_id();
        
        // Verify ownership
        if (!PC_Security::user_owns_listing($listing_id, $user_id)) {
            PC_Ajax_Handler::send_error(__('You do not own this listing.', 'premium-classifieds'), 403);
        }
        
        if (empty($_FILES['image'])) {
            PC_Ajax_Handler::send_error(__('No image uploaded.', 'premium-classifieds'), 400);
        }
        
        // Check max images
        $max_images = (int) get_option('pc_max_images_per_listing', 10);
        $current_images = get_post_meta($listing_id, '_pc_gallery_images', true) ?: [];
        
        if (count($current_images) >= $max_images) {
            PC_Ajax_Handler::send_error(
                sprintf(__('Maximum %d images allowed per listing.', 'premium-classifieds'), $max_images),
                400
            );
        }
        
        // Handle upload
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $attachment_id = media_handle_upload('image', $listing_id);
        
        if (is_wp_error($attachment_id)) {
            PC_Ajax_Handler::send_error($attachment_id->get_error_message(), 500);
        }
        
        // Add to gallery
        $current_images[] = $attachment_id;
        update_post_meta($listing_id, '_pc_gallery_images', $current_images);
        
        // Set as featured image if first image
        if (count($current_images) === 1) {
            set_post_thumbnail($listing_id, $attachment_id);
        }
        
        PC_Ajax_Handler::send_success([
            'message' => __('Image uploaded successfully.', 'premium-classifieds'),
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id)
        ]);
    }
    
    /**
     * AJAX: Add to favorites
     *
     * @since 2.1.0
     * @return void
     */
    public static function add_favorite(): void {
        PC_Ajax_Handler::validate_required(['listing_id']);
        
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        $user_id = get_current_user_id();
        
        $favorites = get_user_meta($user_id, '_pc_favorites', true) ?: [];
        
        if (!in_array($listing_id, $favorites)) {
            $favorites[] = $listing_id;
            update_user_meta($user_id, '_pc_favorites', $favorites);
        }
        
        PC_Ajax_Handler::send_success([
            'message' => __('Added to favorites.', 'premium-classifieds')
        ]);
    }
    
    /**
     * AJAX: Remove from favorites
     *
     * @since 2.1.0
     * @return void
     */
    public static function remove_favorite(): void {
        PC_Ajax_Handler::validate_required(['listing_id']);
        
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        $user_id = get_current_user_id();
        
        $favorites = get_user_meta($user_id, '_pc_favorites', true) ?: [];
        $favorites = array_diff($favorites, [$listing_id]);
        
        update_user_meta($user_id, '_pc_favorites', $favorites);
        
        PC_Ajax_Handler::send_success([
            'message' => __('Removed from favorites.', 'premium-classifieds')
        ]);
    }
    
    /**
     * AJAX: Load favorites
     *
     * @since 2.1.0
     * @return void
     */
    public static function load_favorites(): void {
        $user_id = get_current_user_id();
        $favorites = get_user_meta($user_id, '_pc_favorites', true) ?: [];
        
        if (empty($favorites)) {
            PC_Ajax_Handler::send_success(['listings' => []]);
        }
        
        $query = new WP_Query([
            'post_type' => 'listing',
            'post__in' => $favorites,
            'posts_per_page' => -1,
        ]);
        
        $listings = [];
        while ($query->have_posts()) {
            $query->the_post();
            $listings[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'url' => get_permalink(),
                'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'medium'),
                'price' => get_post_meta(get_the_ID(), '_pc_price', true),
            ];
        }
        wp_reset_postdata();
        
        PC_Ajax_Handler::send_success(['listings' => $listings]);
    }
    
    /**
     * Get unread message count for current user
     *
     * @since 2.1.0
     * @return int Unread count
     */
    private function get_unread_count(): int {
        global $wpdb;
        $user_id = get_current_user_id();
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pc_messages 
             WHERE to_user_id = %d AND is_read = 0",
            $user_id
        ));
        
        return (int) $count;
    }
    
    /**
     * Process multiple image uploads
     *
     * @since 2.1.0
     * @param int $post_id Post ID
     * @param array $files $_FILES array
     * @return void
     */
    private static function process_image_uploads(int $post_id, array $files): void {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $gallery_images = [];
        
        // Handle multiple file uploads
        if (isset($files['name']) && is_array($files['name'])) {
            foreach ($files['name'] as $key => $value) {
                if ($files['error'][$key] === 0) {
                    $file = [
                        'name' => $files['name'][$key],
                        'type' => $files['type'][$key],
                        'tmp_name' => $files['tmp_name'][$key],
                        'error' => $files['error'][$key],
                        'size' => $files['size'][$key]
                    ];
                    
                    $_FILES = ['upload_file' => $file];
                    $attachment_id = media_handle_upload('upload_file', $post_id);
                    
                    if (!is_wp_error($attachment_id)) {
                        $gallery_images[] = $attachment_id;
                    }
                }
            }
        }
        
        if (!empty($gallery_images)) {
            update_post_meta($post_id, '_pc_gallery_images', $gallery_images);
            set_post_thumbnail($post_id, $gallery_images[0]);
        }
    }
    
    /**
     * Send email notification to admin about new listing
     *
     * @since 2.1.0
     * @param int $post_id Post ID
     * @return void
     */
    private static function notify_admin_new_listing(int $post_id): void {
        $admin_email = get_option('pc_admin_email', get_option('admin_email'));
        $post = get_post($post_id);
        $author = get_userdata($post->post_author);
        
        $subject = sprintf(
            __('[%s] New Listing Submitted', 'premium-classifieds'),
            get_bloginfo('name')
        );
        
        $message = sprintf(
            __("A new listing has been submitted:\n\nTitle: %s\nAuthor: %s\nStatus: %s\n\nView: %s\nModerate: %s", 'premium-classifieds'),
            $post->post_title,
            $author->display_name,
            $post->post_status,
            get_permalink($post_id),
            admin_url('post.php?post=' . $post_id . '&action=edit')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
}
