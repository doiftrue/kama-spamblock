<?php

namespace Kama_Spamblock;

use Kama_Spamblock\Guards\Comment_Blocker;
use Kama_Spamblock\Guards\Trackback_Blocker;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/src/Options.php';
require_once dirname( __DIR__, 2 ) . '/src/Guards/Comment_Blocker.php';
require_once dirname( __DIR__, 2 ) . '/src/Guards/Trackback_Blocker.php';
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
	private bool $had_current_screen;
	private $original_current_screen;
	private bool $had_actions;
	private $original_actions;
	private bool $had_filters;
	private $original_filters;

	public function setUp(): void {
		$this->original_options = clone $GLOBALS['stub_wp_options'];
		$this->had_current_screen = array_key_exists( 'current_screen', $GLOBALS );
		$this->original_current_screen = $GLOBALS['current_screen'] ?? null;
		$this->had_actions = array_key_exists( 'kama_spamblock_test_actions', $GLOBALS );
		$this->original_actions = $GLOBALS['kama_spamblock_test_actions'] ?? null;
		$this->had_filters = array_key_exists( 'kama_spamblock_test_filters', $GLOBALS );
		$this->original_filters = $GLOBALS['kama_spamblock_test_filters'] ?? null;
		$GLOBALS['stub_wp_options']->ks_options = [
			'sibmit_button_id' => 'submit',
		];
		$GLOBALS['kama_spamblock_test_actions'] = [];
		$GLOBALS['kama_spamblock_test_filters'] = [];
	}

	public function tearDown(): void {
		$GLOBALS['stub_wp_options'] = $this->original_options;
		$this->restore_global( 'current_screen', $this->had_current_screen, $this->original_current_screen );
		$this->restore_global( 'kama_spamblock_test_actions', $this->had_actions, $this->original_actions );
		$this->restore_global( 'kama_spamblock_test_filters', $this->had_filters, $this->original_filters );
	}

	public function test__constructor_exposes_plugin_paths_and_services(): void {
		$plugin = new Plugin( '/plugins/kama-spamblock/kama-spamblock.php' );

		$this->assertSame( '/plugins/kama-spamblock/kama-spamblock.php', $plugin->main_file );
		$this->assertSame( '/plugins/kama-spamblock', $plugin->dir );
		$this->assertInstanceOf( Options::class, $plugin->opt );
		$this->assertInstanceOf( Comment_Blocker::class, $plugin->comment_blocker );
		$this->assertInstanceOf( Trackback_Blocker::class, $plugin->trackback_blocker );
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
			[ [ 'wp_footer', [ $plugin->comment_blocker, 'print_main_js' ], 0 ] ],
			$GLOBALS['kama_spamblock_test_actions']
		);
		$this->assertSame(
			[ [ 'preprocess_comment', [ $plugin, 'block_spam' ], 0 ] ],
			$GLOBALS['kama_spamblock_test_filters']
		);
	}

	public function test__block_spam_delegates_to_both_blockers_and_returns_commentdata(): void {
		$plugin = new Plugin( '/plugins/kama-spamblock/kama-spamblock.php' );
		$commentdata = [ 'comment_type' => 'custom', 'comment_content' => 'Hello' ];

		$plugin->trackback_blocker = new class extends Trackback_Blocker {
			public array $calls = [];

			public function block_spam( array $commentdata ): void {
				$this->calls[] = $commentdata;
			}
		};
		$plugin->comment_blocker = new class( $plugin->opt ) extends Comment_Blocker {
			public array $calls = [];

			public function block_spam( array $commentdata ): void {
				$this->calls[] = $commentdata;
			}
		};

		$this->assertSame( $commentdata, $plugin->block_spam( $commentdata ) );
		$this->assertSame( [ $commentdata ], $plugin->trackback_blocker->calls );
		$this->assertSame( [ $commentdata ], $plugin->comment_blocker->calls );
	}

	public function test__settings_link_appends_discussion_settings_link(): void {
		$links = Plugin::settings_link( [ '<a href="plugins.php">Plugins</a>' ] );

		$this->assertSame( '<a href="plugins.php">Plugins</a>', $links[0] );
		$this->assertStringContainsString( '/options-discussion.php#wpfooter', $links[1] );
		$this->assertStringContainsString( '>Settings</a>', $links[1] );
	}

	private function restore_global( string $key, bool $had_value, $value ): void {
		if( $had_value ){
			$GLOBALS[ $key ] = $value;
			return;
		}

		unset( $GLOBALS[ $key ] );
	}
}
