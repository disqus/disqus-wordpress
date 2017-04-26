var disqus_url = embedVars.disqusUrl;
var disqus_identifier = embedVars.disqusIdentifier;
var disqus_container_id = 'disqus_thread';
var disqus_shortname = embedVars.disqusShortname;
var disqus_title = embedVars.disqusTitle;
var disqus_config_custom = window.disqus_config;
var disqus_config = function () {
    /*
    All currently supported events:
    onReady: fires when everything is ready,
    onNewComment: fires when a new comment is posted,
    onIdentify: fires when user is authenticated
    */
    if (typeof embedVars.disqusConfig.remote_auth_s3 !== 'undefined') {
        this.page.remote_auth_s3 = embedVars.disqusConfig.remote_auth_s3;
    }

    if (typeof embedVars.disqusConfig.api_key !== 'undefined') {
        this.page.api_key = embedVars.disqusConfig.api_key;
    }

    if (typeof embedVars.disqusConfig.sso !== 'undefined') {
        this.sso = {
            name: embedVars.disqusConfig.sso.name,
            button: embedVars.disqusConfig.sso.button,
            url: embedVars.disqusConfig.sso.url,
            logout: embedVars.disqusConfig.sso.logout,
            width: embedVars.disqusConfig.sso.width,
            height: embedVars.disqusConfig.sso.height
        };
    }

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
