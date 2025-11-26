/**
 * Premium Classifieds - Search Filters JavaScript
 * 
 * Handles interactive filtering, sorting, and view switching on archive page
 *
 * @package    PremiumClassifieds
 * @version    2.1.0
 */

(function($) {
    'use strict';

    /**
     * Archive Filters Controller
     */
    const PCArchiveFilters = {
        
        /**
         * Initialize
         */
        init: function() {
            this.setupFilters();
            this.setupSort();
            this.setupViewMode();
            this.setupUrlState();
        },
        
        /**
         * Setup filter interactions
         */
        setupFilters: function() {
            // Price filter
            $('#pc-price-filter').on('submit', function(e) {
                e.preventDefault();
                PCArchiveFilters.applyFilters();
            });
            
            // Location filter
            $('#pc-location-filter').on('change', function() {
                PCArchiveFilters.applyFilters();
            });
            
            // Featured filter
            $('#pc-featured-filter').on('change', function() {
                PCArchiveFilters.applyFilters();
            });
            
            // Search form
            $('.pc-search-form').on('submit', function(e) {
                e.preventDefault();
                PCArchiveFilters.applyFilters();
            });
        },
        
        /**
         * Setup sorting
         */
        setupSort: function() {
            $('#pc-sort-select').on('change', function() {
                const value = $(this).val();
                const parts = value.split('-');
                
                const url = new URL(window.location.href);
                url.searchParams.set('orderby', parts[0]);
                if (parts[1]) {
                    url.searchParams.set('order', parts[1].toUpperCase());
                }
                
                window.location.href = url.toString();
            });
        },
        
        /**
         * Setup view mode switching
         */
        setupViewMode: function() {
            $('.pc-view-btn').on('click', function() {
                const view = $(this).data('view');
                
                $('.pc-view-btn').removeClass('active');
                $(this).addClass('active');
                
                $('.pc-listings-grid').attr('data-view', view);
                
                // Save preference
                localStorage.setItem('pc_archive_view', view);
            });
            
            // Restore saved view
            const savedView = localStorage.getItem('pc_archive_view');
            if (savedView) {
                $('.pc-view-btn[data-view="' + savedView + '"]').click();
            }
        },
        
        /**
         * Setup URL state management
         */
        setupUrlState: function() {
            // Populate filters from URL
            const url = new URL(window.location.href);
            
            const minPrice = url.searchParams.get('min_price');
            const maxPrice = url.searchParams.get('max_price');
            const location = url.searchParams.get('location');
            const featured = url.searchParams.get('featured');
            
            if (minPrice) {
                $('input[name="min_price"]').val(minPrice);
            }
            if (maxPrice) {
                $('input[name="max_price"]').val(maxPrice);
            }
            if (location) {
                $('#pc-location-filter').val(location);
            }
            if (featured) {
                $('#pc-featured-filter').prop('checked', true);
            }
        },
        
        /**
         * Apply all filters and reload page
         */
        applyFilters: function() {
            const url = new URL(window.location.href);
            
            // Get filter values
            const minPrice = $('input[name="min_price"]').val();
            const maxPrice = $('input[name="max_price"]').val();
            const location = $('#pc-location-filter').val();
            const featured = $('#pc-featured-filter').is(':checked');
            const search = $('input[name="s"]').val();
            
            // Update URL params
            if (minPrice) {
                url.searchParams.set('min_price', minPrice);
            } else {
                url.searchParams.delete('min_price');
            }
            
            if (maxPrice) {
                url.searchParams.set('max_price', maxPrice);
            } else {
                url.searchParams.delete('max_price');
            }
            
            if (location) {
                url.searchParams.set('location', location);
            } else {
                url.searchParams.delete('location');
            }
            
            if (featured) {
                url.searchParams.set('featured', '1');
            } else {
                url.searchParams.delete('featured');
            }
            
            if (search) {
                url.searchParams.set('s', search);
            } else {
                url.searchParams.delete('s');
            }
            
            // Reset to page 1 when filtering
            url.searchParams.delete('paged');
            
            // Reload with new URL
            window.location.href = url.toString();
        },
        
        /**
         * AJAX filter (alternative to page reload)
         */
        ajaxFilter: function() {
            const $grid = $('.pc-listings-grid');
            const $loading = $('<div class="pc-loading-overlay"><div class="pc-spinner"></div></div>');
            
            $grid.css('position', 'relative').append($loading);
            
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_filter_listings',
                    nonce: pcData.nonce,
                    category: PCArchiveFilters.getCurrentCategory(),
                    min_price: $('input[name="min_price"]').val(),
                    max_price: $('input[name="max_price"]').val(),
                    location: $('#pc-location-filter').val(),
                    featured: $('#pc-featured-filter').is(':checked') ? 1 : 0,
                    search: $('input[name="s"]').val(),
                    orderby: $('#pc-sort-select').val(),
                    page: 1
                },
                success: function(response) {
                    if (response.success) {
                        $grid.html(response.data.html);
                        
                        // Update results count
                        $('.pc-results-count strong').text(response.data.found);
                        
                        // Scroll to top of results
                        $('html, body').animate({
                            scrollTop: $grid.offset().top - 100
                        }, 300);
                    }
                },
                error: function() {
                    alert('Filter error. Please try again.');
                },
                complete: function() {
                    $loading.remove();
                    $grid.css('position', '');
                }
            });
        },
        
        /**
         * Get current category from URL
         */
        getCurrentCategory: function() {
            const path = window.location.pathname;
            const match = path.match(/\/category\/([^\/]+)/);
            return match ? match[1] : '';
        },
        
        /**
         * Update URL without reload (for AJAX version)
         */
        updateUrl: function(params) {
            const url = new URL(window.location.href);
            
            Object.keys(params).forEach(key => {
                if (params[key]) {
                    url.searchParams.set(key, params[key]);
                } else {
                    url.searchParams.delete(key);
                }
            });
            
            window.history.pushState({}, '', url.toString());
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('.pc-archive-wrapper').length) {
            PCArchiveFilters.init();
        }
    });

})(jQuery);
