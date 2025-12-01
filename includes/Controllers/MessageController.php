<?php
// File: includes/Controllers/MessageController.php
namespace Premium\Classifieds\Controllers;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Message;
use Premium\Classifieds\Entities\Listing;
use Premium\Classifieds\Entities\Transaction;

/**
 * MessageController
 * Handles sending messages via AJAX. Messages visible fully only after payment (if required by business logic).
 */
class MessageController {

    public function __construct() {
        add_action( 'wp_ajax_pc_send_message', [ $this, 'ajax_send_message' ] );
    }

    /**
     * Send message:
     * POST: nonce, listing_id, to_user, body
     * Business rule: sending message may require payment (configured); for now we allow sending but hide contact until paid.
     */
    public function ajax_send_message() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'Zaloguj się', 'premium-classifieds' ) ], 403 );
        }

        $from_user = get_current_user_id();
        $to_user = isset( $_POST['to_user'] ) ? intval( $_POST['to_user'] ) : 0;
        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
        $body = isset( $_POST['body'] ) ? Helpers::sanitize_html( wp_unslash( $_POST['body'] ) ) : '';

        if ( $to_user <= 0 || empty( $body ) ) {
            return Helpers::json_error( [ 'message' => __( 'Wypełnij wymagane pola', 'premium-classifieds' ) ], 422 );
        }

        // optional: prevent sending message to self
        if ( $from_user === $to_user ) {
            return Helpers::json_error( [ 'message' => __( 'Nie możesz wysłać wiadomości do siebie', 'premium-classifieds' ) ], 400 );
        }

        // If listing provided, ensure it exists
        if ( $listing_id > 0 ) {
            $post = get_post( $listing_id );
            if ( ! $post || $post->post_type !== Listing::POST_TYPE ) {
                return Helpers::json_error( [ 'message' => __( 'Ogłoszenie nie znalezione', 'premium-classifieds' ) ], 404 );
            }
        }

        $msg_id = Message::create( [
            'from_user' => $from_user,
            'to_user' => $to_user,
            'listing_id' => $listing_id,
            'body' => $body,
        ] );

        if ( is_wp_error( $msg_id ) ) {
            return Helpers::json_error( [ 'message' => $msg_id->get_error_message() ], 500 );
        }

        // Optionally notify recipient by email (simple)
        $to_email = get_the_author_meta( 'user_email', $to_user );
        if ( is_email( $to_email ) ) {
            $subject = sprintf( __( 'Nowa wiadomość w %s', 'premium-classifieds' ), get_bloginfo( 'name' ) );
            $message = wp_kses_post( wp_trim_words( $body, 40, '...' ) );
            wp_mail( $to_email, $subject, $message );
        }

        return Helpers::json_success( [ 'message' => __( 'Wysłano wiadomość', 'premium-classifieds' ), 'message_id' => $msg_id ] );
    }
}
