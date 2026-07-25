<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ks_options' );
delete_option( 'kama_spamblock__tokens_data' );
