<?php
// File: includes/Controllers/FavoriteController.php
namespace Premium\Classifieds\Controllers;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Favorite;

/**
 * FavoriteController
 * Toggle/add/remove favorites via AJAX
 */
class FavoriteController {

    public function __construct() {
        add_action( 'wp_ajax_pc_toggle_favorite', [ $this, 'ajax_toggle_favorite' ] );
        add_action( 'wp_ajax_pc_add_favorite', [ $this, 'ajax_add_favorite' ] );
        add_action( 'wp_ajax_pc_remove_favorite', [ $this, 'ajax_remove_favorite' ] );
    }

    /**
     * Toggle favorite
     * POST: nonce, listing_id
     */
    public function ajax_toggle_favorite() {
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

        $user_id = get_current_user_id();
        $res = Favorite::toggle( $user_id, $listing_id );
        if ( is_wp_error( $res ) ) {
            return Helpers::json_error( [ 'message' => $res->get_error_message() ], 500 );
        }

        // res is bool: true added, false removed
        $state = $res ? 'added' : 'removed';
        return Helpers::json_success( [ 'message' => $state, 'state' => $state ] );
    }

    public function ajax_add_favorite() {
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
        $user_id = get_current_user_id();
        $res = Favorite::add( $user_id, $listing_id );
        if ( is_wp_error( $res ) ) {
            return Helpers::json_error( [ 'message' => $res->get_error_message() ], 500 );
        }
        return Helpers::json_success( [ 'message' => 'added', 'id' => $res ] );
    }

    public function ajax_remove_favorite() {
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
        $user_id = get_current_user_id();
        $ok = Favorite::remove( $user_id, $listing_id );
        if ( ! $ok ) {
            return Helpers::json_error( [ 'message' => __( 'Nie udało się usunąć', 'premium-classifieds' ) ], 500 );
        }
        return Helpers::json_success( [ 'message' => 'removed' ] );
    }
}
