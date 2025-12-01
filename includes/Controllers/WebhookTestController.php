<?php
// File: includes/Controllers/WebhookTestController.php
namespace Premium\Classifieds\Controllers;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Transaction;

/**
 * Class WebhookTestController
 * Admin-only tool to simulate webhook events (useful in dev environment).
 */
class WebhookTestController {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
        add_action( 'admin_post_pc_webhook_test', [ $this, 'handle_webhook_test' ] );
    }

    public function add_admin_page() {
        add_submenu_page(
            'pc_main',
            __( 'Webhook Tester', 'premium-classifieds' ),
            __( 'Webhook Tester', 'premium-classifieds' ),
            'manage_options',
            'pc_webhook_tester',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied', 'premium-classifieds' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Webhook Tester', 'premium-classifieds' ); ?></h1>
            <p><?php esc_html_e( 'Simulate a payment_intent.succeeded event for a transaction id.', 'premium-classifieds' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'pc_webhook_test', '_pc_webhook_test' ); ?>
                <input type="hidden" name="action" value="pc_webhook_test" />
                <table class="form-table">
                    <tr>
                        <th><label for="tx_id"><?php esc_html_e( 'Transaction ID', 'premium-classifieds' ); ?></label></th>
                        <td><input id="tx_id" name="tx_id" type="number" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="amount"><?php esc_html_e( 'Amount', 'premium-classifieds' ); ?></label></th>
                        <td><input id="amount" name="amount" type="text" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Simulate succeeded', 'premium-classifieds' ) ); ?>
            </form>
        </div>
        <?php
    }

    public function handle_webhook_test() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied', 'premium-classifieds' ) );
        }
        if ( empty( $_POST['_pc_webhook_test'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pc_webhook_test'] ) ), 'pc_webhook_test' ) ) {
            wp_die( __( 'Invalid nonce', 'premium-classifieds' ) );
        }

        $tx_id = isset( $_POST['tx_id'] ) ? intval( $_POST['tx_id'] ) : 0;
        $amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0.0;

        if ( $tx_id <= 0 ) {
            wp_die( __( 'Invalid transaction id', 'premium-classifieds' ) );
        }

        // Mark transaction succeeded and run business logic similar to webhook
        $tx = Transaction::get( $tx_id );
        if ( ! $tx ) {
            wp_die( __( 'Transaction not found', 'premium-classifieds' ) );
        }

        Transaction::update_status( $tx_id, 'succeeded', [ 'webhook_test' => true, 'amount_override' => $amount ] );

        // Trigger action like PaymentController would
        do_action( 'pc_payment_succeeded', $tx_id, $tx['meta']['purpose'] ?? null, $tx['meta'] ?? [] );

        wp_redirect( add_query_arg( [ 'pc_webhook_test' => 'ok' ], admin_url( 'admin.php?page=pc_webhook_tester' ) ) );
        exit;
    }
}
