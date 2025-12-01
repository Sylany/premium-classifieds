<?php
namespace Premium\Classifieds\Controllers;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Listing;
use Premium\Classifieds\Entities\Message;
use Premium\Classifieds\Entities\Transaction;

/**
 * Class DashboardController
 * Renders frontend dashboard (shortcode) and handles AJAX endpoints for user actions.
 */
class DashboardController {

    /**
     * Constructor — register AJAX hooks
     */
    public function __construct() {
        // Frontend AJAX (logged in)
        add_action( 'wp_ajax_pc_create_listing', [ $this, 'ajax_create_listing' ] );
        add_action( 'wp_ajax_pc_update_listing', [ $this, 'ajax_update_listing' ] );
        add_action( 'wp_ajax_pc_delete_listing', [ $this, 'ajax_delete_listing' ] );
        add_action( 'wp_ajax_pc_upload_image', [ $this, 'ajax_upload_image' ] );

        add_action( 'wp_ajax_pc_get_my_listings', [ $this, 'ajax_get_my_listings' ] );

        add_action( 'wp_ajax_pc_send_message', [ $this, 'ajax_send_message' ] );
        add_action( 'wp_ajax_pc_get_messages', [ $this, 'ajax_get_messages' ] );

        add_action( 'wp_ajax_pc_toggle_favorite', [ $this, 'ajax_toggle_favorite' ] );
        add_action( 'wp_ajax_pc_get_favorites', [ $this, 'ajax_get_favorites' ] );

        add_action( 'wp_ajax_pc_get_transactions', [ $this, 'ajax_get_transactions' ] );
    }

    /**
     * Render dashboard shortcode wrapper
     *
     * @param array $atts
     * @return string
     */
    public function render_dashboard( $atts = [] ) : string {
        if ( ! is_user_logged_in() ) {
            return '<div class="pc-dashboard pc-not-logged">' . esc_html__( 'Please log in to access the dashboard.', 'premium-classifieds' ) . '</div>';
        }

        // Ensure assets are enqueued by main plugin
        ob_start();
        $view = __DIR__ . '/../Views/frontend/dashboard.php';
        if ( file_exists( $view ) ) {
            require $view;
        } else {
            echo '<div class="pc-dashboard">Dashboard view not found.</div>';
        }
        return (string) ob_get_clean();
    }

    /**
     * AJAX: Create listing
     */
    public function ajax_create_listing() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( __( 'Invalid nonce', 'premium-classifieds' ), 400 );
        }

        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
        }

        $current_user = get_current_user_id();

        // Collect and sanitize data
        $data = [
            'title' => isset( $_POST['title'] ) ? Helpers::sanitize_text( wp_unslash( $_POST['title'] ) ) : '',
            'content' => isset( $_POST['content'] ) ? Helpers::sanitize_html( wp_unslash( $_POST['content'] ) ) : '',
            'excerpt' => isset( $_POST['excerpt'] ) ? Helpers::sanitize_text( wp_unslash( $_POST['excerpt'] ) ) : '',
            'contact_phone' => isset( $_POST['contact_phone'] ) ? Helpers::sanitize_text( wp_unslash( $_POST['contact_phone'] ) ) : '',
            'contact_email' => isset( $_POST['contact_email'] ) ? Helpers::sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '',
            'categories' => isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['categories'] ) ) : [],
            'featured_days' => isset( $_POST['featured_days'] ) ? intval( $_POST['featured_days'] ) : 0,
        ];

        $result = Listing::create( $data, $current_user );

        if ( is_wp_error( $result ) ) {
            return Helpers::json_error( [ 'message' => $result->get_error_message() ], 400 );
        }

        return Helpers::json_success( [ 'message' => __( 'Listing created', 'premium-classifieds' ), 'listing_id' => $result ] );
    }

    /**
     * AJAX: Update listing
     */
    public function ajax_update_listing() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( __( 'Invalid nonce', 'premium-classifieds' ), 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
        }

        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
        if ( $listing_id <= 0 ) {
            return Helpers::json_error( __( 'Invalid listing id', 'premium-classifieds' ), 400 );
        }

        $data = [
            'title' => isset( $_POST['title'] ) ? Helpers::sanitize_text( wp_unslash( $_POST['title'] ) ) : null,
            'content' => isset( $_POST['content'] ) ? Helpers::sanitize_html( wp_unslash( $_POST['content'] ) ) : null,
            'excerpt' => isset( $_POST['excerpt'] ) ? Helpers::sanitize_text( wp_unslash( $_POST['excerpt'] ) ) : null,
            'contact_phone' => isset( $_POST['contact_phone'] ) ? Helpers::sanitize_text( wp_unslash( $_POST['contact_phone'] ) ) : null,
            'contact_email' => isset( $_POST['contact_email'] ) ? Helpers::sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : null,
            'categories' => isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['categories'] ) ) : null,
            'featured_days' => isset( $_POST['featured_days'] ) ? intval( $_POST['featured_days'] ) : null,
        ];

        $result = Listing::update( $listing_id, $data );

        if ( is_wp_error( $result ) ) {
            return Helpers::json_error( [ 'message' => $result->get_error_message() ], 400 );
        }

        return Helpers::json_success( [ 'message' => __( 'Listing updated', 'premium-classifieds' ), 'listing_id' => $listing_id ] );
    }

    /**
     * AJAX: Delete listing
     */
    public function ajax_delete_listing() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( __( 'Invalid nonce', 'premium-classifieds' ), 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
        }

        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
        if ( $listing_id <= 0 ) {
            return Helpers::json_error( __( 'Invalid listing id', 'premium-classifieds' ), 400 );
        }

        $result = Listing::delete( $listing_id );
        if ( is_wp_error( $result ) ) {
            return Helpers::json_error( [ 'message' => $result->get_error_message() ], 400 );
        }

        return Helpers::json_success( [ 'message' => __( 'Listing deleted', 'premium-classifieds' ) ] );
    }

    /**
     * AJAX: Upload image (drag & drop)
     */
    public function ajax_upload_image() {
        $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( __( 'Invalid nonce', 'premium-classifieds' ), 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
        }

        if ( empty( $_FILES ) ) {
            return Helpers::json_error( __( 'No file uploaded', 'premium-classifieds' ), 400 );
        }

        // Expect single file under 'file'
        $file_field = reset( $_FILES );
        $upload = Helpers::handle_file_upload( $file_field, get_current_user_id(), 'listing_image', null );

        if ( is_wp_error( $upload ) ) {
            return Helpers::json_error( [ 'message' => $upload->get_error_message() ], 400 );
        }

        // Optionally insert attachment
        $file_path = $upload['file'];
        $file_name = basename( $file_path );
        $file_type = wp_check_filetype( $file_name, null );

        $attachment = [
            'guid' => $upload['url'],
            'post_mime_type' => $file_type['type'] ?? '',
            'post_title' => preg_replace( '/\.[^.]+$/', '', $file_name ),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attachment_id = wp_insert_attachment( $attachment, $file_path );
        if ( ! is_wp_error( $attachment_id ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
            wp_update_attachment_metadata( $attachment_id, $attach_data );
        }

        return Helpers::json_success( [ 'message' => __( 'Uploaded', 'premium-classifieds' ), 'url' => $upload['url'], 'attachment_id' => $attachment_id ?? 0 ] );
    }

    /**
     * AJAX: Get current user's listings (simple)
     */
    public function ajax_get_my_listings() {
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( __( 'Invalid nonce', 'premium-classifieds' ), 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
        }

        $user_id = get_current_user_id();

        $args = [
            'post_type' => Listing::POST_TYPE,
            'author' => $user_id,
            'posts_per_page' => 50,
            'post_status' => [ 'publish', 'pending' ],
        ];
        $q = new \WP_Query( $args );
        $out = [];
        if ( $q->have_posts() ) {
            foreach ( $q->posts as $p ) {
                $out[] = Listing::get( (int) $p->ID );
            }
        }
        wp_reset_postdata();

        return Helpers::json_success( [ 'listings' => $out ] );
    }

    /**
     * AJAX: Send message
     */
    public function ajax_send_message() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( __( 'Invalid nonce', 'premium-classifieds' ), 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
        }

        $from_user = get_current_user_id();
        $to_user = isset( $_POST['to_user'] ) ? intval( $_POST['to_user'] ) : 0;
        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : null;
        $body = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

        $res = Message::create( [
            'from_user' => $from_user,
            'to_user' => $to_user,
            'listing_id' => $listing_id,
            'body' => $body,
        ] );

        if ( is_wp_error( $res ) ) {
            return Helpers::json_error( [ 'message' => $res->get_error_message() ], 400 );
        }

        return Helpers::json_success( [ 'message' => __( 'Message sent', 'premium-classifieds' ), 'id' => $res ] );
    }

    /**
     * AJAX: Get messages for current user (inbox)
     */
    public function ajax_get_messages() {
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( __( 'Invalid nonce', 'premium-classifieds' ), 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
        }

        $user_id = get_current_user_id();
        $type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : 'inbox';
        $page = isset( $_GET['page'] ) ? max( 1, intval( $_GET['page'] ) ) : 1;
        $per = 20;
        $offset = ( $page - 1 ) * $per;

        $msgs = Message::list_for_user( $user_id, $type, $per, $offset );

        return Helpers::json_success( [ 'messages' => $msgs ] );
    }

    /**
     * AJAX: Toggle favorite listing (add/remove)
     */
    public function ajax_toggle_favorite() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( __( 'Invalid nonce', 'premium-classifieds' ), 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
        }

        $user_id = get_current_user_id();
        $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
        if ( $listing_id <= 0 ) {
            return Helpers::json_error( __( 'Invalid listing id', 'premium-classifieds' ), 400 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pc_favorites';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d AND listing_id = %d LIMIT 1", $user_id, $listing_id ) );

        if ( $exists ) {
            $deleted = $wpdb->delete( $table, [ 'id' => $exists ], [ '%d' ] );
            if ( false === $deleted ) {
                Helpers::log( 'Failed to remove favorite: ' . $wpdb->last_error );
                return Helpers::json_error( __( 'Failed to remove favorite', 'premium-classifieds' ), 500 );
            }
            return Helpers::json_success( [ 'message' => __( 'Removed from favorites', 'premium-classifieds' ) ] );
        }

        $inserted = $wpdb->insert( $table, [ 'user_id' => $user_id, 'listing_id' => $listing_id, 'created_at' => current_time( 'mysql', 1 ) ], [ '%d', '%d', '%s' ] );
        if ( false === $inserted ) {
            Helpers::log( 'Failed to add favorite: ' . $wpdb->last_error );
            return Helpers::json_error( __( 'Failed to add favorite', 'premium-classifieds' ), 500 );
        }
        return Helpers::json_success( [ 'message' => __( 'Added to favorites', 'premium-classifieds' ) ] );
    }

    /**
     * AJAX: Get favorites
     */
    public function ajax_get_favorites() {
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( __( 'Invalid nonce', 'premium-classifieds' ), 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $table = $wpdb->prefix . 'pc_favorites';
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT listing_id, created_at FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", $user_id ), ARRAY_A );

        $out = [];
        if ( $rows ) {
            foreach ( $rows as $r ) {
                $out[] = Listing::get( (int) $r['listing_id'] );
            }
        }

        return Helpers::json_success( [ 'favorites' => $out ] );
    }

    /**
     * AJAX: Get transactions (history) for current user
     */
    public function ajax_get_transactions() {
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
        if ( ! Helpers::verify_nonce( $nonce, 'pc_frontend_nonce' ) ) {
            return Helpers::json_error( __( 'Invalid nonce', 'premium-classifieds' ), 400 );
        }
        if ( ! is_user_logged_in() ) {
            return Helpers::json_error( __( 'Not logged in', 'premium-classifieds' ), 403 );
        }

        $user_id = get_current_user_id();
        $page = isset( $_GET['page'] ) ? max( 1, intval( $_GET['page'] ) ) : 1;
        $per = 20;
        $offset = ( $page - 1 ) * $per;

        $txs = Transaction::list_for_user( $user_id, $per, $offset );

        return Helpers::json_success( [ 'transactions' => $txs ] );
    }
}
