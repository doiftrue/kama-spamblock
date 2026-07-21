<?php

namespace Kama_Spamblock;

use WP_Mock;
use WP_Mock\Tools\TestCase;

require_once dirname( __DIR__, 2 ) . '/src/Options.php';

class Options__Test extends TestCase {

	private object $original_options;
	private $original_registered_settings;

	public function setUp(): void {
		parent::setUp();

		$this->original_options = clone $GLOBALS['stub_wp_options'];
		$this->original_registered_settings = $GLOBALS['wp_registered_settings'] ?? null;
	}

	public function tearDown(): void {
		$GLOBALS['stub_wp_options'] = $this->original_options;
		$GLOBALS['wp_registered_settings'] = $this->original_registered_settings;

		parent::tearDown();
	}

	public function test__default_options(): void {
		$GLOBALS['stub_wp_options']->ks_options = [
			'sibmit_button_id' => 'submit',
			'unique_code'      => 'saved-code',
		];

		$options = new Options();

		$this->assertSame(
			[
				'sibmit_button_id' => 'submit',
				'unique_code'      => '',
			],
			$options->default_options()
		);
	}

	public function test__constructor_loads_saved_options(): void {
		$GLOBALS['stub_wp_options']->ks_options = [
			'sibmit_button_id' => 'send-comment',
			'unique_code'      => 'saved-code',
		];

		$options = new Options();

		$this->assertSame( 'send-comment', $options->sibmit_button_id );
		$this->assertSame( 'saved-code', $options->unique_code );
	}

	public function test__constructor_generates_and_saves_missing_unique_code(): void {
		$GLOBALS['stub_wp_options']->ks_options = [
			'sibmit_button_id' => 'submit',
			'unique_code'      => '',
		];

		WP_Mock::userFunction( 'update_option' )
			->with(
				Options::OPT_NAME,
				\Mockery::on( static function ( array $saved ): bool {
					return 'submit' === $saved['sibmit_button_id']
						&& 10 === strlen( $saved['unique_code'] );
				} )
			)
			->andReturn( true );

		$options = new Options();

		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{10}$/', $options->unique_code );
	}

	public function test__sanitize_options_by_field_type(): void {
		$sanitized = Options::sanitize_opt( [
			'sibmit_button_id' => 'send comment!',
			'unique_code'      => 'ab?CD_12-#@!',
			'custom'           => '<b> plain text </b>',
		] );

		$this->assertSame( 'sendcomment', $sanitized['sibmit_button_id'] );
		$this->assertSame( 'abCD_12-#@!', $sanitized['unique_code'] );
		$this->assertSame( 'plain text', $sanitized['custom'] );
	}

	public function test__sanitize_options_generates_empty_unique_code(): void {
		$sanitized = Options::sanitize_opt( [ 'unique_code' => '???' ] );

		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{10}$/', $sanitized['unique_code'] );
	}

	public function test__admin_options_registers_discussion_settings(): void {
		$GLOBALS['stub_wp_options']->ks_options = [
			'sibmit_button_id' => 'submit',
			'unique_code'      => 'saved-code',
		];

		$options = new Options();

		WP_Mock::userFunction( 'add_settings_section' )
			->once()
			->with( 'Plugin', '', '', 'discussion' );

		WP_Mock::userFunction( 'add_settings_field' )
			->once()
			->with(
				Options::OPT_NAME . '_field',
				'Kama Spamblock settings',
				[ $options, 'options_fields' ],
				'discussion',
				'Plugin'
			);

		$options->admin_options();

		$this->assertSame(
			[ Options::class, 'sanitize_opt' ],
			$GLOBALS['wp_registered_settings'][ Options::OPT_NAME ]['sanitize_callback']
		);
	}

	public function test__options_fields_renders_current_values(): void {
		$GLOBALS['stub_wp_options']->ks_options = [
			'sibmit_button_id' => 'send-comment',
			'unique_code'      => 'saved-code',
		];

		$options = new Options();

		ob_start();
		$options->options_fields();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="ks_options[sibmit_button_id]"', $html );
		$this->assertStringContainsString( 'value="send-comment"', $html );
		$this->assertStringContainsString( 'name="ks_options[unique_code]"', $html );
		$this->assertStringContainsString( 'value="saved-code"', $html );
	}
}
