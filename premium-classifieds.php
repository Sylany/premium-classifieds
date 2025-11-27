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
    add_action('admin_notices', 'pc_php_version_notice');
    return;
}

function pc_php_version_notice() {
    echo '<div class="error"><p>';
    printf(
        esc_html__('Premium Classifieds requires PHP 8.0 or higher. You are running PHP %s. Please upgrade.', 'premium-classifieds'),
        PHP_VERSION
    );
    echo '</p></div>';
}

/**
 * EMERGENCY LOAD - Direct file inclusion as fallback
 * This ensures critical files are loaded regardless of autoloader issues
 */
function pc_emergency_load() {
    $critical_files = [
        'class-premium-classifieds' => PC_INCLUDES_DIR . 'class-premium-classifieds.php',
        'class-activator' => PC_INCLUDES_DIR . 'class-activator.php',
        'class-deactivator' => PC_INCLUDES_DIR . 'class-deactivator.php'
    ];
    
    foreach ($critical_files as $class_base => $file_path) {
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            // Try alternative path in core/ directory
            $alt_path = PC_INCLUDES_DIR . 'core/' . basename($file_path);
            if (file_exists($alt_path)) {
                require_once $alt_path;
            } elseif (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("PC CRITICAL: File not found - $file_path");
            }
        }
    }
}

// Load critical files immediately
pc_emergency_load();

/**
 * FIXED Autoloader - Handles both PC_ and Premium_Classifieds
 */
spl_autoload_register(function ($class) {
    // Handle main plugin class - CRITICAL FIX
    if ($class === 'Premium_Classifieds') {
        $files_to_try = [
            PC_INCLUDES_DIR . 'class-premium-classifieds.php',
            PC_INCLUDES_DIR . 'core/class-premium-classifieds.php'
        ];
        
        foreach ($files_to_try as $file) {
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        return;
    }
    
    // Handle PC_ prefixed classes
    if (strpos($class, 'PC_') !== 0) {
        return;
    }

    // Convert PC_Class_Name to class-class-name.php
    $class_name = substr($class, 3); // Remove PC_ prefix
    $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    
    // Search in these directories in order of priority
    $search_dirs = [
        '',
        'core/',
        'admin/',
        'payments/',
        'database/',
        'frontend/',
        'utils/',
        'messaging/',
        'templates/'
    ];

    foreach ($search_dirs as $dir) {
        $file_path = PC_INCLUDES_DIR . $dir . $file_name;
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }

    // Debug: Log missing classes only if debug is enabled
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log("PC Autoloader: Class $class not found. Searched for: $file_name");
    }
});

/**
 * Activation hook - Create database tables and set default options
 * 
 * @since 2.1.0
 */
register_activation_hook(__FILE__, function() {
    if (class_exists('PC_Activator')) {
        PC_Activator::activate();
    } else {
        // Fallback: Try to load activator manually
        $activator_file = PC_INCLUDES_DIR . 'class-activator.php';
        if (file_exists($activator_file)) {
            require_once $activator_file;
            if (class_exists('PC_Activator')) {
                PC_Activator::activate();
            }
        }
    }
});

/**
 * Deactivation hook - Cleanup temporary data (optional)
 * 
 * @since 2.1.0
 */
register_deactivation_hook(__FILE__, function() {
    if (class_exists('PC_Deactivator')) {
        PC_Deactivator::deactivate();
    } else {
        // Fallback: Try to load deactivator manually
        $deactivator_file = PC_INCLUDES_DIR . 'class-deactivator.php';
        if (file_exists($deactivator_file)) {
            require_once $deactivator_file;
            if (class_exists('PC_Deactivator')) {
                PC_Deactivator::deactivate();
            }
        }
    }
});

/**
 * Error logging helper
 */
function pc_log_error($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Premium Classifieds Error: ' . $message);
    }
}

/**
 * Admin notice for missing main class
 */
function pc_main_class_missing_notice() {
    ?>
    <div class="error">
        <p>
            <strong><?php esc_html_e('Premium Classifieds Critical Error:', 'premium-classifieds'); ?></strong>
            <?php esc_html_e('Main plugin class not found. Please reinstall the plugin.', 'premium-classifieds'); ?>
        </p>
        <p>
            <small>
                <?php 
                printf(
                    esc_html__('Checked path: %s', 'premium-classifieds'),
                    PC_INCLUDES_DIR . 'class-premium-classifieds.php'
                );
                ?>
            </small>
        </p>
    </div>
    <?php
}

/**
 * Admin notice for successful activation
 */
function pc_activation_success_notice() {
    ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <strong><?php esc_html_e('Premium Classifieds', 'premium-classifieds'); ?>:</strong>
            <?php esc_html_e('Plugin activated successfully! Configure settings to get started.', 'premium-classifieds'); ?>
        </p>
        <p>
            <a href="<?php echo admin_url('admin.php?page=premium-classifieds-settings'); ?>" class="button button-primary">
                <?php esc_html_e('Go to Settings', 'premium-classifieds'); ?>
            </a>
        </p>
    </div>
    <?php
}

/**
 * Initialize the plugin - SAFE VERSION with comprehensive error handling
 */
function run_premium_classifieds() {
    // Double-check if main class exists
    if (!class_exists('Premium_Classifieds')) {
        // Final attempt to load manually
        $main_class_file = PC_INCLUDES_DIR . 'class-premium-classifieds.php';
        if (file_exists($main_class_file)) {
            require_once $main_class_file;
        } else {
            // Check core directory
            $core_class_file = PC_INCLUDES_DIR . 'core/class-premium-classifieds.php';
            if (file_exists($core_class_file)) {
                require_once $core_class_file;
            }
        }
    }
    
    // Now try to initialize
    if (class_exists('Premium_Classifieds')) {
        try {
            $plugin = Premium_Classifieds::get_instance();
            $plugin->run();
            
            // Show success notice on plugin activation
            if (isset($_GET['activate']) && $_GET['activate'] === 'true') {
                add_action('admin_notices', 'pc_activation_success_notice');
            }
            
        } catch (Exception $e) {
            pc_log_error('Plugin initialization failed: ' . $e->getMessage());
            pc_log_error('Stack trace: ' . $e->getTraceAsString());
            
            add_action('admin_notices', function() use ($e) {
                echo '<div class="error"><p>';
                echo '<strong>' . esc_html__('Premium Classifieds Runtime Error:', 'premium-classifieds') . '</strong> ';
                echo esc_html($e->getMessage());
                echo '</p></div>';
            });
        }
    } else {
        // Ultimate fallback - log everything we know
        pc_log_error('Premium_Classifieds class not found after all attempts');
        pc_log_error('INCLUDES_DIR: ' . PC_INCLUDES_DIR);
        
        // List files for debugging
        if (is_dir(PC_INCLUDES_DIR)) {
            $files = scandir(PC_INCLUDES_DIR);
            pc_log_error('Files in includes: ' . implode(', ', $files));
        } else {
            pc_log_error('INCLUDES_DIR does not exist: ' . PC_INCLUDES_DIR);
        }
        
        add_action('admin_notices', 'pc_main_class_missing_notice');
    }
}

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

/**
 * Load plugin after WordPress initializes with high priority
 */
add_action('plugins_loaded', 'run_premium_classifieds', 1);

/**
 * Debug helper function - can be called from anywhere
 */
if (!function_exists('pc_debug_log')) {
    function pc_debug_log($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($data !== null) {
                error_log('PC DEBUG: ' . $message . ' - ' . print_r($data, true));
            } else {
                error_log('PC DEBUG: ' . $message);
            }
        }
    }
}