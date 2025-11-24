<?php
/**
 * Payment Intent Handler
 *
 * Manages payment creation and processing for the pay-to-contact model.
 * Handles:
 * - Contact reveal payments ($5)
 * - Listing boost payments ($10)
 * - Subscription payments ($49)
 *
 * @package    PremiumClassifieds
 * @subpackage Payments
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Intent Class
 *
 * @since 2.1.0
 */
class PC_Payment_Intent {
    
    /**
     * Stripe gateway instance
     *
     * @var PC_Stripe_Gateway
     */
    private PC_Stripe_Gateway $gateway;
    
    /**
     * Transactions database
     *
     * @var PC_DB_Transactions
     */
    private PC_DB_Transactions $transactions_db;
    
    /**
     * Access grants database
     *
     * @var PC_DB_Access_Grants
     */
    private PC_DB_Access_Grants $access_db;
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        $this->gateway = new PC_Stripe_Gateway();
        $this->transactions_db = new PC_DB_Transactions();
        $this->access_db = new PC_DB_Access_Grants();
    }
    
    /**
     * AJAX: Create checkout session for contact reveal
     *
     * @since 2.1.0
     * @return void
     */
    public static function create_checkout_session(): void {
        // Validate required parameters
        PC_Ajax_Handler::validate_required(['listing_id', 'payment_type']);
        
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        $payment_type = PC_Ajax_Handler::get_post('payment_type', '', 'text');
        $user_id = get_current_user_id();
        
        // Validate listing exists
        $listing = get_post($listing_id);
        if (!$listing || $listing->post_type !== 'listing') {
            PC_Ajax_Handler::send_error(__('Invalid listing.', 'premium-classifieds'), 400);
        }
        
        // Check if user is listing owner
        if ($user_id === (int) $listing->post_author) {
            PC_Ajax_Handler::send_error(__('You cannot purchase access to your own listing.', 'premium-classifieds'), 400);
        }
        
        // Check if user already has access
        $instance = new self();
        if ($instance->access_db->check_access($user_id, $listing_id)) {
            PC_Ajax_Handler::send_error(__('You already have access to this listing.', 'premium-classifieds'), 400);
        }
        
        // Get pricing
        $prices = [
            'contact_reveal' => (float) get_option('pc_price_contact_reveal', 5.00),
            'listing_boost'  => (float) get_option('pc_price_listing_boost', 10.00),
            'subscription'   => (float) get_option('pc_price_subscription', 49.00),
        ];
        
        if (!isset($prices[$payment_type])) {
            PC_Ajax_Handler::send_error(__('Invalid payment type.', 'premium-classifieds'), 400);
        }
        
        $amount = $prices[$payment_type];
        
        // Get user info
        $user = wp_get_current_user();
        
        // Create description
        $descriptions = [
            'contact_reveal' => sprintf(__('Reveal contact for: %s', 'premium-classifieds'), $listing->post_title),
            'listing_boost'  => sprintf(__('Boost listing: %s', 'premium-classifieds'), $listing->post_title),
            'subscription'   => __('Premium subscription (unlimited access)', 'premium-classifieds'),
        ];
        
        // Prepare line items for Stripe Checkout
        $line_items = [
            [
                'price_data' => [
                    'currency'     => strtolower(get_option('pc_currency', 'USD')),
                    'product_data' => [
                        'name'        => $descriptions[$payment_type],
                        'description' => get_bloginfo('name'),
                    ],
                    'unit_amount' => (int) round($amount * 100), // Convert to cents
                ],
                'quantity' => 1,
            ],
        ];
        
        // Prepare success/cancel URLs
        $success_url = add_query_arg([
            'payment_success' => '1',
            'session_id'      => '{CHECKOUT_SESSION_ID}',
        ], get_permalink($listing_id));
        
        $cancel_url = add_query_arg([
            'payment_cancelled' => '1',
        ], get_permalink($listing_id));
        
        // Create pending transaction
        $transaction_id = $instance->transactions_db->log_payment([
            'user_id'    => $user_id,
            'listing_id' => $listing_id,
            'amount'     => $amount,
            'type'       => $payment_type,
            'status'     => 'pending',
            'metadata'   => [
                'listing_title' => $listing->post_title,
                'user_email'    => $user->user_email,
            ],
        ]);
        
        if (!$transaction_id) {
            PC_Ajax_Handler::send_error(__('Failed to create transaction record.', 'premium-classifieds'), 500);
        }
        
        // Create Stripe checkout session
        $session = $instance->gateway->create_checkout_session([
            'success_url'    => $success_url,
            'cancel_url'     => $cancel_url,
            'line_items'     => $line_items,
            'customer_email' => $user->user_email,
            'metadata'       => [
                'user_id'        => $user_id,
                'listing_id'     => $listing_id,
                'transaction_id' => $transaction_id,
                'payment_type'   => $payment_type,
            ],
        ]);
        
        if (is_wp_error($session)) {
            PC_Ajax_Handler::send_error($session->get_error_message(), 500);
        }
        
        // Return checkout URL
        PC_Ajax_Handler::send_success([
            'checkout_url'   => $session->url,
            'session_id'     => $session->id,
            'transaction_id' => $transaction_id,
        ]);
    }
    
    /**
     * AJAX: Verify payment status
     *
     * @since 2.1.0
     * @return void
     */
    public static function verify_payment(): void {
        PC_Ajax_Handler::validate_required(['session_id']);
        
        $session_id = PC_Ajax_Handler::get_post('session_id', '', 'text');
        $user_id = get_current_user_id();
        
        $instance = new self();
        
        if (!$instance->gateway->is_configured()) {
            PC_Ajax_Handler::send_error(__('Payment system not configured.', 'premium-classifieds'), 500);
        }
        
        try {
            // Retrieve checkout session
            $session = $instance->gateway->stripe->checkout->sessions->retrieve($session_id);
            
            if ($session->payment_status === 'paid') {
                // Get metadata
                $metadata = $session->metadata;
                $transaction_id = isset($metadata['transaction_id']) ? (int) $metadata['transaction_id'] : 0;
                $listing_id = isset($metadata['listing_id']) ? (int) $metadata['listing_id'] : 0;
                
                // Update transaction status
                if ($transaction_id > 0) {
                    $instance->transactions_db->update_status($transaction_id, 'completed');
                }
                
                // Grant access if contact reveal
                if ($metadata['payment_type'] === 'contact_reveal' && $listing_id > 0) {
                    $instance->access_db->grant_access($user_id, $listing_id, [
                        'transaction_id' => $transaction_id,
                    ]);
                }
                
                PC_Ajax_Handler::send_success([
                    'status'  => 'completed',
                    'message' => __('Payment successful! You now have access.', 'premium-classifieds'),
                ]);
            } else {
                PC_Ajax_Handler::send_success([
                    'status'  => $session->payment_status,
                    'message' => __('Payment is being processed...', 'premium-classifieds'),
                ]);
            }
            
        } catch (Exception $e) {
            PC_Ajax_Handler::send_error($e->getMessage(), 500);
        }
    }
    
    /**
     * AJAX: Boost listing (make it featured)
     *
     * @since 2.1.0
     * @return void
     */
    public static function boost_listing(): void {
        PC_Ajax_Handler::validate_required(['listing_id']);
        
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        $user_id = get_current_user_id();
        
        // Verify ownership
        if (!PC_Security::user_owns_listing($listing_id, $user_id)) {
            PC_Ajax_Handler::send_error(__('You do not own this listing.', 'premium-classifieds'), 403);
        }
        
        // Check if already featured
        $is_featured = get_post_meta($listing_id, '_pc_featured', true);
        if ($is_featured) {
            PC_Ajax_Handler::send_error(__('This listing is already featured.', 'premium-classifieds'), 400);
        }
        
        $instance = new self();
        $amount = (float) get_option('pc_price_listing_boost', 10.00);
        $listing = get_post($listing_id);
        $user = wp_get_current_user();
        
        // Create checkout session
        $line_items = [
            [
                'price_data' => [
                    'currency'     => strtolower(get_option('pc_currency', 'USD')),
                    'product_data' => [
                        'name'        => sprintf(__('Boost listing: %s', 'premium-classifieds'), $listing->post_title),
                        'description' => __('Feature your listing for 30 days', 'premium-classifieds'),
                    ],
                    'unit_amount' => (int) round($amount * 100),
                ],
                'quantity' => 1,
            ],
        ];
        
        $success_url = add_query_arg([
            'boost_success' => '1',
            'tab'           => 'my-listings',
        ], home_url('/dashboard/'));
        
        $cancel_url = add_query_arg([
            'boost_cancelled' => '1',
            'tab'             => 'my-listings',
        ], home_url('/dashboard/'));
        
        // Create transaction
        $transaction_id = $instance->transactions_db->log_payment([
            'user_id'    => $user_id,
            'listing_id' => $listing_id,
            'amount'     => $amount,
            'type'       => 'listing_boost',
            'status'     => 'pending',
        ]);
        
        $session = $instance->gateway->create_checkout_session([
            'success_url'    => $success_url,
            'cancel_url'     => $cancel_url,
            'line_items'     => $line_items,
            'customer_email' => $user->user_email,
            'metadata'       => [
                'user_id'        => $user_id,
                'listing_id'     => $listing_id,
                'transaction_id' => $transaction_id,
                'payment_type'   => 'listing_boost',
            ],
        ]);
        
        if (is_wp_error($session)) {
            PC_Ajax_Handler::send_error($session->get_error_message(), 500);
        }
        
        PC_Ajax_Handler::send_success([
            'checkout_url' => $session->url,
        ]);
    }
    
    /**
     * Process completed payment (called by webhook)
     *
     * @since 2.1.0
     * @param object $payment_intent Stripe payment intent object
     * @return bool True on success, false on failure
     */
    public function process_completed_payment(object $payment_intent): bool {
        // Get metadata
        $metadata = $payment_intent->metadata;
        
        if (empty($metadata->transaction_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Payment Intent missing transaction_id');
            }
            return false;
        }
        
        $transaction_id = (int) $metadata->transaction_id;
        $listing_id = isset($metadata->listing_id) ? (int) $metadata->listing_id : 0;
        $user_id = isset($metadata->user_id) ? (int) $metadata->user_id : 0;
        $payment_type = isset($metadata->payment_type) ? $metadata->payment_type : '';
        
        // Update transaction
        $this->transactions_db->update_status($transaction_id, 'completed');
        
        // Grant access if contact reveal
        if ($payment_type === 'contact_reveal' && $listing_id > 0 && $user_id > 0) {
            $this->access_db->grant_access($user_id, $listing_id, [
                'transaction_id' => $transaction_id,
            ]);
        }
        
        // Make listing featured if boost
        if ($payment_type === 'listing_boost' && $listing_id > 0) {
            update_post_meta($listing_id, '_pc_featured', 1);
            update_post_meta($listing_id, '_pc_featured_until', date('Y-m-d H:i:s', strtotime('+30 days')));
        }
        
        return true;
    }
    
    /**
     * Process failed payment
     *
     * @since 2.1.0
     * @param object $payment_intent Stripe payment intent object
     * @return bool True on success
     */
    public function process_failed_payment(object $payment_intent): bool {
        $metadata = $payment_intent->metadata;
        
        if (empty($metadata->transaction_id)) {
            return false;
        }
        
        $transaction_id = (int) $metadata->transaction_id;
        $this->transactions_db->update_status($transaction_id, 'failed');
        
        return true;
    }
    
    /**
     * Process refund
     *
     * @since 2.1.0
     * @param string $payment_intent_id Payment intent ID
     * @return bool True on success
     */
    public function process_refund(string $payment_intent_id): bool {
        // Find transaction by Stripe payment ID
        $transaction = $this->transactions_db->get_by_stripe_id($payment_intent_id);
        
        if (!$transaction) {
            return false;
        }
        
        // Update transaction status
        $this->transactions_db->update_status($transaction->id, 'refunded');
        
        // Revoke access if contact reveal
        if ($transaction->type === 'contact_reveal' && $transaction->listing_id) {
            $this->access_db->revoke_user_access($transaction->user_id, $transaction->listing_id);
        }
        
        // Remove featured status if boost
        if ($transaction->type === 'listing_boost' && $transaction->listing_id) {
            update_post_meta($transaction->listing_id, '_pc_featured', 0);
        }
        
        return true;
    }
    
    /**
     * Get payment types and prices
     *
     * @since 2.1.0
     * @return array Associative array of payment types and prices
     */
    public static function get_prices(): array {
        return [
            'contact_reveal' => [
                'amount'      => (float) get_option('pc_price_contact_reveal', 5.00),
                'label'       => __('Reveal Contact', 'premium-classifieds'),
                'description' => __('View phone number and email address', 'premium-classifieds'),
            ],
            'listing_boost' => [
                'amount'      => (float) get_option('pc_price_listing_boost', 10.00),
                'label'       => __('Boost Listing', 'premium-classifieds'),
                'description' => __('Feature your listing for 30 days', 'premium-classifieds'),
            ],
            'subscription' => [
                'amount'      => (float) get_option('pc_price_subscription', 49.00),
                'label'       => __('Premium Subscription', 'premium-classifieds'),
                'description' => __('Unlimited contact reveals for 30 days', 'premium-classifieds'),
            ],
        ];
    }
}
