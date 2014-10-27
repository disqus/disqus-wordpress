/* <![CDATA[ */
var disqus_url = embedVars.disqusUrl;
var disqus_identifier = embedVars.disqusIdentifier;
var disqus_container_id = 'disqus_thread';
var disqus_domain = embedVars.disqusDomain;
var disqus_shortname = embedVars.disqusShortname;
var disqus_title = embedVars.disqusTitle;
var disqus_config = function () {
    /*
    All currently supported events:
    onReady: fires when everything is ready,
    onNewComment: fires when a new comment is posted,
    onIdentify: fires when user is authenticated
    */
    this.language = embedVars.disqusConfig.language;
    this.callbacks.onReady.push(function () {
        if (!embedVars.options.manualSync) {
            // sync comments in the background so we don't block the page
            var script = document.createElement('script');
            script.async = true;
            script.src = '?cf_action=sync_comments&post_id=' + embedVars.postId;

            var firstScript = document.getElementsByTagName('script')[0];
            firstScript.parentNode.insertBefore(script, firstScript);
        }
    });

    if (typeof(window.exports.disqus_config) == 'function') {
        window.exports.disqus_config.call(this);
    }
};

(function() {
    var dsq = document.createElement('script'); dsq.type = 'text/javascript';
    dsq.async = true;
    dsq.src = '//' + disqus_shortname + '.' + embedVars.disqusDomain + '/' + 'embed' + '.js';
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
})();
/* ]]> */