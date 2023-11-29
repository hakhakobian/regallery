<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Gallery {
  private string $post_type = "reacg";
  private string $ajax_slug = "reacg_save_images";
  private $obj;

  public function __construct($that) {
    $this->obj = $that;
    wp_enqueue_media();
    wp_enqueue_script($this->obj->prefix . '_admin');
    wp_enqueue_style($this->obj->prefix . '_admin');
    REACGLibrary::enqueue_scripts($this->obj);
    $this->register_post_type();
    // Add columns to the custom post list.
    add_filter('manage_' . $this->post_type . '_posts_columns' , [ $this, 'thumbnail_column' ]);
    add_action('manage_' . $this->post_type . '_posts_custom_column', [ $this, 'thumbnail_column_content' ], 10, 2);

    add_action('save_post', [ $this, 'save_post' ], 10, 2);

    // Register an ajax action to save images to the gallery.
    add_action('wp_ajax_' . $this->ajax_slug, [ $this, 'save_images' ]);

    // Register a route to make the gallery images data available with the API.
    add_action( 'rest_api_init', function () {
      register_rest_route( $this->obj->prefix . '/v1', '/gallery/(?P<id>\d+)/images', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => [ $this, 'get_images'],
        'args' => array(
          'id' => array(
            'validate_callback' => function($param, $request, $key) {
              return is_numeric( $param );
            }
          ),
        ),
        'permission_callback' => [$this, 'privileged_permission'],
      ) );
    } );

    // Register a route to make the gallery data available with the API.
    add_action( 'rest_api_init', function () {
      register_rest_route( $this->obj->prefix . '/v1', '/gallery/(?P<id>\d+)', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => [ $this, 'get_gallery'],
        'args' => array(
          'id' => array(
            'validate_callback' => function($param, $request, $key) {
              return is_numeric( $param );
            }
          ),
        ),
        'permission_callback' => [$this, 'privileged_permission'],
      ) );
    } );

    // Register a route to get/set/delete options for the gallery with the API.
    add_action( 'rest_api_init', function () {
      require_once REACG()->plugin_dir . "/includes/options.php";
      $options = new REACG_Options(true);
      register_rest_route( $this->obj->prefix . '/v1', '/options/(?P<gallery_id>\d+)', array(
        'methods' => WP_REST_Server::READABLE . ", " . WP_REST_Server::DELETABLE . ", " . WP_REST_Server::EDITABLE,
        'callback' => [ $options, 'options'],
        'args' => array(
          'gallery_id' => array(
            'validate_callback' => function($param, $request, $key) {
              return is_numeric( $param );
            }
          ),
        ),
        'permission_callback' => [$this, 'privileged_permission'],
      ) );
    } );

    // Register a route to get Google fonts with the API.
    add_action( 'rest_api_init', function () {
      register_rest_route( $this->obj->prefix . '/v1', '/google-fonts', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => [ 'REACGLibrary', 'get_fonts'],
        'permission_callback' => [$this, 'restricted_permission'],
      ) );
    } );
  }

  /**
   * @param $request
   *
   * @return bool
   */
  public function privileged_permission($request) {
    if ( $request->get_method() === 'GET' ) {
      return '__return_true';
    }

    return current_user_can( 'edit_posts' );
  }

  /**
   * @return bool
   */
  public function restricted_permission(): bool {
    return current_user_can( 'edit_posts' );
  }

  /**
   * Add columns to the custom post list.
   *
   * @param $columns
   *
   * @return array
   */
  public function thumbnail_column($columns): array {
    $columns = array_merge(array_slice($columns, 0, 1), array('reacg_thumbnail' => __('Thumbnail', 'reacg')), array_slice($columns, 1));
    $columns = array_merge(array_slice($columns, 0, 3), array('reacg_shortcode' => __('Shortcode', 'reacg'), 'reacg_images_count' => __('Images count', 'reacg')), array_slice($columns, 3));

    return $columns;
  }

  /**
   * Add columns content to the custom post list.
   *
   * @param $column_id
   * @param $post_id
   *
   * @return void
   */
  public function thumbnail_column_content($column_id, $post_id): void {
    $images_ids = get_post_meta( $post_id, 'images_ids', true );
    $images_ids_arr = !empty($images_ids) ? json_decode($images_ids) : [];
    switch ( $column_id ) {
      case 'reacg_thumbnail': {
        if ( !empty($images_ids_arr) ) {
          $image = wp_get_attachment_image($images_ids_arr[0], array(50, 50));
        }
        else {
          $image = __('No image', 'wde');
        }

        echo $image;
        break;
      }
      case 'reacg_images_count': {
        echo count($images_ids_arr);
        break;
      }
      case 'reacg_shortcode': {
        echo "<code>" . REACGLibrary::get_shortcode($this->obj, $post_id) . "</code>";
        break;
      }
    }
  }

  /**
   * Get gallery data.
   *
   * @param WP_REST_Request $request
   *
   * @return WP_REST_Response
   */
  public function get_gallery( WP_REST_Request $request ) {
    $parameters = $request->get_url_params();

    if ( !isset($parameters['id']) ) {
      return new WP_REST_Response( wp_send_json(__( 'Missing gallery ID.', 'reacg' )), 400 );
    }
    $gallery_id = (int) $parameters['id'];

    $images_ids = get_post_meta( $gallery_id, 'images_ids', true );

    $data = [];
    $data['images_count'] = 0;
    if ( !empty($images_ids) ) {
      $images_ids_arr = json_decode($images_ids);

      $data['images_count'] = count($images_ids_arr);
    }

    return new WP_REST_Response( wp_send_json($data), 200 );
  }

  /**
   * Get images data for the specific gallery.
   *
   * @param WP_REST_Request $request
   *
   * @return void
   */
  public function get_images( WP_REST_Request $request ) {
    $parameters = $request->get_url_params();

    if ( !isset($parameters['id']) ) {
      return;
    }
    $gallery_id = (int) $parameters['id'];

    $images_ids = get_post_meta( $gallery_id, 'images_ids', true );

    $data = [];
    if ( !empty($images_ids) ) {
      $images_ids_arr = json_decode($images_ids);

      foreach ( $images_ids_arr as $images_id ) {
        $post = get_post($images_id);
        $meta = wp_get_attachment_metadata($images_id);
        $url = wp_get_attachment_url($images_id);
        $base_name = basename($meta['file']);

        $original = [];
        $original['url'] = $url;
        $original['width'] = !empty($meta['width']) ? $meta['width'] : 0;
        $original['height'] = !empty($meta['height']) ? $meta['height'] : 0;

        $thumbnail = [];
        $thumbnail['url'] = !empty($meta['sizes']['thumbnail']['file']) ? str_replace($base_name, $meta['sizes']['thumbnail']['file'], $url) : '';
        $thumbnail['width'] = !empty($meta['sizes']['thumbnail']['width']) ? $meta['sizes']['thumbnail']['width'] : 0;
        $thumbnail['height'] = !empty($meta['sizes']['thumbnail']['height']) ? $meta['sizes']['thumbnail']['height'] : 0;

        $medium_large = [];
        $medium_large['url'] = !empty($meta['sizes']['medium_large']['file']) ? str_replace($base_name, $meta['sizes']['medium_large']['file'], $url) : '';
        $medium_large['width'] = !empty($meta['sizes']['medium_large']['width']) ? $meta['sizes']['medium_large']['width'] : 0;
        $medium_large['height'] = !empty($meta['sizes']['medium_large']['height']) ? $meta['sizes']['medium_large']['height'] : 0;

        $large = [];
        $large['url'] = !empty($meta['sizes']['large']['file']) ? str_replace($base_name, $meta['sizes']['large']['file'], $url) : '';
        $large['width'] = !empty($meta['sizes']['large']['width']) ? $meta['sizes']['large']['width'] : 0;
        $large['height'] = !empty($meta['sizes']['large']['height']) ? $meta['sizes']['large']['height'] : 0;

        if ( !$medium_large['url'] ) {
            if ( !$large['url'] ) {
              $medium_large = $original;
            }
            else {
              $medium_large = $large;
            }
          }
        if ( !$thumbnail['url'] ) {
          $thumbnail = $medium_large;
        }

        $data[$images_id] = [
          'title' => get_the_title($images_id),
          'caption' => wp_get_attachment_caption($images_id),
          'description' => $post->post_content,

          'original' => $original,
          'thumbnail' => $thumbnail,
          'medium_large' => $medium_large,
        ];
      }

      // Filter the data by title and description.
      $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : '';
      if ( $filter ) {
        $data = array_filter($data, function( $item ) use ( $filter ) {
          if ( stripos($item['title'], $filter) !== FALSE || stripos($item['description'], $filter) !== FALSE ) {
            return TRUE;
          }

          return FALSE;
        });
      }

      // Order the data by title or caption or description.
      $order_by = isset($_GET['order_by']) ? sanitize_text_field($_GET['order_by']) : '';
      if ( in_array($order_by, array('title', 'caption', 'description')) ) {
        if ( isset($_GET['order']) && $_GET['order'] == 'desc' ) {
          // For descending order.
          usort($data, fn( $a, $b ) => strtolower($b[$order_by]) <=> strtolower($a[$order_by]));
        }
        else {
          // For ascending order.
          usort($data, fn( $a, $b ) => strtolower($a[$order_by]) <=> strtolower($b[$order_by]));
        }
      }
      // Run pagination on the data.
      if ( !empty($_GET['per_page']) ) {
        $per_page = (int) $_GET['per_page'];
        // We need one of these two parameters (page or offset, where offset is at which element to start).
        if ( isset($_GET['page']) ) {
          $page = $_GET['page'] > 1 ? (int) $_GET['page'] : 1;
          $offset = ($page - 1) * $per_page;
        }
        else {
          $offset = isset($_GET['offset']) && $_GET['offset'] < count($data) ? (int) $_GET['offset'] : 0;
        }
        $data = array_slice($data, $offset, $per_page);
      }
    }

    return wp_send_json($data);
  }

  /**
   * Save the metadata on an ajax call.
   *
   * @return void
   */
  public function save_images(): void {
    if ( isset($_POST['post_id']) && isset($_POST['images_ids']) ) {
      update_post_meta((int) $_POST['post_id'], 'images_ids', sanitize_text_field($_POST['images_ids']));
    }

    wp_die();
  }

  /**
   * Save the metadata on saving the custom post and insert the shortcode as a content.
   *
   * @param $post_id
   * @param $post
   *
   * @return void
   */
  public function save_post($post_id, $post): void {
    if ( is_null($post) || 'reacg' !== $post->post_type ) {
      return;
    }

    if ( isset($_POST['images_ids']) ) {
      update_post_meta($post_id, 'images_ids', sanitize_text_field($_POST['images_ids']));
    }

    remove_action( 'save_post', [ $this, 'save_post' ] );
    // Save the shortcode as the post content.
    wp_update_post([
                     'ID' => $post_id,
                     'post_content' => REACGLibrary::get_shortcode($this->obj, $post_id),
                   ]);
    add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );
  }

  /**
   * Register custom post.
   *
   * @return void
   */
  private function register_post_type(): void {
    $args = array(
      'label' => __('ReGallery', 'reacg'),
      'labels' => array(
        'add_new' => __('Add New Gallery', 'reacg'),
        'add_new_item' => __('Add New Gallery', 'reacg'),
        'edit_item' => __('Edit Gallery', 'reacg'),
        'new_item' => __('New Gallery', 'reacg'),
        'view_item' => __('View Gallery', 'reacg'),
        'view_items' => __('View Galleries', 'reacg'),
        'search_items' => __('Search Galleries', 'reacg'),
        'not_found' => __('No galleries found', 'reacg'),
        'not_found_in_trash' => __('No galleries found in Trash', 'reacg'),
        'all_items' => __('All Galleries', 'reacg'),
        'filter_items_list' => __('Filter galleries list', 'reacg'),
        'items_list_navigation' => __('Galleries list navigation', 'reacg'),
        'items_list' => __('Galleries list', 'reacg'),
        'archives' => __('Gallery Archives', 'reacg'),
        'attributes' => __('Gallery Attributes', 'reacg'),
        'insert_into_item' => __('Insert into gallery', 'reacg'),
        'uploaded_to_this_item' => __('Uploaded to this gallery', 'reacg'),
        'item_published' => __('Gallery published.', 'reacg'),
        'item_published_privately' => __('Gallery published privately.', 'reacg'),
        'item_reverted_to_draft' => __('Gallery reverted to draft.', 'reacg'),
        'item_trashed' => __('Gallery trashed.', 'reacg'),
        'item_scheduled' => __('Gallery scheduled.', 'reacg'),
        'item_updated' => __('Gallery updated.', 'reacg'),
        'item_link' => __('Gallery Link', 'reacg'),
        'item_link_description' => __('A link to a gallery', 'reacg'),
      ),
      'public' => TRUE,
      'exclude_from_search' => TRUE,
      'publicly_queryable' => TRUE,
      'show_ui' => TRUE,
      'show_in_menu' => TRUE,
      'show_in_nav_menus' => TRUE,
      'permalink_epmask' => TRUE,
      'rewrite' => TRUE,
      'supports' => array('title'),
    );
    register_post_type( 'reacg', $args );
    add_action( 'add_meta_boxes_reacg', [ $this, 'add_meta_boxes' ], 1 );
  }

  /**
   * Add metaboxes to the post type.
   *
   * @param $post
   *
   * @return void
   */
  public function add_meta_boxes($post): void {
    if ( 'reacg' !== $post->post_type ) {
      return;
    }

    // Remove all unnecessary metaboxes from the post type screen.
    $this->remove_all_the_metaboxes();

    // Metabox for adding images.
    add_meta_box( 'gallery-images', __( 'Images', 'reacg' ), [ $this, 'meta_box_images' ], 'reacg', 'normal', 'high' );

    // Metabox for live preview.
    add_meta_box( 'gallery-preview', ' ', [ $this, 'meta_box_preview' ], 'reacg', 'normal', 'high' );
  }

  public function meta_box_preview($post): void {
    REACGLibrary::get_rest_routs($this->obj->rest_root, $post->ID);
  }

  /**
   * Metabox to add and show added images.
   *
   * @param $post
   *
   * @return void
   */
  public function meta_box_images($post): void {
    $images_ids = get_post_meta( $post->ID, 'images_ids', true );
    ?><div class="reacg_items"
         data-post-id="<?php echo esc_attr($post->ID); ?>"
         data-ajax-url="<?php echo esc_url(add_query_arg(array('action' => $this->ajax_slug), admin_url('admin-ajax.php'))); ?>">
      <div class="reacg_item reacg_item_new">
        <div class="reacg_item_image">
          <!--<a id="reacg-add-images">
            <p id="add_album_gallery_text"><?php /*_e('Add images', 'reacg'); */?></p>
          </a>-->
        </div>
      </div><?php
      if ( !empty($images_ids) ) {
        foreach (json_decode($images_ids) as $image_id) {
          $title = get_the_title($image_id);
          $url = wp_get_attachment_thumb_url($image_id);
          $data = [
            "id" => $image_id,
            "title" => $title,
            "url" => $url,
          ];
          $this->image_item($data);
        }
      }
      $this->image_item();
      ?><input id="images_ids" name="images_ids" type="hidden" value="<?php echo esc_attr($images_ids); ?>" />
    </div><?php
  }

  private function image_item($data = FALSE): void {
    $template = FALSE;
    if ( $data === FALSE ) {
      $data = [
        "id" => 0,
        "title" => '',
        "url" => '',
      ];
      $template = TRUE;
    }
    ?><div data-id="<?php echo esc_attr($data['id']); ?>" class="reacg_item <?php echo esc_attr($template ? "reacg-template reacg-hidden" : "reacg-sortable"); ?>">
    <div class="reacg_item_image"
         title="<?php echo esc_attr($data['title']); ?>"
         style="background-image: url('<?php echo urldecode($data['url']); ?>')">
      <div class="reacg-overlay">
        <div class="reacg-hover-buttons">
          <span class="reacg-edit dashicons dashicons-edit" title="<?php _e('Edit', 'reacg'); ?>"></span>
          <span class="reacg-delete dashicons dashicons-trash" title="<?php _e('Remove', 'reacg'); ?>"></span>
        </div>
      </div>
    </div>
    </div><?php
  }

  /**
   * Remove all unnecessary metaboxes from the post type.
   *
   * @return void
   */
  public function remove_all_the_metaboxes(): void {
    global $wp_meta_boxes;

    // This is the post type you want to target. Adjust it to match yours.
    $post_type = $this->post_type;

    // These are the metabox IDs you want to pass over. They don't have to match exactly. preg_match will be run on them.
    $pass_over = [ 'submitdiv' ];

    // All the metabox contexts you want to check.
    $contexts = [ 'normal', 'advanced', 'side' ];

    // All the priorities you want to check.
    $priorities = [ 'high', 'core', 'default', 'low' ];

    // Loop through and target each context.
    foreach ( $contexts as $context ) {
      // Now loop through each priority and start the purging process.
      foreach ( $priorities as $priority ) {
        if ( isset( $wp_meta_boxes[ $post_type ][ $context ][ $priority ] ) ) {
          foreach ( (array) $wp_meta_boxes[ $post_type ][ $context ][ $priority ] as $id => $metabox_data ) {
            // If the metabox ID to pass over matches the ID given, remove it from the array and continue.
            if ( in_array( $id, $pass_over, true ) ) {
              unset( $pass_over[ $id ] );
              continue;
            }

            // Otherwise, loop through the pass_over IDs and if we have a match, continue.
            foreach ( $pass_over as $to_pass ) {
              if ( preg_match( '#^' . $id . '#i', $to_pass ) ) {
                continue;
              }
            }

            // If we reach this point, remove the metabox completely.
            unset( $wp_meta_boxes[ $post_type ][ $context ][ $priority ][ $id ] );
          }
        }
      }
    }

  }
}
