<?php
namespace Premium\Classifieds\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 * Main bootstrap for Premium Classifieds plugin.
 *
 * Responsibilities:
 * - Register hooks (init, wp_enqueue_scripts, admin_menu)
 * - Register shortcodes
 * - Initialize controllers
 *
 * Minimal surface to keep response size manageable. Controllers and other classes are loaded on demand.
 */
class Plugin {

    /** @var string */
    private $file;

    /** @var string */
    private $version = '2.0.2';

    /** @var Plugin|null */
    private static $instance = null;

    /**
     * Singleton accessor.
     *
     * @param string $file plugin main file
     * @return Plugin
     */
    public static function instance( string $file = '' ) : self {
        if ( null === self::$instance ) {
            self::$instance = new self( $file );
        }
        return self::$instance;
    }

    /**
     * Constructor - register hooks.
     *
     * @param string $file
     */
    private function __construct( string $file ) {
        $this->file = $file;

        // Load textdomain
        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Register CPT and taxonomies
        add_action( 'init', [ $this, 'register_post_types' ], 5 );

        // Enqueue frontend assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Admin assets
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_assets' ] );

        // Shortcodes
        add_action( 'init', [ $this, 'register_shortcodes' ] );

        // AJAX endpoints for logged in and guest where necessary
        add_action( 'wp_ajax_pc_dashboard_action', [ $this, 'handle_ajax' ] );
        add_action( 'wp_ajax_nopriv_pc_public_action', [ $this, 'handle_public_ajax' ] );

        // Init controllers (lazy-load)
        add_action( 'plugins_loaded', [ $this, 'init_controllers' ], 20 );

        // REST / webhook endpoints (placeholder)
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Security: hide admin bar for non-admins on front-end dashboard
        add_action( 'after_setup_theme', [ $this, 'maybe_hide_admin_bar' ] );
    }

    /**
     * Load plugin translations
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'premium-classifieds', false, dirname( plugin_basename( $this->file ) ) . '/languages' );
    }

    /**
     * Register CPTs and taxonomies — minimal scaffold
     */
    public function register_post_types() : void {
        // Listing CPT
        $labels = [
            'name' => __( 'Listings', 'premium-classifieds' ),
            'singular_name' => __( 'Listing', 'premium-classifieds' ),
            'add_new_item' => __( 'Add New Listing', 'premium-classifieds' ),
            'edit_item' => __( 'Edit Listing', 'premium-classifieds' ),
        ];
        register_post_type( 'pc_listing', [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'supports' => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author' ],
            'rewrite' => [ 'slug' => 'znajdz-towarzysza' ],
        ] );

        // Example taxonomy: category
        register_taxonomy( 'pc_listing_category', 'pc_listing', [
            'label' => __( 'Listing Categories', 'premium-classifieds' ),
            'hierarchical' => true,
            'show_in_rest' => true,
        ] );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() : void {
        $ver = $this->version;
        wp_register_style( 'pc-frontend', plugin_dir_url( $this->file ) . 'assets/css/pc-dashboard.css', [], $ver );
        wp_enqueue_style( 'pc-frontend' );

        wp_register_script( 'pc-frontend', plugin_dir_url( $this->file ) . 'assets/js/pc-frontend.js', [ 'jquery' ], $ver, true );

        // Localize nonce & ajax url
        wp_localize_script( 'pc-frontend', 'pc_frontend', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pc_frontend_nonce' ),
            'strings'  => [
                'confirm_delete' => __( 'Are you sure you want to delete this listing?', 'premium-classifieds' ),
            ],
        ] );

        wp_enqueue_script( 'pc-frontend' );
    }

    /**
     * Admin assets
     */
    public function admin_enqueue_assets( $hook ) : void {
        $ver = $this->version;
        wp_register_style( 'pc-admin', plugin_dir_url( $this->file ) . 'assets/css/pc-dashboard.css', [], $ver );
        wp_enqueue_style( 'pc-admin' );

        wp_register_script( 'pc-admin', plugin_dir_url( $this->file ) . 'assets/js/pc-frontend.js', [ 'jquery' ], $ver, true );
        wp_localize_script( 'pc-admin', 'pc_admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'pc_admin_nonce' ),
        ] );
        wp_enqueue_script( 'pc-admin' );
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes() : void {
        add_shortcode( 'premium_dashboard', [ $this, 'shortcode_dashboard' ] );
        // Additional shortcodes are registered in public/shortcodes.php in future steps
    }

    /**
     * Render user dashboard shortcode (wrapper) — real rendering delegated to DashboardController
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_dashboard( $atts = [] ) : string {
        if ( ! class_exists( 'Premium\\Classifieds\\Controllers\\DashboardController' ) ) {
            return '<div class="pc-dashboard">Dashboard currently unavailable.</div>';
        }
        $controller = new \Premium\Classifieds\Controllers\DashboardController();
        return $controller->render_dashboard( $atts );
    }

    /**
     * Initialize controllers (require files) — lazy-load heavy functionality
     */
    public function init_controllers() : void {
        // require controllers (they will be autoloaded by our spl_autoload_register if present)
        // instantiate any singletons if necessary
        // Example: initialize AdminController only in admin
        if ( is_admin() ) {
            if ( class_exists( 'Premium\\Classifieds\\Controllers\\AdminController' ) ) {
                \Premium\Classifieds\Controllers\AdminController::init();
            }
        }
    }

    /**
     * Register REST API routes (placeholder)
     */
    public function register_rest_routes() : void {
        if ( class_exists( 'Premium\\Classifieds\\Rest\\ApiRoutes' ) ) {
            \Premium\Classifieds\Rest\ApiRoutes::register();
        }
    }

    /**
     * Example unified AJAX handler (placeholder)
     */
    public function handle_ajax() {
        // Basic nonce check
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pc_admin_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }

        // route actions: action param required (pc_action)
        $action = isset( $_POST['pc_action'] ) ? sanitize_text_field( wp_unslash( $_POST['pc_action'] ) ) : '';
        if ( empty( $action ) ) {
            wp_send_json_error( [ 'message' => __( 'No action specified', 'premium-classifieds' ) ], 400 );
        }

        // Dispatch to controllers (example)
        try {
            do_action( 'pc_ajax_' . $action );
        } catch ( \Throwable $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
        }

        // Default response if handler didn't exit
        wp_send_json_success( [ 'message' => __( 'OK', 'premium-classifieds' ) ] );
    }

    /**
     * Public AJAX for non-logged in actions (placeholder)
     */
    public function handle_public_ajax() {
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pc_frontend_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'premium-classifieds' ) ], 400 );
        }

        $action = isset( $_POST['pc_action'] ) ? sanitize_text_field( wp_unslash( $_POST['pc_action'] ) ) : '';
        if ( empty( $action ) ) {
            wp_send_json_error( [ 'message' => __( 'No action specified', 'premium-classifieds' ) ], 400 );
        }

        do_action( 'pc_public_ajax_' . $action );
        wp_send_json_success( [ 'message' => __( 'OK', 'premium-classifieds' ) ] );
    }

    /**
     * Hide admin bar for users who should use only frontend dashboard
     */
    public function maybe_hide_admin_bar() : void {
        if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
            // Optionally hide for 'seller' role as configured
            show_admin_bar( false );
        }
    }

}
