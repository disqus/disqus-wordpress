<?php
/**
 * Implementation of the Disqus API designed for WordPress.
 *
 * @author        Disqus <help@disqus.com>
 * @link          http://disqus.com/
 * @package       Disqus
 * @subpackage    DisqusWordPressAPI
 * @copyright     2007-2013 Disqus
 * @version       2.0
 */

require_once(ABSPATH.WPINC.'/http.php');
require_once(dirname(__FILE__) . '/api/disqusapi/disqusapi.php');

/** @#+
 * Constants
 */
/**
 * Base URL for Disqus.
 */
define('DISQUS_ALLOWED_HTML', '<b><u><i><h1><h2><h3><code><blockquote><br><hr>');

/**
 * Helper methods for all of the Disqus v3 API methods.
 *
 * @author        Disqus <help@disqus.com>
 * @link          http://disqus.com/
 * @package       Disqus
 * @subpackage    DisqusWordPressAPI 
 * @copyright     2007-2013 Disqus
 * @version       2.0
 */
class DisqusWordPressAPI {
    var $short_name;
    var $dsq_secret_key;

    function DisqusWordPressAPI($short_name=null, $dsq_secret_key=null) {
        $this->short_name = $short_name;
        $this->dsq_secret_key = $dsq_secret_key;
        $this->api = new DisqusAPI($dsq_secret_key);  
    }

    function import_wordpress_comments(&$wxr, $timestamp, $eof=true) {
        $http = new WP_Http();
        $response = $http->request(
            DISQUS_IMPORTER_URL . 'api/import-wordpress-comments/',
            array(
                'method' => 'POST',
                'body' => array(
                    'forum_url' => $this->short_name,
                    'forum_api_key' => $this->forum_api_key,
                    'response_type'    => 'php',
                    'wxr' => $wxr,
                    'timestamp' => $timestamp,
                    'eof' => (int)$eof
                )
            )
        );
        if ($response->errors) {
            // hack
            $this->api->last_error = $response->errors;
            return -1;
        }
        $data = unserialize($response['body']);
        if (!$data || $data['stat'] == 'fail') {
            return -1;
        }
        
        return $data;
    }

    function get_forum_posts($start_id=0) {
        // comment id=0 doesn't exist so posts/details will fail using it
        // first ever Disqus comment actually is id=4
        if ($start_id==0) {
            $start_id=4; 
        }

        $last_comment_details = $this->api->posts->details(array('post'=>$start_id));
        $last_timestamp = $last_comment_details->createdAt;

        $response = $this->api->forums->listPosts(array(
            'forum' => $this->short_name,
            'include' => 'approved',
            'since' => $last_timestamp,
            'limit' => 100,
            'order' => 'asc',
            'access_token' => get_option('disqus_access_token')
        ));

        return $response;
    }
}

?>
