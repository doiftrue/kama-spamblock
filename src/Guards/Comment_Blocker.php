<?php

namespace Kama_Spamblock\Guards;

use Kama_Spamblock\Options;

class Comment_Blocker {

	/**
	 * `comment` for WP 5.5+
	 *
	 * @var string[]
	 */
	private array $comment_types = [ '', 'comment' ];

	private Options $opt;
	private string $nonce;
	private string $nonce_field_name;

	public function __construct( Options $opt ) {
		$this->opt = $opt;
		$this->nonce = self::make_hash( gmdate( 'jn' ) . $opt->unique_code );
	}

	public function init(): void {
		$this->nonce_field_name = 'ksbn_code';
	}

	public function block_spam( array $commentdata ): void {
		/**
		 * Allows filtering comment types to process.
		 *
		 * @param string[] $comment_types Array of comment types to process. Default: `['', 'comment']`.
		 */
		$comment_types = apply_filters( 'kama_spamblock__process_comment_types', $this->comment_types );

		if( ! in_array( $commentdata['comment_type'], $comment_types, true ) ) {
			return;
		}

		$ksbn_code = $_POST[ $this->nonce_field_name ] ?? '';
		$ksbn_code = is_string( $ksbn_code ) ? trim( wp_unslash( $ksbn_code ) ) : '';

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
		$field_name = $this->nonce_field_name;
		?>
		<h1><?= __( 'Antispam block your comment!', 'kama-spamblock' ) ?></h1>

		<style>
			.kama-spamblock-form { max-width:45rem; margin: auto; }
			.kama-spamblock-form textarea { display:none; }
			.kama-spamblock-form textarea[name="<?= $field_name ?>"] { display:block; width:100%; height:3em; box-sizing:border-box; margin:1.5em 0; }
			.kama-spamblock-form button { height:70px; width:100%; font-size:150%; cursor:pointer; border:none; color:#fff; background:#555; }
		</style>

		<form class="kama-spamblock-form" method="POST" action="<?= site_url( '/wp-comments-post.php' ) ?>">
			<p>
				<?= strtr(
					__( 'Replace the value in the field below with {CODE} and click the button.', 'kama-spamblock' ), [
					'{CODE}' => "<$field_name>" . esc_html( $this->nonce ) . "</$field_name>"
				] ) ?>
			</p>

			<?php
			// we set value here just to the field has any value to make similar to other fields (this val never used)
			$fields = [
				sprintf( '<textarea name="%s">%s</textarea>', $field_name, esc_textarea( (string) ( $_POST['comment_post_ID'] ?? '' ) ) )
			];
			foreach( $_POST as $key => $val ){
				if( $key === $field_name || ! is_string( $val ) ){
					continue;
				}

				$fields[] = sprintf( '<textarea name="%s">%s</textarea>', esc_attr( $key ), esc_textarea( wp_unslash( $val ) ) );
			}

			shuffle( $fields );
			$fields = implode( "\n", $fields );
			echo $fields;
			?>

			<button type="submit"><?= __( 'Send comment again', 'kama-spamblock' ) ?></button>
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

		$selector = '#' . esc_html( sanitize_html_class( $this->opt->sibmit_button_id ) );
		$uniqcode = esc_html( Options::sanitize_uniue_code( $this->opt->unique_code ) );
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
					input.name = '<?= $this->nonce_field_name ?>';
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
	private static function make_hash( string $key ): string {
		return preg_match( '/^[a-f0-9]{32}$/', $key ) ? $key : md5( $key );
	}

}
