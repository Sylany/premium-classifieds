/**
 * Premium Classifieds - Messaging System
 * 
 * Handles real-time messaging UI updates and AJAX interactions
 *
 * @package    PremiumClassifieds
 * @version    2.1.0
 */

(function($) {
    'use strict';

    /**
     * Messaging System Controller
     */
    const PCMessaging = {
        
        /**
         * Current active thread
         */
        activeThread: null,
        
        /**
         * Poll interval for new messages (milliseconds)
         */
        pollInterval: 30000, // 30 seconds
        
        /**
         * Poll timer ID
         */
        pollTimer: null,
        
        /**
         * Initialize
         */
        init: function() {
            if (!$('.pc-inbox-section').length) {
                return;
            }
            
            this.bindEvents();
            this.startPolling();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Send message from single listing page
            $(document).on('submit', '#pc-contact-message-form', this.sendMessageFromListing.bind(this));
            
            // Auto-expand textarea
            $(document).on('input', '.pc-textarea', this.autoExpandTextarea);
            
            // Message notifications
            this.checkNewMessages();
        },
        
        /**
         * Send message from listing page
         */
        sendMessageFromListing: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $button = $form.find('button[type="submit"]');
            const originalText = $button.html();
            
            $button.prop('disabled', true).html(
                '<span class="dashicons dashicons-update pc-spin"></span> Sending...'
            );
            
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
                        // Show success message
                        PCMessaging.showNotification(
                            response.data.message || 'Message sent successfully!',
                            'success'
                        );
                        
                        // Clear form
                        $form[0].reset();
                        
                        // Redirect to inbox after 2 seconds
                        setTimeout(function() {
                            window.location.href = pcData.dashboardUrl + '?tab=inbox';
                        }, 2000);
                    } else {
                        PCMessaging.showNotification(
                            response.data.message || 'Failed to send message',
                            'error'
                        );
                    }
                },
                error: function() {
                    PCMessaging.showNotification('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).html(originalText);
                }
            });
        },
        
        /**
         * Auto-expand textarea as user types
         */
        autoExpandTextarea: function() {
            const $textarea = $(this);
            $textarea.css('height', 'auto');
            $textarea.css('height', $textarea[0].scrollHeight + 'px');
        },
        
        /**
         * Start polling for new messages
         */
        startPolling: function() {
            if (!pcData.isLoggedIn) {
                return;
            }
            
            // Clear existing timer
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }
            
            // Poll every 30 seconds
            this.pollTimer = setInterval(() => {
                this.checkNewMessages();
            }, this.pollInterval);
        },
        
        /**
         * Check for new messages
         */
        checkNewMessages: function() {
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_load_messages',
                    nonce: pcData.nonce,
                    view: 'inbox',
                    unread_only: true
                },
                success: function(response) {
                    if (response.success) {
                        const unreadCount = response.data.unread_count || 0;
                        PCMessaging.updateBadge(unreadCount);
                        
                        // Play sound if new messages (optional)
                        if (unreadCount > 0 && PCMessaging.lastUnreadCount < unreadCount) {
                            PCMessaging.playNotificationSound();
                        }
                        
                        PCMessaging.lastUnreadCount = unreadCount;
                    }
                }
            });
        },
        
        /**
         * Update unread badge in navigation
         */
        updateBadge: function(count) {
            const $badge = $('[data-tab="inbox"]').find('.pc-badge');
            
            if (count > 0) {
                if ($badge.length) {
                    $badge.text(count).show();
                } else {
                    $('[data-tab="inbox"]').append(
                        `<span class="pc-badge">${count}</span>`
                    );
                }
                
                // Update page title
                document.title = `(${count}) ${pcData.siteTitle || 'Premium Classifieds'}`;
            } else {
                $badge.hide();
                document.title = pcData.siteTitle || 'Premium Classifieds';
            }
        },
        
        /**
         * Play notification sound (optional)
         */
        playNotificationSound: function() {
            // Only play if user has interacted with page (browser requirement)
            if (!this.hasUserInteracted) {
                return;
            }
            
            try {
                const audio = new Audio(pcData.notificationSound || '');
                audio.volume = 0.3;
                audio.play().catch(() => {
                    // Silently fail if browser blocks
                });
            } catch (e) {
                // Ignore errors
            }
        },
        
        /**
         * Show notification
         */
        showNotification: function(message, type) {
            const $notification = $('<div>', {
                class: `pc-notification pc-notification-${type}`,
                html: `
                    <span class="pc-notification-icon"></span>
                    <span class="pc-notification-message">${message}</span>
                `
            });
            
            $('body').append($notification);
            
            setTimeout(() => {
                $notification.addClass('pc-notification-show');
            }, 100);
            
            setTimeout(() => {
                $notification.removeClass('pc-notification-show');
                setTimeout(() => {
                    $notification.remove();
                }, 300);
            }, 4000);
        },
        
        /**
         * Format timestamp
         */
        formatTime: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            const seconds = Math.floor(diff / 1000);
            
            if (seconds < 60) return 'just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
            
            return date.toLocaleDateString();
        },
        
        /**
         * Sanitize HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    /**
     * Track user interaction for notification sounds
     */
    $(document).one('click keypress', function() {
        PCMessaging.hasUserInteracted = true;
    });

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        PCMessaging.init();
    });
    
    /**
     * Cleanup on page unload
     */
    $(window).on('beforeunload', function() {
        if (PCMessaging.pollTimer) {
            clearInterval(PCMessaging.pollTimer);
        }
    });

})(jQuery);
