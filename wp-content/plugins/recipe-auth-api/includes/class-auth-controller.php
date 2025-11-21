<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// use Firebase\JWT\JWT;
// use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key; // ← This is the missing class

require_once RECIPE_AUTH_API_PATH . 'includes/jwt.php';
require_once RECIPE_AUTH_API_PATH . 'includes/class-email-service.php';
// require_once RECIPE_AUTH_API_PATH . 'includes/class-send-sms.php';
// require_once RECIPE_AUTH_API_PATH . 'includes/class-sms-service.php';

class Recipe_Auth_Controller {
  private $namespace = 'recipe-auth/v1';
  private static $auth_user = null;   // ← will be filled by require_auth()

  public function register_routes() {
    // /login route
    register_rest_route( 
      route_namespace: $this->namespace, 
      route: '/login', 
      args: [
      'methods'  => 'POST',
      'callback' => [ $this, 'handle_login' ],
      'permission_callback' => [$this, 'require_guest'],
    ]);

    # /verify
    register_rest_route(
      route_namespace: 'recipe-auth/v1', 
      route: '/verify', 
      args: [
      'methods'  => 'POST',
      'callback' => [$this, 'verify_user'],
      'permission_callback' => [$this, 'require_guest'],
    ]);

    # /logout
    register_rest_route( 
      route_namespace: 'recipe-auth/v1', 
      route: '/logout', 
      args: [
        'methods'  => 'POST',
        'callback' => [ $this, 'logout_user' ],
        'permission_callback' => [$this, 'require_auth'],
      ]);
    
    # /me
    register_rest_route( 
      route_namespace: 'recipe-auth/v1', 
      route: '/me', 
      args: [
        'methods'  => 'GET',
        'callback' => [ $this, 'get_current_user' ],
        'permission_callback' => [$this, 'require_auth'],
      ]);
    
    // GOOGLE OAUTH ROUTES
    register_rest_route( $this->namespace, '/google/start', [
      'methods'  => 'GET',
      'callback' => [ $this, 'google_oauth_start' ],
      'permission_callback' => [$this, 'require_guest'],
    ]);

    register_rest_route( $this->namespace, '/google/callback', [
      'methods'  => 'GET',
      'callback' => [ $this, 'google_oauth_callback' ],
      'permission_callback' => '__return_true', // Allow public access
    ]);
  }

  /**
   * Handle /login endpoint
   * Request body: { "email": "user@example.com" } OR { "phone": "+989123456789" }
   */
  public function handle_login( WP_REST_Request $request ) {
    $email = $request->get_param('email') ? sanitize_email($request->get_param('email')) : '';
    $phone = $request->get_param('phone') ? sanitize_text_field($request->get_param('phone')) : '';

    if ( empty( $email ) && empty( $phone ) ) {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'Email or phone is required.',
      ], 400);
    }

    // Generate a 6-digit OTP
    $otp = wp_rand( 100000, 999999 );

    // Use the email or phone as the key
    $key = $email ? 'otp_' . md5( $email ) : 'otp_' . md5( $phone );

    // Store in transient (expires in 5 min)
    set_transient( $key, $otp, 5 * MINUTE_IN_SECONDS );

    // Send OTP
    if ( $email ) {
      #wp_mail( $email, 'Your Login Code', "Your OTP code is: $otp" );

      # send otp
      // $sent = Email_Service::send_otp(email: $email, otp_code: $otp);

      // if (!$sent) {
      //   return new WP_Error(
      //     code: 'email_failed',
      //     message: 'Failed to send OTP email.',
      //     data: [
      //       'status' => 500,
      //     ]
      //   );
      // }

      error_log("OTP success for $email is $otp");
    } else {
      // Mock SMS sending

      # sending a SMS
      // $api_key = RECIPE_SMS_API_KEY;
      // $username = RECIPE_SMS_USERNAME;
      // $from = RECIPE_SMS_FROM;

      // $token = RECIPE_SMS_TOKEN;

      # instance of sms class
      // $sms_service = new SMS_Service(
      //   username: $username, 
      //   api_key: $api_key, 
      //   from: $from
      // );

      // $sms = new SMS_Service(token: $token);

      // $message = "کد تایید شما: {$otp}\nاین کد تا ۵ دقیقه معتبر است.\nلغو۱۱";

      // $response = $sms->send_sms(
      //   to: $phone,
      //   message: $message,
      // );

      // if ($response) {
      //   error_log("✅ OTP sent successfully!");
      // } else {
      //   error_log("❌ OTP sending failed!");
      // }
    }

    return new WP_REST_Response([
      'success' => true,
      'message' => 'OTP sent successfully.',
      'debug_otp' => defined('WP_DEBUG') && WP_DEBUG ? $otp : null, // for dev
    ], 200);
  }

  public function verify_user( $request ) {
    $params = $request->get_json_params();

    if ( empty($params['email']) && empty($params['phone']) ) {
      return new WP_REST_Response([ 'error' => 'Missing fields' ], 400);
    }

    if ( empty($params['otp']) ) {
      return new WP_REST_Response([ 'error' => 'Missing fields' ], 400);
    }

    $identifier = sanitize_text_field( $params['email'] ?? $params['phone'] ?? '' );
    $otp = sanitize_text_field( $params['otp'] ?? '' );

    if ( empty( $identifier ) || empty( $otp ) ) {
      return new WP_REST_Response([ 'error' => 'Missing fields' ], 400);
    }

    // Determine if it's an email or phone
    $is_email = filter_var( $identifier, FILTER_VALIDATE_EMAIL );

    // Get stored OTP
    $otp_key = 'otp_' . md5( $identifier );
    $stored_otp = get_transient( $otp_key );

    if ( ! $stored_otp || $stored_otp !== $otp ) {
      return new WP_REST_Response([ 'error' => 'Invalid or expired OTP' ], 401);
    }

    // Find or create user
    $user = null;

    if ( $is_email ) {
      $user = get_user_by( 'email', $identifier );
    } else {
      # get user by metadata phone_number
      $users = get_users([
        'meta_key'   => 'phone_number',
        'meta_value' => $identifier,
        'number'     => 1,
      ]);
      if ( ! empty( $users ) ) {
        $user = $users[0];
      }
    }

    $created = false;

    if ( ! $user ) {
      $username = $this->generate_unique_username( $is_email ? explode('@', $identifier)[0] : 'user' );
      $password = wp_generate_password( 16, true, true );

      $userdata = [
        'user_login' => $username,
        'user_pass'  => $password,
        'user_email' => $is_email ? $identifier : '',
        'role'       => 'subscriber',
      ];

      $user_id = wp_insert_user( $userdata );

      if ( is_wp_error( $user_id ) ) {
        return new WP_REST_Response([ 'error' => 'User creation failed' ], 500);
      }

      $user = get_user_by( 'id', $user_id );
      $created = true;

      # user sent a phone number -> store in metadata
      if ( ! $is_email ) {
        update_user_meta( $user_id, 'phone_number', $identifier );
      }

      // Generate random avatar (DiceBear Pixel-Art)
      $avatar_url = 'https://api.dicebear.com/6.x/pixel-art/svg?seed=' . urlencode( $username );
      update_user_meta( $user_id, 'profile_avatar', $avatar_url );
    }

    echo RECIPE_JWT_SECRET;
    #error_log(print_r(RECIPE_JWT_SECRET, true));
    // JWT generation
    if ( ! defined( 'RECIPE_JWT_SECRET' ) ) {
      return new WP_REST_Response([ 'error' => 'JWT secret not defined' ], 500);
    }

    $payload = recipe_jwt_build_payload( $user );

    $jwt = recipe_jwt_encode( $payload );

    // Set JWT as HttpOnly cookie
    $cookie_options = [
      'expires'  => $payload['exp'],
      'path'     => '/',
      'secure'   => isset($_SERVER['HTTPS']),
      'httponly' => true,
      'samesite' => 'None', // if frontend is on different domain
    ];
    setcookie( 'recipe_jwt', $jwt, $cookie_options );

    // Delete OTP transient (one-time use)
    delete_transient( $otp_key );

    // Response
    $avatar_url = get_user_meta( $user->ID, 'profile_avatar', true );

    return new WP_REST_Response([
      'success' => true,
      'created' => $created,
      'user' => [
        'id'       => $user->ID,
        'username' => $user->user_login,
        'email'    => $user->user_email,
        'avatar'   => $avatar_url,
      ]
    ], 200);
  }

  # POST: /me
  public function get_current_user( WP_REST_Request $request ) {
    // 1️⃣ Get JWT from cookie
    $jwt = isset($_COOKIE['recipe_jwt']) ? $_COOKIE['recipe_jwt'] : null;

    if ( ! $jwt ) {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'No authentication token found.'
      ], 401);
    }

    // 2️⃣ Decode JWT
    $user = recipe_jwt_verify_user(token: $jwt);

    if ( ! $user ) {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'User not found.'
      ], 404);
    }

    $user_id = $user->ID;

    // 3️⃣ Build safe user data (exclude sensitive info)
    $avatar = get_user_meta($user_id, 'profile_avatar', true);
    $phone  = get_user_meta($user_id, 'phone_number', true);

    $data = [
      'id'          => $user->ID,
      'username'    => $user->user_login,
      'email'       => $user->user_email,
      'displayName' => $user->display_name,
      'avatar'      => $avatar ?: get_avatar_url($user->ID),
      'phone'       => $phone ?: null,
      'registered'  => $user->user_registered,
      'roles'       => $user->roles,
    ];

    return new WP_REST_Response([
      'success' => true,
      'user' => $data,
    ], 200);
  }

  # POST: /logout
  public function logout_user( WP_REST_Request $request ) {
    // Remove JWT cookie
    setcookie(
      'recipe_jwt',        // name
      '',                  // empty value
      time() - 3600,       // expired in the past
      '/',                 // path
      '',                  // domain (empty = current)
      is_ssl(),            // secure only if HTTPS
      true                 // HttpOnly
    );

    return new WP_REST_Response([
      'success' => true,
      'message' => 'Logged out successfully. JWT cookie removed.'
    ], 200);
  }

  # google social auth

  # /google/start

  // GOOGLE OAUTH: STEP 1 - Redirect to Google
  public function google_oauth_start() {

    # get the google client_id and secret
    if ( ! defined('RECIPE_GOOGLE_CLIENT_ID') || ! defined('RECIPE_GOOGLE_REDIRECT_URI') ) {
      wp_die('Google OAuth not configured.', 'Auth Error', ['response' => 500]);
    }

    # store a code in transient (wp temp storage) -> google sends this back in /callback route
    $state = bin2hex(random_bytes(16));
    set_transient('google_oauth_state_' . $state, true, 15 * MINUTE_IN_SECONDS);

    # 
    $params = [
      'client_id'     => RECIPE_GOOGLE_CLIENT_ID,
      'redirect_uri'  => RECIPE_GOOGLE_REDIRECT_URI,
      'response_type' => 'code', # authorization code vs implicit token
      'scope'         => 'openid email profile', # request userData, name, avatar
      'state'         => $state, # anti-CSRF token
      'prompt'        => 'select_account', # force to show the account chooser UI
      'access_type'   => 'offline', # refresh token
    ];

    # redirect to google consent screen
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    wp_redirect($auth_url);
    exit;
  }
  

  # /google/callback

  // GOOGLE OAUTH: STEP 2 - Handle callback, create user, set JWT
  public function google_oauth_callback(WP_REST_Request $request) {
    # google authorization code
    $code  = $request->get_param('code');
    # state i send in in /google/start
    $state = $request->get_param('state');

    if (!$code || !$state) {
      wp_die('Missing code or state.', 'Auth Error', ['response' => 400]);
    }

    // Validate the state parameter (CSRF protection) -> check transient for state code
    $transient_key = 'google_oauth_state_' . $state;
    if (!get_transient($transient_key)) {
      wp_die('Invalid or expired state.', 'Auth Error', ['response' => 400]);
    }
    delete_transient($transient_key);

    // Exchange code for tokens
    $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
      'body' => [
        'code'          => $code,
        'client_id'     => RECIPE_GOOGLE_CLIENT_ID,
        'client_secret' => RECIPE_GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => RECIPE_GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
      ],
      'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
      'timeout' => 15,
    ]);

    if (is_wp_error($token_response)) {
      wp_die('Token exchange failed: ' . $token_response->get_error_message(), 'Auth Error', ['response' => 500]);
    }

    # google access token
    $tokens = json_decode(wp_remote_retrieve_body($token_response), true);
    $access_token = $tokens['access_token'] ?? null;

    if (!$access_token) {
      wp_die('No access token received.', 'Auth Error', ['response' => 500]);
    }

    # send access token and => get google user data
    // ✅ Fetch user info directly from Google (no need for /v3/certs)
    $user_info_response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . urlencode($access_token));
    if (is_wp_error($user_info_response)) {
      wp_die('Failed to fetch Google user info.', 'Auth Error', ['response' => 500]);
    }

    # extract google user data
    $google_user = json_decode(wp_remote_retrieve_body($user_info_response), true);
    if (empty($google_user['email'])) {
      wp_die('No email in Google user data.', 'Auth Error', ['response' => 400]);
    }

    $email = sanitize_email($google_user['email']);
    $name = sanitize_text_field($google_user['name'] ?? '');
    $picture = $google_user['picture'] ?? '';

    // Then continue with your user creation/login logic
    // (same as your verify_user() logic: create WP user, set avatar, generate JWT cookie, redirect, etc.)

    // Example quick success log:
    error_log("✅ Google login success for {$email}");


    // ✅ Find or create user in WordPress
    $user = get_user_by('email', $email);
    $created = false;

    if (!$user) {
      $username = $this->generate_unique_username($name ?: 'user');
      $password = wp_generate_password(16, true, true);

      $user_id = wp_insert_user([
        'user_login'   => $username,
        'user_pass'    => $password,
        'user_email'   => $email,
        'display_name' => $name,
        'role'         => 'subscriber',
      ]);

      if (is_wp_error($user_id)) {
        wp_die('Failed to create user: ' . $user_id->get_error_message(), 'Auth Error', ['response' => 500]);
      }

      $user = get_user_by('id', $user_id);
      $created = true;

      // Sideload avatar from Google profile
      if ($picture) {
        $this->sideload_user_avatar($user_id, $picture);
      }
    }

    // ✅ Generate JWT token for this user
    if (!defined('RECIPE_JWT_SECRET')) {
      wp_die('JWT secret not configured.', 'Auth Error', ['response' => 500]);
    }

    $payload = recipe_jwt_build_payload($user);
    $jwt = recipe_jwt_encode($payload);

    // ✅ Set JWT as HttpOnly cookie
    $cookie_options = [
      'expires'  => $payload['exp'],
      'path'     => '/',
      'secure'   => isset($_SERVER['HTTPS']),
      'httponly' => true,
      'samesite' => 'None', // if frontend is on another domain
    ];
    setcookie('recipe_jwt', $jwt, $cookie_options);

    // ✅ Redirect to frontend dashboard
    $redirect_url = defined('RECIPE_FRONTEND_URL')
      ? trailingslashit(RECIPE_FRONTEND_URL) . 'dashboard'
      : '/';

    wp_redirect($redirect_url);
    exit;

  }

  # google helpers

  // Helper: Sideload avatar from URL -> store avatar in metadata
  private function sideload_user_avatar( $user_id, $image_url ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($image_url);
    if ( is_wp_error($tmp) ) return;

    $file_array = [
      'name'     => 'avatar_' . $user_id . '.jpg',
      'type'     => 'image/jpeg',
      'tmp_name' => $tmp,
      'error'    => 0,
      'size'     => filesize($tmp),
    ];

    $attachment_id = media_handle_sideload($file_array, 0);
    if ( is_wp_error($attachment_id) ) {
      @unlink($tmp);
      return;
    }

    $attachment_url = wp_get_attachment_url($attachment_id);
    update_user_meta($user_id, 'profile_avatar', $attachment_url);
    @unlink($tmp);
  }

  ## middlewares **********************



  /** --------------------------------------------------------------
   *  MIDDLEWARE: ONLY AUTHENTICATED USERS
   *  -------------------------------------------------------------- */
  public function require_auth( WP_REST_Request $request ): bool {
    $jwt = $_COOKIE['recipe_jwt'] ?? null;

    if ( ! $jwt ) {
      $this->send_json_error( 'No authentication token found.', 401 );
      return false;
    }

    $user = recipe_jwt_verify_user( token: $jwt );

    if ( is_wp_error( $user ) ) {
      $this->send_json_error( $user->get_error_message(), 401 );
      return false;
    }

    if ( ! $user ) {
      $this->send_json_error( 'User not found.', 404 );
      return false;
    }

    // ← make it globally reachable inside the route callback
    self::$auth_user = $user;

    return true;
  }

  /** --------------------------------------------------------------
   *  MIDDLEWARE: ONLY GUESTS (no JWT cookie)
   *  -------------------------------------------------------------- */
  public function require_guest( WP_REST_Request $request ): bool {
    $jwt = $_COOKIE['recipe_jwt'] ?? null;

    if ( $jwt ) {
      $this->send_json_error( 'You are already logged in.', 403 );
      return false;
    }

    return true;
  }

  /** --------------------------------------------------------------
   *  Helper – uniform JSON error response (used by middlewares)
   *  -------------------------------------------------------------- */
  private function send_json_error( string $message, int $status = 400 ) {
    wp_send_json( [
      'success' => false,
      'message' => $message,
    ], $status );
  }

  /** --------------------------------------------------------------
   *  Helper – get the authenticated user from inside a route
   *  -------------------------------------------------------------- */
  public static function get_auth_user(): ?WP_User {
    return self::$auth_user;
  }

  ## helper methods *******************
  private function generate_unique_username( $base ) {
    $username = sanitize_user( $base, true );
    while ( username_exists( $username ) ) {
      $username = $base . '_' . wp_generate_password( 4, false, false );
    }
    return $username;
  }
}
