(function($) {
    'use strict';

    function wooprint_open_print_window(html) {
        var win = window.open('', '_blank');
        if (!win) {
            alert(wooprintAdmin.label.error);
            return;
        }
        win.document.write(html);
        win.document.close();
        win.focus();
    }

    function wooprint_fetch_and_print(orderId) {
        $.ajax({
            url: wooprintAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wooprint_get_receipt',
                order_id: orderId,
                nonce: wooprintAdmin.nonce
            },
            beforeSend: function() {
                $('.wooprint-print-btn, .wooprint-print-btn-small, .wooprint-print-btn-front').prop('disabled', true);
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    wooprint_open_print_window(response.data.html);
                } else {
                    alert(wooprintAdmin.label.error);
                }
            },
            error: function() {
                alert(wooprintAdmin.label.error);
            },
            complete: function() {
                $('.wooprint-print-btn, .wooprint-print-btn-small, .wooprint-print-btn-front').prop('disabled', false);
            }
        });
    }

    function wooprint_fetch_and_preview(orderId) {
        $.ajax({
            url: wooprintAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wooprint_get_receipt',
                order_id: orderId,
                nonce: wooprintAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    var win = window.open('', '_blank');
                    if (!win) return;
                    win.document.write(response.data.html.replace(
                        '<script>window.onload = function() { window.print(); }<\/script>',
                        ''
                    ));
                    win.document.close();
                    win.focus();
                }
            }
        });
    }

    $(document).on('click', '.wooprint-print-btn, .wooprint-print-btn-small, .wooprint-print-btn-front', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        if (orderId) {
            wooprint_fetch_and_print(orderId);
        }
    });

    $(document).on('click', '.wooprint-preview-btn', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        if (orderId) {
            wooprint_fetch_and_preview(orderId);
        }
    });

    var $bulkPrintBtn = $('#wooprint-bulk-print-btn');
    if ($bulkPrintBtn.length) {
        $bulkPrintBtn.on('click', function(e) {
            e.preventDefault();
            var ids = $(this).data('order-ids');
            if (!ids || !ids.length) return;

            $.ajax({
                url: wooprintAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wooprint_get_bulk_receipts',
                    order_ids: ids,
                    nonce: wooprintAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        wooprint_open_print_window(response.data.html);
                    } else {
                        alert(wooprintAdmin.label.error);
                    }
                },
                error: function() {
                    alert(wooprintAdmin.label.error);
                }
            });
        });
    }

    var frame;
    $('#wooprint-upload-header').on('click', function(e) {
        e.preventDefault();
        if (frame) {
            frame.open();
            return;
        }
        frame = wp.media({
            title: wooprintAdmin.mediaTitle || 'Select Logo',
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#wooprint_header_image').val(attachment.id);
            $('#wooprint-header-preview').html(
                '<img src="' + attachment.url + '" style="max-width:200px;height:auto;display:block;margin-bottom:8px;">'
            );
            $('#wooprint-remove-header').show();
        });
        frame.open();
    });

    $('#wooprint-remove-header').on('click', function(e) {
        e.preventDefault();
        $('#wooprint_header_image').val('');
        $('#wooprint-header-preview').empty();
        $(this).hide();
    });

    jQuery('.wp-list-table tbody').sortable({
        items: 'tr:not(.no-items)',
        cursor: 'grab',
        axis: 'y',
        handle: '.wooprint-drag-handle',
        placeholder: 'ui-sortable-placeholder',
        update: function() {
            var ids = [];
            jQuery('.wp-list-table tbody tr').each(function() {
                var id = jQuery(this).attr('id').replace('post-', '');
                if (id) ids.push(parseInt(id));
            });
            if (ids.length && typeof wooprintAdmin !== 'undefined') {
                jQuery.ajax({
                    url: wooprintAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wooprint_save_rule_order',
                        rule_ids: ids,
                        nonce: wooprintAdmin.nonce
                    }
                });
            }
        }
    });

})(jQuery);
