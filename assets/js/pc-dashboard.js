// File: assets/js/pc-dashboard.js
// Frontend dashboard JS: listing form, uploads, favorites, messages, basic UX
// Requires: jQuery
(function(window, document, $){
    'use strict';

    // Localized variables provided by wp_localize_script -> pc_frontend
    var pc = window.pc_frontend || {};
    var ajaxUrl = pc.ajax_url || '/wp-admin/admin-ajax.php';
    var nonce = pc.nonce || '';

    /**
     * Minimal helper to POST AJAX and return a Promise
     * Automatically attaches nonce.
     */
    function ajaxPost(action, data) {
        data = data || {};
        data.action = action;
        data.nonce = nonce;
        return $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: data,
            dataType: 'json',
        });
    }

    /**
     * Helper to POST FormData (for file uploads)
     */
    function ajaxPostForm(action, formData) {
        formData.append('action', action);
        formData.append('nonce', nonce);
        return $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
        });
    }

    /**
     * Show a temporary notice in the UI.
     * Minimal, non-intrusive.
     */
    function showNotice(message, type, timeout) {
        type = type || 'success'; // 'success'|'error'|'info'
        timeout = typeof timeout === 'number' ? timeout : 4000;
        var $notice = $('<div class="pc-notice pc-notice-' + type + '">' + $('<div/>').text(message).html() + '</div>');
        $('body').append($notice);
        setTimeout(function(){ $notice.fadeOut(300, function(){ $notice.remove(); }); }, timeout);
    }

    /**
     * Render thumbnail preview for uploaded image (accepts url or attachment object)
     */
    function renderThumb($container, url, attachId) {
        var $wrap = $('<div class="pc-thumb-wrap"></div>');
        var $img = $('<img class="pc-thumb-img" />').attr('src', url).attr('data-attachment-id', attachId || '');
        var $remove = $('<button type="button" class="pc-thumb-remove">×</button>');
        $wrap.append($img).append($remove);
        $container.append($wrap);
    }

    /**
     * Initialize listing form (create / update)
     * Expects form markup .pc-listing-form with inputs: title, content, contact_email, contact_phone, hidden input gallery ids may be managed via JS
     */
    function initListingForms() {
        $(document).on('submit', '.pc-listing-form', function(e){
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]').first();
            var origText = $btn.text();
            $btn.prop('disabled', true).text($btn.data('processing-text') || 'Proszę czekać...');

            // Collect basic fields
            var formData = {
                title: $.trim( $form.find('[name="title"]').val() || '' ),
                content: $.trim( $form.find('[name="content"]').val() || '' ),
                contact_email: $.trim( $form.find('[name="contact_email"]').val() || '' ),
                contact_phone: $.trim( $form.find('[name="contact_phone"]').val() || '' )
            };

            var listingId = parseInt( $form.find('[name="listing_id"]').val() || 0, 10 );
            if ( listingId > 0 ) {
                formData.listing_id = listingId;
            }

            // Gather gallery attachment IDs present in .pc-gallery (data-attachment-id)
            var gallery = [];
            $form.find('.pc-gallery [data-attachment-id]').each(function(){
                var id = parseInt( $(this).attr('data-attachment-id') || 0, 10 );
                if ( id > 0 ) gallery.push(id);
            });
            if ( gallery.length ) {
                formData.gallery = gallery;
            }

            ajaxPost('pc_save_listing', formData)
                .done(function(res){
                    if ( res && res.success ) {
                        showNotice( res.data && res.data.message ? res.data.message : 'Saved', 'success' );
                        // Optionally redirect to listing page or refresh
                        if ( $form.data('after-success') === 'reload' ) {
                            location.reload();
                        } else if ( res.data && res.data.listing_id ) {
                            // If new listing, maybe redirect to edit or listing page
                            if ( $form.data('redirect-to') ) {
                                window.location.href = $form.data('redirect-to').replace('{id}', res.data.listing_id);
                            } else {
                                // update hidden listing_id for further edits
                                $form.find('[name="listing_id"]').val( res.data.listing_id );
                                $btn.prop('disabled', false).text(origText);
                            }
                        } else {
                            $btn.prop('disabled', false).text(origText);
                        }
                    } else {
                        var msg = (res && res.data && res.data.message) ? res.data.message : 'Error';
                        showNotice(msg, 'error');
                        $btn.prop('disabled', false).text(origText);
                    }
                })
                .fail(function(){
                    showNotice('Błąd sieci', 'error');
                    $btn.prop('disabled', false).text(origText);
                });
        });
    }

    /**
     * Drag & Drop uploads and standard file input handling.
     * Dropzone markup: .pc-dropzone and container for thumbs .pc-gallery
     */
    function initUploads() {
        // Generic drag/drop handling
        $(document).on('dragover', '.pc-dropzone', function(e){
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('pc-dropzone-hover');
        });
        $(document).on('dragleave drop', '.pc-dropzone', function(e){
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('pc-dropzone-hover');
        });

        // Handle drop
        $(document).on('drop', '.pc-dropzone', function(e){
            e.preventDefault();
            e.stopPropagation();
            var files = e.originalEvent.dataTransfer.files;
            if ( ! files || files.length === 0 ) {
                return;
            }
            handleFilesUpload( files, $(this) );
        });

        // Handle file input change
        $(document).on('change', '.pc-upload-input', function(e){
            var files = e.target.files || [];
            if ( files.length ) {
                handleFilesUpload( files, $(this).closest('.pc-dropzone') );
                // reset input
                $(this).val('');
            }
        });

        // Remove thumbnail
        $(document).on('click', '.pc-thumb-remove', function(){
            var $wrap = $(this).closest('.pc-thumb-wrap');
            var attachId = parseInt( $wrap.find('img.pc-thumb-img').attr('data-attachment-id') || 0, 10 );
            $wrap.remove();
            // Optionally notify server to delete attachment (not implemented) or remove from gallery meta when saving
        });

        /**
         * Handle actual file(s) upload via AJAX (uses pc_upload_image)
         * dropZone is element .pc-dropzone - we will look for .pc-gallery inside it to append thumbs
         */
        function handleFilesUpload(files, $dropZone) {
            var $gallery = $dropZone.find('.pc-gallery').first();
            if ( !$gallery.length ) {
                // fallback to globally defined .pc-gallery
                $gallery = $('.pc-gallery').first();
            }
            for ( var i = 0; i < files.length; i++ ) {
                (function(file){
                    var fd = new FormData();
                    fd.append('file', file);
                    // Show provisional thumbnail using FileReader if available
                    if ( window.FileReader ) {
                        var reader = new FileReader();
                        reader.onload = function(evt){
                            renderThumb($gallery, evt.target.result, '');
                        };
                        reader.readAsDataURL(file);
                    }
                    // Send to server
                    ajaxPostForm('pc_upload_image', fd)
                        .done(function(res){
                            if ( res && res.success && res.data ) {
                                // find last provisional thumb without attach id and replace data-attachment-id
                                var $last = $gallery.find('.pc-thumb-wrap').filter(function(){ return ! $(this).find('img').attr('data-attachment-id'); }).first();
                                if ( $last.length ) {
                                    $last.find('img').attr('data-attachment-id', res.data.attachment_id);
                                    $last.find('img').attr('src', res.data.url); // replace with returned URL
                                } else {
                                    renderThumb($gallery, res.data.url, res.data.attachment_id);
                                }
                                showNotice( 'Obraz przesłany', 'success' );
                            } else {
                                var msg = (res && res.data && res.data.message) ? res.data.message : 'Upload error';
                                showNotice(msg, 'error');
                            }
                        })
                        .fail(function(){
                            showNotice('Błąd przesyłania', 'error');
                        });
                })(files[i]);
            }
        }
    }

    /**
     * Delete listing handler
     * Buttons: .pc-delete-listing data-listing="{id}"
     */
    function initDeleteListing() {
        $(document).on('click', '.pc-delete-listing', function(e){
            e.preventDefault();
            var $btn = $(this);
            var id = parseInt( $btn.data('listing') || 0, 10 );
            if ( id <= 0 ) return;
            if ( ! confirm( $btn.data('confirm') || 'Czy na pewno usunąć?' ) ) return;

            ajaxPost('pc_delete_listing', { listing_id: id })
                .done(function(res){
                    if ( res && res.success ) {
                        showNotice( res.data && res.data.message ? res.data.message : 'Usunięto', 'success' );
                        // Remove card from DOM or reload
                        if ( $btn.data('remove-selector') ) {
                            $( $btn.data('remove-selector') ).remove();
                        } else {
                            location.reload();
                        }
                    } else {
                        showNotice( (res && res.data && res.data.message) ? res.data.message : 'Error', 'error' );
                    }
                })
                .fail(function(){ showNotice('Błąd sieci', 'error'); });
        });
    }

    /**
     * Renew listing (extend date)
     */
    function initRenewListing() {
        $(document).on('click', '.pc-renew-listing', function(e){
            e.preventDefault();
            var $btn = $(this);
            var id = parseInt( $btn.data('listing') || 0, 10 );
            if ( id <= 0 ) return;
            ajaxPost('pc_renew_listing', { listing_id: id })
                .done(function(res){
                    if ( res && res.success ) {
                        showNotice( res.data && res.data.message ? res.data.message : 'Odnawianie zakończone', 'success' );
                        if ( $btn.data('after') === 'reload' ) location.reload();
                    } else {
                        showNotice( (res && res.data && res.data.message) ? res.data.message : 'Error', 'error' );
                    }
                })
                .fail(function(){ showNotice('Błąd sieci', 'error'); });
        });
    }

    /**
     * Request feature -> creates pending transaction; response contains tx id + amount
     */
    function initRequestFeature() {
        $(document).on('click', '.pc-request-feature', function(e){
            e.preventDefault();
            var $btn = $(this);
            var id = parseInt( $btn.data('listing') || 0, 10 );
            if ( id <= 0 ) return;
            $btn.prop('disabled', true);

            ajaxPost('pc_request_feature', { listing_id: id })
                .done(function(res){
                    $btn.prop('disabled', false);
                    if ( res && res.success ) {
                        var data = res.data || {};
                        showNotice( data.message || 'Transakcja utworzona', 'success' );
                        // present next steps: open payment modal or redirect to checkout (handler left to payment module)
                        if ( $btn.data('checkout-url') ) {
                            window.location.href = $btn.data('checkout-url').replace('{tx}', data.transaction_id);
                        } else if ( typeof window.pcOpenPaymentModal === 'function' ) {
                            window.pcOpenPaymentModal( data );
                        } else {
                            // show small inline instructions
                            alert('Transakcja: ' + data.transaction_id + '\nKwota: ' + data.amount + ' ' + data.currency);
                        }
                    } else {
                        showNotice( (res && res.data && res.data.message) ? res.data.message : 'Error', 'error' );
                    }
                })
                .fail(function(){ $btn.prop('disabled', false); showNotice('Błąd sieci', 'error'); });
        });
    }

    /**
     * Toggle favorite
     */
    function initFavorites() {
        $(document).on('click', '.pc-toggle-favorite', function(e){
            e.preventDefault();
            var $btn = $(this);
            var id = parseInt( $btn.data('listing') || 0, 10 );
            if ( id <= 0 ) return;
            ajaxPost('pc_toggle_favorite', { listing_id: id })
                .done(function(res){
                    if ( res && res.success ) {
                        var state = res.data && res.data.state ? res.data.state : 'added';
                        if ( state === 'added' ) {
                            $btn.addClass('is-fav');
                        } else {
                            $btn.removeClass('is-fav');
                        }
                        showNotice( state === 'added' ? 'Dodano do ulubionych' : 'Usunięto z ulubionych', 'success' );
                    } else {
                        showNotice( (res && res.data && res.data.message) ? res.data.message : 'Error', 'error' );
                    }
                })
                .fail(function(){ showNotice('Błąd sieci', 'error'); });
        });
    }

    /**
     * Send message
     */
    function initMessages() {
        $(document).on('submit', '.pc-message-form', function(e){
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]').first();
            var orig = $btn.text();
            $btn.prop('disabled', true).text($btn.data('processing-text') || 'Wysyłanie...');

            var data = {
                to_user: parseInt( $form.find('[name="to_user"]').val() || 0, 10 ),
                listing_id: parseInt( $form.find('[name="listing_id"]').val() || 0, 10 ),
                body: $.trim( $form.find('[name="body"]').val() || '' )
            };

            ajaxPost('pc_send_message', data)
                .done(function(res){
                    $btn.prop('disabled', false).text(orig);
                    if ( res && res.success ) {
                        showNotice( res.data && res.data.message ? res.data.message : 'Wysłano', 'success' );
                        if ( $form.data('after-success') === 'clear' ) $form[0].reset();
                        if ( $form.data('after-success') === 'reload' ) location.reload();
                    } else {
                        showNotice( (res && res.data && res.data.message) ? res.data.message : 'Error', 'error' );
                    }
                })
                .fail(function(){ $btn.prop('disabled', false).text(orig); showNotice('Błąd sieci', 'error'); });
        });
    }

    /**
     * Simple initialization routine
     */
    function init() {
        // bail if jQuery absent (already required)
        if ( typeof $ === 'undefined' ) return;

        initListingForms();
        initUploads();
        initDeleteListing();
        initRenewListing();
        initRequestFeature();
        initFavorites();
        initMessages();

        // accessibility helper: allow enter to submit forms in modals etc.
        $(document).on('keydown', '.pc-listing-form input, .pc-listing-form textarea', function(e){
            if ( e.key === 'Enter' && (e.ctrlKey || e.metaKey) ) {
                $(this).closest('form').submit();
            }
        });
    }

    // Run on DOM ready
    $(document).ready(function(){ init(); });

})(window, document, jQuery);
