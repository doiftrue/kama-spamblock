<?php

namespace Kama_Spamblock\Guards;

use Kama_Spamblock\Options;

class Comment_Blocker {

	private const TOKENS_DATA_OPT   = 'kama_spamblock__tokens_data';
	private const TOKENS_DATA_LIMIT = 10;
	private const TOKEN_TTL         = 4 * HOUR_IN_SECONDS;

	private const MIN_FILL_DURATION_SEC = 3;

	/** @var string[] `comment` for WP 5.5+ */
	private array $comment_types = [ '', 'comment' ];

	private Options $options;

	/** Current token name (for field). */
	private string $token_name;

	/** Current token (code). Main part of the token. */
	private string $token_code;

	/** @var string[] Token codes keyed by field name (previous records are kept to handle page cache). */
	private array $tokens_data;

	public function __construct( Options $options ) {
		$this->options = $options;
	}

	public function init(): void {
		$this->tokens_data = $this->get_tokens_data();
		$this->token_name  = (string) array_key_last( $this->tokens_data );
		$this->token_code  = $this->tokens_data[ $this->token_name ];
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

		$post_id = (int) ( $commentdata['comment_post_ID'] ?? 0 );

		$is_valid_token = false;
		foreach( $this->tokens_data as $field_name => $token_code ){
			$post_token = trim( wp_unslash( (string) ( $_POST[ $field_name ] ?? '' ) ) );
			if( ! $post_token ){
				continue;
			}

			$post_duration_sec = (int) ( $_POST[ $this->get_time_field_name( $field_name ) ] ?? 0 );

			if(
				( $post_duration_sec >= self::MIN_FILL_DURATION_SEC )
				&&
				( self::ensure_hash( $post_token ) === $this->make_hashed_token( $post_id, $token_code ) )
			){
				$is_valid_token = true;
				break;
			}
		}

		if( ! $is_valid_token ){
			$form_html = $this->block_form( $this->make_hashed_token( $post_id, $this->token_code ) );
			/** @noinspection ForgottenDebugOutputInspection */
			wp_die( $form_html, 'Spam Blocked', [ 'response' => 403 ] );
		}
	}

	private function make_hashed_token( int $post_id, string $token_code ): string {
		return self::ensure_hash( "$post_id|$token_code" );
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
	private function block_form( string $hashed_token ): string {
		ob_start();
		$token_name = esc_html( $this->token_name );
		$time_flname = esc_html( $this->get_time_field_name( $this->token_name ) );
		?>
		<h1><?= __( 'Antispam block your comment!', 'kama-spamblock' ) ?></h1>

		<style>
			.kama-spamblock-form { max-width:45rem; margin: auto; }
			.kama-spamblock-form textarea { display:none; }
			.kama-spamblock-form textarea[name="<?= $token_name ?>"] { display:block; width:100%; height:3em; box-sizing:border-box; margin:1.5em 0; }
			.kama-spamblock-form button { height:70px; width:100%; font-size:150%; cursor:pointer; border:none; color:#fff; background:#555; }
			.kama-spamblock-form <?= $token_name ?> { padding:.2em .3em; background:rgba(0 0 0 / .1); }
		</style>

		<form class="kama-spamblock-form" method="POST" action="<?= site_url( '/wp-comments-post.php' ) ?>">
			<p>
				<?= strtr(
					__( 'Replace the value in the field below with {CODE} and click the button.', 'kama-spamblock' ), [
					'{CODE}' => $this->render_challenge_html( $hashed_token, wp_rand( 1, 3 ) )
				] ) ?>
			</p>

			<?php
			// we set value here just to the field has any value to make similar to other fields (this val never used)
			$fields = [
				sprintf( '<textarea name="%s">%s</textarea>', $token_name, esc_textarea( (string) ( $_POST['comment_post_ID'] ?? '' ) ) ),
				sprintf( '<textarea name="%s">0</textarea>', $time_flname )
			];
			foreach( $_POST as $key => $val ){
				if( $key !== $token_name && $key !== $time_flname && is_string( $val ) ){
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

	private function render_challenge_html( string $token, int $variant ): string {
		$tag = esc_html( $this->token_name );
		$parts = str_split( $token, 11 );

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
				return "<$tag>" . esc_html( $token ) . "</$tag>";
		}
	}

	public function print_main_js(): void {
		global $post;

		// note: is_singular() may work incorrectly
		if ( ! is_singular() || ! comments_open( $post ) ) {
			return;
		}

		$selector = '#' . esc_html( sanitize_html_class( $this->options->sibmit_button_id ) );
		$token_code = esc_html( $this->token_code );
		$token_name = esc_html( $this->token_name );
		$time_flname = esc_html( $this->get_time_field_name( $this->token_name ) );
		$var_name = substr( uniqid( chr( wp_rand( 97, 122 ) ), false ), 0, 5 );

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

					let $var_name = document.createElement( 'input' );
					$var_name.type = 'hidden';
					$var_name.name = '$token_name';
					$var_name.value = sbmt.form.elements['comment_post_ID'].value + '|$token_code';
					sbmt.before( $var_name );

					$var_name = document.createElement( 'input' );
					$var_name.type = 'hidden';
					$var_name.name = '$time_flname';
					$var_name.value = '' + Math.floor( ( performance.now() - formStart ) / 1000 );
					sbmt.before( $var_name );
				}
			} );
		</script>
		HTML;
	}

	/**
	 * Gets active token records and rotates the current record every four hours.
	 *
	 * Stored option items use the `<field_name>:<token_code>:<created_at>` format.
	 *
	 * @return array<string,string> Token codes keyed by field name.
	 */
	private function get_tokens_data(): array {
		$data = (array) get_option( self::TOKENS_DATA_OPT, [] );

		$last_item = (string) end( $data );
		[ $field_name, $token_code, $created_at ] = $this->parse_token_info( $last_item );

		$is_add_new = ! $field_name || ! $token_code || ( ( time() - $created_at ) >= self::TOKEN_TTL );
		if( $is_add_new ){
			$data[] = $this->generate_token_info();
			$data = array_slice( $data, - self::TOKENS_DATA_LIMIT );
			update_option( self::TOKENS_DATA_OPT, $data );
		}

		return $this->parse_tokens_data( $data );
	}

	/**
	 * Parses stored token records.
	 *
	 * @return array<string,string> Token codes keyed by field name.
	 */
	private function parse_tokens_data( array $tokens_data ): array {
		$token_codes = [];
		foreach( $tokens_data as $record ){
			[ $field_name, $token_code, $created_at ] = $this->parse_token_info( $record );
			if( $field_name && $token_code && $created_at ){
				$token_codes[ $field_name ] = $token_code;
			}
		}

		return $token_codes;
	}

	/**
	 * @param string $record Eg: 'field_name:token_code:1690000000'
	 */
	private function parse_token_info( string $record ): array {
		[ $field_name, $token_code, $created_at ] = explode( ':', $record ) + [ '', '', 0 ];

		return [ $field_name, $token_code, (int) $created_at ];
	}

	private function generate_token_info(): string {
		$token_name = chr( wp_rand( 97, 122 ) ) . substr( md5( wp_generate_uuid4() ), 0, wp_rand( 10, 20 ) );
		$token_code = wp_generate_password( 10, false );

		return "$token_name:$token_code:" . time();
	}

	private function get_time_field_name( string $token_name ): string {
		return $token_name . '_time';
	}

}
