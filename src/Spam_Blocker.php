<?php

namespace Kama_Spamblock;

class Spam_Blocker {

	/**
	 * `comment` for WP 5.5+
	 *
	 * @var string[]
	 */
	private array $regular_comment_types = [ '', 'comment' ];

	private string $nonce;

	public function __construct( Options $opt ) {
		$this->nonce = self::make_hash( gmdate( 'jn' ) . $opt->unique_code );
	}

	/**
	 * Check and block comment if needed.
	 */
	public function block_spam( array $commentdata ): array {
		$this->block_pings_trackbacks( $commentdata );
		$this->block_regular_comment( $commentdata );

		return $commentdata;
	}

	private function block_pings_trackbacks( $commentdata ): void {
		if( ! in_array( $commentdata['comment_type'], [ 'trackback', 'pingback' ], true ) ){
			return;
		}

		$external_html = wp_remote_retrieve_body( wp_remote_get( $commentdata['comment_author_url'] ) );

		$quoted_home_url = preg_quote( parse_url( home_url(), PHP_URL_HOST ), '~' );
		$has_backlink = preg_match( "~<a[^>]+href=['\"](https?:)?//(www\.)?$quoted_home_url~si", $external_html );

		if( ! $has_backlink ){
			die( 'no backlink.' );
		}
	}

	private function block_regular_comment( $commentdata ): void {
		/**
		 * Allowes to filter comment types to process.
		 *
		 * @param string[] $comment_types Array of comment types to process. Default: `['', 'comment']`.
		 */
		$comment_types = apply_filters( 'kama_spamblock__process_comment_types', $this->regular_comment_types );

		if( ! in_array( $commentdata['comment_type'], $comment_types, true ) ) {
			return;
		}

		$ksbn_code = trim( $_POST['ksbn_code'] ?? '' );

		if( self::make_hash( $ksbn_code ) !== $this->nonce ){
			/** @noinspection ForgottenDebugOutputInspection */
			wp_die( $this->block_form(), 'Spam Blocked', [ 'response' => 403 ] );
		}
	}

	/**
	 * Gets Form HTML for blocked comment.
	 */
	private function block_form(): string {
		ob_start();
		?>
		<h1><?= __( 'Antispam block your comment!', 'kama-spamblock' ) ?></h1>

		<form method="POST" action="<?= site_url( '/wp-comments-post.php' ) ?>">
			<p>
				<?= sprintf(
					__( 'Copy %1$s to the field %2$s and press button', 'kama-spamblock' ),
					'<code style="background:rgba(255,255,255,.2);">' . esc_html( $this->nonce ) . '</code>',
					'<input type="text" name="ksbn_code" value="" style="width:150px; border:1px solid #ccc; border-radius:3px; padding:.3em;" />'
				) ?>
			</p>

			<input type="submit" style="height:70px; width:100%; font-size:150%; cursor:pointer; border:none; color:#fff; background:#555;" value="<?= __( 'Send comment again', 'kama-spamblock' ) ?>" />

			<?php
			foreach( $_POST as $key => $val ){
				if( $key === 'ksbn_code' ){
					continue;
				}

				echo sprintf( '<textarea style="display:none;" name="%s">%s</textarea>',
					esc_attr( $key ),
					esc_textarea( stripslashes( $val ) )
				);
			}
			?>
		</form>
		<?php
		return ob_get_clean();
	}

	public function print_main_js(): void {
		global $post;

		// note: is_singular() may work incorrectly
		if ( ! is_singular() || ! comments_open( $post ) ) {
			return;
		}

		$selector = '#' . esc_html( sanitize_html_class( plugin()->opt->sibmit_button_id ) );
		$uniqcode = esc_html( Options::sanitize_uniue_code( plugin()->opt->unique_code ) );
		?>
		<script id="kama_spamblock">
			window.addEventListener( 'DOMContentLoaded', function() {
				document.addEventListener( 'mousedown', handleSubmit );
				document.addEventListener( 'touchstart', handleSubmit );
				document.addEventListener( 'keypress', handleSubmit );

				function handleSubmit( ev ){
					let sbmt = ev.target.closest( '<?= $selector ?>' );
					if( ! sbmt ){
						return;
					}

					let input = document.createElement( 'input' );
					let date = new Date();

					input.value = ''+ date.getUTCDate() + (date.getUTCMonth() + 1) + '<?= $uniqcode ?>';
					input.name = 'ksbn_code';
					input.type = 'hidden';

					sbmt.parentNode.insertBefore( input, sbmt );
				}
			} );
		</script>
		<?php
	}

	/**
	 * Creates hash from specified key if it's not hashed yet.
	 */
	public static function make_hash( string $key ): string {
		return preg_match( '/^[a-f0-9]{32}$/', $key ) ? $key : md5( $key );
	}

}
