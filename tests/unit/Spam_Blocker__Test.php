<?php

namespace Kama_Spamblock;

use WP_Mock;
use WP_Mock\Tools\TestCase;

require_once dirname( __DIR__, 2 ) . '/src/Options.php';
require_once dirname( __DIR__, 2 ) . '/src/Spam_Blocker.php';

function plugin() {
	return $GLOBALS['kama_spamblock_test_plugin'];
}

class Spam_Blocker__Test extends TestCase {

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
		$GLOBALS['kama_spamblock_test_plugin'] = (object) [
			'opt' => new Options(),
		];
		$_POST = [];
	}

	public function tearDown(): void {
		$GLOBALS['stub_wp_options'] = $this->original_options;
		unset( $GLOBALS['kama_spamblock_test_plugin'] );
		$_POST = $this->original_post;

		parent::tearDown();
	}

	public function test__make_hash_keeps_hash_or_hashes_plain_key(): void {
		$hash = '0123456789abcdef0123456789abcdef';

		$this->assertSame( $hash, Spam_Blocker::make_hash( $hash ) );
		$this->assertSame( md5( 'plain-key' ), Spam_Blocker::make_hash( 'plain-key' ) );
	}

	public function test__regular_comment_with_current_code_is_allowed(): void {
		$_POST['ksbn_code'] = gmdate( 'jn' ) . 'test-code';
		$comment = [ 'comment_type' => 'comment', 'comment_content' => 'Hello' ];

		$result = $this->blocker()->block_spam( $comment );

		$this->assertSame( $comment, $result );
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

	public function test__pingback_with_backlink_is_allowed(): void {
		WP_Mock::userFunction( 'wp_safe_remote_get' )
			->once()
			->with( 'https://source.test/post', [
				'timeout'             => 5,
				'redirection'         => 2,
				'limit_response_size' => 1024 * 1024,
			] )
			->andReturn( [ 'body' => '<a href="https://example.test/article">Source</a>' ] );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test' );

		$comment = [
			'comment_type'       => 'pingback',
			'comment_author_url' => 'https://source.test/post',
		];

		$this->assertSame( $comment, $this->blocker()->block_spam( $comment ) );
	}

	public function test__trackback_without_backlink_is_blocked(): void {
		WP_Mock::userFunction( 'wp_safe_remote_get' )
			->andReturn( [ 'body' => '<p>No link here</p>' ] );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test' );
		WP_Mock::userFunction( 'wp_die' )
			->once()
			->with( 'No backlink.', 'Spam Blocked', [ 'response' => 403 ] );

		$this->blocker()->block_spam( [
			'comment_type'       => 'trackback',
			'comment_author_url' => 'https://source.test/post',
		] );
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

	private function blocker(): Spam_Blocker {
		return new Spam_Blocker( $GLOBALS['kama_spamblock_test_plugin']->opt );
	}
}
