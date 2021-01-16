<?php
/**
 * Plugin Name: StoryChief WPML
 * Plugin URI: https://storychief.io/wordpress-wpml
 * Description: This plugin lets StoryChief and WPML work together.
 * Version: 1.0.3
 * Author: Gregory Claeyssens
 * Author URI: http://storychief.io
 * License: GPL2
 */

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'STORYCHIEF_WPML_VERSION', '1.0.3' );
define( 'STORYCHIEF_WPML__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STORYCHIEF_WPML__PLUGIN_BASE_NAME', plugin_basename(__FILE__) );

require_once( STORYCHIEF_WPML__PLUGIN_DIR . 'class.storychief-wpml.php' );

add_action( 'init', array( 'Storychief_WPML', 'init' ) );
