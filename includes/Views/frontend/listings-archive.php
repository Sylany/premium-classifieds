<?php
// File: includes/Views/frontend/listings-archive.php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="pc-listings-archive">
    <?php if ( empty( $listings ) ) : ?>
        <p><?php _e( 'Brak ogłoszeń', 'premium-classifieds' ); ?></p>
    <?php else: ?>
        <?php foreach ( $listings as $l ) : ?>
            <div class="pc-listing-card">
                <h3><a href="<?php echo get_permalink( $l['ID'] ); ?>"><?php echo esc_html( $l['title'] ); ?></a></h3>
                <div class="pc-listing-excerpt"><?php echo wp_kses_post( wp_trim_words( $l['content'], 30 ) ); ?></div>
                <p><a href="<?php echo esc_url( get_permalink( $l['ID'] ) ); ?>"><?php _e( 'Zobacz', 'premium-classifieds' ); ?></a></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
