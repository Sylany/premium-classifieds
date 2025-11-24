/**
 * Premium Classifieds - Single Listing JavaScript
 * 
 * Handles gallery navigation, favorites, and share functionality
 *
 * @package    PremiumClassifieds
 * @version    2.1.0
 */

(function($) {
    'use strict';

    /**
     * Single Listing Controller
     */
    const PCSingleListing = {
        
        /**
         * Initialize
         */
        init: function() {
            this.setupGallery();
            this.setupFavorites();
            this.setupShare();
            this.setupReport();
        },
        
        /**
         * Setup gallery thumbnail navigation
         */
        setupGallery: function() {
            $('.pc-gallery-thumb').on('click', function() {
                const fullUrl = $(this).data('full');
                
                // Update main image
                $('#pc-gallery-main-image').attr('src', fullUrl);
                
                // Update active state
                $('.pc-gallery-thumb').removeClass('active');
                $(this).addClass('active');
            });
            
            // Lightbox on main image click (optional)
            $('#pc-gallery-main-image').on('click', function() {
                // TODO: Implement lightbox in future version
                // For now, open in new tab
                window.open($(this).attr('src'), '_blank');
            });
        },
        
        /**
         * Setup favorites functionality
         */
        setupFavorites: function() {
            $('.pc-add-favorite-btn').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const listingId = $button.data('listing-id');
                const $icon = $button.find('.dashicons');
                const isFavorite = $icon.hasClass('dashicons-heart-filled');
                
                // Check if logged in
                if (!pcData.isLoggedIn) {
                    alert('Please login to add favorites.');
                    window.location.href = pcData.loginUrl;
                    return;
                }
                
                const action = isFavorite ? 'pc_remove_favorite' : 'pc_add_favorite';
                
                $.ajax({
                    url: pcData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: action,
                        nonce: pcData.nonce,
                        listing_id: listingId
                    },
                    success: function(response) {
                        if (response.success) {
                            // Toggle icon
                            if (isFavorite) {
                                $icon.removeClass('dashicons-heart-filled').addClass('dashicons-heart');
                                $button.attr('title', 'Add to favorites');
                            } else {
                                $icon.removeClass('dashicons-heart').addClass('dashicons-heart-filled');
                                $button.attr('title', 'Remove from favorites');
                            }
                            
                            // Show notification
                            PCSingleListing.showNotification(response.data.message, 'success');
                        }
                    },
                    error: function() {
                        PCSingleListing.showNotification('An error occurred', 'error');
                    }
                });
            });
        },
        
        /**
         * Setup share functionality
         */
        setupShare: function() {
            $('.pc-share-btn').on('click', function(e) {
                e.preventDefault();
                
                const url = window.location.href;
                const title = document.title;
                
                // Check if Web Share API is available
                if (navigator.share) {
                    navigator.share({
                        title: title,
                        url: url
                    }).catch(function(error) {
                        console.log('Error sharing:', error);
                    });
                } else {
                    // Fallback: Copy to clipboard
                    PCSingleListing.copyToClipboard(url);
                    PCSingleListing.showNotification('Link copied to clipboard!', 'success');
                }
            });
        },
        
        /**
         * Setup report functionality
         */
        setupReport: function() {
            $('.pc-report-btn').on('click', function(e) {
                e.preventDefault();
                
                const reason = prompt('Please tell us why you are reporting this listing:');
                
                if (!reason || reason.trim() === '') {
                    return;
                }
                
                // TODO: Implement report functionality
                alert('Thank you for your report. We will review this listing.');
                
                // In production, send AJAX request to admin
                /*
                $.ajax({
                    url: pcData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pc_report_listing',
                        nonce: pcData.nonce,
                        listing_id: pcData.listingId,
                        reason: reason
                    }
                });
                */
            });
        },
        
        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            const tempInput = document.createElement('input');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
        },
        
        /**
         * Show notification
         */
        showNotification: function(message, type) {
            const $notification = $('<div>', {
                class: 'pc-notification pc-notification-' + type,
                html: '<span class="pc-notification-message">' + message + '</span>'
            });
            
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.addClass('pc-notification-show');
            }, 100);
            
            setTimeout(function() {
                $notification.removeClass('pc-notification-show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('.pc-single-listing-wrapper').length) {
            PCSingleListing.init();
        }
    });

})(jQuery);
