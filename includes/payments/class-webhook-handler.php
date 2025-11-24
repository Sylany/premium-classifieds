<?php
/**
 * Stripe Webhook Handler
 *
 * Listens for Stripe webhook events and processes them.
 * Events handled:
 * - payment_intent.succeeded
 * - payment_intent.payment_failed
 * - charge.refunded
 * - checkout.session.completed
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
 * Webhook Handler Class
 *
 * @since 2.1.0
 */
class PC_Webhook_Handler {
    
    /**
     * Stripe gateway instance
     *
     * @var PC_Stripe_Gateway
     */
    private PC_Stripe_Gateway $gateway;
    
    /**
     * Payment intent handler
     *
     * @var PC_Payment_Intent
     */
    private PC_Payment_Intent $payment_intent;
    
    /**
     * Webhook endpoint slug
     *
     * @var string
     */
    private const WEBHOOK_ENDPOINT = 'pc-stripe-webhook';
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        $this->gateway = new PC_Stripe_Gateway();
        $this->payment_intent = new PC_Payment_Intent();
        
        $this->setup_hooks();
    }
    
    /**
     * Setup WordPress hooks
     *
     * @since 2.1.0
     * @return void
     */
    private function setup_hooks(): void {
        // Add rewrite rule for webhook endpoint
        add_action('init', [$this, 'add_webhook_endpoint']);
        
        // Handle webhook requests
        add_action('template_redirect', [$this, 'handle_webhook_request']);
        
        // Display webhook URL in admin
        add_action('admin_notices', [$this, 'show_webhook_url']);
    }
    
    /**
     * Add webhook endpoint rewrite rule
     *
     * @since 2.1.0
     * @return void
     */
    public function add_webhook_endpoint(): void {
        add_rewrite_rule(
            '^' . self::WEBHOOK_ENDPOINT . '/?$',
            'index.php?pc_webhook=1',
            'top'
        );
        
        add_rewrite_tag('%pc_webhook%', '([^&]+)');
    }
    
    /**
     * Handle incoming webhook request
     *
     * @since 2.1.0
     * @return void
     */
    public function handle_webhook_request(): void {
        // Check if this is a webhook request
        if (!get_query_var('pc_webhook')) {
            return;
        }
        
        // Log webhook request
        $this->log_webhook('Webhook request received');
        
        // Get request body
        $payload = @file_get_contents('php://input');
        
        if (empty($payload)) {
            $this->log_webhook('Empty payload');
            $this->send_response(400, ['error' => 'Empty payload']);
        }
        
        // Get Stripe signature
        $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
        
        if (empty($signature)) {
            $this->log_webhook('Missing signature');
            $this->send_response(400, ['error' => 'Missing signature']);
        }
        
        // Verify webhook signature
        $event = $this->gateway->verify_webhook_signature($payload, $signature);
        
        if (is_wp_error($event)) {
            $this->log_webhook('Signature verification failed: ' . $event->get_error_message());
            $this->send_response(400, ['error' => $event->get_error_message()]);
        }
        
        $this->log_webhook('Event type: ' . $event->type);
        
        // Process event
        try {
            $this->process_event($event);
            $this->send_response(200, ['received' => true]);
        } catch (Exception $e) {
            $this->log_webhook('Processing error: ' . $e->getMessage());
            $this->send_response(500, ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Process webhook event
     *
     * @since 2.1.0
     * @param object $event Stripe event object
     * @return void
     */
    private function process_event(object $event): void {
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handle_checkout_completed($event->data->object);
                break;
                
            case 'payment_intent.succeeded':
                $this->handle_payment_succeeded($event->data->object);
                break;
                
            case 'payment_intent.payment_failed':
                $this->handle_payment_failed($event->data->object);
                break;
                
            case 'charge.refunded':
                $this->handle_refund($event->data->object);
                break;
                
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handle_subscription_event($event->data->object);
                break;
                
            case 'customer.subscription.deleted':
                $this->handle_subscription_cancelled($event->data->object);
                break;
                
            default:
                $this->log_webhook('Unhandled event type: ' . $event->type);
        }
    }
    
    /**
     * Handle checkout session completed
     *
     * @since 2.1.0
     * @param object $session Checkout session object
     * @return void
     */
    private function handle_checkout_completed(object $session): void {
        $this->log_webhook('Checkout completed: ' . $session->id);
        
        if ($session->payment_status !== 'paid') {
            $this->log_webhook('Payment not completed yet');
            return;
        }
        
        $metadata = $session->metadata;
        
        if (empty($metadata->transaction_id)) {
            $this->log_webhook('Missing transaction_id in metadata');
            return;
        }
        
        $transaction_id = (int) $metadata->transaction_id;
        $listing_id = isset($metadata->listing_id) ? (int) $metadata->listing_id : 0;
        $user_id = isset($metadata->user_id) ? (int) $metadata->user_id : 0;
        $payment_type = isset($metadata->payment_type) ? $metadata->payment_type : '';
        
        // Get transactions database
        $transactions_db = new PC_DB_Transactions();
        $access_db = new PC_DB_Access_Grants();
        
        // Update transaction
        $transactions_db->update_status($transaction_id, 'completed');
        
        // Grant access if contact reveal
        if ($payment_type === 'contact_reveal' && $listing_id > 0 && $user_id > 0) {
            $access_db->grant_access($user_id, $listing_id, [
                'transaction_id' => $transaction_id,
                'access_type'    => 'contact_reveal',
            ]);
            
            $this->log_webhook("Access granted: User {$user_id} -> Listing {$listing_id}");
        }
        
        // Make listing featured if boost
        if ($payment_type === 'listing_boost' && $listing_id > 0) {
            update_post_meta($listing_id, '_pc_featured', 1);
            update_post_meta($listing_id, '_pc_featured_until', date('Y-m-d H:i:s', strtotime('+30 days')));
            
            $this->log_webhook("Listing boosted: {$listing_id}");
        }
        
        $this->log_webhook("Transaction {$transaction_id} completed successfully");
    }
    
    /**
     * Handle payment succeeded
     *
     * @since 2.1.0
     * @param object $payment_intent Payment intent object
     * @return void
     */
    private function handle_payment_succeeded(object $payment_intent): void {
        $this->log_webhook('Payment succeeded: ' . $payment_intent->id);
        
        $result = $this->payment_intent->process_completed_payment($payment_intent);
        
        if ($result) {
            $this->log_webhook('Payment processed successfully');
        } else {
            $this->log_webhook('Failed to process payment');
        }
    }
    
    /**
     * Handle payment failed
     *
     * @since 2.1.0
     * @param object $payment_intent Payment intent object
     * @return void
     */
    private function handle_payment_failed(object $payment_intent): void {
        $this->log_webhook('Payment failed: ' . $payment_intent->id);
        
        $this->payment_intent->process_failed_payment($payment_intent);
    }
    
    /**
     * Handle refund
     *
     * @since 2.1.0
     * @param object $charge Charge object
     * @return void
     */
    private function handle_refund(object $charge): void {
        $this->log_webhook('Refund processed: ' . $charge->id);
        
        if (empty($charge->payment_intent)) {
            $this->log_webhook('No payment intent ID in charge');
            return;
        }
        
        $this->payment_intent->process_refund($charge->payment_intent);
    }
    
    /**
     * Handle subscription event
     *
     * @since 2.1.0
     * @param object $subscription Subscription object
     * @return void
     */
    private function handle_subscription_event(object $subscription): void {
        $this->log_webhook('Subscription event: ' . $subscription->id);
        
        // TODO: Implement subscription handling in future version
        // For now, just log the event
    }
    
    /**
     * Handle subscription cancelled
     *
     * @since 2.1.0
     * @param object $subscription Subscription object
     * @return void
     */
    private function handle_subscription_cancelled(object $subscription): void {
        $this->log_webhook('Subscription cancelled: ' . $subscription->id);
        
        // TODO: Revoke subscription access
    }
    
    /**
     * Send HTTP response and exit
     *
     * @since 2.1.0
     * @param int $status_code HTTP status code
     * @param array $data Response data
     * @return void
     */
    private function send_response(int $status_code, array $data): void {
        status_header($status_code);
        header('Content-Type: application/json');
        echo wp_json_encode($data);
        exit;
    }
    
    /**
     * Log webhook activity
     *
     * @since 2.1.0
     * @param string $message Log message
     * @return void
     */
    private function log_webhook(string $message): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        error_log('[PC Webhook] ' . $message);
        
        // Also store in database for admin review
        $this->store_webhook_log($message);
    }
    
    /**
     * Store webhook log in database
     *
     * @since 2.1.0
     * @param string $message Log message
     * @return void
     */
    private function store_webhook_log(string $message): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pc_webhook_logs';
        
        // Create table if not exists (simple logging)
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS {$table_name} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                message TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX created_idx (created_at)
            )"
        );
        
        $wpdb->insert(
            $table_name,
            [
                'message'    => $message,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s']
        );
    }
    
    /**
     * Get webhook logs (admin tool)
     *
     * @since 2.1.0
     * @param int $limit Number of logs to retrieve
     * @return array Array of log entries
     */
    public function get_webhook_logs(int $limit = 50): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pc_webhook_logs';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Show webhook URL in admin
     *
     * @since 2.1.0
     * @return void
     */
    public function show_webhook_url(): void {
        $screen = get_current_screen();
        
        if (!$screen || strpos($screen->id, 'premium-classifieds-settings') === false) {
            return;
        }
        
        $webhook_url = home_url(self::WEBHOOK_ENDPOINT);
        
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php esc_html_e('Stripe Webhook URL:', 'premium-classifieds'); ?></strong><br>
                <code style="background: #f0f0f1; padding: 3px 8px; border-radius: 3px;"><?php echo esc_html($webhook_url); ?></code>
            </p>
            <p>
                <?php esc_html_e('Add this URL to your Stripe Dashboard → Webhooks. Select these events:', 'premium-classifieds'); ?>
                <code>checkout.session.completed</code>,
                <code>payment_intent.succeeded</code>,
                <code>payment_intent.payment_failed</code>,
                <code>charge.refunded</code>
            </p>
        </div>
        <?php
    }
    
    /**
     * Get webhook endpoint URL
     *
     * @since 2.1.0
     * @return string Webhook URL
     */
    public static function get_webhook_url(): string {
        return home_url(self::WEBHOOK_ENDPOINT);
    }
    
    /**
     * Test webhook endpoint (admin tool)
     *
     * @since 2.1.0
     * @return bool True if endpoint is accessible
     */
    public function test_webhook_endpoint(): bool {
        $response = wp_remote_post(
            self::get_webhook_url(),
            [
                'body'    => '{"test": true}',
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 10,
            ]
        );
        
        return !is_wp_error($response);
    }
}
