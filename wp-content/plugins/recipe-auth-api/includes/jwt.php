<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Encodes data into a JWT token.
 *
 * @param array $payload  The JWT payload (must include 'exp', 'iat', etc.)
 * @return string|WP_Error
 */
function recipe_jwt_encode( array $payload ) {
  if ( ! defined( 'RECIPE_JWT_SECRET' ) ) {
    return new WP_Error( 'jwt_secret_missing', 'JWT secret not defined in configuration.' );
  }

  try {
    $token = JWT::encode( $payload, RECIPE_JWT_SECRET, 'HS256' );
    return $token;
  } catch ( Exception $e ) {
    return new WP_Error( 'jwt_encode_error', $e->getMessage() );
  }
}

/**
 * Decodes a JWT token and validates signature + expiry.
 *
 * @param string $token  The raw JWT token string.
 * @return object|WP_Error  Decoded payload or error.
 */
function recipe_jwt_decode( string $token ) {
  if ( ! defined( 'RECIPE_JWT_SECRET' ) ) {
    return new WP_Error( 'jwt_secret_missing', 'JWT secret not defined in configuration.' );
  }

  try {
    $decoded = JWT::decode( $token, new Key( RECIPE_JWT_SECRET, 'HS256' ) );
    return $decoded;
  } catch ( Exception $e ) {
    return new WP_Error( 'jwt_decode_error', $e->getMessage() );
  }
}

/**
 * Creates a standard JWT payload for a given WP user.
 *
 * @param int|WP_User $user  The user ID or WP_User object.
 * @param int $ttl  Expiry time in seconds (default 7 days).
 * @return array
 */
function recipe_jwt_build_payload( $user, $ttl = 604800 ) {
  $user = is_object( $user ) ? $user : get_user_by( 'id', $user );
  if ( ! $user ) return [];

  $issuedAt = time();
  $expireAt = $issuedAt + $ttl;

  return [
    'iss'  => get_site_url(),
    'iat'  => $issuedAt,
    'exp'  => $expireAt,
    'sub'  => $user->ID,
    'data' => [
      'email'    => $user->user_email,
      'username' => $user->user_login,
    ],
  ];
}

/**
 * Verifies a JWT token string and returns the user object if valid.
 *
 * @param string $token
 * @return WP_User|WP_Error
 */
function recipe_jwt_verify_user( string $token ) {
  $decoded = recipe_jwt_decode( $token );

  if ( is_wp_error( $decoded ) ) {
    return $decoded;
  }

  $user_id = $decoded->sub ?? null;
  if ( ! $user_id ) {
    return new WP_Error( 'jwt_invalid_sub', 'Missing user ID in token.' );
  }

  $user = get_user_by( 'id', $user_id );
  if ( ! $user ) {
    return new WP_Error( 'jwt_user_not_found', 'User not found.' );
  }

  return $user;
}
