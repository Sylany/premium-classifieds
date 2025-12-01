<?php
// File: includes/Controllers/PaymentController.php
namespace Premium\Classifieds\Controllers;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Transaction;
use Premium\Classifieds\Entities\Listing;
use Premium\Classifieds\Entities\Message;

/**
 * Class PaymentController
 * Handles Stripe payments (PaymentIntent/Checkout) and webhooks.
 *
 * Notes:
 * - Requires Stripe PHP SDK (composer require stripe/stripe-php).
 * - Stripe keys are stored in options:
 *      pc_stripe_publishable_key
 *      pc_stripe_secret_key
 *      pc_stripe_webhook_secret
 *
 * - Payment flow:
 *   1) AJAX create PaymentIntent -> Transaction created with status 'pending' and provider_id = PaymentIntent.id
 *   2) Client completes payment using client_secret (Stripe.js)
 *   3) Stripe sends webhook 'payment_intent.succeeded' to our REST endpoint
 *   4) Webhook handler verifies signature and updates Transaction status -> 'succeeded' and triggers business logic (reveal contact, mark message paid)
 */
class PaymentController {

    public function __construct() {
        // AJAX endpoints
        add_action( 'wp_ajax_pc_create_payment_intent', [ $this, 'ajax_create_payment_intent' ] );
        add_action( 'wp_ajax_nopriv_pc_create_payment_intent', [ $this, 'ajax_create_payment_intent' ] );

        add_action( 'wp_ajax_pc_create_checkout_session', [ $this, 'ajax_create_checkout_session' ] );
        add_action( 'wp_ajax_nopriv_pc_create_checkout_session', [ $this, 'ajax_create_checkout_session' ] );

        // Register REST route for webhooks
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    /**
     * Return Stripe client or WP_Error if not configured
     *
     * @return \Stripe\StripeClient|\WP_Error
     */
    private function get_stripe_client() {
        if ( ! class_exists( '\\Stripe\\StripeClient' ) ) {
            return new \WP_Error( 'stripe_missing', __( 'Stripe PHP SDK not installed. Run `composer require stripe/stripe-php` and ensure vendor/autoload.php is loaded.', 'premium-classifieds' ) );
        }

        $secret = Helpers::get_option( 'pc_stripe_secret_key', '' );
        if ( empty( $secret ) ) {
            return new \WP_Error( 'stripe_key_missing', __( 'Stripe Secret Key is not configured. Please set it in plugin settings.', 'premium-classifieds' ) );
        }

        try {
            $client = new \Stripe\StripeClient( $secret );
            return $client;
        } catch ( \Throwable $e ) {
            Helpers::log( 'Stripe client init error: ' . $e->getMessage() );
            return new \WP_Error( 'stripe_init_failed', __( 'Failed to initialize Stripe client', 'premium-classifieds' ) );
        }
    }

    /**
     * AJAX: create PaymentIntent for a simple pay-per-action.
     * Expects POST:
     *  - nonce
     *  - amount (float) or use server-side price based on purpose/listing
     *  - currency (optional)
     *  - purpose (reveal_contact|feature|message)
     *  - listing_id (optional)
     *  - message_id (optional)
     */
    public function ajax_create_payment_intent() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ] , 400 );
        }

        // Basic auth: must be logged in to pay
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'You must be logged in to pay.', 'premium-classifieds' ) ], 403 );
        }

        $user_id = get_current_user_id();
        $purpose = isset( $_POST['purpose'] ) ? sanitize_text_field( wp_unslash( $_POST['purpose'] ) ) : 'reveal_contact';
        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : null;
        $message_id = isset( $_POST['message_id'] ) ? intval( $_POST['message_id'] ) : null;
        $currency = isset( $_POST['currency'] ) ? strtoupper( Helpers::sanitize_text( wp_unslash( $_POST['currency'] ) ) ) : Helpers::get_option( 'pc_currency', 'PLN' );

        // Determine price server-side if not provided (to prevent client-side tampering)
        $amount = 0.0;
        switch ( $purpose ) {
            case 'feature':
                $amount = floatval( Helpers::get_option( 'pc_price_feature', 9.00 ) );
                break;
            case 'reveal_contact':
                $amount = floatval( Helpers::get_option( 'pc_price_reveal_contact', 19.00 ) );
                break;
            case 'message':
                // message specific price (could be same as reveal_contact)
                $amount = floatval( Helpers::get_option( 'pc_price_reveal_contact', 19.00 ) );
                break;
            default:
                return Helpers::json_error( [ 'message' => __( 'Invalid purpose', 'premium-classifieds' ) ], 400 );
        }

        // Convert to smallest currency unit for Stripe (cents)
        $amount_minor = $this->to_minor_units( $amount, $currency );

        // Create pending Transaction in DB
        $tx_data = [
            'user_id' => $user_id,
            'amount' => $amount,
            'currency' => $currency,
            'provider' => 'stripe',
            'provider_id' => null, // set after PaymentIntent created
            'listing_id' => $listing_id,
            'status' => 'pending',
            'meta' => [
                'purpose' => $purpose,
                'message_id' => $message_id,
            ],
        ];

        $tx_id = Transaction::create( $tx_data );
        if ( is_wp_error( $tx_id ) ) {
            return Helpers::json_error( [ 'message' => $tx_id->get_error_message() ], 500 );
        }

        // Create Stripe PaymentIntent
        $stripe = $this->get_stripe_client();
        if ( is_wp_error( $stripe ) ) {
            // Rollback: we might choose to delete transaction or leave pending - keep for audit
            return Helpers::json_error( [ 'message' => $stripe->get_error_message() ], 500 );
        }

        try {
            $pi = $stripe->paymentIntents->create([
                'amount' => $amount_minor,
                'currency' => strtolower( $currency ),
                'metadata' => [
                    'pc_transaction_id' => $tx_id,
                    'pc_purpose' => $purpose,
                    'pc_listing_id' => $listing_id ?: '',
                    'pc_message_id' => $message_id ?: '',
                    'pc_user_id' => $user_id,
                ],
                'automatic_payment_methods' => [ 'enabled' => true ],
                // optionally description
                'description' => sprintf( 'PremiumClassifieds: %s (tx:%d)', $purpose, $tx_id ),
            ]);

            // Update transaction with provider_id (PaymentIntent id)
            Transaction::update_status( $tx_id, 'pending', [ 'provider_id' => $pi->id ] );

            return Helpers::json_success( [
                'client_secret' => $pi->client_secret,
                'payment_intent_id' => $pi->id,
                'transaction_id' => $tx_id,
            ] );
        } catch ( \Throwable $e ) {
            Helpers::log( 'Stripe create PaymentIntent error: ' . $e->getMessage() );
            return Helpers::json_error( [ 'message' => __( 'Stripe error: ', 'premium-classifieds' ) . $e->getMessage() ], 500 );
        }
    }

    /**
     * AJAX: create Checkout Session (optional)
     * Accepts price + metadata similar to PaymentIntent flow.
     */
    public function ajax_create_checkout_session() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ] , 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'You must be logged in to pay.', 'premium-classifieds' ) ], 403 );
        }

        $user_id = get_current_user_id();
        $purpose = isset( $_POST['purpose'] ) ? sanitize_text_field( wp_unslash( $_POST['purpose'] ) ) : 'reveal_contact';
        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : null;
        $message_id = isset( $_POST['message_id'] ) ? intval( $_POST['message_id'] ) : null;
        $currency = isset( $_POST['currency'] ) ? strtoupper( Helpers::sanitize_text( wp_unslash( $_POST['currency'] ) ) ) : Helpers::get_option( 'pc_currency', 'PLN' );

        $amount = 0.0;
        switch ( $purpose ) {
            case 'feature':
                $amount = floatval( Helpers::get_option( 'pc_price_feature', 9.00 ) );
                break;
            case 'reveal_contact':
                $amount = floatval( Helpers::get_option( 'pc_price_reveal_contact', 19.00 ) );
                break;
            default:
                return Helpers::json_error( [ 'message' => __( 'Invalid purpose', 'premium-classifieds' ) ], 400 );
        }
        $amount_minor = $this->to_minor_units( $amount, $currency );

        $tx_id = Transaction::create( [
            'user_id' => $user_id,
            'amount' => $amount,
            'currency' => $currency,
            'provider' => 'stripe',
            'provider_id' => null,
            'listing_id' => $listing_id,
            'status' => 'pending',
            'meta' => [
                'purpose' => $purpose,
                'message_id' => $message_id,
            ],
        ] );

        if ( is_wp_error( $tx_id ) ) {
            return Helpers::json_error( [ 'message' => $tx_id->get_error_message() ], 500 );
        }

        $stripe = $this->get_stripe_client();
        if ( is_wp_error( $stripe ) ) {
            return Helpers::json_error( [ 'message' => $stripe->get_error_message() ], 500 );
        }

        // Create Checkout Session
        try {
            $success_url = add_query_arg( [ 'pc_payment' => 'success', 'tx' => $tx_id ], home_url( '/' ) );
            $cancel_url = add_query_arg( [ 'pc_payment' => 'cancel', 'tx' => $tx_id ], home_url( '/' ) );

            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'mode' => 'payment',
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower( $currency ),
                            'product_data' => [
                                'name' => sprintf( 'Premium Classifieds: %s', $purpose ),
                            ],
                            'unit_amount' => $amount_minor,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'metadata' => [
                    'pc_transaction_id' => $tx_id,
                    'pc_purpose' => $purpose,
                    'pc_listing_id' => $listing_id ?: '',
                    'pc_message_id' => $message_id ?: '',
                    'pc_user_id' => $user_id,
                ],
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
            ]);

            // Update transaction with provider id (session id)
            Transaction::update_status( $tx_id, 'pending', [ 'provider_id' => $session->id ] );

            return Helpers::json_success( [ 'session_id' => $session->id, 'transaction_id' => $tx_id ] );
        } catch ( \Throwable $e ) {
            Helpers::log( 'Stripe Checkout create error: ' . $e->getMessage() );
            return Helpers::json_error( [ 'message' => __( 'Stripe error: ', 'premium-classifieds' ) . $e->getMessage() ], 500 );
        }
    }

    /**
     * Register REST route(s) for webhook handling.
     */
    public function register_rest_routes() {
        register_rest_route( 'pc/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_webhook' ],
            'permission_callback' => '__return_true', // Stripe will call unauthenticated
        ] );
    }

    /**
     * Handle Stripe webhook.
     * Verifies signature (if secret configured) and processes events.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_webhook( \WP_REST_Request $request ) {
        $body = $request->get_body();
        $headers = getallheaders();

        $stripe_secret = Helpers::get_option( 'pc_stripe_webhook_secret', '' );
        $stripe_client = $this->get_stripe_client();

        // If stripe client not available, log and exit
        if ( is_wp_error( $stripe_client ) ) {
            Helpers::log( 'Webhook received but Stripe client missing: ' . $stripe_client->get_error_message() );
            return rest_ensure_response( [ 'ok' => false, 'message' => 'Stripe client missing' ] );
        }

        $sig_header = isset( $headers['Stripe-Signature'] ) ? $headers['Stripe-Signature'] : ( isset( $headers['stripe-signature'] ) ? $headers['stripe-signature'] : '' );

        try {
            if ( ! empty( $stripe_secret ) && class_exists( '\\Stripe\\Webhook' ) ) {
                // Use Stripe\Webhook to verify signature
                $event = \Stripe\Webhook::constructEvent( $body, $sig_header, $stripe_secret );
            } else {
                // Attempt to decode without verifying (less secure) - only if no webhook secret set
                $event = json_decode( $body );
                if ( null === $event ) {
                    throw new \Exception( 'Invalid webhook payload' );
                }
            }

            $type = is_object( $event ) ? $event->type ?? ( $event['type'] ?? '' ) : ( $event->type ?? '' );

            Helpers::log( 'Stripe webhook received: ' . (string) $type );

            if ( $type === 'payment_intent.succeeded' ) {
                $pi = is_object( $event ) ? $event->data->object : $event['data']['object'];
                $this->process_successful_payment_intent( $pi );
            } elseif ( $type === 'checkout.session.completed' ) {
                $session = is_object( $event ) ? $event->data->object : $event['data']['object'];
                // Optionally handle checkout session events
                $this->process_checkout_session( $session );
            } elseif ( $type === 'payment_intent.payment_failed' ) {
                $pi = is_object( $event ) ? $event->data->object : $event['data']['object'];
                $this->process_failed_payment_intent( $pi );
            }

            return rest_ensure_response( [ 'received' => true ] );
        } catch ( \UnexpectedValueException $e ) {
            Helpers::log( 'Webhook payload invalid: ' . $e->getMessage() );
            return new \WP_REST_Response( 'Invalid payload', 400 );
        } catch ( \Stripe\Exception\SignatureVerificationException $e ) {
            Helpers::log( 'Webhook signature verification failed: ' . $e->getMessage() );
            return new \WP_REST_Response( 'Signature verification failed', 400 );
        } catch ( \Throwable $e ) {
            Helpers::log( 'Webhook processing error: ' . $e->getMessage() );
            return new \WP_REST_Response( 'Webhook handler error', 500 );
        }
    }

    /**
     * Process a successful PaymentIntent object from Stripe.
     *
     * @param object|array $pi
     */
    private function process_successful_payment_intent( $pi ): void {
        // Support both object and associative array
        $metadata = is_object( $pi ) ? (array) $pi->metadata : ( $pi['metadata'] ?? [] );
        $provider_id = is_object( $pi ) ? $pi->id : ( $pi['id'] ?? null );
        $amount = is_object( $pi ) ? ( $pi->amount / 100 ) : ( ( $pi['amount'] ?? 0 ) / 100 );
        $currency = is_object( $pi ) ? strtoupper( $pi->currency ) : strtoupper( $pi['currency'] ?? '' );

        $tx_meta = [
            'provider_raw' => is_object( $pi ) ? (array) $pi : ( $pi ),
        ];

        $tx_id = isset( $metadata['pc_transaction_id'] ) ? intval( $metadata['pc_transaction_id'] ) : null;
        $purpose = isset( $metadata['pc_purpose'] ) ? sanitize_text_field( $metadata['pc_purpose'] ) : null;
        $listing_id = isset( $metadata['pc_listing_id'] ) && $metadata['pc_listing_id'] !== '' ? intval( $metadata['pc_listing_id'] ) : null;
        $message_id = isset( $metadata['pc_message_id'] ) && $metadata['pc_message_id'] !== '' ? intval( $metadata['pc_message_id'] ) : null;
        $user_id = isset( $metadata['pc_user_id'] ) ? intval( $metadata['pc_user_id'] ) : null;

        if ( $tx_id ) {
            Transaction::update_status( $tx_id, 'succeeded', array_merge( $tx_meta, [ 'provider_id' => $provider_id, 'amount_minor' => $pi->amount ?? ($pi['amount'] ?? 0) ] ) );
        } else {
            // Try find transaction by provider_id
            $existing = Transaction::get_by_provider( 'stripe', $provider_id );
            if ( $existing && isset( $existing['id'] ) ) {
                $tx_id = intval( $existing['id'] );
                Transaction::update_status( $tx_id, 'succeeded', array_merge( $tx_meta, [ 'amount_minor' => $pi->amount ?? ($pi['amount'] ?? 0) ] ) );
            } else {
                // Create a record if none exists (rare)
                $new_tx = Transaction::create( [
                    'user_id' => $user_id ?: 0,
                    'amount' => $amount,
                    'currency' => $currency,
                    'provider' => 'stripe',
                    'provider_id' => $provider_id,
                    'listing_id' => $listing_id,
                    'status' => 'succeeded',
                    'meta' => [ 'origin' => 'webhook', 'raw' => $tx_meta ],
                ] );
                $tx_id = is_wp_error( $new_tx ) ? null : $new_tx;
            }
        }

        // Business logic on success
        if ( $purpose === 'reveal_contact' && $listing_id && $user_id ) {
            Listing::mark_contact_revealed( $listing_id, $user_id );
        }

        if ( $purpose === 'message' && $message_id && $user_id ) {
            // Mark message paid and reveal contact if needed
            Message::mark_paid( $message_id, $user_id, true );
        }

        if ( $purpose === 'feature' && $listing_id ) {
            // Add featured_until meta for listing (example: 7 days)
            $days = 7;
            $until = date( 'Y-m-d H:i:s', strtotime( '+' . $days . ' days' ) );
            update_post_meta( $listing_id, Listing::META_FEATURED_UNTIL, $until );
        }

        do_action( 'pc_payment_succeeded', $tx_id, $purpose, $metadata );
    }

    /**
     * Process checkout session completed
     *
     * @param object|array $session
     */
    private function process_checkout_session( $session ): void {
        $metadata = is_object( $session ) ? (array) $session->metadata : ( $session['metadata'] ?? [] );
        $provider_id = is_object( $session ) ? $session->id : ( $session['id'] ?? null );

        $tx_id = isset( $metadata['pc_transaction_id'] ) ? intval( $metadata['pc_transaction_id'] ) : null;
        if ( $tx_id ) {
            Transaction::update_status( $tx_id, 'succeeded', [ 'provider_id' => $provider_id ] );
            $purpose = $metadata['pc_purpose'] ?? null;
            $listing_id = isset( $metadata['pc_listing_id'] ) && $metadata['pc_listing_id'] !== '' ? intval( $metadata['pc_listing_id'] ) : null;
            $user_id = isset( $metadata['pc_user_id'] ) ? intval( $metadata['pc_user_id'] ) : null;

            if ( $purpose === 'reveal_contact' && $listing_id && $user_id ) {
                Listing::mark_contact_revealed( $listing_id, $user_id );
            }
            if ( $purpose === 'feature' && $listing_id ) {
                $days = 7;
                $until = date( 'Y-m-d H:i:s', strtotime( '+' . $days . ' days' ) );
                update_post_meta( $listing_id, Listing::META_FEATURED_UNTIL, $until );
            }
        }
    }

    /**
     * Process failed PaymentIntent
     *
     * @param object|array $pi
     */
    private function process_failed_payment_intent( $pi ): void {
        $metadata = is_object( $pi ) ? (array) $pi->metadata : ( $pi['metadata'] ?? [] );
        $provider_id = is_object( $pi ) ? $pi->id : ( $pi['id'] ?? null );

        // Try find transaction by provider id
        $existing = Transaction::get_by_provider( 'stripe', $provider_id );
        if ( $existing && isset( $existing['id'] ) ) {
            Transaction::update_status( intval( $existing['id'] ), 'failed', [ 'provider_raw' => $pi ] );
        }
        do_action( 'pc_payment_failed', $existing['id'] ?? null, $metadata );
    }

    /**
     * Convert float amount to minor unit integer (e.g. cents) for Stripe.
     * Supports common currencies simply (no exhaustive currency table).
     *
     * @param float $amount
     * @param string $currency
     * @return int
     */
    private function to_minor_units( float $amount, string $currency ): int {
        // Currencies without minor units: JPY etc. For simplicity assume 2 decimals except JPY.
        $no_minor = [ 'JPY' ];
        if ( in_array( strtoupper( $currency ), $no_minor, true ) ) {
            return (int) round( $amount );
        }
        return (int) round( $amount * 100 );
    }
}
