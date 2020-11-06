<?php
/**
 * Plugin Name: Really Simple SSL
 * Plugin URI: https://www.really-simple-ssl.com
 * Description: Lightweight plugin without any setup to make your site SSL proof
 * Version: 4.0.0
 * Text Domain: really-simple-ssl
 * Domain Path: /languages
 * Author: Really Simple Plugins
 * Author URI: https://really-simple-plugins.com
 * License: GPL2
 */
/*  Copyright 2014  Rogier Lankhorst  (email : rogier@rogierlankhorst.com)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
defined('ABSPATH') or die("you do not have access to this page!");

if (!function_exists('rsssl_activation_check')) {
	/**
	 * Checks if the plugin can safely be activated, at least php 5.6 and wp 4.8
	 */
	function rsssl_activation_check()
	{
		if (version_compare(PHP_VERSION, '5.6', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(__('Really Simple SSL cannot be activated. The plugin requires PHP 5.6 or higher', 'really-simple-ssl'));
		}

		global $wp_version;
		if (version_compare($wp_version, '4.8', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(__('Really Simple SSL cannot be activated. The plugin requires WordPress 4.8 or higher', 'really-simple-ssl'));
		}
	}
	register_activation_hook( __FILE__, 'rsssl_activation_check' );
}

class REALLY_SIMPLE_SSL
{
	private static $instance;
	public $rsssl_front_end;
	public $rsssl_mixed_content_fixer;
	public $rsssl_multisite;
	public $rsssl_cache;
	public $rsssl_server;
	public $really_simple_ssl;
	public $rsssl_help;
	public $rsssl_certificate;

	private function __construct()
	{
	}

	public static function instance()
	{
		if (!isset(self::$instance) && !(self::$instance instanceof REALLY_SIMPLE_SSL)) {
			self::$instance = new REALLY_SIMPLE_SSL;
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->rsssl_front_end = new rsssl_front_end();
			self::$instance->rsssl_mixed_content_fixer = new rsssl_mixed_content_fixer();

			$wpcli = defined( 'WP_CLI' ) && WP_CLI;

			if (is_admin() || is_multisite() || $wpcli || defined('RSSSL_DOING_SYSTEM_STATUS')) {
				if (is_multisite()) {
					self::$instance->rsssl_multisite = new rsssl_multisite();
				}
				self::$instance->rsssl_cache = new rsssl_cache();
				self::$instance->rsssl_server = new rsssl_server();
				self::$instance->really_simple_ssl = new rsssl_admin();
				self::$instance->rsssl_help = new rsssl_help();
				self::$instance->rsssl_certificate = new rsssl_certificate();
				self::$instance->rsssl_site_health = new rsssl_site_health();

				if ( $wpcli ) {
					self::$instance->rsssl_wp_cli = new rsssl_wp_cli();
				}
			}
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function setup_constants()
	{
		define('rsssl_url', plugin_dir_url(__FILE__));
		define('rsssl_path', trailingslashit(plugin_dir_path(__FILE__)));
		define('rsssl_template_path', trailingslashit(plugin_dir_path(__FILE__)).'grid/templates/');
		define('rsssl_plugin', plugin_basename(__FILE__));
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		$debug = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? time() : '';
		$plugin_data = get_plugin_data(__FILE__);
		define('rsssl_version', $plugin_data['Version'] . $debug);
	}

	private function includes()
	{
		require_once(rsssl_path . 'class-front-end.php');
		require_once(rsssl_path . 'class-mixed-content-fixer.php');

		$wpcli = defined( 'WP_CLI' ) && WP_CLI;
		if ( $wpcli ) {
			require_once(rsssl_path . 'class-rsssl-wp-cli.php');
		}

		if (is_admin() || is_multisite() || $wpcli || defined('RSSSL_DOING_SYSTEM_STATUS')) {
			if (is_multisite()) {
				require_once(rsssl_path . 'class-multisite.php');
				require_once(rsssl_path . 'multisite-cron.php');
			}
            require_once(rsssl_path . 'class-admin.php');
			require_once(rsssl_path . 'class-cache.php');
			require_once(rsssl_path . 'class-server.php');
            require_once(rsssl_path . 'class-help.php');
			require_once(rsssl_path . 'class-certificate.php');
			require_once(rsssl_path . 'class-site-health.php');
		}
	}

	private function hooks()
	{
		add_action('admin_notices', array( $this, 'admin_notices'));

		/**
		 * Fire custom hook
		 */
		if ( is_admin() ) {
			do_action('rsssl_admin_init' );
		}

		add_action('wp_loaded', array(self::$instance->rsssl_front_end, 'force_ssl'), 20);
		if (is_admin() || is_multisite()) {
			add_action('plugins_loaded', array(self::$instance->really_simple_ssl, 'init'), 10);
		}
	}

	/**
	 * Notice about possible compatibility issues with add ons
	 */
	public static function admin_notices() {
		//prevent showing the review on edit screen, as gutenberg removes the class which makes it editable.
		$screen = get_current_screen();
		if ( $screen->parent_base === 'edit' ) return;
		if ( self::has_old_addon('really-simple-ssl-pro/really-simple-ssl-pro.php') ||
		     self::has_old_addon('really-simple-ssl-pro-multisite/really-simple-ssl-pro-multisite.php' ) ||
		     self::has_old_addon('really-simple-ssl-social/really-simple-ssl-social.php' )
		) {
			?>
			<div id="message" class="error notice really-simple-plugins">
				<h1><?php echo __("Plugin dependency error","really-simple-ssl-pro");?></h1>
				<p><?php echo __("You have a premium add with a version that is not compatible with the >4.0 release of Really Simple SSL.","really-simple-ssl");?></p>
				<p><?php echo __("Please upgrade to the latest version to be able use the full functionality of the plugin.","really-simple-ssl");?></p>
			</div>
			<?php
		}
	}

	/**
	 * Check if we have a pre 4.0 add on active which should be upgraded
	 * @param $file
	 *
	 * @return bool
	 */
	public static function has_old_addon($file) {
		require_once(ABSPATH.'wp-admin/includes/plugin.php');
		$data = false;
		if (is_plugin_active($file)) $data = get_plugin_data( trailingslashit(WP_PLUGIN_DIR) . $file, false, false );
		if ($data && version_compare($data['Version'], '4.0.0', '<')) {
			return true;
		}
		return false;
	}
}

function RSSSL()
{
	return REALLY_SIMPLE_SSL::instance();
}

add_action('plugins_loaded', 'RSSSL', 8);
