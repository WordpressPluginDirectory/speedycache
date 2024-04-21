<?php
/*
Plugin Name: SpeedyCache
Plugin URI: https://speedycache.com
Description: SpeedyCache is a plugin that helps you reduce the load time of your website by means of caching, minification, and compression of your website.
Version: 1.1.6
Author: Softaculous Team
Author URI: https://speedycache.com/
Text Domain: speedycache
*/

/*
* SPEEDYCACHE
* https://speedycache.com/
* (c) SpeedyCache Team
*/

/*
SpeedyCache is fork of WP Fastest Cache :
https://wordpress.org/plugins/wp-fastest-cache/
Copyright (C)2013 Emre Vona

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.	
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/


// We need the ABSPATH
if (!defined('ABSPATH')) exit;

if(!function_exists('add_action')){
	echo 'You are not allowed to access this page directly.';
	exit;
}

// If SPEEDYCACHE_VERSION exists then the plugin is loaded already !
if(defined('SPEEDYCACHE_VERSION')) {
	return;
}

define('SPEEDYCACHE_FILE', __FILE__);

include_once(dirname(__FILE__).'/init.php');
