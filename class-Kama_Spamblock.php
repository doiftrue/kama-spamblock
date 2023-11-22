<?php

class Kama_Spamblock {

	use Kama_Spamblock__Admin;

	const OPT_NAME = 'ks_options';

	/**
	 * `comment` for WP 5.5+
	 *
	 * @var string[]
	 */
	private $process_comment_types = [ '', 'comment' ];

	/**
	 * @var object
	 */
	public $opt;

	/**
	 * @var string
	 */
	private $nonce = '';

	/**
	 * @var Kama_Spamblock
	 */
	private static $inst;

	public static function instance(){

		self::$inst || self::$inst = new self();

		return self::$inst;
	}

	public function __construct(){

		$this->opt = array_merge( $this->default_options(), get_option( self::OPT_NAME, [] ) );

		$this->opt = apply_filters( 'kama_spamblock__options', $this->opt );

		$this->opt = (object) $this->opt;

		$this->process_comment_types = apply_filters( 'kama_spamblock__process_comment_types', $this->process_comment_types );

		if( ! defined( 'DOING_AJAX' ) ){
			load_plugin_textdomain( 'kama-spamblock', false, basename( __DIR__ ) . '/languages' );
		}

		if( is_admin() ){
			$this->admin_init();
		}
		elseif( $this->process_comment_types ) {
			$this->init();
		}

	}

	public function init(){

		if( ! wp_doing_ajax() && ! is_admin() ){
			add_action( 'wp_footer', [ $this, 'main_js' ], 99 );
		}

		$this->nonce = self::make_nonce( date( 'jn' ) . $this->opt->unique_code );

		add_filter( 'preprocess_comment', [ $this, '_block_spam' ], 0 );
	}

	/**
	 * Check and block comment if needed.
	 *
	 * @param array $commentdata
	 *
	 * @return array
	 */
	public function _block_spam( $commentdata ){

		$com_type = $commentdata['comment_type'];

		// Pings and trackbacks protect
		if( in_array( $com_type, [ 'trackback', 'pingback' ], true ) ){

			$external_html = wp_remote_retrieve_body( wp_remote_get( $commentdata['comment_author_url'] ) );

			// no back link
			if( ! preg_match( '~<a[^>]+href=[\'"](https?:)?//' . preg_quote( parse_url( home_url(), PHP_URL_HOST ), '~' ) . '~si', $external_html ) ){
				die( 'no backlink...' );
			}
		}
		// regular comment
		elseif( in_array( $com_type, $this->process_comment_types, true ) ) {

			$ksbn_code = isset( $_POST['ksbn_code'] ) ? trim( $_POST['ksbn_code'] ) : '';

			if( self::make_nonce( $ksbn_code ) !== $this->nonce ){
				wp_die( $this->block_form() );
			}
		}

		return $commentdata;
	}

	private static function make_nonce( $key ){

		// check maybe already md5
		return preg_match( '/^[a-f0-9]{32}$/', $key ) ? $key : md5( $key );
	}

	public function main_js(){
		global $post;

		// note: is_singular() in some themes may work incorrectly
		if( !empty( $post ) && 'open' !== $post->comment_status && is_singular() ){
			return;
		}
		?>
		<script id="kama_spamblock">
			(function(){

				const catch_submit = function( ev ){

					let sbmt = ev.target.closest( '#<?= esc_html( $this->opt->sibmit_button_id ) ?>' );

					if( ! sbmt ){
						return;
					}

					let input = document.createElement( 'input' );
					let date = new Date();

					input.value = ''+ date.getUTCDate() + (date.getUTCMonth() + 1) + '<?= esc_html( $this->opt->unique_code ) ?>';
					input.name = 'ksbn_code';
					input.type = 'hidden';

					sbmt.parentNode.insertBefore( input, sbmt );
				}

				document.addEventListener( 'mousedown', catch_submit );
				document.addEventListener( 'keypress', catch_submit );
			})()
		</script>
		<?php
	}

	/**
	 * Output form when comment has been blocked.
	 *
	 * @return string
	 */
	private function block_form(){
		ob_start();
		?>

		<h1><?= __( 'Antispam block your comment!', 'kama-spamblock' ) ?></h1>

		<form method="post" action="<?= site_url( '/wp-comments-post.php' ) ?>">
			<p>
				<?= sprintf(
			       __( 'Copy %1$s to the field %2$s and press button', 'kama-spamblock' ),
			       '<code style="background:rgba(255,255,255,.2);">' . $this->nonce . '</code>',
			       '<input type="text" name="ksbn_code" value="" style="width:150px; border:1px solid #ccc; border-radius:3px; padding:.3em;" />'
		       ) ?>
			</p>

			<input type="submit" style="height:70px; width:100%; font-size:150%; cursor:pointer; border:none; color:#fff; background:#555;" value="<?= __( 'Send comment again', 'kama-spamblock' ) ?>" />

			<?php
			unset( $_POST['ksbn_code'] );

			foreach( $_POST as $key => $val ){
				echo sprintf( '<textarea style="display:none;" name="%s">%s</textarea>', $key, esc_textarea( stripslashes( $val ) ) );
			}
			?>
		</form>

		<?php
		return ob_get_clean();
	}

	/**
	 * default_options.
	 *
	 * @return string[]
	 */
	private function default_options(){

		// no dynamic options here!
		return [
			'sibmit_button_id' => 'submit',
			'unique_code'      => 'uniq9065',
		];
	}

}

trait Kama_Spamblock__Admin {

	## admin
	private function admin_init(){
		add_action( 'admin_init', [ $this, 'admin_options' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'settings_link' ] );
	}

	public function settings_link( $links ){

		$links[] = '<a href="' . admin_url( '/options-discussion.php#wpfooter' ) . '">' . __( 'Settings', 'kama-spamblock' ) . '</a>';

		return $links;
	}

	public function admin_options(){
		add_settings_section( 'kama_spamblock', '', '', 'discussion' ); // set no title

		add_settings_field( self::OPT_NAME . '_field', __( 'Kama Spamblock settings', 'kama-spamblock' ), [
			$this,
			'options_field',
		], 'discussion', 'kama_spamblock' );

		register_setting( 'discussion', self::OPT_NAME, [ __CLASS__, 'sanitize_opt' ] );
	}

	public static function sanitize_opt( $opts ){

		foreach( $opts as $key => & $val ){
			if( 'sibmit_button_id' === $key ){
				$val = sanitize_html_class( $val );
			}
			elseif( 'unique_code' === $key ){
				$val = preg_replace( '~[^A-Za-z0-9*%$#@!_-]~', '', $val );
			}
			else{
				$val = sanitize_text_field( $val );
			}
		}

		return $opts;
	}

	public function options_field(){
		echo '
		<p>
			<input type="text" name="' . self::OPT_NAME . '[sibmit_button_id]" value="' . esc_attr( $this->opt->sibmit_button_id ) . '" /> ' .
		     __( 'ID attribute of comment form submit button. Default: <code>submit</code>', 'kama-spamblock' ) . '
		</p>
		<p>
			<input type="text" name="' . self::OPT_NAME . '[unique_code]" value="' . esc_attr( $this->opt->unique_code ) . '" /> ' .
		     __( 'Any unique code. Change it if  you receave spam comment...', 'kama-spamblock' ) . '
		</p>
		';
	}

}
