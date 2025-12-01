<?php
// File: admin/pages/transactions.php (updated)
defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
global $wpdb;

$table = $wpdb->prefix . 'pc_transactions';
$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200", ARRAY_A );
?>
<div class="wrap pc-admin-wrap">
    <h1><?php esc_html_e( 'Transakcje', 'premium-classifieds' ); ?></h1>

    <h2><?php esc_html_e( 'Przychody (ostatnie 12 mies.)', 'premium-classifieds' ); ?></h2>
    <canvas id="pc-earnings-chart" width="800" height="250"></canvas>

    <hr />

    <table class="widefat fixed striped" style="margin-top:20px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'premium-classifieds' ); ?></th>
                <th><?php esc_html_e( 'Użytkownik', 'premium-classifieds' ); ?></th>
                <th><?php esc_html_e( 'Listing', 'premium-classifieds' ); ?></th>
                <th><?php esc_html_e( 'Kwota', 'premium-classifieds' ); ?></th>
                <th><?php esc_html_e( 'Provider', 'premium-classifieds' ); ?></th>
                <th><?php esc_html_e( 'Status', 'premium-classifieds' ); ?></th>
                <th><?php esc_html_e( 'Data', 'premium-classifieds' ); ?></th>
                <th><?php esc_html_e( 'Akcje', 'premium-classifieds' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $rows ) : foreach ( $rows as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( $r['id'] ); ?></td>
                    <td><?php echo esc_html( $r['user_id'] ); ?></td>
                    <td><?php echo esc_html( $r['listing_id'] ); ?></td>
                    <td><?php echo esc_html( $r['amount'] . ' ' . $r['currency'] ); ?></td>
                    <td><?php echo esc_html( $r['provider'] ); ?></td>
                    <td><?php echo esc_html( $r['status'] ); ?></td>
                    <td><?php echo esc_html( $r['created_at'] ); ?></td>
                    <td>
                        <button class="button pc-delete-transaction" data-id="<?php echo esc_attr( $r['id'] ); ?>"><?php esc_html_e( 'Usuń', 'premium-classifieds' ); ?></button>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8"><?php esc_html_e( 'Brak transakcji', 'premium-classifieds' ); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script type="text/javascript">
jQuery(function($){
    $(document).on('click', '.pc-delete-transaction', function(e){
        e.preventDefault();
        if (!confirm('<?php echo esc_js( __( 'Czy na pewno chcesz usunąć tę transakcję?', 'premium-classifieds' ) ); ?>')) return;
        var id = $(this).data('id');
        $.post(ajaxurl, { action: 'pc_admin_delete_transaction', id: id, nonce: pc_admin.nonce }).done(function(res){
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Error');
            }
        }).fail(function(){ alert('Network error'); });
    });
});
</script>
