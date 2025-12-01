<?php
// File: includes/Views/frontend/user-profile.php
defined( 'ABSPATH' ) || exit;

$user = get_userdata( $user_id );
if ( ! $user ) {
    echo '<div class="pc-user-profile">' . esc_html__( 'Użytkownik nie znaleziony', 'premium-classifieds' ) . '</div>';
    return;
}

// get user's listings
$args = [
    'post_type' => \Premium\Classifieds\Entities\Listing::POST_TYPE,
    'author' => $user->ID,
    'post_status' => 'publish',
    'posts_per_page' => 12,
];
$q = new WP_Query( $args );
?>
<div class="pc-user-profile">
    <div class="pc-user-header">
        <h2><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></h2>
        <p class="pc-user-bio"><?php echo esc_html( get_user_meta( $user->ID, 'description', true ) ); ?></p>
    </div>

    <div class="pc-user-listings">
        <h3><?php esc_html_e( 'Ogłoszenia użytkownika', 'premium-classifieds' ); ?></h3>
        <div class="pc-listings-grid">
            <?php
            if ( $q->have_posts() ) {
                while ( $q->have_posts() ) {
                    $q->the_post();
                    include __DIR__ . '/listing-card.php';
                }
            } else {
                echo '<p>' . esc_html__( 'Użytkownik nie ma jeszcze ogłoszeń.', 'premium-classifieds' ) . '</p>';
            }
            wp_reset_postdata();
            ?>
        </div>
    </div>
</div>
