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
    add_action('admin_head', [ $this, 'modify_external_submenu_url']);

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

    add_filter('wp_generate_attachment_metadata', [ $this, 'generate_attachment_metadata' ], 10, 2);
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
      register_rest_route( $this->obj->prefix . '/v1', '/gallery/(?P<id>-?\d+)/images', array(
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

    // Register a route to get custom templates.
    add_action( 'rest_api_init', function () {
      register_rest_route( $this->obj->prefix . '/v1', '/templates', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => [ $this, 'get_custom_templates'],
        'permission_callback' => [$this, 'privileged_permission'],
      ) );
    } );

    // Register a route to get/set/delete options for the gallery with the API.
    add_action( 'rest_api_init', function () {
      require_once REACG()->plugin_dir . "/includes/options.php";
      $options = new REACG_Options(true);
      register_rest_route( $this->obj->prefix . '/v1', '/options/(?P<gallery_id>-?\d+)', array(
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
        'permission_callback' => [$this, 'privileged_permission'],
      ) );
    } );
  }

  public function generate_attachment_metadata($metadata, $attachment_id) {
    $mime = get_post_mime_type($attachment_id);

    if ( strpos($mime, 'image/') === 0 && !empty($metadata['image_meta']) ) {
      $exif = $this->get_exif($metadata['image_meta']);
      if ( !empty($exif) ) {
        update_post_meta($attachment_id, 'exif', sanitize_textarea_field($exif));
      }
    }

    return $metadata;
  }

  /**
   * Build a readable summary of EXIF data.
   *
   * @param $im
   *
   * @return string
   */
  private function get_exif($im) {
    $lines = [];
    if ( !empty($im['camera']) ) {
      $lines[] = 'Camera: ' . $im['camera'];
    }
    if ( !empty($im['lens']) ) {
      $lines[] = 'Lens: ' . $im['lens'];
    }
    if ( !empty($im['focal_length']) ) {
      $lines[] = 'Focal Length: ' . rtrim($im['focal_length'], 'mm') . 'mm';
    }
    if ( !empty($im['aperture']) ) {
      $lines[] = 'Aperture: f/' . $im['aperture'];
    }
    if ( !empty($im['shutter_speed']) ) {
      $lines[] = 'Shutter: ' . $im['shutter_speed'] . 's';
    }
    if ( !empty($im['iso']) ) {
      $lines[] = 'ISO: ' . $im['iso'];
    }
    if ( !empty($im['copyright']) ) {
      $lines[] = 'Copyright: ' . $im['copyright'];
    }

    return implode("\n", $lines);
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
    $form_fields['action_url'] = [
      'label' => __('Action URL', 'reacg'),
      'input' => 'text',
      'value' => get_post_meta($post->ID, 'action_url', TRUE),
    ];

    $exif = get_post_meta($post->ID, 'exif', TRUE);
    if ( empty($exif) ) {
      $metadata = wp_get_attachment_metadata($post->ID);
      if ( !empty($metadata['image_meta']) ) {
        $exif = $this->get_exif($metadata['image_meta']);
      }
    }
    $form_fields['exif'] = [
      'label' => __('Metadata (EXIF)', 'reacg'),
      'input' => 'textarea',
      'value' => $exif,
      'helps' => __('Auto-generated from image metadata on upload. You can edit this text if needed.', 'reacg'),
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
    if (isset($attachment['exif'])) {
      update_post_meta($post['ID'], 'exif', sanitize_textarea_field($attachment['exif']));
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
    add_submenu_page('edit.php?post_type=reacg', REACG_BUY_NOW_TEXT, REACG_BUY_NOW_TEXT, 'manage_options', 'reacg-upgrade');
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
          $submenu[$parent_slug][$index][2] = esc_url(add_query_arg(['utm_medium' => 'submenu', 'utm_campaign' => 'about_us'], REACG_WEBSITE_URL_UTM));
        }
        elseif ( $menu_item[2] === 'reacg-upgrade' ) {
          // Replace with the external URL.
          $submenu[$parent_slug][$index][2] = esc_url(add_query_arg(['utm_medium' => 'submenu', 'utm_campaign' => 'upgrade'], REACG_WEBSITE_URL_UTM . '/#pricing'));
          if ( isset( $submenu[ $parent_slug ][ $index ][4] ) ) {
            $submenu[ $parent_slug ][ $index ][4] .= ' reacg-sidebar-upgrade-pro';
          } else {
            $submenu[ $parent_slug ][ $index ][] = 'reacg-sidebar-upgrade-pro';
          }
          echo '<style>
                  a.reacg-sidebar-upgrade-pro {
                    background-color: rgb(147 177 77) !important;
                  }
                  a.reacg-sidebar-upgrade-pro:focus,
                  a.reacg-sidebar-upgrade-pro:active,
                  a.reacg-sidebar-upgrade-pro:hover {
                    background-color: rgb(135 162 71) !important;
                  }
                  a.reacg-sidebar-upgrade-pro,
                  a.reacg-sidebar-upgrade-pro:focus,
                  a.reacg-sidebar-upgrade-pro:active,
                  a.reacg-sidebar-upgrade-pro:hover {
                    color: #FFFFFF !important;
                    font-weight: 600 !important;
                    border-radius: 3px !important;
                    padding: 3px 10px 5px !important;
                    margin: 5px 12px !important;
                    width: max-content !important;
                    border: none !important;
                    outline: none !important;
                    box-shadow: none !important;
                  }
                  @media only screen and (max-width: 960px) {
                    .auto-fold #adminmenu li.menu-top .wp-submenu>li>a.reacg-sidebar-upgrade-pro {
                      margin-left: 12px !important;
                    }
                    .auto-fold #adminmenu .wp-has-current-submenu li>a.reacg-sidebar-upgrade-pro {
                      margin-left: 14px !important;
                    }
                  }
                 </style>';
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

    // If request comes from Elementor editor iframe.
    if ( isset( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], 'elementor' ) !== FALSE ) {
      return '__return_true';
    }

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

            // The post type is disabled.
            if ( !$item ) {
              // The attachment doesn't exist.
              if ( $item === FALSE ) {
                unset($images_ids_arr[$key]);
                $is_deleted_attachment = TRUE;
              }
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
    $data = [];
    $all_images_count = 0;
    $images_ids_arr = [];

    if ( $gallery_id == -1 ) {
      // To get all galleries images.
      $gallery_ids = REACGLibrary::get_galleries();
      foreach ( $gallery_ids as $galleryId ) {
        $gallery_images_ids = get_post_meta($galleryId, 'images_ids', TRUE);
        if ( !empty($gallery_images_ids) ) {
          $images_ids_arr = array_merge($images_ids_arr, json_decode($gallery_images_ids, TRUE));
        }
      }
    }
    else {
      $images_ids = get_post_meta($gallery_id, 'images_ids', TRUE);
      if ( !empty($images_ids) ) {
        $images_ids_arr = json_decode($images_ids, TRUE);
      }
    }

    if ( !empty($images_ids_arr) ) {
      $order_by = !empty($gallery_options['general']['orderBy']) ? sanitize_text_field($gallery_options['general']['orderBy']) : (isset($_GET['order_by']) ? sanitize_text_field($_GET['order_by']) : '');
      $order = !empty($gallery_options['general']['orderDirection']) ? sanitize_text_field($gallery_options['general']['orderDirection']) : (isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'asc');
      $is_deleted_attachment = FALSE;
      $dynamic_exists = FALSE;
      foreach ( $images_ids_arr as $key => $images_id ) {
        $item = $this->get_item_data($images_id);

        // The attachment doesn't exist or the post type is disabled.
        if ( !$item ) {
          // The attachment doesn't exist.
          if ( $item === FALSE ) {
            unset($images_ids_arr[$key]);
            $is_deleted_attachment = TRUE;
          }
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
              // The post type is disabled.
              if ( !$item ) {
                continue;
              }
              $item['id'] = $gallery_id . $images_id . $post->ID;
              $item += $this->get_item_extra_data($post_type, $post);
              $item['type'] = 'image'; // Overwrite type to show post as image in the gallery.
              $description = !empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;
              $item['description'] = html_entity_decode(wp_trim_words(strip_shortcodes(wp_strip_all_tags($description)), 100, '...'));
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
            $item += $this->get_item_extra_data($matches[1], $post);
            $item['type'] = 'image'; // Overwrite type to show post as image in the gallery.
          }
          else {
            $post = get_post($images_id);
            $description = $post->post_content;
            $item += $this->get_item_extra_data($item['type'], $post);
          }
          $item['description'] = html_entity_decode(wp_trim_words(strip_shortcodes(wp_strip_all_tags($description)), 100, '...'));
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

      // Filter the data by title, description, alt and caption.
      $filter = !empty($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
      if ( $filter) {
        $data = array_values(array_filter($data, function( $item ) use ( $filter ) {
          return stripos($item['title'], $filter) !== FALSE
            || stripos($item['description'], $filter) !== FALSE
            || stripos($item['alt'], $filter) !== FALSE
            || stripos($item['caption'], $filter) !== FALSE;
        }));
      }

      // Images count after filter.
      $all_images_count = count($data);

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
      $views_with_pagination = ['thumbnails', 'mosaic', 'masonry', 'justified'];
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
   * Return the product formatted price based on WooCommerce option.
   *
   * @param $product
   *
   * @return string
   */
  private function get_product_price($product) {
    $decimals = wc_get_price_decimals();
    $price = number_format( $product->get_price(), $decimals, wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
    $currency        = get_woocommerce_currency();
    $currency_symbol = get_woocommerce_currency_symbol( $currency );
    $currency_pos    = get_option( 'woocommerce_currency_pos' );

    // Format price based on position.
    switch ( $currency_pos ) {
      case 'left':
        $formatted_price = $currency_symbol . $price;
        break;
      case 'right':
        $formatted_price = $price . $currency_symbol;
        break;
      case 'left_space':
        $formatted_price = $currency_symbol . ' ' . $price;
        break;
      case 'right_space':
        $formatted_price = $price . ' ' . $currency_symbol;
        break;
      default:
        $formatted_price = $price . ' ' . $currency_symbol;
        break;
    }

    return $formatted_price;
  }

  /**
   * Return the Action URL depends on type.
   *
   * @param $type
   * @param $post
   *
   * @return array
   */
  private function get_item_extra_data($type, $post) {
    $data = [
      'title' => '',
      'caption' => '',
      'author' => '',
      'date_created' => '',
      'exif' => '',
      'action_url' => '',
      'item_url' => '',
      'checkout_url' => '',
      'price' => '',
    ];
    switch ($type) {
      case "image":
      case "video": {
        $data['title'] = html_entity_decode(get_the_title($post->ID));
        $data['caption'] = html_entity_decode(wp_get_attachment_caption($post->ID));
        $data['author'] = html_entity_decode(get_the_author_meta('display_name', get_post_field('post_author', $post->ID)));
        $data['date_created'] = html_entity_decode(get_the_date( '', $post->ID ));
        $data['exif'] = html_entity_decode(get_post_meta($post->ID, 'exif', TRUE));

        if ( $type === 'image' ) {
          $metadata = wp_get_attachment_metadata($post->ID);
          if ( !empty($metadata['image_meta']) ) {
            if ( !empty($metadata['image_meta']['credit']) ) {
              $data['author'] = html_entity_decode($metadata['image_meta']['credit']);
            }
            if ( !empty($metadata['image_meta']['created_timestamp']) ) {
              $data['date_created'] = html_entity_decode(date_i18n(get_option('date_format'), (int) $metadata['image_meta']['created_timestamp']));
            }
            if ( empty($data['exif'])) {
              $data['exif'] = html_entity_decode($this->get_exif($metadata['image_meta']));
            }
          }
        }

        $data['action_url'] = esc_url_raw(get_post_meta($post->ID, 'action_url', TRUE));
        $data['item_url'] = esc_url_raw(get_attachment_link($post->ID));
        break;
      }
      case "post":
      case "page":
      case "product": {
        $data['title'] = html_entity_decode(get_the_title($post->ID));
        $data['caption'] = html_entity_decode(wp_trim_words(strip_shortcodes(wp_strip_all_tags($post->post_excerpt)), 10, '...'));
        $data['author'] = html_entity_decode(get_the_author_meta('display_name', get_post_field('post_author', $post->ID)));
        $data['date_created'] = html_entity_decode(get_the_date( '', $post->ID ));
        $data['action_url'] = esc_url_raw(get_permalink($post->ID));
        $data['item_url'] = $data['action_url'];
        if ( $type === 'product' && $this->obj->woocommerce_is_active ) {
          $product = wc_get_product( $post->ID );
          if ( $product && $product->is_type( 'simple' ) ) {
            $data['checkout_url'] = esc_url_raw(add_query_arg('add-to-cart', $post->ID, wc_get_cart_url()));
            if ( $product->get_price() ) {
              $data['price'] = html_entity_decode($this->get_product_price($product));
            }
          }
        }
        break;
      }
    }

    return $data;
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

    if ( isset($_POST['custom_css']) ) {
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
    add_meta_box( 'gallery-settings', __( 'Settings', 'reacg' ), [ $this, 'meta_box_settings' ], 'reacg', 'normal', 'high' );
    add_meta_box( 'gallery-preview', __( 'Preview', 'reacg' ), [ $this, 'meta_box_preview' ], 'reacg', 'normal', 'low' );

    // Metabox for live preview.

    // Metabox to activate/deactivate pro version.
    add_meta_box( 'gallery-license', __( 'License', 'reacg' ), [ $this, 'meta_box_license' ], 'reacg', 'side', 'low' );

    // Metabox to display the available publishing methods.
    add_meta_box( 'gallery-help', __( 'Help', 'reacg' ), [ $this, 'meta_box_help' ], 'reacg', 'side', 'low' );

    add_meta_box( 'gallery-custom-css', __('Custom CSS', 'reacg') . REACGLibrary::$pro_icon, [$this, 'meta_box_custom_css'], 'reacg', 'side', 'low' );
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
        'title' => __('Gutenberg Block', 'reacg'),
        'video' => 'https://www.youtube.com/watch?v=Ep5L3xKdDH8',
      ];
    }
    if ( class_exists( '\Elementor\Plugin' ) ) {
      $available_builders['elementor'] = [
        'title' => __('Elementor Widget', 'reacg'),
        'video' => 'https://www.youtube.com/watch?v=GedxyRxQ02A',
      ];
    }
    if ( class_exists( 'ET_Builder_Module' ) ) {
      $available_builders['divi'] = [
        'title' => __('Divi Builder Module', 'reacg'),
        'video' => 'https://www.youtube.com/watch?v=Z69eZOoWJi0',
      ];
    }
    if ( function_exists( 'vc_map' ) ) {
      $available_builders['wpbakery'] = [
        'title' => __('WPBakery Builder Element', 'reacg'),
        'video' => 'https://www.youtube.com/watch?v=FClpKpREzPQ',
      ];
    }
    if ( class_exists( 'FLBuilder' ) ) {
      $available_builders['beaverbuilder'] = [
        'title' => __('Beaver Builder Module', 'reacg'),
        'video' => 'https://www.youtube.com/watch?v=A5U2ghLKYNg',
      ];
    }
    if ( class_exists( '\Bricks\Elements' ) ) {
      $available_builders['bricks'] = [
        'title' => __('Bricks Builder Element', 'reacg'),
        'video' => 'https://www.youtube.com/watch?v=aiYdYAn1D_8',
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
        <?php
        if ( !empty($builder['video']) ) {
        ?>
        <a href="<?php echo esc_url($builder['video']); ?>" target="_blank" title="<?php esc_html_e( 'How to', 'reacg' ); ?>">
        <?php
        }
        ?>
          <strong><?php esc_html_e($builder['title']); ?></strong>
          <?php
        if ( !empty($builder['video']) ) {
        ?>
        </a>
        <?php
        }
        ?>
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
    <div class="reacg_help_icon">
      <svg xmlns="http://www.w3.org/2000/svg" fill="#ffffff" viewBox="0 0 24 24">
        <path d="M12,19c-.829,0-1.5-.672-1.5-1.5,0-1.938,1.352-3.709,3.909-5.118,1.905-1.05,2.891-3.131,2.51-5.301-.352-2.003-1.997-3.648-4-4-1.445-.254-2.865,.092-4.001,.974-1.115,.867-1.816,2.164-1.922,3.559-.063,.825-.785,1.445-1.609,1.382-.826-.063-1.445-.783-1.382-1.609,.17-2.237,1.29-4.315,3.073-5.7C8.89,.278,11.149-.275,13.437,.126c3.224,.566,5.871,3.213,6.437,6.437,.597,3.399-1.018,6.794-4.017,8.447-1.476,.813-2.357,1.744-2.357,2.49,0,.828-.671,1.5-1.5,1.5Zm-1.5,3.5c0,.828,.672,1.5,1.5,1.5s1.5-.672,1.5-1.5-.672-1.5-1.5-1.5-1.5,.672-1.5,1.5Z"/>
      </svg>
    </div>
    <?php
  }

  /**
   * Add meta box to activate/deactivate Pro version.
   *
   * @return void
   */
  public function meta_box_license() {
    ?>
    <div class="reacg-pro-not-active" style="display: none;">
      <p>
        <?php echo sprintf(__( "You're using the free version of %s - no license needed. Enjoy!", 'reacg' ), REACG_NICENAME); ?>
        <img draggable="false" role="img" class="emoji" alt="🙂" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f642.svg" />
      </p>
      <p>
        <?php echo sprintf(__( "To unlock more features consider %s.", 'reacg' ), '<strong><a href="https://regallery.team/#pricing?utm_source=plugin&amp;utm_campaign=upgradingtopro" target="_blank" rel="noopener noreferrer">' . __('upgrading to PRO', 'reacg') . '</a></strong>'); ?>
      </p>
      <label for="reacg-license-key">
        <p class="description">
          <?php echo sprintf(__( "Already purchased? Simply enter your license key below to activate %s PRO!", 'reacg' ), REACG_NICENAME); ?>
        </p>
      </label>
      <input placeholder="<?php esc_html_e( "Paste license key here", 'reacg' ); ?>" type="text" id="reacg-license-key" class="reacg-license-key"  />
      <p class="reacg-error description hidden"></p>
      <div class="reacg-activate-action">
        <span class="spinner"></span>
        <button type="button" class="button button-primary button-large reacg-primary-button reacg-license-activate-button" data-activate="true">
          <?php esc_html_e( "Activate Pro version", 'reacg' ); ?>
        </button>
      </div>
    </div>
    <div class="reacg-pro-active" style="display: none;">
      <p class="reacg-success">
        <?php echo sprintf(__( "%s PRO version is active!", 'reacg' ), REACG_NICENAME); ?>
      </p>
      <div class="reacg-activate-action">
        <span class="spinner"></span>
        <button type="button" class="button button-secondary button-large reacg-license-activate-button" data-activate="false">
          <?php esc_html_e( "Deactivate Pro version", 'reacg' ); ?>
        </button>
      </div>
    </div>
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
    <textarea name="custom_css" rows="20"><?php echo esc_attr($css); ?></textarea>
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
      if ( !array_key_exists($id, REACG_ALLOWED_POST_TYPES) ) {
        // If is post (is not image or video), but the post type is disabled (e.g. deactivated WooCommerce plugin after adding products).
        return "";
      }
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
      // Get the post featured image alt as image alt.
      $data['alt'] = html_entity_decode(get_post_meta($post_thumbnail_id, '_wp_attachment_image_alt', TRUE));

      return $data;
    }
    elseif ( !is_int($id) ) {
      // If is post (is not image or video), but the post type is disabled (e.g. deactivated WooCommerce plugin after adding products).
      return "";
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
      $data['title'] = html_entity_decode(get_the_title($id));
      // Get the video cover image alt as video alt.
      $data['alt'] = html_entity_decode(get_post_meta($thumbnail_id, '_wp_attachment_image_alt', TRUE));
    }
    else {
      $data = $this->get_image_urls($id);

      // If attachment doesn't exist.
      if ( !$data ) {
        return FALSE;
      }

      $data['type'] = "image";
      $data['alt'] = html_entity_decode(get_post_meta($id, '_wp_attachment_image_alt', TRUE));
      $data['title'] = html_entity_decode(get_the_title($id));
    }

    return $data;
  }

  /**
   * Metabox to show settings separately.
   *
   * @return void
   */
  public function meta_box_settings() {
    ?><div class="reacg-wrapper" id="reacg_settings"></div>
    <?php
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

    // Calculate edits count.
    $edit_count = get_post_meta($post_id, 'edit_count', true);
    $edit_count = $edit_count ? (int) $edit_count + 1 : 1;
    update_post_meta($post_id, 'edit_count', $edit_count);

    if ( $valid_ajax_call ) {
      ob_start();
    }
    ?><div class="reacg_items"
         data-post-id="<?php echo esc_attr($post_id); ?>"
         data-edit-count="<?php echo esc_attr($edit_count); ?>"
         data-ajax-url="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php'), -1, $this->obj->nonce)); ?>">
      <div class="reacg_item reacg_item_new">
        <div class="reacg_item_image"></div>
      </div><?php
      if ( !empty($images_ids) ) {
        $images_ids_arr = json_decode($images_ids, true);
        foreach ($images_ids_arr as $image_id) {
          $item = $this->get_item_data($image_id);

          // The attachment doesn't exist or the post type is disabled.
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

  public function get_custom_templates(WP_REST_Request $request = null) {
    if ( !is_null($request) ) {
      $posts = get_posts(array(
                           'posts_per_page' => -1,
                           'post_type' => 'reacg',
                           'post_status' => 'publish',
                         ));
      $data = [];
      foreach ( $posts as $key => $post ) {
        $data[$key] = [];
        $data[$key]['id'] = $post->ID;
        $data[$key]['title'] = $post->post_title ? $post->post_title : __('(no title)', 'reacg');
      }
      if (!empty($data)) {
        $data[] = [
          'id'    => -1,
          'title' => __('All Images Template', 'reacg'),
        ];
      }

      return new WP_REST_Response( wp_send_json($data, 200), 200 );
    }

    return wp_send_json(new WP_Error( 'wrong_template', __( 'There is no such a template.', 'reacg' ), array( 'status' => 400 ) ), 400);
  }
}
