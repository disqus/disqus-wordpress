jQuery(document).ready( function($) {

    $('#menu-comments').pointer({
        content: pointerContent,
        position: {
            edge: 'left', // arrow direction
            align:  'center' // vertical alignment
        },
        pointerWidth: 350,
        close: function() {
            $.post(ajaxurl, {
                pointer: 'disqus_settings_pointer', // pointer ID
                action: 'dismiss-wp-pointer'
            });
        }
    }).pointer('open');
});
