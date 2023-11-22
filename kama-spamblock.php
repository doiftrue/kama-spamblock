<?php
/**
 * Plugin Name: Kama SpamBlock
 *
 * Description: Block spam when comment is posted by a robot. Checks pings/trackbacks for real backlink.
 *
 * Text Domain: kama-spamblock
 * Domain Path: /languages
 *
 * Author: Kama
 * Author URI: https://wp-kama.ru
 * Plugin URI: https://wp-kama.ru/95
 *
 * Version: 1.8.1
 */

require_once __DIR__ . '/class-Kama_Spamblock.php';

add_action( 'init', 'kama_spamblock', 11 );

function kama_spamblock(){
	return Kama_Spamblock::instance();
}

