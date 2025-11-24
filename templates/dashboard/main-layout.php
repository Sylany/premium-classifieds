<?php
/**
 * Dashboard Main Layout Template
 *
 * Variables available:
 * @var WP_User $user Current user object
 * @var int $user_id Current user ID
 * @var array $tabs Available tabs configuration
 *
 * @package    PremiumClassifieds
 * @subpackage Templates/Dashboard
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'my-listings';
?>

<div class="pc-dashboard-wrapper">
    <!-- Dashboard Header -->
    <div class="pc-dashboard-header">
        <div class="pc-header-content">
            <div class="pc-user-info">
                <div class="pc-avatar">
                    <?php echo get_avatar($user_id, 48); ?>
                </div>
                <div class="pc-user-details">
                    <h2 class="pc-user-name"><?php echo esc_html($user->display_name); ?></h2>
                    <p class="pc-user-email"><?php echo esc_html($user->user_email); ?></p>
                </div>
            </div>
            <div class="pc-header-actions">
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pc-btn pc-btn-secondary">
                    <span class="dashicons dashicons-exit"></span>
                    <?php esc_html_e('Logout', 'premium-classifieds'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="pc-dashboard-container">
        <!-- Sidebar Navigation -->
        <aside class="pc-dashboard-sidebar">
            <nav class="pc-dashboard-nav">
                <ul class="pc-nav-list">
                    <?php foreach ($tabs as $tab_key => $tab_config): ?>
                        <li class="pc-nav-item <?php echo $active_tab === $tab_key ? 'active' : ''; ?>">
                            <a href="#" 
                               class="pc-nav-link" 
                               data-tab="<?php echo esc_attr($tab_key); ?>">
                                <span class="dashicons <?php echo esc_attr($tab_config['icon']); ?>"></span>
                                <span class="pc-nav-text"><?php echo esc_html($tab_config['title']); ?></span>
                                <?php if (isset($tab_config['badge']) && $tab_config['badge'] > 0): ?>
                                    <span class="pc-badge"><?php echo esc_html($tab_config['badge']); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="pc-dashboard-main">
            <!-- Loading Indicator -->
            <div class="pc-loading-overlay" style="display: none;">
                <div class="pc-spinner"></div>
                <p><?php esc_html_e('Loading...', 'premium-classifieds'); ?></p>
            </div>

            <!-- Tab Content Container -->
            <div id="pc-tab-content" class="pc-tab-content">
                <?php
                // Load default tab content
                $default_template = PC_TEMPLATES_DIR . 'dashboard/' . $tabs[$active_tab]['template'];
                if (file_exists($default_template)) {
                    include $default_template;
                }
                ?>
            </div>
        </main>
    </div>
</div>

<!-- Success/Error Toast Notification -->
<div id="pc-toast" class="pc-toast" style="display: none;">
    <div class="pc-toast-content">
        <span class="pc-toast-icon"></span>
        <span class="pc-toast-message"></span>
    </div>
</div>
