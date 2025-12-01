<?php
// File: includes/Controllers/SubscriptionsController.php
namespace Premium\Classifieds\Controllers;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Transaction;

/**
 * Class SubscriptionsController
 * Handles subscription purchases via Stripe Checkout.
 */
class SubscriptionsController {

    public function __construct() {
        add_action( 'wp_ajax_pc_create_subscription_session', [ $this, 'ajax_create_subscription_session' ] );
        add_action( 'wp_ajax_nopriv_pc_create_subscription_session', [ $this, 'ajax_create_subscription_session' ] );
    }

    /**
     * Create a Stripe Checkout Session for subscription plans.
     *
     * Expects POST:
     * - nonce
     * - price_id (Stripe Price ID)
     * - plan_key (optional logical identifier: monthly_yearly_pro)
     */
    public function ajax_create_subscription_session() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }

        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'You must be logged in to subscribe.', 'premium-classifieds' ) ], 403 );
        }

        $price_id = isset( $_POST['price_id'] ) ? sanitize_text_field( wp_unslash( $_POST['price_id'] ) ) : '';
        $plan_key = isset( $_POST['plan_key'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_key'] ) ) : '';

        if ( empty( $price_id ) ) {
            return Helpers::json_error( [ 'message' => __( 'Missing price id', 'premium-classifieds' ) ], 400 );
        }

        $stripe_client = ( new PaymentController() )->get_stripe_client();
        if ( is_wp_error( $stripe_client ) ) {
            return Helpers::json_error( [ 'message' => $stripe_client->get_error_message() ], 500 );
        }

        $user_id = get_current_user_id();

        // Create a pending transaction for audit
        $tx_id = Transaction::create( [
            'user_id' => $user_id,
            'amount' => 0.00, // amount will be known from Stripe price; leave zero
            'currency' => Helpers::get_option( 'pc_currency', 'PLN' ),
            'provider' => 'stripe',
            'provider_id' => null,
            'listing_id' => null,
            'status' => 'pending',
            'meta' => [ 'purpose' => 'subscription', 'plan_key' => $plan_key, 'price_id' => $price_id ],
        ] );

        if ( is_wp_error( $tx_id ) ) {
            return Helpers::json_error( [ 'message' => $tx_id->get_error_message() ], 500 );
        }

        try {
            $session = $stripe_client->checkout->sessions->create([
                'mode' => 'subscription',
                'line_items' => [
                    [
                        'price' => $price_id,
                        'quantity' => 1,
                    ],
                ],
                'client_reference_id' => $user_id,
                'metadata' => [
                    'pc_transaction_id' => $tx_id,
                    'pc_plan_key' => $plan_key,
                    'pc_user_id' => $user_id,
                ],
                'success_url' => add_query_arg( [ 'pc_subscription' => 'success', 'tx' => $tx_id ], home_url( '/' ) ),
                'cancel_url' => add_query_arg( [ 'pc_subscription' => 'cancel', 'tx' => $tx_id ], home_url( '/' ) ),
            ]);

            // Update transaction provider_id to checkout session id
            Transaction::update_status( $tx_id, 'pending', [ 'provider_id' => $session->id ] );

            return Helpers::json_success( [ 'session_id' => $session->id, 'transaction_id' => $tx_id ] );
        } catch ( \Throwable $e ) {
            Helpers::log( 'Subscription session create error: ' . $e->getMessage() );
            return Helpers::json_error( [ 'message' => __( 'Stripe error: ', 'premium-classifieds' ) . $e->getMessage() ], 500 );
        }
    }
}
