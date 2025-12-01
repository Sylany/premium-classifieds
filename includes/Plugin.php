<?php
// File: includes/Plugin.php
namespace Premium\Classifieds;

defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Controllers\DashboardController;
use Premium\Classifieds\Controllers\PaymentController;
use Premium\Classifieds\Controllers\AdminController;
use Premium\Classifieds\Rest\ApiRoutes;

/**
 * Main Plugin class - bootstrap and wiring
 */
class Plugin {

    /**
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * @var string
     */
    public $version = PC_PLUGIN_VERSION;

    /**
     * Access singleton
     *
     * @return Plugin
     */
    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Prevent direct instantiation
    }

    /**
     * Start plugin: register controllers, shortcodes, assets etc.
     */
    public function run(): void {
        // Ensure core helpers loaded (if composer didn't autoload PSR-4)
        $this->maybe_load_core_files();

        // Instantiate controllers
        $this->init_controllers();

        // Admin controller init
        AdminController::init();

        // Register REST routes (on rest_api_init)
        add_action( 'rest_api_init', [ ApiRoutes::class, 'register' ] );

        // Register shortcodes (public)
        $this->load_shortcodes();

        // Enqueue assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        // No admin enqueue here; AdminController handles enqueues for admin screens
    }

    /**
     * Load core files when composer autoload not present
     */
    private function maybe_load_core_files(): void {
        // If classes are not autoloaded, require minimal set
        if ( ! class_exists( 'Premium\\Classifieds\\Core\\Helpers' ) ) {
            $core = PC_PLUGIN_DIR . 'includes/Core/Helpers.php';
            if ( file_exists( $core ) ) {
                require_once $core;
            }
        }

        if ( ! class_exists( 'Premium\\Classifieds\\Core\\Installer' ) ) {
            $installer = PC_PLUGIN_DIR . 'includes/Core/Installer.php';
            if ( file_exists( $installer ) ) {
                require_once $installer;
            }
        }

        // Controllers fallback (if composer not used)
        $controller_files = [
            'includes/Controllers/DashboardController.php',
            'includes/Controllers/PaymentController.php',
            'includes/Controllers/ProfileController.php',
            'includes/Controllers/AdminController.php',
        ];
        foreach ( $controller_files as $f ) {
            $path = PC_PLUGIN_DIR . $f;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        // Entities fallback
        $entities = [
            'includes/Entities/Listing.php',
            'includes/Entities/Transaction.php',
            'includes/Entities/Message.php',
        ];
        foreach ( $entities as $e ) {
            $p = PC_PLUGIN_DIR . $e;
            if ( file_exists( $p ) ) {
                require_once $p;
            }
        }

        // Views and public shortcodes
        $shortcodes = PC_PLUGIN_DIR . 'public/shortcodes.php';
        if ( file_exists( $shortcodes ) ) {
            require_once $shortcodes;
        }
    }

    /**
     * Instantiate controllers (frontend)
     */
    private function init_controllers(): void {
        // Dashboard controller (handles AJAX and shortcode view render)
        if ( class_exists( DashboardController::class ) ) {
            new DashboardController();
        }

        // Payment controller (Stripe)
        if ( class_exists( PaymentController::class ) ) {
            new PaymentController();
        }

        // Profile controller (AJAX profile update)
        if ( class_exists( 'Premium\\Classifieds\\Controllers\\ProfileController' ) ) {
            new Controllers\ProfileController();
        }
    }

    /**
     * Load public shortcodes file (already included in maybe_load_core_files but be safe)
     */
    private function load_shortcodes(): void {
        $file = PC_PLUGIN_DIR . 'public/shortcodes.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }

    /**
     * Enqueue frontend assets and localize script
     */
    public function enqueue_frontend_assets(): void {
        // Only enqueue when shortcode is present on page would be ideal; simple approach: always enqueue
        $ver = $this->version;

        // CSS
        $css = PC_PLUGIN_URL . 'assets/css/pc-dashboard.css';
        if ( file_exists( PC_PLUGIN_DIR . 'assets/css/pc-dashboard.css' ) ) {
            wp_enqueue_style( 'pc-frontend', $css, [], $ver );
        }

        // JS
        $js = PC_PLUGIN_URL . 'assets/js/pc-frontend.js';
        if ( file_exists( PC_PLUGIN_DIR . 'assets/js/pc-frontend.js' ) ) {
            wp_enqueue_script( 'pc-frontend', $js, [ 'jquery' ], $ver, true );

            // Localize data: ajax_url, nonce, stripe key
            $data = [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'pc_frontend_nonce' ),
                'stripe_key' => Helpers::get_option( 'pc_stripe_publishable_key', '' ),
            ];
            wp_localize_script( 'pc-frontend', 'pc_frontend', $data );
        }
    }
}
