<?php
/**
 * AJAX Request Router
 *
 * Centralized handler for all AJAX requests in the plugin.
 * Provides:
 * - Automatic nonce verification
 * - User capability checks
 * - Standardized JSON responses
 * - Error handling
 * - Rate limiting
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
 * AJAX Handler Class
 *
 * @since 2.1.0
 */
class PC_Ajax_Handler {
    
    /**
     * Registered AJAX actions
     *
     * @var array<string, array>
     */
    private array $actions = [];
    
    /**
     * Constructor - Register WordPress AJAX hooks
     *
     * @since 2.1.0
     */
    public function __construct() {
        $this->register_actions();
        $this->hook_ajax_handlers();
    }
    
    /**
     * Register all AJAX actions
     *
     * Format: 'action_name' => [
     *     'callback' => [ClassName, 'method'],
     *     'auth' => true/false,
     *     'capability' => 'required_cap' (optional)
     * ]
     *
     * @since 2.1.0
     * @return void
     */
    private function register_actions(): void {
        $this->actions = [
            // Dashboard actions
            'pc_load_dashboard_tab' => [
                'callback' => [PC_Dashboard::class, 'load_tab'],
                'auth' => true,
            ],
            'pc_add_listing' => [
                'callback' => [PC_Dashboard::class, 'add_listing'],
                'auth' => true,
            ],
            'pc_edit_listing' => [
                'callback' => [PC_Dashboard::class, 'edit_listing'],
                'auth' => true,
            ],
            'pc_delete_listing' => [
                'callback' => [PC_Dashboard::class, 'delete_listing'],
                'auth' => true,
            ],
            'pc_upload_image' => [
                'callback' => [PC_Dashboard::class, 'upload_image'],
                'auth' => true,
            ],
            
            // Messaging actions
            'pc_send_message' => [
                'callback' => [PC_Message_System::class, 'send_message'],
                'auth' => true,
            ],
            'pc_load_messages' => [
                'callback' => [PC_Message_System::class, 'load_messages'],
                'auth' => true,
            ],
            'pc_mark_message_read' => [
                'callback' => [PC_Message_System::class, 'mark_read'],
                'auth' => true,
            ],
            
            // Payment actions
            'pc_create_checkout' => [
                'callback' => [PC_Payment_Intent::class, 'create_checkout_session'],
                'auth' => true,
            ],
            'pc_verify_payment' => [
                'callback' => [PC_Payment_Intent::class, 'verify_payment'],
                'auth' => true,
            ],
            'pc_boost_listing' => [
                'callback' => [PC_Payment_Intent::class, 'boost_listing'],
                'auth' => true,
            ],
            
            // Favorites actions
            'pc_add_favorite' => [
                'callback' => [PC_Dashboard::class, 'add_favorite'],
                'auth' => true,
            ],
            'pc_remove_favorite' => [
                'callback' => [PC_Dashboard::class, 'remove_favorite'],
                'auth' => true,
            ],
            'pc_load_favorites' => [
                'callback' => [PC_Dashboard::class, 'load_favorites'],
                'auth' => true,
            ],
            
            // Admin actions
            'pc_moderate_listing' => [
                'callback' => [PC_Moderation::class, 'moderate'],
                'auth' => true,
                'capability' => 'moderate_listings',
            ],
            'pc_export_data' => [
                'callback' => [PC_Data_Exporter::class, 'export'],
                'auth' => true,
                'capability' => 'manage_options',
            ],
            'pc_get_revenue_stats' => [
                'callback' => [PC_Admin_Dashboard::class, 'get_stats'],
                'auth' => true,
                'capability' => 'manage_options',
            ],
            
            // Public actions (no auth required)
            'pc_search_listings' => [
                'callback' => [PC_Search_Filter::class, 'search'],
                'auth' => false,
            ],
			
// Messaging actions
'pc_send_message' => [
    'callback' => [PC_Message_System::class, 'send_message'],
    'auth' => true,
],
'pc_load_messages' => [
    'callback' => [PC_Message_System::class, 'load_messages'],
    'auth' => true,
],
'pc_mark_message_read' => [
    'callback' => [PC_Message_System::class, 'mark_read'],
    'auth' => true,
],
'pc_mark_all_read' => [
    'callback' => [PC_Message_System::class, 'mark_all_read'],
    'auth' => true,
],
'pc_delete_message' => [
    'callback' => [PC_Message_System::class, 'delete_message'],
    'auth' => true,
],
'pc_get_threads' => [
    'callback' => [PC_Message_System::class, 'get_threads'],
    'auth' => true,
],
        ];
    }
    
    /**
     * Hook all AJAX actions into WordPress
     *
     * @since 2.1.0
     * @return void
     */
    private function hook_ajax_handlers(): void {
        foreach ($this->actions as $action => $config) {
            if ($config['auth']) {
                // Authenticated users only
                add_action("wp_ajax_{$action}", function() use ($action) {
                    $this->handle_request($action);
                });
            } else {
                // Public (both logged in and logged out)
                add_action("wp_ajax_{$action}", function() use ($action) {
                    $this->handle_request($action);
                });
                add_action("wp_ajax_nopriv_{$action}", function() use ($action) {
                    $this->handle_request($action);
                });
            }
        }
    }
    
    /**
     * Handle AJAX request
     *
     * Orchestrates:
     * 1. Nonce verification
     * 2. Authentication check
     * 3. Capability check
     * 4. Rate limiting
     * 5. Callback execution
     * 6. Error handling
     *
     * @since 2.1.0
     * @param string $action Action identifier
     * @return void
     */
    private function handle_request(string $action): void {
        // Verify action is registered
        if (!isset($this->actions[$action])) {
            $this->send_error('Invalid action', 400);
        }
        
        $config = $this->actions[$action];
        
        // Verify nonce
        $nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
        if (!PC_Security::verify_nonce($nonce, 'dashboard')) {
            PC_Security::log_security_event('Invalid nonce', ['action' => $action]);
            $this->send_error('Security check failed', 403);
        }
        
        // Check authentication
        if ($config['auth'] && !is_user_logged_in()) {
            $this->send_error('Authentication required', 401);
        }
        
        // Check capabilities
        if (isset($config['capability'])) {
            if (!current_user_can($config['capability'])) {
                PC_Security::log_security_event('Capability check failed', [
                    'action' => $action,
                    'required_cap' => $config['capability']
                ]);
                $this->send_error('Permission denied', 403);
            }
        }
        
        // Rate limiting (5 requests per minute per action)
        if (!PC_Security::check_rate_limit($action, 30, 60)) {
            PC_Security::log_security_event('Rate limit exceeded', ['action' => $action]);
            $this->send_error('Too many requests. Please slow down.', 429);
        }
        
        // Execute callback
        try {
            $callback = $config['callback'];
            
            if (!is_callable($callback)) {
                throw new Exception('Invalid callback configuration');
            }
            
            // Call the handler
            call_user_func($callback);
            
        } catch (Exception $e) {
            // Log error
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("AJAX Error [{$action}]: " . $e->getMessage());
            }
            
            // Send generic error to client (don't expose internal details)
            $this->send_error(
                __('An error occurred. Please try again.', 'premium-classifieds'),
                500
            );
        }
    }
    
    /**
     * Send JSON success response
     *
     * @since 2.1.0
     * @param mixed $data Response data
     * @param int $status_code HTTP status code
     * @return void Dies after sending response
     */
    public static function send_success($data = null, int $status_code = 200): void {
        wp_send_json_success($data, $status_code);
    }
    
    /**
     * Send JSON error response
     *
     * @since 2.1.0
     * @param string $message Error message
     * @param int $status_code HTTP status code
     * @return void Dies after sending response
     */
    public static function send_error(string $message, int $status_code = 400): void {
        wp_send_json_error(['message' => $message], $status_code);
    }
    
    /**
     * Get sanitized POST parameter
     *
     * @since 2.1.0
     * @param string $key Parameter key
     * @param mixed $default Default value if not set
     * @param string $type Data type (text, int, float, bool, email, url, html)
     * @return mixed Sanitized value
     */
    public static function get_post(string $key, $default = '', string $type = 'text') {
        if (!isset($_POST[$key])) {
            return $default;
        }
        
        $value = wp_unslash($_POST[$key]);
        
        return match($type) {
            'int' => PC_Security::sanitize_int($value),
            'float' => PC_Security::sanitize_float($value),
            'bool' => PC_Security::sanitize_bool($value),
            'email' => PC_Security::sanitize_email($value),
            'url' => PC_Security::sanitize_url($value),
            'html' => PC_Security::sanitize_html($value),
            'textarea' => PC_Security::sanitize_textarea($value),
            'array' => is_array($value) ? PC_Security::sanitize_text($value) : [],
            default => PC_Security::sanitize_text($value),
        };
    }
    
    /**
     * Get sanitized GET parameter
     *
     * @since 2.1.0
     * @param string $key Parameter key
     * @param mixed $default Default value if not set
     * @param string $type Data type
     * @return mixed Sanitized value
     */
    public static function get_query(string $key, $default = '', string $type = 'text') {
        if (!isset($_GET[$key])) {
            return $default;
        }
        
        $value = wp_unslash($_GET[$key]);
        
        return match($type) {
            'int' => PC_Security::sanitize_int($value),
            'float' => PC_Security::sanitize_float($value),
            'bool' => PC_Security::sanitize_bool($value),
            'email' => PC_Security::sanitize_email($value),
            'url' => PC_Security::sanitize_url($value),
            'array' => is_array($value) ? PC_Security::sanitize_text($value) : [],
            default => PC_Security::sanitize_text($value),
        };
    }
    
    /**
     * Validate required parameters
     *
     * @since 2.1.0
     * @param array<string> $required_params List of required parameter names
     * @param string $source 'post' or 'get'
     * @return void Dies with error if validation fails
     */
    public static function validate_required(array $required_params, string $source = 'post'): void {
        $data = $source === 'get' ? $_GET : $_POST;
        $missing = [];
        
        foreach ($required_params as $param) {
            if (!isset($data[$param]) || empty($data[$param])) {
                $missing[] = $param;
            }
        }
        
        if (!empty($missing)) {
            self::send_error(
                sprintf(
                    __('Missing required parameters: %s', 'premium-classifieds'),
                    implode(', ', $missing)
                ),
                400
            );
        }
    }
}
