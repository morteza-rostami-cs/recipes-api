<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// Load .env for sensitive configuration
$env_path = __DIR__ . '/wp-content/plugins/recipe-auth-api/.env';

if ( file_exists( $env_path )) {
	
  $env = parse_ini_file( $env_path);

  if ( $env && is_array($env) ) {
    #define( 'RECIPE_JWT_SECRET', $env['JWT_SECRET'] );
		if ( $env && is_array( $env ) ) {

			# loop through all .env variables
			foreach ( $env as $key => $value ) {
				// Define constants like RECIPE_JWT_SECRET, RECIPE_SMTP_HOST, etc.
				$const_key = 'RECIPE_' . strtoupper( $key );
				if ( ! defined( $const_key ) ) {
					define( $const_key, $value );
				}
			}

			# specific env s

			$is_dev = RECIPE_WP_ENV !== 'production'; # check if it's dev

			define( 'RECIPE_GOOGLE_REDIRECT_URI',
				$is_dev ? $env['GOOGLE_REDIRECT_URI_DEV'] : $env['GOOGLE_REDIRECT_URI_PROD']
			);

			define( 'RECIPE_FRONTEND_URL',
				$is_dev ? $env['FRONTEND_URL_DEV'] : $env['FRONTEND_URL_PROD']
			);

			define( 'RECIPE_BACKEND_URL',
				$is_dev ? $env['BACKEND_URL_DEV'] : $env['BACKEND_URL_PROD']
			);
  	}
  }
}

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '<]M.M&FtU$#GNW<-s#mJ)g?{~%uX6rT.P,*A9&?l3jh`PTV!nWom&SMiNewb9[l*' );
define( 'SECURE_AUTH_KEY',   'du&F0uj7kYSZTf^z36~yMp5DJJd1Oa4[7-p}!gU<,B~CBC,wkrkGQ&tx_?a^[J,+' );
define( 'LOGGED_IN_KEY',     'i eE07ZP}$@XI$|ZcRqt4d*fM1_q^Q~C%<{Lu?bEh@1Di0qMko;zygecoYFm$4Ii' );
define( 'NONCE_KEY',         'A*TSM,i(J*.[P:PPdXwk=Q*GpY^pm;Jj7l$l:d5nmQPaH=jt[]+VV6eceJ!)v5TK' );
define( 'AUTH_SALT',         'd56W-,RaKuNCRxRik+D/|!O#o8PZW2EOlalYd3Z,AvD4d)Rqw[,Maq*lM7S)Y6Rc' );
define( 'SECURE_AUTH_SALT',  'Pm`3O.I?Ws3c zER$6U-:2c5!{bo::y}R:Eh@@h-GI^G<)KO~I^6Q53mM#1fP^?Q' );
define( 'LOGGED_IN_SALT',    'wKQQVizzLyJOIYJ]k[cK |7Hy*sULmS;_|-WI,LJmh26h!Q/S$mZr5se:huvmme@' );
define( 'NONCE_SALT',        '6-)o#B1>%C?x# gg8Bj<]d5)2fiYU+Wa^S ;RKsA{XoC,QKfV*Y}z7+L&w-Sbt{>' );
define( 'WP_CACHE_KEY_SALT', 'ZJ(;ZPf.7$}+rGX8o#gCPlDz`I#00B|Eh(<hG?h{^]Fzq4{{]X@POT|)V3$V%bg7' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define('WP_DEBUG', true); 
	define('WP_DEBUG_LOG', true);
	define('WP_DEBUG_DISPLAY', true);
	@ini_set('display_errors', 1);
}

// define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_ENV', getenv('WP_ENV') ?: 'development' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
// error_log($env_path);
// error_log(file_exists( $env_path));

# smtp email
add_action('phpmailer_init', function($phpmailer) {
  $phpmailer->isSMTP();
  $phpmailer->Host       = RECIPE_SMTP_HOST;
  $phpmailer->Port       = RECIPE_SMTP_PORT;
  $phpmailer->SMTPAuth   = true;
  $phpmailer->SMTPSecure = 'tls';
  $phpmailer->Username   = RECIPE_SMTP_USER;
  $phpmailer->Password   = RECIPE_SMTP_PASS;
  $phpmailer->From       = RECIPE_SMTP_FROM_EMAIL;
  $phpmailer->FromName   = RECIPE_SMTP_FROM_NAME;
});
