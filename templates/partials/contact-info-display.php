<?php
/**
 * Contact Information Display
 *
 * Shows unlocked contact details after payment or for listing owner.
 *
 * @package    PremiumClassifieds
 * @subpackage Templates/Partials
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Variables expected from parent template:
// $listing_id, $phone, $email, $whatsapp
?>

<div class="pc-contact-info-unlocked">
    <div class="pc-contact-info-header">
        <span class="dashicons dashicons-yes-alt"></span>
        <h3><?php esc_html_e('Contact Information', 'premium-classifieds'); ?></h3>
    </div>
    
    <div class="pc-contact-info-list">
        
        <?php if ($phone): ?>
            <div class="pc-contact-info-item">
                <div class="pc-contact-icon">
                    <span class="dashicons dashicons-phone"></span>
                </div>
                <div class="pc-contact-details">
                    <strong><?php esc_html_e('Phone', 'premium-classifieds'); ?></strong>
                    <a href="tel:<?php echo esc_attr($phone); ?>" class="pc-contact-link">
                        <?php echo esc_html($phone); ?>
                    </a>
                </div>
                <button class="pc-btn-copy" data-copy="<?php echo esc_attr($phone); ?>" title="<?php esc_attr_e('Copy', 'premium-classifieds'); ?>">
                    <span class="dashicons dashicons-admin-page"></span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($email): ?>
            <div class="pc-contact-info-item">
                <div class="pc-contact-icon">
                    <span class="dashicons dashicons-email"></span>
                </div>
                <div class="pc-contact-details">
                    <strong><?php esc_html_e('Email', 'premium-classifieds'); ?></strong>
                    <a href="mailto:<?php echo esc_attr($email); ?>" class="pc-contact-link">
                        <?php echo esc_html($email); ?>
                    </a>
                </div>
                <button class="pc-btn-copy" data-copy="<?php echo esc_attr($email); ?>" title="<?php esc_attr_e('Copy', 'premium-classifieds'); ?>">
                    <span class="dashicons dashicons-admin-page"></span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($whatsapp): ?>
            <div class="pc-contact-info-item">
                <div class="pc-contact-icon">
                    <span class="dashicons dashicons-admin-comments"></span>
                </div>
                <div class="pc-contact-details">
                    <strong><?php esc_html_e('WhatsApp', 'premium-classifieds'); ?></strong>
                    <a href="https://wa.me/<?php echo esc_attr($whatsapp); ?>" 
                       target="_blank" 
                       rel="nofollow" 
                       class="pc-contact-link">
                        <?php echo esc_html($whatsapp); ?>
                    </a>
                </div>
                <button class="pc-btn-copy" data-copy="<?php echo esc_attr($whatsapp); ?>" title="<?php esc_attr_e('Copy', 'premium-classifieds'); ?>">
                    <span class="dashicons dashicons-admin-page"></span>
                </button>
            </div>
        <?php endif; ?>
        
    </div>
    
    <?php if (!$is_owner): ?>
        <!-- Message Form for Buyers -->
        <div class="pc-message-form-container">
            <h4><?php esc_html_e('Send a Message', 'premium-classifieds'); ?></h4>
            <form id="pc-contact-message-form" class="pc-contact-form">
                <input type="hidden" name="listing_id" value="<?php echo esc_attr($listing_id); ?>">
                
                <div class="pc-form-group">
                    <label for="message_subject"><?php esc_html_e('Subject', 'premium-classifieds'); ?></label>
                    <input type="text" 
                           id="message_subject" 
                           name="subject" 
                           class="pc-input" 
                           placeholder="<?php esc_attr_e('What would you like to discuss?', 'premium-classifieds'); ?>"
                           required>
                </div>
                
                <div class="pc-form-group">
                    <label for="message_content"><?php esc_html_e('Message', 'premium-classifieds'); ?></label>
                    <textarea id="message_content" 
                              name="message" 
                              class="pc-textarea" 
                              rows="5"
                              placeholder="<?php esc_attr_e('Write your message here...', 'premium-classifieds'); ?>"
                              required></textarea>
                </div>
                
                <button type="submit" class="pc-btn pc-btn-primary">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e('Send Message', 'premium-classifieds'); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="pc-contact-footer">
        <p>
            <span class="dashicons dashicons-info"></span>
            <?php 
            if ($is_owner) {
                esc_html_e('This is how buyers will see your contact information after payment.', 'premium-classifieds');
            } else {
                esc_html_e('You have lifetime access to this contact information.', 'premium-classifieds');
            }
            ?>
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Copy to clipboard functionality
    $('.pc-btn-copy').on('click', function() {
        const text = $(this).data('copy');
        const $button = $(this);
        
        navigator.clipboard.writeText(text).then(function() {
            $button.html('<span class="dashicons dashicons-yes"></span>');
            setTimeout(function() {
                $button.html('<span class="dashicons dashicons-admin-page"></span>');
            }, 2000);
        }).catch(function(err) {
            alert('Failed to copy: ' + err);
        });
    });
    
    // Message form submission
    $('#pc-contact-message-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update pc-spin"></span> Sending...');
        
        $.ajax({
            url: pcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pc_send_message',
                nonce: pcData.nonce,
                listing_id: $form.find('[name="listing_id"]').val(),
                subject: $form.find('[name="subject"]').val(),
                message: $form.find('[name="message"]').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(pcData.strings.messageSent || 'Message sent successfully!');
                    $form[0].reset();
                } else {
                    alert(response.data.message || 'Failed to send message');
                }
            },
            error: function() {
                alert('Network error. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>
