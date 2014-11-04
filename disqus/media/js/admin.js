jQuery(document).ready(function($) {
    
    // Replace comments dashboard link
    $('#dashboard_right_now .inside table td.last a, #dashboard_recent_comments .inside .textright a.button').attr('href', 'edit-comments.php?page=disqus');

    // Focuses on username text box if present
    if ($('#dsq-username').length) {
    	$('#dsq-username').focus();
    }

    // Set current Disqus admin state
    $('#dsq-tabs li').click(function() {
        $('#dsq-tabs li.selected').removeClass('selected');
        $(this).addClass('selected');
        $('.dsq-main, .dsq-advanced').hide();
        $('.' + $(this).attr('rel')).show();
    });
    if (location.href.indexOf('#adv') != -1) {
        $('#dsq-tab-advanced').click();
    }

    // Bind click events to export/sync buttons
    dsq_fire_export();
    dsq_fire_import();
});

var dsq_fire_export = function() {
    jQuery(function($) {
        $('#dsq_export a.button').unbind().click(function() {
            $('#dsq_export .status').removeClass('dsq-export-fail').addClass('dsq-exporting').html('Processing...');
            dsq_export_comments();
            return false;
        });
    });
};

var dsq_export_comments = function() {
    jQuery(function($) {
        var status = $('#dsq_export .status');
        var nonce = $('#dsq-form_nonce_export').val();
        var export_info = (status.attr('rel') || '0|' + (new Date().getTime()/1000)).split('|');        
        $.get(
            adminVars.indexUrl,
            {
                cf_action: 'export_comments',
                post_id: export_info[0],
                timestamp: export_info[1],
                _dsqexport_wpnonce: nonce
            },
            function(response) {
                switch (response.result) {
                    case 'success':
                        status.html(response.msg).attr('rel', response.post_id + '|' + response.timestamp);
                        switch (response.status) {
                            case 'partial':
                                dsq_export_comments();
                            break;
                            case 'complete':
                                status.removeClass('dsq-exporting').addClass('dsq-exported');
                            break;
                        }
                    break;
                    case 'fail':
                        status.parent().html(response.msg);
                        dsq_fire_export();
                    break;
                }
            },
            'json'
        );
    });
};

var dsq_fire_import = function() {
    jQuery(function($) {
        $('#dsq_import a.button, #dsq_import_retry').unbind().click(function() {
            var wipe = $('#dsq_import_wipe').is(':checked');
            $('#dsq_import .status').removeClass('dsq-import-fail').addClass('dsq-importing').html('Processing...');
            dsq_import_comments(wipe);
            return false;
        });
    });
};

var dsq_import_comments = function(wipe) {
    jQuery(function($) {
        var status = $('#dsq_import .status');
        var nonce = $('#dsq-form_nonce_import').val();
        var last_comment_id = status.attr('rel') || '0';
        $.get(
            adminVars.indexUrl,
            {
                cf_action: 'import_comments',
                last_comment_id: last_comment_id,
                wipe: (wipe ? 1 : 0),
                _dsqimport_wpnonce: nonce
            },
            function(response) {
                switch (response.result) {
                    case 'success':
                        status.html(response.msg).attr('rel', response.last_comment_id);
                        switch (response.status) {
                            case 'partial':
                                dsq_import_comments(false);
                                break;
                            case 'complete':
                                status.removeClass('dsq-importing').addClass('dsq-imported');
                                break;
                        }
                    break;
                    case 'fail':
                        status.parent().html(response.msg);
                        dsq_fire_import();
                    break;
                }
            },
            'json'
        );
    });
};
