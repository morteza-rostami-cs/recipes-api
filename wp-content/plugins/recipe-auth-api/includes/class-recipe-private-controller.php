<?php
/**
 * Private Recipe Endpoints – Auth Required
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Recipe_Private_Controller {

  private $namespace = 'recipe-auth/v1';
  private $post_type = 'recipe';
  private $auth_controller;

  public function __construct() {
    $this->auth_controller = new Recipe_Auth_Controller();
  }

  public function register_routes() {
    register_rest_route(
      route_namespace: $this->namespace,
      route: '/user/recipes',
      args: [
        'methods'             => 'GET',
        'callback'            => [ $this, 'get_user_recipes' ],
        'permission_callback' => [ $this->auth_controller, 'require_auth' ],
        'args'                => recipe_get_base_collection_params(),
      ]
    );

    # /create
    register_rest_route(
      route_namespace: $this->namespace,
      route: '/user/recipes/create',
      args: [
        'methods'             => 'POST',
        'callback'            => [ $this, 'create_recipe' ],
        'permission_callback' => [ $this->auth_controller, 'require_auth' ],
        'args'                => $this->get_create_recipe_args(),
      ]
    );

    # /update
    register_rest_route(
      route_namespace: $this->namespace,
      // /recipe-auth/v1/user/recipes/66/edit
      route: '/user/recipes/(?P<id>\d+)/edit',
      args: [
        'methods'             => 'POST',
        'callback'            => [ $this, 'update_recipe' ],
        'permission_callback' => [ $this->auth_controller, 'require_auth' ],
        'args'                => array_merge(
          [ 
            'id' => [
                'validate_callback' => function( $value, $request, $param ) {
                  return is_numeric( $value ) && $value > 0;
                }
              ]
          ],
          $this->get_create_recipe_args() // reuse validation
        ),
      ]
    );

    # /delete
    register_rest_route(
      route_namespace: $this->namespace,
      route: '/user/recipes/(?P<id>\d+)',
      args: [
        'methods'             => 'DELETE',
        'callback'            => [ $this, 'delete_recipe' ],
        'permission_callback' => [ $this->auth_controller, 'require_auth' ],
        'args'                => [
          'id' => [
            'validate_callback' => function( $value ) {
              return is_numeric( $value ) && $value > 0;
            }
          ]
        ],
      ]
    );

    # /categories for create recipe form
    register_rest_route('recipe-auth/v1', '/categories/all', [
      'methods'             => WP_REST_Server::READABLE,
      'callback'            => [$this, 'get_all_categories'],
      'permission_callback' => [ $this->auth_controller, 'require_auth' ], // reuse auth
      'args'                => [
          'per_page' => [
              'default'           => 100,
              'sanitize_callback' => 'absint',
          ],
          'page' => [
              'default'           => 1,
              'sanitize_callback' => 'absint',
          ],
      ],
    ]);
  }

  /**
   * GET /user/recipes
   */
  public function get_user_recipes( WP_REST_Request $request ) {
    $user = $this->auth_controller->get_auth_user();

    if ( ! $user ) {
      return new WP_Error( 'unauthorized', 'User not authenticated.', [ 'status' => 401 ] );
    }

    $args = [
      'post_type'      => $this->post_type,
      'post_status'    => 'any',
      'author'         => $user->ID,
      'posts_per_page' => $request['per_page'] ?? 10,
      'paged'          => $request['page'] ?? 1,
      'orderby'        => 'date',
      'order'          => $request['sort'] === 'oldest' ? 'ASC' : 'DESC',
    ];

    // Visibility filter
    if ( ! empty( $request['visibility'] ) ) {
      $allowed = [ 'public', 'private' ];
      $vis     = $request['visibility'];
      if ( in_array( $vis, $allowed ) ) {
        $args['meta_query'][] = [
          'key'     => 'visibility',
          'value'   => $vis,
          'compare' => '=',
        ];
      }
    }

    $query   = new WP_Query( $args );
    $recipes = [];

    foreach ( $query->posts as $post ) {
      $recipes[] = recipe_prepare_for_response( $post );
    }

    $response = rest_ensure_response( $recipes );
    $response->header( 'X-WP-Total', (int) $query->found_posts );
    $response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

    return $response;
  }

  # POST /recipes/create

  public function create_recipe( WP_REST_Request $request ) {

    # get auth user
    $user = $this->auth_controller->get_auth_user();

    # check if auth user exist
    if ( ! $user ) {
      return new WP_Error( 'unauthorized', 'User not authenticated.', [ 'status' => 401 ] );
    }

    // 1. Insert post -> some values are stored directly on CPT recipe
    $post_id = wp_insert_post(
      postarr: [

      # get_params -> returns all parameters — that includes body, query string, and URL/path parameters, all merged into a single associative array.
      'post_title'   => $request->get_param('title'),
      # store -> description in excerpt
      'post_excerpt' => $request->get_param('description') ?? '',
      'post_status'  => 'publish',
      'post_type'    => $this->post_type, # recipe CPT
      'post_author'  => $user->ID,
      ], 
      wp_error: true # returns a WP_Error OR postId
    );
  
    # check for post creation error
    if ( is_wp_error( $post_id ) ) return $post_id;

    // 2. Handle featured image
    # _FILES -> contains uploaded files
    if ( ! empty( $_FILES['featured_image']['name'] ) ) {

      # load some scripts for upload
      if ( ! function_exists( 'media_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
      }

      # store/upload file, and create metadata and db stuff -> return error or attachment_id
      $attachment_id = media_handle_upload(
        'featured_image',
        $post_id,
        [ 'post_title' => sanitize_file_name( $_FILES['featured_image']['name'] ) ]
      );

      # check file upload success
      if ( is_wp_error( $attachment_id ) ) {
        # delete cpt if file upload fails
        wp_delete_post( 
          post_id: $post_id, 
          force_delete: true 
        );
        return $attachment_id;
      }

      # set the thumbnail for post
      set_post_thumbnail( $post_id, $attachment_id );
    }

    // 3. Meta fields
    # some of the meta fields -> meta_name => validation_function
    $meta_fields = [
      # convert to int
      'total_time'   => fn($v) => absint(maybeint: $v),
      'calories'     => fn($v) => absint($v),
      'servings'     => fn($v) => absint($v),
      # sanitize a string
      'difficulty'   => 'sanitize_text_field',
      'visibility'   => 'sanitize_text_field',
      # convert to float
      'protein'      => fn($v) => (float) $v,
      'carbs'        => fn($v) => (float) $v,
      'fat'          => fn($v) => (float) $v,
      'fiber'        => fn($v) => (float) $v,
      'description'  => 'sanitize_textarea_field',
    ];

    # loop through metadata s -> only numeric, string and boolean (primitive data)
    foreach ( $meta_fields as $key => $sanitize ) {
      # check it a param/key exists => eg: fiber => value
      if ( $request->has_param($key) ) {
        # get the value: eg: fiber = 3
        $value = $request->get_param($key);

        # check if $sanitize is a function
        if ( is_callable($sanitize) ) {
          # sanitize the value of our param -> using functions we define above in meta_fields
          $value = $sanitize($value);

        }

        # update cpt meta data for each key => value
        update_post_meta(
          post_id: $post_id, 
          meta_key: $key, 
          meta_value: $value # wp serialize array for storage
        );
      }
    }

    // Arrays
    /*
    for getting array here: 
      front must send : 
      ingredients[] = 'item1'
      ingredients[] = 'item2'
      eg: formData.append('ingredients[]', value1);
      # otherwise: we wp gets json , not array
    */ 
    foreach (['ingredients', 'instructions'] as $array_key) {
      if ( $request->has_param($array_key) ) {
        $arr = $request->get_param($array_key);

        update_post_meta(
          post_id: $post_id, 
          meta_key: $array_key, 
          # be sure -> it is an array
          meta_value: is_array($arr) ? $arr : []
        );
      }
    }

    // 4. Category (single)
    # category = 'dinner' -> slug
    if ( $request->has_param('category') ) {
      # get category value
      $category_slug = sanitize_title( $request->get_param('category'));

      # get taxonomy by slug 
      # returns: WP_Term object or false on error
      # return value: WP_Term|array|false
      # might be array or object 
      $term = get_term_by(
        field: 'slug', 
        value: $category_slug, 
        taxonomy: 'recipe_category'
      );
      
      # $term = category_slug
      # check if term exist -> term is an taxonomy object -> $term->name = "dinner"
      # if category does not exist create it
      if ( ! $term ) { # most probably it does exists -> cause: i get them from server
        # Return type: on success wp_insert_term() returns an array with keys 'term_id' and 'term_taxonomy_id'.
        # on failure -> error
        # return value: array|WP_Error
        $inserted = wp_insert_term( # store $category in taxonomy
          term: $category_slug, 
          taxonomy: 'recipe_category',
          args: [
            'slug' => $slug,
            # create name from slug
            'name' => ucfirst( str_replace( '-', ' ', $slug ) ),
          ]
        );

        # check for error
        if ( is_wp_error( $inserted ) ) {
          return new WP_Error(
            'term_creation_failed',
            'Failed to create category: ' . $inserted->get_error_message(),
            [ 'status' => 500 ]
          );
        }
        
        # set term_id 
        $term_id = $inserted['term_id'];

      } else {
      # $term/category does exist
      $term_id = $term->term_id; # here $term is an object
      }

      // Now safely set the post’s category
      wp_set_object_terms( $post_id, (int) $term_id, 'recipe_category' );
    } 

    // 5. Tags (array or JSON)
    if ( $request->has_param('tags') ) {
      # get tags from request -> it should be array 
      # -> cause i am doing this in front: formData.append(tags[], value)
      $tags_raw = $request->get_param('tags');
      
      # check maybe it is json -> decode it
      if ( is_string($tags_raw) ) $tags_raw = json_decode($tags_raw, true);

      # be sure that it is array
      $tags = array_filter(array_unique(array_map('sanitize_text_field', (array) $tags_raw)));

      # if not empty -> set the tags
      if ( ! empty($tags) ) {
        wp_set_object_terms($post_id, $tags, 'recipe_tag');
      }
    }

    // 6. Return
    $post = get_post($post_id);
    return rest_ensure_response(recipe_prepare_for_response($post));
  }


  # PUT user/recipes/edit

  public function update_recipe( WP_REST_Request $request ) {
    $user = $this->auth_controller->get_auth_user();
    if ( ! $user ) {
      return new WP_Error('unauthorized', 'Authentication required.', [ 'status' => 401 ]);
    }

    # get & convert post_id
    $post_id = intval($request['id']);
    # get post by id
    $post = get_post($post_id);

    # be sure : post exists & it is recipe type
    if ( ! $post || $post->post_type !== $this->post_type ) {
      return new WP_Error('not_found', 'Recipe not found.', [ 'status' => 404 ]);
    }

    # check that: authUser owns the post
    if ( intval($post->post_author) !== $user->ID ) {
      return new WP_Error('forbidden', 'You can only edit your own recipes.', [ 'status' => 403 ]);
    }

    // 1. Update post
    $update = [ 'ID' => $post_id ];
    if ( $request->has_param('title') ) $update['post_title'] = $request->get_param('title');
    if ( $request->has_param('description') ) $update['post_excerpt'] = $request->get_param('description');

    # update cpt
    $result = wp_update_post(
      postarr: $update, 
      wp_error: true
    );
    # check for error
    if ( is_wp_error($result) ) return $result;

    # front input is -> File or 'delete'
    # File goes into -> get_file_params
    # "delete" goes in -> get_param
    $files = $request->get_file_params();
    $image_action = $request->get_param('featured_image') ?? null;
    
    if ($image_action === 'delete') {
      # front asked to delete image
      $old_thumb = get_post_thumbnail_id(post: $post_id);

      if ($old_thumb) {
        wp_delete_attachment($old_thumb, true);
      }
      delete_post_thumbnail($post_id);
      error_log("✅ Featured image deleted for post $post_id");
    }
    # if front sends a File
    else if (!empty($files['featured_image'])) {
      // Case 2: new image file uploaded
      if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
      }

      $old_thumb = get_post_thumbnail_id($post_id);
      # file upload happens behind the scene -> media_handle_upload
      $attachment_id = media_handle_upload('featured_image', $post_id);

      if (!is_wp_error($attachment_id)) {
        set_post_thumbnail($post_id, $attachment_id);
        if ($old_thumb) wp_delete_attachment($old_thumb, true);
        error_log("✅ Featured image updated for post $post_id");
      } else {
        error_log("❌ Upload failed: " . $attachment_id->get_error_message());
      }
    }

    // 3. Meta fields
    $meta_fields = [
      'total_time'   => fn($v) => absint($v),
      'calories'     => fn($v) => absint($v),
      'servings'     => fn($v) => absint($v),
      'difficulty'   => 'sanitize_text_field',
      'visibility'   => 'sanitize_text_field',
      'protein'      => fn($v) => (float) $v,
      'carbs'        => fn($v) => (float) $v,
      'fat'          => fn($v) => (float) $v,
      'fiber'        => fn($v) => (float) $v,
      'description'  => 'sanitize_textarea_field',
    ];

    foreach ( $meta_fields as $key => $sanitize ) {
      if ( $request->has_param($key) ) {
        $val = $request->get_param($key);
        if ( is_callable($sanitize) ) $val = $sanitize($val);
        update_post_meta($post_id, $key, $val);
      }
    }

    foreach (['ingredients', 'instructions'] as $key) {
      if ( $request->has_param($key) ) {
        $arr = $request->get_param($key);
        update_post_meta($post_id, $key, is_array($arr) ? $arr : []);
      }
    }

    // 4. Category (single)
    if ($request->has_param('category')) {
      $slug = sanitize_title( $request->get_param('category'));

      # find by slug
      $term = get_term_by(
        field: 'slug',
        value: $slug, 
        taxonomy: 'recipe_category',
      );

      // If not found, create it
      if ( ! $term ) {
        $inserted = wp_insert_term( 
          term: ucfirst( str_replace( '-', ' ', $slug ) ), // readable name
          taxonomy: 'recipe_category',
          args: [ 'slug' => $slug ]
        );

        if ( is_wp_error( $inserted ) ) {
          return new WP_Error(
            'term_creation_failed',
            'Failed to create category: ' . $inserted->get_error_message(),
            [ 'status' => 500 ]
          );
        }

        $term_id = $inserted['term_id'];
      } else {
        $term_id = $term->term_id;
      }

      // Assign or replace the category
      wp_set_object_terms( $post_id, (int) $term_id, 'recipe_category' );
    }

    // 5. Tags
    if ( $request->has_param('tags') ) {
      $tags_raw = $request->get_param('tags');
      if ( is_string($tags_raw) ) $tags_raw = json_decode($tags_raw, true);
      $tags = array_filter(array_unique(array_map('sanitize_text_field', (array) $tags_raw)));

      if ( ! empty($tags) ) {
        wp_set_object_terms($post_id, $tags, 'recipe_tag');
      }
    }

    // 6. Return updated
    $post = get_post($post_id);
    return rest_ensure_response(recipe_prepare_for_response($post));
  }


  # DELETE
  public function delete_recipe( WP_REST_Request $request ) {
    $user = $this->auth_controller->get_auth_user();
    if ( ! $user ) {
      return new WP_Error( 'unauthorized', 'Authentication required.', [ 'status' => 401 ] );
    }

    $post_id = $request['id'];
    $post    = get_post( $post_id );

    if ( ! $post || $post->post_type !== $this->post_type ) {
      return new WP_Error( 'not_found', 'Recipe not found.', [ 'status' => 404 ] );
    }

    if ( (int) $post->post_author !== $user->ID ) {
      return new WP_Error( 'forbidden', 'You can only delete your own recipes.', [ 'status' => 403 ] );
    }

    // Delete featured image first
    $thumb_id = get_post_thumbnail_id( $post_id );
    if ( $thumb_id ) {
      wp_delete_attachment( $thumb_id, true );
    }

    // Delete the recipe
    $deleted = wp_delete_post( $post_id, true );

    if ( ! $deleted ) {
      return new WP_Error( 'delete_failed', 'Failed to delete recipe.', [ 'status' => 500 ] );
    }

    return rest_ensure_response( [ 'message' => 'Recipe deleted successfully.' ] );
  }

  # methods
  private function get_create_recipe_args() {
    return [
        'title' => [
            'required'          => true,
            'type'              => 'string',
            'sanitize_callback' => fn( $v ) => sanitize_text_field( $v ),
        ],
        'description' => [
            'type'              => 'string',
            'sanitize_callback' => fn( $v ) => sanitize_textarea_field( $v ),
        ],
        'total_time' => [
            'type'              => 'integer',
            'minimum'           => 1,
            'sanitize_callback' => fn( $v ) => absint( $v ),
        ],
        'calories' => [
            'type'              => 'integer',
            'minimum'           => 0,
            'sanitize_callback' => fn( $v ) => absint( $v ),
        ],
        'servings' => [
            'type'              => 'integer',
            'minimum'           => 1,
            'sanitize_callback' => fn( $v ) => absint( $v ),
        ],
        'difficulty' => [
            'type'    => 'string',
            'enum'    => [ 'easy', 'medium', 'hard' ],
            'default' => 'easy',
        ],
        'visibility' => [
            'type'    => 'string',
            'enum'    => [ 'public', 'private' ],
            'default' => 'public',
        ],
        'protein' => [
            'type'              => 'number',
            'minimum'           => 0,
            'sanitize_callback' => fn( $v ) => floatval( $v ),
        ],
        'carbs' => [
            'type'              => 'number',
            'minimum'           => 0,
            'sanitize_callback' => fn( $v ) => floatval( $v ),
        ],
        'fat' => [
            'type'              => 'number',
            'minimum'           => 0,
            'sanitize_callback' => fn( $v ) => floatval( $v ),
        ],
        'fiber' => [
            'type'              => 'number',
            'minimum'           => 0,
            'sanitize_callback' => fn( $v ) => floatval( $v ),
        ],
        'ingredients' => [
            'type'              => 'array',
            'items'             => [ 'type' => 'string' ],
            'sanitize_callback' => function ( $value ) {
                return is_array( $value )
                    ? array_filter( array_map( 'sanitize_text_field', $value ) )
                    : [];
            },
        ],
        'instructions' => [
            'type'              => 'array',
            'items'             => [ 'type' => 'string' ],
            'sanitize_callback' => function ( $value ) {
                return is_array( $value )
                    ? array_filter( array_map( 'sanitize_textarea_field', $value ) )
                    : [];
            },
        ],
        'categories' => [
            'type'  => 'array',
            'items' => [ 'type' => [ 'string', 'integer' ] ],
        ],
        'tags' => [
            'type'  => 'array',
            'items' => [ 'type' => [ 'string', 'integer' ] ],
        ],
    ];
  }

  # get categories
  /**
 * Get ALL recipe categories (including empty ones)
 */
public function get_all_categories( WP_REST_Request $request ) {
    $per_page = (int) ( $request['per_page'] ?? 100 );
    $page     = (int) ( $request['page'] ?? 1 );
    $offset   = ( $page - 1 ) * $per_page;

    $args = [
        'taxonomy'   => 'recipe_category',
        'hide_empty' => false,     // ← Show ALL categories
        'number'     => $per_page,
        'offset'     => $offset,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ];

    $categories = get_terms( $args );

    if ( is_wp_error( $categories ) ) {
        return new WP_Error(
            'categories_error',
            'Failed to fetch categories.',
            [ 'status' => 500 ]
        );
    }

    // Prepare response data
    $data = array_map( [ $this, 'prepare_category_for_response' ], $categories );

    $response = rest_ensure_response( $data );

    // Total count (all categories)
    $total = wp_count_terms( [
        'taxonomy'   => 'recipe_category',
        'hide_empty' => false,
    ] );

    $pages = $per_page > 0 ? ceil( $total / $per_page ) : 1;

    $response->header( 'X-WP-Total', (int) $total );
    $response->header( 'X-WP-TotalPages', (int) $pages );

    return $response;
  }

  private function prepare_category_for_response( $term ) {
    return [
        'id'   => $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'count'=> $term->count, // 0 if empty
    ];
  }
}