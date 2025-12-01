<?php
/**
 * Plugin Name: Premium Classifieds (Pay-to-Contact)
 * Description: Platforma ogłoszeń z modelem Pay-to-Contact.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: premium-classifieds
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PC_PLUGIN_FILE', __FILE__);
define('PC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PC_VERSION', '1.0.0');

// Autoload (includes/autoload.php)
if (file_exists(PC_PLUGIN_DIR . 'includes/autoload.php')) {
    require_once PC_PLUGIN_DIR . 'includes/autoload.php';
}

/**
 * LOAD ADMIN
 * This was missing!
 */
if (file_exists(PC_PLUGIN_DIR . 'admin/admin-init.php')) {
    require_once PC_PLUGIN_DIR . 'admin/admin-init.php';
}

// Core bootstrap
if (file_exists(PC_PLUGIN_DIR . 'includes/Core/Plugin.php')) {
    add_action('plugins_loaded', function () {
        if (class_exists('\PremiumClassifieds\Core\Plugin')) {
            \PremiumClassifieds\Core\Plugin::instance()->init();
        }
    });
}

// Public shortcodes + ajax
if (file_exists(PC_PLUGIN_DIR . 'public/shortcodes.php')) {
    require_once PC_PLUGIN_DIR . 'public/shortcodes.php';
}
if (file_exists(PC_PLUGIN_DIR . 'public/ajax.php')) {
    require_once PC_PLUGIN_DIR . 'public/ajax.php';
}

// Activation / Deactivation
register_activation_hook(__FILE__, function () {
    if (class_exists('\PremiumClassifieds\Core\Installer')) {
        \PremiumClassifieds\Core\Installer::activate();
    }
});

register_deactivation_hook(__FILE__, function () {
    if (class_exists('\PremiumClassifieds\Core\Installer')) {
        \PremiumClassifieds\Core\Installer::deactivate();
    }
});

// uninstall.php handles full removal
