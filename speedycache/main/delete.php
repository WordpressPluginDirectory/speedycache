<?php

namespace SpeedyCache;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT');
}

use \SpeedyCache\Util;

class Delete{
	
	static $cache_lifespan = 0;
	
	static function run($actions){
		global $speedycache;
		
		// Even if the actions are empty, the cache will be deleted.
		self::all_cache();
		self::purge_varnish();
		\SpeedyCache\CDN::purge();
		delete_option('speedycache_html_size');
		delete_option('speedycache_assets_size');

		if(empty($actions)){
			return;
		}
		
		if(!empty($actions['minified'])){
			self::minified();
		}
		
		if(!empty($actions['font'])){
			self::local_fonts();
		}
		
		if(!empty($actions['gravatars'])){
			self::gravatar();
		}
		
		if(!empty($actions['domain'])){
			self::all_for_domain();
		}
		
		if(!empty($actions['preload'])){
			if(!empty($speedycache->options['preload'])){
				\SpeedyCache\Preload::build_preload_list();
			}
		}
	}
	
	// Deletes a single page
	static function cache($post_id){
		if(!isset($post_id) || $post_id === FALSE || !is_numeric($post_id)){
			return;
		}
		
		$link = get_permalink($post_id);

		if(empty($link)){
			return;
		}
		
		$parsed_url = wp_parse_url($link);
		$path = $parsed_url['path'];
		
		$file = '';
		if(empty($path) || $path == '/'){
			$file = 'index.html';
		}

		$cache_path = [];
		$cache_path[] = Util::cache_path('all/'.trim($path, '/') . $file);
		if($speedycache->options['mobile_theme']){
			$cache_path[] = Util::cache_path('mobile-cache/'.trim($path, '/') . $file);
		}

		foreach($cache_path as $c_path){
			if(!file_exists($c_path)){
				continue;
			}

			if(is_dir($c_path)){
				self::rmdir($c_path);
				continue;
			}

			unlink($c_path);
		}
		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}

	// Delete cache of whole site
	static function all_cache(){

		// Our cache is saved in 2 file, /all and /mobile-cache
		// We also need to delete Critical CSS too as it gets injected in the HTML
		$deletable_dirs = ['all', 'mobile-cache', 'critical-css'];
		
		foreach($deletable_dirs as $dir){
			$path = Util::cache_path($dir);
			self::rmdir($path);
		}

		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}
	
	// Delete minified and Critical css content.
	static function minified(){
		$assets_cache_path = Util::cache_path('assets');

		if(!file_exists($assets_cache_path)){
			return;
		}
		
		self::rmdir($assets_cache_path);
		
		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}
	
	// Delete local fonts
	static function local_fonts(){
		$fonts_path = Util::cache_path('fonts');
		
		if(!file_exists($fonts_path)){
			return;
		}

		self::rmdir($fonts_path);
		
		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}

	static function gravatar(){
		$gravatar_path = Util::cache_path('gravatars');
		
		if(!file_exists($gravatar_path)){
			return;
		}

		self::rmdir($gravatar_path);
		
		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}
	
	// Delete everything of the current domain, like minfied, cache, gravatar and fonts.
	static function all_for_domain(){
		
	}
	
	static function rmdir($dir){

		if(!file_exists($dir)){
			return;
		}

		$files = array_diff(scandir($dir), ['..', '.']);

		foreach($files as $file){			
			if(is_dir($dir.'/'.$file)){
				self::rmdir($dir.'/'.$file);
				continue;
			}

			unlink($dir.'/'.$file);
		}

		rmdir($dir);
	}
	
	static function purge_varnish(){
		global $speedycache;

		if(empty($speedycache->options['purge_varnish'])){
			return;
		}

		$server = !empty($speedycache->options['varniship']) ? $speedycache->options['varniship'] : '127.0.0.1';
		
		
		$url = home_url();
		$url = parse_url($url);

		if($url == FALSE){
			return;
		}
		
		$sslverify = ($url['scheme'] === 'https') ? true : false;
		$request_url = $url['scheme'] .'://'. $server . '/.*';

		$request_args = array(
			'method'    => 'PURGE',
			'headers'   => array(
				'Host'       => $url['host'],
			),
			'sslverify' => $sslverify,
		);

		$res = wp_remote_request($request_url, $request_args);

		if(is_wp_error($res)){
			$msg = $res->get_error_message();
			return array($msg, 'error');
		}

		if(is_array($res) && !empty($res['response']['code']) && '200' != $res['response']['code']){
			$msg = 'Something Went Wrong Unable to Purge Varnish';
			
			if(empty($res['response']['code']) && '501' == $res['response']['code']){
				$msg = 'Your server dosen\'t allows PURGE request';

				if(!empty($res['headers']['allow'])){
					$msg .= 'The accepted HTTP methods are' . $res['headers']['allow'];
				}
				
				$msg = __('Please contact your hosting provider if, Varnish is enabled and still getting this error', 'speedycache');
			}
			
			return array($msg, 'error');
		}
		
		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
		
		return array(__('Purged Varnish Cache Succesfully', 'speedycache'), 'success');
	}
	
	static function expired_cache(){
		global $speedycache;

		self::$cache_lifespan = Util::cache_lifespan();
		
		// We don't want to clean cache if cache is disabled
		if(empty($speedycache->options['status']) || empty(self::$cache_lifespan)){
			wp_clear_scheduled_hook('speedycache_purge_cache');
			return;
		}

		$cache_path = Util::cache_path('all');
		
		if(!file_exists($cache_path)){
			return;
		}
		
		self::rec_clean_expired($cache_path);
		
		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}
	
	static function rec_clean_expired($path){
		$files = array_diff(scandir($path), array('..', '.'));

		if(empty($files)){
			return;
		}

		foreach($files as $file){
			$file_path = $path . '/'. $file;

			if(is_dir($file_path)){
				self::rec_clean_expired($file_path);
				return;
			}

			if((filemtime($file_path) + self::$cache_lifespan) < time()){
				unlink($file_path);
			}
		}
	}

	function fetch_linked_posts($post_id){
		if(!$post_id){
			return [];
		}

		$current_post_url = get_permalink($post_id);

		// Query posts that might contain a link to the current post
		$args = [
			'post_type'      => 'any',
			'posts_per_page' => 10,
			's'              => $current_post_url, // Search for the URL in the content
		];

		$query = new WP_Query($args);
		
		$linked_posts = [];

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				// Verify that the content actually contains the URL
				if (strpos(get_the_content(), $current_post_url) !== false) {
					$linked_posts[] = get_the_ID();
				}
			}
		}

		wp_reset_postdata();

		return $linked_posts;
	}
}
