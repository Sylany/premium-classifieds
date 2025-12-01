<?php
// File: includes/Views/frontend/listing-card.php
defined( 'ABSPATH' ) || exit;

global $post;
$listing_id = get_the_ID();
$title = get_the_title();
$excerpt = get_the_excerpt();
$thumb = get_the_post_thumbnail_url( $post, 'medium' );
$author_id = $post->post_author;
$author_name = get_the_author_meta( 'display_name', $author_id );
$permalink = get_permalink( $post );
?>
<article id="post-<?php echo esc_attr( $listing_id ); ?>" class="pc-listing-card">
    <a class="pc-listing-link" href="<?php echo esc_url( $permalink ); ?>">
        <div class="pc-listing-thumb">
            <?php if ( $thumb ) : ?>
                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
            <?php else : ?>
                <div class="pc-placeholder-thumb"><?php esc_html_e( 'No image', 'premium-classifieds' ); ?></div>
            <?php endif; ?>
        </div>
        <div class="pc-listing-body">
            <h3 class="pc-listing-title"><?php echo esc_html( $title ); ?></h3>
            <div class="pc-listing-excerpt"><?php echo wp_kses_post( wp_trim_words( $excerpt, 20, '...' ) ); ?></div>
            <div class="pc-listing-meta">
                <span class="pc-listing-author"><?php echo esc_html( $author_name ); ?></span>
            </div>
        </div>
    </a>
    <div class="pc-listing-cta">
        <a href="<?php echo esc_url( $permalink ); ?>" class="pc-btn pc-btn-primary"><?php esc_html_e( 'Wyświetl profil', 'premium-classifieds' ); ?></a>
    </div>
</article>
