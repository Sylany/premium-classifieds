<?php
/**
 * Main Plugin Class - Orchestrates all subsystems
 *
 * This class implements the Singleton pattern to ensure only one instance
 * exists throughout the WordPress lifecycle. It coordinates all plugin
 * components and manages their initialization order.
 *
 * @package    PremiumClassifieds
 * @subpackage Core
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Premium Classifieds Main Controller
 * 
 * Responsibilities:
 * - Initialize all subsystems in correct order
 * - Register WordPress hooks and filters
 * - Manage asset loading (CSS/JS)
 * - Coordinate frontend/admin components
 *
 * @since 2.1.0
 */
class Premium_Classifieds {
    
    /**
     * Singleton instance
     *
     * @var Premium_Classifieds|null
     */
    private static ?Premium_Classifieds $instance = null;
    
    /**
     * Plugin version
     *
     * @var string
     */
    private string $version;
    
    /**
     * Component instances
     *
     * @var array<string, object>
     */
    private array $components = [];
    
    /**
     * Private constructor - Singleton pattern
     *
     * @since 2.1.0
     */
    private function __construct() {
        $this->version = PC_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    /**
     * Get singleton instance
     *
     * @since 2.1.0
     * @return Premium_Classifieds
     */
    public static function get_instance(): Premium_Classifieds {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Prevent cloning of singleton
     *
     * @since 2.1.0
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of singleton
     *
     * @since 2.1.0
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Load required dependencies and initialize components
     *
     * @since 2.1.0
     * @return void
     */
    private function load_dependencies(): void {
        // Core components
        $this->components['security'] = new PC_Security();
        $this->components['ajax'] = new PC_Ajax_Handler();
        
        // Database handlers
        $this->components['db_transactions'] = new PC_DB_Transactions();
        $this->components['db_messages'] = new PC_DB_Messages();
        $this->components['db_access'] = new PC_DB_Access_Grants();
        
        // Core WordPress integration
        $this->components['post_types'] = new PC_Post_Types();
        $this->components['taxonomies'] = new PC_Taxonomies();
        $this->components['meta_boxes'] = new PC_Meta_Boxes();
        
        // Frontend components
        if (!is_admin()) {
            $this->components['dashboard'] = new PC_Dashboard();
            $this->components['listing_display'] = new PC_Listing_Display();
        }
        
        // Admin components
        if (is_admin()) {
            $this->components['admin_dashboard'] = new PC_Admin_Dashboard();
            $this->components['moderation'] = new PC_Moderation();
            $this->components['settings'] = new PC_Settings();
            $this->components['data_exporter'] = new PC_Data_Exporter();
        }
        
        // Payment system (always loaded for webhooks)
        $this->components['stripe_gateway'] = new PC_Stripe_Gateway();
        $this->components['webhook_handler'] = new PC_Webhook_Handler();
    }
    
    /**
     * Register admin-specific hooks
     *
     * @since 2.1.0
     * @return void
     */
    private function define_admin_hooks(): void {
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Add settings link to plugins page
        add_filter(
            'plugin_action_links_' . PC_PLUGIN_BASENAME,
            [$this, 'add_settings_link']
        );
        
        // Admin menu
        add_action('admin_menu', [$this, 'register_admin_menu']);
        
        // Save post meta
        add_action('save_post', [$this, 'save_listing_meta'], 10, 2);
    }
    
    /**
     * Register public-facing hooks
     *
     * @since 2.1.0
     * @return void
     */
    private function define_public_hooks(): void {
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        
        // Register shortcodes
        add_shortcode('premium_dashboard', [$this, 'render_dashboard_shortcode']);
        add_shortcode('premium_listings', [$this, 'render_listings_shortcode']);
        
        // Template overrides
        add_filter('template_include', [$this, 'load_custom_templates']);
        
        // Restrict contact info display
        add_filter('the_content', [$this, 'filter_listing_content'], 20);
    }
    
    /**
     * Enqueue admin CSS and JavaScript
     *
     * @since 2.1.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void {
        // Only load on our plugin pages
        if (!str_contains($hook, 'premium-classifieds')) {
            return;
        }
        
        // Admin stylesheet
        wp_enqueue_style(
            'pc-admin-styles',
            PC_ASSETS_URL . 'css/admin.css',
            [],
            $this->version
        );
        
        // Chart.js for revenue dashboard
        wp_enqueue_script(
            'chart-js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );
        
        // Admin JavaScript
        wp_enqueue_script(
            'pc-admin-scripts',
            PC_ASSETS_URL . 'js/admin.js',
            ['jquery', 'chart-js'],
            $this->version,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('pc-admin-scripts', 'pcAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pc_admin_nonce'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this?', 'premium-classifieds'),
                'exportSuccess' => __('Data exported successfully', 'premium-classifieds'),
                'error' => __('An error occurred', 'premium-classifieds'),
            ]
        ]);
    }
    
    /**
     * Enqueue public CSS and JavaScript
     *
     * @since 2.1.0
     * @return void
     */
    public function enqueue_public_assets(): void {
        // Frontend stylesheet
        wp_enqueue_style(
            'pc-frontend-styles',
            PC_ASSETS_URL . 'css/frontend-dashboard.css',
            [],
            $this->version
        );
        
        // Public stylesheet
        wp_enqueue_style(
            'pc-public-styles',
            PC_ASSETS_URL . 'css/public.css',
            [],
            $this->version
        );
        
        // jQuery (WordPress bundled)
        wp_enqueue_script('jquery');
        
        // Dashboard AJAX handler
        wp_enqueue_script(
            'pc-dashboard-scripts',
            PC_ASSETS_URL . 'js/dashboard.js',
            ['jquery'],
            $this->version,
            true
        );
        
        // Stripe integration
        wp_enqueue_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            [],
            null,
            true
        );
        
        wp_enqueue_script(
            'pc-stripe-checkout',
            PC_ASSETS_URL . 'js/stripe-checkout.js',
            ['jquery', 'stripe-js'],
            $this->version,
            true
        );
        
        // Drag & drop uploader
        wp_enqueue_script(
            'pc-drag-drop',
            PC_ASSETS_URL . 'js/drag-drop-upload.js',
            ['jquery'],
            $this->version,
            true
        );
        
        // Localize with current user data and settings
        $user_id = get_current_user_id();
        wp_localize_script('pc-dashboard-scripts', 'pcData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pc_dashboard_nonce'),
            'userId' => $user_id,
            'isLoggedIn' => is_user_logged_in(),
            'stripePublicKey' => get_option('pc_stripe_public_key', ''),
            'prices' => [
                'contactReveal' => (float) get_option('pc_price_contact_reveal', 5.00),
                'listingBoost' => (float) get_option('pc_price_listing_boost', 10.00),
                'subscription' => (float) get_option('pc_price_subscription', 49.00),
            ],
            'strings' => [
                'confirmDelete' => __('Delete this listing?', 'premium-classifieds'),
                'uploading' => __('Uploading...', 'premium-classifieds'),
                'messageSent' => __('Message sent successfully', 'premium-classifieds'),
            ]
        ]);
    }
    
    /**
     * Add settings link to plugins page
     *
     * @since 2.1.0
     * @param array<string> $links Existing plugin action links
     * @return array<string> Modified links
     */
    public function add_settings_link(array $links): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=premium-classifieds-settings'),
            __('Settings', 'premium-classifieds')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Register admin menu pages
     *
     * @since 2.1.0
     * @return void
     */
    public function register_admin_menu(): void {
        // Main menu
        add_menu_page(
            __('Premium Classifieds', 'premium-classifieds'),
            __('Classifieds', 'premium-classifieds'),
            'manage_options',
            'premium-classifieds',
            [$this->components['admin_dashboard'], 'render'],
            'dashicons-megaphone',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'premium-classifieds',
            __('Dashboard', 'premium-classifieds'),
            __('Dashboard', 'premium-classifieds'),
            'manage_options',
            'premium-classifieds',
            [$this->components['admin_dashboard'], 'render']
        );
        
        // Settings submenu
        add_submenu_page(
            'premium-classifieds',
            __('Settings', 'premium-classifieds'),
            __('Settings', 'premium-classifieds'),
            'manage_options',
            'premium-classifieds-settings',
            [$this->components['settings'], 'render']
        );
    }
    
    /**
     * Render dashboard shortcode
     *
     * @since 2.1.0
     * @param array<string, mixed> $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_dashboard_shortcode(array $atts): string {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access your dashboard.', 'premium-classifieds') . '</p>';
        }
        
        return $this->components['dashboard']->render();
    }
    
    /**
     * Render listings shortcode
     *
     * @since 2.1.0
     * @param array<string, mixed> $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_listings_shortcode(array $atts): string {
        $atts = shortcode_atts([
            'category' => '',
            'limit' => 12,
            'featured' => false,
        ], $atts);
        
        return $this->components['listing_display']->render_archive($atts);
    }
    
    /**
     * Load custom templates for listings
     *
     * @since 2.1.0
     * @param string $template Current template path
     * @return string Modified template path
     */
    public function load_custom_templates(string $template): string {
        if (is_singular('listing')) {
            $custom_template = PC_TEMPLATES_DIR . 'single-listing.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        if (is_post_type_archive('listing')) {
            $custom_template = PC_TEMPLATES_DIR . 'archive-listing.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Filter listing content to hide contact info unless paid
     *
     * @since 2.1.0
     * @param string $content Post content
     * @return string Filtered content
     */
    public function filter_listing_content(string $content): string {
        if (!is_singular('listing')) {
            return $content;
        }
        
        $post_id = get_the_ID();
        $user_id = get_current_user_id();
        
        // Check if user has access
        $has_access = $this->components['db_access']->check_access($user_id, $post_id);
        
        if (!$has_access) {
            // Hide contact info and show paywall
            $content .= $this->components['listing_display']->render_paywall($post_id);
        }
        
        return $content;
    }
    
    /**
     * Save custom post meta
     *
     * @since 2.1.0
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @return void
     */
    public function save_listing_meta(int $post_id, \WP_Post $post): void {
        if ($post->post_type !== 'listing') {
            return;
        }
        
        // Delegate to meta boxes component
        $this->components['meta_boxes']->save($post_id);
    }
    
    /**
     * Run the plugin - Main execution entry point
     *
     * @since 2.1.0
     * @return void
     */
    public function run(): void {
        // Plugin is now fully initialized
        do_action('premium_classifieds_loaded', $this);
    }
    
    /**
     * Get plugin version
     *
     * @since 2.1.0
     * @return string Version number
     */
    public function get_version(): string {
        return $this->version;
    }
    
    /**
     * Get component instance by name
     *
     * @since 2.1.0
     * @param string $component_name Component identifier
     * @return object|null Component instance or null if not found
     */
    public function get_component(string $component_name): ?object {
        return $this->components[$component_name] ?? null;
    }
}
