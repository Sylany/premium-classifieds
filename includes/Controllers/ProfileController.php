<?php
// File: includes/Controllers/ProfileController.php
namespace Premium\Classifieds\Controllers;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;

/**
 * Class ProfileController
 * Handles AJAX profile update endpoints (frontend).
 */
class ProfileController {

    public function __construct() {
        add_action( 'wp_ajax_pc_update_profile', [ $this, 'ajax_update_profile' ] );
    }

    /**
     * AJAX: update current user's profile
     *
     * Expected POST:
     *  - nonce
     *  - display_name
     *  - description
     *  - contact_email
     */
    public function ajax_update_profile() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( [ 'message' => __( 'Not logged in', 'premium-classifieds' ) ], 403 );
        }

        $user_id = get_current_user_id();

        $display_name = isset( $_POST['display_name'] ) ? Helpers::sanitize_text( wp_unslash( $_POST['display_name'] ) ) : '';
        $description = isset( $_POST['description'] ) ? Helpers::sanitize_html( wp_unslash( $_POST['description'] ) ) : '';
        $contact_email = isset( $_POST['contact_email'] ) ? Helpers::sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';

        $userdata = [ 'ID' => $user_id ];
        if ( $display_name !== '' ) {
            $userdata['display_name'] = $display_name;
            // Optionally set nicename / user_nicename? keep it simple
        }
        if ( $contact_email !== '' && is_email( $contact_email ) ) {
            $userdata['user_email'] = $contact_email;
        }

        // Update user
        $updated = wp_update_user( $userdata );
        if ( is_wp_error( $updated ) ) {
            return Helpers::json_error( [ 'message' => $updated->get_error_message() ], 500 );
        }

        // Update meta (description)
        update_user_meta( $user_id, 'description', $description );

        return Helpers::json_success( [ 'message' => __( 'Profile updated', 'premium-classifieds' ) ] );
    }
}
