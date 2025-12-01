<?php
// File: includes/Payments/StripeWebhooks.php
namespace Premium\Classifieds\Payments;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Transaction;
use Premium\Classifieds\Entities\Listing;
use Premium\Classifieds\Entities\Message;

/**
 * StripeWebhooks
 * Register REST route and process events
 */
class StripeWebhooks {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'pc/v1', '/stripe-webhook', [
            'methods' => 'POST',
            'callback' => [ __CLASS__, 'handle_request' ],
            'permission_callback' => '__return_true', // Stripe calls this unauthenticated
        ] );
    }

    /**
     * Handle incoming webhook
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_request( WP_REST_Request $request ): WP_REST_Response {
        $body = $request->get_body();
        $headers = function_exists( 'getallheaders' ) ? getallheaders() : [];
        $sig_header = $headers['Stripe-Signature'] ?? $headers['stripe-signature'] ?? '';

        $webhook_secret = StripeService::webhook_secret();
        $event = null;

        try {
            if ( ! empty( $webhook_secret ) && class_exists( '\\Stripe\\Webhook' ) ) {
                $event = \Stripe\Webhook::constructEvent( $body, $sig_header, $webhook_secret );
            } else {
                // Fallback - parse without signature (not recommended)
                $event = json_decode( $body );
                if ( null === $event ) {
                    throw new \UnexpectedValueException( 'Invalid JSON' );
                }
            }

            $type = is_object( $event ) ? ( $event->type ?? '' ) : ( $event['type'] ?? '' );

            Helpers::log( 'Stripe webhook received: ' . $type );

            switch ( $type ) {
                case 'payment_intent.succeeded':
                    $pi = is_object( $event ) ? $event->data->object : $event['data']['object'];
                    self::handle_payment_intent_succeeded( $pi );
                    break;

                case 'checkout.session.completed':
                    $session = is_object( $event ) ? $event->data->object : $event['data']['object'];
                    self::handle_checkout_session_completed( $session );
                    break;

                case 'invoice.payment_succeeded':
                    $invoice = is_object( $event ) ? $event->data->object : $event['data']['object'];
                    self::handle_invoice_payment_succeeded( $invoice );
                    break;

                case 'payment_intent.payment_failed':
                    $pi = is_object( $event ) ? $event->data->object : $event['data']['object'];
                    self::handle_payment_intent_failed( $pi );
                    break;

                default:
                    // unhandled event
                    Helpers::log( 'Unhandled Stripe event: ' . $type );
                    break;
            }

            return rest_ensure_response( [ 'received' => true ] );
        } catch ( \Stripe\Exception\SignatureVerificationException $e ) {
            Helpers::log( 'Stripe signature verification failed: ' . $e->getMessage() );
            return new WP_REST_Response( 'Invalid signature', 400 );
        } catch ( \Throwable $e ) {
            Helpers::log( 'Stripe webhook processing error: ' . $e->getMessage() );
            return new WP_REST_Response( 'Webhook handler error', 500 );
        }
    }

    /**
     * PaymentIntent succeeded
     *
     * @param object|array $pi
     */
    protected static function handle_payment_intent_succeeded( $pi ): void {
        $metadata = is_object( $pi ) ? (array) $pi->metadata : ( $pi['metadata'] ?? [] );
        $provider_id = is_object( $pi ) ? $pi->id : ( $pi['id'] ?? null );
        $tx_id = isset( $metadata['pc_transaction_id'] ) ? intval( $metadata['pc_transaction_id'] ) : null;

        if ( $tx_id ) {
            Transaction::update_status( $tx_id, 'succeeded', [ 'provider_id' => $provider_id, 'provider_raw' => $pi ] );
        } else {
            // try find by provider id
            $existing = Transaction::get_by_provider( 'stripe', $provider_id );
            if ( $existing && isset( $existing['id'] ) ) {
                Transaction::update_status( intval( $existing['id'] ), 'succeeded', [ 'provider_raw' => $pi ] );
                $tx_id = intval( $existing['id'] );
            } else {
                // create fallback transaction
                $new = Transaction::create( [
                    'user_id' => intval( $metadata['pc_user_id'] ?? 0 ),
                    'amount' => ( ( $pi->amount ?? $pi['amount'] ?? 0 ) / 100 ),
                    'currency' => strtoupper( $pi->currency ?? $pi['currency'] ?? '' ),
                    'provider' => 'stripe',
                    'provider_id' => $provider_id,
                    'status' => 'succeeded',
                    'meta' => [ 'origin' => 'webhook' ],
                ] );
                if ( ! is_wp_error( $new ) ) {
                    $tx_id = $new;
                }
            }
        }

        $purpose = $metadata['pc_purpose'] ?? $metadata['purpose'] ?? null;
        $listing_id = isset( $metadata['pc_listing_id'] ) && $metadata['pc_listing_id'] !== '' ? intval( $metadata['pc_listing_id'] ) : null;
        $message_id = isset( $metadata['pc_message_id'] ) && $metadata['pc_message_id'] !== '' ? intval( $metadata['pc_message_id'] ) : null;
        $user_id = isset( $metadata['pc_user_id'] ) ? intval( $metadata['pc_user_id'] ) : null;

        // Business actions
        if ( $purpose === 'reveal_contact' && $listing_id && $user_id ) {
            Listing::mark_contact_revealed( $listing_id, $user_id );
        }
        if ( $purpose === 'message' && $message_id && $user_id ) {
            Message::mark_paid( $message_id, $user_id, true );
        }
        if ( $purpose === 'feature' && $listing_id ) {
            $days = intval( Helpers::get_option( 'pc_feature_days', 7 ) );
            $until = date( 'Y-m-d H:i:s', strtotime( '+' . $days . ' days' ) );
            update_post_meta( $listing_id, Listing::META_FEATURED_UNTIL, $until );
        }

        do_action( 'pc_payment_succeeded', $tx_id, $purpose, $metadata );
    }

    /**
     * Checkout session completed: mark transaction succeeded, attach provider_id
     */
    protected static function handle_checkout_session_completed( $session ): void {
        $metadata = is_object( $session ) ? (array) $session->metadata : ( $session['metadata'] ?? [] );
        $provider_id = is_object( $session ) ? $session->id : ( $session['id'] ?? null );
        $tx_id = isset( $metadata['pc_transaction_id'] ) ? intval( $metadata['pc_transaction_id'] ) : null;

        if ( $tx_id ) {
            Transaction::update_status( $tx_id, 'succeeded', [ 'provider_id' => $provider_id, 'provider_raw' => $session ] );
        }

        // Business actions similar to payment_intent.succeeded
        $purpose = $metadata['pc_purpose'] ?? null;
        $listing_id = isset( $metadata['pc_listing_id'] ) && $metadata['pc_listing_id'] !== '' ? intval( $metadata['pc_listing_id'] ) : null;
        $user_id = isset( $metadata['pc_user_id'] ) ? intval( $metadata['pc_user_id'] ) : null;

        if ( $purpose === 'reveal_contact' && $listing_id && $user_id ) {
            Listing::mark_contact_revealed( $listing_id, $user_id );
        }
        if ( $purpose === 'feature' && $listing_id ) {
            $days = intval( Helpers::get_option( 'pc_feature_days', 7 ) );
            $until = date( 'Y-m-d H:i:s', strtotime( '+' . $days . ' days' ) );
            update_post_meta( $listing_id, Listing::META_FEATURED_UNTIL, $until );
        }

        do_action( 'pc_checkout_completed', $tx_id, $metadata );
    }

    protected static function handle_invoice_payment_succeeded( $invoice ): void {
        // For subscriptions: assign subscription in DB, grant benefits etc.
        $metadata = is_object( $invoice ) ? (array) $invoice->metadata : ( $invoice['metadata'] ?? [] );
        $tx_id = isset( $metadata['pc_transaction_id'] ) ? intval( $metadata['pc_transaction_id'] ) : null;
        if ( $tx_id ) {
            Transaction::update_status( $tx_id, 'succeeded', [ 'provider_raw' => $invoice ] );
        }
        // Further subscription logic can be added here
        do_action( 'pc_invoice_payment_succeeded', $tx_id, $metadata );
    }

    protected static function handle_payment_intent_failed( $pi ): void {
        $provider_id = is_object( $pi ) ? $pi->id : ( $pi['id'] ?? null );
        $existing = Transaction::get_by_provider( 'stripe', $provider_id );
        if ( $existing && isset( $existing['id'] ) ) {
            Transaction::update_status( intval( $existing['id'] ), 'failed', [ 'provider_raw' => $pi ] );
        }
        do_action( 'pc_payment_failed', $existing['id'] ?? null, $pi );
    }
}
