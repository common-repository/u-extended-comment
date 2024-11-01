<?php
/* 
Plugin Name: U Extended Comment
Plugin URI: http://urlless.com/u-extended-comment/
Description: This plugin extends the standard WordPress comment system.
Version: 1.1
Author: Taehan Lee
Author URI: http://urlless.com
*/ 

class UExtendedComment {

	var $id = 'uexc';
	var $ver = '1.1';
	var $url, $path, $plugins;
	
	function UExtendedComment(){
		$this->url = plugin_dir_url(__FILE__);
		$this->path = plugin_dir_path(__FILE__);
		
		load_plugin_textdomain($this->id, false, dirname(plugin_basename(__FILE__)).'/languages/');
		
		register_activation_hook( __FILE__, 'uexc_activation' );
		add_action( 'plugins_loaded', array(&$this, 'loaded'));
		
		$includes = array( 'admin', 'utils', 'editor', 'attachment', 'widget');
		foreach ( $includes as $include_file )
			require( $this->path . 'includes/' . $include_file . '.php' );
	}
	
	function loaded(){
		do_action( 'uexc_loaded' );
	}

}

$uexc = new UExtendedComment();



function uexc_activation() {
	global $wp_version;
	if (version_compare($wp_version, "3.2", "<")) 
		wp_die("This plugin requires WordPress version 3.2 or higher.");
			
	register_uninstall_hook( __FILE__, 'uexc_uninstall' );
	do_action( 'uexc_activation' );
}

function uexc_uninstall(){
	do_action( 'uexc_uninstall' );
}