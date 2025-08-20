<?php
/**
 * WP config file for the staging site.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
/** Database config */
define( 'DB_NAME', '{{DB_NAME_STAGING}}' );
define( 'DB_USER', '{{DB_USER_STAGING}}' );
define( 'DB_PASSWORD', '{{DB_PASSWORD_STAGING}}' );
define( 'DB_HOST', '{{DB_HOST_STAGING}}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', 'utf8_unicode_ci' );
$table_prefix = '{{WORDPRESS_TABLE_PREFIX}}_';

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
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );

/** Set the environment type */
define( 'WP_ENVIRONMENT_TYPE', 'staging' );

/** Force correct url to be used in Herd */
define( 'WP_HOME', 'https://{{STAGING_URL}}' );
define( 'WP_SITEURL', 'https://{{STAGING_URL}}.test/wp' );

/** Mailtrap Credentials */
define( 'MAILTRAP_USERNAME', '{{MAILTRAP_USERNAME}}' );
define( 'MAILTRAP_PASSWORD', '{{MAILTRAP_PASSWORD}}' );

/** Disable post revisions */
define( 'WP_POST_REVISIONS', false );
