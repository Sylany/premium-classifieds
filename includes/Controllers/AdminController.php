<?php

namespace PremiumClassifieds\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

class AdminController
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    /**
     * Register admin menu
     */
    public static function menu()
    {
        add_menu_page(
            'Premium Classifieds',
            'Premium Classifieds',
            'manage_options',
            'pc-dashboard',
            [__CLASS__, 'render_dashboard'],
            'dashicons-megaphone',
            26
        );

        add_submenu_page(
            'pc-dashboard',
            'Listings',
            'Listings',
            'manage_options',
            'pc-listings',
            [__CLASS__, 'render_listings']
        );

        add_submenu_page(
            'pc-dashboard',
            'Moderation',
            'Moderation',
            'manage_options',
            'pc-moderation',
            [__CLASS__, 'render_moderation']
        );

        add_submenu_page(
            'pc-dashboard',
            'Transactions',
            'Transactions',
            'manage_options',
            'pc-transactions',
            [__CLASS__, 'render_transactions']
        );

        add_submenu_page(
            'pc-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'pc-settings',
            [__CLASS__, 'render_settings']
        );
    }


    /**
     * DASHBOARD
     */
    public static function render_dashboard()
    {
        include PC_PLUGIN_DIR . 'admin/pages/dashboard.php';
    }

    /**
     * LISTINGS
     */
    public static function render_listings()
    {
        include PC_PLUGIN_DIR . 'admin/pages/listings.php';
    }

    /**
     * MODERATION
     */
    public static function render_moderation()
    {
        include PC_PLUGIN_DIR . 'admin/pages/moderation.php';
    }

    /**
     * TRANSACTIONS
     */
    public static function render_transactions()
    {
        include PC_PLUGIN_DIR . 'admin/pages/transactions.php';
    }

    /**
     * SETTINGS
     */
    public static function render_settings()
    {
        include PC_PLUGIN_DIR . 'admin/pages/settings.php';
    }
}
