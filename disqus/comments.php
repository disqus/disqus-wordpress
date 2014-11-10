<?php
if (DISQUS_DEBUG) {
    echo "<p><strong>Disqus Debug</strong> thread_id: ".get_post_meta($post->ID, 'dsq_thread_id', true)."</p>";
}
?>

<div id="disqus_thread">
    <?php if (!get_option('disqus_disable_ssr') && have_comments()): ?>
        <div id="dsq-content">

<?php if (get_comment_pages_count() > 1 && get_option('page_comments')): // Are there comments to navigate through? ?>
            <div class="navigation">
                <div class="nav-previous">
                    <span class="meta-nav">&larr;</span>&nbsp;
                    <?php previous_comments_link( dsq_i('Older Comments')); ?>
                </div>
                <div class="nav-next">
                    <?php next_comments_link(dsq_i('Newer Comments')); ?>
                    &nbsp;<span class="meta-nav">&rarr;</span>
                </div>
            </div> <!-- .navigation -->
<?php endif; // check for comment navigation ?>

            <ul id="dsq-comments">
                <?php
                    /* Loop through and list the comments. Tell wp_list_comments()
                     * to use dsq_comment() to format the comments.
                     */
                    wp_list_comments(array('callback' => 'dsq_comment'));
                ?>
            </ul>

<?php if (get_comment_pages_count() > 1 && get_option('page_comments')): // Are there comments to navigate through? ?>
            <div class="navigation">
                <div class="nav-previous">
                    <span class="meta-nav">&larr;</span>
                    &nbsp;<?php previous_comments_link( dsq_i('Older Comments') ); ?>
                </div>
                <div class="nav-next">
                    <?php next_comments_link( dsq_i('Newer Comments') ); ?>
                    &nbsp;<span class="meta-nav">&rarr;</span>
                </div>
            </div><!-- .navigation -->
<?php endif; // check for comment navigation ?>

        </div>

    <?php endif; ?>
</div>

<?php

global $wp_version;

$embed_vars = array(
    'disqusConfig' => array(
        'platform' => 'wordpress@'.$wp_version,
        'language' => apply_filters( 'disqus_language_filter', '' ),
    ),
    'disqusIdentifier' => dsq_identifier_for_post( $post ),
    'disqusShortname' => strtolower( get_option( 'disqus_forum_url' ) ),
    'disqusTitle' => dsq_title_for_post( $post ),
    'disqusUrl' => get_permalink(),
    'options' => array(
        'manualSync' => get_option('disqus_manual_sync'),
    ),
    'postId' => $post->ID,
);

// Add SSO vars if enabled
$sso = dsq_sso();
if ($sso) {
    global $current_site;

    foreach ($sso as $k=>$v) {
        $embed_vars['disqusConfig'][$k] = $v;
    }

    $siteurl = site_url();
    $sitename = get_bloginfo('name');
    $embed_vars['disqusConfig']['sso'] = array(
        'name' => wp_specialchars_decode($sitename, ENT_QUOTES),
        'button' => get_option('disqus_sso_button'),
        'url' => $siteurl.'/wp-login.php',
        'logout' => $siteurl.'/wp-login.php?action=logout',
        'width' => '800',
        'height' => '700',
    );
}

if ( get_option('dsq_external_js') == '1' ) {
    wp_register_script( 'dsq_embed_script', plugins_url( '/media/js/disqus.js', __FILE__ ) );
    wp_localize_script( 'dsq_embed_script', 'embedVars', $embed_vars );
    wp_enqueue_script( 'dsq_embed_script', plugins_url( '/media/js/disqus.js', __FILE__ ) );
}
else {

?>
<script type="text/javascript">
var disqus_url = '<?php echo get_permalink(); ?>';
var disqus_identifier = '<?php echo dsq_identifier_for_post($post); ?>';
var disqus_container_id = 'disqus_thread';
var disqus_shortname = '<?php echo strtolower(get_option('disqus_forum_url')); ?>';
var disqus_title = <?php echo cf_json_encode( dsq_title_for_post($post) ); ?>;
var disqus_config_custom = window.disqus_config;
var disqus_config = function () {
    /*
    All currently supported events:
    onReady: fires when everything is ready,
    onNewComment: fires when a new comment is posted,
    onIdentify: fires when user is authenticated
    */
    
    <?php
    $sso = dsq_sso();
    if ($sso) {
        foreach ($sso as $k=>$v) {
            echo 'this.page.' . esc_js($k) . ' = \'' . esc_js($v) . '\';';
            echo "\n";
        }
        echo dsq_sso_login();
    }
    ?>

    this.language = '<?php echo esc_js( apply_filters('disqus_language_filter', '') ) ?>';
    <?php if (!get_option('disqus_manual_sync')): ?>
    this.callbacks.onReady.push(function () {

        // sync comments in the background so we don't block the page
        var script = document.createElement('script');
        script.async = true;
        script.src = '?cf_action=sync_comments&post_id=<?php echo esc_attr( $post->ID ); ?>';

        var firstScript = document.getElementsByTagName('script')[0];
        firstScript.parentNode.insertBefore(script, firstScript);
    });
    <?php endif; ?>

    if (disqus_config_custom) {
        disqus_config_custom.call(this);
    }
};

(function() {
    var dsq = document.createElement('script'); dsq.type = 'text/javascript';
    dsq.async = true;
    dsq.src = '//' + disqus_shortname + '.disqus.com/embed.js';
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
})();
</script>

<?php

}

?>
