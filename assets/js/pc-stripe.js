// File: assets/js/pc-stripe.js
(function(window, document, $){
    'use strict';

    if (typeof Stripe === 'undefined') {
        console.warn('Stripe.js not loaded.');
        return;
    }

    var pc = window.pc_frontend || {};
    var stripeKey = pc.stripe_key || '';
    var ajaxUrl = pc.ajax_url || '/wp-admin/admin-ajax.php';
    var nonce = pc.nonce || '';

    var stripe = Stripe(stripeKey);

    // Initialize elements when .pc-stripe-form exists
    $(function(){
        $('.pc-stripe-form').each(function(){
            var $form = $(this);
            var intentContainer = $form.find('.pc-stripe-intent');
            var cardElementDiv = $form.find('.pc-card-element')[0];

            var elements = stripe.elements();
            var style = {
                base: {
                    color: "#32325d",
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: "antialiased",
                    fontSize: "16px",
                    "::placeholder": { color: "#a0aec0" }
                },
                invalid: { color: "#e53e3e", iconColor: "#e53e3e" }
            };

            var card = elements.create('card', { style: style });
            card.mount(cardElementDiv);

            $form.on('submit', function(e){
                e.preventDefault();
                var purpose = $form.data('purpose') || 'reveal_contact';
                var listing_id = $form.data('listing') || $form.find('input[name="listing_id"]').val();

                // Create PaymentIntent via AJAX
                $.post(ajaxUrl, {
                    action: 'pc_create_payment_intent',
                    nonce: nonce,
                    purpose: purpose,
                    listing_id: listing_id
                }).done(function(res){
                    if (res.success) {
                        var clientSecret = res.data.client_secret;
                        stripe.confirmCardPayment(clientSecret, {
                            payment_method: { card: card }
                        }).then(function(result){
                            if (result.error) {
                                alert(result.error.message || 'Payment failed');
                            } else if ( result.paymentIntent && result.paymentIntent.status === 'succeeded' ) {
                                // reload or call callback
                                if ($form.data('after-success') === 'reload') {
                                    location.reload();
                                } else {
                                    $form.trigger('pc_payment_success', [ result.paymentIntent ]);
                                }
                            }
                        });
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Error creating payment');
                    }
                }).fail(function(){ alert('Network error'); });
            });
        });
    });
})(window, document, jQuery);
