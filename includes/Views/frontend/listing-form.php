<?php
// File: includes/Views/frontend/listing-form.php
if ( ! defined( 'ABSPATH' ) ) exit;
$user = wp_get_current_user();
$listing = isset( $listing ) ? $listing : null;
$listing_id = $listing['ID'] ?? 0;
?>
<form class="pc-listing-form" method="post" data-after-success="reload">
    <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
    <input type="hidden" name="listing_id" value="<?php echo esc_attr( $listing_id ); ?>">

    <p>
        <label><?php _e( 'Tytuł', 'premium-classifieds' ); ?></label><br>
        <input type="text" name="title" value="<?php echo esc_attr( $listing['title'] ?? '' ); ?>" required>
    </p>

    <p>
        <label><?php _e( 'Treść', 'premium-classifieds' ); ?></label><br>
        <textarea name="content" rows="6"><?php echo esc_textarea( $listing['content'] ?? '' ); ?></textarea>
    </p>

    <p>
        <label><?php _e( 'Email kontaktowy', 'premium-classifieds' ); ?></label><br>
        <input type="email" name="contact_email" value="<?php echo esc_attr( $listing['contact_email'] ?? '' ); ?>">
    </p>

    <p>
        <label><?php _e( 'Telefon', 'premium-classifieds' ); ?></label><br>
        <input type="text" name="contact_phone" value="<?php echo esc_attr( $listing['contact_phone'] ?? '' ); ?>">
    </p>

    <p>
        <label><?php _e( 'Galeria', 'premium-classifieds' ); ?></label><br>
        <div class="pc-dropzone" style="border:1px dashed #ccc;padding:8px;">
            <input type="file" class="pc-upload-input" multiple>
            <div class="pc-gallery"></div>
            <small><?php _e( 'Przeciągnij aby dodać zdjęcia', 'premium-classifieds' ); ?></small>
        </div>
    </p>

    <p>
        <button type="submit"><?php _e( 'Zapisz', 'premium-classifieds' ); ?></button>
    </p>
</form>
