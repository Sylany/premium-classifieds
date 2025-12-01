<?php
// File: includes/Payments/StripeCheckout.php
namespace Premium\Classifieds\Payments;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Transaction;

/**
 * StripeCheckout
 * Higher-level wrappers for PaymentIntent / Checkout creation flows.
 */
class StripeCheckout {

    /**
     * Create PaymentIntent for a one-off payment
     *
     * @param int $tx_id Transaction id in local DB (created before)
     * @param float $amount Decimal amount in major units (e.g. 19.00)
     * @param string $currency
     * @param array $metadata
     * @return array|\WP_Error  ['client_secret'=>..., 'id'=>...]
     */
    public static function createPaymentIntent( int $tx_id, float $amount, string $currency = 'PLN', array $metadata = [] ) {
        $stripe = StripeService::client();
        if ( is_wp_error( $stripe ) ) {
            return $stripe;
        }

        $amount_minor = StripeService::to_minor_units( $amount, $currency );

        try {
            $meta = wp_parse_args( $metadata, [
                'pc_transaction_id' => $tx_id,
            ] );

            $pi = $stripe->paymentIntents->create([
                'amount' => $amount_minor,
                'currency' => strtolower( $currency ),
                'automatic_payment_methods' => [ 'enabled' => true ],
                'metadata' => $meta,
                'description' => sprintf( 'PremiumClassifieds tx:%d', $tx_id ),
            ]);

            // Persist provider_id
            Transaction::update_status( $tx_id, 'pending', [ 'provider_id' => $pi->id ] );

            return [
                'client_secret' => $pi->client_secret,
                'id' => $pi->id,
            ];
        } catch ( \Throwable $e ) {
            Helpers::log( 'Stripe createPaymentIntent error: ' . $e->getMessage() );
            return new \WP_Error( 'stripe_pi_error', $e->getMessage() );
        }
    }

    /**
     * Create Checkout Session (payment or subscription)
     *
     * @param array $args associative array: mode=payment|subscription, line_items, metadata, success_url, cancel_url
     * @return array|\WP_Error ['id'=>..., 'url'=>...] or WP_Error
     */
    public static function createCheckoutSession( array $args ) {
        $stripe = StripeService::client();
        if ( is_wp_error( $stripe ) ) {
            return $stripe;
        }

        try {
            $session = $stripe->checkout->sessions->create( $args );
            return [
                'id' => $session->id,
                'url' => $session->url ?? null,
                'object' => $session,
            ];
        } catch ( \Throwable $e ) {
            Helpers::log( 'Stripe createCheckoutSession error: ' . $e->getMessage() );
            return new \WP_Error( 'stripe_checkout_error', $e->getMessage() );
        }
    }

    /**
     * Retrieve PaymentIntent by id (helper)
     *
     * @param string $pi_id
     * @return object|\WP_Error
     */
    public static function retrievePaymentIntent( string $pi_id ) {
        $stripe = StripeService::client();
        if ( is_wp_error( $stripe ) ) {
            return $stripe;
        }
        try {
            return $stripe->paymentIntents->retrieve( $pi_id );
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'stripe_retrieve_pi', $e->getMessage() );
        }
    }
}
