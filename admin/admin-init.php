<?php

use PremiumClassifieds\Controllers\AdminController;

// Blokada bezpośredniego dostępu
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initializes admin area for Premium Classifieds
 */
add_action('plugins_loaded', function () {

    if (is_admin()) {
        // Load admin controller
        AdminController::init();
    }
});
