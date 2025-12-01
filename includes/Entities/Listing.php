<?php
namespace Premium\Classifieds\Entities;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;

/**
 * Class Listing
 * Model/Service dla ogłoszeń (CPT: pc_listing)
 *
 * Uwaga:
 * - Dane kontaktowe (email/phone) są przechowywane w postmeta, ale nie ujawniane bez sprawdzenia płatności.
 * - Autentykację płatności sprawdzamy w tabeli wp_pc_transactions (PaymentController zapisuje tam rekordy).
 */
class Listing {

    /**
     * Post type const
     */
    public const POST_TYPE = 'pc_listing';

    /**
     * Meta keys
     */
    public const META_PHONE = 'pc_contact_phone';
    public const META_EMAIL = 'pc_contact_email';
    public const META_FEATURED_UNTIL = 'pc_featured_until';

    /**
     * Create a new listing (sanitized)
     *
     * @param array $data
     * @param int $author_id
     * @return int|\WP_Error
     */
    public static function create( array $data, int $author_id ) {
        if ( empty( $author_id ) || ! get_userdata( $author_id ) ) {
            return new \WP_Error( 'invalid_author', __( 'Invalid author', 'premium-classifieds' ) );
        }

        // sanitize and prepare
        $title = isset( $data['title'] ) ? Helpers::sanitize_text( $data['title'] ) : '';
        $content = isset( $data['content'] ) ? Helpers::sanitize_html( $data['content'] ) : '';
        $excerpt = isset( $data['excerpt'] ) ? Helpers::sanitize_text( $data['excerpt'] ) : '';
        $status = ( Helpers::get_option( 'pc_auto_approve', 'yes' ) === 'yes' ) ? 'publish' : 'pending';

        $postarr = [
            'post_type'    => self::POST_TYPE,
            'post_title'   => $title ?: wp_trim_words( $content, 6, '...' ),
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $status,
            'post_author'  => $author_id,
        ];

        $post_id = wp_insert_post( $postarr, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Save safe meta (contact details intentionally saved but not exposed)
        if ( isset( $data['contact_phone'] ) ) {
            $phone = Helpers::sanitize_text( $data['contact_phone'] );
            update_post_meta( $post_id, self::META_PHONE, $phone );
        }
        if ( isset( $data['contact_email'] ) ) {
            $email = Helpers::sanitize_email( $data['contact_email'] );
            update_post_meta( $post_id, self::META_EMAIL, $email );
        }

        // Categories (if provided)
        if ( ! empty( $data['categories'] ) && is_array( $data['categories'] ) ) {
            $cats = array_map( function ( $c ) {
                return Helpers::sanitize_text( $c );
            }, $data['categories'] );
            wp_set_object_terms( $post_id, $cats, 'pc_listing_category', false );
        }

        // Featured until (optional)
        if ( ! empty( $data['featured_days'] ) ) {
            $days = Helpers::sanitize_int( $data['featured_days'] );
            $until = date( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );
            update_post_meta( $post_id, self::META_FEATURED_UNTIL, $until );
        }

        return (int) $post_id;
    }

    /**
     * Update existing listing
     *
     * @param int $id
     * @param array $data
     * @return int|\WP_Error
     */
    public static function update( int $id, array $data ) {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return new \WP_Error( 'invalid_listing', __( 'Listing not found', 'premium-classifieds' ) );
        }

        // Capability: owner or manage_options
        $user_id = get_current_user_id();
        if ( ! self::can_user_edit( $id, $user_id ) ) {
            return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this listing', 'premium-classifieds' ) );
        }

        $postarr = [
            'ID' => $id,
        ];
        if ( isset( $data['title'] ) ) {
            $postarr['post_title'] = Helpers::sanitize_text( $data['title'] );
        }
        if ( isset( $data['content'] ) ) {
            $postarr['post_content'] = Helpers::sanitize_html( $data['content'] );
        }
        if ( isset( $data['excerpt'] ) ) {
            $postarr['post_excerpt'] = Helpers::sanitize_text( $data['excerpt'] );
        }

        $updated = wp_update_post( $postarr, true );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        // Meta updates
        if ( isset( $data['contact_phone'] ) ) {
            $phone = Helpers::sanitize_text( $data['contact_phone'] );
            update_post_meta( $id, self::META_PHONE, $phone );
        }
        if ( isset( $data['contact_email'] ) ) {
            $email = Helpers::sanitize_email( $data['contact_email'] );
            update_post_meta( $id, self::META_EMAIL, $email );
        }

        if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
            $cats = array_map( function ( $c ) {
                return Helpers::sanitize_text( $c );
            }, $data['categories'] );
            wp_set_object_terms( $id, $cats, 'pc_listing_category', false );
        }

        return (int) $id;
    }

    /**
     * Delete listing (soft delete via wp_delete_post)
     *
     * @param int $id
     * @return bool|\WP_Error
     */
    public static function delete( int $id ) {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return new \WP_Error( 'invalid_listing', __( 'Listing not found', 'premium-classifieds' ) );
        }

        $user_id = get_current_user_id();
        if ( ! self::can_user_edit( $id, $user_id ) ) {
            return new \WP_Error( 'forbidden', __( 'You do not have permission to delete this listing', 'premium-classifieds' ) );
        }

        $result = wp_delete_post( $id, true ); // force delete
        return ( $result !== false );
    }

    /**
     * Get listing data safe for frontend (without revealing contact)
     *
     * @param int $id
     * @return array|null
     */
    public static function get( int $id ): ?array {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return null;
        }

        $data = [
            'ID' => (int) $post->ID,
            'title' => Helpers::esc_html( $post->post_title ),
            'content' => Helpers::sanitize_html( $post->post_content ),
            'excerpt' => Helpers::esc_html( $post->post_excerpt ),
            'author' => (int) $post->post_author,
            'status' => $post->post_status,
            'featured_until' => get_post_meta( $post->ID, self::META_FEATURED_UNTIL, true ) ?: null,
            'thumbnail' => get_the_post_thumbnail_url( $post, 'medium' ),
            'permalink' => get_permalink( $post ),
        ];

        return $data;
    }

    /**
     * Return contact details if viewer has paid or is owner.
     *
     * @param int $listing_id
     * @param int|null $viewer_id
     * @return array|false ['email'=>..., 'phone'=>...] or false
     */
    public static function get_contact_if_paid( int $listing_id, ?int $viewer_id ) {
        $post = get_post( $listing_id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return false;
        }

        // Owner always sees contact
        if ( $viewer_id && (int) $post->post_author === (int) $viewer_id ) {
            return [
                'email' => Helpers::sanitize_email( get_post_meta( $listing_id, self::META_EMAIL, true ) ),
                'phone' => Helpers::sanitize_text( get_post_meta( $listing_id, self::META_PHONE, true ) ),
                'granted_via' => 'owner',
            ];
        }

        if ( empty( $viewer_id ) || ! get_userdata( $viewer_id ) ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pc_transactions';
        // Look for a successful transaction for this user + listing
        $sql = $wpdb->prepare(
            "SELECT id, provider, provider_id, status, amount, created_at FROM {$table} WHERE user_id = %d AND listing_id = %d AND status = %s LIMIT 1",
            $viewer_id,
            $listing_id,
            'succeeded'
        );
        $row = $wpdb->get_row( $sql, ARRAY_A );

        if ( $row ) {
            // Optionally mark that user has access (audit)
            self::mark_contact_revealed( $listing_id, $viewer_id );

            return [
                'email' => Helpers::sanitize_email( get_post_meta( $listing_id, self::META_EMAIL, true ) ),
                'phone' => Helpers::sanitize_text( get_post_meta( $listing_id, self::META_PHONE, true ) ),
                'granted_via' => 'payment',
                'transaction_id' => $row['id'],
                'provider' => $row['provider'],
            ];
        }

        // Also check user meta-based grants (if any)
        $access = get_user_meta( $viewer_id, 'pc_contact_access', true );
        if ( is_array( $access ) && in_array( $listing_id, $access, true ) ) {
            return [
                'email' => Helpers::sanitize_email( get_post_meta( $listing_id, self::META_EMAIL, true ) ),
                'phone' => Helpers::sanitize_text( get_post_meta( $listing_id, self::META_PHONE, true ) ),
                'granted_via' => 'manual',
            ];
        }

        return false;
    }

    /**
     * Mark that a user has been granted access to contact (audit + quick check)
     *
     * @param int $listing_id
     * @param int $viewer_id
     * @return bool
     */
    public static function mark_contact_revealed( int $listing_id, int $viewer_id ): bool {
        if ( empty( $viewer_id ) || empty( $listing_id ) ) {
            return false;
        }
        $access = get_user_meta( $viewer_id, 'pc_contact_access', true );
        if ( ! is_array( $access ) ) {
            $access = [];
        }
        if ( ! in_array( $listing_id, $access, true ) ) {
            $access[] = (int) $listing_id;
            update_user_meta( $viewer_id, 'pc_contact_access', $access );
        }

        // Optional: write an audit log entry (WP_DEBUG)
        Helpers::log( sprintf( 'Contact revealed: listing=%d to_user=%d', $listing_id, $viewer_id ) );

        return true;
    }

    /**
     * Check if user can edit listing (owner or manage_options)
     *
     * @param int $listing_id
     * @param int|null $user_id
     * @return bool
     */
    public static function can_user_edit( int $listing_id, ?int $user_id = null ): bool {
        if ( empty( $user_id ) ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return false;
        }
        $post = get_post( $listing_id );
        if ( ! $post ) {
            return false;
        }
        if ( (int) $post->post_author === (int) $user_id ) {
            return true;
        }
        return user_can( $user_id, 'manage_options' );
    }
}
