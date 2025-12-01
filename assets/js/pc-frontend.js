// File: assets/js/pc-frontend.js
(function(window, document, $){
    'use strict';

    var pc = window.pc_frontend || {};
    var ajaxUrl = pc.ajax_url || '/wp-admin/admin-ajax.php';
    var nonce = pc.nonce || '';

    function switchTab(name) {
        $('.pc-tab').attr('hidden', 'hidden');
        $('.pc-tab[data-tabpane="' + name + '"]').removeAttr('hidden');
        $('.pc-tab-btn').removeClass('active');
        $('.pc-tab-btn[data-tab="' + name + '"]').addClass('active');

        // lazy load content
        if (name === 'my-listings') loadMyListings();
        if (name === 'messages') loadMessages();
        if (name === 'favorites') loadFavorites();
        if (name === 'transactions') loadTransactions();
    }

    function loadMyListings() {
        $('#pc-my-listings-container').html('<p>Ładowanie...</p>');
        $.get(ajaxUrl, { action: 'pc_get_my_listings', nonce: nonce })
            .done(function(res){
                if (res.success) {
                    var html = '';
                    if (res.data.listings && res.data.listings.length) {
                        res.data.listings.forEach(function(l){
                            html += '<div class="pc-listing-card"><h3>'+ escapeHtml(l.title) +'</h3>';
                            html += '<p>'+ (l.excerpt||'') +'</p>';
                            html += '<div class="pc-listing-actions">';
                            html += '<button class="pc-edit-listing" data-id="'+ l.ID +'">Edytuj</button>';
                            html += '<button class="pc-delete-listing" data-id="'+ l.ID +'">Usuń</button>';
                            html += '</div></div>';
                        });
                    } else {
                        html = '<p><?php /* translated in PHP */ ?></p>';
                        html = '<p>Brak ogłoszeń.</p>';
                    }
                    $('#pc-my-listings-container').html(html);
                } else {
                    $('#pc-my-listings-container').html('<p>Błąd ładowania.</p>');
                }
            }).fail(function(){ $('#pc-my-listings-container').html('<p>Błąd sieci.</p>'); });
    }

    function loadMessages(){
        $('#pc-messages-list').html('<p>Ładowanie...</p>');
        $.get(ajaxUrl, { action: 'pc_get_messages', nonce: nonce })
            .done(function(res){
                if (res.success) {
                    var html = '';
                    if (res.data.messages && res.data.messages.length) {
                        res.data.messages.forEach(function(m){
                            html += '<div class="pc-message-row"><strong>From: '+ m.from_user +'</strong><p>'+ escapeHtml(m.excerpt) +'</p></div>';
                        });
                    } else {
                        html = '<p>Brak wiadomości.</p>';
                    }
                    $('#pc-messages-list').html(html);
                } else {
                    $('#pc-messages-list').html('<p>Błąd ładowania.</p>');
                }
            }).fail(function(){ $('#pc-messages-list').html('<p>Błąd sieci.</p>'); });
    }

    function loadFavorites(){
        $('#pc-favorites-container').html('<p>Ładowanie...</p>');
        $.get(ajaxUrl, { action: 'pc_get_favorites', nonce: nonce })
            .done(function(res){
                if (res.success) {
                    var html = '';
                    if (res.data.favorites && res.data.favorites.length) {
                        res.data.favorites.forEach(function(l){
                            html += '<div class="pc-listing-card"><h3>'+ escapeHtml(l.title) +'</h3></div>';
                        });
                    } else {
                        html = '<p>Brak ulubionych.</p>';
                    }
                    $('#pc-favorites-container').html(html);
                } else {
                    $('#pc-favorites-container').html('<p>Błąd ładowania.</p>');
                }
            }).fail(function(){ $('#pc-favorites-container').html('<p>Błąd sieci.</p>'); });
    }

    function loadTransactions(){
        $('#pc-transactions-container').html('<p>Ładowanie...</p>');
        $.get(ajaxUrl, { action: 'pc_get_transactions', nonce: nonce })
            .done(function(res){
                if (res.success) {
                    var html = '';
                    if (res.data.transactions && res.data.transactions.length) {
                        res.data.transactions.forEach(function(t){
                            html += '<div class="pc-transaction-row">#'+ t.id + ' ' + (t.provider||'') + ' ' + (t.amount||'') + ' ' + (t.status||'') + '</div>';
                        });
                    } else {
                        html = '<p>Brak transakcji.</p>';
                    }
                    $('#pc-transactions-container').html(html);
                } else {
                    $('#pc-transactions-container').html('<p>Błąd ładowania.</p>');
                }
            }).fail(function(){ $('#pc-transactions-container').html('<p>Błąd sieci.</p>'); });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return $('<div/>').text(text).html();
    }

    // Save listing
    $(document).on('click', '#pc-listing-save', function(e){
        e.preventDefault();
        var $form = $('#pc-listing-form');
        var data = {
            action: 'pc_create_listing',
            nonce: $('input[name="nonce"]', $form).val(),
            title: $('#pc-title').val(),
            content: $('#pc-content').val(),
            excerpt: $('#pc-excerpt').val(),
            contact_email: $('#pc-contact-email').val(),
            contact_phone: $('#pc-contact-phone').val()
        };
        $.post(ajaxUrl, data).done(function(res){
            if (res.success) {
                alert('Ogłoszenie zapisane');
                switchTab('my-listings');
                loadMyListings();
            } else {
                alert('Błąd: ' + (res.data && res.data.message ? res.data.message : ''));
            }
        }).fail(function(){ alert('Błąd sieci'); });
    });

    // Send message
    $(document).on('click', '#pc-send-message-btn', function(e){
        e.preventDefault();
        var $form = $('#pc-send-message-form');
        var data = {
            action: 'pc_send_message',
            nonce: $('input[name="nonce"]', $form).val(),
            to_user: $('#pc-to-user').val(),
            body: $('#pc-message-body').val()
        };
        $.post(ajaxUrl, data).done(function(res){
            if (res.success) {
                alert('Wysłano');
                $('#pc-message-body').val('');
                loadMessages();
            } else {
                alert('Błąd: ' + (res.data && res.data.message ? res.data.message : ''));
            }
        }).fail(function(){ alert('Błąd sieci'); });
    });

    // Drag & drop upload
    $(function(){
        // default first tab
        switchTab('my-listings');

        var dz = document.getElementById('pc-dropzone');
        if (dz) {
            dz.addEventListener('dragover', function(e){ e.preventDefault(); dz.classList.add('pc-dropzone-over'); });
            dz.addEventListener('dragleave', function(e){ dz.classList.remove('pc-dropzone-over'); });
            dz.addEventListener('drop', function(e){
                e.preventDefault();
                dz.classList.remove('pc-dropzone-over');
                var files = e.dataTransfer.files;
                handleFilesUpload(files);
            });
            $('#pc-dropzone-input').on('change', function(e){
                handleFilesUpload(e.target.files);
            });
        }

        function handleFilesUpload(files) {
            if (!files || !files.length) return;
            Array.prototype.forEach.call(files, function(file){
                var fd = new FormData();
                fd.append('action', 'pc_upload_image');
                fd.append('nonce', nonce);
                fd.append('file', file);
                $.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false
                }).done(function(res){
                    if (res.success) {
                        $('#pc-gallery-previews').append('<img src="'+ res.data.url +'" alt="" class="pc-thumb" />');
                    } else {
                        alert('Upload error: ' + (res.data && res.data.message ? res.data.message : ''));
                    }
                }).fail(function(){ alert('Upload network error'); });
            });
        }
    });

})(window, document, jQuery);
