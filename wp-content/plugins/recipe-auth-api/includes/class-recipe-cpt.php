<?php
/**
 * Recipe Custom Post Type + Meta + REST API
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Recipe_CPT {

  private $post_type = 'recipe';
  private $meta_fields = [];

  public function __construct() {

    # define all the meta fields we need -> inside an array
    $this->define_meta_fields();

    # register custom posts
    add_action( 'init', [ $this, 'register_cpt' ] );
    # register taxonomies
    add_action( 'init', [ $this, 'register_taxonomies' ] );

    # add metaboxes for admin panel -> basically edit metadata
    add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );

    # code for saving metadata, basically: when you submit data from admin metabox
    add_action( 
      hook_name: 'save_post_' . $this->post_type, 
      callback: [ $this, 'save_meta' ], 
      priority: 10, 
      accepted_args: 2 
    );

    # register metafield s to show up on rest api
    add_action( 
      hook_name: 'rest_api_init', 
      callback: [ $this, 'register_rest_fields' ] 
    );
  }


  private function define_meta_fields() {
    # all recipe meta fields
    $this->meta_fields = [
      // Basic Info
      'total_time'   => 'int',     // minutes
      'calories'     => 'int',
      'servings'     => 'int',
      'difficulty'   => 'string',  // easy, medium, hard
      'visibility'   => 'string',  // public, private

      // Nutrition (per serving)
      'protein'      => 'float',
      'carbs'        => 'float',
      'fat'          => 'float',
      'fiber'        => 'float',

      // Content
      'description'  => 'text',
      'ingredients'  => 'array',   // serialized array
      'instructions' => 'array',   // serialized array of steps
    ];
  }

  public function register_cpt() {
    # settings for recipe admin panel table
    $labels = [
      'name'                  => _x( 'Recipes', 'Post type general name', 'recipe-auth' ),
      'singular_name'         => _x( 'Recipe', 'Post type singular name', 'recipe-auth' ),
      'menu_name'             => _x( 'Recipes', 'Admin Menu text', 'recipe-auth' ),
      'name_admin_bar'        => _x( 'Recipe', 'Add New on Toolbar', 'recipe-auth' ),
      'add_new'               => __( 'Add New', 'recipe-auth' ),
      'add_new_item'          => __( 'Add New Recipe', 'recipe-auth' ),
      'new_item'              => __( 'New Recipe', 'recipe-auth' ),
      'edit_item'             => __( 'Edit Recipe', 'recipe-auth' ),
      'view_item'             => __( 'View Recipe', 'recipe-auth' ),
      'all_items'             => __( 'All Recipes', 'recipe-auth' ),
      'search_items'          => __( 'Search Recipes', 'recipe-auth' ),
      'not_found'             => __( 'No recipes found.', 'recipe-auth' ),
    ];

    # some settings for Recipe CPT -> eg: show on rest api
    $args = [
      'labels'             => $labels,
      'public'             => true,
      'publicly_queryable' => true,
      'show_ui'            => true,
      'show_in_menu'       => true,
      'query_var'          => true,
      'rewrite'            => [ 'slug' => 'recipe' ],
      'capability_type'    => 'post',
      'has_archive'        => true,
      'hierarchical'       => false,
      'menu_position'      => 20,
      'menu_icon'          => 'dashicons-food',
      'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author' ],
      'show_in_rest'       => true,
      'rest_base'          => 'recipes',
    ];

      register_post_type( 
        post_type: $this->post_type, 
        args: $args 
      );
  }

  # register taxonomies for CPT recipe ->tags and categories
  public function register_taxonomies() {
    // Category
    register_taxonomy( 
      taxonomy: 'recipe_category', 
      object_type: $this->post_type, 
      args: [
      'label'         => __( 'Categories', 'recipe-auth' ),
      'hierarchical'  => true,
      'show_in_rest'  => true,
      'rest_base'     => 'recipe_categories',
    ]);

    // Tag
    register_taxonomy( 'recipe_tag', $this->post_type, [
      'label'         => __( 'Tags', 'recipe-auth' ),
      'hierarchical'  => false,
      'show_in_rest'  => true,
      'rest_base'     => 'recipe_tags',
    ]);
  }

  # register metaboxes render form functions for admin panel
  public function add_meta_boxes() {
    add_meta_box(
      id: 'recipe_details',
      title: __( 'Recipe Details', 'recipe-auth' ),
      callback: [ $this, 'render_details_metabox' ],
      screen: $this->post_type,
      context: 'normal',
      priority: 'high'
    );

    add_meta_box(
      'recipe_nutrition',
      __( 'Nutrition Facts (per serving)', 'recipe-auth' ),
      [ $this, 'render_nutrition_metabox' ],
      $this->post_type,
      'side'
    );

    add_meta_box(
      'recipe_content',
      __( 'Ingredients & Instructions', 'recipe-auth' ),
      [ $this, 'render_content_metabox' ],
      $this->post_type,
      'normal',
      'high'
    );
  }

  # render form for metadata
  public function render_details_metabox( $post ) {
    wp_nonce_field( 'recipe_meta_nonce', 'recipe_meta_nonce' );
    # get all metadata values
    $values = $this->get_post_meta( $post->ID );

    ?>
    <table class="form-table">
        <tr>
            <th><label for="total_time"><?php _e( 'Total Time (minutes)', 'recipe-auth' ); ?></label></th>
            <td><input type="number" id="total_time" name="total_time" value="<?= esc_attr( $values['total_time'] ?? '' ) ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="calories"><?php _e( 'Calories', 'recipe-auth' ); ?></label></th>
            <td><input type="number" id="calories" name="calories" value="<?= esc_attr( $values['calories'] ?? '' ) ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="servings"><?php _e( 'Servings', 'recipe-auth' ); ?></label></th>
            <td><input type="number" id="servings" name="servings" value="<?= esc_attr( $values['servings'] ?? '' ) ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="difficulty"><?php _e( 'Difficulty', 'recipe-auth' ); ?></label></th>
            <td>
                <select id="difficulty" name="difficulty">
                    <option value="easy" <?= selected( $values['difficulty'] ?? '', 'easy', false ) ?>><?php _e( 'Easy', 'recipe-auth' ); ?></option>
                    <option value="medium" <?= selected( $values['difficulty'] ?? '', 'medium', false ) ?>><?php _e( 'Medium', 'recipe-auth' ); ?></option>
                    <option value="hard" <?= selected( $values['difficulty'] ?? '', 'hard', false ) ?>><?php _e( 'Hard', 'recipe-auth' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="visibility"><?php _e( 'Visibility', 'recipe-auth' ); ?></label></th>
            <td>
                <select id="visibility" name="visibility">
                    <option value="public" <?= selected( $values['visibility'] ?? 'public', 'public', false ) ?>><?php _e( 'Public', 'recipe-auth' ); ?></option>
                    <option value="private" <?= selected( $values['visibility'] ?? '', 'private', false ) ?>><?php _e( 'Private', 'recipe-auth' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="description"><?php _e( 'Short Description', 'recipe-auth' ); ?></label></th>
            <td><textarea id="description" name="description" rows="3" class="large-text"><?= esc_textarea( $values['description'] ?? '' ) ?></textarea></td>
        </tr>
    </table>
    <?php
  }

  # separate form for nutrition
  public function render_nutrition_metabox( $post ) {
    $values = $this->get_post_meta( $post->ID );
    ?>
    <p><strong><?php _e( 'Per serving:', 'recipe-auth' ); ?></strong></p>
    <table class="form-table">
        <tr>
            <th><label for="protein"><?php _e( 'Protein (g)', 'recipe-auth' ); ?></label></th>
            <td><input type="number" step="0.1" id="protein" name="protein" value="<?= esc_attr( $values['protein'] ?? '' ) ?>" /></td>
        </tr>
        <tr>
            <th><label for="carbs"><?php _e( 'Carbs (g)', 'recipe-auth' ); ?></label></th>
            <td><input type="number" step="0.1" id="carbs" name="carbs" value="<?= esc_attr( $values['carbs'] ?? '' ) ?>" /></td>
        </tr>
        <tr>
            <th><label for="fat"><?php _e( 'Fat (g)', 'recipe-auth' ); ?></label></th>
            <td><input type="number" step="0.1" id="fat" name="fat" value="<?= esc_attr( $values['fat'] ?? '' ) ?>" /></td>
        </tr>
        <tr>
            <th><label for="fiber"><?php _e( 'Fiber (g)', 'recipe-auth' ); ?></label></th>
            <td><input type="number" step="0.1" id="fiber" name="fiber" value="<?= esc_attr( $values['fiber'] ?? '' ) ?>" /></td>
        </tr>
    </table>
    <?php
  }

  # render forms for ingredients and instructions
  public function render_content_metabox( $post ) {
    $ingredients = $this->get_post_meta( $post->ID )['ingredients'] ?? [];
    $instructions = $this->get_post_meta( $post->ID )['instructions'] ?? [];
    ?>
    <div style="margin-bottom: 20px;">
        <h4><?php _e( 'Ingredients', 'recipe-auth' ); ?></h4>
        <div id="ingredients-container">
            <?php foreach ( $ingredients as $i => $ing ): ?>
                <p><input type="text" name="ingredients[]" value="<?= esc_attr( $ing ) ?>" class="widefat" placeholder="e.g. 2 cups flour" />
                <button type="button" class="button remove-ingredient">Remove</button></p>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-ingredient" class="button">+ Add Ingredient</button>
    </div>

    <div>
        <h4><?php _e( 'Instructions', 'recipe-auth' ); ?></h4>
        <div id="instructions-container">
            <?php foreach ( $instructions as $i => $step ): ?>
                <p><textarea name="instructions[]" rows="2" class="widefat"><?= esc_textarea( $step ) ?></textarea>
                <button type="button" class="button remove-instruction">Remove</button></p>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-instruction" class="button">+ Add Step</button>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function addField(containerId, template) {
            const container = document.getElementById(containerId);
            const p = document.createElement('p');
            p.innerHTML = template;
            container.appendChild(p);
        }

        document.getElementById('add-ingredient')?.addEventListener('click', () => {
            addField('ingredients-container', '<input type="text" name="ingredients[]" class="widefat" placeholder="e.g. 2 cups flour" /> <button type="button" class="button remove-ingredient">Remove</button>');
        });

        document.getElementById('add-instruction')?.addEventListener('click', () => {
            addField('instructions-container', '<textarea name="instructions[]" rows="2" class="widefat"></textarea> <button type="button" class="button remove-instruction">Remove</button>');
        });

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-ingredient') || e.target.classList.contains('remove-instruction')) {
                e.target.parentElement.remove();
            }
        });
    });
    </script>
    <?php
  }

  # handle save metadata from admin panel
  public function save_meta( $post_id, $post ) {
    if ( ! isset( $_POST['recipe_meta_nonce'] ) || ! wp_verify_nonce( $_POST['recipe_meta_nonce'], 'recipe_meta_nonce' ) ) {
      return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( $post->post_type !== $this->post_type ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $sanitized = [];

    foreach ( $this->meta_fields as $key => $type ) {
        if ( ! isset( $_POST[ $key ] ) && $type !== 'array' ) continue;

        $value = $_POST[ $key ] ?? null;

        switch ( $key ) {
            case 'ingredients':
            case 'instructions':
                $value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [];
                $value = array_filter( $value );
                break;
            case 'total_time':
            case 'calories':
            case 'servings':
                $value = absint( $value );
                break;
            case 'protein':
            case 'carbs':
            case 'fat':
            case 'fiber':
                $value = floatval( $value );
                break;
            case 'difficulty':
            case 'visibility':
                $allowed = $key === 'difficulty' ? ['easy','medium','hard'] : ['public','private'];
                $value = in_array( $value, $allowed ) ? $value : ( $key === 'visibility' ? 'public' : 'easy' );
                break;
            case 'description':
                $value = sanitize_textarea_field( $value );
                break;
        }

        if ( $value !== null && $value !== '' ) {
            update_post_meta( $post_id, $key, $value );
        } else {
            delete_post_meta( $post_id, $key );
        }
    }
  }

  private function get_post_meta( $post_id ) {
    $values = [];
    foreach ( $this->meta_fields as $key => $type ) {
      $value = get_post_meta( $post_id, $key, true );
      if ( $type === 'array' && ! is_array( $value ) ) {
          $value = $value ? maybe_unserialize( $value ) : [];
          $value = is_array( $value ) ? $value : [];
      }
      $values[ $key ] = $value;
    }
    return $values;
  }

  # register metadata fields in rest api
  public function register_rest_fields() {
    foreach ( $this->meta_fields as $key => $type ) {
        register_rest_field( 
          object_type: $this->post_type, 
          attribute: $key, 
          args: [
            'get_callback' => function( $object ) use ( $key ) {
                return get_post_meta( $object['id'], $key, true );
            },
            'update_callback' => function( $value, $object ) use ( $key ) {
                // Only allow update if user owns the recipe
                if ( $object->post_author != get_current_user_id() ) {
                    return new WP_Error( 'rest_forbidden', 'You can only edit your own recipes.', [ 'status' => 403 ] );
                }
                update_post_meta( $object->id, $key, $value );
                return $value;
            },
            'schema' => [
                'description' => ucfirst( str_replace( '_', ' ', $key ) ),
                'type'        => $type === 'array' ? 'array' : ( $type === 'int' ? 'integer' : ( $type === 'float' ? 'number' : 'string' ) ),
            ],
        ]);
    }

    // Also expose author info in REST
    register_rest_field( $this->post_type, 'author_info', [
        'get_callback' => function( $object ) {
            $user = get_user_by( 'id', $object['author'] );
            return $user ? [
                'id' => $user->ID,
                'name' => $user->display_name,
                'avatar' => get_avatar_url( $user->ID ),
            ] : null;
        },
    ]);
  }
}

// Initialize
new Recipe_CPT();