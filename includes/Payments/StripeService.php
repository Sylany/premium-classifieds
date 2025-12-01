<?php
// File: includes/Payments/StripeService.php
namespace Premium\Classifieds\Payments;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;

/**
 * StripeService
 * Thin wrapper returning Stripe client and helpers.
 */
class StripeService {

    /**
     * Return \Stripe\StripeClient or WP_Error if missing/config wrong
     *
     * @return \Stripe\StripeClient|\WP_Error
     */
    public static function client() {
        if ( ! class_exists( '\\Stripe\\StripeClient' ) ) {
            return new \WP_Error( 'stripe_sdk_missing', __( 'Stripe PHP SDK missing. Run `composer require stripe/stripe-php`.', 'premium-classifieds' ) );
        }

        $secret = Helpers::get_option( 'pc_stripe_secret_key', '' );
        if ( empty( $secret ) ) {
            return new \WP_Error( 'stripe_secret_missing', __( 'Stripe secret key not configured.', 'premium-classifieds' ) );
        }

        try {
            $client = new \Stripe\StripeClient( $secret );
            return $client;
        } catch ( \Throwable $e ) {
            Helpers::log( 'Stripe client init error: ' . $e->getMessage() );
            return new \WP_Error( 'stripe_init_error', $e->getMessage() );
        }
    }

    /**
     * Convert decimal amount to minor units for currency
     *
     * @param float $amount
     * @param string $currency
     * @return int
     */
    public static function to_minor_units( float $amount, string $currency ): int {
        $no_minor = [ 'JPY' ];
        $currency = strtoupper( $currency );
        if ( in_array( $currency, $no_minor, true ) ) {
            return (int) round( $amount );
        }
        return (int) round( $amount * 100 );
    }

    /**
     * Read publishable key from options
     *
     * @return string
     */
    public static function publishable_key(): string {
        return (string) Helpers::get_option( 'pc_stripe_publishable_key', '' );
    }

    /**
     * Webhook secret
     *
     * @return string
     */
    public static function webhook_secret(): string {
        return (string) Helpers::get_option( 'pc_stripe_webhook_secret', '' );
    }
}
