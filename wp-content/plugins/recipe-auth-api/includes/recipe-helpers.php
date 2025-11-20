<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shared Recipe Response Helpers
 */

function recipe_prepare_for_response( $post ) {
  $post_id = $post->ID;
  $author  = get_user_by( 'id', $post->post_author );

  // 🧩 Determine author avatar
  $avatar_url = null;
  if ( $author ) {
    $custom_avatar = get_user_meta( $author->ID, 'profile_avatar', true );

    if ( ! empty( $custom_avatar ) && filter_var( $custom_avatar, FILTER_VALIDATE_URL ) ) {
      // ✅ Use custom uploaded avatar (valid URL)
      $avatar_url = esc_url( $custom_avatar );
    } else {
      // ✅ Fallback to WordPress avatar
      $avatar_url = get_avatar_url( $author->ID );
    }
  }

  return [
    'id'             => $post_id,
    'title'          => get_the_title( $post ),
    'slug'           => $post->post_name,
    'excerpt'        => get_the_excerpt( $post ),
    'featured_image' => get_the_post_thumbnail_url( $post_id, 'large' ) ?: null,
    'author'         => $author ? [
      'id'     => $author->ID,
      'name'   => $author->display_name,
      'avatar' => $avatar_url, // 👈 use dynamic avatar
    ] : null,
    'date'           => $post->post_date,
    'status'         => $post->post_status,

    'total_time'     => (int) get_post_meta( $post_id, 'total_time', true ),
    'calories'       => (int) get_post_meta( $post_id, 'calories', true ),
    'servings'       => (int) get_post_meta( $post_id, 'servings', true ),
    'difficulty'     => get_post_meta( $post_id, 'difficulty', true ) ?: 'easy',
    'visibility'     => get_post_meta( $post_id, 'visibility', true ),

    'protein'        => floatval( get_post_meta( $post_id, 'protein', true ) ),
    'carbs'          => floatval( get_post_meta( $post_id, 'carbs', true ) ),
    'fat'            => floatval( get_post_meta( $post_id, 'fat', true ) ),
    'fiber'          => floatval( get_post_meta( $post_id, 'fiber', true ) ),

    'description'    => get_post_meta( $post_id, 'description', true ),
    'ingredients'    => recipe_get_array_meta( $post_id, 'ingredients' ),
    'instructions'   => recipe_get_array_meta( $post_id, 'instructions' ),

    'categories'     => recipe_get_taxonomy_terms( $post_id, 'recipe_category' ),
    'tags'           => recipe_get_taxonomy_terms( $post_id, 'recipe_tag' ),

    'link'           => get_permalink( $post_id ),
  ];
}


function recipe_get_array_meta( $post_id, $key ) {
  $value = get_post_meta( $post_id, $key, true );
  return is_array( $value ) ? array_values( $value ) : [];
}

function recipe_get_taxonomy_terms( $post_id, $taxonomy ) {
  $terms = get_the_terms( $post_id, $taxonomy );
  if ( ! $terms || is_wp_error( $terms ) ) return [];
  return array_map( fn( $term ) => [
    'id'   => $term->term_id,
    'name' => $term->name,
    'slug' => $term->slug,
  ], $terms );
}

function recipe_get_base_collection_params() {
  return [
    'page' => [
      'description' => 'Current page.',
      'type'        => 'integer',
      'default'     => 1,
      'minimum'     => 1,
    ],
    'per_page' => [
      'description' => 'Items per page.',
      'type'        => 'integer',
      'default'     => 10,
      'minimum'     => 1,
      'maximum'     => 100,
    ],
  ];
}