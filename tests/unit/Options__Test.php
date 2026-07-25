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
		];

		$options = new Options();

		$this->assertSame(
			[
				'sibmit_button_id' => 'submit',
			],
			$options->default_options()
		);
	}

	public function test__constructor_loads_saved_options(): void {
		$GLOBALS['stub_wp_options']->ks_options = [
			'sibmit_button_id' => 'send-comment',
		];

		$options = new Options();

		$this->assertSame( 'send-comment', $options->sibmit_button_id );
	}

	public function test__sanitize_options_by_field_type(): void {
		$sanitized = Options::sanitize_opt( [
			'sibmit_button_id' => 'send comment!',
			'custom'           => '<b> plain text </b>',
		] );

		$this->assertSame( 'sendcomment', $sanitized['sibmit_button_id'] );
		$this->assertSame( 'plain text', $sanitized['custom'] );
	}

	public function test__admin_options_registers_discussion_settings(): void {
		$GLOBALS['stub_wp_options']->ks_options = [
			'sibmit_button_id' => 'submit',
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
		];

		$options = new Options();

		ob_start();
		$options->options_fields();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="ks_options[sibmit_button_id]"', $html );
		$this->assertStringContainsString( 'value="send-comment"', $html );
		$this->assertStringNotContainsString( 'unique_code', $html );
	}
}
