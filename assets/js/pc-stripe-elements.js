// File: assets/js/pc-stripe-elements.js
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

    $(function(){
        // initialize card elements for each .pc-stripe-form
        $('.pc-stripe-form').each(function(){
            var $form = $(this);
            var cardDiv = $form.find('.pc-card-element')[0];
            if (!cardDiv) return;

            var elements = stripe.elements();
            var style = {
                base: { color: "#32325d", fontSize: "16px", '::placeholder': { color: '#a0aec0' } },
                invalid: { color: "#e53e3e" }
            };
            var card = elements.create('card', { style: style });
            card.mount(cardDiv);

            var $submit = $form.find('button[type="submit"]');

            $form.on('submit', function(e){
                e.preventDefault();
                $submit.prop('disabled', true).text( $submit.data('processing-text') || 'Proszę czekać…' );

                // prepare data for payment intent
                var purpose = $form.data('purpose') || 'reveal_contact';
                var listing_id = $form.data('listing') || $form.find('input[name="listing_id"]').val() || '';
                var message_id = $form.data('message') || '';

                $.post(ajaxUrl, {
                    action: 'pc_create_payment_intent',
                    nonce: nonce,
                    purpose: purpose,
                    listing_id: listing_id,
                    message_id: message_id
                }).done(function(res){
                    if (!res.success) {
                        alert( (res.data && res.data.message) ? res.data.message : 'Error creating payment' );
                        $submit.prop('disabled', false).text( $form.data('submit-text') || 'Kup' );
                        return;
                    }
                    var clientSecret = res.data.client_secret;
                    stripe.confirmCardPayment(clientSecret, {
                        payment_method: { card: card }
                    }).then(function(result){
                        if (result.error) {
                            alert(result.error.message || 'Payment failed');
                            $submit.prop('disabled', false).text( $form.data('submit-text') || 'Kup' );
                        } else if ( result.paymentIntent && result.paymentIntent.status === 'succeeded' ) {
                            // success — refresh or trigger event
                            if ($form.data('after-success') === 'reload') {
                                location.reload();
                            } else {
                                $form.trigger('pc_payment_success', [ result.paymentIntent ]);
                                $submit.prop('disabled', false).text( $form.data('submit-text') || 'Kup' );
                            }
                        }
                    });
                }).fail(function(){
                    alert('Network error');
                    $submit.prop('disabled', false).text( $form.data('submit-text') || 'Kup' );
                });
            });
        });
    });

})(window, document, jQuery);
