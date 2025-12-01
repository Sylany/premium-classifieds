<?php
// File: includes/Controllers/ListingController.php
namespace Premium\Classifieds\Controllers;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Listing;
use Premium\Classifieds\Entities\Transaction;
use WP_Error;

/**
 * ListingController
 * AJAX endpoints for creating, updating, deleting, renewing and requesting feature for listings.
 */
class ListingController {

    public function __construct() {
        // logged-in actions only
        add_action( 'wp_ajax_pc_save_listing', [ $this, 'ajax_save_listing' ] );
        add_action( 'wp_ajax_pc_delete_listing', [ $this, 'ajax_delete_listing' ] );
        add_action( 'wp_ajax_pc_renew_listing', [ $this, 'ajax_renew_listing' ] );
        add_action( 'wp_ajax_pc_request_feature', [ $this, 'ajax_request_feature' ] );
    }

    /**
     * Create or update listing
     * POST: nonce, listing_id (optional), title, content, contact_email, contact_phone, gallery (array of attachment ids optional)
     */
    public function ajax_save_listing() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'Zaloguj się', 'premium-classifieds' ) ], 403 );
        }

        $user_id = get_current_user_id();

        // basic fields
        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
        $title = isset( $_POST['title'] ) ? Helpers::sanitize_text( wp_unslash( $_POST['title'] ) ) : '';
        $content = isset( $_POST['content'] ) ? Helpers::sanitize_html( wp_unslash( $_POST['content'] ) ) : '';
        $contact_email = isset( $_POST['contact_email'] ) ? Helpers::sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
        $contact_phone = isset( $_POST['contact_phone'] ) ? Helpers::sanitize_text( wp_unslash( $_POST['contact_phone'] ) ) : '';

        // validation
        if ( empty( $title ) ) {
            return Helpers::json_error( [ 'message' => __( 'Tytuł jest wymagany', 'premium-classifieds' ) ], 422 );
        }

        // If updating, check ownership
        if ( $listing_id > 0 ) {
            $post = get_post( $listing_id );
            if ( ! $post || $post->post_type !== Listing::POST_TYPE ) {
                return Helpers::json_error( [ 'message' => __( 'Ogłoszenie nie znalezione', 'premium-classifieds' ) ], 404 );
            }
            if ( (int) $post->post_author !== (int) $user_id && ! current_user_can( 'edit_others_posts' ) ) {
                return Helpers::json_error( [ 'message' => __( 'Brak uprawnień', 'premium-classifieds' ) ], 403 );
            }

            $res = Listing::update( $listing_id, [
                'title' => $title,
                'content' => $content,
                'meta' => [
                    Listing::META_CONTACT_EMAIL => $contact_email,
                    Listing::META_CONTACT_PHONE => $contact_phone,
                ],
            ] );

            if ( is_wp_error( $res ) ) {
                return Helpers::json_error( [ 'message' => $res->get_error_message() ], 500 );
            }

            // Gallery attachments (optional)
            if ( ! empty( $_POST['gallery'] ) && is_array( $_POST['gallery'] ) ) {
                $this->sync_gallery( $listing_id, $_POST['gallery'] );
            }

            return Helpers::json_success( [ 'message' => __( 'Ogłoszenie zaktualizowane', 'premium-classifieds' ), 'listing_id' => $listing_id ] );
        }

        // Creating new listing — check user limit
        if ( ! \Premium\Classifieds\Entities\User::can_create_listing( $user_id ) && ! current_user_can( 'manage_options' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Osiągnięto limit ogłoszeń', 'premium-classifieds' ) ], 403 );
        }

        $status = Helpers::get_option( 'pc_auto_approve', 'yes' ) === 'yes' ? 'publish' : 'pending';
        $new_id = Listing::create( [
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'meta' => [
                Listing::META_CONTACT_EMAIL => $contact_email,
                Listing::META_CONTACT_PHONE => $contact_phone,
            ],
        ], $user_id );

        if ( is_wp_error( $new_id ) ) {
            return Helpers::json_error( [ 'message' => $new_id->get_error_message() ], 500 );
        }

        if ( ! empty( $_POST['gallery'] ) && is_array( $_POST['gallery'] ) ) {
            $this->sync_gallery( $new_id, $_POST['gallery'] );
        }

        return Helpers::json_success( [ 'message' => __( 'Ogłoszenie utworzone', 'premium-classifieds' ), 'listing_id' => $new_id ] );
    }

    /**
     * Delete listing (ajax)
     * POST: nonce, listing_id
     */
    public function ajax_delete_listing() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'Zaloguj się', 'premium-classifieds' ) ], 403 );
        }

        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
        if ( $listing_id <= 0 ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid listing id', 'premium-classifieds' ) ], 400 );
        }

        $post = get_post( $listing_id );
        if ( ! $post || $post->post_type !== Listing::POST_TYPE ) {
            return Helpers::json_error( [ 'message' => __( 'Ogłoszenie nie znalezione', 'premium-classifieds' ) ], 404 );
        }

        $user_id = get_current_user_id();
        if ( (int) $post->post_author !== (int) $user_id && ! current_user_can( 'delete_others_posts' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Brak uprawnień', 'premium-classifieds' ) ], 403 );
        }

        $res = Listing::delete( $listing_id );
        if ( is_wp_error( $res ) ) {
            return Helpers::json_error( [ 'message' => $res->get_error_message() ], 500 );
        }

        return Helpers::json_success( [ 'message' => __( 'Ogłoszenie usunięte', 'premium-classifieds' ) ] );
    }

    /**
     * Renew listing (extend expiry) — simple implementation: update post_date to now
     * POST: nonce, listing_id
     */
    public function ajax_renew_listing() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'Zaloguj się', 'premium-classifieds' ) ], 403 );
        }

        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
        if ( $listing_id <= 0 ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid id', 'premium-classifieds' ) ], 400 );
        }

        $post = get_post( $listing_id );
        if ( ! $post || $post->post_type !== Listing::POST_TYPE ) {
            return Helpers::json_error( [ 'message' => __( 'Listing not found', 'premium-classifieds' ) ], 404 );
        }

        $user_id = get_current_user_id();
        if ( (int) $post->post_author !== (int) $user_id && ! current_user_can( 'edit_others_posts' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Brak uprawnień', 'premium-classifieds' ) ], 403 );
        }

        $now = current_time( 'mysql' );
        $updated = wp_update_post( [ 'ID' => $listing_id, 'post_date' => $now, 'post_date_gmt' => get_gmt_from_date( $now ) ], true );
        if ( is_wp_error( $updated ) ) {
            return Helpers::json_error( [ 'message' => $updated->get_error_message() ], 500 );
        }

        return Helpers::json_success( [ 'message' => __( 'Ogłoszenie odnowione', 'premium-classifieds' ) ] );
    }

    /**
     * Request feature (creates a pending transaction, returns transaction id and next steps)
     * POST: nonce, listing_id
     */
    public function ajax_request_feature() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'Zaloguj się', 'premium-classifieds' ) ], 403 );
        }

        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
        if ( $listing_id <= 0 ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid listing id', 'premium-classifieds' ) ], 400 );
        }

        $post = get_post( $listing_id );
        if ( ! $post || $post->post_type !== Listing::POST_TYPE ) {
            return Helpers::json_error( [ 'message' => __( 'Listing not found', 'premium-classifieds' ) ], 404 );
        }

        $user_id = get_current_user_id();
        if ( (int) $post->post_author !== (int) $user_id && ! current_user_can( 'edit_others_posts' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Brak uprawnień', 'premium-classifieds' ) ], 403 );
        }

        $price = floatval( Helpers::get_option( 'pc_price_feature', 9.00 ) );
        $currency = Helpers::get_option( 'pc_currency', 'PLN' );

        // create pending transaction; payment will be processed via Stripe/PayPal controllers
        $tx_id = Transaction::create( [
            'user_id' => $user_id,
            'listing_id' => $listing_id,
            'amount' => $price,
            'currency' => $currency,
            'provider' => '', // set by payment flow
            'provider_id' => null,
            'status' => 'pending',
            'meta' => [ 'purpose' => 'feature', 'listing_title' => get_the_title( $listing_id ) ],
        ] );

        if ( is_wp_error( $tx_id ) ) {
            return Helpers::json_error( [ 'message' => $tx_id->get_error_message() ], 500 );
        }

        return Helpers::json_success( [ 'message' => __( 'Transakcja utworzona', 'premium-classifieds' ), 'transaction_id' => $tx_id, 'amount' => $price, 'currency' => $currency ] );
    }

    /**
     * Sync gallery attachment IDs (store as post meta list)
     *
     * @param int $listing_id
     * @param array $attachments array of attachment ids (ints)
     * @return void
     */
    protected function sync_gallery( int $listing_id, array $attachments ) {
        $clean = [];
        foreach ( $attachments as $a ) {
            $id = intval( $a );
            if ( $id > 0 && get_post_type( $id ) === 'attachment' ) {
                $clean[] = $id;
            }
        }
        update_post_meta( $listing_id, 'pc_gallery', $clean );
    }
}
