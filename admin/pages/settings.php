<?php
if (!defined('ABSPATH')) exit;

// Import klasy Helpers z poprawnym namespace
use PremiumClassifieds\Core\Helpers;

// Obsługa zapisu ustawień
if (!empty($_POST) && check_admin_referer('pc_save_settings', 'pc_nonce')) {

    update_option('pc_contact_price', sanitize_text_field($_POST['pc_contact_price'] ?? '5'));
    update_option('pc_currency', sanitize_text_field($_POST['pc_currency'] ?? 'USD'));
    update_option('pc_stripe_public', sanitize_text_field($_POST['pc_stripe_public'] ?? ''));
    update_option('pc_stripe_secret', sanitize_text_field($_POST['pc_stripe_secret'] ?? ''));

    echo '<div class="updated"><p>Ustawienia zapisane.</p></div>';
}

$price      = get_option('pc_contact_price', '5');
$currency   = get_option('pc_currency', 'USD');
$stripe_pub = get_option('pc_stripe_public', '');
$stripe_sec = get_option('pc_stripe_secret', '');

echo '<div class="wrap">';
echo '<h1>Premium Classifieds – Ustawienia</h1>';

echo '<form method="POST" action="">';
wp_nonce_field('pc_save_settings', 'pc_nonce');

echo '<table class="form-table">';

// cena kontaktu
echo '<tr><th><label for="pc_contact_price">Cena kontaktu</label></th>';
echo '<td><input type="number" name="pc_contact_price" id="pc_contact_price" min="0" step="0.01" value="' . esc_attr($price) . '"> ';
echo '<span>' . esc_html($currency) . '</span></td></tr>';

// waluta
echo '<tr><th><label for="pc_currency">Waluta</label></th>';
echo '<td><input type="text" name="pc_currency" id="pc_currency" value="' . esc_attr($currency) . '" maxlength="3"></td></tr>';

// Stripe public key
echo '<tr><th><label for="pc_stripe_public">Stripe Public Key</label></th>';
echo '<td><input type="text" name="pc_stripe_public" id="pc_stripe_public" value="' . esc_attr($stripe_pub) . '" class="regular-text"></td></tr>';

// Stripe secret key
echo '<tr><th><label for="pc_stripe_secret">Stripe Secret Key</label></th>';
echo '<td><input type="text" name="pc_stripe_secret" id="pc_stripe_secret" value="' . esc_attr($stripe_sec) . '" class="regular-text"></td></tr>';

echo '</table>';

// Save button
submit_button('Zapisz ustawienia');

echo '</form>';
echo '</div>';
