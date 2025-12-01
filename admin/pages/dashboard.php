<?php
if ( ! defined( 'ABSPATH' ) ) exit;

echo '<div class="wrap">';
echo '<h1>Premium Classifieds – Dashboard</h1>';

echo '<p>Witamy w panelu administracyjnym Premium Classifieds.</p>';

echo '<hr>';

echo '<h2>Statystyki</h2>';

global $wpdb;

$listings_table = $wpdb->prefix . 'pc_listings';
$messages_table = $wpdb->prefix . 'pc_messages';
$transactions_table = $wpdb->prefix . 'pc_transactions';

$total_listings      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $listings_table" );
$total_messages      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $messages_table" );
$total_transactions  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $transactions_table" );

echo '<ul>';
echo '<li><strong>Ogłoszenia:</strong> ' . $total_listings . '</li>';
echo '<li><strong>Wiadomości:</strong> ' . $total_messages . '</li>';
echo '<li><strong>Transakcje:</strong> ' . $total_transactions . '</li>';
echo '</ul>';

echo '</div>';
