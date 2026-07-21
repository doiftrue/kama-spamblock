<?php
/**
 * Plugin Name: Kama SpamBlock
 *
 * Description: Block spam when comment is posted by a robot. Check pings/trackbacks for real backlink.
 *
 * Text Domain: kama-spamblock
 * Domain Path: /languages
 *
 * Author:     Kama
 * Author URI: https://wp-kama.ru
 * Plugin URI: https://wp-kama.ru/95
 *
 * Requires PHP: 7.4
 * Requires at least: 5.7
 *
 * Version: 1.9.0
 */

namespace Kama_Spamblock;

require_once __DIR__ . '/src/Plugin.php';
require_once __DIR__ . '/src/Options.php';
require_once __DIR__ . '/src/Comment_Spam_Blocker.php';
require_once __DIR__ . '/src/Trackback_Spam_Blocker.php';

add_action( 'init', '\Kama_Spamblock\init', 11 );

function init() {
	return plugin()->init();
}

function plugin(): Plugin {
	static $inst;

	$inst || $inst = new Plugin( __FILE__ );

	return $inst;
}
