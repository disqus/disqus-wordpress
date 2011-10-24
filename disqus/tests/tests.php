<?php

require_once(dirname(__FILE__) . '/../lib/wp-cli.php');
require_once(dirname(__FILE__) . '/../disqus.php');

require_once('PHPUnit/Autoload.php');

if (!defined('DISQUS_TEST_DATABASE')) {
    define('DISQUS_TEST_DATABASE', sprintf('%s_test', DB_NAME));
}
class DisqusPluginTest extends PHPUnit_Framework_TestCase {
    /**
     * Sets up our database and fixtures.
     */
    protected function setUp() {
        global $wpdb;

        // If only we could use transactions for our fixture :(
        shell_exec(sprintf('mysql -u%s -p%s -D%s -h%s < initial.sql', DB_USER, DB_PASSWORD, DISQUS_TEST_DATABASE, DB_HOST));

        $res = $wpdb->get_results('show tables');
        assert(count($res) == 11);
    }

    protected function query($query) {
        global $wpdb;

        $result = $wpdb->query($query);
        if ($result === false) {
            throw new Exception(sprintf('Database error: %s', $wpdb->last_error));
        }
    }

    public function test_pending_posts_api() {
        dsq_add_pending_post_id(1);
        $this->assertEquals(dsq_get_pending_post_ids(), array(1));

        dsq_clear_pending_post_ids(array(1));
        $this->assertEquals(dsq_get_pending_post_ids(), array());
    }

    // /**
    //  * Tests the dsq_sync_post function.
    //  */
    // public function testSyncPost() {
    //     global $wpdb;
    //
    //     $post_id = $wpdb->get_var($wpdb->prepare('SELECT max(comment_date) FROM ' . $wpdb->comments . ' WHERE comment_post_ID = %d AND comment_agent LIKE \'Disqus/%%\'', $post->ID));
    //
    //
    //     dsq_sync_post();
    // }
    //
    // /**
    //  * Tests the dsq_sync_comments function.
    //  */
    // public function testSyncComments() {
    //     global $wpdb;
    //
    //     $post_id = $wpdb->get_var($wpdb->prepare('SELECT max(comment_date) FROM ' . $wpdb->comments . ' WHERE comment_post_ID = %d AND comment_agent LIKE \'Disqus/%%\'', $post->ID));
    //
    //     dsq_sync_comments($comments);
    // }
}

function main() {
    function cleanup() {
        global $wpdb;

        // we need to ensure that we switch off this database
        $wpdb->select(null);

        // drop our test database
        // XXX: why cant we use $wpdb->query ?
        mysql_query(sprintf('DROP DATABASE `%s`', DISQUS_TEST_DATABASE)) or die(mysql_error());
    }

    global $wpdb;
    // $this->query(sprintf('DROP DATABASE IF EXISTS `%s`', DISQUS_TEST_DATABASE));
    // check existance of test db
    $exists = $wpdb->get_var($wpdb->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \'%s\'', DISQUS_TEST_DATABASE));

    if (!empty($exists)) {
        printf("Test database '%s' already exists. Continuing will drop this database and all data!\nContinue?  [y/n]\n", $exists);
        $handle = fopen ("php://stdin","r");
        $line = strtolower(trim(fgets($handle)));
        if($line != 'yes' && $line != 'y'){
            echo "ABORTING!\n";
            exit;
        }
    }
    // setup database
    $wpdb->query(sprintf('CREATE DATABASE `%s`', DISQUS_TEST_DATABASE));
    $wpdb->select(DISQUS_TEST_DATABASE);

    // $fp = fopen('initial.sql', 'r');
    // $buffer = '';
    // while (($line = fgets($fp)) !== false) {
    //     $buffer .= trim($line);
    //     if (strpos($line, ';')) {
    //         if (!empty($buffer)) {
    //             $this->query($buffer);
    //         }
    //         $buffer = '';
    //     }
    // }
    //

    try {
        $suite  = new PHPUnit_Framework_TestSuite('DisqusPluginTest');
        $result = $suite->run();

        require_once('PHPUnit/TextUI/ResultPrinter.php');

        $printer = new PHPUnit_TextUI_ResultPrinter();
        $printer->printResult($result);

        cleanup();
    } catch (Exception $ex) {
        cleanup();

        throw $ex;
    }
}

main();

?>