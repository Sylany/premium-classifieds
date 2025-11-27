/**
 * Search Filters with AJAX - Premium Classifieds
 * 
 * @package Premium_Classifieds
 */

(function($) {
    'use strict';

    const SearchFilters = {
        $form: null,
        $grid: null,
        $resultsCount: null,
        isLoading: false,
        currentPage: 1,
        
        init: function() {
            this.$form = $('#pc-search-filters-form');
            this.$grid = $('.pc-listings-grid');
            this.$resultsCount = $('.pc-results-count strong');
            
            if (!this.$form.length || !this.$grid.length) {
                return;
            }
            
            this.bindEvents();
            this.initUrlState();
        },
        
        bindEvents: function() {
            // Form submission
            this.$form.on('submit', (e) => {
                e.preventDefault();
                this.applyFiltersAjax();
            });
            
            // Instant search on input change
            this.$form.on('change', 'select, input[type="checkbox"]', () => {
                this.applyFiltersAjax();
            });
            
            // Price range with debounce
            let priceTimeout;
            this.$form.on('input', 'input[name="min_price"], input[name="max_price"]', () => {
                clearTimeout(priceTimeout);
                priceTimeout = setTimeout(() => {
                    this.applyFiltersAjax();
                }, 800);
            });
            
            // Clear filters
            $('#pc-clear-filters').on('click', (e) => {
                e.preventDefault();
                this.clearFilters();
            });
            
            // Pagination
            $(document).on('click', '.pc-pagination a', (e) => {
                e.preventDefault();
                const page = $(e.currentTarget).data('page');
                if (page) {
                    this.currentPage = page;
                    this.applyFiltersAjax();
                }
            });
            
            // Favorite toggle from grid
            $(document).on('click', '.pc-favorite-btn', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleFavorite($(e.currentTarget));
            });
        },
        
        initUrlState: function() {
            const params = new URLSearchParams(window.location.search);
            
            // Restore filter values from URL
            if (params.has('search')) {
                $('input[name="s"]').val(params.get('search'));
            }
            if (params.has('category')) {
                $('#pc-category-filter').val(params.get('category'));
            }
            if (params.has('location')) {
                $('#pc-location-filter').val(params.get('location'));
            }
            if (params.has('min_price')) {
                $('input[name="min_price"]').val(params.get('min_price'));
            }
            if (params.has('max_price')) {
                $('input[name="max_price"]').val(params.get('max_price'));
            }
            if (params.has('featured')) {
                $('#pc-featured-filter').prop('checked', true);
            }
            if (params.has('orderby')) {
                $('#pc-sort-select').val(params.get('orderby'));
            }
        },
        
        applyFiltersAjax: function() {
            if (this.isLoading) return;
            
            this.isLoading = true;
            this.showLoading();
            
            const formData = this.getFormData();
            
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_filter_listings',
                    nonce: pcData.nonce,
                    ...formData,
                    page: this.currentPage
                },
                success: (response) => {
                    if (response.success) {
                        this.$grid.html(response.data.html);
                        
                        // Update results count
                        if (this.$resultsCount.length && response.data.found !== undefined) {
                            this.$resultsCount.text(response.data.found);
                        }
                        
                        // Update pagination
                        if (response.data.pagination) {
                            this.updatePagination(response.data.pagination);
                        }
                        
                        // Update URL without reload
                        this.updateUrl(formData);
                        
                        // Scroll to results
                        this.scrollToResults();
                        
                        // Show empty state if no results
                        if (response.data.found === 0) {
                            this.showEmptyState();
                        }
                    } else {
                        this.showError(response.data || 'Failed to filter listings');
                    }
                },
                error: (xhr) => {
                    const error = xhr.responseJSON?.data || 'An error occurred while filtering';
                    this.showError(error);
                },
                complete: () => {
                    this.hideLoading();
                    this.isLoading = false;
                }
            });
        },
        
        getFormData: function() {
            const data = {};
            
            // Search query
            const search = $('input[name="s"]').val();
            if (search) data.search = search;
            
            // Category
            const category = $('#pc-category-filter').val();
            if (category) data.category = category;
            
            // Location
            const location = $('#pc-location-filter').val();
            if (location) data.location = location;
            
            // Price range
            const minPrice = $('input[name="min_price"]').val();
            const maxPrice = $('input[name="max_price"]').val();
            if (minPrice) data.min_price = minPrice;
            if (maxPrice) data.max_price = maxPrice;
            
            // Featured only
            const featured = $('#pc-featured-filter').is(':checked');
            if (featured) data.featured = '1';
            
            // Sorting
            const orderby = $('#pc-sort-select').val();
            if (orderby) data.orderby = orderby;
            
            return data;
        },
        
        updateUrl: function(params) {
            const url = new URL(window.location);
            
            // Clear existing params
            url.search = '';
            
            // Add new params
            Object.keys(params).forEach(key => {
                if (params[key]) {
                    url.searchParams.set(key, params[key]);
                }
            });
            
            // Add page if not 1
            if (this.currentPage > 1) {
                url.searchParams.set('paged', this.currentPage);
            }
            
            // Update URL without reload
            window.history.pushState({}, '', url);
        },
        
        clearFilters: function() {
            this.$form[0].reset();
            this.currentPage = 1;
            
            // Clear URL params
            const url = new URL(window.location);
            url.search = '';
            window.history.pushState({}, '', url);
            
            // Reload with no filters
            this.applyFiltersAjax();
        },
        
        updatePagination: function(html) {
            const $pagination = $('.pc-pagination');
            if ($pagination.length) {
                $pagination.html(html);
            }
        },
        
        scrollToResults: function() {
            $('html, body').animate({
                scrollTop: this.$grid.offset().top - 100
            }, 400);
        },
        
        showLoading: function() {
            const $loading = $('<div class="pc-loading-overlay"><div class="pc-spinner"></div><span>Loading listings...</span></div>');
            this.$grid.css('position', 'relative').append($loading);
            this.$grid.css('opacity', '0.5');
        },
        
        hideLoading: function() {
            $('.pc-loading-overlay').remove();
            this.$grid.css('opacity', '1');
        },
        
        showEmptyState: function() {
            const emptyHtml = `
                <div class="pc-empty-state" style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                    <div class="pc-empty-icon" style="font-size: 64px; color: #ccc; margin-bottom: 20px;">
                        <span class="dashicons dashicons-search"></span>
                    </div>
                    <h3 style="margin: 0 0 10px; font-size: 24px; color: #333;">No listings found</h3>
                    <p style="color: #666; margin: 0 0 20px;">Try adjusting your filters or search terms.</p>
                    <button type="button" class="pc-btn pc-btn-primary" onclick="SearchFilters.clearFilters()">
                        Clear All Filters
                    </button>
                </div>
            `;
            this.$grid.html(emptyHtml);
        },
        
        showError: function(message) {
            const errorHtml = `
                <div class="pc-notice pc-notice-error" style="grid-column: 1/-1; margin: 20px 0;">
                    <strong>Error:</strong> ${message}
                </div>
            `;
            this.$grid.prepend(errorHtml);
        },
        
        toggleFavorite: function($btn) {
            const listingId = $btn.data('listing-id');
            const $icon = $btn.find('.dashicons');
            
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_toggle_favorite',
                    nonce: pcData.nonce,
                    listing_id: listingId
                },
                beforeSend: () => {
                    $btn.prop('disabled', true);
                },
                success: (response) => {
                    if (response.success) {
                        if (response.data.is_favorite) {
                            $icon.removeClass('dashicons-heart-outline').addClass('dashicons-heart');
                            $btn.addClass('active').attr('title', 'Remove from favorites');
                        } else {
                            $icon.removeClass('dashicons-heart').addClass('dashicons-heart-outline');
                            $btn.removeClass('active').attr('title', 'Add to favorites');
                        }
                    }
                },
                complete: () => {
                    $btn.prop('disabled', false);
                }
            });
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        SearchFilters.init();
    });
    
    // Expose to global scope for button onclick
    window.SearchFilters = SearchFilters;

})(jQuery);

/**
 * CSS Styles for AJAX Loading
 */
const styles = `
<style>
.pc-loading-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 100;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.pc-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #2196F3;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.pc-loading-overlay span {
    font-size: 14px;
    color: #666;
    font-weight: 500;
}

.pc-favorite-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 4px;
    transition: all 0.2s;
    color: #999;
}

.pc-favorite-btn:hover {
    background: rgba(0,0,0,0.05);
    color: #e91e63;
}

.pc-favorite-btn.active {
    color: #e91e63;
}

.pc-favorite-btn .dashicons {
    width: 20px;
    height: 20px;
    font-size: 20px;
}

.pc-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 40px;
    padding: 20px 0;
}

.pc-pagination a,
.pc-pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
    transition: all 0.2s;
}

.pc-pagination a:hover {
    background: #2196F3;
    color: #fff;
    border-color: #2196F3;
}

.pc-pagination .current {
    background: #2196F3;
    color: #fff;
    border-color: #2196F3;
    font-weight: 600;
}

.pc-pagination .dots {
    border: none;
    cursor: default;
}

.pc-pagination .prev,
.pc-pagination .next {
    font-weight: 600;
}

.pc-notice {
    padding: 16px 20px;
    border-radius: 4px;
    margin: 20px 0;
}

.pc-notice-error {
    background: #ffebee;
    border-left: 4px solid #f44336;
    color: #c62828;
}
</style>
`;

// Inject styles if they don't exist
if (!document.getElementById('pc-ajax-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'pc-ajax-styles';
    styleSheet.innerHTML = styles.replace(/<\/?style>/g, '');
    document.head.appendChild(styleSheet);
}