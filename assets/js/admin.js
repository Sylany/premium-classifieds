/**
 * Premium Classifieds - Admin JavaScript
 * 
 * Handles admin panel interactions:
 * - Revenue charts (Chart.js)
 * - Moderation actions
 * - Data export
 *
 * @package    PremiumClassifieds
 * @version    2.1.0
 */

(function($) {
    'use strict';

    /**
     * Admin Controller
     */
    const PCAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.initCharts();
            this.initModeration();
            this.initExport();
        },
        
        /**
         * Initialize Chart.js charts
         */
        initCharts: function() {
            if (typeof Chart === 'undefined' || typeof pcDashboardData === 'undefined') {
                return;
            }
            
            // Revenue chart
            const revenueCtx = document.getElementById('pc-revenue-chart');
            if (revenueCtx) {
                this.createRevenueChart(revenueCtx);
            }
            
            // Transaction types chart
            const typesCtx = document.getElementById('pc-types-chart');
            if (typesCtx) {
                this.createTypesChart(typesCtx);
            }
        },
        
        /**
         * Create revenue over time chart
         */
        createRevenueChart: function(canvas) {
            const data = pcDashboardData.revenueByDate;
            
            const labels = data.map(item => item.date);
            const values = data.map(item => parseFloat(item.total));
            
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: values,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '$' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value;
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Create transaction types pie chart
         */
        createTypesChart: function(canvas) {
            const data = pcDashboardData.revenueByType;
            
            const labels = data.map(item => {
                const typeLabels = {
                    'contact_reveal': 'Contact Reveal',
                    'listing_boost': 'Listing Boost',
                    'subscription': 'Subscription'
                };
                return typeLabels[item.type] || item.type;
            });
            
            const values = data.map(item => parseFloat(item.total));
            
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            '#2563eb',
                            '#f59e0b',
                            '#10b981'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': $' + context.parsed.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize moderation actions
         */
        initModeration: function() {
            // Approve listing
            $(document).on('click', '.pc-approve-listing', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const listingId = $button.data('listing-id');
                
                if (!confirm('Approve and publish this listing?')) {
                    return;
                }
                
                $button.prop('disabled', true).text('Approving...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pc_moderate_listing',
                        nonce: pcAdmin.nonce,
                        listing_id: listingId,
                        action_type: 'approve'
                    },
                    success: function(response) {
                        if (response.success) {
                            PCAdmin.showNotice(response.data.message, 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            PCAdmin.showNotice(response.data.message, 'error');
                            $button.prop('disabled', false).text('Approve & Publish');
                        }
                    },
                    error: function() {
                        PCAdmin.showNotice(pcAdmin.strings.error, 'error');
                        $button.prop('disabled', false).text('Approve & Publish');
                    }
                });
            });
            
            // Reject listing
            $(document).on('click', '.pc-reject-listing', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const listingId = $button.data('listing-id');
                
                if (!confirm('Reject this listing? The author will be notified.')) {
                    return;
                }
                
                $button.prop('disabled', true).text('Rejecting...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pc_moderate_listing',
                        nonce: pcAdmin.nonce,
                        listing_id: listingId,
                        action_type: 'reject'
                    },
                    success: function(response) {
                        if (response.success) {
                            PCAdmin.showNotice(response.data.message, 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            PCAdmin.showNotice(response.data.message, 'error');
                            $button.prop('disabled', false).text('Reject Listing');
                        }
                    },
                    error: function() {
                        PCAdmin.showNotice(pcAdmin.strings.error, 'error');
                        $button.prop('disabled', false).text('Reject Listing');
                    }
                });
            });
        },
        
        /**
         * Initialize export functionality
         */
        initExport: function() {
            $(document).on('click', '.pc-export-btn', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const type = $button.data('type');
                const format = $button.data('format');
                const originalText = $button.text();
                
                $button.prop('disabled', true).text('Exporting...');
                
                // Create form and submit
                const form = $('<form>', {
                    method: 'POST',
                    action: ajaxurl
                });
                
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'action',
                    value: 'pc_export_data'
                }));
                
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'nonce',
                    value: pcAdmin.nonce
                }));
                
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'type',
                    value: type
                }));
                
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'format',
                    value: format
                }));
                
                $('body').append(form);
                form.submit();
                form.remove();
                
                // Re-enable button
                setTimeout(function() {
                    $button.prop('disabled', false).text(originalText);
                }, 2000);
            });
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $('<div>', {
                class: 'notice ' + noticeClass + ' is-dismissible',
                html: '<p>' + message + '</p>'
            });
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        PCAdmin.init();
    });

})(jQuery);
