<?php
/**
 * Dashboard Payment History Template
 *
 * @package Premium_Classifieds
 */

defined('ABSPATH') || exit;

$user_id = get_current_user_id();
$transactions_db = new PC_DB_Transactions();

// Pagination
$per_page = 20;
$paged = isset($_GET['tx_page']) ? absint($_GET['tx_page']) : 1;
$offset = ($paged - 1) * $per_page;

// Get transactions
$transactions = $transactions_db->get_user_transactions($user_id, [
    'limit' => $per_page,
    'offset' => $offset
]);

$total = $transactions_db->get_user_transaction_count($user_id);
$total_pages = ceil($total / $per_page);

// Calculate stats
$stats = $transactions_db->get_user_stats($user_id);
?>

<div class="pc-section">
    <div class="pc-section-header">
        <h2><?php esc_html_e('Payment History', 'premium-classifieds'); ?></h2>
    </div>

    <!-- Stats Cards -->
    <?php if (!empty($transactions)): ?>
        <div class="pc-stats-grid">
            <div class="pc-stat-card">
                <div class="pc-stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="pc-stat-content">
                    <div class="pc-stat-value">$<?php echo esc_html(number_format($stats['total_spent'] ?? 0, 2)); ?></div>
                    <div class="pc-stat-label"><?php esc_html_e('Total Spent', 'premium-classifieds'); ?></div>
                </div>
            </div>

            <div class="pc-stat-card">
                <div class="pc-stat-icon pc-stat-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="pc-stat-content">
                    <div class="pc-stat-value"><?php echo esc_html($stats['completed'] ?? 0); ?></div>
                    <div class="pc-stat-label"><?php esc_html_e('Completed', 'premium-classifieds'); ?></div>
                </div>
            </div>

            <div class="pc-stat-card">
                <div class="pc-stat-icon pc-stat-info">
                    <span class="dashicons dashicons-list-view"></span>
                </div>
                <div class="pc-stat-content">
                    <div class="pc-stat-value"><?php echo esc_html($total); ?></div>
                    <div class="pc-stat-label"><?php esc_html_e('Total Transactions', 'premium-classifieds'); ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="pc-payment-filters">
        <form method="GET" id="pc-payment-filter-form">
            <input type="hidden" name="page" value="pc-dashboard">
            <input type="hidden" name="tab" value="payment-history">
            
            <div class="pc-filter-row">
                <select name="type" id="pc-type-filter" class="pc-select">
                    <option value=""><?php esc_html_e('All Types', 'premium-classifieds'); ?></option>
                    <option value="contact_reveal"><?php esc_html_e('Contact Reveal', 'premium-classifieds'); ?></option>
                    <option value="listing_boost"><?php esc_html_e('Listing Boost', 'premium-classifieds'); ?></option>
                </select>

                <select name="status" id="pc-status-filter" class="pc-select">
                    <option value=""><?php esc_html_e('All Statuses', 'premium-classifieds'); ?></option>
                    <option value="completed"><?php esc_html_e('Completed', 'premium-classifieds'); ?></option>
                    <option value="pending"><?php esc_html_e('Pending', 'premium-classifieds'); ?></option>
                    <option value="failed"><?php esc_html_e('Failed', 'premium-classifieds'); ?></option>
                    <option value="refunded"><?php esc_html_e('Refunded', 'premium-classifieds'); ?></option>
                </select>

                <button type="submit" class="pc-btn pc-btn-secondary">
                    <span class="dashicons dashicons-filter"></span>
                    <?php esc_html_e('Filter', 'premium-classifieds'); ?>
                </button>
                
                <button type="button" class="pc-btn pc-btn-text" id="pc-clear-filters">
                    <?php esc_html_e('Clear', 'premium-classifieds'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Transactions Table -->
    <?php if (empty($transactions)): ?>
        <div class="pc-empty-state">
            <div class="pc-empty-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <h3><?php esc_html_e('No Payments Yet', 'premium-classifieds'); ?></h3>
            <p><?php esc_html_e('Your payment history will appear here once you make a purchase.', 'premium-classifieds'); ?></p>
            <a href="<?php echo esc_url(home_url('/listings')); ?>" class="pc-btn pc-btn-primary">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e('Browse Listings', 'premium-classifieds'); ?>
            </a>
        </div>
    <?php else: ?>
        <div class="pc-table-container">
            <table class="pc-payment-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'premium-classifieds'); ?></th>
                        <th><?php esc_html_e('Type', 'premium-classifieds'); ?></th>
                        <th><?php esc_html_e('Listing', 'premium-classifieds'); ?></th>
                        <th><?php esc_html_e('Amount', 'premium-classifieds'); ?></th>
                        <th><?php esc_html_e('Payment ID', 'premium-classifieds'); ?></th>
                        <th><?php esc_html_e('Status', 'premium-classifieds'); ?></th>
                        <th><?php esc_html_e('Actions', 'premium-classifieds'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): 
                        $listing = get_post($tx->listing_id);
                        $type_labels = [
                            'contact_reveal' => __('Contact Reveal', 'premium-classifieds'),
                            'listing_boost' => __('Listing Boost', 'premium-classifieds')
                        ];
                    ?>
                        <tr>
                            <td>
                                <div class="pc-tx-date">
                                    <strong><?php echo esc_html(date_i18n('M j, Y', strtotime($tx->created_at))); ?></strong>
                                    <small><?php echo esc_html(date_i18n('g:i A', strtotime($tx->created_at))); ?></small>
                                </div>
                            </td>
                            
                            <td>
                                <div class="pc-tx-type">
                                    <?php if ($tx->type === 'contact_reveal'): ?>
                                        <span class="dashicons dashicons-email"></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-star-filled"></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($type_labels[$tx->type] ?? ucfirst(str_replace('_', ' ', $tx->type))); ?>
                                </div>
                            </td>
                            
                            <td>
                                <?php if ($listing): ?>
                                    <a href="<?php echo esc_url(get_permalink($listing)); ?>" target="_blank" class="pc-tx-listing">
                                        <?php echo esc_html(wp_trim_words($listing->post_title, 5)); ?>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                <?php else: ?>
                                    <span class="pc-text-muted"><?php esc_html_e('(Listing removed)', 'premium-classifieds'); ?></span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <strong class="pc-tx-amount">$<?php echo esc_html(number_format($tx->amount, 2)); ?></strong>
                            </td>
                            
                            <td>
                                <code class="pc-payment-id" title="<?php echo esc_attr($tx->stripe_payment_id); ?>">
                                    <?php echo esc_html(substr($tx->stripe_payment_id, 0, 20)) . '...'; ?>
                                </code>
                            </td>
                            
                            <td>
                                <?php
                                $status_classes = [
                                    'completed' => 'success',
                                    'pending' => 'warning',
                                    'failed' => 'error',
                                    'refunded' => 'secondary'
                                ];
                                $status_class = $status_classes[$tx->status] ?? 'secondary';
                                ?>
                                <span class="pc-status-badge pc-status-<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($tx->status)); ?>
                                </span>
                            </td>
                            
                            <td>
                                <div class="pc-tx-actions">
                                    <button type="button" 
                                            class="pc-btn-icon pc-view-receipt" 
                                            data-tx-id="<?php echo esc_attr($tx->id); ?>"
                                            title="<?php esc_attr_e('View Receipt', 'premium-classifieds'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    
                                    <?php if ($tx->status === 'completed'): ?>
                                        <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=pc_download_receipt&tx_id=' . $tx->id . '&nonce=' . wp_create_nonce('pc_receipt_' . $tx->id))); ?>" 
                                           class="pc-btn-icon"
                                           title="<?php esc_attr_e('Download Receipt', 'premium-classifieds'); ?>"
                                           download>
                                            <span class="dashicons dashicons-download"></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pc-pagination">
                <?php if ($paged > 1): ?>
                    <a href="?page=pc-dashboard&tab=payment-history&tx_page=<?php echo ($paged - 1); ?>" class="pc-btn pc-btn-sm">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <?php esc_html_e('Previous', 'premium-classifieds'); ?>
                    </a>
                <?php endif; ?>

                <span class="pc-page-info">
                    <?php printf(
                        esc_html__('Page %d of %d', 'premium-classifieds'),
                        $paged,
                        $total_pages
                    ); ?>
                </span>

                <?php if ($paged < $total_pages): ?>
                    <a href="?page=pc-dashboard&tab=payment-history&tx_page=<?php echo ($paged + 1); ?>" class="pc-btn pc-btn-sm">
                        <?php esc_html_e('Next', 'premium-classifieds'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Receipt Modal -->
<div id="pc-receipt-modal" class="pc-modal" style="display: none;">
    <div class="pc-modal-backdrop"></div>
    <div class="pc-modal-content">
        <div class="pc-modal-header">
            <h3><?php esc_html_e('Payment Receipt', 'premium-classifieds'); ?></h3>
            <button type="button" class="pc-modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="pc-modal-body" id="pc-receipt-content">
            <div class="pc-loading">
                <div class="pc-spinner"></div>
            </div>
        </div>
    </div>
</div>

<style>
.pc-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.pc-stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.2s;
}

.pc-stat-card:hover {
    border-color: #2196F3;
    box-shadow: 0 2px 8px rgba(33,150,243,0.1);
}

.pc-stat-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e3f2fd;
    color: #2196F3;
    border-radius: 50%;
}

.pc-stat-icon.pc-stat-success {
    background: #e8f5e9;
    color: #4caf50;
}

.pc-stat-icon.pc-stat-info {
    background: #f3e5f5;
    color: #9c27b0;
}

.pc-stat-icon .dashicons {
    width: 24px;
    height: 24px;
    font-size: 24px;
}

.pc-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1;
    margin-bottom: 4px;
}

.pc-stat-label {
    font-size: 13px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pc-payment-filters {
    margin-bottom: 24px;
    padding: 20px;
    background: #f9fafb;
    border-radius: 8px;
}

.pc-filter-row {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.pc-table-container {
    overflow-x: auto;
    margin-bottom: 24px;
}

.pc-payment-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.pc-payment-table thead {
    background: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
}

.pc-payment-table th {
    padding: 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pc-payment-table td {
    padding: 16px;
    border-bottom: 1px solid #f3f4f6;
}

.pc-payment-table tbody tr:hover {
    background: #f9fafb;
}

.pc-tx-date {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.pc-tx-date strong {
    color: #1f2937;
}

.pc-tx-date small {
    font-size: 12px;
    color: #9ca3af;
}

.pc-tx-type {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pc-tx-listing {
    color: #2196F3;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
}

.pc-tx-listing:hover {
    text-decoration: underline;
}

.pc-tx-listing .dashicons {
    width: 14px;
    height: 14px;
    font-size: 14px;
}

.pc-tx-amount {
    color: #059669;
    font-size: 16px;
}

.pc-payment-id {
    font-size: 11px;
    padding: 4px 8px;
    background: #f3f4f6;
    border-radius: 4px;
    color: #6b7280;
    cursor: pointer;
}

.pc-payment-id:hover {
    background: #e5e7eb;
}

.pc-tx-actions {
    display: flex;
    gap: 8px;
}

.pc-page-info {
    padding: 8px 16px;
    color: #6b7280;
    font-size: 14px;
}

@media (max-width: 768px) {
    .pc-payment-table {
        font-size: 13px;
    }
    
    .pc-payment-table th,
    .pc-payment-table td {
        padding: 12px 8px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Clear filters
    $('#pc-clear-filters').on('click', function() {
        window.location.href = '?page=pc-dashboard&tab=payment-history';
    });
    
    // View receipt
    $('.pc-view-receipt').on('click', function() {
        const txId = $(this).data('tx-id');
        
        $('#pc-receipt-modal').fadeIn(200);
        
        $.ajax({
            url: pcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pc_get_receipt',
                nonce: pcData.nonce,
                tx_id: txId
            },
            success: function(response) {
                if (response.success) {
                    $('#pc-receipt-content').html(response.data.html);
                } else {
                    $('#pc-receipt-content').html('<p class="pc-error">Failed to load receipt.</p>');
                }
            },
            error: function() {
                $('#pc-receipt-content').html('<p class="pc-error">An error occurred.</p>');
            }
        });
    });
    
    // Close modal
    $('.pc-modal-close, .pc-modal-backdrop').on('click', function() {
        $('#pc-receipt-modal').fadeOut(200);
    });
    
    // Copy payment ID on click
    $('.pc-payment-id').on('click', function() {
        const text = $(this).attr('title');
        navigator.clipboard.writeText(text).then(() => {
            pcUtils.showNotice('Payment ID copied!', 'success');
        });
    });
});
</script>