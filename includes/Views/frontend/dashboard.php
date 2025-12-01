<?php
// File: includes/Views/frontend/dashboard.php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="pc-dashboard">
    <h2><?php _e( 'Panel użytkownika', 'premium-classifieds' ); ?></h2>

    <div class="pc-dashboard-grid">
        <div class="pc-card">
            <h3><?php _e( 'Moje ogłoszenia', 'premium-classifieds' ); ?></h3>
            <?php echo do_shortcode( '[png_my_listings]' ); ?>
        </div>

        <div class="pc-card">
            <h3><?php _e( 'Dodaj ogłoszenie', 'premium-classifieds' ); ?></h3>
            <?php echo do_shortcode( '[png_listing_form]' ); ?>
        </div>
    </div>
</div>
