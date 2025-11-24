<?php
/**
 * Security Utilities
 *
 * Centralized security functions for:
 * - Nonce generation/verification
 * - Input sanitization
 * - Output escaping
 * - CSRF protection
 * - SQL injection prevention
 *
 * @package    PremiumClassifieds
 * @subpackage Utils
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Helper Class
 *
 * @since 2.1.0
 */
class PC_Security {
    
    /**
     * Nonce action prefix
     *
     * @var string
     */
    private const NONCE_PREFIX = 'pc_nonce_';
    
    /**
     * Nonce lifetime in seconds (12 hours)
     *
     * @var int
     */
    private const NONCE_LIFETIME = 43200;
    
    /**
     * Create a nonce for a specific action
     *
     * @since 2.1.0
     * @param string $action Action identifier
     * @return string Nonce token
     */
    public static function create_nonce(string $action): string {
        return wp_create_nonce(self::NONCE_PREFIX . $action);
    }
    
    /**
     * Verify a nonce
     *
     * @since 2.1.0
     * @param string $nonce Nonce token to verify
     * @param string $action Action identifier
     * @return bool True if valid, false otherwise
     */
    public static function verify_nonce(string $nonce, string $action): bool {
        return (bool) wp_verify_nonce($nonce, self::NONCE_PREFIX . $action);
    }
    
    /**
     * Verify AJAX nonce and die if invalid
     *
     * @since 2.1.0
     * @param string $nonce Nonce token from AJAX request
     * @param string $action Action identifier
     * @return void Dies with error if invalid
     */
    public static function verify_ajax_nonce(string $nonce, string $action): void {
        if (!self::verify_nonce($nonce, $action)) {
            wp_send_json_error([
                'message' => __('Security check failed. Please refresh the page.', 'premium-classifieds')
            ], 403);
            wp_die();
        }
    }
    
    /**
     * Check if current user can perform action
     *
     * @since 2.1.0
     * @param string $capability Required capability
     * @param int|null $object_id Optional object ID for meta caps
     * @return bool True if user has capability
     */
    public static function current_user_can(string $capability, ?int $object_id = null): bool {
        if ($object_id) {
            return current_user_can($capability, $object_id);
        }
        return current_user_can($capability);
    }
    
    /**
     * Check user capability and die if unauthorized
     *
     * @since 2.1.0
     * @param string $capability Required capability
     * @param int|null $object_id Optional object ID
     * @return void Dies with error if unauthorized
     */
    public static function require_capability(string $capability, ?int $object_id = null): void {
        if (!self::current_user_can($capability, $object_id)) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'premium-classifieds')
            ], 403);
            wp_die();
        }
    }
    
    /**
     * Sanitize text input
     *
     * @since 2.1.0
     * @param string|array $input Raw input
     * @return string|array Sanitized input
     */
    public static function sanitize_text($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize_text'], $input);
        }
        return sanitize_text_field($input);
    }
    
    /**
     * Sanitize textarea input (preserves line breaks)
     *
     * @since 2.1.0
     * @param string $input Raw input
     * @return string Sanitized input
     */
    public static function sanitize_textarea(string $input): string {
        return sanitize_textarea_field($input);
    }
    
    /**
     * Sanitize HTML content (allows safe HTML tags)
     *
     * @since 2.1.0
     * @param string $input Raw HTML
     * @return string Sanitized HTML
     */
    public static function sanitize_html(string $input): string {
        $allowed_tags = [
            'a' => ['href' => [], 'title' => [], 'target' => []],
            'br' => [],
            'em' => [],
            'strong' => [],
            'p' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
        ];
        return wp_kses($input, $allowed_tags);
    }
    
    /**
     * Sanitize email address
     *
     * @since 2.1.0
     * @param string $email Raw email
     * @return string Sanitized email
     */
    public static function sanitize_email(string $email): string {
        return sanitize_email($email);
    }
    
    /**
     * Sanitize URL
     *
     * @since 2.1.0
     * @param string $url Raw URL
     * @return string Sanitized URL
     */
    public static function sanitize_url(string $url): string {
        return esc_url_raw($url);
    }
    
    /**
     * Sanitize integer
     *
     * @since 2.1.0
     * @param mixed $value Raw value
     * @return int Sanitized integer
     */
    public static function sanitize_int($value): int {
        return absint($value);
    }
    
    /**
     * Sanitize float
     *
     * @since 2.1.0
     * @param mixed $value Raw value
     * @return float Sanitized float
     */
    public static function sanitize_float($value): float {
        return floatval($value);
    }
    
    /**
     * Sanitize boolean
     *
     * @since 2.1.0
     * @param mixed $value Raw value
     * @return bool Sanitized boolean
     */
    public static function sanitize_bool($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Sanitize array of IDs
     *
     * @since 2.1.0
     * @param array $ids Raw array of IDs
     * @return array<int> Sanitized array of integers
     */
    public static function sanitize_id_array(array $ids): array {
        return array_map('absint', $ids);
    }
    
    /**
     * Escape for HTML output
     *
     * @since 2.1.0
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function esc_html(string $text): string {
        return esc_html($text);
    }
    
    /**
     * Escape for HTML attribute
     *
     * @since 2.1.0
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function esc_attr(string $text): string {
        return esc_attr($text);
    }
    
    /**
     * Escape URL
     *
     * @since 2.1.0
     * @param string $url URL to escape
     * @return string Escaped URL
     */
    public static function esc_url(string $url): string {
        return esc_url($url);
    }
    
    /**
     * Escape JavaScript string
     *
     * @since 2.1.0
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function esc_js(string $text): string {
        return esc_js($text);
    }
    
    /**
     * Validate email format
     *
     * @since 2.1.0
     * @param string $email Email to validate
     * @return bool True if valid email format
     */
    public static function is_valid_email(string $email): bool {
        return is_email($email) !== false;
    }
    
    /**
     * Validate URL format
     *
     * @since 2.1.0
     * @param string $url URL to validate
     * @return bool True if valid URL format
     */
    public static function is_valid_url(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Check if user owns a listing
     *
     * @since 2.1.0
     * @param int $listing_id Listing post ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if user owns the listing
     */
    public static function user_owns_listing(int $listing_id, ?int $user_id = null): bool {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $post = get_post($listing_id);
        if (!$post || $post->post_type !== 'listing') {
            return false;
        }
        
        return (int) $post->post_author === $user_id;
    }
    
    /**
     * Prevent timing attacks on string comparison
     *
     * @since 2.1.0
     * @param string $known_string Known string
     * @param string $user_string User-provided string
     * @return bool True if strings match
     */
    public static function hash_equals(string $known_string, string $user_string): bool {
        return hash_equals($known_string, $user_string);
    }
    
    /**
     * Generate secure random token
     *
     * @since 2.1.0
     * @param int $length Token length in bytes (default 32)
     * @return string Hex-encoded random token
     */
    public static function generate_token(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Rate limit check (simple implementation)
     *
     * @since 2.1.0
     * @param string $action Action identifier
     * @param int $limit Maximum attempts
     * @param int $period Time period in seconds
     * @return bool True if under limit, false if exceeded
     */
    public static function check_rate_limit(string $action, int $limit = 5, int $period = 60): bool {
        $user_id = get_current_user_id();
        $ip = self::get_client_ip();
        $key = "pc_rate_limit_{$action}_{$user_id}_{$ip}";
        
        $attempts = get_transient($key);
        if (false === $attempts) {
            set_transient($key, 1, $period);
            return true;
        }
        
        if ($attempts >= $limit) {
            return false;
        }
        
        set_transient($key, $attempts + 1, $period);
        return true;
    }
    
    /**
     * Get client IP address
     *
     * @since 2.1.0
     * @return string Client IP address
     */
    public static function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // CloudFlare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Log security event
     *
     * @since 2.1.0
     * @param string $event Event description
     * @param array $context Additional context
     * @return void
     */
    public static function log_security_event(string $event, array $context = []): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_entry = sprintf(
            '[Premium Classifieds Security] %s | User: %d | IP: %s | Context: %s',
            $event,
            get_current_user_id(),
            self::get_client_ip(),
            wp_json_encode($context)
        );
        
        error_log($log_entry);
    }
}
