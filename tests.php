<?php

require_once(dirname(__FILE__) . '/scripts/wp-cli.php');
require_once(dirname(__FILE__) . '/disqus.php');

require_once('PHPUnit/Framework.php');

class DisqusPluginTest extends PHPUnit_Framework_TestCase {
    public function test_dsq_sync_comments() {
        global $wpdb;
        
        $post_id = $wpdb->get_var($wpdb->prepare('SELECT max(comment_date) FROM ' . $wpdb->comments . ' WHERE comment_post_ID = %d AND comment_agent LIKE \'Disqus/%%\'', $post->ID));
        
        dsq_sync_comments($post, $comments);
    }
}

$suite  = new PHPUnit_Framework_TestSuite('DisqusPluginTest');
$result = $suite->run();

//$result = PHPUnit::run($suite);

require_once('PHPUnit/TextUI/ResultPrinter.php');

$printer = new PHPUnit_TextUI_ResultPrinter();
$printer->printResult($result);

?>