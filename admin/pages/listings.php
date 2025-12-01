<?php
if ( ! defined( 'ABSPATH' ) ) exit;

echo '<div class="wrap">';
echo '<h1>Premium Classifieds – Ogłoszenia</h1>';

global $wpdb;

$table = $wpdb->prefix . 'pc_listings';

$listings = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200");

echo '<table class="widefat fixed striped">';
echo '<thead>
    <tr>
        <th>ID</th>
        <th>Tytuł</th>
        <th>Autor</th>
        <th>Status</th>
        <th>Data</th>
    </tr>
</thead>';

echo '<tbody>';

if (!empty($listings)) {
    foreach ($listings as $row) {
        echo '<tr>';
        echo '<td>' . intval($row->id) . '</td>';
        echo '<td>' . esc_html($row->title) . '</td>';
        echo '<td>' . esc_html(get_the_author_meta('display_name', $row->user_id)) . '</td>';
        echo '<td>' . esc_html($row->status) . '</td>';
        echo '<td>' . esc_html($row->created_at) . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="5">Brak ogłoszeń.</td></tr>';
}

echo '</tbody>';
echo '</table>';

echo '</div>';
