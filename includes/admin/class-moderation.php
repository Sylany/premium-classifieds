<?php
/**
 * Listing Moderation Controller
 *
 * Handles approval/rejection of pending listings.
 * Provides quick moderation interface for administrators.
 *
 * @package    PremiumClassifieds
 * @subpackage Admin
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Moderation Class
 *
 * @since 2.1.0
 */
class PC_Moderation {
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_moderation_meta_box']);
        add_action('admin_notices', [$this, 'show_pending_notice']);
    }
    
    /**
     * Add moderation meta box
     *
     * @since 2.1.0
     * @return void
     */
    public function add_moderation_meta_box(): void {
        add_meta_box(
            'pc_moderation_actions',
            __('Moderation Actions', 'premium-classifieds'),
            [$this, 'render_moderation_meta_box'],
            'listing',
            'side',
            'high'
        );
    }
    
    /**
     * Render moderation meta box
     *
     * @since 2.1.0
     * @param WP_Post $post Post object
     * @return void
     */
    public function render_moderation_meta_box(\WP_Post $post): void {
        if ($post->post_status !== 'pending') {
            ?>
            <p><?php esc_html_e('This listing has already been moderated.', 'premium-classifieds'); ?></p>
            <p><strong><?php esc_html_e('Status:', 'premium-classifieds'); ?></strong> <?php echo esc_html(ucfirst($post->post_status)); ?></p>
            <?php
            return;
        }
        
        wp_nonce_field('pc_moderate_listing', 'pc_moderation_nonce');
        
        $author = get_userdata($post->post_author);
        ?>
        
        <div class="pc-moderation-info">
            <p>
                <strong><?php esc_html_e('Submitted by:', 'premium-classifieds'); ?></strong><br>
                <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $post->post_author)); ?>">
                    <?php echo esc_html($author->display_name); ?>
                </a>
            </p>
            <p>
                <strong><?php esc_html_e('Submitted on:', 'premium-classifieds'); ?></strong><br>
                <?php echo esc_html(get_the_date('', $post)); ?>
            </p>
        </div>
        
        <div class="pc-moderation-actions">
            <button type="button" 
                    class="button button-primary button-large pc-approve-listing" 
                    data-listing-id="<?php echo esc_attr($post->ID); ?>"
                    style="width: 100%; margin-bottom: 10px;">
                <span class="dashicons dashicons-yes" style="margin-top: 4px;"></span>
                <?php esc_html_e('Approve & Publish', 'premium-classifieds'); ?>
            </button>
            
            <button type="button" 
                    class="button button-secondary button-large pc-reject-listing" 
                    data-listing-id="<?php echo esc_attr($post->ID); ?>"
                    style="width: 100%;">
                <span class="dashicons dashicons-no" style="margin-top: 4px;"></span>
                <?php esc_html_e('Reject Listing', 'premium-classifieds'); ?>
            </button>
        </div>
        
        <style>
        .pc-moderation-info {
            background: #f6f7f7;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .pc-moderation-info p {
            margin: 8px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Show pending listings notice
     *
     * @since 2.1.0
     * @return void
     */
    public function show_pending_notice(): void {
        if (!current_user_can('moderate_listings')) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['dashboard', 'edit-listing'])) {
            return;
        }
        
        $pending_count = wp_count_posts('listing')->pending;
        
        if ($pending_count > 0) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('Premium Classifieds:', 'premium-classifieds'); ?></strong>
                    <?php
                    printf(
                        esc_html(_n('%s listing is awaiting moderation.', '%s listings are awaiting moderation.', $pending_count, 'premium-classifieds')),
                        '<strong>' . number_format_i18n($pending_count) . '</strong>'
                    );
                    ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_status=pending&post_type=listing')); ?>" class="button button-small" style="margin-left: 10px;">
                        <?php esc_html_e('Review Now', 'premium-classifieds'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * AJAX: Moderate listing
     *
     * @since 2.1.0
     * @return void
     */
    public static function moderate(): void {
        PC_Ajax_Handler::validate_required(['listing_id', 'action_type']);
        
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        $action = PC_Ajax_Handler::get_post('action_type', '', 'text');
        
        // Verify listing exists
        $listing = get_post($listing_id);
        if (!$listing || $listing->post_type !== 'listing') {
            PC_Ajax_Handler::send_error(__('Invalid listing.', 'premium-classifieds'), 400);
        }
        
        // Check if already moderated
        if ($listing->post_status !== 'pending') {
            PC_Ajax_Handler::send_error(__('This listing has already been moderated.', 'premium-classifieds'), 400);
        }
        
        $author = get_userdata($listing->post_author);
        
        switch ($action) {
            case 'approve':
                wp_update_post([
                    'ID'          => $listing_id,
                    'post_status' => 'publish',
                ]);
                
                // Send approval email
                self::send_approval_email($listing_id, $author);
                
                PC_Ajax_Handler::send_success([
                    'message' => __('Listing approved and published successfully!', 'premium-classifieds'),
                ]);
                break;
                
            case 'reject':
                wp_update_post([
                    'ID'          => $listing_id,
                    'post_status' => 'draft',
                ]);
                
                // Send rejection email
                self::send_rejection_email($listing_id, $author);
                
                PC_Ajax_Handler::send_success([
                    'message' => __('Listing rejected.', 'premium-classifieds'),
                ]);
                break;
                
            default:
                PC_Ajax_Handler::send_error(__('Invalid action.', 'premium-classifieds'), 400);
        }
    }
    
    /**
     * Send approval email to listing owner
     *
     * @since 2.1.0
     * @param int $listing_id Listing ID
     * @param WP_User $author Author user object
     * @return void
     */
    private static function send_approval_email(int $listing_id, \WP_User $author): void {
        $listing = get_post($listing_id);
        
        $subject = sprintf(
            __('[%s] Your listing has been approved', 'premium-classifieds'),
            get_bloginfo('name')
        );
        
        $message = sprintf(
            __("Good news! Your listing '%s' has been approved and is now live.\n\nView your listing: %s\n\nThank you for using %s!", 'premium-classifieds'),
            $listing->post_title,
            get_permalink($listing_id),
            get_bloginfo('name')
        );
        
        wp_mail($author->user_email, $subject, $message);
    }
    
    /**
     * Send rejection email to listing owner
     *
     * @since 2.1.0
     * @param int $listing_id Listing ID
     * @param WP_User $author Author user object
     * @return void
     */
    private static function send_rejection_email(int $listing_id, \WP_User $author): void {
        $listing = get_post($listing_id);
        
        $subject = sprintf(
            __('[%s] Your listing was not approved', 'premium-classifieds'),
            get_bloginfo('name')
        );
        
        $message = sprintf(
            __("We're sorry, but your listing '%s' could not be approved at this time.\n\nCommon reasons for rejection:\n- Inappropriate content\n- Incomplete information\n- Violates our terms of service\n\nYou can edit and resubmit your listing from your dashboard.\n\nDashboard: %s", 'premium-classifieds'),
            $listing->post_title,
            home_url('/dashboard/')
        );
        
        wp_mail($author->user_email, $subject, $message);
    }
}
