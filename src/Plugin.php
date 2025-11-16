<?php

namespace Kama_Spamblock;

class Plugin {

	public string $dir;
	public string $main_file;

	public Options $opt;
	public Spam_Blocker $blocker;

	public function __construct( string $main_file ) {
		$this->main_file = $main_file;
		$this->dir       = dirname( $main_file );

		$this->opt     = new Options();
		$this->blocker = new Spam_Blocker( $this->opt );
	}

	public function init(): void {
		if( ! defined( 'DOING_AJAX' ) ){
			load_plugin_textdomain( 'kama-spamblock', false, basename( $this->dir ) . '/languages' );
		}

		is_admin()
			? $this->init_admin()
			: $this->init_front();
	}

	private function init_admin(): void {
		add_action( 'admin_init', [ $this->opt, 'admin_options' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->main_file ), [ __CLASS__, 'settings_link' ] );
	}

	private function init_front(): void {
		add_action( 'wp_footer', [ $this->blocker, 'print_main_js' ], 0 );
		add_filter( 'preprocess_comment', [ $this->blocker, 'block_spam' ], 0 );
	}

	public static function settings_link( $links ) {
		$links[] = sprintf( '<a href="%s">%s</a>', admin_url( '/options-discussion.php#wpfooter' ), __( 'Settings', 'kama-spamblock' ) );

		return $links;
	}

}

