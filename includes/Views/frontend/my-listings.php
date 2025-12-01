<?php
// File: includes/Views/frontend/my-listings.php
if ( ! defined( 'ABSPATH' ) ) exit;
global $query;
$query = $query ?? null;
$posts = $query ? $query->posts : [];
?>
<div class="pc-my-listings">
    <?php if ( empty( $posts ) ) : ?>
        <p><?php _e( 'Nie masz jeszcze ogłoszeń.', 'premium-classifieds' ); ?></p>
    <?php else: ?>
        <?php foreach ( $posts as $p ) : ?>
            <div class="pc-my-listing-card" id="pc-listing-<?php echo esc_attr( $p->ID ); ?>">
                <h4><?php echo esc_html( get_the_title( $p ) ); ?></h4>
                <p><?php echo wp_kses_post( wp_trim_words( $p->post_content, 20 ) ); ?></p>
                <p>
                    <a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php _e( 'Edytuj', 'premium-classifieds' ); ?></a>
                    <button class="pc-delete-listing" data-listing="<?php echo esc_attr( $p->ID ); ?>" data-confirm="<?php _e( 'Usuń ogłoszenie?', 'premium-classifieds' ); ?>"><?php _e( 'Usuń', 'premium-classifieds' ); ?></button>
                </p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
