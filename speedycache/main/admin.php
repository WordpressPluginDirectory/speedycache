<?php

namespace SpeedyCache;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

use \SpeedyCache\Util;

class Admin{
	
	static $conflicting_plugins = [];
	
	static function hooks(){
		add_action('admin_notices', '\SpeedyCache\Admin::combitibility_notice');
		add_action('admin_menu', '\SpeedyCache\Admin::list_menu');
		//add_action('wp_before_admin_bar_render', '\SpeedyCache\Admin::admin_bar');
		do_action('speedycache_pro_admin_hooks'); // adds hooks for the pro-version.
		add_action('admin_post_speedycache_delete_cache', '\SpeedyCache\Admin::delete_cache');
		add_action('admin_post_speedycache_delete_single', '\SpeedyCache\Admin::delete_single');
		
		$post_types = ['post', 'page', 'category', 'tag'];

		foreach($post_types as $post_type){
			add_filter($post_type.'_row_actions', '\SpeedyCache\Admin::delete_link', 10, 2 );
		}

	}
	
	static function list_menu(){
		global $speedycache;

		$capability = 'activate_plugins';

		//$speedycache->settings['disabled_tabs'] = apply_filters('speedycache_disabled_tabs', []);
		
		$hooknames[] = add_menu_page('SpeedyCache Settings', 'SpeedyCache', $capability, 'speedycache', '\SpeedyCache\Settings::base', SPEEDYCACHE_URL.'/assets/images/icon.svg');
	
		foreach($hooknames as $hookname){
			add_action('load-'.$hookname, '\SpeedyCache\Admin::load_assets');
		}
	}
	
	static function load_assets(){
		add_action('admin_enqueue_scripts', '\SpeedyCache\Admin::enqueue_scripts');
	}

	// Enqueues Admin CSS on load of the page
	static function enqueue_scripts(){
		wp_enqueue_style('speedycache-admin', SPEEDYCACHE_URL.'/assets/css/admin.css', [], SPEEDYCACHE_VERSION);
		wp_enqueue_script('speedycache-admin', SPEEDYCACHE_URL . '/assets/js/admin.js', [], SPEEDYCACHE_VERSION);
		
		wp_localize_script('speedycache-admin', 'speedycache_ajax', [
			'url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('speedycache_ajax_nonce'),
			'premium' => defined('SPEEDYCACHE_PRO'),
		]);
	}
	
	// Post action to delete cache through Manage cache options.
	static function delete_cache(){
		check_admin_referer('speedycache_post_nonce');
		
		if(!current_user_can('manage_options')){
			wp_die(esc_html__('You do not have a required privilege', 'speedycache'));
		}

		$delete['minified'] = isset($_POST['minified']);
		$delete['font'] = isset($_POST['font']);
		$delete['gravatars'] = isset($_POST['gravatars']);
		$delete['domain'] = isset($_POST['domain']);
		$delete['preload'] = isset($_POST['preload_cache']);

		\SpeedyCache\Delete::run($delete);
		$redirect_to = esc_url_raw(wp_unslash($_POST['_wp_http_referer']));

		wp_safe_redirect($redirect_to);
		die();
	}
	
	static function delete_single(){
		check_admin_referer('speedycache_post_nonce', 'security');
		
		if(!current_user_can('manage_options')){
			wp_die(esc_html__('You do not have a required privilege', 'speedycache'));
		}

		$post_id = Util::sanitize_get('post_id');
		\SpeedyCache\Delete::cache($post_id);

		$redirect_to = esc_url_raw(wp_unslash($_REQUEST['referer']));

		wp_safe_redirect($redirect_to);
		die();
	}
	
	static function combitibility_notice(){
		
		$incompatible_plugins = [
			'wp-rocket/wp-rocket.php' => 'WP Rocket',
			'wp-super-cache/wp-cache.php' => 'WP Super Cache',
			'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
			'swift-performance-lite/performance.php' => 'Swift Performance Lite',
			'swift-performance/performance.php' => 'Swift Performance',
			'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
			'wp-optimize/wp-optimize.php' => 'WP Optimize',
			'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
		];

		$conflicting_plugins = [];
		foreach($incompatible_plugins as $plugin_path => $plugin_name){
			if(is_plugin_active($plugin_path)){
				$conflicting_plugins[] = $plugin_name;
			}
		}
		
		if(empty($conflicting_plugins)){
			return;
		}
		
		echo '<div class="notice notice-warning is-dismissible">
		<h3>Conflicting Plugins</h3>
        <p>'.esc_html__('You have activated plugins that conflict with SpeedyCache. We recommend deactivating these plugins to ensure SpeedyCache functions properly.', 'speedycache').'</p>
		<ol>';

		foreach($conflicting_plugins as $plugin){
			echo '<li>'.esc_html($plugin).'</li>';
		}

		echo '</ol></div>';
	}
	
	static function delete_link($actions, $post){
		if(!current_user_can('manage_options')){
			return;
		}
		
		$request_url   = remove_query_arg( '_wp_http_referer' );

		$actions['speedycache_delete'] = '<a href="'.admin_url('admin-post.php?action=speedycache_delete_single&post_id='.$post->ID.'&security='.wp_create_nonce('speedycache_post_nonce')).'&referer='.esc_url($request_url).'">'.esc_html__('Delete Cache', 'speedycache').'</a>';
		
		return $actions;
	}
	
	static function admin_bar(){
		global $wp_admin_bar, $pagenow;
		
		$wp_admin_bar->add_node(array(
			'id'    => 'speedycache-adminbar',
			'title' => __('SpeedyCache', 'speedycache'),
		));

		$wp_admin_bar->add_menu(array(
			'id'    => 'speedycache-adminbar-delete-all',
			'title' => __('Delete all Cache', 'speedycache'),
			'parent' => 'speedycache-adminbar',
			'meta' => ['class' => 'speedycache-adminbar-options']
		));

		if(!is_admin()){
			$wp_admin_bar->add_menu(array(
				'id'    => 'speedycache-adminbar-delete',
				'parent' => 'speedycache-adminbar',
				'title' => __('Clean this Page', 'speedycache'),
				'meta' => ['class' => 'speedycache-adminbar-options']
			));
		}
	}
}
