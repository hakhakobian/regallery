<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Gallery {
  private $post_type = "reacg";
  private $obj;

  public function __construct($that) {
    $this->obj = $that;
    $this->register_post_type();
    // Add columns to the custom post list.
    add_filter('manage_' . $this->post_type . '_posts_columns' , [ $this, 'custom_columns' ]);
    add_action('manage_' . $this->post_type . '_posts_custom_column', [ $this, 'custom_columns_content' ], 10, 2);
    add_filter('manage_edit-' . $this->post_type . '_sortable_columns', [ $this, 'make_images_count_column_sortable' ]);
    add_action('pre_get_posts', [ $this, 'images_count_column_orderby' ]);

    add_action('save_post', [ $this, 'save_post' ], 10, 2);
    add_action('delete_post', [ $this, 'delete_post' ], 10, 2);

    // Register an ajax action to save images to the gallery.
    add_action('wp_ajax_reacg_save_images', [ $this, 'save_images' ]);
    add_action('wp_ajax_reacg_save_thumbnail', [ $this, 'save_thumbnail' ]);
    add_action('wp_ajax_reacg_delete_thumbnail', [ $this, 'delete_thumbnail' ]);

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
  public function restricted_permission() {
    return current_user_can( 'edit_posts' );
  }

  /**
   * Add columns to the custom post list.
   *
   * @param $columns
   *
   * @return array
   */
  public function custom_columns($columns) {
    wp_enqueue_style($this->obj->prefix . '_admin');

    $columns = array_merge(array_slice($columns, 0, 1), array('reacg_thumbnail' => __('Thumbnail', 'reacg')), array_slice($columns, 1));
    $columns = array_merge(array_slice($columns, 0, 3), array('reacg_shortcode' => __('Shortcode', 'reacg'), 'reacg_images_count' => __('Images count', 'reacg')), array_slice($columns, 3));

    return $columns;
  }

  /**
   * Maker the Images count columns sortable.
   *
   * @param $columns
   *
   * @return mixed
   */
  public function make_images_count_column_sortable($columns) {
    $columns['reacg_images_count'] = 'reacg_images_count';
    return $columns;
  }

  /**
   * Images count column ordering.
   *
   * @param $query
   *
   * @return void
   */
  public function images_count_column_orderby($query) {
    if ( !is_admin() ) {
      return;
    }

    if ( 'reacg_images_count' === $query->get('orderby') ) {
      $query->set('meta_key', 'images_count');
      $query->set('orderby', 'meta_value_num');
    }
  }

  /**
   * Add columns content to the custom post list.
   *
   * @param $column_id
   * @param $post_id
   *
   * @return void
   */
  public function custom_columns_content($column_id, $post_id) {
    switch ( $column_id ) {
      case 'reacg_thumbnail': {
        $images_ids = get_post_meta( $post_id, 'images_ids', TRUE );
        $images_ids_arr = !empty($images_ids) ? json_decode($images_ids, TRUE) : [];
        if ( !empty($images_ids_arr) ) {
          $url = "";
          $is_deleted_attachment = FALSE;
          foreach ( $images_ids_arr as $key => $images_id ) {
            $item = $this->get_item_data($images_id);

            if ( !$item ) {
              // The attachment doesn't exist.
              unset($images_ids_arr[$key]);
              $is_deleted_attachment = TRUE;
            }
            elseif ( $url === "" ) {
              // Get first existing thumbnail.
              $url = $item['thumbnail']['url'];
            }
          }

          if ( $url === "" ) {
            $url = $this->obj->plugin_url . $this->obj->no_image;
          }

          // Remove attachment ID from the gallery if it doesn't exist anymore.
          if ( $is_deleted_attachment ) {
            update_post_meta($post_id, 'images_ids', json_encode(array_values($images_ids_arr)));
          }

          ?><div style='background-image: url("<?php echo esc_url($url); ?>")'></div><?php
        }
        else {
          esc_html_e('No image', 'reacg');
        }
        break;
      }
      case 'reacg_images_count': {
        $images_count = get_post_meta( $post_id, 'images_count', TRUE );
        // Count the images ids in case of empty meta.
        if ( empty($images_count) ) {
          $images_ids = get_post_meta( $post_id, 'images_ids', TRUE );
          $images_ids_arr = !empty($images_ids) ? json_decode($images_ids, TRUE) : [];
          $images_count = count($images_ids_arr);
          update_post_meta($post_id, 'images_count', $images_count);
        }
        echo esc_html($images_count);
        break;
      }
      case 'reacg_shortcode': {
        ?><code><?php echo esc_html(REACGLibrary::get_shortcode($this->obj, $post_id)); ?></code><?php
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
    $gallery_id = REACGLibrary::get_gallery_id($request, 'id');

    $images_count = get_post_meta( $gallery_id, 'images_count', TRUE );

    $data = [];
    $data['images_count'] = 0;

    if ( !empty($images_count) ) {
      $data['images_count'] = (int) $images_count;
    }
    else {
      // Count the images ids in case of empty meta.
      $images_ids = get_post_meta( $gallery_id, 'images_ids', TRUE );
      if ( !empty($images_ids) ) {
        $images_ids_arr = json_decode($images_ids, TRUE);
        $data['images_count'] = count($images_ids_arr);
      }
    }

    return new WP_REST_Response( wp_send_json($data, 200), 200 );
  }

  /**
   * Get images data for the specific gallery.
   *
   * @param WP_REST_Request $request
   *
   * @return void
   */
  public function get_images( WP_REST_Request $request ) {
    $gallery_id = REACGLibrary::get_gallery_id($request, 'id');

    $images_ids = get_post_meta( $gallery_id, 'images_ids', TRUE );

    $data = [];
    if ( !empty($images_ids) ) {
      $images_ids_arr = json_decode($images_ids, TRUE);
      $is_deleted_attachment = FALSE;
      foreach ( $images_ids_arr as $key => $images_id ) {
        $item = $this->get_item_data($images_id);

        // The attachment doesn't exist.
        if ( !$item ) {
          unset($images_ids_arr[$key]);
          $is_deleted_attachment = TRUE;
          continue;
        }

        $post = get_post($images_id);
        $item['id'] = $gallery_id . $images_id;
        $item['title'] = html_entity_decode(get_the_title($images_id));
        $item['caption'] = html_entity_decode(wp_get_attachment_caption($images_id));
        $item['description'] = html_entity_decode($post->post_content);
        $item['date'] = $post->post_date;
        $data[] = $item;
      }

      // Remove attachment ID from the gallery if it doesn't exist anymore.
      if ( $is_deleted_attachment ) {
        update_post_meta($gallery_id, 'images_ids', json_encode(array_values($images_ids_arr)));
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
      if ( in_array($order_by, array('title', 'caption', 'description', 'date')) ) {
          // For ascending order.
          usort($data, function($a, $b) use ($order_by) {
            return strtolower($a[$order_by]) <=> strtolower($b[$order_by]);
          });
      }

      // For descending order.
      if ( isset($_GET['order']) && $_GET['order'] == 'desc' ) {
        $data = array_reverse($data);
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

    return new WP_REST_Response( wp_send_json($data, 200), 200 );
  }

  /**
   * Save images.
   *
   * @return void
   */
  public function save_images() {
    if ( isset( $_GET[$this->obj->nonce] )
      && wp_verify_nonce( $_GET[$this->obj->nonce]) ) {
      if ( isset($_POST['post_id']) && isset($_POST['images_ids']) ) {
        $post_id = (int) $_POST['post_id'];
        $images_ids = sanitize_text_field($_POST['images_ids']);
        update_post_meta($post_id, 'images_ids', $images_ids);
        $images_ids_arr = !empty($images_ids) ? json_decode($images_ids, TRUE) : [];
        $images_count = count($images_ids_arr);
        update_post_meta($post_id, 'images_count', $images_count);
      }
    }

    wp_die();
  }

  /**
   * Save thumbnail.
   *
   * @return void
   */
  public function save_thumbnail() {
    if ( isset( $_GET[$this->obj->nonce] )
      && wp_verify_nonce( $_GET[$this->obj->nonce]) ) {
      if ( isset($_POST['id']) && isset($_POST['thumbnail_id']) ) {
        $id = (int) $_POST['id'];
        $thumbnail_id = (int) $_POST['thumbnail_id'];
        $metadata = wp_get_attachment_metadata($id);
        $metadata['thumbnail_id'] = $thumbnail_id;
        wp_update_attachment_metadata( $id, $metadata );
      }
    }

    wp_die();
  }

  /**
   * Delete thumbnail.
   *
   * @return void
   */
  public function delete_thumbnail() {
    if ( isset( $_GET[$this->obj->nonce] )
      && wp_verify_nonce( $_GET[$this->obj->nonce]) ) {
      if ( isset($_POST['id']) ) {
        $id = (int) $_POST['id'];
        $metadata = wp_get_attachment_metadata($id);
        unset($metadata['thumbnail_id']);
        wp_update_attachment_metadata( $id, $metadata );
      }
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
  public function save_post($post_id, $post) {
    if ( is_null($post) || 'reacg' !== $post->post_type ) {
      return;
    }

    if ( isset($_POST['images_ids']) ) {
      $images_ids = sanitize_text_field($_POST['images_ids']);
      update_post_meta($post_id, 'images_ids', $images_ids);

      // Save images count as a separate meta to make it possible to order galleries by images count.
      $images_ids_arr = json_decode($images_ids, TRUE);
      $images_count = is_array($images_ids_arr) ? count($images_ids_arr) : 0;
      update_post_meta($post_id, 'images_count', $images_count);
    }

    remove_action( 'save_post', [ $this, 'save_post' ] );
    // Save the shortcode as the post content.
    wp_update_post([
                     'ID' => $post_id,
                     'post_title' => sanitize_text_field($post->post_title),
                     'post_content' => REACGLibrary::get_shortcode($this->obj, $post_id),
                   ]);
    add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );
  }

  /**
   * Delete the post metas on the post delete.
   *
   * @param $post_id
   * @param $post
   *
   * @return void
   */
  public function delete_post($post_id, $post) {
    if ( is_null($post) || 'reacg' !== $post->post_type ) {
      return;
    }

    delete_post_meta($post_id, 'images_ids');
    delete_post_meta($post_id, 'images_count');
  }

  /**
   * Register custom post.
   *
   * @return void
   */
  private function register_post_type() {
    $args = array(
      'label' => $this->obj->nicename,
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
      'menu_icon' => $this->obj->plugin_url . '/assets/images/logo.png',
      'exclude_from_search' => TRUE,
      'publicly_queryable' => TRUE,
      'show_ui' => TRUE,
      'show_in_menu' => TRUE,
      'show_in_nav_menus' => TRUE,
      'permalink_epmask' => TRUE,
      'rewrite' => TRUE,
      // Editor is not used, but added to avoid a bug connected with preview. It is hidden with CSS (admin.css)
      'supports' => array('title', 'editor'),
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
  public function add_meta_boxes($post) {
    if ( 'reacg' !== $post->post_type ) {
      return;
    }

    wp_enqueue_media();
    wp_enqueue_script($this->obj->prefix . '_admin');
    wp_enqueue_style($this->obj->prefix . '_admin');

    // Remove all unnecessary metaboxes from the post type screen.
    $this->remove_all_the_metaboxes();

    // Metabox for adding images.
    add_meta_box( 'gallery-images', __( 'Images', 'reacg' ), [ $this, 'meta_box_images' ], 'reacg', 'normal', 'high' );

    // Metabox for live preview.
    add_meta_box( 'gallery-preview', ' ', [ $this, 'meta_box_preview' ], 'reacg', 'normal', 'high' );
  }

  public function meta_box_preview($post) {
    REACGLibrary::get_rest_routs($post->ID);
  }

  /**
   * Return the given image all size URLs.
   *
   * @param $id
   *
   * @return
   */
  private function get_image_urls($id) {
    if ( $id === 0 ) {
      $no_image = [
        'url' => $this->obj->plugin_url . $this->obj->no_image,
        'width' => 700,
        'height' => 700,
      ];
      return [
        'original' => $no_image,
        'thumbnail' => $no_image,
        'medium_large' => $no_image,
        'large' => $no_image,
      ];
    }

    $url = wp_get_attachment_url($id);
    $meta = wp_get_attachment_metadata($id);

    if ( !$url || !$meta ) {
      return FALSE;
    }

    $base_name = isset($meta['file']) ? basename($meta['file']) : "";

    $thumbnail = [];
    $thumbnail['url'] = !empty($meta['sizes']['medium']['file']) ? str_replace($base_name, urlencode($meta['sizes']['medium']['file']), $url) : '';
    $thumbnail['width'] = !empty($meta['sizes']['medium']['width']) ? $meta['sizes']['medium']['width'] : 0;
    $thumbnail['height'] = !empty($meta['sizes']['medium']['height']) ? $meta['sizes']['medium']['height'] : 0;

    $medium_large = [];
    $medium_large['url'] = !empty($meta['sizes']['medium_large']['file']) ? str_replace($base_name, urlencode($meta['sizes']['medium_large']['file']), $url) : '';
    $medium_large['width'] = !empty($meta['sizes']['medium_large']['width']) ? $meta['sizes']['medium_large']['width'] : 0;
    $medium_large['height'] = !empty($meta['sizes']['medium_large']['height']) ? $meta['sizes']['medium_large']['height'] : 0;

    $large = [];
    $large['url'] = !empty($meta['sizes']['large']['file']) ? str_replace($base_name, urlencode($meta['sizes']['large']['file']), $url) : '';
    $large['width'] = !empty($meta['sizes']['large']['width']) ? $meta['sizes']['large']['width'] : 0;
    $large['height'] = !empty($meta['sizes']['large']['height']) ? $meta['sizes']['large']['height'] : 0;

    $original = [];
    $basename = basename($url);
    $original['url'] = str_replace($basename, urlencode($basename), $url);
    $original['width'] = !empty($meta['width']) ? $meta['width'] : 0;
    $original['height'] = !empty($meta['height']) ? $meta['height'] : 0;

    if ( !$large['url'] ) {
      $large = $original;
    }
    if ( !$medium_large['url'] ) {
      $medium_large = $large;
    }
    if ( !$thumbnail['url'] ) {
      $thumbnail = $medium_large;
    }

    return [
      'original' => $original,
      'thumbnail' => $thumbnail,
      'medium_large' => $medium_large,
      'large' => $large,
    ];
  }

  /**
   * Return the given item data.
   *
   * @param $id
   *
   * @return
   */
  private function get_item_data($id) {
    $meta = wp_get_attachment_metadata($id);

    if ( isset($meta['mime_type']) && strpos($meta['mime_type'], "video") !== -1 ) {
      $url = wp_get_attachment_url($id);

      // If the attachment doesn't exist.
      if ( !$url ) {
        return FALSE;
      }

      // Get Video URL as an original URL and selected image URL as a thumbnail URL.
      $thumbnail_id = isset($meta['thumbnail_id']) ? $meta['thumbnail_id'] : 0;
      $data = $this->get_image_urls($thumbnail_id);
      if ( !$data ) {
        // If the attachment which is selected as a thumbnail doesn't exist, get no image as a thumbnail.
        $data = $this->get_image_urls(0);
      }

      $basename = basename($url);
      $data['original']['url'] = str_replace($basename, urlencode($basename), $url);
      $data['original']['width'] = !empty($meta['width']) ? $meta['width'] : 0;
      $data['original']['height'] = !empty($meta['height']) ? $meta['height'] : 0;
      $data['type'] = "video";
    }
    else {
      $data = $this->get_image_urls($id);
      // If the attachment exists.
      if ( $data ) {
        $data['type'] = "image";
      }
    }

    return $data;
  }

  /**
   * Metabox to add and show added images.
   *
   * @param $post
   *
   * @return void
   */
  public function meta_box_images($post) {
    $images_ids = get_post_meta( $post->ID, 'images_ids', true );
    $ajax_url = admin_url('admin-ajax.php');
    $ajax_url = wp_nonce_url($ajax_url, -1, $this->obj->nonce);
    ?><div class="reacg_items"
         data-post-id="<?php echo esc_attr($post->ID); ?>"
         data-ajax-url="<?php echo esc_url($ajax_url); ?>">
      <div class="reacg_item reacg_item_new">
        <div class="reacg_item_image"></div>
      </div><?php
      if ( !empty($images_ids) ) {
        $images_ids_arr = json_decode($images_ids, true);
        foreach ($images_ids_arr as $image_id) {
          $item = $this->get_item_data($image_id);

          // The attachment doesn't exist.
          if ( !$item ) {
            continue;
          }

          $data = [
            "id" => $image_id,
            "type" => $item['type'],
            "title" => get_the_title($image_id),
            "url" => $item['thumbnail']['url'],
          ];
          $this->image_item($data);
        }
      }
      $this->image_item();
      ?><input id="images_ids" name="images_ids" type="hidden" value="<?php echo esc_attr($images_ids); ?>" />
    </div><?php
  }

  private function image_item($data = FALSE) {
    $template = FALSE;
    if ( $data === FALSE ) {
      $data = [
        "id" => 0,
        "title" => '',
        "type" => 'image',
        "url" => '',
      ];
      $template = TRUE;
    }
    ?><div data-id="<?php echo esc_attr($data['id']); ?>"
           data-type="<?php echo esc_attr($data['type']); ?>"
           class="reacg_item <?php echo esc_attr($template ? "reacg-template reacg-hidden" : "reacg-sortable"); ?>">
    <div class="reacg_item_image"
         title="<?php echo esc_attr($data['title']); ?>"
         style="background-image: url('<?php echo esc_url($data['url']); ?>')">
      <div class="reacg-cover <?php echo esc_attr($data['type'] === "image" ? "reacg-hidden" : ""); ?>">
      </div>
      <div class="reacg-overlay">
        <div class="reacg-hover-buttons">
          <span class="reacg-edit-thumbnail dashicons dashicons-cover-image <?php echo esc_attr($data['type'] === "image" ? "reacg-hidden" : ""); ?>" title="<?php esc_html_e('Edit video cover', 'reacg'); ?>"></span>
          <span class="reacg-edit dashicons dashicons-edit" title="<?php esc_html_e('Edit', 'reacg'); ?>"></span>
          <span class="reacg-delete dashicons dashicons-trash" title="<?php esc_html_e('Remove', 'reacg'); ?>"></span>
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
  public function remove_all_the_metaboxes() {
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
