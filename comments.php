<?php
if (DISQUS_DEBUG) {
    echo "<p><strong>Disqus Debug</strong> thread_id: ".get_post_meta($post->ID, 'dsq_thread_id', true)."</p>";
}
?>

<div id="disqus_thread">
    <?php if (!get_option('disqus_disable_ssr')): ?>
        <?php
        // if (is_file(TEMPLATEPATH . '/comments.php')) {
        //     include(TEMPLATEPATH . '/comments.php');
        // }
        ?>
        <div id="dsq-content">
            <ul id="dsq-comments">
    <?php foreach ($comments as $comment) : ?>
                <li id="dsq-comment-<?php echo comment_ID(); ?>">
                    <div id="dsq-comment-header-<?php echo comment_ID(); ?>" class="dsq-comment-header">
                        <cite id="dsq-cite-<?php echo comment_ID(); ?>">
    <?php if(comment_author_url()) : ?>
                            <a id="dsq-author-user-<?php echo comment_ID(); ?>" href="<?php echo comment_author_url(); ?>" target="_blank" rel="nofollow"><?php echo comment_author(); ?></a>
    <?php else : ?>
                            <span id="dsq-author-user-<?php echo comment_ID(); ?>"><?php echo comment_author(); ?></span>
    <?php endif; ?>
                        </cite>
                    </div>
                    <div id="dsq-comment-body-<?php echo comment_ID(); ?>" class="dsq-comment-body">
                        <div id="dsq-comment-message-<?php echo comment_ID(); ?>" class="dsq-comment-message"><?php echo wp_filter_kses(comment_text()); ?></div>
                    </div>
                </li>
    <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<script type="text/javascript">
/* <![CDATA[ */
    var disqus_url = '<?php echo get_permalink(); ?>';
    var disqus_identifier = '<?php echo dsq_identifier_for_post($post); ?>';
    var disqus_container_id = 'disqus_thread';
    var disqus_domain = '<?php echo DISQUS_DOMAIN; ?>';
    var disqus_shortname = '<?php echo strtolower(get_option('disqus_forum_url')); ?>';
    var disqus_title = <?php echo cf_json_encode(dsq_title_for_post($post)); ?>;
    <?php if (false && get_option('disqus_developer')): ?>
        var disqus_developer = 1;
    <?php endif; ?>
    var disqus_config = function () {
        var config = this; // Access to the config object

        /* 
           All currently supported events:
            * preData — fires just before we request for initial data
            * preInit - fires after we get initial data but before we load any dependencies
            * onInit  - fires when all dependencies are resolved but before dtpl template is rendered
            * afterRender - fires when template is rendered but before we show it
            * onReady - everything is done
         */

        config.callbacks.preData.push(function() {
            // clear out the container (its filled for SEO/legacy purposes)
            document.getElementById(disqus_container_id).innerHTML = '';
        });
        <?php if (!get_option('disqus_manual_sync')): ?>
        config.callbacks.onReady.push(function() {
            // sync comments in the background so we don't block the page
            DISQUS.request.get('?cf_action=sync_comments&post_id=<?php echo $post->ID; ?>');
        });
        <?php endif; ?>
        <?php
        $sso = dsq_sso();
        if ($sso) {
            foreach ($sso as $k=>$v) {
                echo "this.page.{$k} = '{$v}';\n";
            }
        }
        ?>
    };
    var facebookXdReceiverPath = '<?php echo DSQ_PLUGIN_URL . '/xd_receiver.htm' ?>';
/* ]]> */
</script>

<script type="text/javascript">
/* <![CDATA[ */
    var DsqLocal = {
        'trackbacks': [
<?php
    $count = 0;
    foreach ($comments as $comment) {
        $comment_type = get_comment_type();
        if ( $comment_type != 'comment' ) {
            if( $count ) { echo ','; }
?>
            {
                'author_name':    <?php echo cf_json_encode(get_comment_author()); ?>,
                'author_url':    <?php echo cf_json_encode(get_comment_author_url()); ?>,
                'date':            <?php echo cf_json_encode(get_comment_date('m/d/Y h:i A')); ?>,
                'excerpt':        <?php echo cf_json_encode(str_replace(array("\r\n", "\n", "\r"), '<br />', get_comment_excerpt())); ?>,
                'type':            <?php echo cf_json_encode($comment_type); ?>
            }
<?php
            $count++;
        }
    }
?>
        ],
        'trackback_url': <?php echo cf_json_encode(get_trackback_url()); ?>
    };
/* ]]> */
</script>

<script type="text/javascript">
/* <![CDATA[ */
(function() {
    var dsq = document.createElement('script'); dsq.type = 'text/javascript';
    dsq.async = true;
    dsq.src = 'http://' + disqus_shortname + '.' + disqus_domain + '/embed.js?pname=wordpress&pver=<?php echo DISQUS_VERSION; ?>';
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
})();
/* ]]> */
</script>
