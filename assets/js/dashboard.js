/**
 * Premium Classifieds - Dashboard JavaScript
 * 
 * Handles AJAX interactions for the frontend dashboard:
 * - Tab navigation
 * - Listing CRUD operations
 * - Image uploads
 * - Favorites management
 * - Toast notifications
 *
 * @package    PremiumClassifieds
 * @version    2.1.0
 */

(function($) {
    'use strict';

    /**
     * Dashboard Controller
     */
    const PCDashboard = {
        
        /**
         * Initialize dashboard
         */
        init: function() {
            this.setupTabNavigation();
            this.setupListingForm();
            this.setupListingActions();
            this.setupImageUpload();
            this.setupFavorites();
        },

        /**
         * Setup tab navigation with AJAX loading
         */
        setupTabNavigation: function() {
            $(document).on('click', '.pc-nav-link, [data-tab]', function(e) {
                e.preventDefault();
                
                const $link = $(this);
                const tab = $link.data('tab');
                
                if (!tab) return;
                
                // Update active state
                $('.pc-nav-item').removeClass('active');
                $link.closest('.pc-nav-item').addClass('active');
                
                // Load tab content
                PCDashboard.loadTab(tab);
            });
        },

        /**
         * Load tab content via AJAX
         */
        loadTab: function(tab) {
            const $content = $('#pc-tab-content');
            const $loading = $('.pc-loading-overlay');
            
            // Show loading
            $loading.fadeIn(200);
            
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_load_dashboard_tab',
                    nonce: pcData.nonce,
                    tab: tab
                },
                success: function(response) {
                    if (response.success) {
                        $content.html(response.data.html);
                        
                        // Re-initialize components for new content
                        PCDashboard.reinitialize();
                    } else {
                        PCDashboard.showToast(response.data.message || pcData.strings.error, 'error');
                    }
                },
                error: function() {
                    PCDashboard.showToast(pcData.strings.error, 'error');
                },
                complete: function() {
                    $loading.fadeOut(200);
                }
            });
        },

        /**
         * Setup listing form submission
         */
        setupListingForm: function() {
            $(document).on('submit', '#pc-listing-form', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $button = $form.find('#pc-submit-listing');
                const originalText = $button.html();
                const listingId = $form.find('input[name="listing_id"]').val();
                const isEdit = listingId && listingId !== '0';
                
                // Get form data
                const formData = new FormData(this);
                formData.append('action', isEdit ? 'pc_edit_listing' : 'pc_add_listing');
                formData.append('nonce', pcData.nonce);
                
                // Get content from WordPress editor
                if (typeof tinyMCE !== 'undefined') {
                    const editor = tinyMCE.get('listing_content');
                    if (editor) {
                        formData.set('content', editor.getContent());
                    }
                }
                
                // Validate required fields
                const title = formData.get('title');
                const content = formData.get('content');
                
                if (!title || title.length < 5) {
                    PCDashboard.showToast('Title must be at least 5 characters long.', 'error');
                    return;
                }
                
                if (!content || content.length < 50) {
                    PCDashboard.showToast('Description must be at least 50 characters long.', 'error');
                    return;
                }
                
                // Disable button
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + pcData.strings.uploading);
                
                $.ajax({
                    url: pcData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            PCDashboard.showToast(response.data.message, 'success');
                            
                            // Redirect to my listings after 1 second
                            setTimeout(function() {
                                PCDashboard.loadTab('my-listings');
                            }, 1000);
                        } else {
                            PCDashboard.showToast(response.data.message || pcData.strings.error, 'error');
                        }
                    },
                    error: function(xhr) {
                        const message = xhr.responseJSON?.data?.message || pcData.strings.error;
                        PCDashboard.showToast(message, 'error');
                    },
                    complete: function() {
                        $button.prop('disabled', false).html(originalText);
                    }
                });
            });
        },

        /**
         * Setup listing action buttons (edit, delete)
         */
        setupListingActions: function() {
            // Edit listing
            $(document).on('click', '.pc-edit-listing', function(e) {
                e.preventDefault();
                const listingId = $(this).data('listing-id');
                window.location.href = '?tab=add-listing&edit=' + listingId;
            });
            
            // Delete listing
            $(document).on('click', '.pc-delete-listing', function(e) {
                e.preventDefault();
                
                if (!confirm(pcData.strings.confirmDelete)) {
                    return;
                }
                
                const $button = $(this);
                const listingId = $button.data('listing-id');
                const $row = $button.closest('.pc-listing-row');
                
                $button.prop('disabled', true);
                
                $.ajax({
                    url: pcData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pc_delete_listing',
                        nonce: pcData.nonce,
                        listing_id: listingId
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if table is empty
                                if ($('.pc-listings-table tbody tr').length === 0) {
                                    PCDashboard.loadTab('my-listings');
                                }
                            });
                            PCDashboard.showToast(response.data.message, 'success');
                        } else {
                            PCDashboard.showToast(response.data.message || pcData.strings.error, 'error');
                            $button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        PCDashboard.showToast(pcData.strings.error, 'error');
                        $button.prop('disabled', false);
                    }
                });
            });
            
            // Boost listing (will be connected to Stripe in Phase 3)
            $(document).on('click', '.pc-boost-listing', function(e) {
                e.preventDefault();
                const listingId = $(this).data('listing-id');
                
                PCDashboard.showToast('Boost feature coming in Phase 3 (Stripe integration)', 'error');
            });
        },

        /**
         * Setup image upload functionality
         */
        setupImageUpload: function() {
            const $dropzone = $('#pc-image-dropzone');
            const $fileInput = $('#listing_images');
            const $preview = $('#pc-image-preview');
            
            // Click to browse
            $dropzone.on('click', function(e) {
                if ($(e.target).hasClass('pc-remove-image')) return;
                $fileInput.click();
            });
            
            // Drag and drop
            $dropzone.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            $dropzone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            $dropzone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                PCDashboard.handleImageFiles(files);
            });
            
            // File input change
            $fileInput.on('change', function() {
                PCDashboard.handleImageFiles(this.files);
            });
            
            // Remove image
            $(document).on('click', '.pc-remove-image', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $item = $(this).closest('.pc-preview-item');
                $item.fadeOut(200, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Handle image file uploads
         */
        handleImageFiles: function(files) {
            const maxImages = parseInt(pcData.maxImages) || 10;
            const currentCount = $('#pc-image-preview .pc-preview-item').length;
            
            if (currentCount >= maxImages) {
                PCDashboard.showToast('Maximum ' + maxImages + ' images allowed.', 'error');
                return;
            }
            
            const $preview = $('#pc-image-preview');
            
            Array.from(files).slice(0, maxImages - currentCount).forEach(function(file) {
                // Validate file type
                if (!file.type.match('image.*')) {
                    PCDashboard.showToast('Only image files are allowed.', 'error');
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    PCDashboard.showToast('File size must be less than 5MB.', 'error');
                    return;
                }
                
                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    const $item = $('<div class="pc-preview-item">' +
                        '<img src="' + e.target.result + '" alt="">' +
                        '<button type="button" class="pc-remove-image">' +
                        '<span class="dashicons dashicons-no"></span>' +
                        '</button>' +
                        '</div>');
                    
                    $preview.append($item);
                };
                reader.readAsDataURL(file);
            });
        },

        /**
         * Setup favorites functionality
         */
        setupFavorites: function() {
            // Add to favorites
            $(document).on('click', '.pc-add-favorite', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const listingId = $button.data('listing-id');
                
                $.ajax({
                    url: pcData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pc_add_favorite',
                        nonce: pcData.nonce,
                        listing_id: listingId
                    },
                    success: function(response) {
                        if (response.success) {
                            $button.removeClass('pc-add-favorite').addClass('pc-remove-favorite');
                            $button.find('.dashicons').removeClass('dashicons-heart').addClass('dashicons-heart-filled');
                            PCDashboard.showToast(response.data.message, 'success');
                        }
                    }
                });
            });
            
            // Remove from favorites
            $(document).on('click', '.pc-remove-favorite', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const listingId = $button.data('listing-id');
                
                $.ajax({
                    url: pcData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pc_remove_favorite',
                        nonce: pcData.nonce,
                        listing_id: listingId
                    },
                    success: function(response) {
                        if (response.success) {
                            $button.removeClass('pc-remove-favorite').addClass('pc-add-favorite');
                            $button.find('.dashicons').removeClass('dashicons-heart-filled').addClass('dashicons-heart');
                            PCDashboard.showToast(response.data.message, 'success');
                        }
                    }
                });
            });
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            const $toast = $('#pc-toast');
            const $message = $toast.find('.pc-toast-message');
            
            // Set message and type
            $message.text(message);
            $toast.removeClass('pc-toast-success pc-toast-error').addClass('pc-toast-' + type);
            
            // Show toast
            $toast.fadeIn(300);
            
            // Auto hide after 4 seconds
            setTimeout(function() {
                $toast.fadeOut(300);
            }, 4000);
        },

        /**
         * Reinitialize components after AJAX load
         */
        reinitialize: function() {
            // Reinitialize WordPress editor if present
            if (typeof tinyMCE !== 'undefined' && $('#listing_content').length) {
                setTimeout(function() {
                    wp.editor.initialize('listing_content', {
                        tinymce: {
                            wpautop: true,
                            plugins: 'lists,paste,tabfocus,fullscreen,wordpress,wpautoresize,wpeditimage,wplink',
                            toolbar1: 'bold,italic,strikethrough,bullist,numlist,blockquote,link,unlink',
                        },
                        quicktags: false,
                        mediaButtons: false
                    });
                }, 100);
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('.pc-dashboard-wrapper').length) {
            PCDashboard.init();
        }
    });

})(jQuery);
