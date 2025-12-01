<?php
// File: includes/Views/frontend/single-listing.php
defined( 'ABSPATH' ) || exit;

use Premium\Classifieds\Core\Helpers;
use Premium\Classifieds\Entities\Listing;

global $post;
if ( ! $post || $post->post_type !== Listing::POST_TYPE ) {
    echo '<div class="pc-single-listing-notfound">' . esc_html__( 'Ogłoszenie nie istnieje.', 'premium-classifieds' ) . '</div>';
    return;
}

$listing_id = $post->ID;
$title = get_the_title( $post );
$content = apply_filters( 'the_content', $post->post_content );
$excerpt = get_the_excerpt( $post );
$author_id = $post->post_author;
$author_name = get_the_author_meta( 'display_name', $author_id );
$thumb = get_the_post_thumbnail_url( $post, 'large' );
$gallery_ids = []; // optional: fetch attachments related to this listing (by gallery meta or attachments)
$contact_available = Listing::get_contact_if_paid( $listing_id, get_current_user_id() );
$price = floatval( Helpers::get_option( 'pc_price_reveal_contact', 19.00 ) );
$currency = Helpers::get_option( 'pc_currency', 'PLN' );
?>
<article class="pc-single-listing">
    <header class="pc-single-header">
        <h1 class="pc-single-title"><?php echo esc_html( $title ); ?></h1>
        <div class="pc-single-author"><?php echo esc_html( $author_name ); ?></div>
    </header>

    <div class="pc-single-grid">
        <div class="pc-single-media">
            <?php if ( $thumb ) : ?>
                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="pc-single-main-thumb" />
            <?php else : ?>
                <div class="pc-placeholder-thumb"><?php esc_html_e( 'Brak zdjęcia', 'premium-classifieds' ); ?></div>
            <?php endif; ?>

            <!-- gallery previews (if any attachments) -->
            <div class="pc-single-gallery">
                <?php
                $attachments = get_attached_media( 'image', $listing_id );
                if ( ! empty( $attachments ) ) {
                    foreach ( $attachments as $att ) {
                        $url = wp_get_attachment_image_url( $att->ID, 'thumbnail' );
                        echo '<img class="pc-thumb" src="' . esc_url( $url ) . '" alt="" />';
                    }
                }
                ?>
            </div>
        </div>

        <div class="pc-single-body">
            <div class="pc-single-content"><?php echo wp_kses_post( $content ); ?></div>

            <div class="pc-single-contact">
                <?php if ( $contact_available ) : 
                    $contact = Listing::get_contact_if_paid( $listing_id, get_current_user_id() );
                ?>
                    <div class="pc-contact-details">
                        <?php if ( ! empty( $contact['phone'] ) ) : ?>
                            <div class="pc-contact-phone"><?php echo esc_html( $contact['phone'] ); ?></div>
                        <?php endif; ?>
                        <?php if ( ! empty( $contact['email'] ) ) : ?>
                            <div class="pc-contact-email"><a href="mailto:<?php echo esc_attr( $contact['email'] ); ?>"><?php echo esc_html( $contact['email'] ); ?></a></div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="pc-contact-cta">
                        <p><?php esc_html_e( 'Aby zobaczyć dane kontaktowe, zakup dostęp.', 'premium-classifieds' ); ?></p>
                        <?php if ( is_user_logged_in() ) : ?>
                            <button class="pc-btn pc-btn-primary pc-buy-contact" 
                                data-listing="<?php echo esc_attr( $listing_id ); ?>" 
                                data-purpose="reveal_contact"
                                data-price="<?php echo esc_attr( $price ); ?>"
                                data-currency="<?php echo esc_attr( $currency ); ?>">
                                <?php printf( esc_html__( 'Kup dostęp — %1$.2f %2$s', 'premium-classifieds' ), $price, $currency ); ?>
                            </button>
                        <?php else : ?>
                            <p><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Zaloguj się', 'premium-classifieds' ); ?></a> <?php esc_html_e( 'aby kupić dostęp.', 'premium-classifieds' ); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pc-single-actions">
                <a href="#pc-send-message" class="pc-btn"><?php esc_html_e( 'Napisz wiadomość', 'premium-classifieds' ); ?></a>
            </div>
        </div>
    </div>

    <section id="pc-send-message" class="pc-single-message">
        <h3><?php esc_html_e( 'Wyślij wiadomość', 'premium-classifieds' ); ?></h3>
        <form id="pc-single-message-form" class="pc-form">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'pc_frontend_nonce' ) ); ?>" />
            <input type="hidden" name="listing_id" value="<?php echo esc_attr( $listing_id ); ?>" />
            <input type="hidden" name="to_user" value="<?php echo esc_attr( $author_id ); ?>" />
            <div class="pc-field">
                <label for="pc-single-message-body"><?php esc_html_e( 'Wiadomość', 'premium-classifieds' ); ?></label>
                <textarea id="pc-single-message-body" name="body" rows="4" required></textarea>
            </div>
            <button type="button" class="pc-btn pc-btn-primary" id="pc-single-send-message"><?php esc_html_e( 'Wyślij', 'premium-classifieds' ); ?></button>
        </form>
    </section>
</article>

<!-- Minimal JS handlers for buy button and message (depends on pc-frontend.js) -->
<script>
jQuery(function($){
    $(document).on('click', '.pc-buy-contact', function(e){
        e.preventDefault();
        var $btn = $(this);
        var listing = $btn.data('listing');
        var purpose = $btn.data('purpose');
        var nonce = '<?php echo esc_js( wp_create_nonce( 'pc_frontend_nonce' ) ); ?>';
        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Łączenie z płatnością...', 'premium-classifieds' ) ); ?>');

        $.post(ajaxurl, {
            action: 'pc_create_payment_intent',
            nonce: nonce,
            purpose: purpose,
            listing_id: listing
        }).done(function(res){
            if (res.success) {
                // Use Stripe.js on front-end to confirm card payment; client_secret returned
                if (typeof Stripe === 'undefined') {
                    alert('<?php echo esc_js( __( 'Stripe.js is not loaded — please include Stripe.js in your theme.', 'premium-classifieds' ) ); ?>');
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Kup dostęp', 'premium-classifieds' ) ); ?>');
                    return;
                }
                var stripe = Stripe('<?php echo esc_js( Helpers::get_option( 'pc_stripe_publishable_key', '' ) ); ?>');
                stripe.confirmCardPayment(res.data.client_secret, {
                    payment_method: {
                        card: { /* card element should be provided in production */ }
                    }
                }).then(function(result){
                    if (result.error) {
                        alert(result.error.message || '<?php echo esc_js( __( 'Błąd płatności', 'premium-classifieds' ) ); ?>');
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Kup dostęp', 'premium-classifieds' ) ); ?>');
                    } else if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                        // refresh page to reveal contact (webhook will have processed)
                        location.reload();
                    }
                });
            } else {
                alert( res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Błąd tworzenia płatności', 'premium-classifieds' ) ); ?>' );
                $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Kup dostęp', 'premium-classifieds' ) ); ?>');
            }
        }).fail(function(){ alert('<?php echo esc_js( __( 'Błąd sieci', 'premium-classifieds' ) ); ?>'); $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Kup dostęp', 'premium-classifieds' ) ); ?>'); });
    });

    $(document).on('click', '#pc-single-send-message', function(){
        var $form = $('#pc-single-message-form');
        var data = {
            action: 'pc_send_message',
            nonce: $('input[name="nonce"]', $form).val(),
            listing_id: $('input[name="listing_id"]', $form).val(),
            to_user: $('input[name="to_user"]', $form).val(),
            body: $('#pc-single-message-body').val()
        };
        $.post(ajaxurl, data).done(function(res){
            if (res.success) {
                alert('<?php echo esc_js( __( 'Wysłano', 'premium-classifieds' ) ); ?>');
                $('#pc-single-message-form')[0].reset();
            } else {
                alert( res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Błąd', 'premium-classifieds' ) ); ?>' );
            }
        }).fail(function(){ alert('<?php echo esc_js( __( 'Błąd sieci', 'premium-classifieds' ) ); ?>'); });
    });
});
</script>
