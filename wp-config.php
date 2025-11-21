<?php
/**
 * Custom wp-config.php with RECIPE_* plugin support
 * Merges your existing setup for Recipe API plugin and standard WP configs
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ------------------------------
// Load .env for sensitive configuration
// ------------------------------
$env_paths = [
    __DIR__ . '/.env', // root-level
    __DIR__ . '/wp-content/plugins/recipe-auth-api/.env', // plugin-level fallback
];

$env = [];

foreach ($env_paths as $path) {
    if (file_exists($path)) {
        $parsed = parse_ini_file($path, false, INI_SCANNER_TYPED);
        if (is_array($parsed)) {
            $env = array_merge($env, $parsed); // root overrides plugin if duplicated
        }
    }
}

// define RECIPE_* constants
foreach ($env as $key => $value) {
    $const_key = 'RECIPE_' . strtoupper($key);
    if (!defined($const_key)) {
        define($const_key, $value);
    }
}




// ------------------------------
// Determine environment
// ------------------------------
define( 'WP_ENV', getenv('WP_ENV') ?: 'development' );
$is_dev = WP_ENV !== 'production';

// ------------------------------
// WP URLs (optional, for consistency in production)
// ------------------------------
if ( getenv('WP_HOME') ) {
    define( 'WP_HOME', getenv('WP_HOME') );
}
if ( getenv('WP_SITEURL') ) {
    define( 'WP_SITEURL', getenv('WP_SITEURL') );
}

// ------------------------------
// Recipe plugin URLs
// ------------------------------
define( 'RECIPE_GOOGLE_REDIRECT_URI', $is_dev ? $env['GOOGLE_REDIRECT_URI_DEV'] : $env['GOOGLE_REDIRECT_URI_PROD'] );
define( 'RECIPE_FRONTEND_URL',      $is_dev ? $env['FRONTEND_URL_DEV'] : $env['FRONTEND_URL_PROD'] );
define( 'RECIPE_BACKEND_URL',       $is_dev ? $env['BACKEND_URL_DEV'] : $env['BACKEND_URL_PROD'] );

// ------------------------------
// Database Settings
// ------------------------------
define( 'DB_NAME',     getenv('DB_NAME') ?: 'local' );
define( 'DB_USER',     getenv('DB_USER') ?: 'root' );
define( 'DB_PASSWORD', getenv('DB_PASSWORD') ?: 'root' );
define( 'DB_HOST',     getenv('DB_HOST') ?: 'localhost' );
define( 'DB_CHARSET',  'utf8' );
define( 'DB_COLLATE',  '' );

// ------------------------------
// Authentication Unique Keys and Salts
// ------------------------------
define( 'AUTH_KEY',         getenv('AUTH_KEY') ?: 'put-your-default-key-here' );
define( 'SECURE_AUTH_KEY',  getenv('SECURE_AUTH_KEY') ?: 'put-your-default-key-here' );
define( 'LOGGED_IN_KEY',    getenv('LOGGED_IN_KEY') ?: 'put-your-default-key-here' );
define( 'NONCE_KEY',        getenv('NONCE_KEY') ?: 'put-your-default-key-here' );
define( 'AUTH_SALT',        getenv('AUTH_SALT') ?: 'put-your-default-key-here' );
define( 'SECURE_AUTH_SALT', getenv('SECURE_AUTH_SALT') ?: 'put-your-default-key-here' );
define( 'LOGGED_IN_SALT',   getenv('LOGGED_IN_SALT') ?: 'put-your-default-key-here' );
define( 'NONCE_SALT',       getenv('NONCE_SALT') ?: 'put-your-default-key-here' );
define( 'WP_CACHE_KEY_SALT', getenv('WP_CACHE_KEY_SALT') ?: 'put-your-default-key-here' );

// ------------------------------
// Table prefix
// ------------------------------
$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'wp_';

// ------------------------------
// Debugging
// ------------------------------
if ( ! defined('WP_DEBUG') ) {
    define('WP_DEBUG', true);
    define('WP_DEBUG_LOG', true);
    define('WP_DEBUG_DISPLAY', true);
    @ini_set('display_errors', 1);
}

// ------------------------------
// HTTPS behind reverse proxy (optional)
// ------------------------------
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// ------------------------------
// Absolute path
// ------------------------------
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

// ------------------------------
// Setup WordPress vars and includes
// ------------------------------
require_once ABSPATH . 'wp-settings.php';

// ------------------------------
// SMTP Email (optional)
// ------------------------------
add_action('phpmailer_init', function($phpmailer) {
    if (!defined('RECIPE_SMTP_HOST')) return;

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

error_log("=========================\n\n");
error_log('ENV PATH: ' . $env_paths);
error_log('ENV CONTENTS: ' . print_r($env, true));