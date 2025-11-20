<?php
/**
 * Public Recipe Endpoints
 * Namespace: recipe-auth/v1
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Recipe_Public_Controller {

  private $namespace = 'recipe-auth/v1';
  private $post_type = 'recipe';

  public function register_routes() {
    register_rest_route( $this->namespace, '/recipes', [
      'methods'             => 'GET',
      'callback'            => [ $this, 'get_recipes' ],
      'permission_callback' => '__return_true', // Public
      'args'                => recipe_get_base_collection_params(),
    ]);

    // NEW: GET /recipes/{id}
    register_rest_route( $this->namespace, '/recipes/(?P<id>\d+)', [
      'methods'             => 'GET',
      'callback'            => [ $this, 'get_recipe' ],
      'permission_callback' => '__return_true',
      'args'                => [
        'id' => [
          'validate_callback' => function( $param ) {
            return is_numeric( $param ) && $param > 0;
          },
        ],
      ],
    ]);

    # /categories
    register_rest_route(
      route_namespace: $this->namespace,
      route: '/recipe-categories',
      args: [
        'methods'             => 'GET',
        'callback'            => [ $this, 'get_recipe_categories' ],
        'permission_callback' => '__return_true',
        'args'                => $this->get_categories_params(),
      ]
);
  }

  /**
   * GET /recipes
   */
  public function get_recipes( WP_REST_Request $request ) {
    $args = [
      'post_type'      => $this->post_type,
      'post_status'    => 'publish',
      'posts_per_page' => $request['per_page'] ?? 10, #limit
      'paged'          => $request['page'] ?? 1, # page
      'meta_query'     => [
        [
          'key'     => 'visibility',
          'value'   => 'public',
          'compare' => '=',
        ],
      ],
    ];

    // Search
    if ( ! empty( $request['search'] ) ) {
      $args['s'] = sanitize_text_field( $request['search'] );
    }

    // Category filter
    if ( ! empty( $request['category'] ) ) {
      $args['tax_query'][] = [
        'taxonomy' => 'recipe_category',
        'field'    => 'slug',
        'terms'    => sanitize_text_field( $request['category'] ),
      ];
    }

    // Tag filter
    if ( ! empty( $request['tag'] ) ) {
      $args['tax_query'][] = [
        'taxonomy' => 'recipe_tag',
        'field'    => 'slug',
        'terms'    => sanitize_text_field( $request['tag'] ),
      ];
    }

    // Combine tax queries
    if ( isset( $args['tax_query'] ) && count( $args['tax_query'] ) > 1 ) {
      $args['tax_query']['relation'] = 'AND';
    }

    $query = new WP_Query( $args );
    $recipes = [];

    foreach ( $query->posts as $post ) {
      # basically generate full post-data for UI
      $recipes[] = recipe_prepare_for_response( $post );
    }

    $response = rest_ensure_response( $recipes );

    // Pagination headers
    $response->header( 'X-WP-Total', (int) $query->found_posts );
    $response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

    return $response;
  }

  /**
 * GET /recipes/{id}
 */
  public function get_recipe( WP_REST_Request $request ) {
    $id = $request['id'];
    $post = get_post( $id );

    if ( ! $post || $post->post_type !== $this->post_type || $post->post_status !== 'publish' ) {
      return new WP_Error( 'not_found', 'Recipe not found.', [ 'status' => 404 ] );
    }

    // Check visibility
    $visibility = get_post_meta( $id, 'visibility', true );
    if ( $visibility !== 'public' ) {
      return new WP_Error( 'forbidden', 'This recipe is private.', [ 'status' => 403 ] );
    }

    $recipe = recipe_prepare_for_response( $post );

    return rest_ensure_response( $recipe );
  }

  /**
 * GET /recipe-categories
 */
  public function get_recipe_categories( WP_REST_Request $request ) {
    $per_page = (int) ( $request['per_page'] ?? 100 );
    $page     = (int) ( $request['page'] ?? 1 );
    $offset   = ( $page - 1 ) * $per_page;

    $args = [
      'taxonomy'   => 'recipe_category',
      'hide_empty' => true,
      'number'     => $per_page,
      'offset'     => $offset,
    ];

    $categories = get_terms( $args );

    if ( is_wp_error( $categories ) ) {
      return new WP_Error( 'categories_error', 'Failed to fetch categories.', [ 'status' => 500 ] );
    }

    // Debug
    #error_log( 'Categories query: ' . print_r( $args, true ) );
    #error_log( 'Categories result: ' . print_r( $categories, true ) );

    # force data to array
    $data = array_values( array_map( [ $this, 'prepare_category_for_response' ], $categories ) );
    $response = rest_ensure_response( $data );

    $total = wp_count_terms( 
      args: [ 
        'taxonomy' => 'recipe_category', 
        'hide_empty' => true, # hide categories that don't have recipes  
        ] );

    $pages = $per_page > 0 ? ceil( $total / $per_page ) : 1;

    $response->header( 'X-WP-Total', (int) $total );
    $response->header( 'X-WP-TotalPages', (int) $pages );

    return $response;
  }

  private function prepare_category_for_response( $term ) {
    return [
      'id'          => $term->term_id,
      'name'        => $term->name,
      'slug'        => $term->slug,
      'description' => $term->description,
      'count'       => $term->count,
      'link'        => get_term_link( $term ),
    ];
  }

  private function get_categories_params() {
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
        'default'     => 100,
        'minimum'     => 1,
        'maximum'     => 100,
      ],
    ];
  }
}
