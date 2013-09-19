<?php
/**
 * Helper script for setting up the WP command line environment
 */
error_reporting(E_ALL | E_STRICT);

if (php_sapi_name() != 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    // Don't execute for web requests
    die("This script must be run from CLI.");
}

if (!isset($argv)) {
    $argv = array();
}

function print_line() {
    $args = func_get_args();
    $result = call_user_func_array('sprintf', $args);
    print("{$result}\n");
}

if (!empty($argc)) {
    for ($x=0; $x < $argc; $x++){
        $param = $argv[$x];
        if (strpos($param, '--host=') === 0) {
            $param_host = substr($param, strpos($param, '=') + 1);
        }
        if (strpos($param, '--uri=') === 0) {
            $param_uri = substr($param, strpos($param, '=') + 1);
        }
    }
}

define('DOING_AJAX', true);
define('WP_USE_THEMES', false);
if (isset($_ENV['WORDPRESS_PATH'])) {
    define('ABSPATH', $_ENV['WORDPRESS_PATH']);
} else {
    $path = str_replace('\\', '/', dirname(__FILE__));
    $parts = explode('/', $path);

    if (count($parts) > 4 && is_file($tmp_path = implode('/', array_slice($parts, 0, -4)) . '/wp-config.php')) {
        // Logical try for default plugin install, 4 levels up. (wp-content/plugins/disqus-comment-system/lib)
        define('ABSPATH', dirname($tmp_path) . '/');
    } else {
        // Iterate upwards until finding any wp-config.php file.
        // Not the best security here, as we could end up running an injected wp-config.php script with the shell user privs.
        do {
            $tmp_path = implode('/', $parts) . '/wp-config.php';
            if(@is_file($tmp_path)) {
                define('ABSPATH', dirname($tmp_path) . '/');
                break;
            }
        } while (null !== array_pop($parts));
    }
}

if (!defined('ABSPATH')) {
    print_line("Unable to determine wordpress path. Please set it using WORDPRESS_PATH.");
    die();
}

$_SERVER = array(
    'HTTP_HOST'      => empty($param_host) ? 'disqus.com' : $param_host,
    'SERVER_NAME'    => empty($param_host) ? 'localhost'  : $param_host,
    'REQUEST_URI'    => empty($param_uri)  ? '/'          : $param_uri,
    'REQUEST_METHOD' => 'GET',
    'SCRIPT_NAME'    => '',
    'PHP_SELF'       => __FILE__
);

require_once(ABSPATH . 'wp-config.php');

// swap out the object cache due to memory constraints

global $wp_object_cache;

class DummyWP_Object_Cache extends WP_Object_Cache {
    function set($id, $data, $group = 'default', $expire = '') {
        return;
    }
    function delete($id, $group = 'default', $force = false) {
        return;
    }
    function add($id, $data, $group = 'default', $expire = '') {
        return;
    }
}

// HACK: kill all output buffers (some plugins, like Hyper Cache, use theses)
while (@ob_end_flush());

// We cant simply replace the object cache incase set/add do something that
// matters to the webserver
// $wp_object_cache = new DummyWP_Object_Cache();

?>
