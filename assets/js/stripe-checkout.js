/**
 * Premium Classifieds - Stripe Checkout Integration
 * 
 * Handles frontend payment interactions:
 * - Contact reveal payments
 * - Listing boost payments
 * - Payment verification
 *
 * @package    PremiumClassifieds
 * @version    2.1.0
 */

(function($) {
    'use strict';

    /**
     * Stripe Checkout Controller
     */
    const PCStripeCheckout = {
        
        /**
         * Stripe instance
         */
        stripe: null,
        
        /**
         * Initialize Stripe checkout
         */
        init: function() {
            // Initialize Stripe.js
            if (typeof Stripe !== 'undefined' && pcData.stripePublicKey) {
                this.stripe = Stripe(pcData.stripePublicKey);
            } else {
                console.error('Stripe.js not loaded or public key missing');
                return;
            }
            
            // Setup event handlers
            this.setupRevealContactButton();
            this.setupBoostButton();
            this.checkPaymentStatus();
        },
        
        /**
         * Setup "Reveal Contact" button
         */
        setupRevealContactButton: function() {
            $(document).on('click', '.pc-reveal-contact-btn', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const listingId = $button.data('listing-id');
                const amount = parseFloat(pcData.prices.contactReveal);
                
                // Confirm purchase
                const confirmed = confirm(
                    'Reveal contact information for $' + amount.toFixed(2) + '?\n\n' +
                    'You will get lifetime access to:\n' +
                    '• Phone number\n' +
                    '• Email address\n' +
                    '• Ability to send messages'
                );
                
                if (!confirmed) {
                    return;
                }
                
                // Disable button
                $button.prop('disabled', true).html(
                    '<span class="dashicons dashicons-update pc-spin"></span> Processing...'
                );
                
                // Create checkout session
                PCStripeCheckout.createCheckoutSession(listingId, 'contact_reveal', $button);
            });
        },
        
        /**
         * Setup "Boost Listing" button
         */
        setupBoostButton: function() {
            $(document).on('click', '.pc-boost-listing', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const listingId = $button.data('listing-id');
                const amount = parseFloat(pcData.prices.listingBoost);
                
                // Confirm purchase
                const confirmed = confirm(
                    'Boost your listing for $' + amount.toFixed(2) + '?\n\n' +
                    'Your listing will be featured for 30 days:\n' +
                    '• Shown at the top of search results\n' +
                    '• Highlighted with a star badge\n' +
                    '• Increased visibility'
                );
                
                if (!confirmed) {
                    return;
                }
                
                $button.prop('disabled', true);
                
                // Create checkout session
                $.ajax({
                    url: pcData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pc_boost_listing',
                        nonce: pcData.nonce,
                        listing_id: listingId
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.checkout_url;
                        } else {
                            alert(response.data.message || 'An error occurred');
                            $button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('Network error. Please try again.');
                        $button.prop('disabled', false);
                    }
                });
            });
        },
        
        /**
         * Create Stripe checkout session
         */
        createCheckoutSession: function(listingId, paymentType, $button) {
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_create_checkout',
                    nonce: pcData.nonce,
                    listing_id: listingId,
                    payment_type: paymentType
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to Stripe Checkout
                        window.location.href = response.data.checkout_url;
                    } else {
                        alert(response.data.message || 'Payment failed');
                        if ($button) {
                            $button.prop('disabled', false).html('Reveal Contact ($' + pcData.prices.contactReveal + ')');
                        }
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.data?.message || 'Network error';
                    alert(message);
                    if ($button) {
                        $button.prop('disabled', false).html('Reveal Contact ($' + pcData.prices.contactReveal + ')');
                    }
                }
            });
        },
        
        /**
         * Check payment status after redirect
         */
        checkPaymentStatus: function() {
            // Check if returning from Stripe checkout
            const urlParams = new URLSearchParams(window.location.search);
            const sessionId = urlParams.get('session_id');
            const paymentSuccess = urlParams.get('payment_success');
            const paymentCancelled = urlParams.get('payment_cancelled');
            
            if (paymentCancelled) {
                this.showNotification('Payment was cancelled.', 'warning');
                this.cleanUrl();
                return;
            }
            
            if (paymentSuccess && sessionId) {
                // Show processing message
                this.showNotification('Verifying payment...', 'info');
                
                // Verify payment with backend
                $.ajax({
                    url: pcData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pc_verify_payment',
                        nonce: pcData.nonce,
                        session_id: sessionId
                    },
                    success: function(response) {
                        if (response.success) {
                            PCStripeCheckout.showNotification(
                                response.data.message || 'Payment successful!',
                                'success'
                            );
                            
                            // Reload page after 2 seconds to show unlocked content
                            setTimeout(function() {
                                PCStripeCheckout.cleanUrl();
                                location.reload();
                            }, 2000);
                        } else {
                            PCStripeCheckout.showNotification(
                                response.data.message || 'Payment verification failed',
                                'error'
                            );
                        }
                    },
                    error: function() {
                        PCStripeCheckout.showNotification(
                            'Failed to verify payment. Please refresh the page.',
                            'error'
                        );
                    }
                });
            }
            
            // Check boost success
            const boostSuccess = urlParams.get('boost_success');
            if (boostSuccess) {
                this.showNotification('Your listing has been boosted!', 'success');
                this.cleanUrl();
            }
        },
        
        /**
         * Show notification to user
         */
        showNotification: function(message, type) {
            // Create notification element
            const $notification = $('<div>', {
                class: 'pc-payment-notification pc-notification-' + type,
                html: '<div class="pc-notification-content">' +
                      '<span class="pc-notification-icon"></span>' +
                      '<span class="pc-notification-message">' + message + '</span>' +
                      '<button class="pc-notification-close">&times;</button>' +
                      '</div>'
            });
            
            // Add to page
            $('body').append($notification);
            
            // Show notification
            setTimeout(function() {
                $notification.addClass('pc-notification-show');
            }, 100);
            
            // Auto hide after 5 seconds
            setTimeout(function() {
                PCStripeCheckout.hideNotification($notification);
            }, 5000);
            
            // Close button handler
            $notification.find('.pc-notification-close').on('click', function() {
                PCStripeCheckout.hideNotification($notification);
            });
        },
        
        /**
         * Hide notification
         */
        hideNotification: function($notification) {
            $notification.removeClass('pc-notification-show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        },
        
        /**
         * Clean URL parameters
         */
        cleanUrl: function() {
            if (window.history && window.history.replaceState) {
                const url = window.location.pathname;
                window.history.replaceState({}, document.title, url);
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        PCStripeCheckout.init();
    });

})(jQuery);
