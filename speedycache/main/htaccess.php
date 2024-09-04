<?php

namespace SpeedyCache;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class Htaccess {

	static function init(){

		if(!empty($_SEVRER['SERVER_SOFTWARE'])){
			$server_name = sanitize_text_field(wp_unslash($_SEVRER['SERVER_SOFTWARE']));

			if(!empty($server_name) && (preg_match('/nginx/i', $server_name) || preg_match('/iis/i', $server_name))){
				return;
			}
		}

		$htaccess_file = ABSPATH . '/.htaccess';

		if(!file_exists($htaccess_file)){
			return false;
		}

		if(!is_writable($htaccess_file)){
			return;
		}

		$htaccess_content = file_get_contents($htaccess_file);

		$htaccess_rules = '';
		self::headers($htaccess_rules);
		self::gzip($htaccess_rules);
		self::browser_cache($htaccess_rules);
		self::webp($htaccess_rules);
		self::serving_rules($htaccess_rules);

		// TODO: Need to add modified time here.
		// Cleaning stuff
		$htaccess_content = preg_replace("/#\s?BEGIN\s?LBCspeedycache.*?#\s?END\s?LBCspeedycache/s", '', $htaccess_content);
		$htaccess_content = preg_replace("/#\s?BEGIN\s?WEBPspeedycache.*?#\s?END\s?WEBPspeedycache/s", '', $htaccess_content);
		$htaccess_content = preg_replace("/#\s?BEGIN\s?Gzipspeedycache.*?#\s?END\s?Gzipspeedycache/s", '', $htaccess_content);
		$htaccess_content = preg_replace("/#\s?BEGIN\s?SpeedyCacheheaders.*?#\s?END\s?SpeedyCacheheaders/s", '', $htaccess_content);
		$htaccess_content = preg_replace("/#\s?BEGIN\s?speedycache.*?#\s?END\s?speedycache/s", '', $htaccess_content);
		$htaccess_content = $htaccess_rules ."\n" . trim($htaccess_content);

		file_put_contents($htaccess_file, $htaccess_content);

	}
	
	static function serving_rules(&$htaccess_rules){
		global $speedycache;
		
		// Admin cookie rule
		$admin_users = get_users(['role' => 'administrator', 'fields' => 'user_login']);
		if(!empty($admin_users) && is_array($admin_users)){
			$user_names = '';
			$user_names = implode('|', $admin_users);
			$user_names = preg_replace("/\s/", "\s", $user_names);
			$admin_cookie = 'RewriteCond %{HTTP:Cookie} !wordpress_logged_in_[^\=]+\='.$user_names;
		}

		$htaccess_rules .= '# BEGIN speedycache
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /';
		
		if(!empty($speedycache->options['mobile']) && !empty($speedycache->options['mobile_theme'])){
			$htaccess_rules .= '
	RewriteCond %{REQUEST_METHOD} GET
	RewriteCond %{HTTP_USER_AGENT} !(Mediatoolkitbot|facebookexternalhit|SpeedyCacheCCSS)
	RewriteCond %{HTTP_USER_AGENT} (Mobile|Android|Silk\/|Kindle|Opera\sMini|BlackBerry|Opera\sMobi) [NC]
	RewriteCond %{HTTP:Cookie} !comment_author_
	'.$admin_cookie.'
	RewriteCond %{REQUEST_URI} !(\/){2}$
	RewriteCond %{REQUEST_URI} !^/(wp-(?:admin|login|register|comments-post|cron|json))/ [NC]
	RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/speedycache/%{HTTP_HOST}/mobile-cache%{REQUEST_URI}/index.html -f
	RewriteRule ^(.*) /wp-content/cache/speedycache/%{HTTP_HOST}/mobile-cache%{REQUEST_URI}/index.html [L]'."\n";
		}

$htaccess_rules .= '
	RewriteCond %{REQUEST_METHOD} GET
	RewriteCond %{HTTP_USER_AGENT} !(Mediatoolkitbot|facebookexternalhit|SpeedyCacheCCSS)
	RewriteCond %{HTTP:Cookie} !comment_author_
	'.$admin_cookie."\n";

	if(!empty($speedycache->options['mobile'])){
		$htaccess_rules .= '
	RewriteCond %{HTTP_USER_AGENT} !(Mobile|Android|Silk\/|Kindle|Opera\sMini|BlackBerry|Opera\sMobi) [NC]' . "\n";
	}

	$htaccess_rules .= '
	RewriteCond %{REQUEST_URI} !(\/){2}$
	RewriteCond %{REQUEST_URI} !^/(wp-(?:admin|login|register|comments-post|cron|json))/ [NC]
	RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/speedycache/%{HTTP_HOST}/all%{REQUEST_URI}/index.html -f
	RewriteRule ^(.*) /wp-content/cache/speedycache/%{HTTP_HOST}/all%{REQUEST_URI}/index.html [L]
</IfModule>
# END speedycache' . PHP_EOL;
	}
	
	static function browser_cache(&$htaccess_rules){
		global $speedycache;

		if(empty($speedycache->options['lbc'])){
			return;
		}

		$htaccess_rules .= '# BEGIN LBCspeedycache
<IfModule mod_expires.c>
	ExpiresActive on
	ExpiresDefault A0
	ExpiresByType text/css A10368000
	ExpiresByType text/javascript A10368000
	ExpiresByType font/ttf A10368000
	ExpiresByType font/otf A10368000
	ExpiresByType font/woff A10368000
	ExpiresByType font/woff2 A10368000
	ExpiresByType image/jpg A10368000
	ExpiresByType image/jpeg A10368000
	ExpiresByType image/png A10368000
	ExpiresByType image/gif A10368000
	ExpiresByType image/webp A10368000
	ExpiresByType image/x-icon A10368000
	ExpiresByType image/svg+xml A10368000
	ExpiresByType image/vnd.microsoft.icon A10368000
	ExpiresByType video/ogg A10368000
	ExpiresByType video/mp4 A10368000
	ExpiresByType video/webm A10368000
	ExpiresByType audio/ogg A10368000
	ExpiresByType application/pdf A10368000
	ExpiresByType application/javascript A10368000
	ExpiresByType application/x-javascript A10368000
	ExpiresByType application/x-font-ttf A10368000
	ExpiresByType application/x-font-woff A10368000
	ExpiresByType application/font-woff A10368000
	ExpiresByType application/font-woff2 A10368000
	ExpiresByType application/vnd.ms-fontobject A10368000
</IfModule>
# END LBCspeedycache' . PHP_EOL;
	}
	
	static function webp(&$htaccess_rules){
		$htaccess_rules .= '# BEGIN WEBPspeedycache
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteCond %{HTTP_ACCEPT} image/webp
	RewriteCond %{REQUEST_FILENAME} \.(jpe?g|png|gif)$
	RewriteCond %{DOCUMENT_ROOT}/$1.webp -f
	RewriteRule ^(.+)\.(jpe?g|png|gif)$ $1.webp [T=image/webp,L]
</IfModule>
<IfModule mod_headers.c>
  Header append Vary Accept env=REDIRECT_accept
</IfModule>
AddType image/webp .webp
# END WEBPspeedycache' . PHP_EOL;
	}
	
	static function gzip(&$htaccess_rules){
		global $speedycache;

		if(empty($speedycache->options['gzip'])){
			return;
		}

		$htaccess_rules .= '# BEGIN Gzipspeedycache
<IfModule mod_deflate.c>
	AddOutputFilterByType DEFLATE font/opentype
	AddOutputFilterByType DEFLATE font/otf
	AddOutputFilterByType DEFLATE font/ttf
	AddOutputFilterByType DEFLATE font/woff
	AddOutputFilterByType DEFLATE font/woff2
	AddOutputFilterByType DEFLATE text/js
	AddOutputFilterByType DEFLATE text/css
	AddOutputFilterByType DEFLATE text/html
	AddOutputFilterByType DEFLATE text/javascript
	AddOutputFilterByType DEFLATE text/plain
	AddOutputFilterByType DEFLATE text/xml
	AddOutputFilterByType DEFLATE image/svg+xml
	AddOutputFilterByType DEFLATE image/x-icon
	AddOutputFilterByType DEFLATE application/javascript
	AddOutputFilterByType DEFLATE application/x-javascript
	AddOutputFilterByType DEFLATE application/vnd.ms-fontobject
	AddOutputFilterByType DEFLATE application/x-font
	AddOutputFilterByType DEFLATE application/x-font-opentype
	AddOutputFilterByType DEFLATE application/x-font-otf
	AddOutputFilterByType DEFLATE application/x-font-truetype
	AddOutputFilterByType DEFLATE application/x-font-ttf
	AddOutputFilterByType DEFLATE application/font-woff2
	AddOutputFilterByType DEFLATE application/xhtml+xml
	AddOutputFilterByType DEFLATE application/xml
	AddOutputFilterByType DEFLATE application/rss+xml
</IfModule>
# END Gzipspeedycache'. PHP_EOL;
	}
	
	static function headers(&$htaccess_rules){
		$url = site_url();
		$parsed_url = wp_parse_url($url);

		$htaccess_rules .= '# BEGIN SpeedyCacheheaders
FileETag None
<IfModule mod_headers.c>
	Header unset ETag
</IfModule>
<FilesMatch "\.(html)$">
<IfModule mod_headers.c>
	Header set x-speedycache-source "Server"
	Header set Cache-Tag "'.$parsed_url['host'].'"
	Header set CDN-Cache-Control "max-age=1296000"
	Header set Cache-Control "public"
	Header unset Pragma
	Header unset Last-Modified
</IfModule>
</FilesMatch>

<FilesMatch "\.(css|htc|js|asf|asx|wax|wmv|wmx|avi|bmp|class|divx|doc|docx|eot|exe|gif|gz|gzip|ico|jpg|jpeg|jpe|json|mdb|mid|midi|mov|qt|mp3|m4a|mp4|m4v|mpeg|mpg|mpe|mpp|otf|odb|odc|odf|odg|odp|ods|odt|ogg|pdf|png|pot|pps|ppt|pptx|ra|ram|svg|svgz|swf|tar|tif|tiff|ttf|ttc|wav|wma|wri|xla|xls|xlsx|xlt|xlw|zip)$">
	<IfModule mod_headers.c>
		Header unset Pragma
		Header set Cache-Control "public"
	</IfModule>
</FilesMatch>
# END SpeedyCacheheaders'. PHP_EOL;
	}

}
