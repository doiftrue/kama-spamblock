<?php

namespace Kama_Spamblock;

class Options {

	public const OPT_NAME = 'ks_options';

	public string $sibmit_button_id;

	public function __construct() {
		$opt = array_merge( $this->default_options(), get_option( self::OPT_NAME, [] ) );
		$opt = apply_filters( 'kama_spamblock__options', $opt );

		$this->sibmit_button_id = (string) $opt['sibmit_button_id'];
	}

	public function default_options(): array {
		return [
			'sibmit_button_id' => 'submit',
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
			else{
				$val = sanitize_text_field( $val );
			}
		}

		return $opts;
	}

	public function options_fields(): void {
		?>
		<p>
			<input type="text" name="<?= self::OPT_NAME ?>[sibmit_button_id]" value="<?= esc_attr( $this->sibmit_button_id ) ?>" />
			<?= __( 'ID attribute of comment form submit button. Default: <code>submit</code>', 'kama-spamblock' ) ?>
		</p>
		<?php
	}

}
