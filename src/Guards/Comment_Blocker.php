<?php

namespace Kama_Spamblock\Guards;

use Kama_Spamblock\Options;

class Comment_Blocker {

	private const TOKEN_NAMES_OPTION = 'kama_spamblock__nonce_field_names';
	private const TOKEN_NAMES_LIMIT  = 10;
	private const TOKEN_NAME_TTL     = 4 * HOUR_IN_SECONDS;

	/** @var string[] `comment` for WP 5.5+ */
	private array $comment_types = [ '', 'comment' ];

	private Options $options;

	/** Token value. */
	private string $token;

	/** Current using token name for field. */
	private string $token_name;

	/** @var string[] All available token names for fields (prev generated names to handle page cache). */
	private array $token_names;


	public function __construct( Options $options ) {
		$this->options = $options;
		$this->token = self::make_hash( gmdate( 'jn' ) . $options->unique_code );
	}

	public function init(): void {
		$this->token_names = $this->get_token_names();
		$this->token_name = end( $this->token_names );
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

		$token = '';
		foreach( $this->token_names as $field_name ){
			$token = (string) ( $_POST[ $field_name ] ?? '' );
			if( $token ){
				$token = trim( wp_unslash( $token ) );
				break;
			}
		}

		if( self::make_hash( $token ) !== $this->token ){
			/** @noinspection ForgottenDebugOutputInspection */
			wp_die( $this->block_form(), 'Spam Blocked', [ 'response' => 403 ] );
		}
	}

	/**
	 * Gets Form HTML for blocked comment.
	 */
	private function block_form(): string {
		ob_start();
		$field_name = esc_html( $this->token_name );
		?>
		<h1><?= __( 'Antispam block your comment!', 'kama-spamblock' ) ?></h1>

		<style>
			.kama-spamblock-form { max-width:45rem; margin: auto; }
			.kama-spamblock-form textarea { display:none; }
			.kama-spamblock-form textarea[name="<?= $field_name ?>"] { display:block; width:100%; height:3em; box-sizing:border-box; margin:1.5em 0; }
			.kama-spamblock-form button { height:70px; width:100%; font-size:150%; cursor:pointer; border:none; color:#fff; background:#555; }
			.kama-spamblock-form <?= $field_name ?> { padding:.2em .3em; background:rgba(0 0 0 / .1); }
		</style>

		<form class="kama-spamblock-form" method="POST" action="<?= site_url( '/wp-comments-post.php' ) ?>">
			<p>
				<?= strtr(
					__( 'Replace the value in the field below with {CODE} and click the button.', 'kama-spamblock' ), [
					'{CODE}' => "<$field_name>" . esc_html( $this->token ) . "</$field_name>"
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

			<button type="button"><?= __( 'Send comment again', 'kama-spamblock' ) ?></button>
			<script>document.currentScript.previousElementSibling.addEventListener( 'click', ev => ev.currentTarget.form.requestSubmit() )</script>
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

		$selector = '#' . esc_html( sanitize_html_class( $this->options->sibmit_button_id ) );
		$uniqcode = esc_html( Options::sanitize_uniue_code( $this->options->unique_code ) );
		$filedkey = esc_html( $this->token_name );

		echo <<<HTML
		<script id="kama_spamblock">
			window.addEventListener( 'DOMContentLoaded', function() {
				document.addEventListener( 'mousedown', handleSubmit );
				document.addEventListener( 'touchstart', handleSubmit );
				document.addEventListener( 'keypress', handleSubmit );

				function handleSubmit( ev ){
					let sbmt = ev.target.closest( '$selector' );
					if( ! sbmt ){
						return;
					}

					let date = new Date();
					let $filedkey = document.createElement( 'input' );
					$filedkey.value = '' + date.getUTCDate() + (date.getUTCMonth() + 1) + '$uniqcode';
					$filedkey.name = '$filedkey';
					$filedkey.type = 'hidden';

					sbmt.before( $filedkey );
				}
			} );
		</script>
		HTML;
	}

	/**
	 * Gets active token field names and rotates the current name every four hours.
	 *
	 * Stored option items use the `<name>:<created_at>` format.
	 *
	 * @return string[]
	 */
	private function get_token_names(): array {
		$names_data = (array) get_option( self::TOKEN_NAMES_OPTION, [] );

		$last_item = (string) end( $names_data );
		[ $field_name, $created_at ] = $this->parse_token_name( $last_item );

		$is_add_new = ! $field_name || ( ( time() - $created_at ) >= self::TOKEN_NAME_TTL );
		if( $is_add_new ){
			$names_data[] = $this->create_token_name() . ':' . time();
			$names_data = array_slice( $names_data, - self::TOKEN_NAMES_LIMIT );
			update_option( self::TOKEN_NAMES_OPTION, $names_data );
		}

		return $this->parse_token_names( $names_data );
	}

	/**
	 * Parses stored token entries and returns valid token field names.
	 *
	 * @return string[]
	 */
	private function parse_token_names( array $names_data ): array {
		$names = [];
		foreach( $names_data as $name_data ){
			[ $field_name ] = $this->parse_token_name( $name_data );
			$field_name && ( $names[] = $field_name );
		}

		return $names;
	}

	private function parse_token_name( string $name_data ): array {
		[ $field_name, $created_at ] = explode( ':', $name_data ) + [ '', 0 ];

		return [ $field_name, (int) $created_at ];
	}

	private function create_token_name(): string {
		$char = chr( wp_rand( 97, 122 ) );

		return $char . substr( md5( wp_generate_uuid4() ), 0, wp_rand( 10, 20 ) );
	}

	/**
	 * Creates hash from specified token code (if it's not hashed yet).
	 */
	private static function make_hash( string $token ): string {
		return preg_match( '/^[a-f0-9]{32}$/', $token ) ? $token : md5( $token );
	}

}
