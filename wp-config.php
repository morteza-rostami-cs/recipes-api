<?php
/**
 * Custom wp-config.php with RECIPE_* plugin support
 * Uses phpdotenv to load .env safely
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ------------------------------
// HTTPS behind reverse proxy
// ------------------------------
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// ------------------------------
// Debugging
// ------------------------------
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
@ini_set('display_errors', 1);

// ------------------------------
// Load .env using phpdotenv
// ------------------------------
require_once __DIR__ . '/wp-content/plugins/recipe-auth-api/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // still load root .env
$dotenv->safeLoad();

// ------------------------------
// Define RECIPE_* constants
// ------------------------------
$recipe_keys = [
    'JWT_SECRET',
    'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME',
    'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET',
    'GOOGLE_REDIRECT_URI_DEV', 'GOOGLE_REDIRECT_URI_PROD',
    'FRONTEND_URL_DEV', 'FRONTEND_URL_PROD',
    'BACKEND_URL_DEV', 'BACKEND_URL_PROD',
];

foreach ($recipe_keys as $key) {
    $const_key = 'RECIPE_' . strtoupper($key);
    if (!defined($const_key)) {
        define($const_key, $_ENV[$key] ?? '');
    }
}

// ------------------------------
// Determine environment
// ------------------------------
define('WP_ENV', $_ENV['WP_ENV'] ?? 'development');
$is_dev = WP_ENV !== 'production';

// ------------------------------
// Recipe plugin URLs
// ------------------------------
define('RECIPE_GOOGLE_REDIRECT_URI', $is_dev ? RECIPE_GOOGLE_REDIRECT_URI_DEV : RECIPE_GOOGLE_REDIRECT_URI_PROD);
define('RECIPE_FRONTEND_URL',      $is_dev ? RECIPE_FRONTEND_URL_DEV : RECIPE_FRONTEND_URL_PROD);
define('RECIPE_BACKEND_URL',       $is_dev ? RECIPE_BACKEND_URL_DEV : RECIPE_BACKEND_URL_PROD);

// ------------------------------
// Database Settings
// ------------------------------
define('DB_NAME',     $_ENV['DB_NAME'] ?? 'local');
define('DB_USER',     $_ENV['DB_USER'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? 'root');
define('DB_HOST',     $_ENV['DB_HOST'] ?? 'localhost');
define('DB_CHARSET',  'utf8');
define('DB_COLLATE',  '');

// ------------------------------
// Authentication Unique Keys and Salts
// ------------------------------
define('AUTH_KEY',         $_ENV['AUTH_KEY'] ?? 'put-your-default-key-here');
define('SECURE_AUTH_KEY',  $_ENV['SECURE_AUTH_KEY'] ?? 'put-your-default-key-here');
define('LOGGED_IN_KEY',    $_ENV['LOGGED_IN_KEY'] ?? 'put-your-default-key-here');
define('NONCE_KEY',        $_ENV['NONCE_KEY'] ?? 'put-your-default-key-here');
define('AUTH_SALT',        $_ENV['AUTH_SALT'] ?? 'put-your-default-key-here');
define('SECURE_AUTH_SALT', $_ENV['SECURE_AUTH_SALT'] ?? 'put-your-default-key-here');
define('LOGGED_IN_SALT',   $_ENV['LOGGED_IN_SALT'] ?? 'put-your-default-key-here');
define('NONCE_SALT',       $_ENV['NONCE_SALT'] ?? 'put-your-default-key-here');
define('WP_CACHE_KEY_SALT', $_ENV['WP_CACHE_KEY_SALT'] ?? 'put-your-default-key-here');

// ------------------------------
// Table prefix
// ------------------------------
$table_prefix = $_ENV['WORDPRESS_TABLE_PREFIX'] ?? 'wp_';

// ------------------------------
// WordPress URLs (optional)
// ------------------------------
if (isset($_ENV['WP_HOME'])) {
    define('WP_HOME', $_ENV['WP_HOME']);
}
if (isset($_ENV['WP_SITEURL'])) {
    define('WP_SITEURL', $_ENV['WP_SITEURL']);
}

// ------------------------------
// Absolute path
// ------------------------------
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// ------------------------------
// Setup WordPress vars and includes
// ------------------------------
require_once ABSPATH . 'wp-settings.php';

// ------------------------------
// SMTP Email configuration
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

// ------------------------------
// Debug JWT secret
// ------------------------------
file_put_contents(__DIR__ . '/env_debug.log', date('Y-m-d H:i:s') . " :: RECIPE_JWT_SECRET=" . (RECIPE_JWT_SECRET ?? 'MISSING') . "\n", FILE_APPEND);
