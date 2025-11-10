<?php

namespace Kama_Spamblock;

class Options {

	public const OPT_NAME = 'ks_options';

	public string $sibmit_button_id;

	public string $unique_code;

	public function __construct() {
		$opt = array_merge( $this->default_options(), get_option( self::OPT_NAME, [] ) );
		$this->check_empty_unique_code( $opt['unique_code'] );

		$opt = apply_filters( 'kama_spamblock__options', $opt );

		$this->sibmit_button_id = (string) $opt['sibmit_button_id'];
		$this->unique_code      = (string) $opt['unique_code'];
	}

	private function check_empty_unique_code( string $code ): void {
	    if( ! $code ){
		    $opt = get_option( self::OPT_NAME, [] );
		    $opt['unique_code'] = wp_generate_password( 10, false );
		    update_option( self::OPT_NAME, $opt );
	    }
	}

	public function default_options(): array {
		return [
			'sibmit_button_id' => 'submit',
			'unique_code'      => '', // default value will be auto-generated
		];
	}

	public function admin_options(): void {
		add_settings_section( 'Plugin', '', '', 'discussion' ); // set no title

		add_settings_field(
			self::OPT_NAME . '_field',
			__( 'Kama Spamblock settings', 'kama-spamblock' ),
			[ $this, 'options_fields', ],
			'discussion',
			'Plugin'
		);

		register_setting( 'discussion', self::OPT_NAME, [ __CLASS__, 'sanitize_opt' ] );
	}

	public static function sanitize_opt( $opts ) {
		foreach( $opts as $key => & $val ){
			if( 'sibmit_button_id' === $key ){
				$val = sanitize_html_class( $val );
			}
			elseif( 'unique_code' === $key ){
				$val = self::sanitize_uniue_code( $val );
				$val || $val = wp_generate_password( 10, false );
			}
			else{
				$val = sanitize_text_field( $val );
			}
		}

		return $opts;
	}

	public static function sanitize_uniue_code( string $code ) {
		return preg_replace( '~[^A-Za-z0-9*%$#@!_-]~', '', $code );
	}

	public function options_fields(): void {
		?>
		<p>
			<input type="text" name="<?= self::OPT_NAME ?>[sibmit_button_id]" value="<?= esc_attr( $this->sibmit_button_id ) ?>" />
			<?= __( 'ID attribute of comment form submit button. Default: <code>submit</code>', 'kama-spamblock' ) ?>
		</p>
		<p>
			<input type="text" name="<?= self::OPT_NAME ?>[unique_code]" value="<?= esc_attr( $this->unique_code ) ?>" />
			<?= __( 'Any unique code. Change it if you receave spam comments.', 'kama-spamblock' ) ?>
		</p>
		<?php
	}

}
