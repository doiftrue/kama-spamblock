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
 * Requires PHP: 7.0
 * Requires at least: 5.7
 *
 * Version: 1.8.3
 */

require_once __DIR__ . '/Kama_Spamblock.php';
require_once __DIR__ . '/Kama_Spamblock_Options.php';

add_action( 'init', 'kama_spamblock_init', 11 );

function kama_spamblock_init() {
	return kama_spamblock()->init_plugin();
}

function kama_spamblock(): Kama_Spamblock {
	static $inst;

	$inst || $inst = new Kama_Spamblock( __FILE__, new Kama_Spamblock_Options() );

	return $inst;
}
