<?php
/**
 * Stripe Gateway Integration
 *
 * Wrapper for Stripe PHP SDK. Handles API initialization,
 * authentication, and error handling.
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
 * Stripe Gateway Class
 *
 * @since 2.1.0
 */
class PC_Stripe_Gateway {
    
    /**
     * Stripe API instance
     *
     * @var \Stripe\StripeClient|null
     */
    private $stripe = null;
    
    /**
     * Test mode flag
     *
     * @var bool
     */
    private bool $test_mode;
    
    /**
     * API secret key
     *
     * @var string
     */
    private string $secret_key;
    
    /**
     * API publishable key
     *
     * @var string
     */
    private string $public_key;
    
    /**
     * Webhook secret
     *
     * @var string
     */
    private string $webhook_secret;
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        $this->load_stripe_library();
        $this->initialize_keys();
        $this->setup_hooks();
    }
    
    /**
     * Load Stripe PHP library
     *
     * @since 2.1.0
     * @return void
     */
    private function load_stripe_library(): void {
        // Check if Stripe library is already loaded
        if (class_exists('\Stripe\Stripe')) {
            return;
        }
        
        // Load Stripe via Composer autoload if available
        $composer_autoload = PC_PLUGIN_DIR . 'vendor/autoload.php';
        
        if (file_exists($composer_autoload)) {
            require_once $composer_autoload;
        } else {
            // Fallback: Inform admin to install Stripe SDK
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                echo esc_html__('Premium Classifieds: Stripe PHP SDK not found. Please run "composer install" in the plugin directory.', 'premium-classifieds');
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Initialize API keys from settings
     *
     * @since 2.1.0
     * @return void
     */
    private function initialize_keys(): void {
        $this->test_mode = get_option('pc_stripe_mode', 'test') === 'test';
        
        if ($this->test_mode) {
            $this->secret_key = get_option('pc_stripe_test_secret_key', '');
            $this->public_key = get_option('pc_stripe_test_public_key', '');
        } else {
            $this->secret_key = get_option('pc_stripe_live_secret_key', '');
            $this->public_key = get_option('pc_stripe_live_public_key', '');
        }
        
        $this->webhook_secret = get_option('pc_stripe_webhook_secret', '');
        
        // Initialize Stripe with API key
        if (!empty($this->secret_key) && class_exists('\Stripe\Stripe')) {
            try {
                \Stripe\Stripe::setApiKey($this->secret_key);
                \Stripe\Stripe::setAppInfo(
                    'Premium Classifieds',
                    PC_VERSION,
                    home_url()
                );
                
                $this->stripe = new \Stripe\StripeClient($this->secret_key);
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Stripe Gateway Init Error: ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Setup WordPress hooks
     *
     * @since 2.1.0
     * @return void
     */
    private function setup_hooks(): void {
        // Add Stripe notice if keys not configured
        add_action('admin_notices', [$this, 'admin_notices']);
    }
    
    /**
     * Show admin notice if Stripe not configured
     *
     * @since 2.1.0
     * @return void
     */
    public function admin_notices(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (empty($this->secret_key) || empty($this->public_key)) {
            $settings_url = admin_url('admin.php?page=premium-classifieds-settings');
            echo '<div class="notice notice-warning"><p>';
            printf(
                esc_html__('Premium Classifieds: Stripe is not configured. %sAdd your API keys%s to enable payments.', 'premium-classifieds'),
                '<a href="' . esc_url($settings_url) . '">',
                '</a>'
            );
            echo '</p></div>';
        }
    }
    
    /**
     * Check if Stripe is properly configured
     *
     * @since 2.1.0
     * @return bool True if configured, false otherwise
     */
    public function is_configured(): bool {
        return !empty($this->secret_key) && !empty($this->public_key) && $this->stripe !== null;
    }
    
    /**
     * Create a Payment Intent
     *
     * @since 2.1.0
     * @param array $args {
     *     Payment intent parameters
     *     @type float  $amount      Amount in dollars (will be converted to cents)
     *     @type string $currency    Currency code (default: USD)
     *     @type string $description Payment description
     *     @type array  $metadata    Additional metadata
     * }
     * @return object|\WP_Error Payment intent object or WP_Error on failure
     */
    public function create_payment_intent(array $args) {
        if (!$this->is_configured()) {
            return new \WP_Error('stripe_not_configured', __('Stripe is not properly configured.', 'premium-classifieds'));
        }
        
        try {
            // Convert amount to cents
            $amount_cents = (int) round($args['amount'] * 100);
            
            $intent_params = [
                'amount'      => $amount_cents,
                'currency'    => isset($args['currency']) ? strtolower($args['currency']) : 'usd',
                'description' => isset($args['description']) ? $args['description'] : '',
                'metadata'    => isset($args['metadata']) ? $args['metadata'] : [],
            ];
            
            // Add customer if provided
            if (isset($args['customer_id'])) {
                $intent_params['customer'] = $args['customer_id'];
            }
            
            // Create payment intent
            $intent = $this->stripe->paymentIntents->create($intent_params);
            
            return $intent;
            
        } catch (\Stripe\Exception\CardException $e) {
            return new \WP_Error('card_error', $e->getError()->message);
        } catch (\Stripe\Exception\RateLimitException $e) {
            return new \WP_Error('rate_limit', __('Too many requests. Please try again later.', 'premium-classifieds'));
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return new \WP_Error('invalid_request', $e->getMessage());
        } catch (\Stripe\Exception\AuthenticationException $e) {
            return new \WP_Error('authentication_error', __('Authentication with Stripe failed.', 'premium-classifieds'));
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            return new \WP_Error('api_connection', __('Network communication with Stripe failed.', 'premium-classifieds'));
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return new \WP_Error('api_error', $e->getMessage());
        } catch (Exception $e) {
            return new \WP_Error('unknown_error', $e->getMessage());
        }
    }
    
    /**
     * Create Checkout Session
     *
     * @since 2.1.0
     * @param array $args {
     *     Checkout session parameters
     *     @type string $success_url URL to redirect on success
     *     @type string $cancel_url  URL to redirect on cancel
     *     @type array  $line_items  Array of line items
     *     @type array  $metadata    Additional metadata
     * }
     * @return object|\WP_Error Checkout session object or WP_Error on failure
     */
    public function create_checkout_session(array $args) {
        if (!$this->is_configured()) {
            return new \WP_Error('stripe_not_configured', __('Stripe is not properly configured.', 'premium-classifieds'));
        }
        
        try {
            $session_params = [
                'payment_method_types' => ['card'],
                'mode'                 => 'payment',
                'success_url'          => $args['success_url'],
                'cancel_url'           => $args['cancel_url'],
                'line_items'           => $args['line_items'],
                'metadata'             => isset($args['metadata']) ? $args['metadata'] : [],
            ];
            
            // Add customer email if provided
            if (isset($args['customer_email'])) {
                $session_params['customer_email'] = $args['customer_email'];
            }
            
            $session = $this->stripe->checkout->sessions->create($session_params);
            
            return $session;
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Stripe Checkout Session Error: ' . $e->getMessage());
            }
            return new \WP_Error('checkout_error', $e->getMessage());
        }
    }
    
    /**
     * Retrieve Payment Intent
     *
     * @since 2.1.0
     * @param string $payment_intent_id Payment intent ID
     * @return object|\WP_Error Payment intent object or WP_Error on failure
     */
    public function retrieve_payment_intent(string $payment_intent_id) {
        if (!$this->is_configured()) {
            return new \WP_Error('stripe_not_configured', __('Stripe is not properly configured.', 'premium-classifieds'));
        }
        
        try {
            return $this->stripe->paymentIntents->retrieve($payment_intent_id);
        } catch (Exception $e) {
            return new \WP_Error('retrieve_error', $e->getMessage());
        }
    }
    
    /**
     * Create or retrieve customer
     *
     * @since 2.1.0
     * @param int $user_id WordPress user ID
     * @return string|false Stripe customer ID or false on failure
     */
    public function get_or_create_customer(int $user_id) {
        if (!$this->is_configured()) {
            return false;
        }
        
        // Check if customer ID already exists
        $customer_id = get_user_meta($user_id, '_pc_stripe_customer_id', true);
        
        if (!empty($customer_id)) {
            try {
                // Verify customer still exists in Stripe
                $this->stripe->customers->retrieve($customer_id);
                return $customer_id;
            } catch (Exception $e) {
                // Customer no longer exists, create new one
                delete_user_meta($user_id, '_pc_stripe_customer_id');
            }
        }
        
        // Create new customer
        try {
            $user = get_userdata($user_id);
            
            $customer = $this->stripe->customers->create([
                'email'    => $user->user_email,
                'name'     => $user->display_name,
                'metadata' => [
                    'user_id' => $user_id,
                    'site'    => home_url(),
                ],
            ]);
            
            update_user_meta($user_id, '_pc_stripe_customer_id', $customer->id);
            
            return $customer->id;
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Stripe Create Customer Error: ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Create refund
     *
     * @since 2.1.0
     * @param string $payment_intent_id Payment intent ID
     * @param float|null $amount Amount to refund (null for full refund)
     * @return object|\WP_Error Refund object or WP_Error on failure
     */
    public function create_refund(string $payment_intent_id, ?float $amount = null) {
        if (!$this->is_configured()) {
            return new \WP_Error('stripe_not_configured', __('Stripe is not properly configured.', 'premium-classifieds'));
        }
        
        try {
            $refund_params = ['payment_intent' => $payment_intent_id];
            
            if ($amount !== null) {
                $refund_params['amount'] = (int) round($amount * 100);
            }
            
            return $this->stripe->refunds->create($refund_params);
            
        } catch (Exception $e) {
            return new \WP_Error('refund_error', $e->getMessage());
        }
    }
    
    /**
     * Verify webhook signature
     *
     * @since 2.1.0
     * @param string $payload Request body
     * @param string $signature Stripe-Signature header
     * @return object|\WP_Error Event object or WP_Error on failure
     */
    public function verify_webhook_signature(string $payload, string $signature) {
        if (empty($this->webhook_secret)) {
            return new \WP_Error('webhook_not_configured', __('Webhook secret not configured.', 'premium-classifieds'));
        }
        
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhook_secret
            );
            
            return $event;
            
        } catch (\UnexpectedValueException $e) {
            return new \WP_Error('invalid_payload', $e->getMessage());
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new \WP_Error('invalid_signature', $e->getMessage());
        }
    }
    
    /**
     * Get publishable key
     *
     * @since 2.1.0
     * @return string Publishable key
     */
    public function get_public_key(): string {
        return $this->public_key;
    }
    
    /**
     * Check if in test mode
     *
     * @since 2.1.0
     * @return bool True if test mode
     */
    public function is_test_mode(): bool {
        return $this->test_mode;
    }
    
    /**
     * Format amount for display
     *
     * @since 2.1.0
     * @param float $amount Amount in dollars
     * @param string $currency Currency code
     * @return string Formatted amount
     */
    public static function format_amount(float $amount, string $currency = 'USD'): string {
        $currency_symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
        ];
        
        $symbol = $currency_symbols[strtoupper($currency)] ?? $currency . ' ';
        
        return $symbol . number_format($amount, 2);
    }
    
    /**
     * Get Stripe dashboard URL for payment
     *
     * @since 2.1.0
     * @param string $payment_id Payment intent ID
     * @return string Dashboard URL
     */
    public function get_dashboard_url(string $payment_id): string {
        $base_url = $this->test_mode 
            ? 'https://dashboard.stripe.com/test/payments/'
            : 'https://dashboard.stripe.com/payments/';
        
        return $base_url . $payment_id;
    }
}