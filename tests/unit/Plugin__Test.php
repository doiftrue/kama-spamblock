<?php

namespace Kama_Spamblock;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/src/Options.php';
require_once dirname( __DIR__, 2 ) . '/src/Spam_Blocker.php';
require_once dirname( __DIR__, 2 ) . '/src/Plugin.php';

function load_plugin_textdomain(): bool {
	return true;
}

function add_action( string $hook, $callback, int $priority = 10 ): void {
	$GLOBALS['kama_spamblock_test_actions'][] = [ $hook, $callback, $priority ];
}

function add_filter( string $hook, $callback, int $priority = 10 ): void {
	$GLOBALS['kama_spamblock_test_filters'][] = [ $hook, $callback, $priority ];
}

class Plugin__Test extends TestCase {

	private object $original_options;
	private $original_current_screen;

	public function setUp(): void {
		$this->original_options = clone $GLOBALS['stub_wp_options'];
		$this->original_current_screen = $GLOBALS['current_screen'] ?? null;
		$GLOBALS['stub_wp_options']->ks_options = [
			'sibmit_button_id' => 'submit',
			'unique_code'      => 'test-code',
		];
		$GLOBALS['kama_spamblock_test_actions'] = [];
		$GLOBALS['kama_spamblock_test_filters'] = [];
	}

	public function tearDown(): void {
		$GLOBALS['stub_wp_options'] = $this->original_options;
		$GLOBALS['current_screen'] = $this->original_current_screen;
		unset( $GLOBALS['kama_spamblock_test_actions'], $GLOBALS['kama_spamblock_test_filters'] );
	}

	public function test__constructor_exposes_plugin_paths_and_services(): void {
		$plugin = new Plugin( '/plugins/kama-spamblock/kama-spamblock.php' );

		$this->assertSame( '/plugins/kama-spamblock/kama-spamblock.php', $plugin->main_file );
		$this->assertSame( '/plugins/kama-spamblock', $plugin->dir );
		$this->assertInstanceOf( Options::class, $plugin->opt );
		$this->assertInstanceOf( Spam_Blocker::class, $plugin->blocker );
	}

	public function test__init_registers_admin_hooks(): void {
		$plugin = new Plugin( '/plugins/kama-spamblock/kama-spamblock.php' );

		$GLOBALS['current_screen'] = new class {
			public function in_admin(): bool {
				return true;
			}
		};

		$plugin->init();

		$this->assertSame(
			[ [ 'admin_init', [ $plugin->opt, 'admin_options' ], 10 ] ],
			$GLOBALS['kama_spamblock_test_actions']
		);
		$this->assertSame(
			[ [
				'plugin_action_links_' . plugin_basename( $plugin->main_file ),
				[ Plugin::class, 'settings_link' ],
				10,
			] ],
			$GLOBALS['kama_spamblock_test_filters']
		);
	}

	public function test__init_registers_front_hooks(): void {
		$plugin = new Plugin( '/plugins/kama-spamblock/kama-spamblock.php' );

		unset( $GLOBALS['current_screen'] );

		$plugin->init();

		$this->assertSame(
			[ [ 'wp_footer', [ $plugin->blocker, 'print_main_js' ], 0 ] ],
			$GLOBALS['kama_spamblock_test_actions']
		);
		$this->assertSame(
			[ [ 'preprocess_comment', [ $plugin->blocker, 'block_spam' ], 0 ] ],
			$GLOBALS['kama_spamblock_test_filters']
		);
	}

	public function test__settings_link_appends_discussion_settings_link(): void {
		$links = Plugin::settings_link( [ '<a href="plugins.php">Plugins</a>' ] );

		$this->assertSame( '<a href="plugins.php">Plugins</a>', $links[0] );
		$this->assertStringContainsString( '/options-discussion.php#wpfooter', $links[1] );
		$this->assertStringContainsString( '>Settings</a>', $links[1] );
	}
}
