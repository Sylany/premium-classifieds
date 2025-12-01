<?php
// File: includes/Controllers/PayPalController.php
namespace Premium\Classifieds\Controllers;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Transaction;

/**
 * Class PayPalController
 * Lightweight PayPal-by-email flow: create transaction and give user instructions to pay manually (admin confirms).
 */
class PayPalController {

    public function __construct() {
        add_action( 'wp_ajax_pc_paypal_create', [ $this, 'ajax_create_paypal_instruction' ] );
        add_action( 'wp_ajax_nopriv_pc_paypal_create', [ $this, 'ajax_create_paypal_instruction' ] );
    }

    /**
     * AJAX: Create PayPal instruction
     *
     * POST: nonce, purpose (reveal_contact|feature|message), listing_id|message_id
     *
     * Returns: provider='paypal', amount, paypal_email, paypal_me_link (if available)
     */
    public function ajax_create_paypal_instruction() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }

        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'Not logged in', 'premium-classifieds' ) ], 403 );
        }

        $user_id = get_current_user_id();
        $purpose = isset( $_POST['purpose'] ) ? sanitize_text_field( wp_unslash( $_POST['purpose'] ) ) : 'reveal_contact';
        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : null;
        $message_id = isset( $_POST['message_id'] ) ? intval( $_POST['message_id'] ) : null;
        $currency = Helpers::get_option( 'pc_currency', 'PLN' );

        // Determine price server side
        $amount = 0.0;
        switch ( $purpose ) {
            case 'feature':
                $amount = floatval( Helpers::get_option( 'pc_price_feature', 9.00 ) );
                break;
            case 'reveal_contact':
            case 'message':
            default:
                $amount = floatval( Helpers::get_option( 'pc_price_reveal_contact', 19.00 ) );
                break;
        }

        // Create a pending transaction with provider paypal
        $tx_id = Transaction::create( [
            'user_id' => $user_id,
            'amount' => $amount,
            'currency' => $currency,
            'provider' => 'paypal',
            'provider_id' => null,
            'listing_id' => $listing_id,
            'status' => 'pending',
            'meta' => [ 'purpose' => $purpose, 'message_id' => $message_id ],
        ] );

        if ( is_wp_error( $tx_id ) ) {
            return Helpers::json_error( [ 'message' => $tx_id->get_error_message() ], 500 );
        }

        $paypal_email = Helpers::get_option( 'pc_paypal_email', '' );
        $paypal_me = '';
        if ( ! empty( $paypal_email ) && strpos( $paypal_email, '@' ) === false ) {
            // maybe admin stored PayPal.me code (simple heuristic)
            $paypal_me = 'https://paypal.me/' . rawurlencode( $paypal_email ) . '/' . number_format( $amount, 2, '.', '' );
        }

        $response = [
            'transaction_id' => $tx_id,
            'amount' => number_format( (float) $amount, 2, '.', '' ),
            'currency' => $currency,
            'paypal_email' => $paypal_email,
            'paypal_me_link' => $paypal_me,
            'instructions' => sprintf( __( 'Please send %1$s %2$s to the PayPal account: %3$s and then notify admin or wait for confirmation.', 'premium-classifieds' ), number_format( (float) $amount, 2, '.', '' ), $currency, $paypal_email ),
        ];

        return Helpers::json_success( $response );
    }
}
