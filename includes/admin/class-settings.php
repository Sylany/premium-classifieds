<?php
/**
 * Settings Page Controller
 *
 * Manages plugin settings in WordPress admin.
 * Provides interface for:
 * - Stripe configuration (API keys, webhook)
 * - Pricing settings (contact reveal, boost, subscription)
 * - Feature flags (messaging, favorites, moderation)
 * - Email notifications
 * - General settings
 *
 * @package    PremiumClassifieds
 * @subpackage Admin
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Class
 *
 * @since 2.1.0
 */
class PC_Settings {
    
    /**
     * Settings page slug
     *
     * @var string
     */
    private const PAGE_SLUG = 'premium-classifieds-settings';
    
    /**
     * Active tab
     *
     * @var string
     */
    private string $active_tab = 'stripe';
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'show_save_notice']);
        
        // Get active tab from URL
        $this->active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'stripe';
    }
    
    /**
     * Render settings page
     *
     * @since 2.1.0
     * @return void
     */
    public function render(): void {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'premium-classifieds'));
        }
        
        ?>
        <div class="wrap pc-settings-wrap">
            <h1>
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e('Premium Classifieds Settings', 'premium-classifieds'); ?>
            </h1>
            
            <?php $this->render_tabs(); ?>
            
            <form method="post" action="options.php" class="pc-settings-form">
                <?php
                settings_fields('pc_settings_' . $this->active_tab);
                
                switch ($this->active_tab) {
                    case 'stripe':
                        $this->render_stripe_settings();
                        break;
                    case 'pricing':
                        $this->render_pricing_settings();
                        break;
                    case 'features':
                        $this->render_feature_settings();
                        break;
                    case 'notifications':
                        $this->render_notification_settings();
                        break;
                    case 'general':
                        $this->render_general_settings();
                        break;
                }
                
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render settings tabs
     *
     * @since 2.1.0
     * @return void
     */
    private function render_tabs(): void {
        $tabs = [
            'stripe'        => [
                'title' => __('Stripe & Payments', 'premium-classifieds'),
                'icon'  => 'dashicons-money-alt',
            ],
            'pricing'       => [
                'title' => __('Pricing', 'premium-classifieds'),
                'icon'  => 'dashicons-tag',
            ],
            'features'      => [
                'title' => __('Features', 'premium-classifieds'),
                'icon'  => 'dashicons-admin-plugins',
            ],
            'notifications' => [
                'title' => __('Notifications', 'premium-classifieds'),
                'icon'  => 'dashicons-email',
            ],
            'general'       => [
                'title' => __('General', 'premium-classifieds'),
                'icon'  => 'dashicons-admin-generic',
            ],
        ];
        
        echo '<nav class="nav-tab-wrapper wp-clearfix">';
        foreach ($tabs as $tab => $config) {
            $active = $this->active_tab === $tab ? 'nav-tab-active' : '';
            $url = add_query_arg(['page' => self::PAGE_SLUG, 'tab' => $tab], admin_url('admin.php'));
            
            printf(
                '<a href="%s" class="nav-tab %s"><span class="dashicons %s"></span> %s</a>',
                esc_url($url),
                esc_attr($active),
                esc_attr($config['icon']),
                esc_html($config['title'])
            );
        }
        echo '</nav>';
    }
    
    /**
     * Register all settings
     *
     * @since 2.1.0
     * @return void
     */
    public function register_settings(): void {
        // Stripe settings
        register_setting('pc_settings_stripe', 'pc_stripe_mode');
        register_setting('pc_settings_stripe', 'pc_stripe_test_public_key');
        register_setting('pc_settings_stripe', 'pc_stripe_test_secret_key');
        register_setting('pc_settings_stripe', 'pc_stripe_live_public_key');
        register_setting('pc_settings_stripe', 'pc_stripe_live_secret_key');
        register_setting('pc_settings_stripe', 'pc_stripe_webhook_secret');
        
        // Pricing settings
        register_setting('pc_settings_pricing', 'pc_price_contact_reveal');
        register_setting('pc_settings_pricing', 'pc_price_listing_boost');
        register_setting('pc_settings_pricing', 'pc_price_subscription');
        register_setting('pc_settings_pricing', 'pc_currency');
        
        // Feature settings
        register_setting('pc_settings_features', 'pc_enable_messaging');
        register_setting('pc_settings_features', 'pc_enable_favorites');
        register_setting('pc_settings_features', 'pc_enable_subscriptions');
        register_setting('pc_settings_features', 'pc_require_approval');
        register_setting('pc_settings_features', 'pc_listing_duration_days');
        register_setting('pc_settings_features', 'pc_max_images_per_listing');
        
        // Notification settings
        register_setting('pc_settings_notifications', 'pc_notify_new_listing');
        register_setting('pc_settings_notifications', 'pc_notify_new_message');
        register_setting('pc_settings_notifications', 'pc_notify_payment_received');
        register_setting('pc_settings_notifications', 'pc_admin_email');
        
        // General settings
        register_setting('pc_settings_general', 'pc_listings_per_page');
        register_setting('pc_settings_general', 'pc_enable_recaptcha');
        register_setting('pc_settings_general', 'pc_recaptcha_site_key');
        register_setting('pc_settings_general', 'pc_recaptcha_secret_key');
    }
    
    /**
     * Render Stripe settings
     *
     * @since 2.1.0
     * @return void
     */
    private function render_stripe_settings(): void {
        $mode = get_option('pc_stripe_mode', 'test');
        $test_public = get_option('pc_stripe_test_public_key', '');
        $test_secret = get_option('pc_stripe_test_secret_key', '');
        $live_public = get_option('pc_stripe_live_public_key', '');
        $live_secret = get_option('pc_stripe_live_secret_key', '');
        $webhook_secret = get_option('pc_stripe_webhook_secret', '');
        
        $gateway = new PC_Stripe_Gateway();
        $webhook_url = PC_Webhook_Handler::get_webhook_url();
        ?>
        
        <div class="pc-settings-section">
            <h2><?php esc_html_e('Stripe Configuration', 'premium-classifieds'); ?></h2>
            <p class="description">
                <?php esc_html_e('Configure your Stripe API keys to accept payments. Get your keys from', 'premium-classifieds'); ?>
                <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a>
            </p>
            
            <!-- Mode Selection -->
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pc_stripe_mode"><?php esc_html_e('Stripe Mode', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <select name="pc_stripe_mode" id="pc_stripe_mode" class="regular-text">
                            <option value="test" <?php selected($mode, 'test'); ?>><?php esc_html_e('Test Mode', 'premium-classifieds'); ?></option>
                            <option value="live" <?php selected($mode, 'live'); ?>><?php esc_html_e('Live Mode', 'premium-classifieds'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Use test mode for development. Switch to live mode when ready to accept real payments.', 'premium-classifieds'); ?>
                        </p>
                        
                        <?php if ($mode === 'test'): ?>
                            <div class="notice notice-warning inline">
                                <p>
                                    <strong><?php esc_html_e('Test Mode Active', 'premium-classifieds'); ?></strong><br>
                                    <?php esc_html_e('No real payments will be processed. Use test card: 4242 4242 4242 4242', 'premium-classifieds'); ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="notice notice-success inline">
                                <p>
                                    <strong><?php esc_html_e('Live Mode Active', 'premium-classifieds'); ?></strong><br>
                                    <?php esc_html_e('Real payments are being processed.', 'premium-classifieds'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <!-- Test Mode Keys -->
            <h3><?php esc_html_e('Test Mode Keys', 'premium-classifieds'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pc_stripe_test_public_key"><?php esc_html_e('Publishable Key (Test)', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="pc_stripe_test_public_key" 
                               id="pc_stripe_test_public_key" 
                               value="<?php echo esc_attr($test_public); ?>" 
                               class="regular-text code"
                               placeholder="pk_test_...">
                        <p class="description"><?php esc_html_e('Starts with pk_test_', 'premium-classifieds'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pc_stripe_test_secret_key"><?php esc_html_e('Secret Key (Test)', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               name="pc_stripe_test_secret_key" 
                               id="pc_stripe_test_secret_key" 
                               value="<?php echo esc_attr($test_secret); ?>" 
                               class="regular-text code"
                               placeholder="sk_test_...">
                        <p class="description"><?php esc_html_e('Starts with sk_test_', 'premium-classifieds'); ?></p>
                    </td>
                </tr>
            </table>
            
            <!-- Live Mode Keys -->
            <h3><?php esc_html_e('Live Mode Keys', 'premium-classifieds'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pc_stripe_live_public_key"><?php esc_html_e('Publishable Key (Live)', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="pc_stripe_live_public_key" 
                               id="pc_stripe_live_public_key" 
                               value="<?php echo esc_attr($live_public); ?>" 
                               class="regular-text code"
                               placeholder="pk_live_...">
                        <p class="description"><?php esc_html_e('Starts with pk_live_', 'premium-classifieds'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pc_stripe_live_secret_key"><?php esc_html_e('Secret Key (Live)', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               name="pc_stripe_live_secret_key" 
                               id="pc_stripe_live_secret_key" 
                               value="<?php echo esc_attr($live_secret); ?>" 
                               class="regular-text code"
                               placeholder="sk_live_...">
                        <p class="description"><?php esc_html_e('Starts with sk_live_', 'premium-classifieds'); ?></p>
                    </td>
                </tr>
            </table>
            
            <!-- Webhook Configuration -->
            <h3><?php esc_html_e('Webhook Configuration', 'premium-classifieds'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Webhook URL', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               value="<?php echo esc_attr($webhook_url); ?>" 
                               class="large-text code" 
                               readonly>
                        <button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">
                            <?php esc_html_e('Copy URL', 'premium-classifieds'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Add this URL to your Stripe Dashboard → Webhooks. Select these events:', 'premium-classifieds'); ?>
                            <code>checkout.session.completed</code>,
                            <code>payment_intent.succeeded</code>,
                            <code>charge.refunded</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pc_stripe_webhook_secret"><?php esc_html_e('Webhook Signing Secret', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               name="pc_stripe_webhook_secret" 
                               id="pc_stripe_webhook_secret" 
                               value="<?php echo esc_attr($webhook_secret); ?>" 
                               class="regular-text code"
                               placeholder="whsec_...">
                        <p class="description"><?php esc_html_e('Get this from Stripe Dashboard after creating the webhook.', 'premium-classifieds'); ?></p>
                    </td>
                </tr>
            </table>
            
            <!-- Connection Test -->
            <div class="pc-connection-test">
                <h3><?php esc_html_e('Connection Test', 'premium-classifieds'); ?></h3>
                <?php if ($gateway->is_configured()): ?>
                    <div class="notice notice-success inline">
                        <p>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <strong><?php esc_html_e('Stripe is configured correctly!', 'premium-classifieds'); ?></strong>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-error inline">
                        <p>
                            <span class="dashicons dashicons-warning"></span>
                            <strong><?php esc_html_e('Stripe is not configured.', 'premium-classifieds'); ?></strong>
                            <?php esc_html_e('Please add your API keys above and save settings.', 'premium-classifieds'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render pricing settings
     *
     * @since 2.1.0
     * @return void
     */
    private function render_pricing_settings(): void {
        $contact_reveal = get_option('pc_price_contact_reveal', '5.00');
        $listing_boost = get_option('pc_price_listing_boost', '10.00');
        $subscription = get_option('pc_price_subscription', '49.00');
        $currency = get_option('pc_currency', 'USD');
        ?>
        
        <div class="pc-settings-section">
            <h2><?php esc_html_e('Pricing Configuration', 'premium-classifieds'); ?></h2>
            <p class="description">
                <?php esc_html_e('Set prices for different features. All prices are in the currency specified below.', 'premium-classifieds'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pc_currency"><?php esc_html_e('Currency', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <select name="pc_currency" id="pc_currency" class="regular-text">
                            <option value="USD" <?php selected($currency, 'USD'); ?>>USD - US Dollar ($)</option>
                            <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR - Euro (€)</option>
                            <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP - British Pound (£)</option>
                            <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD - Canadian Dollar (C$)</option>
                            <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD - Australian Dollar (A$)</option>
                            <option value="JPY" <?php selected($currency, 'JPY'); ?>>JPY - Japanese Yen (¥)</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pc_price_contact_reveal">
                            <?php esc_html_e('Contact Reveal Price', 'premium-classifieds'); ?>
                        </label>
                    </th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px; font-weight: 600;">$</span>
                            <input type="number" 
                                   name="pc_price_contact_reveal" 
                                   id="pc_price_contact_reveal" 
                                   value="<?php echo esc_attr($contact_reveal); ?>" 
                                   class="small-text" 
                                   step="0.01" 
                                   min="0">
                        </div>
                        <p class="description">
                            <?php esc_html_e('One-time payment to reveal phone number and email address.', 'premium-classifieds'); ?>
                            <?php esc_html_e('Buyers get lifetime access.', 'premium-classifieds'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pc_price_listing_boost">
                            <?php esc_html_e('Listing Boost Price', 'premium-classifieds'); ?>
                        </label>
                    </th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px; font-weight: 600;">$</span>
                            <input type="number" 
                                   name="pc_price_listing_boost" 
                                   id="pc_price_listing_boost" 
                                   value="<?php echo esc_attr($listing_boost); ?>" 
                                   class="small-text" 
                                   step="0.01" 
                                   min="0">
                        </div>
                        <p class="description">
                            <?php esc_html_e('Feature a listing at the top of search results for 30 days.', 'premium-classifieds'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pc_price_subscription">
                            <?php esc_html_e('Premium Subscription Price', 'premium-classifieds'); ?>
                        </label>
                    </th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px; font-weight: 600;">$</span>
                            <input type="number" 
                                   name="pc_price_subscription" 
                                   id="pc_price_subscription" 
                                   value="<?php echo esc_attr($subscription); ?>" 
                                   class="small-text" 
                                   step="0.01" 
                                   min="0">
                            <span><?php esc_html_e('/ month', 'premium-classifieds'); ?></span>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Monthly subscription for unlimited contact reveals.', 'premium-classifieds'); ?>
                            <strong><?php esc_html_e('(Coming soon)', 'premium-classifieds'); ?></strong>
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Pricing Preview -->
            <div class="pc-pricing-preview">
                <h3><?php esc_html_e('Pricing Preview', 'premium-classifieds'); ?></h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px;">
                    <div style="background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: 8px; padding: 20px; text-align: center;">
                        <h4 style="margin-top: 0;"><?php esc_html_e('Contact Reveal', 'premium-classifieds'); ?></h4>
                        <p style="font-size: 32px; font-weight: 700; color: #0ea5e9; margin: 10px 0;">
                            $<span id="preview_contact_reveal"><?php echo esc_html($contact_reveal); ?></span>
                        </p>
                        <p style="margin: 0; font-size: 14px; color: #64748b;"><?php esc_html_e('one-time', 'premium-classifieds'); ?></p>
                    </div>
                    <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 8px; padding: 20px; text-align: center;">
                        <h4 style="margin-top: 0;"><?php esc_html_e('Listing Boost', 'premium-classifieds'); ?></h4>
                        <p style="font-size: 32px; font-weight: 700; color: #f59e0b; margin: 10px 0;">
                            $<span id="preview_listing_boost"><?php echo esc_html($listing_boost); ?></span>
                        </p>
                        <p style="margin: 0; font-size: 14px; color: #64748b;"><?php esc_html_e('30 days', 'premium-classifieds'); ?></p>
                    </div>
                    <div style="background: #f0fdf4; border: 2px solid #10b981; border-radius: 8px; padding: 20px; text-align: center;">
                        <h4 style="margin-top: 0;"><?php esc_html_e('Subscription', 'premium-classifieds'); ?></h4>
                        <p style="font-size: 32px; font-weight: 700; color: #10b981; margin: 10px 0;">
                            $<span id="preview_subscription"><?php echo esc_html($subscription); ?></span>
                        </p>
                        <p style="margin: 0; font-size: 14px; color: #64748b;"><?php esc_html_e('per month', 'premium-classifieds'); ?></p>
                    </div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Live preview update
                $('#pc_price_contact_reveal').on('input', function() {
                    $('#preview_contact_reveal').text($(this).val());
                });
                $('#pc_price_listing_boost').on('input', function() {
                    $('#preview_listing_boost').text($(this).val());
                });
                $('#pc_price_subscription').on('input', function() {
                    $('#preview_subscription').text($(this).val());
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Render feature settings
     *
     * @since 2.1.0
     * @return void
     */
    private function render_feature_settings(): void {
        $enable_messaging = get_option('pc_enable_messaging', '1');
        $enable_favorites = get_option('pc_enable_favorites', '1');
        $enable_subscriptions = get_option('pc_enable_subscriptions', '0');
        $require_approval = get_option('pc_require_approval', '1');
        $listing_duration = get_option('pc_listing_duration_days', '30');
        $max_images = get_option('pc_max_images_per_listing', '10');
        ?>
        
        <div class="pc-settings-section">
            <h2><?php esc_html_e('Feature Settings', 'premium-classifieds'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Messaging System', 'premium-classifieds'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="pc_enable_messaging" 
                                   value="1" 
                                   <?php checked($enable_messaging, '1'); ?>>
                            <?php esc_html_e('Enable internal messaging between buyers and sellers', 'premium-classifieds'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Allows users to communicate after purchasing contact access.', 'premium-classifieds'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Favorites System', 'premium-classifieds'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="pc_enable_favorites" 
                                   value="1" 
                                   <?php checked($enable_favorites, '1'); ?>>
                            <?php esc_html_e('Allow users to save listings to favorites', 'premium-classifieds'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Subscriptions', 'premium-classifieds'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="pc_enable_subscriptions" 
                                   value="1" 
                                   <?php checked($enable_subscriptions, '1'); ?>>
                            <?php esc_html_e('Enable premium subscriptions (unlimited reveals)', 'premium-classifieds'); ?>
                        </label>
                        <p class="description">
                            <strong><?php esc_html_e('Coming in future version', 'premium-classifieds'); ?></strong>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Listing Moderation', 'premium-classifieds'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="pc_require_approval" 
                                   value="1" 
                                   <?php checked($require_approval, '1'); ?>>
                            <?php esc_html_e('Require admin approval before listings are published', 'premium-classifieds'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('New listings will be set to "Pending Review" status.', 'premium-classifieds'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pc_listing_duration_days"><?php esc_html_e('Listing Duration', 'premium-classifieds'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="pc_listing_duration_days" 
                               id="pc_listing_duration_days" 
                               value="<?php echo esc_attr($listing_duration); ?>" 
                               class="small-text" 
                               min="1">
                        <span><?php esc_html_e('days', 'premium-classifieds'); ?></span>
                        <p class="description">
                            <?php esc_html_e('Listings will automatically expire after