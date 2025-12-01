<?php
// File: public/ajax.php
if ( ! defined( 'ABSPATH' ) ) exit;

use PremiumClassifieds\Entities\Listing;
use PremiumClassifieds\Entities\Message;
use PremiumClassifieds\Entities\Favorite;
use PremiumClassifieds\Entities\Transaction;
use PremiumClassifieds\Entities\User;

/**
 * Helpers
 */
function pc_ajax_json_error( $message, $code = 400 ) {
    wp_send_json_error( [ 'message' => $message ], $code );
}

/**
 * Save listing (create or update)
 */
add_action( 'wp_ajax_pc_save_listing', function() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pc_frontend_nonce' ) ) {
        pc_ajax_json_error( __( 'Invalid nonce', 'premium-classifieds' ), 403 );
    }
    if ( ! is_user_logged_in() ) {
        pc_ajax_json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
    }
    $user_id = get_current_user_id();

    $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
    $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    $content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
    $contact_email = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
    $contact_phone = isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '';

    if ( empty( $title ) ) {
        pc_ajax_json_error( __( 'Tytuł jest wymagany', 'premium-classifieds' ) );
    }

    if ( $listing_id > 0 ) {
        // update
        $res = Listing::update( $listing_id, [
            'title' => $title,
            'content' => $content,
            'meta' => [
                Listing::META_CONTACT_EMAIL => $contact_email,
                Listing::META_CONTACT_PHONE => $contact_phone,
            ],
        ] );
        if ( is_wp_error( $res ) ) {
            pc_ajax_json_error( $res->get_error_message() );
        }
        wp_send_json_success( [ 'message' => __( 'Ogłoszenie zaktualizowane', 'premium-classifieds' ), 'listing_id' => $listing_id ] );
    }

    // create
    $status = get_option( 'pc_auto_approve', '1' ) == '1' ? 'publish' : 'pending';
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
        pc_ajax_json_error( $new_id->get_error_message() );
    }

    // handle gallery ids if provided (comma separated or array)
    if ( ! empty( $_POST['gallery'] ) ) {
        $gallery = $_POST['gallery'];
        if ( is_string( $gallery ) ) {
            $gallery = explode( ',', $gallery );
        }
        $clean = [];
        foreach ( (array) $gallery as $a ) {
            $id = intval( $a );
            if ( $id > 0 && get_post_type( $id ) === 'attachment' ) $clean[] = $id;
        }
        update_post_meta( $new_id, 'pc_gallery', $clean );
    }

    wp_send_json_success( [ 'message' => __( 'Ogłoszenie utworzone', 'premium-classifieds' ), 'listing_id' => $new_id ] );
} );

/**
 * Delete listing
 */
add_action( 'wp_ajax_pc_delete_listing', function() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pc_frontend_nonce' ) ) {
        pc_ajax_json_error( __( 'Invalid nonce', 'premium-classifieds' ), 403 );
    }
    if ( ! is_user_logged_in() ) {
        pc_ajax_json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
    }
    $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
    if ( $listing_id <= 0 ) {
        pc_ajax_json_error( __( 'Invalid listing id', 'premium-classifieds' ) );
    }
    $post = get_post( $listing_id );
    if ( ! $post || $post->post_type !== Listing::POST_TYPE ) {
        pc_ajax_json_error( __( 'Listing not found', 'premium-classifieds' ) );
    }
    $user_id = get_current_user_id();
    if ( (int) $post->post_author !== (int) $user_id && ! current_user_can( 'delete_others_posts' ) ) {
        pc_ajax_json_error( __( 'Brak uprawnień', 'premium-classifieds' ), 403 );
    }
    $res = Listing::delete( $listing_id );
    if ( is_wp_error( $res ) ) pc_ajax_json_error( $res->get_error_message() );
    wp_send_json_success( [ 'message' => __( 'Ogłoszenie usunięte', 'premium-classifieds' ) ] );
} );

/**
 * Upload image (simple handler)
 */
add_action( 'wp_ajax_pc_upload_image', function() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pc_frontend_nonce' ) ) {
        pc_ajax_json_error( __( 'Invalid nonce', 'premium-classifieds' ), 403 );
    }
    if ( ! is_user_logged_in() ) {
        pc_ajax_json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
    }
    if ( empty( $_FILES['file'] ) ) {
        pc_ajax_json_error( __( 'No file uploaded', 'premium-classifieds' ) );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $file = $_FILES['file'];
    $overrides = [ 'test_form' => false, 'mimes' => null ];
    $move = wp_handle_upload( $file, $overrides );
    if ( isset( $move['error'] ) ) {
        pc_ajax_json_error( $move['error'] );
    }

    $file_path = $move['file'];
    $filetype = wp_check_filetype( $file_path, null );
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title' => sanitize_file_name( basename( $file_path ) ),
        'post_content' => '',
        'post_status' => 'inherit',
    ];
    $attach_id = wp_insert_attachment( $attachment, $file_path );
    if ( ! $attach_id ) {
        pc_ajax_json_error( __( 'Failed to create attachment', 'premium-classifieds' ) );
    }
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
    wp_update_attachment_metadata( $attach_id, $attach_data );

    wp_send_json_success( [ 'attachment_id' => $attach_id, 'url' => wp_get_attachment_url( $attach_id ) ] );
} );

/**
 * Toggle favorite
 */
add_action( 'wp_ajax_pc_toggle_favorite', function() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pc_frontend_nonce' ) ) {
        pc_ajax_json_error( __( 'Invalid nonce', 'premium-classifieds' ), 403 );
    }
    if ( ! is_user_logged_in() ) pc_ajax_json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );

    $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
    if ( $listing_id <= 0 ) pc_ajax_json_error( __( 'Invalid id', 'premium-classifieds' ) );
    $user_id = get_current_user_id();
    $res = Favorite::toggle( $user_id, $listing_id );
    if ( is_wp_error( $res ) ) pc_ajax_json_error( $res->get_error_message() );

    $state = $res ? 'added' : 'removed';
    wp_send_json_success( [ 'state' => $state ] );
} );

/**
 * Send message
 */
add_action( 'wp_ajax_pc_send_message', function() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pc_frontend_nonce' ) ) {
        pc_ajax_json_error( __( 'Invalid nonce', 'premium-classifieds' ), 403 );
    }
    if ( ! is_user_logged_in() ) pc_ajax_json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );

    $from = get_current_user_id();
    $to = isset( $_POST['to_user'] ) ? intval( $_POST['to_user'] ) : 0;
    $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
    $body = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

    if ( $to <= 0 || empty( $body ) ) pc_ajax_json_error( __( 'Invalid params', 'premium-classifieds' ) );

    $id = Message::create ? Message::create([
        'from_user' => $from,
        'to_user' => $to,
        'listing_id' => $listing_id,
        'body' => $body,
    ]) : null;

    // fallback if Message class uses OOP save method
    if ( is_null( $id ) ) {
        // try constructing object
        $m = new \PremiumClassifieds\Entities\Message( [
            'sender_id' => $from,
            'receiver_id' => $to,
            'listing_id' => $listing_id,
            'content' => $body,
        ] );
        $m_id = $m->save();
        if ( $m_id ) {
            wp_send_json_success( [ 'message' => __( 'Wysłano wiadomość', 'premium-classifieds' ), 'id' => $m_id ] );
        }
        pc_ajax_json_error( __( 'Failed to send message', 'premium-classifieds' ) );
    }

    if ( is_wp_error( $id ) ) {
        pc_ajax_json_error( $id->get_error_message() );
    }

    wp_send_json_success( [ 'message' => __( 'Wysłano wiadomość', 'premium-classifieds' ), 'id' => $id ] );
} );
