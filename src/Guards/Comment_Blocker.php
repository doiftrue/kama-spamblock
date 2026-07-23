<?php

namespace Kama_Spamblock\Guards;

use Kama_Spamblock\Options;

class Comment_Blocker {

	private const TOKEN_NAMES_OPTION = 'kama_spamblock__nonce_field_names';
	private const TOKEN_NAMES_LIMIT  = 10;
	private const TOKEN_NAME_TTL     = 4 * HOUR_IN_SECONDS;

	private const MIN_FILL_DURATION_SEC = 3;

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
		$this->token = self::ensure_hash( gmdate( 'jn' ) . $options->unique_code );
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
		$passed_sec = 0;
		foreach( $this->token_names as $field_name ){
			$token = (string) ( $_POST[ $field_name ] ?? '' );
			if( $token ){
				$token = trim( wp_unslash( $token ) );
				$passed_sec = (int) ( $_POST[ $this->get_time_field_name( $field_name ) ] ?? 0 );
				break;
			}
		}

		if(
			( $passed_sec < self::MIN_FILL_DURATION_SEC )
			||
			( self::ensure_hash( $token ) !== $this->token )
		){
			/** @noinspection ForgottenDebugOutputInspection */
			wp_die( $this->block_form(), 'Spam Blocked', [ 'response' => 403 ] );
		}
	}

	/**
	 * Creates hash from specified token code (if it's not hashed yet).
	 */
	private static function ensure_hash( string $token ): string {
		return preg_match( '/^[a-f0-9]{32}$/', $token ) ? $token : md5( $token );
	}

	/**
	 * Gets Form HTML for blocked comment.
	 */
	private function block_form(): string {
		ob_start();
		$token_flname = esc_html( $this->token_name );
		$time_flname = esc_html( $this->get_time_field_name( $this->token_name ) );
		?>
		<h1><?= __( 'Antispam block your comment!', 'kama-spamblock' ) ?></h1>

		<style>
			.kama-spamblock-form { max-width:45rem; margin: auto; }
			.kama-spamblock-form textarea { display:none; }
			.kama-spamblock-form textarea[name="<?= $token_flname ?>"] { display:block; width:100%; height:3em; box-sizing:border-box; margin:1.5em 0; }
			.kama-spamblock-form button { height:70px; width:100%; font-size:150%; cursor:pointer; border:none; color:#fff; background:#555; }
			.kama-spamblock-form <?= $token_flname ?> { padding:.2em .3em; background:rgba(0 0 0 / .1); }
		</style>

		<form class="kama-spamblock-form" method="POST" action="<?= site_url( '/wp-comments-post.php' ) ?>">
			<p>
				<?= strtr(
					__( 'Replace the value in the field below with {CODE} and click the button.', 'kama-spamblock' ), [
					'{CODE}' => $this->render_challenge_html( wp_rand( 1, 3 ) )
				] ) ?>
			</p>

			<?php
			// we set value here just to the field has any value to make similar to other fields (this val never used)
			$fields = [
				sprintf( '<textarea name="%s">%s</textarea>', $token_flname, esc_textarea( (string) ( $_POST['comment_post_ID'] ?? '' ) ) ),
				sprintf( '<textarea name="%s">0</textarea>', $time_flname )
			];
			foreach( $_POST as $key => $val ){
				if( $key !== $token_flname && $key !== $time_flname && is_string( $val ) ){
					$fields[] = sprintf( '<textarea name="%s">%s</textarea>', esc_attr( $key ), esc_textarea( wp_unslash( $val ) ) );
				}
			}

			shuffle( $fields );
			$fields = implode( "\n", $fields );
			echo $fields;
			?>

			<button type="button"><?= __( 'Send comment again', 'kama-spamblock' ) ?></button>
			<script>
				{
					let button = document.currentScript.previousElementSibling;
					let formStart = performance.now();
					button.addEventListener( 'click', ev => {
						let form = ev.currentTarget.form;
						form.elements['<?= $time_flname ?>'].value = '' + Math.floor( ( performance.now() - formStart ) / 1000 );
						form.requestSubmit();
					} );
				}
			</script>
		</form>
		<?php
		return ob_get_clean();
	}

	private function render_challenge_html( int $variant ): string {
		$tag = esc_html( $this->token_name );
		$parts = str_split( $this->token, 11 );

		switch( $variant ){
			case 1:
				return "<$tag><$tag-a>$parts[0]</$tag-a><$tag-b>$parts[1]</$tag-b><$tag-c>$parts[2]</$tag-c></$tag>";
			case 2:
				return sprintf(
					'<%1$s data-a="%2$s" data-b="%3$s" data-c="%4$s"></%1$s>'
					. '<script>(el=>el.textContent=el.dataset.c+el.dataset.a+el.dataset.b)(document.currentScript.previousElementSibling)</script>',
					$tag,
					esc_attr( $parts[1] ),
					esc_attr( $parts[2] ),
					esc_attr( $parts[0] )
				);
			case 3:
			default:
				return "<$tag>" . esc_html( $this->token ) . "</$tag>";
		}
	}

	public function print_main_js(): void {
		global $post;

		// note: is_singular() may work incorrectly
		if ( ! is_singular() || ! comments_open( $post ) ) {
			return;
		}

		$selector = '#' . esc_html( sanitize_html_class( $this->options->sibmit_button_id ) );
		$uniq_code = esc_html( Options::sanitize_uniue_code( $this->options->unique_code ) );
		$token_flname = esc_html( $this->token_name );
		$time_flname = esc_html( $this->get_time_field_name( $this->token_name ) );

		echo <<<HTML
		<script id="kama_spamblock">
			window.addEventListener( 'DOMContentLoaded', function() {
				document.addEventListener( 'mousedown', handleSubmit );
				document.addEventListener( 'touchstart', handleSubmit );
				document.addEventListener( 'keypress', handleSubmit );
				let formStart = performance.now();

				function handleSubmit( ev ){
					let sbmt = ev.target.closest( '$selector' );
					if( ! sbmt ){
						return;
					}

					let date = new Date();
					let $token_flname = document.createElement( 'input' );
					$token_flname.type = 'hidden';
					$token_flname.name = '$token_flname';
					$token_flname.value = '' + date.getUTCDate() + (date.getUTCMonth() + 1) + '$uniq_code';
					sbmt.before( $token_flname );

					let dur = document.createElement( 'input' );
					dur.type = 'hidden';
					dur.name = '$time_flname';
					dur.value = '' + Math.floor( ( performance.now() - formStart ) / 1000 );
					sbmt.before( dur );
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

	private function get_time_field_name( string $token_name ): string {
		return $token_name . '_time';
	}

}
