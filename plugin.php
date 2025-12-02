<?php
/**
 * Plugin Name:       Power Funnel
 * Plugin URI:        https://github.com/j7-dev/wp-power-funnel
 * Description:       自動抓取 Youtube 直播場次，讓用戶可以透過 LINE 報名
 * Version:           0.0.1
 * Requires at least: 5.7
 * Requires PHP:      8.0
 * Author:            Your Name
 * Author URI:        https://github.com/j7-dev/wp-power-funnel
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       power_funnel
 * Domain Path:       /languages
 * Tags: youtube, funnel, line, marketing, webinar
 */

declare (strict_types = 1);

namespace J7\PowerFunnel;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if ( \class_exists( 'J7\PowerFunnel\Plugin' ) ) {
	return;
}
require_once __DIR__ . '/vendor/autoload.php';

/**
	* Class Plugin
	*/
final class Plugin {
	use \J7\WpUtils\Traits\PluginTrait;
	use \J7\WpUtils\Traits\SingletonTrait;

	/**
	 * Constructor
	 */
	public function __construct() {

		// self::$template_page_names = [ '404' ];

		$this->required_plugins = [
			// [
			// 'name'     => 'WooCommerce',
			// 'slug'     => 'woocommerce',
			// 'required' => true,
			// 'version'  => '7.6.0',
			// ],
			// [
			// 'name'     => 'Powerhouse',
			// 'slug'     => 'powerhouse',
			// 'source'   => ''https://github.com/j7-dev/wp-powerhouse/releases/latest/download/powerhouse.zip',
			// 'version'  => '3.0.0',
			// 'required' => true,
			// ],
		];

		$this->init(
			[
				'app_name'    => 'Power Funnel',
				'github_repo' => 'https://github.com/j7-dev/wp-power-funnel',
				'callback'    => [ Bootstrap::class, 'register_hooks' ],
				'lc'          => 'ZmFsc2',
			]
		);
	}
}

Plugin::instance();
