(function($) {
    'use strict';

    var pollingInterval = null;
    var isPrinting = false;

    function wooprint_open_print_window(html) {
        var win = window.open('', '_blank');
        if (!win) {
            return false;
        }
        win.document.write(html);
        win.document.close();
        win.focus();
        return true;
    }

    function wooprint_poll() {
        if (isPrinting) {
            return;
        }

        $.ajax({
            url: wooprintStream.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wooprint_poll',
                nonce: wooprintStream.nonce
            },
            success: function(response) {
                if (!response.success || !response.data.html) {
                    return;
                }

                isPrinting = true;

                var jobId = response.data.job_id || 0;
                var opened = wooprint_open_print_window(response.data.html);

                if (opened && jobId) {
                    setTimeout(function() {
                        $.ajax({
                            url: wooprintStream.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'wooprint_mark_printed',
                                job_id: jobId,
                                nonce: wooprintStream.nonce
                            }
                        });
                    }, 1000);
                }

                setTimeout(function() {
                    isPrinting = false;
                }, 5000);
            }
        });
    }

    function wooprint_start_stream() {
        if (pollingInterval) {
            return;
        }

        var interval = parseInt(wooprintStream.interval, 10) || 5000;
        pollingInterval = setInterval(wooprint_poll, interval);

        wooprint_poll();
    }

    function wooprint_stop_stream() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    $(document).ready(function() {
        if (typeof wooprintStream !== 'undefined' && wooprintStream.enabled === '1') {
            wooprint_start_stream();
        }
    });

})(jQuery);
