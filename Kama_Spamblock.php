<?php

class Kama_Spamblock {

	/** @var Kama_Spamblock_Options */
	public $opt;

	/** @var string */
	public $plug_dir;

	/** @var string */
	public $plug_file;

	/** @var string */
	private $nonce = '';

	/**
	 * `comment` for WP 5.5+
	 *
	 * @var string[]
	 */
	private $process_comment_types = [ '', 'comment' ];

	public function __construct( string $plug_file, Kama_Spamblock_Options $opt ) {
		$this->opt = $opt;

		$this->plug_file = $plug_file;
		$this->plug_dir  = dirname( $plug_file );

		$this->process_comment_types = apply_filters( 'kama_spamblock__process_comment_types', $this->process_comment_types );
	}

	public function init_plugin() {
		if( ! defined( 'DOING_AJAX' ) ){
			load_plugin_textdomain( 'kama-spamblock', false, basename( $this->plug_dir ) . '/languages' );
		}

		is_admin()
			? $this->init_admin()
			: $this->init_front();
	}

	private function init_admin() {
		add_action( 'admin_init', [ $this->opt, 'admin_options' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->plug_file ), [ Kama_Spamblock_Options::class, 'settings_link' ] );
	}

	private function init_front() {
		if( ! $this->process_comment_types ) {
			return;
		}

		if( ! wp_doing_ajax() && ! is_admin() ){
			add_action( 'wp_footer', [ $this, 'main_js' ], 0 );
		}

		$this->nonce = self::make_hash( date( 'jn' ) . $this->opt->unique_code );

		add_filter( 'preprocess_comment', [ $this, 'block_spam' ], 0 );
	}

	/**
	 * Check and block comment if needed.
	 */
	public function block_spam( array $commentdata ): array {

		$this->block_pings_trackbacks( $commentdata );
		$this->block_regular_comment( $commentdata );

		return $commentdata;
	}

	private function block_pings_trackbacks( $commentdata ) {

		if( ! in_array( $commentdata['comment_type'], [ 'trackback', 'pingback' ], true ) ){
			return;
		}

		$external_html = wp_remote_retrieve_body( wp_remote_get( $commentdata['comment_author_url'] ) );

		$quoted_home_url = preg_quote( parse_url( home_url(), PHP_URL_HOST ), '~' );
		$has_backlink = preg_match( "~<a[^>]+href=['\"](https?:)?//$quoted_home_url~si", $external_html );

		if( ! $has_backlink ){
			die( 'no backlink.' );
		}
	}

	private function block_regular_comment( $commentdata ) {

		if( ! in_array( $commentdata['comment_type'], $this->process_comment_types, true ) ) {
			return;
		}

		$ksbn_code = trim( $_POST['ksbn_code'] ?? '' );

		if( self::make_hash( $ksbn_code ) !== $this->nonce ){
			/** @noinspection ForgottenDebugOutputInspection */
			wp_die( $this->block_form() );
		}
	}

	/**
	 * Creates hash from specified key if it's not hashed yet.
	 */
	private static function make_hash( string $key ): string {
		return preg_match( '/^[a-f0-9]{32}$/', $key ) ? $key : md5( $key );
	}

	/**
	 * @return void
	 */
	public function main_js() {
		global $post;

		// note: is_singular() may work incorrectly
		if( $post && ( 'open' !== $post->comment_status ) && is_singular() ){
			return;
		}
		?>
		<script id="kama_spamblock">
			window.addEventListener( 'DOMContentLoaded', function() {
				document.addEventListener( 'mousedown', handleSubmit );
				document.addEventListener( 'touchstart', handleSubmit );
				document.addEventListener( 'keypress', handleSubmit );

				function handleSubmit( ev ){
					let sbmt = ev.target.closest( '#<?= esc_html( sanitize_html_class( $this->opt->sibmit_button_id ) ) ?>' );
					if( ! sbmt ){
						return;
					}

					let input = document.createElement( 'input' );
					let date = new Date();

					input.value = ''+ date.getUTCDate() + (date.getUTCMonth() + 1) + '<?= esc_html( Kama_Spamblock_Options::sanitize_uniue_code( $this->opt->unique_code ) ) ?>';
					input.name = 'ksbn_code';
					input.type = 'hidden';

					sbmt.parentNode.insertBefore( input, sbmt );
				}
			} );
		</script>
		<?php
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

}

