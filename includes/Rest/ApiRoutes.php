<?php
// File: includes/Rest/ApiRoutes.php
namespace Premium\Classifieds\Rest;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Listing;
use Premium\Classifieds\Entities\Transaction;

/**
 * Class ApiRoutes
 * Registers REST API routes for public consumption.
 */
class ApiRoutes {

    public static function register(): void {
        // Listings collection (public)
        register_rest_route( 'pc/v1', '/listings', [
            'methods' => 'GET',
            'callback' => [ __CLASS__, 'get_listings' ],
            'permission_callback' => '__return_true',
        ] );

        // Single listing
        register_rest_route( 'pc/v1', '/listing/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [ __CLASS__, 'get_listing' ],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [ 'validate_callback' => 'is_numeric' ],
            ],
        ] );

        // Reveal contact via REST (POST) - requires auth and payment check
        register_rest_route( 'pc/v1', '/listing/(?P<id>\d+)/reveal', [
            'methods' => 'POST',
            'callback' => [ __CLASS__, 'reveal_contact' ],
            'permission_callback' => [ __CLASS__, 'reveal_permission_callback' ],
            'args' => [
                'id' => [ 'validate_callback' => 'is_numeric' ],
            ],
        ] );
    }

    /**
     * GET /pc/v1/listings
     * Query params: per_page, page, category, search
     */
    public static function get_listings( WP_REST_Request $request ): WP_REST_Response {
        $per_page = max( 1, intval( $request->get_param( 'per_page' ) ?? 12 ) );
        $page = max( 1, intval( $request->get_param( 'page' ) ?? 1 ) );
        $category = $request->get_param( 'category' );
        $search = $request->get_param( 'search' );

        $args = [
            'post_type' => Listing::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
        ];
        if ( ! empty( $category ) ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'pc_listing_category',
                    'field' => 'slug',
                    'terms' => sanitize_text_field( $category ),
                ],
            ];
        }
        if ( ! empty( $search ) ) {
            $args['s'] = sanitize_text_field( $search );
        }

        $q = new \WP_Query( $args );
        $out = [];
        if ( $q->have_posts() ) {
            foreach ( $q->posts as $p ) {
                $out[] = Listing::get( (int) $p->ID );
            }
        }
        wp_reset_postdata();

        return rest_ensure_response( [
            'page' => $page,
            'per_page' => $per_page,
            'total' => (int) $q->found_posts,
            'listings' => $out,
        ] );
    }

    /**
     * GET /pc/v1/listing/{id}
     */
    public static function get_listing( WP_REST_Request $request ): WP_REST_Response {
        $id = intval( $request->get_param( 'id' ) );
        $data = Listing::get( $id );
        if ( ! $data ) {
            return new WP_REST_Response( [ 'error' => __( 'Listing not found', 'premium-classifieds' ) ], 404 );
        }

        // Also include whether current user can see contact (convenience)
        $viewer = get_current_user_id() ?: null;
        $contact = Listing::get_contact_if_paid( $id, $viewer );
        $data['contact_available'] = $contact ? true : false;

        return rest_ensure_response( $data );
    }

    /**
     * POST /pc/v1/listing/{id}/reveal
     * Creates PaymentIntent via PaymentController on client side; this route simply checks current status or triggers server-side purchase record (optionally).
     *
     * This endpoint is protected: require user to be logged in.
     */
    public static function reveal_permission_callback(): bool {
        return is_user_logged_in();
    }

    /**
     * Reveal contact - returns contact if allowed or instructs client to create payment
     * Response codes:
     *  - 200: contact returned
     *  - 402: payment required (returns price and suggested flow)
     */
    public static function reveal_contact( WP_REST_Request $request ): WP_REST_Response {
        $id = intval( $request->get_param( 'id' ) );
        $user_id = get_current_user_id();
        $listing = get_post( $id );
        if ( ! $listing || $listing->post_type !== Listing::POST_TYPE ) {
            return new WP_REST_Response( [ 'error' => __( 'Listing not found', 'premium-classifieds' ) ], 404 );
        }

        // If owner
        if ( (int) $listing->post_author === (int) $user_id ) {
            $contact = Listing::get_contact_if_paid( $id, $user_id );
            return rest_ensure_response( $contact );
        }

        $contact = Listing::get_contact_if_paid( $id, $user_id );
        if ( $contact ) {
            return rest_ensure_response( $contact );
        }

        // Not paid -> return price and hint to create PaymentIntent via AJAX
        $price = floatval( Helpers::get_option( 'pc_price_reveal_contact', 19.00 ) );
        return new WP_REST_Response( [
            'error' => 'payment_required',
            'message' => __( 'Payment required to reveal contact', 'premium-classifieds' ),
            'price' => $price,
            'currency' => Helpers::get_option( 'pc_currency', 'PLN' ),
            'create_payment_nonce' => wp_create_nonce( 'pc_frontend_nonce' ),
        ], 402 );
    }
}
