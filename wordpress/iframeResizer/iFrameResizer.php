<?php
/**
 * Plugin Name: iFrame-Resizer
 * Plugin URI: http://simplecam.de
 * Description: A brief description of the plugin.
 * Version: 0.0.1
 * Author: Jörn Bungartz
 * Author URI: http://bl-solutions.de
 * License: Copyrighted
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function enqueue_iframe_resizer_script() {
	wp_enqueue_script( 'iFrameResizer', plugin_dir_url( __FILE__ ) . 'iframeResizer.min.js', array(), '2.8.6', true );
}

function call_iframe_resize() {
	echo <<<END
	<script type="text/javascript">
		jQuery(document).ready(function(){
			jQuery('iframe').iFrameResize();
		});	
	</script>
END;
}

add_action('wp_enqueue_scripts', 'enqueue_iframe_resizer_script');
add_action('wp_footer', 'call_iframe_resize', 100);

?>