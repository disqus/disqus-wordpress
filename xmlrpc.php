<?php
// TODO: How can we make this work if the cwd is a symlink?
//require_once('../../../wp-config.php');
require_once('/home/jason/public_html/wp20/wp-config.php');
include_once(ABSPATH . WPINC . '/class-IXR.php');


class WPDisqusServer extends IXR_Server {
	function WPDisqusServer() {
		$this->IXR_Server(array(
			'wpdisqus.add' => 'this:add'
		));
	}


	function add($args) {
		if(sizeof($args) != 2) {
			return new IXR_Error(-1, 'I only know how to add two numbers.');
		}
		return $args[0] + $args[1];
	}
}

$server = new WPDisqusServer();

?>
