<?php

namespace Kama_Spamblock;

use Closure;
use Kama_Spamblock\Guards\Comment_Blocker;
use WP_Mock;
use WP_Mock\Tools\TestCase;

require_once dirname( __DIR__, 2 ) . '/src/Options.php';
require_once dirname( __DIR__, 2 ) . '/src/Guards/Comment_Blocker.php';

class Comment_Blocker__Test extends TestCase {

	private object $original_options;
	private array $original_post;

	public function setUp(): void {
		parent::setUp();

		$this->original_options = clone $GLOBALS['stub_wp_options'];
		$this->original_post = $_POST;
		$GLOBALS['stub_wp_options']->ks_options = [
			'sibmit_button_id' => 'send-comment',
			'unique_code'      => 'test-code',
		];
		$_POST = [];
	}

	public function tearDown(): void {
		$GLOBALS['stub_wp_options'] = $this->original_options;
		$_POST = $this->original_post;

		parent::tearDown();
	}

	public function test__make_hash_keeps_hash_or_hashes_plain_key(): void {
		$hash = '0123456789abcdef0123456789abcdef';
		$call = Closure::bind( fn( $key ) => Comment_Blocker::make_hash( $key ), null, Comment_Blocker::class );

		$this->assertSame( $hash, $call( $hash ) );
		$this->assertSame( md5( 'plain-key' ), $call( 'plain-key' ) );
	}

	public function test__regular_comment_with_current_code_is_allowed(): void {
		$_POST['ksbn_code'] = gmdate( 'jn' ) . 'test-code';

		$this->blocker()->block_spam( [ 'comment_type' => 'comment' ] );

		$this->assertTrue( true );
	}

	public function test__regular_comment_without_current_code_is_blocked_with_retry_form(): void {
		$_POST = [ 'comment' => 'Keep me', 'ksbn_code' => 'invalid' ];

		WP_Mock::userFunction( 'wp_die' )
			->once()
			->with(
				\Mockery::on( static function ( string $html ): bool {
					return false !== strpos( $html, 'Antispam block your comment!' )
						&& false !== strpos( $html, 'name="ksbn_code"' )
						&& false !== strpos( $html, 'name="comment"' )
						&& false === strpos( $html, 'name="ksbn_code">invalid' );
				} ),
				'Spam Blocked',
				[ 'response' => 403 ]
			);

		$this->blocker()->block_spam( [ 'comment_type' => 'comment' ] );
		$this->assertTrue( true );
	}

	public function test__main_js_is_not_printed_when_comments_are_closed(): void {
		WP_Mock::userFunction( 'is_singular' )->andReturn( true );
		WP_Mock::userFunction( 'comments_open' )->andReturn( false );

		ob_start();
		$this->blocker()->print_main_js();
		$html = (string) ob_get_clean();

		$this->assertSame( '', $html );
	}

	public function test__main_js_contains_configured_selector_and_unique_code(): void {
		WP_Mock::userFunction( 'is_singular' )->andReturn( true );
		WP_Mock::userFunction( 'comments_open' )->andReturn( true );

		ob_start();
		$this->blocker()->print_main_js();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( "closest( '#send-comment' )", $html );
		$this->assertStringContainsString( "(date.getUTCMonth() + 1) + 'test-code'", $html );
		$this->assertStringContainsString( "input.name = 'ksbn_code'", $html );
	}

	private function blocker(): Comment_Blocker {
		$blocker = new Comment_Blocker( new Options() );
		$blocker->init();

		return $blocker;
	}
}
