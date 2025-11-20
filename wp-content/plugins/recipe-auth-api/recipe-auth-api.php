<?php
/**
 * Plugin Name: Recipe Auth API
 * Description: Custom plugin for OTP + JWT auth and Recipe APIs.
 * Version: 1.0.0
 * Author: Morteza Rostami
 */

if ( ! defined( 'ABSPATH' ) ) exit; // No direct access

# load composer packages
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}



// Define constants
define( 'RECIPE_AUTH_API_PATH', plugin_dir_path( __FILE__ ) );
define( 'RECIPE_AUTH_API_URL', plugin_dir_url( __FILE__ ) );

# for file upload
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Include files

# helpers for recipe data
require_once RECIPE_AUTH_API_PATH . 'includes/recipe-helpers.php';

# controllers
require_once RECIPE_AUTH_API_PATH . 'includes/class-auth-controller.php';
require_once RECIPE_AUTH_API_PATH . 'includes/class-recipe-public-controller.php';
require_once RECIPE_AUTH_API_PATH . 'includes/class-recipe-private-controller.php';

require_once RECIPE_AUTH_API_PATH . 'includes/helpers.php';
# recipe cpt & metadata
require_once RECIPE_AUTH_API_PATH . 'includes/class-recipe-cpt.php';

/**
 * Allow CORS and cookie-based JWT
 */
/**
 * CORS: Allow React dev server
 */

add_filter('pre_term_slug', function($slug, $term) {
    // Use the raw name so Farsi characters are kept
    if (!empty($_POST['name'])) {
        $slug = $_POST['name'];  // the Persian name the user entered
    }

    // Replace spaces with hyphens
    $slug = str_replace(' ', '-', $slug);

    // Ensure no encoding
    $slug = urldecode($slug);

    return $slug;
}, 10, 2);


add_action('init', function () {
  // Allow cookies + origin
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Origin: http://localhost:5173');
  header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

  // Handle preflight EARLY
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    status_header(200);
    exit;
  }
});

/**
 * Parse JWT from HttpOnly cookie (for /me and private routes)
 */
add_action('rest_api_init', function () {
  register_rest_field('user', 'jwt', [
    'get_callback' => function () {
      return isset($_COOKIE['jwt_token']) ? $_COOKIE['jwt_token'] : null;
    },
  ]);
});

// Initialize plugin
add_action('rest_api_init', function () {
  $auth_controller = new Recipe_Auth_Controller();
  $auth_controller->register_routes();
});

// Initialize
add_action( 'rest_api_init', function() {
  $controller = new Recipe_Public_Controller();
  $controller->register_routes();
});

// Initialize
add_action( 'rest_api_init', function() {
  $controller = new Recipe_Private_Controller();
  $controller->register_routes();
});