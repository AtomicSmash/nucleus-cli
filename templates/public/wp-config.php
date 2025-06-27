<?php
/**
 * WP config file for all sites. Pulls in config values for specific environments from the active-config file.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound,WordPress.Security.ValidatedSanitizedInput,WordPress.PHP.YodaConditions.NotYoda
define( 'PROJECT_DIR', realpath( __DIR__ . '/.' ) );
define( 'ROOT_DIR', realpath( __DIR__ . '/.' ) );

/** Load Composer */
require_once PROJECT_DIR . '/vendor/autoload.php';

if ( file_exists( ROOT_DIR . '/active-config.php' ) ) {
	require ROOT_DIR . '/active-config.php';
} else {
	require ROOT_DIR . '/.config/wp-configs/development.php';
}

// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable,WordPress.WP.GlobalVariablesOverride.Prohibited
$table_prefix  = 'wp_';

define( 'FS_METHOD', 'direct' );
define( 'DISABLE_WP_CRON', true );
define( 'ALTERNATE_WP_CRON', true );

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
