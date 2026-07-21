<?php

namespace Kama_Spamblock;

class Trackback_Blocker {

	public function block_spam( array $commentdata ): void {
		if( ! in_array( $commentdata['comment_type'], [ 'trackback', 'pingback' ], true ) ){
			return;
		}

		$comment_author_url = $commentdata['comment_author_url'] ?? '';
		if( ! is_string( $comment_author_url ) ){
			$this->block_no_backlink();
			return;
		}

		$response = wp_safe_remote_get( $comment_author_url, [
			'timeout'             => 5,
			'redirection'         => 2,
			'limit_response_size' => 1024 * 1024,
		] );

		if( is_wp_error( $response ) ){
			$this->block_no_backlink();
			return;
		}

		$external_html = wp_remote_retrieve_body( $response );

		if( ! $this->has_backlink( $external_html ) ){
			$this->block_no_backlink();
		}
	}

	private function has_backlink( string $html ): bool {
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$quoted_home_url = preg_quote( $home_host, '~' );

		return (bool) preg_match( "~<a[^>]+href=['\"](https?:)?//(www\.)?$quoted_home_url(?=[:/?#'\"\\s>])~si", $html );
	}

	private function block_no_backlink(): void {
		/** @noinspection ForgottenDebugOutputInspection */
		wp_die( 'No backlink.', 'Spam Blocked', [ 'response' => 403 ] );
	}

}
