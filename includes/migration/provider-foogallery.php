<?php

defined('ABSPATH') || die('Access Denied');

class REACG_Migration_Provider_FooGallery implements REACG_Migration_Provider_Interface {
  public function get_key() {
    return 'foo';
  }

  public function get_label() {
    return 'FooGallery';
  }

  public function is_available() {
    return post_type_exists('foogallery');
  }

  public function list_galleries($args = []) {
    $page = !empty($args['page']) ? max(1, intval($args['page'])) : 1;
    $per_page = !empty($args['per_page']) ? max(1, intval($args['per_page'])) : 50;
    $search = !empty($args['search']) ? sanitize_text_field($args['search']) : '';

    $query = new WP_Query([
      'post_type' => 'foogallery',
      'post_status' => ['publish', 'draft', 'private'],
      'posts_per_page' => $per_page,
      'paged' => $page,
      's' => $search,
      'fields' => 'ids',
      'no_found_rows' => false,
    ]);

    $items = [];
    foreach ($query->posts as $post_id) {
      $attachment_ids = $this->extract_attachment_ids($post_id);
      $items[] = [
        'id' => (string) $post_id,
        'title' => get_the_title($post_id) ?: __('(no title)', 'regallery'),
        'image_count' => count($attachment_ids),
      ];
    }

    return [
      'items' => $items,
      'total' => intval($query->found_posts),
      'page' => $page,
      'per_page' => $per_page,
    ];
  }

  public function get_gallery($external_id) {
    $post_id = intval($external_id);
    if (!$post_id || get_post_type($post_id) !== 'foogallery') {
      return new WP_Error('reacg_migration_invalid_gallery', __('Invalid FooGallery gallery.', 'regallery'));
    }

    $attachment_ids = $this->extract_attachment_ids($post_id);
    $template = $this->get_template($post_id);
    $raw_settings = $this->get_settings($post_id);
    $items = [];
    foreach ($attachment_ids as $attachment_id) {
      $items[] = [
        'attachment_id' => intval($attachment_id),
      ];
    }

    return [
      'id' => (string) $post_id,
      'title' => get_the_title($post_id) ?: __('Imported FooGallery', 'regallery'),
      'items' => $items,
      'settings' => $this->map_settings($template, $raw_settings),
    ];
  }

  public function get_shortcode_patterns($source_gallery_id) {
    $id = preg_quote((string) $source_gallery_id, '/');

    return [
      '/\\[foogallery\\b[^\\]]*\\bid\\s*=\\s*(?:"|\')?' . $id . '(?:"|\')?[^\\]]*\\]/i',
    ];
  }

  public function get_block_namespaces() {
    return ['foogallery', 'foo-gallery', 'fooplugins'];
  }

  public function get_block_id_attributes() {
    return ['id'];
  }

  public function prefer_shortcode_replacement() {
    return false;
  }

  public function replace_specific_gallery($source_gallery_id, $migrated_gallery_id, $replacement_shortcode, $replacement_block) {
    return null;
  }

  public function replace_post_meta_references($post_id, $source_gallery_id, $migrated_gallery_id) {
    $raw = get_post_meta($post_id, '_elementor_data', true);
    if (empty($raw) || !is_string($raw)) {
      return 0;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
      return 0;
    }

    $count = 0;
    $data = $this->walk_elementor_tree(
      $data,
      intval($source_gallery_id),
      intval($migrated_gallery_id),
      $count
    );

    if ($count === 0) {
      return 0;
    }

    $encoded = wp_json_encode($data);
    if ($encoded === false) {
      return 0;
    }

    update_post_meta($post_id, '_elementor_data', wp_slash($encoded));

    if (class_exists( '\Elementor\Plugin' )) {
      $document = \Elementor\Plugin::instance()->documents->get($post_id, false);
      if ($document) {
        $document->delete_meta('_elementor_css');
      }
      delete_post_meta($post_id, '_elementor_css');
    }

    return $count;
  }

  public function source_exists_in_posts($source_gallery_id) {
    $source_gallery_id = trim((string) $source_gallery_id);
    $id_numeric = intval($source_gallery_id);

    if ($this->elementor_foo_widget_exists($id_numeric)) {
      return true;
    }

    return $this->content_reference_exists(
      ['%[foogallery%', '%wp:fooplugins%', '%wp:foogallery%'],
      [
        '/\\[foogallery\\b[^\\]]*\\bid\\s*=\\s*["\']?' . preg_quote($source_gallery_id, '/') . '["\'\\s\\]]/i',
        '/wp:(?:fooplugins|foogallery)[^\\}]*\\b' . preg_quote((string) $id_numeric, '/') . '\\b/i',
      ]
    );
  }

  private function content_reference_exists($like_clauses, $verify_regexes) {
    global $wpdb;

    $candidate_ids = [];
    foreach ((array) $like_clauses as $like) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery
      $rows = $wpdb->get_col(
        $wpdb->prepare(
          "SELECT ID FROM {$wpdb->posts}
           WHERE post_status NOT IN ('auto-draft','trash','inherit')
             AND post_type != 'foogallery'
             AND post_content LIKE %s
           LIMIT 200",
          $like
        )
      );
      if (!empty($rows)) {
        $candidate_ids = array_unique(array_merge($candidate_ids, $rows));
      }
    }

    if (empty($candidate_ids)) {
      return false;
    }

    $ids_placeholder = implode(',', array_map('intval', $candidate_ids));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
    $posts = $wpdb->get_results("SELECT ID, post_type, post_content FROM {$wpdb->posts} WHERE ID IN ({$ids_placeholder})", ARRAY_A);

    foreach ((array) $posts as $post_row) {
      if (empty($post_row['post_type']) || !$this->is_replaceable_post_type($post_row['post_type'])) {
        continue;
      }

      $content = isset($post_row['post_content']) ? (string) $post_row['post_content'] : '';
      foreach ((array) $verify_regexes as $regex) {
        if (preg_match($regex, $content)) {
          return true;
        }
      }
    }

    return false;
  }

  private function elementor_foo_widget_exists($source_gallery_id) {
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $candidate_post_ids = $wpdb->get_col(
      $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta}
         WHERE meta_key = '_elementor_data'
           AND meta_value LIKE %s
         LIMIT 200",
        '%foogallery%'
      )
    );

    if (empty($candidate_post_ids)) {
      return false;
    }

    foreach ($candidate_post_ids as $post_id) {
      $post_type = get_post_type(intval($post_id));
      if (!$this->is_replaceable_post_type($post_type)) {
        continue;
      }

      $raw = get_post_meta(intval($post_id), '_elementor_data', true);
      if (empty($raw) || !is_string($raw)) {
        continue;
      }
      $data = json_decode($raw, true);
      if (is_array($data) && $this->elementor_tree_has_foo_widget($data, $source_gallery_id)) {
        return true;
      }
    }

    return false;
  }

  private function elementor_tree_has_foo_widget(array $elements, $source_gallery_id) {
    foreach ($elements as $element) {
      if (
        !empty($element['elType']) &&
        $element['elType'] === 'widget' &&
        !empty($element['widgetType']) &&
        $element['widgetType'] === 'foogallery' &&
        isset($element['settings']['gallery_id']) &&
        intval($element['settings']['gallery_id']) === $source_gallery_id
      ) {
        return true;
      }

      if (!empty($element['elements']) && is_array($element['elements'])) {
        if ($this->elementor_tree_has_foo_widget($element['elements'], $source_gallery_id)) {
          return true;
        }
      }
    }

    return false;
  }

  private function is_replaceable_post_type($post_type) {
    if (!is_string($post_type) || $post_type === '') {
      return false;
    }

    $excluded = [
      'attachment',
      'revision',
      'nav_menu_item',
      'custom_css',
      'customize_changeset',
      'oembed_cache',
      'user_request',
      REACG_CUSTOM_POST_TYPE,
      'foogallery',
    ];

    if (in_array($post_type, $excluded, true)) {
      return false;
    }

    return post_type_supports($post_type, 'editor') || in_array($post_type, ['wp_block', 'wp_template', 'wp_template_part', 'elementor_library'], true);
  }

  private function walk_elementor_tree(array $elements, $source_gallery_id, $migrated_gallery_id, &$count) {
    foreach ($elements as &$element) {
      if (
        !empty($element['elType']) &&
        $element['elType'] === 'widget' &&
        !empty($element['widgetType']) &&
        $element['widgetType'] === 'foogallery' &&
        isset($element['settings']['gallery_id']) &&
        intval($element['settings']['gallery_id']) === $source_gallery_id
      ) {
        $element['widgetType'] = 'reacg-elementor';
        $element['settings'] = [
          'post_id' => (string) $migrated_gallery_id,
          'enable_options' => 'no',
        ];
        $count++;
        continue;
      }

      if (!empty($element['elements']) && is_array($element['elements'])) {
        $element['elements'] = $this->walk_elementor_tree(
          $element['elements'],
          $source_gallery_id,
          $migrated_gallery_id,
          $count
        );
      }
    }
    unset($element);

    return $elements;
  }

  private function get_template($post_id) {
    $template = get_post_meta($post_id, 'foogallery_template', true);
    return sanitize_key((string) $template);
  }

  private function get_settings($post_id) {
    $settings = get_post_meta($post_id, '_foogallery_settings', true);

    if (is_string($settings)) {
      $decoded_json = json_decode($settings, true);
      if (is_array($decoded_json)) {
        $settings = $decoded_json;
      } else {
        $parsed = maybe_unserialize($settings);
        if (is_array($parsed)) {
          $settings = $parsed;
        }
      }
    }

    return is_array($settings) ? $settings : [];
  }

  private function map_settings($template, $settings) {
    $template = sanitize_key((string) $template);
    $settings = is_array($settings) ? $settings : [];
    if ($template === '') {
      $template = $this->detect_template_from_settings($settings);
    }

    $layout_type = $this->map_template_to_type($template);
    $overrides = [
      'type' => $layout_type,
      $layout_type => [],
    ];

    $prefixes = array_values(array_unique(array_filter([
      $template,
      str_replace('-', '_', $template),
      str_replace('_', '-', $template),
    ])));

    $layout_ref = & $overrides[$layout_type];

    $this->apply_template_preset($template, $layout_type, $overrides, $layout_ref);

    if ( isset($settings[$template . '_thumbnail_dimensions'])) {
      if ( isset($settings[$template . '_thumbnail_dimensions']['width']) ) {
        $layout_ref['width'] = intval($settings[$template . '_thumbnail_dimensions']['width']);
      }
      if ( isset($settings[$template . '_thumbnail_dimensions']['height']) ) {
        $layout_ref['height'] = intval($settings[$template . '_thumbnail_dimensions']['height']);
      }
    }

    if ( isset($settings[$template . '_thumbnail_link'])) {
      if ( $settings[$template . '_thumbnail_link'] === 'image' ) {
        $overrides['general']['clickAction'] = 'lightbox';
      } elseif ( $settings[$template . '_thumbnail_link'] === 'page' || $settings[$template . '_thumbnail_link'] === 'custom' ) {
        $overrides['general']['clickAction'] = 'url';
      } elseif ( $settings[$template . '_thumbnail_link'] === 'none' ) {
        $overrides['general']['clickAction'] = 'none';
      }
    }
    if ( isset($settings[$template . '_spacing']) ) {
      $layout_ref['gap'] = intval($settings[$template . '_spacing']);
    }

    if ( isset($settings[$template . '_theme']) ) {
      if ( $settings[$template . '_theme'] === 'fg-light' ) {
        $layout_ref['paddingColor'] = '#ffffff';
      } elseif ( $settings[$template . '_theme'] === 'fg-dark' ) {
        $layout_ref['paddingColor'] = '#333333';
      }
    }
    if ( isset($settings[$template . '_border_size']) ) {
      if ( $settings[$template . '_border_size'] === '' ) {
        $layout_ref['padding'] = 0;
      } elseif ( $settings[$template . '_border_size'] === 'fg-border-thin' ) {
        $layout_ref['padding'] = 4;
      } elseif ( $settings[$template . '_border_size'] === 'fg-border-medium' ) {
        $layout_ref['padding'] = 10;
      } elseif ( $settings[$template . '_border_size'] === 'fg-border-thick' ) {
        $layout_ref['padding'] = 16;
      }
    }
    if ( isset($settings[$template . '_rounded_corners']) ) {
      if ( $settings[$template . '_rounded_corners'] === '' ) {
        $layout_ref['borderRadius'] = 0;
      } elseif ( $settings[$template . '_rounded_corners'] === 'fg-round-small' ) {
        $layout_ref['borderRadius'] = 1;
      } elseif ( $settings[$template . '_rounded_corners'] === 'fg-round-medium' ) {
        $layout_ref['borderRadius'] = 2;
      } elseif ( $settings[$template . '_rounded_corners'] === 'fg-round-large' ) {
        $layout_ref['borderRadius'] = 5;
      } elseif ( $settings[$template . '_rounded_corners'] === 'fg-round-full' ) {
        $layout_ref['borderRadius'] = 50;
      }
    }
    if ( isset($settings[$template . '_hover_effect_type']) ) {
      if ( $settings[$template . '_hover_effect_type'] === 'normal' ) {
        if ( isset($settings[$template . '_hover_effect_scale']) && ($settings[$template . '_hover_effect_scale'] === 'fg-hover-zoomed'
            || $settings[$template . '_hover_effect_scale'] === 'fg-hover-semi-zoomed') ) {
          $layout_ref['hoverEffect'] = 'zoom-out';
        }
        if ( isset($settings[$template . '_hover_effect_icon']) ) {
          if ( $settings[$template . '_hover_effect_icon'] === '' ) {
            $layout_ref['hoverEffect'] = 'overlay';
          } elseif ( strpos($settings[$template . '_hover_effect_icon'], 'zoom') !== false ) {
            $layout_ref['hoverEffect'] = 'overlay-icon-zoom';
          } elseif ( strpos($settings[$template . '_hover_effect_icon'], 'plus') !== false ) {
            $layout_ref['hoverEffect'] = 'overlay-icon-plus';
          } elseif ( $settings[$template . '_hover_effect_icon'] === 'fg-hover-eye'
          || $settings[$template . '_hover_effect_icon'] === 'fg-hover-external' ) {
            $layout_ref['hoverEffect'] = 'overlay-icon-fullscreen';
          }
        }
      } elseif ( $settings[$template . '_hover_effect_type'] === 'none' ) {
        $layout_ref['showTitle'] = FALSE;
        $layout_ref['showCaption'] = FALSE;
        $layout_ref['showDescription'] = FALSE;
        $layout_ref['hoverEffect'] = 'none';
      } elseif ( $settings[$template . '_hover_effect_type'] === 'preset' ) {
        $layout_ref['showTitle'] = TRUE;
        $layout_ref['showCaption'] = TRUE;
        $layout_ref['titleVisibility'] = 'onHover';
        $layout_ref['captionVisibility'] = 'onHover';
        $layout_ref['titlePosition'] = 'center';
        $layout_ref['captionPosition'] = 'center';
        $layout_ref['titleAlignment'] = 'center';
        $layout_ref['titleFontSize'] = '16px';
        $layout_ref['captionFontSize'] = '11px';
        // Specific hover effecr.
        $overrides['css'] = '.thumbnail-gallery__image-wrapper .thumbnail-gallery__title_on-hover > div, .photo-album-item__image-wrapper .photo-album-item__title_on-hover > div { width: calc(100% - 50px); height: calc(100% - 50px); margin: 25px; background-color: rgba(0, 0, 0, 0.7) !important; } .MuiImageListItemBar-title, .MuiImageListItemBar-subtitle { display: -webkit-box; -webkit-box-orient: vertical; word-break: break-word; margin: 20px; white-space: unset; } .MuiImageListItemBar-title { -webkit-line-clamp: 2; margin-bottom: 0; } .MuiImageListItemBar-subtitle { -webkit-line-clamp: 3; } .MuiImageListItemBar-subtitle:before { content: ""; display: block; width: 30%; border-bottom: 1px solid currentcolor; margin: 0 auto 20px; } .MuiImageListItemBar-titleWrap { gap: 0 !important; }';
      }
    }
    if ( isset($settings[$template . '_hover_effect_caption_visibility']) ) {
      if ( $settings[$template . '_hover_effect_caption_visibility'] === 'fg-caption-hover' ) {
        $layout_ref['titleVisibility'] = 'onHover';
        $layout_ref['titlePosition'] = 'center';
      } elseif ( $settings[$template . '_hover_effect_caption_visibility'] === 'fg-caption-always' ) {
        $layout_ref['titleVisibility'] = 'alwaysShown';
        $layout_ref['titlePosition'] = 'bottom';
      } elseif ( $settings[$template . '_hover_effect_caption_visibility'] === 'fg-captions-bottom' ) {
        $layout_ref['titleVisibility'] = 'alwaysShown';
        $layout_ref['titlePosition'] = 'below';
        $layout_ref['descriptionPosition'] = 'below';
      } elseif ( $settings[$template . '_hover_effect_caption_visibility'] === '' ) {
        $layout_ref['showTitle'] = FALSE;
        $layout_ref['showCaption'] = FALSE;
        $layout_ref['showDescription'] = FALSE;
      }
    }
    if ( isset($settings[$template . '_caption_position']) ) {
      $layout_ref['showTitle'] = TRUE;
      $layout_ref['showDescription'] = TRUE;
      $layout_ref['titleVisibility'] = 'alwaysShown';
      if ( $settings[$template . '_caption_position'] === 'fg-captions-top' ) {
        $layout_ref['titlePosition'] = 'above';
        $layout_ref['descriptionPosition'] = 'above';
      } elseif ( $settings[$template . '_caption_position'] === '' ) {
        $layout_ref['titlePosition'] = 'below';
        $layout_ref['descriptionPosition'] = 'below';
      }
    }
    if ( isset($settings[$template . '_caption_desc_clamp']) ) {
      $layout_ref['descriptionMaxRowsCount'] = intval($settings[$template . '_caption_desc_clamp']);
    }
    if ( isset($settings[$template . '_caption_alignment']) ) {
      if ( $settings[$template . '_caption_alignment'] === 'fg-c-l' ) {
        $layout_ref['titleAlignment'] = 'left';
      } elseif ( $settings[$template . '_caption_alignment'] === 'fg-c-c' ) {
        $layout_ref['titleAlignment'] = 'center';
      } elseif ( $settings[$template . '_caption_alignment'] === 'fg-c-r' ) {
        $layout_ref['titleAlignment'] = 'right';
      }
    }
    if ( isset($settings[$template . '_caption_title_source']) ) {
      $layout_ref['showTitle'] = TRUE;
      if ( $settings[$template . '_caption_title_source'] === 'none' ) {
        $layout_ref['showTitle'] = FALSE;
      } elseif ( $settings[$template . '_caption_title_source'] === 'desc' ) {
        // Only description value is different.
        $layout_ref['titleSource'] = 'description';
      } elseif ( $settings[$template . '_caption_title_source'] === '' ) {
        // Default.
        $layout_ref['titleSource'] = 'title';
      } else {
        $layout_ref['titleSource'] = $settings[$template . '_caption_title_source'];
      }
    }
    if ( isset($settings[$template . '_caption_desc_source']) ) {
      $layout_ref['showDescriptions'] = TRUE;
      if ( $settings[$template . '_caption_desc_source'] === 'none' ) {
        $layout_ref['showDescription'] = FALSE;
      } elseif ( $settings[$template . '_caption_desc_source'] === 'desc' ) {
        // Only description value is different.
        $layout_ref['descriptionSource'] = 'description';
      } elseif ( $settings[$template . '_caption_desc_source'] === '' ) {
        // Default.
        $layout_ref['descriptionSource'] = 'title';
      } else {
        $layout_ref['descriptionSource'] = $settings[$template . '_caption_desc_source'];
      }
    }
    if ( $settings[$template . '_hover_effect_type'] === 'preset' ) {
        $layout_ref['titleSource'] = 'caption';
        $layout_ref['captionSource'] = 'description';
    }
    if ( isset($settings[$template . '_paging_type']) ) {
      if ( strpos($settings[$template . '_paging_type'], 'page') !== false
      || $settings[$template . '_paging_type'] === 'dots' ) {
        $layout_ref['paginationType'] = 'simple';
      } elseif ( $settings[$template . '_paging_type'] === '' ) {
        $layout_ref['paginationType'] = 'none';
      } elseif ( strpos($settings[$template . '_paging_type'], 'infin') !== false ) {
        $layout_ref['paginationType'] = 'scroll';
      } elseif ( strpos($settings[$template . '_paging_type'], 'load') !== false ) {
        $layout_ref['paginationType'] = 'loadMore';
      }
    }
    if ( isset($settings[$template . '_paging_size']) ) {
      $overrides['general']['itemsPerPage'] = intval($settings[$template . '_paging_size']);
    }

    if ( isset($settings[$template . '_lightbox']) ) {
      $overrides['general']['clickAction'] = 'none';
    }
    if ( isset($settings[$template . '_lightbox_theme']) ) {
      if ( $settings[$template . '_lightbox_theme'] === '' ) {
        // Inherit.
        $settings[$template . '_lightbox_theme'] = $settings[$template . '_theme'];
      }
      if ( $settings[$template . '_lightbox_theme'] === 'fg-light' ) {
        $overrides['lightbox']['backgroundColor'] = '#EEEEEE';
      } elseif ( $settings[$template . '_lightbox_theme'] === 'fg-dark' ) {
        $overrides['lightbox']['backgroundColor'] = '#151515';
      }
    }
    if ( isset($settings[$template . '_lightbox_transition']) ) {
      if ( $settings[$template . '_lightbox_transition'] === 'fade' ) {
        $overrides['lightbox']['imageAnimation'] = 'fade';
      } elseif ( $settings[$template . '_lightbox_transition'] === 'horizontal' ) {
        $overrides['lightbox']['imageAnimation'] = 'slideH';
      } elseif ( $settings[$template . '_lightbox_transition'] === 'vertical' ) {
        $overrides['lightbox']['imageAnimation'] = 'slideV';
      } elseif ( $settings[$template . '_lightbox_transition'] === 'none' ) {
        $overrides['lightbox']['imageAnimation'] = 'blur';
      }
    }
    if ( isset($settings[$template . '_lightbox_auto_progress']) ) {
      if ( $settings[$template . '_lightbox_auto_progress'] === 'yes' ) {
        $overrides['lightbox']['autoplay'] = TRUE;
        if ( isset($settings[$template . '_lightbox_auto_progress_seconds']) ) {
          $overrides['lightbox']['slideDuration'] = intval($settings[$template . '_lightbox_auto_progress_seconds']) * 1000;
        }
      } elseif ( $settings[$template . '_lightbox_auto_progress'] === 'no' ) {
        $overrides['lightbox']['autoplay'] = FALSE;
      }
    }
    if ( isset($settings[$template . '_lightbox_show_fullscreen_button']) ) {
      if ( $settings[$template . '_lightbox_show_fullscreen_button'] === 'yes' ) {
        $overrides['lightbox']['isFullscreenAllowed'] = TRUE;
      } elseif ( $settings[$template . '_lightbox_show_fullscreen_button'] === 'no' ) {
        $overrides['lightbox']['isFullscreenAllowed'] = FALSE;
      }
    }
    if ( isset($settings[$template . '_lightbox_thumbs']) ) {
      if ( $settings[$template . '_lightbox_thumbs'] === 'left' ) {
        $overrides['lightbox']['thumbnailsPosition'] = 'end';
      } elseif ( $settings[$template . '_lightbox_thumbs'] === 'right' ) {
        $overrides['lightbox']['thumbnailsPosition'] = 'start';
      } else {
        $overrides['lightbox']['thumbnailsPosition'] = $settings[$template . '_lightbox_thumbs'];
      }
    }
    if ( isset($settings[$template . '_lightbox_thumbs_size']) ) {
      if ( $settings[$template . '_lightbox_thumbs_size'] === 'small' ) {
        $overrides['lightbox']['thumbnailWidth'] = 70;
        $overrides['lightbox']['thumbnailHeight'] = 70;
      }
    }
    if ( isset($settings[$template . '_lightbox_info_enabled']) ) {
      if ( $settings[$template . '_lightbox_info_enabled'] === 'disabled' ) {
        $overrides['lightbox']['showTitle'] = FALSE;
        $overrides['lightbox']['showDescription'] = FALSE;
        $overrides['lightbox']['showCaption'] = FALSE;
      } elseif ( $settings[$template . '_lightbox_info_enabled'] === ''
       || $settings[$template . '_lightbox_info_enabled'] === 'hidden' ) {
        $overrides['lightbox']['showTitle'] = TRUE;
        $overrides['lightbox']['showDescription'] = TRUE;
        $overrides['lightbox']['showCaption'] = FALSE;
      }
    }
    if ( isset($settings[$template . '_lightbox_info_alignment']) ) {
      if ( $settings[$template . '_lightbox_info_alignment'] === 'default'
       || $settings[$template . '_lightbox_info_alignment'] === 'justify' ) {
        $overrides['lightbox']['titleAlignment'] = 'left';
      } else {
        $overrides['lightbox']['titleAlignment'] = $settings[$template . '_lightbox_info_alignment'];
      }
    }
    if ( isset($settings[$template . '_lightbox_info_position']) ) {
      if ( $settings[$template . '_lightbox_info_position'] === 'top' ) {
        $overrides['lightbox']['textPosition'] = isset($settings[$template . '_lightbox_info_overlay']) && $settings[$template . '_lightbox_info_overlay'] === 'yes' ? 'top' : 'above';
      } elseif ( $settings[$template . '_lightbox_info_position'] === 'bottom' ) {
        $overrides['lightbox']['textPosition'] = isset($settings[$template . '_lightbox_info_overlay']) && $settings[$template . '_lightbox_info_overlay'] === 'yes' ? 'bottom' : 'below';
      }
    }
    if ( isset($settings[$template . '_lightbox_caption_override']) ) {
      if ( $settings[$template . '_lightbox_caption_override'] === 'override' ) {
        $overrides['lightbox']['showTitle'] = TRUE;
        if ( $settings[$template . '_lightbox_caption_override_title'] === 'none' ) {
          $overrides['lightbox']['showTitle'] = FALSE;
        } elseif ( $settings[$template . '_lightbox_caption_override_title'] === '' ) {
          // Same as thumbnail.
          $overrides['lightbox']['titleSource'] = $settings[$template . '_caption_title_source'];
        } elseif ( $settings[$template . '_lightbox_caption_override_title'] === 'desc' ) {
          // Only description value is different.
          $overrides['lightbox']['titleSource'] = 'description';
        } else {
          $overrides['lightbox']['titleSource'] = $settings[$template . '_lightbox_caption_override_title'];
        }

        $overrides['lightbox']['showDescription'] = TRUE;
        if ( $settings[$template . '_lightbox_caption_override_desc'] === 'none' ) {
          $overrides['lightbox']['showDescription'] = FALSE;
        } elseif ( $settings[$template . '_lightbox_caption_override_desc'] === '' ) {
          // Same as thumbnail.
          $overrides['lightbox']['descriptionSource'] = $settings[$template . '_caption_desc_source'];
        } elseif ( $settings[$template . '_lightbox_caption_override_desc'] === 'desc' ) {
          // Only description value is different.
          $overrides['lightbox']['descriptionSource'] = 'description';
        } else {
          $overrides['lightbox']['descriptionSource'] = $settings[$template . '_lightbox_caption_override_desc'];
        }
      } elseif ( $settings[$template . '_lightbox_caption_override'] === '' ) {
        // Same as thumbnail.
        $overrides['lightbox']['titleSource'] = $settings[$template . '_caption_title_source'];
        $overrides['lightbox']['descriptionSource'] = $settings[$template . '_caption_desc_source'];
      }
    }

    if ( $template === 'masonry' ) {
      if ( isset($settings[$template . '_thumbnail_width'])) {
        $layout_ref['width'] = 100;
      }
      if ( isset($settings[$template . '_gutter_width'])) {
        $layout_ref['gap'] = intval($settings[$template . '_gutter_width']);
      }
      if ( isset($settings[$template . '_layout'])
          && strpos($settings[$template . '_layout'], 'col') !== false ) {
        $layout_ref['columns'] = intval(str_replace('col', '', $settings[$template . '_layout']));
      }
    }
    if ( $template === 'justified' ) {
      if ( isset($settings[$template . '_thumb_height'])) {
        $layout_ref['rowHeight'] = intval($settings[$template . '_thumb_height']);
      }
      if ( isset($settings[$template . '_margins'])) {
        $layout_ref['gap'] = intval($settings[$template . '_margins']);
      }
    }
    if ( $template === 'simple_portfolio' ) {
      if ( isset($settings[$template . '_gutter'])) {
        $layout_ref['gap'] = intval($settings[$template . '_gutter']);
      }
    }
    if ( $template === 'thumbnail' ) {
      $overrides['type'] = 'thumbnails';
      $layout_ref['columns'] = 1;
      $layout_ref['paginationType'] = 'none';
      $layout_ref['showAllItems'] = false;
      $layout_ref['containerPadding'] = 30;
      $overrides['general']['itemsPerPage'] = 1;
      // Specific template.
      $overrides['css'] = '.MuiImageList-standard>div:nth-child(2) .thumbnail-gallery__image-wrapper  {
            transform: rotate(-15deg);
            }
            .MuiImageList-standard>div:nth-child(3) .thumbnail-gallery__image-wrapper  {
            transform: rotate(10deg);
            }
            .MuiImageList-standard>div:nth-child(4) .thumbnail-gallery__image-wrapper  {
            transform: rotate(-5deg);
            }
            .thumbnail-gallery__image-wrapper  {
            box-shadow: 0 0 3px 0 #959595;
            }
            .reacg-thumbnails-item:first-child  {
            z-index: 1;
            }
            .reacg-thumbnails-item:not(:first-child)  {
            position: absolute;
            }';
    }

    if ( $template === 'image-viewer' ) {
      $overrides['type'] = 'thumbnails';
      $layout_ref['columns'] = 1;
      $layout_ref['paginationType'] = 'simple';
      $overrides['general']['itemsPerPage'] = 1;
      if ( isset($settings[$template . '_thumbnail_size'])) {
        if ( isset($settings[$template . '_thumbnail_size']['width']) ) {
          $layout_ref['width'] = intval($settings[$template . '_thumbnail_size']['width']);
        }
        if ( isset($settings[$template . '_thumbnail_size']['height']) ) {
          $layout_ref['height'] = intval($settings[$template . '_thumbnail_size']['height']);
        }
      }
    }
    if ( $template === 'carousel' ) {
      $layout_ref['scale'] = 0.1;
      if ( isset($settings[$template . '_maxItems'])) {
        $layout_ref['imagesCount'] = intval($settings[$template . '_maxItems']);
      }
      if ( isset($settings[$template . '_gutter']['max'])) {
        $layout_ref['spaceBetween'] = intval($settings[$template . '_gutter']['max']);
      }
      if ( isset($settings[$template . '_autoplay_interaction'])) {
        if ( $settings[$template . '_autoplay_interaction'] === 'pause' ) {
          $layout_ref['autoplay'] = TRUE;
        } elseif ( $settings[$template . '_autoplay_interaction'] === 'disable' ) {
          $layout_ref['autoplay'] = FALSE;
        }
      }
      if ( isset($settings[$template . '_autoplay_time'])) {
        if ( $settings[$template . '_autoplay_time'] == 0 ) {
          $layout_ref['autoplay'] = FALSE;
        } else {
          $layout_ref['slideDuration'] = intval($settings[$template . '_autoplay_time']) * 1000;
        }
      }
      if ( isset($settings[$template . '_show_nav_arrows'])) {
        if ( isset($settings[$template . '_show_nav_arrows'])
             && isset($settings[$template . '_show_pagination']) ) {
          if ( $settings[$template . '_show_nav_arrows'] === ''
          && $settings[$template . '_show_pagination'] === '' ) {
            $layout_ref['navigation'] = 'arrowsAndDots';
          }
          if ( $settings[$template . '_show_nav_arrows'] === ''
          && $settings[$template . '_show_pagination'] !== '' ) {
            $layout_ref['navigation'] = 'arrows';
          }
          if ( $settings[$template . '_show_nav_arrows'] !== ''
          && $settings[$template . '_show_pagination'] === '' ) {
            $layout_ref['navigation'] = 'dots';
          }
        }
      }
    }

    return $this->cleanup_overrides($overrides);
  }

  private function map_template_to_type($template) {
    $template = $this->normalize_template_slug($template);
    if ($template === 'justified') {
      return 'justified';
    }
    if ($template === 'masonry') {
      return 'masonry';
    }
    if ($template === 'carousel') {
      return 'carousel';
    }
    if (in_array($template, [
      'responsive',
      'grid',
      'image_viewer',
      'image-viewer',
      'simple_portfolio',
      'single_thumbnail',
      'thumbnail',
      'polaroid',
      'polaroid_pro',
      'foogridpro',
      'default',
      'slider',
      'slideshow',
    ], true)) {
      return 'thumbnails';
    }

    return 'thumbnails';
  }

  private function detect_template_from_settings($settings) {
    foreach (array_keys((array) $settings) as $key) {
      $key = (string) $key;
      if (strpos($key, 'masonry_') === 0) {
        return 'masonry';
      }
      if (strpos($key, 'justified_') === 0) {
        return 'justified';
      }
      if (strpos($key, 'thumbnail_') === 0) {
        return 'thumbnail';
      }
      if (strpos($key, 'responsive_') === 0 || strpos($key, 'grid_') === 0) {
        return 'responsive';
      }
      if (strpos($key, 'simple_portfolio_') === 0) {
        return 'simple_portfolio';
      }
      if (strpos($key, 'carousel_') === 0) {
        return 'carousel';
      }
      if (strpos($key, 'polaroid_') === 0 || strpos($key, 'foogridpro_') === 0) {
        return 'polaroid';
      }
      if (strpos($key, 'image_viewer_') === 0 || strpos($key, 'image-viewer_') === 0) {
        return 'image_viewer';
      }
    }

    return '';
  }

  private function normalize_template_slug($template) {
    return strtolower(str_replace('-', '_', sanitize_key((string) $template)));
  }

  private function apply_template_preset($template, $layout_type, &$overrides, &$layout_ref) {
    $template = $this->normalize_template_slug($template);

    // Requested mapping: image_viewer/single_thumbnail => Grid with page count 1.
    if (in_array($template, ['image_viewer', 'thumbnail', 'single_thumbnail'], true) && $layout_type === 'thumbnails') {
      $overrides['general']['itemsPerPage'] = 1;
      $layout_ref['showAllItems'] = false;
      $layout_ref['paginationType'] = 'simple';
    }

    // Requested mapping: simple portfolio => Grid with title + description below.
    if ($template === 'simple_portfolio' && $layout_type === 'thumbnails') {
      $layout_ref['showTitle'] = true;
      $layout_ref['titleVisibility'] = 'alwaysShown';
      $layout_ref['titlePosition'] = 'below';
      $layout_ref['showDescription'] = true;
      $layout_ref['descriptionPosition'] = 'below';
    }

    // Requested mapping: polaroid pro => Grid with title below.
    if (in_array($template, ['polaroid', 'polaroid_pro', 'foogridpro'], true) && $layout_type === 'thumbnails') {
      $layout_ref['showTitle'] = true;
      $layout_ref['titleVisibility'] = 'alwaysShown';
      $layout_ref['titlePosition'] = 'below';
    }
  }

  private function cleanup_overrides($value) {
    if (!is_array($value)) {
      return $value;
    }

    foreach ($value as $key => $item) {
      if (is_array($item)) {
        $value[$key] = $this->cleanup_overrides($item);
        if ($value[$key] === []) {
          unset($value[$key]);
        }
      }
    }

    return $value;
  }

  private function extract_attachment_ids($post_id) {
    // FooGallery stores media-library image IDs in `foogallery_attachments`.
    $value = get_post_meta($post_id, 'foogallery_attachments', true);

    // Backward/defensive fallback for unexpected custom installs.
    if (empty($value)) {
      $value = get_post_meta($post_id, '_foogallery_attachments', true);
    }

    $ids = $this->normalize_ids($value);

    // Fallback: some datasource flows can keep IDs in datasource payload.
    if (empty($ids)) {
      $datasource_value = get_post_meta($post_id, '_foogallery_datasource_value', true);
      $ids = $this->normalize_ids($datasource_value);
    }

    $ids = array_filter(array_map('intval', (array) $ids));
    return array_values(array_unique($ids));
  }

  private function normalize_ids($value) {
    if (empty($value)) {
      return [];
    }

    if (is_string($value)) {
      $decoded_json = json_decode($value, true);
      if (is_array($decoded_json)) {
        $value = $decoded_json;
      } else {
        $parsed = maybe_unserialize($value);
        if (is_array($parsed)) {
          $value = $parsed;
        } else {
          return array_filter(array_map('trim', explode(',', (string) $value)));
        }
      }
    }

    if (!is_array($value)) {
      return [];
    }

    $ids = [];
    foreach ($value as $key => $item) {
      if (is_array($item)) {
        // Common shapes: ['id' => 123], nested lists, associative payloads.
        if (isset($item['id']) && is_numeric($item['id'])) {
          $ids[] = intval($item['id']);
        }
        if (isset($item['attachment_id']) && is_numeric($item['attachment_id'])) {
          $ids[] = intval($item['attachment_id']);
        }
        $ids = array_merge($ids, $this->normalize_ids($item));
        continue;
      }

      if (is_numeric($item)) {
        $ids[] = intval($item);
        continue;
      }

      if (is_string($item) && is_numeric(trim($item))) {
        $ids[] = intval(trim($item));
        continue;
      }

      // Ignore non-numeric payload values.
    }

    return $ids;
  }
}
