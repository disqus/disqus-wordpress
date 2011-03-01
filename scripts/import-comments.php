#!/usr/bin/php
<?php
/**
 * Incrementally downloads all comments from DISQUS.
 *
 * ``php import-comments.php``
 */

require_once(dirname(__FILE__) . '/../lib/wp-cli.php');
require_once(dirname(__FILE__) . '/../disqus.php');

$forum_url = get_option('disqus_forum_url');

if (empty($forum_url)) {
    print_line("Disqus has not been configured on this installation!");
    die();
}

print_line('---------------------------------------------------------');
print_line('Discovered DISQUS forum shortname as %s', $forum_url);
print_line('---------------------------------------------------------');

$imported = true;
if (in_array('--reset', $argv)) {
    $last_comment_id = 0;
} else {
    $last_comment_id = get_option('disqus_last_comment_id');
}
$total = 0;
$global_start = microtime();

while ($imported) {
    print_line('  Importing chunk starting at comment id %d', $last_comment_id);
    $start = microtime();
    $result = dsq_sync_forum($last_comment_id);
    if ($result === false) {
        print_line('---------------------------------------------------------');
        print_line('There was an error communicating with DISQUS!');
        print_line($dsq_api->get_last_error());
        print_line('---------------------------------------------------------');
        die();
        break;
    } else {
        list($imported, $last_comment_id) = $result;
    }
    $total += $imported;
    $time = abs(microtime() - $start);
    print_line('    %d comments imported (took %.2fs)', $imported, $time);
}
$total_time = abs(microtime() - $global_start);
print_line('---------------------------------------------------------');
print_line('Done (took %.2fs)! %d comments imported from DISQUS', $total_time, $total);
print_line('---------------------------------------------------------');
?>