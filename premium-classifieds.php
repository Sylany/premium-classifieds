<?php
/**
 * Premium Classifieds - Pay-to-Contact Platform
 *
 * @package           PremiumClassifieds
 * @author            Your Company
 * @copyright         2025 Your Company
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Premium Classifieds
 * Plugin URI:        https://yourcompany.com/premium-classifieds
 * Description:       Enterprise-grade pay-to-contact classifieds platform with native Stripe integration. Users pay to reveal contact details or send messages.
 * Version:           2.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Company
 * Author URI:        https://yourcompany.com
 * Text Domain:       premium-classifieds
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly - Critical security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin constants - Single source of truth for paths and versions
 */
define('PC_VERSION', '2.1.0');
define('PC_PLUGIN_FILE', __FILE__);
define('PC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('PC_INCLUDES_DIR', PC_PLUGIN_DIR . 'includes/');
define('PC_ASSETS_URL', PC_PLUGIN_URL . 'assets/');
define('PC_TEMPLATES_DIR', PC_PLUGIN_DIR . 'templates/');

/**
 * Minimum PHP version check - Prevent activation on incompatible environments
 */
if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        printf(
            esc_html__('Premium Classifieds requires PHP 8.0 or higher. You are running PHP %s. Please upgrade.', 'premium-classifieds'),
            PHP_VERSION
        );
        echo '</p></div>';
    });
    return;
}

/**
 * Autoloader - PSR-4 compatible class loading
 * Maps class names to file paths automatically
 * 
 * Example: PC_Security -> includes/utils/class-security.php
 */
spl_autoload_register(function ($class) {
    // Only autoload our classes (prefix: PC_)
    if (strpos($class, 'PC_') !== 0) {
        return;
    }

    // Convert class name to file path
    // PC_Security -> class-security.php
    // PC_DB_Transactions -> database/class-db-transactions.php
    $class_name = strtolower(str_replace('_', '-', substr($class, 3)));
    
    // Determine subdirectory based on class prefix
    $subdirs = [
        'db-' => 'database/',
        'admin-' => 'admin/',
        'stripe-' => 'payments/',
        'payment-' => 'payments/',
        'webhook-' => 'payments/',
        'transaction-' => 'payments/',
        'message-' => 'messaging/',
        'notification' => 'messaging/',
        'dashboard' => 'frontend/',
        'listing-' => 'frontend/',
        'search-' => 'frontend/',
        'post-types' => 'core/',
        'taxonomies' => 'core/',
        'meta-boxes' => 'core/',
        'roles-' => 'core/',
    ];

    $subdir = '';
    foreach ($subdirs as $prefix => $dir) {
        if (strpos($class_name, $prefix) === 0) {
            $subdir = $dir;
            break;
        }
    }

    // Special handling for utils
    if (in_array($class_name, ['security', 'ajax-handler', 'template-loader'])) {
        $subdir = 'utils/';
    }

    $file = PC_INCLUDES_DIR . $subdir . 'class-' . $class_name . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Load core dependencies - Required before main class instantiation
 */
require_once PC_INCLUDES_DIR . 'class-activator.php';
require_once PC_INCLUDES_DIR . 'class-deactivator.php';

/**
 * Activation hook - Create database tables and set default options
 * 
 * @since 2.1.0
 */
register_activation_hook(__FILE__, function() {
    PC_Activator::activate();
});

/**
 * Deactivation hook - Cleanup temporary data (optional)
 * 
 * @since 2.1.0
 */
register_deactivation_hook(__FILE__, function() {
    PC_Deactivator::deactivate();
});

/**
 * Initialize the plugin - Entry point after WordPress loads
 * 
 * @since 2.1.0
 * @return void
 */
function run_premium_classifieds(): void {
    try {
        $plugin = Premium_Classifieds::get_instance();
        $plugin->run();
    } catch (Exception $e) {
        // Log critical errors to WordPress debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Premium Classifieds Fatal Error: ' . $e->getMessage());
        }
        
        // Show admin notice for critical failures
        add_action('admin_notices', function() use ($e) {
            echo '<div class="error"><p>';
            echo esc_html__('Premium Classifieds failed to initialize: ', 'premium-classifieds');
            echo esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
}

/**
 * Load plugin only after WordPress fully initializes
 * Ensures all WP functions are available
 */
add_action('plugins_loaded', 'run_premium_classifieds');

/**
 * Load text domain for translations
 * 
 * @since 2.1.0
 */
add_action('init', function() {
    load_plugin_textdomain(
        'premium-classifieds',
        false,
        dirname(PC_PLUGIN_BASENAME) . '/languages'
    );
});
