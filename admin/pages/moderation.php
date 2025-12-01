<?php
// File: admin/pages/moderation.php
defined( 'ABSPATH' ) || exit;

// list recent pending listings
$args = [
    'post_type' => 'pc_listing',
    'post_status' => 'pending',
    'posts_per_page' => 50,
];
$q = new WP_Query( $args );
?>
<div class="wrap pc-admin-wrap">
    <h1><?php esc_html_e( 'Moderacja ogłoszeń', 'premium-classifieds' ); ?></h1>

    <?php if ( $q->have_posts() ) : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'premium-classifieds' ); ?></th>
                    <th><?php esc_html_e( 'Tytuł', 'premium-classifieds' ); ?></th>
                    <th><?php esc_html_e( 'Autor', 'premium-classifieds' ); ?></th>
                    <th><?php esc_html_e( 'Data', 'premium-classifieds' ); ?></th>
                    <th><?php esc_html_e( 'Akcje', 'premium-classifieds' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php while ( $q->have_posts() ): $q->the_post(); ?>
                    <tr>
                        <td><?php the_ID(); ?></td>
                        <td><?php the_title(); ?></td>
                        <td><?php echo get_the_author_meta( 'display_name', get_post_field( 'post_author', get_the_ID() ) ); ?></td>
                        <td><?php echo get_the_date(); ?></td>
                        <td>
                            <a class="button" href="<?php echo esc_url( get_edit_post_link( get_the_ID() ) ); ?>"><?php esc_html_e( 'Edytuj', 'premium-classifieds' ); ?></a>
                            <button class="button pc-moderate" data-id="<?php echo esc_attr( get_the_ID() ); ?>" data-act="accept"><?php esc_html_e( 'Akceptuj', 'premium-classifieds' ); ?></button>
                            <button class="button pc-moderate" data-id="<?php echo esc_attr( get_the_ID() ); ?>" data-act="reject"><?php esc_html_e( 'Odrzuć', 'premium-classifieds' ); ?></button>
                        </td>
                    </tr>
                <?php endwhile; wp_reset_postdata(); ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><?php esc_html_e( 'Brak oczekujących ogłoszeń.', 'premium-classifieds' ); ?></p>
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(function($){
    $(document).on('click', '.pc-moderate', function(e){
        e.preventDefault();
        var id = $(this).data('id');
        var act = $(this).data('act');
        if (!confirm('<?php echo esc_js( __( 'Potwierdź akcję', 'premium-classifieds' ) ); ?>')) return;
        $.post(ajaxurl, { action: 'pc_admin_moderate_listing', listing_id: id, act: act, nonce: pc_admin.nonce }).done(function(res){
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Error');
            }
        }).fail(function(){ alert('Network error'); });
    });
});
</script>
