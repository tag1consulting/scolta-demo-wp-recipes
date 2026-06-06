<?php
/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/** Authentication Unique Keys and Salts. */
define( 'AUTH_KEY', getenv( 'WP_AUTH_KEY' ) );
define( 'SECURE_AUTH_KEY', getenv( 'WP_SECURE_AUTH_KEY' ) );
define( 'LOGGED_IN_KEY', getenv( 'WP_LOGGED_IN_KEY' ) );
define( 'NONCE_KEY', getenv( 'WP_NONCE_KEY' ) );
define( 'AUTH_SALT', getenv( 'WP_AUTH_SALT' ) );
define( 'SECURE_AUTH_SALT', getenv( 'WP_SECURE_AUTH_SALT' ) );
define( 'LOGGED_IN_SALT', getenv( 'WP_LOGGED_IN_SALT' ) );
define( 'NONCE_SALT', getenv( 'WP_NONCE_SALT' ) );

/** Absolute path to the WordPress directory. */
defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __FILE__ ) . '/' );

define( 'DB_NAME', getenv( 'DB_NAME' ) );
define( 'DB_USER', getenv( 'DB_USERNAME' ) );
define( 'DB_PASSWORD', getenv( 'DB_PASSWORD' ) );
define( 'DB_HOST', getenv( 'DB_HOST' ) );
define( 'WP_HOME', getenv( 'WP_HOME' ) );
define( 'WP_SITEURL', getenv( 'WP_SITEURL' ) );
define( 'WP_DEBUG', filter_var( getenv( 'WP_DEBUG' ), FILTER_VALIDATE_BOOLEAN ) );

if ( ! isset( $table_prefix ) || empty( $table_prefix ) ) {
	// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
	$table_prefix = 'wp_';
	// phpcs:enable
}

/** Include wp-settings.php */
if ( file_exists( ABSPATH . '/wp-settings.php' ) ) {
	require_once ABSPATH . '/wp-settings.php';
}
