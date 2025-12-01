<?php
// File: includes/Views/frontend/profile-edit.php
defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;

if ( ! is_user_logged_in() ) {
    echo '<div class="pc-profile-edit-locked">' . esc_html__( 'Zaloguj się, aby edytować profil.', 'premium-classifieds' ) . '</div>';
    return;
}

$user = wp_get_current_user();
?>
<div class="pc-profile-edit">
    <h2><?php esc_html_e( 'Edytuj profil', 'premium-classifieds' ); ?></h2>
    <form id="pc-profile-edit-form" method="post">
        <?php echo wp_nonce_field( 'pc_profile_edit', '_pc_profile_edit_nonce', true, false ); ?>
        <div class="pc-field">
            <label for="pc-display-name"><?php esc_html_e( 'Nazwa wyświetlana', 'premium-classifieds' ); ?></label>
            <input id="pc-display-name" name="display_name" type="text" value="<?php echo esc_attr( $user->display_name ); ?>" />
        </div>

        <div class="pc-field">
            <label for="pc-bio"><?php esc_html_e( 'O mnie', 'premium-classifieds' ); ?></label>
            <textarea id="pc-bio" name="description" rows="5"><?php echo esc_textarea( get_user_meta( $user->ID, 'description', true ) ); ?></textarea>
        </div>

        <div class="pc-field">
            <label for="pc-contact-email"><?php esc_html_e( 'E-mail kontaktowy', 'premium-classifieds' ); ?></label>
            <input id="pc-contact-email" name="contact_email" type="email" value="<?php echo esc_attr( $user->user_email ); ?>" />
        </div>

        <div class="pc-actions">
            <button type="button" id="pc-profile-save" class="pc-btn pc-btn-primary"><?php esc_html_e( 'Zapisz profil', 'premium-classifieds' ); ?></button>
        </div>
    </form>
</div>

<script>
jQuery(function($){
    $(document).on('click', '#pc-profile-save', function(e){
        e.preventDefault();
        var $form = $('#pc-profile-edit-form');
        var data = {
            action: 'pc_update_profile',
            nonce: '<?php echo esc_js( wp_create_nonce( 'pc_frontend_nonce' ) ); ?>',
            display_name: $('#pc-display-name').val(),
            description: $('#pc-bio').val(),
            contact_email: $('#pc-contact-email').val()
        };
        $.post(ajaxurl, data).done(function(res){
            if (res.success) {
                alert('<?php echo esc_js( __( 'Profil zaktualizowany', 'premium-classifieds' ) ); ?>');
            } else {
                alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Błąd', 'premium-classifieds' ) ); ?>');
            }
        }).fail(function(){ alert('<?php echo esc_js( __( 'Błąd sieci', 'premium-classifieds' ) ); ?>'); });
    });
});
</script>
