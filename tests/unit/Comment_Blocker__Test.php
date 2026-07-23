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
		$GLOBALS['stub_wp_options']->kama_spamblock__nonce_field_names = [ 'spamblock_code:' . time() ];
		$_POST = [];
	}

	public function tearDown(): void {
		$GLOBALS['stub_wp_options'] = $this->original_options;
		$_POST = $this->original_post;

		parent::tearDown();
	}

	public function test__ensure_hash_keeps_hash_or_hashes_plain_key(): void {
		$hash = '0123456789abcdef0123456789abcdef';
		$call = Closure::bind( fn( $key ) => Comment_Blocker::ensure_hash( $key ), null, Comment_Blocker::class );

		$this->assertSame( $hash, $call( $hash ) );
		$this->assertSame( md5( 'plain-key' ), $call( 'plain-key' ) );
	}

	public function test__regular_comment_with_current_code_is_allowed(): void {
		$_POST['spamblock_code'] = $this->plain_token( 123 );
		$_POST['spamblock_code_time'] = '3';

		$this->blocker()->block_spam( [ 'comment_type' => 'comment', 'comment_post_ID' => 123 ] );

		$this->assertTrue( true );
	}

	public function test__regular_comment_without_current_code_is_blocked_with_retry_form(): void {
		$_POST = [ 'comment' => 'Keep me', 'comment_post_ID' => '123', 'spamblock_code' => 'invalid' ];

		WP_Mock::userFunction( 'wp_die' )
			->once()
			->with(
				\Mockery::on( static function ( string $html ): bool {
					return false !== strpos( $html, 'Antispam block your comment!' )
						&& false !== strpos( $html, 'name="spamblock_code"' )
						&& false !== strpos( $html, 'name="spamblock_code_time"' )
						&& false !== strpos( $html, "form.elements['spamblock_code_time']" )
						&& false !== strpos( $html, 'name="comment"' )
						&& false !== strpos( $html, '<button type="button">' )
						&& false === strpos( $html, 'name="spamblock_code">invalid' );
				} ),
				'Spam Blocked',
				[ 'response' => 403 ]
			);

		$this->blocker()->block_spam( [ 'comment_type' => 'comment', 'comment_post_ID' => 123 ] );
		$this->assertTrue( true );
	}

	public function test__retry_challenge_has_three_markup_variants(): void {
		$blocker = $this->blocker();
		$render = Closure::bind(
			fn( string $token, int $variant ) => $this->render_challenge_html( $token, $variant ),
			$blocker,
			Comment_Blocker::class
		);
		$token = md5( $this->plain_token( 123 ) );
		$parts = str_split( $token, 11 );

		$this->assertStringContainsString( ">$token<", $render( $token, 3 ) );

		$split = $render( $token, 1 );
		$this->assertStringContainsString( ">$parts[0]<", $split );
		$this->assertStringContainsString( ">$parts[1]<", $split );
		$this->assertStringContainsString( ">$parts[2]<", $split );

		$data = $render( $token, 2 );
		$this->assertStringContainsString( 'data-a="' . $parts[1] . '"', $data );
		$this->assertStringContainsString( 'data-b="' . $parts[2] . '"', $data );
		$this->assertStringContainsString( 'data-c="' . $parts[0] . '"', $data );
		$this->assertStringContainsString( 'el.dataset.c+el.dataset.a+el.dataset.b', $data );
	}

	public function test__main_js_is_not_printed_when_comments_are_closed(): void {
		WP_Mock::userFunction( 'is_singular' )->andReturn( true );
		WP_Mock::userFunction( 'comments_open' )->andReturn( false );

		ob_start();
		$this->blocker()->print_main_js();
		$html = (string) ob_get_clean();

		$this->assertSame( '', $html );
	}

	public function test__main_js_uses_configured_selector_unique_code_field_name_and_form_duration(): void {
		WP_Mock::userFunction( 'is_singular' )->andReturn( true );
		WP_Mock::userFunction( 'comments_open' )->andReturn( true );

		ob_start();
		$this->blocker()->print_main_js();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( "closest( '#send-comment' )", $html );
		$this->assertStringContainsString( 'date.getUTCFullYear()', $html );
		$this->assertStringContainsString( "form.elements['comment_post_ID'].value", $html );
		$this->assertStringContainsString( "utcDate + '|' + postId + '|test-code'", $html );
		$this->assertMatchesRegularExpression( "/\\.name = 'spamblock_code';/", $html );
		$this->assertMatchesRegularExpression( "/\\.name = 'spamblock_code_time';/", $html );
		$this->assertStringContainsString( 'performance.now() - formStart', $html );
	}

	public function test__regular_comment_with_previous_field_name_is_allowed(): void {
		$GLOBALS['stub_wp_options']->kama_spamblock__nonce_field_names = [
			'old_spamblock_code:' . ( time() - 14400 ),
			'spamblock_code:' . time(),
		];
		$_POST['old_spamblock_code'] = $this->plain_token( 123 );
		$_POST['old_spamblock_code_time'] = '3';

		$this->blocker()->block_spam( [ 'comment_type' => 'comment', 'comment_post_ID' => 123 ] );

		$this->assertTrue( true );
	}

	public function test__regular_comment_is_blocked_when_submitted_too_soon(): void {
		$_POST['spamblock_code'] = $this->plain_token( 123 );
		$_POST['spamblock_code_time'] = '2';

		WP_Mock::userFunction( 'wp_die' )
			->once()
			->with( \Mockery::type( 'string' ), 'Spam Blocked', [ 'response' => 403 ] );

		$this->blocker()->block_spam( [ 'comment_type' => 'comment', 'comment_post_ID' => 123 ] );
		$this->assertTrue( true );
	}

	public function test__regular_comment_with_token_for_another_post_is_blocked(): void {
		$_POST['spamblock_code'] = $this->plain_token( 123 );
		$_POST['spamblock_code_time'] = '3';

		WP_Mock::userFunction( 'wp_die' )
			->once()
			->with( \Mockery::type( 'string' ), 'Spam Blocked', [ 'response' => 403 ] );

		$this->blocker()->block_spam( [ 'comment_type' => 'comment', 'comment_post_ID' => 456 ] );
		$this->assertTrue( true );
	}

	public function test__regular_comment_with_token_for_another_date_is_blocked(): void {
		$previous_day = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
		$previous_year = ( (int) gmdate( 'Y' ) - 1 ) . gmdate( '-m-d' );
		$_POST['spamblock_code_time'] = '3';

		WP_Mock::userFunction( 'wp_die' )
			->twice()
			->with( \Mockery::type( 'string' ), 'Spam Blocked', [ 'response' => 403 ] );

		foreach( [ $previous_day, $previous_year ] as $date ){
			$_POST['spamblock_code'] = $this->plain_token( 123, $date );
			$this->blocker()->block_spam( [ 'comment_type' => 'comment', 'comment_post_ID' => 123 ] );
		}

		$this->assertTrue( true );
	}

	public function test__init_creates_a_field_name_when_history_is_empty(): void {
		unset( $GLOBALS['stub_wp_options']->kama_spamblock__nonce_field_names );
		WP_Mock::userFunction( 'wp_generate_uuid4' )->once()->andReturn( 'field-name' );
		WP_Mock::userFunction( 'update_option' )
			->once()
			->with(
				'kama_spamblock__nonce_field_names',
				\Mockery::on( static function ( array $field_names ): bool {
					return 1 === count( $field_names )
						&& 1 === preg_match( '/^[a-z][a-f0-9]{10,20}:[0-9]+$/', $field_names[0] );
				} )
			)
			->andReturn( true );

		$blocker = new Comment_Blocker( new Options() );
		$blocker->init();

		$this->assertTrue( true );
	}

	public function test__init_rotates_expired_name_and_keeps_ten_latest_entries(): void {
		$field_names = [];
		for( $i = 1; $i <= 10; $i++ ){
			$field_names[] = "field$i:" . ( time() - 14400 );
		}
		$GLOBALS['stub_wp_options']->kama_spamblock__nonce_field_names = $field_names;

		WP_Mock::userFunction( 'wp_generate_uuid4' )->once()->andReturn( 'another-field-name' );
		WP_Mock::userFunction( 'update_option' )
			->once()
			->with(
				'kama_spamblock__nonce_field_names',
				\Mockery::on( static function ( array $saved ): bool {
					return 10 === count( $saved )
						&& 0 === strpos( $saved[0], 'field2:' )
						&& 1 === preg_match( '/^[a-z][a-f0-9]{10,20}:[0-9]+$/', $saved[9] );
				} )
			)
			->andReturn( true );

		$blocker = new Comment_Blocker( new Options() );
		$blocker->init();

		$this->assertTrue( true );
	}

	private function blocker(): Comment_Blocker {
		$blocker = new Comment_Blocker( new Options() );
		$blocker->init();

		return $blocker;
	}

	private function plain_token( int $post_id, ?string $date = null ): string {
		return ( $date ?? gmdate( 'Y-m-d' ) ) . "|$post_id|test-code";
	}

}
