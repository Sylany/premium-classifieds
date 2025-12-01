<?php
// File: includes/Views/frontend/search-form.php
defined( 'ABSPATH' ) || exit;

$show_filters = isset( $show_filters ) ? $show_filters : 'yes';
$categories = get_terms( [ 'taxonomy' => 'pc_listing_category', 'hide_empty' => true ] );
?>
<form id="pc-search-form" class="pc-search-form" method="get" action="<?php echo esc_url( get_post_type_archive_link( \Premium\Classifieds\Entities\Listing::POST_TYPE ) ); ?>">
    <div class="pc-field">
        <input type="text" name="s" placeholder="<?php esc_attr_e( 'Szukaj...', 'premium-classifieds' ); ?>" value="<?php echo esc_attr( $_GET['s'] ?? '' ); ?>" />
    </div>

    <?php if ( $show_filters === 'yes' ) : ?>
    <div class="pc-field">
        <select name="category">
            <option value=""><?php esc_html_e( 'Wszystkie kategorie', 'premium-classifieds' ); ?></option>
            <?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
                <?php foreach ( $categories as $c ) : ?>
                    <option value="<?php echo esc_attr( $c->slug ); ?>" <?php selected( $_GET['category'] ?? '', $c->slug ); ?>><?php echo esc_html( $c->name ); ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="pc-actions">
        <button type="submit" class="pc-btn pc-btn-primary"><?php esc_html_e( 'Szukaj', 'premium-classifieds' ); ?></button>
    </div>
</form>
