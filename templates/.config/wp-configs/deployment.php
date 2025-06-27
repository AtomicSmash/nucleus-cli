<?php
/**
 * WP config file for the development site.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
/** Database config */
define( 'DB_NAME', '{{PROJECT_NAME}}' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', 'utf8_unicode_ci' );
$table_prefix = 'wp_';

/** Core tweaks */
define( 'DISALLOW_FILE_EDIT', false );
define( 'DISALLOW_FILE_MODS', false );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'AUTOMATIC_UPDATER_DISABLED', true );

/** WordPress salts - https://api.wordpress.org/secret-key/1.1/salt/ */
define( 'AUTH_KEY', '{{WP_AUTH_KEY}}' );
define( 'SECURE_AUTH_KEY', '{{WP_SECURE_AUTH_KEY}}' );
define( 'LOGGED_IN_KEY', '{{WP_LOGGED_IN_KEY}}' );
define( 'NONCE_KEY', '{{WP_NONCE_KEY}}' );
define( 'AUTH_SALT', '{{WP_AUTH_SALT}}' );
define( 'SECURE_AUTH_SALT', '{{WP_SECURE_AUTH_SALT}}' );
define( 'LOGGED_IN_SALT', '{{WP_LOGGED_IN_SALT}}' );
define( 'NONCE_SALT', '{{WP_NONCE_SALT}}' );

/** Debug */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'SCRIPT_DEBUG', true );

/** Set the environment type */
define( 'WP_ENVIRONMENT_TYPE', 'development' );

/** Force correct url to be used in Herd */
define( 'WP_HOME', 'https://{{PROJECT_NAME}}.test' );
define( 'WP_SITEURL', 'https://{{PROJECT_NAME}}.test/wp' );

/** Mailtrap Credentials */
define( 'MAILTRAP_USERNAME', '{{MAILTRAP_USERNAME}}' );
define( 'MAILTRAP_PASSWORD', '{{MAILTRAP_PASSWORD}}' );

/** Disable post revisions */
define( 'WP_POST_REVISIONS', false );
