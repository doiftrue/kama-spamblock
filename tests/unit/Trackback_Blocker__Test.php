<?php

namespace Kama_Spamblock;

use Kama_Spamblock\Guards\Trackback_Blocker;
use WP_Mock;
use WP_Mock\Tools\TestCase;

require_once dirname( __DIR__, 2 ) . '/src/Guards/Trackback_Blocker.php';

class Trackback_Blocker__Test extends TestCase {

	public function test__pingback_with_backlink_is_allowed(): void {
		WP_Mock::userFunction( 'wp_safe_remote_get' )
			->once()
			->with( 'https://source.test/post', [
				'timeout'             => 5,
				'redirection'         => 2,
				'limit_response_size' => 1024 * 1024,
			] )
			->andReturn( [
				'response' => [ 'code' => 200 ],
				'headers'  => [],
				'body'     => '<a href="https://example.test/article">Source</a>',
			] );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test' );

		$this->blocker()->block_spam( [
			'comment_type'       => 'pingback',
			'comment_author_url' => 'https://source.test/post',
		] );

		$this->assertTrue( true );
	}

	public function test__trackback_without_backlink_is_blocked(): void {
		WP_Mock::userFunction( 'wp_safe_remote_get' )
			->andReturn( [
				'response' => [ 'code' => 200 ],
				'headers'  => [ 'content-type' => 'text/html; charset=UTF-8' ],
				'body'     => '<p>No link here</p>',
			] );
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

	public function test__trackback_with_backlink_in_error_response_is_blocked(): void {
		WP_Mock::userFunction( 'wp_safe_remote_get' )
			->andReturn( [
				'response' => [ 'code' => 404 ],
				'headers'  => [ 'content-type' => 'text/html' ],
				'body'     => '<a href="https://example.test/article">Source</a>',
			] );
		WP_Mock::userFunction( 'wp_die' )
			->once()
			->with( 'No backlink.', 'Spam Blocked', [ 'response' => 403 ] );

		$this->blocker()->block_spam( [
			'comment_type'       => 'trackback',
			'comment_author_url' => 'https://source.test/post',
		] );
		$this->assertTrue( true );
	}

	public function test__trackback_with_backlink_in_binary_response_is_blocked(): void {
		WP_Mock::userFunction( 'wp_safe_remote_get' )
			->andReturn( [
				'response' => [ 'code' => 200 ],
				'headers'  => [ 'content-type' => 'application/pdf' ],
				'body'     => '<a href="https://example.test/article">Source</a>',
			] );
		WP_Mock::userFunction( 'wp_die' )
			->once()
			->with( 'No backlink.', 'Spam Blocked', [ 'response' => 403 ] );

		$this->blocker()->block_spam( [
			'comment_type'       => 'trackback',
			'comment_author_url' => 'https://source.test/post',
		] );
		$this->assertTrue( true );
	}

	private function blocker(): Trackback_Blocker {
		return new Trackback_Blocker();
	}
}
