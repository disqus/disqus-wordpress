jQuery(document).ready(function($) {
	
    // fix menu
    var mc = $('#menu-comments');
    mc.find('a.wp-has-submenu').attr('href', 'edit-comments.php?page=disqus').end().find('.wp-submenu  li:has(a[href="edit-comments.php?page=disqus"])').prependTo(mc.find('.wp-submenu ul'));

	// fix admin bar
    $('#wp-admin-bar-comments').find('a.ab-item').attr('href', 'edit-comments.php?page=disqus');

	// Remove trackbacks
    $('#dashboard_recent_comments div.trackback').remove();

    // Replace comment counts
	$('#dashboard_right_now').find('.b-comments a').html(dashboardVars.stats.totalComments).end().find('.b_approved a').html(dashboardVars.stats.approved).end().find('.b-waiting a').html(dashboardVars.stats.moderated).end().find('.b-spam a').html(dashboardVars.stats.spam);
});
