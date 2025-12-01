<?php
// File: includes/Core/Helpers.php
namespace Premium\Classifieds\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Helpers
 * Utility static methods: nonce, sanitize, escape, upload, auth checks, ajax responses.
 */
class Helpers {

    /**
     * Generate nonce field
     *
     * @param string $action
     * @param string $name
     */
    public static function nonce_field( string $action = 'pc_action', string $name = '_pc_nonce' ): void {
        echo wp_nonce_field( $action, $name, true, false );
    }

    /**
     * Verify nonce (returns bool)
     *
     * @param string|null $nonce
     * @param string $action
     * @return bool
     */
    public static function verify_nonce( ?string $nonce, string $action = 'pc_action' ): bool {
        if ( empty( $nonce ) ) {
            return false;
        }
        return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), $action );
    }

    /**
     * Sanitize text field
     *
     * @param mixed $value
     * @return string
     */
    public static function sanitize_text( $value ): string {
        return sanitize_text_field( wp_strip_all_tags( (string) $value ) );
    }

    /**
     * Sanitize HTML (allow limited tags)
     *
     * @param string $html
     * @return string
     */
    public static function sanitize_html( string $html ): string {
        $allowed = wp_kses_allowed_html( 'post' );
        return wp_kses( $html, $allowed );
    }

    /**
     * Sanitize email
     *
     * @param string $email
     * @return string
     */
    public static function sanitize_email( string $email ): string {
        return sanitize_email( $email );
    }

    /**
     * Sanitize integer
     *
     * @param mixed $val
     * @return int
     */
    public static function sanitize_int( $val ): int {
        return intval( filter_var( $val, FILTER_SANITIZE_NUMBER_INT ) );
    }

    /**
     * Sanitize float (decimal)
     *
     * @param mixed $val
     * @return float
     */
    public static function sanitize_float( $val ): float {
        return floatval( filter_var( $val, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ) );
    }

    /**
     * Escape HTML for output
     *
     * @param string $text
     * @return string
     */
    public static function esc_html( string $text ): string {
        return esc_html( $text );
    }

    /**
     * Escape attribute
     *
     * @param string $text
     * @return string
     */
    public static function esc_attr( string $text ): string {
        return esc_attr( $text );
    }

    /**
     * Escape URL
     *
     * @param string $url
     * @return string
     */
    public static function esc_url( string $url ): string {
        return esc_url_raw( $url );
    }

    /**
     * Require capability or die with JSON error (for AJAX)
     *
     * @param string $cap
     * @param string $message
     * @return void
     */
    public static function require_cap( string $cap, string $message = 'Permission denied' ): void {
        if ( ! current_user_can( $cap ) ) {
            wp_send_json_error( [ 'message' => $message ], 403 );
            wp_die();
        }
    }

    /**
     * Safe JSON success response for AJAX
     *
     * @param mixed $data
     * @param int $code
     * @return void
     */
    public static function json_success( $data = [], int $code = 200 ): void {
        wp_send_json_success( $data, $code );
    }

    /**
     * Safe JSON error response for AJAX
     *
     * @param array|string $data
     * @param int $code
     * @return void
     */
    public static function json_error( $data = [], int $code = 400 ): void {
        wp_send_json_error( is_string( $data ) ? [ 'message' => $data ] : $data, $code );
    }

    /**
     * Allowed mime types for listing images (merges with WP mime types)
     *
     * @return array
     */
    public static function allowed_image_mimes(): array {
        $defaults = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        $wp_mimes = wp_get_mime_types();
        // Merge but keep our prioritized types
        return array_merge( $wp_mimes, $defaults );
    }

    /**
     * Handle single file upload (returns array with url, file, type) or WP_Error
     *
     * @param array $file_array $_FILES['field']
     * @param int|null $user_id
     * @param string $context for error messages
     * @param int|null $max_bytes optional max file size in bytes
     * @return array|\WP_Error
     */
    public static function handle_file_upload( array $file_array, ?int $user_id = null, string $context = 'pc_upload', ?int $max_bytes = null ) {
        if ( empty( $file_array ) || empty( $file_array['tmp_name'] ) ) {
            return new \WP_Error( 'no_file', __( 'No file provided', 'premium-classifieds' ) );
        }

        // Basic capability check
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return new \WP_Error( 'not_logged_in', __( 'User not logged in', 'premium-classifieds' ) );
        }

        // Check file size
        if ( $max_bytes && isset( $file_array['size'] ) && (int) $file_array['size'] > $max_bytes ) {
            return new \WP_Error( 'file_too_large', __( 'File exceeds maximum allowed size', 'premium-classifieds' ) );
        }

        // Validate mime type
        $filetype = wp_check_filetype_and_ext( $file_array['tmp_name'], $file_array['name'] );
        $allowed = self::allowed_image_mimes();
        if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed, true ) ) {
            return new \WP_Error( 'invalid_mime', __( 'Invalid file type', 'premium-classifieds' ) );
        }

        // Use WP's handler
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $overrides = [
            'test_form' => false,
            'mimes'     => $allowed,
        ];

        $movefile = wp_handle_upload( $file_array, $overrides );

        if ( isset( $movefile['error'] ) ) {
            return new \WP_Error( 'upload_error', $movefile['error'] );
        }

        // Optionally create attachment and attach to user or listing (caller responsibility)
        return $movefile;
    }

    /**
     * Safe option getter with default
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get_option( string $key, $default = null ) {
        $value = get_option( $key, $default );
        return $value;
    }

    /**
     * Safe logger for debugging
     *
     * @param string $message
     */
    public static function log( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[premium-classifieds] ' . $message );
        }
    }
}
