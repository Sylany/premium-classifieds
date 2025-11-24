<?php
/**
 * Paywall Component
 *
 * Displays payment prompt for contact information access.
 *
 * @package    PremiumClassifieds
 * @subpackage Templates/Partials
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$contact_price = (float) get_option('pc_price_contact_reveal', 5.00);
$is_logged_in = is_user_logged_in();
?>

<div class="pc-paywall-container">
    <div class="pc-paywall-icon">
        <span class="dashicons dashicons-lock"></span>
    </div>
    
    <h3 class="pc-paywall-title">
        <?php esc_html_e('Contact Information Hidden', 'premium-classifieds'); ?>
    </h3>
    
    <p class="pc-paywall-description">
        <?php esc_html_e('Pay once to unlock lifetime access to this listing\'s contact details and messaging.', 'premium-classifieds'); ?>
    </p>
    
    <ul class="pc-paywall-features">
        <li>
            <span class="dashicons dashicons-phone"></span>
            <?php esc_html_e('Phone number', 'premium-classifieds'); ?>
        </li>
        <li>
            <span class="dashicons dashicons-email"></span>
            <?php esc_html_e('Email address', 'premium-classifieds'); ?>
        </li>
        <?php if (get_post_meta($listing_id, '_pc_whatsapp', true)): ?>
            <li>
                <span class="dashicons dashicons-admin-comments"></span>
                <?php esc_html_e('WhatsApp number', 'premium-classifieds'); ?>
            </li>
        <?php endif; ?>
        <li>
            <span class="dashicons dashicons-email-alt"></span>
            <?php esc_html_e('Send unlimited messages', 'premium-classifieds'); ?>
        </li>
    </ul>
    
    <div class="pc-paywall-price">
        $<?php echo esc_html(number_format($contact_price, 2)); ?>
        <small><?php esc_html_e('one-time payment', 'premium-classifieds'); ?></small>
    </div>
    
    <div class="pc-paywall-cta">
        <?php if ($is_logged_in): ?>
            <button class="pc-reveal-contact-btn" data-listing-id="<?php echo esc_attr($listing_id); ?>">
                <span class="dashicons dashicons-unlock"></span>
                <?php esc_html_e('Reveal Contact Information', 'premium-classifieds'); ?>
            </button>
        <?php else: ?>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="pc-btn pc-btn-primary">
                <span class="dashicons dashicons-admin-users"></span>
                <?php esc_html_e('Login to Purchase', 'premium-classifieds'); ?>
            </a>
            <p class="pc-login-prompt">
                <?php esc_html_e('Don\'t have an account?', 'premium-classifieds'); ?>
                <a href="<?php echo esc_url(wp_registration_url()); ?>"><?php esc_html_e('Sign up', 'premium-classifieds'); ?></a>
            </p>
        <?php endif; ?>
    </div>
    
    <p class="pc-paywall-footer">
        <span class="dashicons dashicons-shield-alt"></span>
        <?php esc_html_e('Secure payment powered by Stripe', 'premium-classifieds'); ?>
    </p>
    
    <div class="pc-paywall-benefits">
        <h4><?php esc_html_e('Why pay to reveal?', 'premium-classifieds'); ?></h4>
        <div class="pc-benefits-grid">
            <div class="pc-benefit-item">
                <span class="dashicons dashicons-yes-alt"></span>
                <span><?php esc_html_e('Verified buyers only', 'premium-classifieds'); ?></span>
            </div>
            <div class="pc-benefit-item">
                <span class="dashicons dashicons-yes-alt"></span>
                <span><?php esc_html_e('Reduce spam inquiries', 'premium-classifieds'); ?></span>
            </div>
            <div class="pc-benefit-item">
                <span class="dashicons dashicons-yes-alt"></span>
                <span><?php esc_html_e('Quality connections', 'premium-classifieds'); ?></span>
            </div>
            <div class="pc-benefit-item">
                <span class="dashicons dashicons-yes-alt"></span>
                <span><?php esc_html_e('Instant access', 'premium-classifieds'); ?></span>
            </div>
        </div>
    </div>
</div>
