<?php
/**
 * Dashboard Settings Template
 *
 * @package Premium_Classifieds
 */

defined('ABSPATH') || exit;

$user_id = get_current_user_id();
$user = wp_get_current_user();

// Get user preferences
$notify_messages = get_user_meta($user_id, '_pc_notify_messages', true) !== '0';
$notify_purchases = get_user_meta($user_id, '_pc_notify_purchases', true) !== '0';
$notify_listing_status = get_user_meta($user_id, '_pc_notify_listing_status', true) !== '0';
$public_profile = get_user_meta($user_id, '_pc_public_profile', true) !== '0';
?>

<div class="pc-section">
    <div class="pc-section-header">
        <h2><?php esc_html_e('Account Settings', 'premium-classifieds'); ?></h2>
    </div>

    <div class="pc-settings-container">
        <!-- Profile Settings -->
        <div class="pc-settings-section">
            <h3 class="pc-settings-title">
                <span class="dashicons dashicons-admin-users"></span>
                <?php esc_html_e('Profile Information', 'premium-classifieds'); ?>
            </h3>
            
            <form id="pc-profile-form" class="pc-settings-form">
                <div class="pc-form-group">
                    <label class="pc-label" for="display_name">
                        <?php esc_html_e('Display Name', 'premium-classifieds'); ?>
                        <span class="pc-required">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="display_name" 
                        id="display_name"
                        class="pc-input" 
                        value="<?php echo esc_attr($user->display_name); ?>"
                        required
                    >
                    <small class="pc-help-text">
                        <?php esc_html_e('This name will be visible to other users on your listings.', 'premium-classifieds'); ?>
                    </small>
                </div>
                
                <div class="pc-form-group">
                    <label class="pc-label" for="user_email">
                        <?php esc_html_e('Email Address', 'premium-classifieds'); ?>
                        <span class="pc-required">*</span>
                    </label>
                    <input 
                        type="email" 
                        name="user_email" 
                        id="user_email"
                        class="pc-input" 
                        value="<?php echo esc_attr($user->user_email); ?>"
                        required
                    >
                    <small class="pc-help-text">
                        <?php esc_html_e('Used for notifications and account recovery.', 'premium-classifieds'); ?>
                    </small>
                </div>

                <div class="pc-form-group">
                    <label class="pc-label" for="description">
                        <?php esc_html_e('Bio / About', 'premium-classifieds'); ?>
                    </label>
                    <textarea 
                        name="description" 
                        id="description"
                        class="pc-textarea" 
                        rows="4"
                        maxlength="500"
                        placeholder="<?php esc_attr_e('Tell others about yourself...', 'premium-classifieds'); ?>"
                    ><?php echo esc_textarea($user->description); ?></textarea>
                    <div class="pc-char-counter">
                        <span id="bio-char-count">0</span> / 500
                    </div>
                </div>

                <div class="pc-form-group pc-checkbox-group">
                    <label class="pc-checkbox-label">
                        <input 
                            type="checkbox" 
                            name="public_profile" 
                            id="public_profile"
                            value="1"
                            <?php checked($public_profile, true); ?>
                        >
                        <span><?php esc_html_e('Make my profile public', 'premium-classifieds'); ?></span>
                    </label>
                    <small class="pc-help-text">
                        <?php esc_html_e('Allow others to view your profile and all your active listings.', 'premium-classifieds'); ?>
                    </small>
                </div>

                <button type="submit" class="pc-btn pc-btn-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e('Save Profile', 'premium-classifieds'); ?>
                </button>
            </form>
        </div>

        <!-- Notification Preferences -->
        <div class="pc-settings-section">
            <h3 class="pc-settings-title">
                <span class="dashicons dashicons-email-alt"></span>
                <?php esc_html_e('Notification Preferences', 'premium-classifieds'); ?>
            </h3>
            
            <form id="pc-notifications-form" class="pc-settings-form">
                <p class="pc-section-description">
                    <?php esc_html_e('Choose which email notifications you want to receive.', 'premium-classifieds'); ?>
                </p>

                <div class="pc-notification-item">
                    <div class="pc-notification-header">
                        <label class="pc-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="notify_messages" 
                                value="1"
                                <?php checked($notify_messages, true); ?>
                            >
                            <span class="pc-notification-title">
                                <?php esc_html_e('New Messages', 'premium-classifieds'); ?>
                            </span>
                        </label>
                        <span class="pc-notification-badge">Email</span>
                    </div>
                    <p class="pc-notification-description">
                        <?php esc_html_e('Get notified when someone sends you a message about your listings.', 'premium-classifieds'); ?>
                    </p>
                </div>

                <div class="pc-notification-item">
                    <div class="pc-notification-header">
                        <label class="pc-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="notify_purchases" 
                                value="1"
                                <?php checked($notify_purchases, true); ?>
                            >
                            <span class="pc-notification-title">
                                <?php esc_html_e('Contact Purchases', 'premium-classifieds'); ?>
                            </span>
                        </label>
                        <span class="pc-notification-badge">Email</span>
                    </div>
                    <p class="pc-notification-description">
                        <?php esc_html_e('Get notified when someone purchases access to your contact information.', 'premium-classifieds'); ?>
                    </p>
                </div>

                <div class="pc-notification-item">
                    <div class="pc-notification-header">
                        <label class="pc-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="notify_listing_status" 
                                value="1"
                                <?php checked($notify_listing_status, true); ?>
                            >
                            <span class="pc-notification-title">
                                <?php esc_html_e('Listing Status Changes', 'premium-classifieds'); ?>
                            </span>
                        </label>
                        <span class="pc-notification-badge">Email</span>
                    </div>
                    <p class="pc-notification-description">
                        <?php esc_html_e('Get notified when your listings are approved, rejected, or expired.', 'premium-classifieds'); ?>
                    </p>
                </div>

                <button type="submit" class="pc-btn pc-btn-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e('Save Preferences', 'premium-classifieds'); ?>
                </button>
            </form>
        </div>

        <!-- Privacy & Security -->
        <div class="pc-settings-section">
            <h3 class="pc-settings-title">
                <span class="dashicons dashicons-shield"></span>
                <?php esc_html_e('Privacy & Security', 'premium-classifieds'); ?>
            </h3>
            
            <div class="pc-info-box">
                <p>
                    <strong><?php esc_html_e('Password', 'premium-classifieds'); ?></strong><br>
                    <?php esc_html_e('To change your password, please visit your WordPress profile settings.', 'premium-classifieds'); ?>
                </p>
                <a href="<?php echo esc_url(admin_url('profile.php')); ?>" class="pc-btn pc-btn-secondary">
                    <span class="dashicons dashicons-lock"></span>
                    <?php esc_html_e('Change Password', 'premium-classifieds'); ?>
                </a>
            </div>

            <div class="pc-info-box pc-info-danger">
                <p>
                    <strong><?php esc_html_e('Delete Account', 'premium-classifieds'); ?></strong><br>
                    <?php esc_html_e('Permanently delete your account and all associated data. This action cannot be undone.', 'premium-classifieds'); ?>
                </p>
                <button type="button" class="pc-btn pc-btn-danger" id="pc-delete-account-btn">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Delete Account', 'premium-classifieds'); ?>
                </button>
            </div>
        </div>

        <!-- Data & Export -->
        <div class="pc-settings-section">
            <h3 class="pc-settings-title">
                <span class="dashicons dashicons-database"></span>
                <?php esc_html_e('Data & Export', 'premium-classifieds'); ?>
            </h3>
            
            <p class="pc-section-description">
                <?php esc_html_e('Download a copy of your data for your records.', 'premium-classifieds'); ?>
            </p>

            <div class="pc-export-buttons">
                <button type="button" class="pc-btn pc-btn-secondary" id="pc-export-listings">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Export My Listings', 'premium-classifieds'); ?>
                </button>

                <button type="button" class="pc-btn pc-btn-secondary" id="pc-export-messages">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Export Messages', 'premium-classifieds'); ?>
                </button>

                <button type="button" class="pc-btn pc-btn-secondary" id="pc-export-payments">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Export Payment History', 'premium-classifieds'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div id="pc-delete-account-modal" class="pc-modal" style="display: none;">
    <div class="pc-modal-backdrop"></div>
    <div class="pc-modal-content pc-modal-danger">
        <div class="pc-modal-header">
            <h3><?php esc_html_e('Delete Account', 'premium-classifieds'); ?></h3>
            <button type="button" class="pc-modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="pc-modal-body">
            <div class="pc-warning-box">
                <span class="dashicons dashicons-warning"></span>
                <p><strong><?php esc_html_e('Warning: This action is permanent!', 'premium-classifieds'); ?></strong></p>
            </div>
            
            <p><?php esc_html_e('Deleting your account will:', 'premium-classifieds'); ?></p>
            <ul class="pc-delete-consequences">
                <li><?php esc_html_e('Remove all your listings permanently', 'premium-classifieds'); ?></li>
                <li><?php esc_html_e('Delete all your messages', 'premium-classifieds'); ?></li>
                <li><?php esc_html_e('Remove your profile and settings', 'premium-classifieds'); ?></li>
                <li><?php esc_html_e('Cancel any active subscriptions', 'premium-classifieds'); ?></li>
            </ul>

            <div class="pc-form-group">
                <label class="pc-label">
                    <?php esc_html_e('Type DELETE to confirm:', 'premium-classifieds'); ?>
                </label>
                <input 
                    type="text" 
                    id="delete-confirm-input" 
                    class="pc-input"
                    placeholder="DELETE"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="pc-modal-footer">
            <button type="button" class="pc-btn pc-btn-text pc-modal-close">
                <?php esc_html_e('Cancel', 'premium-classifieds'); ?>
            </button>
            <button type="button" class="pc-btn pc-btn-danger" id="pc-confirm-delete" disabled>
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Delete My Account', 'premium-classifieds'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.pc-settings-container {
    display: flex;
    flex-direction: column;
    gap: 32px;
}

.pc-settings-section {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 24px;
}

.pc-settings-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 0 20px;
    font-size: 18px;
    color: #1f2937;
}

.pc-settings-title .dashicons {
    color: #2196F3;
    width: 24px;
    height: 24px;
    font-size: 24px;
}

.pc-settings-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.pc-section-description {
    color: #6b7280;
    margin: 0 0 20px;
}

.pc-form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.pc-checkbox-group {
    background: #f9fafb;
    padding: 16px;
    border-radius: 6px;
}

.pc-checkbox-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    font-weight: 500;
}

.pc-checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.pc-required {
    color: #ef4444;
}

.pc-help-text {
    color: #6b7280;
    font-size: 13px;
}

.pc-char-counter {
    text-align: right;
    font-size: 12px;
    color: #9ca3af;
}

.pc-notification-item {
    padding: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 12px;
    transition: all 0.2s;
}

.pc-notification-item:hover {
    border-color: #2196F3;
    background: #f9fafb;
}

.pc-notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.pc-notification-title {
    font-weight: 600;
    color: #1f2937;
}

.pc-notification-badge {
    font-size: 11px;
    padding: 4px 8px;
    background: #e3f2fd;
    color: #1976d2;
    border-radius: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.pc-notification-description {
    color: #6b7280;
    font-size: 14px;
    margin: 0;
}

.pc-info-box {
    padding: 20px;
    background: #f9fafb;
    border-left: 4px solid #2196F3;
    border-radius: 4px;
    margin-bottom: 16px;
}

.pc-info-box p {
    margin: 0 0 16px;
}

.pc-info-danger {
    background: #fef2f2;
    border-left-color: #ef4444;
}

.pc-export-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.pc-warning-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    margin-bottom: 20px;
    color: #991b1b;
}

.pc-warning-box .dashicons {
    width: 24px;
    height: 24px;
    font-size: 24px;
    flex-shrink: 0;
}

.pc-delete-consequences {
    margin: 16px 0;
    padding-left: 24px;
    color: #6b7280;
}

.pc-delete-consequences li {
    margin-bottom: 8px;
}

.pc-modal-danger .pc-modal-content {
    border-top: 4px solid #ef4444;
}

@media (max-width: 768px) {
    .pc-settings-section {
        padding: 16px;
    }
    
    .pc-export-buttons {
        flex-direction: column;
    }
    
    .pc-export-buttons .pc-btn {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Bio character counter
    const updateCharCount = () => {
        const count = $('#description').val().length;
        $('#bio-char-count').text(count);
        
        if (count > 450) {
            $('#bio-char-count').css('color', '#ef4444');
        } else {
            $('#bio-char-count').css('color', '#9ca3af');
        }
    };
    
    $('#description').on('input', updateCharCount);
    updateCharCount();
    
    // Save profile
    $('#pc-profile-form').on('submit', function(e) {
        e.preventDefault();
        
        const $btn = $(this).find('button[type="submit"]');
        const originalText = $btn.html();
        
        $.ajax({
            url: pcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pc_update_profile',
                nonce: pcData.nonce,
                display_name: $('#display_name').val(),
                user_email: $('#user_email').val(),
                description: $('#description').val(),
                public_profile: $('#public_profile').is(':checked') ? '1' : '0'
            },
            beforeSend: function() {
                $btn.prop('disabled', true).html('<span class="pc-spinner-sm"></span> Saving...');
            },
            success: function(response) {
                if (response.success) {
                    pcUtils.showNotice('Profile updated successfully!', 'success');
                } else {
                    pcUtils.showNotice(response.data || 'Failed to update profile', 'error');
                }
            },
            error: function(xhr) {
                pcUtils.showNotice('An error occurred', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Save notifications
    $('#pc-notifications-form').on('submit', function(e) {
        e.preventDefault();
        
        const $btn = $(this).find('button[type="submit"]');
        const originalText = $btn.html();
        
        $.ajax({
            url: pcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pc_update_notifications',
                nonce: pcData.nonce,
                notify_messages: $('input[name="notify_messages"]').is(':checked') ? '1' : '0',
                notify_purchases: $('input[name="notify_purchases"]').is(':checked') ? '1' : '0',
                notify_listing_status: $('input[name="notify_listing_status"]').is(':checked') ? '1' : '0'
            },
            beforeSend: function() {
                $btn.prop('disabled', true).html('<span class="pc-spinner-sm"></span> Saving...');
            },
            success: function(response) {
                if (response.success) {
                    pcUtils.showNotice('Preferences saved successfully!', 'success');
                } else {
                    pcUtils.showNotice(response.data || 'Failed to save preferences', 'error');
                }
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Delete account modal
    $('#pc-delete-account-btn').on('click', function() {
        $('#pc-delete-account-modal').fadeIn(200);
        $('#delete-confirm-input').val('').focus();
    });
    
    $('.pc-modal-close, .pc-modal-backdrop').on('click', function() {
        $('.pc-modal').fadeOut(200);
    });
    
    // Enable delete button when typing DELETE
    $('#delete-confirm-input').on('input', function() {
        const value = $(this).val().trim();
        $('#pc-confirm-delete').prop('disabled', value !== 'DELETE');
    });
    
    // Confirm delete
    $('#pc-confirm-delete').on('click', function() {
        const $btn = $(this);
        
        if (!confirm('Are you absolutely sure? This will permanently delete your account and all data.')) {
            return;
        }
        
        $.ajax({
            url: pcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pc_delete_account',
                nonce: pcData.nonce,
                confirm: 'DELETE'
            },
            beforeSend: function() {
                $btn.prop('disabled', true).html('<span class="pc-spinner-sm"></span> Deleting...');
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect || '/';
                } else {
                    alert(response.data || 'Failed to delete account');
                }
            },
            error: function() {
                alert('An error occurred while deleting your account');
            }
        });
    });
    
    // Export data
    $('.pc-export-buttons button').on('click', function() {
        const action = $(this).attr('id').replace('pc-export-', '');
        
        window.location.href = pcData.ajaxUrl + 
            '?action=pc_export_data' +
            '&type=' + action +
            '&nonce=' + pcData.nonce;
    });
});
</script>