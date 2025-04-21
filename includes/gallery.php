<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Gallery {
  private $post_type = "reacg";
  private $obj;

  public function __construct($that, $register = TRUE) {
    $this->obj = $that;
    if ( !$register ) {
      return;
    }
    $this->register_post_type();
    add_action('admin_menu', [ $this, 'add_submenu' ]);
    add_action('admin_menu', [ $this, 'modify_external_submenu_url'], 999);

    // Add columns to the custom post list.
    add_filter('manage_' . $this->post_type . '_posts_columns' , [ $this, 'custom_columns' ]);
    add_action('manage_' . $this->post_type . '_posts_custom_column', [ $this, 'custom_columns_content' ], 10, 2);
    add_filter('manage_edit-' . $this->post_type . '_sortable_columns', [ $this, 'make_images_count_column_sortable' ]);
    add_action('pre_get_posts', [ $this, 'images_count_column_orderby' ]);

    // Add duplicate link to the list.
    add_filter('post_row_actions', [$this, 'duplicate_link'], 10, 2 );
    add_action('admin_action_reacg_duplicate_gallery', [ $this, 'duplicate_gallery' ]);

    add_action('save_post', [ $this, 'save_post' ], 10, 2);
    add_action('delete_post', [ $this, 'delete_post' ], 10, 2);

    // Register an ajax action to save images to the gallery.
    add_action('wp_ajax_reacg_save_images', [ $this, 'save_images' ]);
    add_action('wp_ajax_reacg_save_thumbnail', [ $this, 'save_thumbnail' ]);
    add_action('wp_ajax_reacg_delete_thumbnail', [ $this, 'delete_thumbnail' ]);

    // Add custom field to the media uploader.
    add_filter('attachment_fields_to_edit', [ $this, 'attachment_field' ], 10, 2);
    // Save custom field when an image is updated.
    add_filter('attachment_fields_to_save', [ $this, 'save_attachment_field' ], 10, 2);

    // Register an ajax action to save a gallery (need for builders).
    add_action('wp_ajax_reacg_save_gallery', [ $this, 'save_gallery' ]);
    // Register an ajax action to get the gallery images (need for builders).
    add_action('wp_ajax_reacg_get_images', [ $this, 'meta_box_images' ]);

    // Register a route to make the gallery images data available with the API.
    add_action( 'rest_api_init', function () {
      register_rest_route( $this->obj->prefix . '/v1', '/gallery/(?P<id>\d+)/images', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => [ $this, 'get_images_rout'],
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
   * Add custom field to the media uploader.
   *
   * @param $form_fields
   * @param $post
   *
   * @return mixed
   */
  public function attachment_field($form_fields, $post) {
    $action_url = get_post_meta($post->ID, 'action_url', true);

    $form_fields['action_url'] = [
      'label' => __('Action URL', 'reacg'),
      'input' => 'text',
      'value' => $action_url,
    ];

    return $form_fields;
  }

  /**
   * Save custom field when an image is updated.
   *
   * @param $post
   * @param $attachment
   *
   * @return mixed
   */
  public function save_attachment_field($post, $attachment) {
    if ( isset($attachment['action_url']) ) {
      update_post_meta($post['ID'], 'action_url', sanitize_url($attachment['action_url']));
    }

    return $post;
  }

  /**
   * Add submenu.
   *
   * @return void
   */
  public function add_submenu() {
    add_submenu_page('edit.php?post_type=reacg', __('About Us', 'reacg'), __('About Us', 'reacg'), 'manage_options', 'reacg-external-link');
  }

  /**
   * Modify external submenu URL.
   *
   * @return void
   */
  public function modify_external_submenu_url() {
    global $submenu;
    $parent_slug = 'edit.php?post_type=reacg';

    if ( isset( $submenu[$parent_slug] ) ) {
      foreach ( $submenu[$parent_slug] as $index => $menu_item ) {
        // Check for the temporary menu slug.
        if ( $menu_item[2] === 'reacg-external-link' ) {
          // Replace with the external URL.
          $submenu[$parent_slug][$index][2] = $this->obj->public_url;
        }
      }
    }
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
   * Add a Duplicate link to the quick actions list.
   *
   * @param $actions
   * @param $post
   *
   * @return mixed
   */
  public function duplicate_link( $actions, $post ) {
    if ( current_user_can( 'edit_posts' ) && $post->post_type === $this->post_type ) {
      $url = wp_nonce_url(
        admin_url( 'admin.php?action=reacg_duplicate_gallery&post=' . $post->ID ),
        -1,
        REACG_NONCE
      );

      $title = sprintf(__('Duplicate “%s”', 'reacg'), empty( $post->post_title ) ? __('(no title)', 'reacg') : $post->post_title);

      $actions['duplicate'] = '<a href="' . esc_url( $url ) . '" title="' . esc_html($title) . '" aria-label="' . esc_html($title) . '">' . esc_html__('Duplicate', 'reacg') . '</a>';
    }

    return $actions;
  }

  /**
   * Duplicate the gallery.
   *
   * @return void
   */
  public function duplicate_gallery() {
    if ( !isset($_GET[REACG_NONCE]) || !wp_verify_nonce($_GET[REACG_NONCE]) ) {
      return;
    }

    if ( !isset($_GET['post']) ) {
      wp_die('No gallery to duplicate has been provided!');
    }

    $post_id = intval($_GET['post']);

    $post = get_post($post_id);
    if ( !empty($post) ) {
      $new_post = array(
        'post_title' => $post->post_title . ' ' . __('(Copy)', 'reacg'),
        'post_content' => $post->post_content,
        'post_status' => 'draft',
        'post_type' => $post->post_type,
        'post_author' => get_current_user_id(),
      );
      $new_post_id = wp_insert_post($new_post);
      if ( $new_post_id && !is_wp_error($new_post_id) ) {
        // Copy taxonomy terms.
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ( $taxonomies as $taxonomy ) {
          $post_terms = wp_get_object_terms($post_id, $taxonomy, array( 'fields' => 'slugs' ));
          wp_set_object_terms($new_post_id, $post_terms, $taxonomy, FALSE);
        }

        // Copy post meta.
        $post_meta = get_post_meta($post_id);
        foreach ( $post_meta as $meta_key => $meta_values ) {
          foreach ( $meta_values as $meta_value ) {
            add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
          }
        }

        // Copy options.
        add_option('reacg_options' . $new_post_id, get_option('reacg_options' . $post_id, FALSE));
      }

      // Redirect to the edit screen.
      wp_redirect(admin_url('edit.php?post_type=' . $this->post_type));
      exit;
    }
    else {
      wp_die('Gallery creation failed, could not find original gallery.');
    }
  }

  /**
   * Get images rout.
   *
   * @param WP_REST_Request $request
   *
   * @return WP_REST_Response
   */
  public function get_images_rout( WP_REST_Request $request ) {
    $data = $this->get_images(REACGLibrary::get_gallery_id($request, 'id'));
    $response = new WP_REST_Response($data['images'], 200);
    $response->header('X-Images-Count', $data['count']);

    return $response;
  }

  /**
   * Get images data for the specific gallery.
   *
   * @param $gallery_id
   * @param $gallery_options
   *
   * @return array
   */
  public function get_images( $gallery_id, $gallery_options = FALSE ) {
    $images_ids = get_post_meta( $gallery_id, 'images_ids', TRUE );

    $order_by = !empty($gallery_options['general']['orderBy']) ? sanitize_text_field($gallery_options['general']['orderBy']) : (isset($_GET['order_by']) ? sanitize_text_field($_GET['order_by']) : '');
    $order = !empty($gallery_options['general']['orderDirection']) ? sanitize_text_field($gallery_options['general']['orderDirection']) : (isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'asc');

    $data = [];
    $all_images_count = 0;
    if ( !empty($images_ids) ) {
      $images_ids_arr = json_decode($images_ids, TRUE);
      $is_deleted_attachment = FALSE;
      $dynamic_exists = FALSE;
      foreach ( $images_ids_arr as $key => $images_id ) {
        $item = $this->get_item_data($images_id);

        // The attachment doesn't exist.
        if ( !$item ) {
          unset($images_ids_arr[$key]);
          $is_deleted_attachment = TRUE;
          continue;
        }

        $item['id'] = $gallery_id . $images_id;

        if ( strpos($images_id, "dynamic") !== FALSE ) {
          $dynamic_exists = TRUE;
          $post_type = str_replace('dynamic', '', $images_id);

          $gallery_data = $this->get_posts( $gallery_id, $post_type, $order_by, $order );
          if ( !empty($gallery_data['posts']) ) {
            $exclude_without_image = !empty($gallery_data['additional_data']['exclude_without_image']);
            foreach ( $gallery_data['posts'] as $post ) {
              if ( $exclude_without_image && empty(get_post_thumbnail_id($post->ID)) ) {
                // When some posts have invalid or orphaned _thumbnail_id values.
                continue;
              }
              $item = $this->get_item_data($post_type . $post->ID);
              $item['id'] = $gallery_id . $images_id . $post->ID;
              $item['caption'] = html_entity_decode($post->post_excerpt);
              $item['action_url'] = esc_url(get_permalink($post->ID));
              $item['type'] = 'image'; // Overwrite type to show post as image in the gallery.
              $item['title'] = html_entity_decode(get_the_title($post->ID));
              $item['description'] = html_entity_decode(wp_trim_words(strip_shortcodes(wp_strip_all_tags($post->post_content)), 50, '...'));
              $item['date'] = $post->post_date;
              $data[] = $item;
            }
          }
        }
        else {
          if ( preg_match('/^(' . implode('|', array_keys(REACG_ALLOWED_POST_TYPES)) . ')(\d+)$/', $images_id, $matches) ) {
            $images_id = $matches[2];
            $post = get_post($images_id);
            $description = !empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;
            $item['caption'] = html_entity_decode(wp_trim_words(strip_shortcodes(wp_strip_all_tags($post->post_excerpt)), 10, '...'));
            $item['action_url'] = esc_url(get_permalink($images_id));
            $item['type'] = 'image'; // Overwrite type to show post as image in the gallery.
          }
          else {
            $post = get_post($images_id);
            $item['caption'] = html_entity_decode(wp_get_attachment_caption($images_id));
            $description = $post->post_content;
            $item['action_url'] = esc_url(get_post_meta($images_id, 'action_url', TRUE));
          }
          $item['title'] = html_entity_decode(get_the_title($images_id));
          $item['description'] = html_entity_decode(wp_trim_words(strip_shortcodes(wp_strip_all_tags($description)), 50, '...'));
          $item['date'] = $post->post_date;
          $data[] = $item;
        }
      }

      $all_images_count = count($data);

      // Update images count if there is selected dynamic posts.
      if ( $dynamic_exists &&
        get_post_meta( $gallery_id, 'images_count', TRUE ) != $all_images_count ) {
        update_post_meta($gallery_id, 'images_count', $all_images_count);
      }

      // Remove attachment ID from the gallery if it doesn't exist anymore.
      if ( $is_deleted_attachment ) {
        update_post_meta($gallery_id, 'images_ids', json_encode(array_values($images_ids_arr)));
      }

      // Filter the data by title and description.
      $filter = !empty($gallery_options['general']['filter']) ? sanitize_text_field($gallery_options['general']['filter']) : (isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : '');
      if ( $filter ) {
        $data = array_filter($data, function( $item ) use ( $filter ) {
          if ( stripos($item['title'], $filter) !== FALSE || stripos($item['description'], $filter) !== FALSE ) {
            return TRUE;
          }

          return FALSE;
        });
      }

      // Order the data.
      if ( in_array($order_by, array('title', 'caption', 'description', 'date')) ) {
          // For ascending order.
          usort($data, function($a, $b) use ($order_by) {
            return strtolower($a[$order_by]) <=> strtolower($b[$order_by]);
          });
      }

      // For descending order.
      if ( $order == 'desc' ) {
        $data = array_reverse($data);
      }

      $per_page = '';
      $views_with_pagination = ['thumbnails', 'mosaic', 'masonry'];
      if ( !empty($gallery_options['general']['itemsPerPage'])
        && in_array($gallery_options['type'], $views_with_pagination)
        && !empty($gallery_options[$gallery_options['type']]['paginationType'])
        && $gallery_options[$gallery_options['type']]['paginationType'] !== 'none' ) {
        $per_page = sanitize_text_field($gallery_options['general']['itemsPerPage']);
      }
      elseif ( !empty($_GET['per_page']) ) {
        $per_page = sanitize_text_field($_GET['per_page']);
      }
      // Run pagination on the data.
      if ( !empty($per_page) ) {
        $per_page = (int) $per_page;
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

    return ['images' => $data, 'count' => $all_images_count];
  }

  /**
   * Get posts.
   *
   * @param $gallery_id
   * @param $post_type
   * @param $order_by
   * @param $order
   *
   * @return array
   */
  private function get_posts($gallery_id, $post_type, $order_by, $order) {
    $args = [
      'post_type' => $post_type,
      'posts_per_page' => -1,
    ];

    // To match gallery order by values to posts order by values.
    $args['orderby'] = str_replace(['default', 'caption', 'description'], ['ID', 'name', 'ID'], $order_by);
    $args['order'] = $order;

    $additional_data = get_post_meta( $gallery_id, 'additional_data', TRUE );
    $additional_data_arr = [];
    if ( !empty($additional_data) ) {
      $additional_data_arr = json_decode($additional_data, TRUE);
      $tax_query = [
        'relation' => !empty($additional_data_arr['relation']) && $additional_data_arr['relation'] == "and" ? 'AND' : 'OR',
      ];
      foreach ( $additional_data_arr['taxonomies'] as $taxonomy_string ) {
        $term = explode(':', $taxonomy_string);
        if ( count($term) === 2 ) {
          $tax_query[] = [
            'taxonomy' => $term[0],
            'field' => 'term_id',
            'terms' => [ (int) $term[1] ],
          ];
        }
      }
      if ( count($tax_query) > 1 ) {
        // Make sure at least one condition exists.
        $args['tax_query'] = $tax_query;
      }
      if ( !empty($additional_data_arr['exclude']) ) {
        $args['post__not_in'] = $additional_data_arr['exclude'];
      }
      if ( !empty($additional_data_arr['exclude_without_image']) ) {
        $args['meta_query'] = [
          'relation' => 'AND',
          [
            'key'     => '_thumbnail_id',
            'value'   => '',
            'compare' => '!=',
          ],
          [
            'key' => '_thumbnail_id',
            'compare' => 'EXISTS',
          ],
        ];
      }
      if ( !empty($additional_data_arr['count']) ) {
        $args['posts_per_page'] = intval($additional_data_arr['count']);
      }
    }

    return ['posts' => get_posts( $args ), 'additional_data' => $additional_data_arr];
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
        $images_ids = sanitize_text_field(wp_unslash($_POST['images_ids']));
        update_post_meta($post_id, 'images_ids', $images_ids);

        if ( isset($_POST['additional_data']) ) {
          $additional_data = sanitize_text_field(wp_unslash($_POST['additional_data']));
          update_post_meta($post_id, 'additional_data', $additional_data);
        }

        /* Update the gallery timestamp on images save to prevent data from being read from the cache.*/
        if ( isset($_POST['gallery_timestamp']) ) {
          $timestamp = sanitize_text_field($_POST['gallery_timestamp']);
          update_post_meta($post_id, 'gallery_timestamp', $timestamp);
        }

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
   * Register an ajax action to save a gallery (need for builders).
   *
   * @return void
   */
  public function save_gallery() {
    if ( isset( $_GET[$this->obj->nonce] )
      && wp_verify_nonce( $_GET[$this->obj->nonce]) ) {
      $new_post = [
        'post_title' => '(no title)',
        'post_status' => 'publish',
        'post_type' => 'reacg',
      ];
      $post_id = wp_insert_post($new_post, TRUE);
      if ( $post_id ) {
        wp_update_post([
                         'ID' => $post_id,
                         'post_content' => REACGLibrary::get_shortcode($this->obj, $post_id),
                       ]);
      }

      echo json_encode($post_id);
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

    if ( isset($_POST['additional_data']) ) {
      $additional_data = sanitize_text_field(wp_unslash($_POST['additional_data']));
      update_post_meta($post_id, 'additional_data', $additional_data);
    }

    if ( isset($_POST['css']) ) {
      $options = get_option('reacg_options' . $post_id, FALSE);
      if ( !empty($options) ) {
        $data = json_decode($options, TRUE);
        $data['custom_css'] = stripslashes(sanitize_text_field($_POST['custom_css']));
        $data['custom_css'] = preg_replace('/\s+/', ' ', $data['custom_css']);
        update_option('reacg_options' . $post_id, json_encode($data));
      }
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
   * Delete the post metas and options on the post delete.
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

    // Delete the post metas.
    delete_post_meta($post_id, 'images_ids');
    delete_post_meta($post_id, 'additional_data');
    delete_post_meta($post_id, 'gallery_timestamp');
    delete_post_meta($post_id, 'options_timestamp');
    delete_post_meta($post_id, 'images_count');

    // Delete all options connected with the gallery.
    require_once REACG()->plugin_dir . "/includes/options.php";
    new REACG_Options(FALSE, $post_id);
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

    // Metabox to display the available publishing methods.
    add_meta_box( 'gallery-help', __( 'Help', 'reacg' ), [ $this, 'meta_box_help' ], 'reacg', 'side', 'default' );

    add_meta_box( 'gallery-custom-css', __('Custom CSS', 'reacg'), [$this, 'meta_box_custom_css'], 'reacg', 'side', 'low' );
  }

  public function meta_box_preview($post) {
    REACGLibrary::get_rest_routs($post->ID);
  }

  /**
   * Display the available publishing methods.
   *
   * @param $post
   *
   * @return void
   */
  public function meta_box_help( $post ) {
    $available_builders = [];
    if ( function_exists( 'register_block_type' ) ) {
      $available_builders['gutenberg'] = [
        'title' => __('Gutenberg block', 'reacg'),
        'video' => 'https://www.youtube.com/watch?v=Ep5L3xKdDH8',
      ];
    }
    if ( class_exists( '\Elementor\Plugin' ) ) {
      $available_builders['elementor'] = [
        'title' => __('Elementor widget', 'reacg'),
        'video' => 'https://www.youtube.com/watch?v=GedxyRxQ02A',
      ];
    }
    if ( class_exists( 'ET_Builder_Module' ) ) {
      $available_builders['divi'] = [
        'title' => __('Divi builder module', 'reacg'),
        'video' => 'https://www.youtube.com/watch?v=Z69eZOoWJi0',
      ];
    }
    if ( !empty($available_builders) ) {
      ?>
    <p>
      <?php esc_html_e( 'You can insert the gallery into a post or page using:', 'reacg' ); ?>
    </p>
    <ul>
      <?php
      foreach ( $available_builders as $builder ) {
        ?>
      <li>
        <a href="<?php echo esc_url($builder['video']); ?>" target="_blank" title="<?php esc_html_e( 'How to', 'reacg' ); ?>">
          <strong><?php esc_html_e($builder['title']); ?></strong>
        </a>
      </li>
        <?php
      }
      ?>
    </ul>
    <?php esc_html_e( 'Or just paste the shortcode into any builder:', 'reacg' ); ?>
      <?php
    }
    else {
      esc_html_e( 'Paste the shortcode into a post or page to show the gallery:', 'reacg' );
    }
    ?>
    <p class="reacg_shortcode">
      <code><?php echo esc_html(REACGLibrary::get_shortcode($this->obj, $post->ID)); ?></code>
    </p>
    <?php
  }

  /**
   * Add meta box for adding Custom CSS.
   *
   * @param $post
   *
   * @return void
   */
  public function meta_box_custom_css($post) {
    $css = "";
    $options = get_option('reacg_options' . $post->ID, FALSE);
    if ( !empty($options) ) {
      $data = json_decode($options, TRUE);
      if ( !empty($data['custom_css']) ) {
        $css = $data['custom_css'];
        $css = preg_replace('/;/', ";\n ", $css); // Add newline after each semicolon
        $css = preg_replace('/{/', " {\n ", $css); // Add newline and indentation after each opening brace
        $css = preg_replace('/ }/', "}\n", $css); // Add newline before and after each closing brace
      }
    }
    ?>
    <textarea name="custom_css" cols="35" rows="20"><?php echo esc_attr($css); ?></textarea>
    <?php
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
   * @return array|array[]|false
   */
  private function get_item_data($id) {
    if ( strpos($id, "dynamic") !== FALSE ) {
      $data = [
        "type" => $id,
        "title" => REACG_ALLOWED_POST_TYPES[$id]['title'],
        'thumbnail' => ['url' => ''],
      ];

      return $data;
    }

    if ( preg_match('/^(' . implode('|', array_keys(REACG_ALLOWED_POST_TYPES)) . ')(\d+)$/', $id, $matches) ) {
      $id = $matches[2];
      if ( 'publish' !== get_post_status( $id ) ) {
        return FALSE;
      }
      $post_thumbnail_id = get_post_thumbnail_id($id);
      $data = $this->get_image_urls($post_thumbnail_id);
      $data['type'] = $matches[1];
      $data['title'] = html_entity_decode(get_the_title($id));

      return $data;
    }
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
    $data['title'] = html_entity_decode(get_the_title($id));

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
    // Verify if the request is an AJAX call and ensure its validity.
    $valid_ajax_call = isset( $_GET[$this->obj->nonce] )
      && wp_verify_nonce( $_GET[$this->obj->nonce])
      && isset($_GET['id'])
      && isset($_GET['action'])
      && $_GET['action'] === 'reacg_get_images';

    // Get the ID depending on whether it is called from builders or the custom post.
    $post_id = isset($_GET['id']) ? (int) $_GET['id'] : $post->ID;
    $images_ids = get_post_meta( $post_id, 'images_ids', true );
    $additional_data = get_post_meta( $post_id, 'additional_data', true );

    if ( $valid_ajax_call ) {
      ob_start();
    }
    ?><div class="reacg_items"
         data-post-id="<?php echo esc_attr($post_id); ?>"
         data-ajax-url="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php'), -1, $this->obj->nonce)); ?>">
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
            "title" => $item['title'],
            "url" => $item['thumbnail']['url'],
          ];
          $this->image_item($data);
        }
      }
      $this->image_item();
      ?><input class="images_ids" id="images_ids" name="images_ids" type="hidden" value="<?php echo esc_attr($images_ids); ?>" />
      <input class="additional_data" id="additional_data" name="additional_data" type="hidden" value="<?php echo esc_attr($additional_data); ?>" />
    </div><?php

    if ( $valid_ajax_call ) {
      echo json_encode(ob_get_clean());
      wp_die();
    }
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

    $edit_btn = ""; // Available for images and dynamic posts.
    $thumbnail_edit_btn = "reacg-hidden"; // Available for videos.
    $cover_icon = "reacg-hidden"; // Available for all types except for images.

    if ( $data['type'] === "video" ) {
      $thumbnail_edit_btn = "";
      $cover_icon = "dashicons dashicons-controls-play";
    }
    elseif ( array_key_exists($data['type'], REACG_ALLOWED_POST_TYPES) ) {
      $cover_icon = "dashicons " . REACG_ALLOWED_POST_TYPES[$data['type']]['class'];
      if ( strpos($data['type'], 'dynamic') === FALSE ) {
        $edit_btn = "reacg-hidden";
      }
    }

    ?><div data-id="<?php echo esc_attr($data['id']); ?>"
           data-type="<?php echo esc_attr($data['type']); ?>"
           class="reacg_item <?php echo esc_attr($template ? "reacg-template reacg-hidden" : "reacg-sortable"); ?>">
    <div class="reacg_item_image"
         title="<?php echo esc_attr($data['title']); ?>"
         style="background-image: url('<?php echo esc_url($data['url']); ?>')">
      <div class="reacg-cover <?php echo esc_attr($cover_icon); ?>">
      </div>
      <div class="reacg-overlay">
        <div class="reacg-hover-buttons">
          <span class="reacg-edit-thumbnail dashicons dashicons-cover-image <?php echo esc_attr($thumbnail_edit_btn); ?>" title="<?php esc_html_e('Edit video cover', 'reacg'); ?>"></span>
          <span class="reacg-edit dashicons dashicons-edit <?php echo esc_attr($edit_btn); ?>" title="<?php esc_html_e('Edit', 'reacg'); ?>"></span>
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
