<?php
/**
 * Plugin Name: JWT Cookie f. SimpleCam
 * Plugin URI: http://simplecam.de
 * Description: A brief description of the plugin.
 * Version: 0.0.1
 * Author: Jörn Bungartz
 * Author URI: http://bl-solutions.de
 * License: Copyrighted
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function set_jwt_cookie($user_login, $user) {
	require_once dirname( __FILE__ ) . '/JWT.php';

	$payload = (object) array('id' => $user->ID,
                                 'login' => $user_login, 
                                 'first_name' => $user->first_name,
                                 'last_name' => $user->last_name,
                                 'email' => $user->user_email);
	$jwt = new JWT();
	$cookie = $jwt->encode($payload, AUTH_KEY);
	setcookie(JWT_COOKIE_NAME, $cookie, time() + 14 * DAY_IN_SECONDS, "/", JWT_COOKIE_DOMAIN, false, true);    
}

function reset_jwt_cookie() {
	setcookie(JWT_COOKIE_NAME, '', time() - 3600, "/", JWT_COOKIE_DOMAIN);
	setcookie(DJANGO_SESSION_COOKIE_NAME, '', time() - 3600, "/";
}

add_action('wp_login', 'set_jwt_cookie', 10, 2);
add_action('wp_logout', 'reset_jwt_cookie', 10, 2);

?>