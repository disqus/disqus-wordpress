<?php
/**
 * Implementation of the Disqus API designed for WordPress.
 *
 * @author        Disqus <team@disqus.com>
 * @copyright    2007-2010 Big Head Labs
 * @link        http://disqus.com/
 * @package        Disqus
 * @subpackage    DisqusWordPressAPI
 * @version        2.0
 */

require_once(ABSPATH.WPINC.'/http.php');
require_once(dirname(__FILE__) . '/api/disqus/disqus.php');
/** @#+
 * Constants
 */
/**
 * Base URL for Disqus.
 */
define('DISQUS_ALLOWED_HTML', '<b><u><i><h1><h2><h3><code><blockquote><br><hr>');

/**
 * Helper methods for all of the Disqus v2 API methods.
 *
 * @package        Disqus
 * @subpackage    DisqusWordPressAPI
 * @author        DISQUS.com <team@disqus.com>
 * @copyright    2007-2008 Big Head Labs
 * @version        1.0
 */
class DisqusWordPressAPI {
    var $short_name;
    var $forum_api_key;

    function DisqusWordPressAPI($short_name=null, $forum_api_key=null, $user_api_key=null) {
        $this->short_name = $short_name;
        $this->forum_api_key = $forum_api_key;
        $this->user_api_key = $user_api_key;
        $this->api = new WP_DisqusAPI($user_api_key, $forum_api_key, DISQUS_API_URL);
    }

    function get_last_error() {
        return $this->api->get_last_error();
    }

    function get_user_api_key($username, $password) {
        $response = $this->api->call('get_user_api_key', array(
            'username'    => $username,
            'password'    => $password,
        ), true);
        return $response;
    }

    function get_forum_list($user_api_key) {
        $this->api->user_api_key = $user_api_key;
        return $this->api->get_forum_list();
    }

    function get_forum_api_key($user_api_key, $id) {
        $this->api->user_api_key = $user_api_key;
        return $this->api->get_forum_api_key($id);
    }
    
    function get_forum_posts($start_id=0) {
        $response = $this->api->get_forum_posts(null, array(
            'filter' => 'approved',
            'start_id' => $start_id,
            'limit' => 100,
            'order' => 'asc',
            'full_info' => 1
        ));
        return $response;
    }

    function import_wordpress_comments(&$wxr, $timestamp, $eof=true) {
        $http = new WP_Http();
        $response = $http->request(
            DISQUS_IMPORTER_URL . 'api/import-wordpress-comments/',
            array(
                'method' => 'POST',
                'timeout' => 60,
                'body' => array(
                    'forum_url' => $this->short_name,
                    'forum_api_key' => $this->forum_api_key,
                    'response_type'    => 'php',
                    'wxr' => $wxr,
                    'timestamp' => $timestamp,
                    'eof' => (int)$eof
                ),
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
}

class WP_DisqusAPI extends DisqusAPI {

	/**
	 * Wrapper to the WordPress HTTP API for making an HTTP request.
	 *
	 * @param	string	$url		URL to make request to.
	 * @param	array	$postdata	(optional) If postdata is provided, the request
	 *								method is POST with the key/value pairs as
	 *								the data.
	 * @param	array	$file		(optional) Should provide associative array
	 *								with two keys: name and field.  Name should
	 *								be the name of the file and field is the name
	 *								of the field to POST.
	 */
	function urlopen($url, $postdata=false, $file=false) {
		$response = array(
			'data' => '',
			'code' => 0
		);

		if ( $file ) {
			extract( $file, EXTR_PREFIX_ALL, 'file' );
		}

		$args = array(
			'user-agent' => USER_AGENT,
			'timeout'    => SOCKET_TIMEOUT,
			'method'     => ( $postdata ) ? 'POST' : 'GET',
		);

		if ( !empty( $file_name ) && !empty( $file_field ) ) {
			if ( !$postdata ) {
				$postdata = array();
			}
			$postdata[$file_field] = '@' . $file_name;
		}

		if ( $postdata ) {
			$args['body'] = $postdata;
		}

		$request = wp_remote_request( $url, $args );

		if ( is_wp_error( $request ) ) {
			return $response;
		}

		$response['code']    = wp_remote_retrieve_response_code( $request );
		$response['headers'] = wp_remote_retrieve_headers( $request );
		$response['data']    = wp_remote_retrieve_body( $request );

		return $response;
	}

}
