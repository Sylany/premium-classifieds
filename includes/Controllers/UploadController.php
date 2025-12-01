<?php
// File: includes/Controllers/UploadController.php
namespace Premium\Classifieds\Controllers;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;

/**
 * UploadController
 * Handles AJAX uploads for images (drag & drop).
 */
class UploadController {

    public function __construct() {
        add_action( 'wp_ajax_pc_upload_image', [ $this, 'ajax_upload_image' ] );
    }

    /**
     * Receive file in $_FILES['file']
     * Optional POST: listing_id to attach media to a listing
     */
    public function ajax_upload_image() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'Zaloguj się', 'premium-classifieds' ) ], 403 );
        }

        if ( empty( $_FILES['file'] ) ) {
            return Helpers::json_error( [ 'message' => __( 'No file', 'premium-classifieds' ) ], 400 );
        }

        $file = $_FILES['file'];
        $move = Helpers::handle_file_upload( $file, get_current_user_id(), 'listing_upload' );
        if ( is_wp_error( $move ) ) {
            return Helpers::json_error( [ 'message' => $move->get_error_message() ], 500 );
        }

        // Insert attachment
        $filetype = wp_check_filetype( $move['file'], null );
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name( $move['file'] ),
            'post_content' => '',
            'post_status' => 'inherit',
        ];
        $attach_id = wp_insert_attachment( $attachment, $move['file'] );
        if ( is_wp_error( $attach_id ) || ! $attach_id ) {
            return Helpers::json_error( [ 'message' => __( 'Failed to create attachment', 'premium-classifieds' ) ], 500 );
        }

        // Require image.php functions
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $meta = wp_generate_attachment_metadata( $attach_id, $move['file'] );
        wp_update_attachment_metadata( $attach_id, $meta );

        // Optionally attach to listing
        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
        if ( $listing_id > 0 ) {
            // add to gallery meta array
            $gallery = get_post_meta( $listing_id, 'pc_gallery', true );
            if ( ! is_array( $gallery ) ) {
                $gallery = [];
            }
            $gallery[] = $attach_id;
            update_post_meta( $listing_id, 'pc_gallery', $gallery );
        }

        $url = wp_get_attachment_url( $attach_id );
        return Helpers::json_success( [ 'attachment_id' => $attach_id, 'url' => $url ] );
    }
}
