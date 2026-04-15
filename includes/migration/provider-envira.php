<?php

defined('ABSPATH') || die('Access Denied');

class REACG_Migration_Provider_Envira implements REACG_Migration_Provider_Interface {
  public function get_key() {
    return 'envira';
  }

  public function get_label() {
    return 'Envira Gallery';
  }

  public function is_available() {
    return post_type_exists('envira');
  }

  public function list_galleries($args = []) {
    $page = !empty($args['page']) ? max(1, intval($args['page'])) : 1;
    $per_page = !empty($args['per_page']) ? max(1, intval($args['per_page'])) : 50;
    $search = !empty($args['search']) ? sanitize_text_field($args['search']) : '';

    $query = new WP_Query([
      'post_type' => 'envira',
      'post_status' => ['publish', 'draft', 'private'],
      'posts_per_page' => $per_page,
      'paged' => $page,
      's' => $search,
      'fields' => 'ids',
      'no_found_rows' => false,
    ]);

    $items = [];
    foreach ($query->posts as $post_id) {
      $items[] = [
        'id' => (string) $post_id,
        'title' => get_the_title($post_id) ?: __('(no title)', 'regallery'),
        'image_count' => count($this->extract_attachment_ids($post_id)),
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
    if (!$post_id || get_post_type($post_id) !== 'envira') {
      return new WP_Error('reacg_migration_invalid_gallery', __('Invalid Envira gallery.', 'regallery'));
    }

    $data = $this->get_gallery_data($post_id);
    $gallery_items = !empty($data['gallery']) && is_array($data['gallery']) ? $data['gallery'] : [];
    $items = [];
    foreach ($gallery_items as $key => $item_data) {
      $item_data = is_array($item_data) ? $item_data : [];
      $item = [];

      if (is_array($item_data) && !empty($item_data['id'])) {
        $item['attachment_id'] = intval($item_data['id']);
      } else {
        $item['attachment_id'] = intval($key);
      }

      if (!empty($item_data['src']) && empty($item['attachment_id'])) {
        $item['url'] = esc_url_raw($item_data['src']);
      }

      if (!empty($item_data['title'])) {
        $item['title'] = sanitize_text_field($item_data['title']);
      }
      if (!empty($item_data['caption'])) {
        $item['caption'] = sanitize_text_field($item_data['caption']);
      }
      if (!empty($item_data['description'])) {
        $item['description'] = sanitize_textarea_field($item_data['description']);
      }
      if (!empty($item_data['alt'])) {
        $item['alt'] = sanitize_text_field($item_data['alt']);
      }

      $action_url = '';
      foreach (['link', 'href', 'url'] as $link_key) {
        if (!empty($item_data[$link_key])) {
          $action_url = $item_data[$link_key];
          break;
        }
      }
      if ($action_url !== '') {
        $item['action_url'] = esc_url_raw($action_url);
      }

      if (!empty($item['attachment_id']) || !empty($item['url'])) {
        $items[] = $item;
      }
    }
    return [
      'id' => (string) $post_id,
      'title' => get_the_title($post_id) ?: __('Imported Envira Gallery', 'regallery'),
      'items' => $items,
      'settings' => $this->map_settings($data),
    ];
  }

  public function get_shortcode_patterns($source_gallery_id) {
    $id = preg_quote((string) $source_gallery_id, '/');

    return [
      '/\\[envira-gallery\\b[^\\]]*\\bid\\s*=\\s*(?:"|\')?' . $id . '(?:"|\')?[^\\]]*\\]/i',
      '/\\[envira\\b[^\\]]*\\bid\\s*=\\s*(?:"|\')?' . $id . '(?:"|\')?[^\\]]*\\]/i',
    ];
  }

  public function get_block_namespaces() {
    return ['envira-gallery', 'envira'];
  }

  public function get_block_id_attributes() {
    return ['id'];
  }

  public function prefer_shortcode_replacement() {
    return true;
  }

  public function replace_specific_gallery($source_gallery_id, $migrated_gallery_id, $replacement_shortcode, $replacement_block) {
    return null;
  }

  public function replace_post_meta_references($post_id, $source_gallery_id, $migrated_gallery_id) {
    return 0;
  }

  public function source_exists_in_posts($source_gallery_id) {
    $source_gallery_id = trim((string) $source_gallery_id);
    $id_numeric = intval($source_gallery_id);

    return $this->content_reference_exists(
      ['%[envira-gallery%', '%[envira %', '%wp:envira%'],
      [
        '/\\[envira(?:-gallery)?\\b[^\\]]*\\bid\\s*=\\s*["\']?' . preg_quote($source_gallery_id, '/') . '["\'\\s\\]]/i',
        '/wp:envira[^\\}]*\\b' . preg_quote((string) $id_numeric, '/') . '\\b/i',
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
    $contents = $wpdb->get_col("SELECT post_content FROM {$wpdb->posts} WHERE ID IN ({$ids_placeholder})");

    foreach ((array) $contents as $content) {
      foreach ((array) $verify_regexes as $regex) {
        if (preg_match($regex, $content)) {
          return true;
        }
      }
    }

    return false;
  }

  private function get_gallery_data($post_id) {
    $data = get_post_meta($post_id, '_eg_gallery_data', true);
    if (is_string($data)) {
      $decoded = $this->safe_unserialize_array($data);
      if (is_array($decoded)) {
        $data = $decoded;
      }
    }

    return is_array($data) ? $data : [];
  }

  private function safe_unserialize_array($value) {
    if (!is_string($value) || $value === '' || !is_serialized($value)) {
      return [];
    }

    $decoded = @unserialize(trim($value), ['allowed_classes' => false]);
    if (is_array($decoded)) {
      return $decoded;
    }

    return [];
  }

  private function extract_attachment_ids($post_id) {
    $data = $this->get_gallery_data($post_id);

    $ids = [];
    if (!empty($data['gallery']) && is_array($data['gallery'])) {
      foreach ($data['gallery'] as $key => $value) {
        if (is_array($value) && isset($value['id'])) {
          $ids[] = intval($value['id']);
        } else {
          $ids[] = intval($key);
        }
      }
    }

    $ids = array_filter(array_map('intval', $ids));
    return array_values(array_unique($ids));
  }

  private function map_settings($data) {
    $config = !empty($data['config']) && is_array($data['config']) ? $data['config'] : [];
    $overrides = [];
    $title_visibility_explicit = false;
    $caption_visibility_explicit = false;

    $layout = $this->detect_type($config);
    $overrides['type'] = $layout;

    $overrides[$layout] = [];

    $columns = $this->get_int($config, ['columns']);
    if ($columns !== null) {
      if ($columns === 0) {
        $layout = 'justified';
        $overrides['type'] = $layout;
        $overrides[$layout] = [];
      } else {
        $layout = 'masonry';
        $overrides['type'] = $layout;
        $overrides[$layout] = [];
        $overrides[$layout]['columns'] = max(1, $columns);
      }
    }

    $gap = $this->get_int($config, ['gutter']);
    if ($gap !== null) {
      $overrides[$layout]['gap'] = max(0, $gap);
    }

    $row_height = $this->get_int($config, ['justified_row_height']);
    if ($row_height !== null && $layout === 'justified') {
      $overrides[$layout]['rowHeight'] = max(50, $row_height + 70);
    }
    $justified_margins = $this->get_int($config, ['justified_margins']);
    if ($justified_margins !== null && $layout === 'justified') {
      $overrides[$layout]['gap'] = max(0, $justified_margins);
    }

    // Additional Envira aliases used by some versions/addons.
    $title_hover_flag = $this->get_bool($config, [
      'title_hover',
      'title_on_hover',
      'title_display_on_hover',
      'show_titles_on_hover',
    ]);
    if ($title_hover_flag === true) {
      $overrides[$layout]['showTitle'] = true;
      $overrides[$layout]['titleVisibility'] = 'onHover';
      $title_visibility_explicit = true;
    }

    $show_caption = $this->get_bool($config, ['caption_display', 'captions', 'show_caption']);
    if ($show_caption !== null) {
      $overrides[$layout]['showCaption'] = $show_caption;
      $overrides['lightbox']['showCaption'] = $show_caption;
    }

    $caption_display_mode = $this->get_string($config, ['caption_display', 'caption_position', 'captions']);
    if ($caption_display_mode !== null) {
      $caption_visibility_explicit = true;
      $normalized_caption_mode = strtolower($caption_display_mode);
      $is_hidden = strpos($normalized_caption_mode, 'none') !== false || strpos($normalized_caption_mode, 'hide') !== false;
      $is_on_hover = strpos($normalized_caption_mode, 'hover') !== false;
      $is_always = strpos($normalized_caption_mode, 'always') !== false || strpos($normalized_caption_mode, 'show') !== false;

      $overrides[$layout]['showCaption'] = !$is_hidden;
      if ($is_on_hover) {
        $overrides[$layout]['captionVisibility'] = 'onHover';
      } elseif ($is_always) {
        $overrides[$layout]['captionVisibility'] = 'alwaysShown';
      } else {
        // Mode exists but is ambiguous (e.g. "1"); let hover-effect fallback decide.
        $caption_visibility_explicit = false;
      }
    }

    $caption_hover_flag = $this->get_bool($config, [
      'caption_hover',
      'caption_on_hover',
      'caption_display_on_hover',
      'show_captions_on_hover',
    ]);
    if ($caption_hover_flag === true) {
      $overrides[$layout]['showCaption'] = true;
      $overrides[$layout]['captionVisibility'] = 'onHover';
      $caption_visibility_explicit = true;
    }

    $raw_hover_effect = $this->get_string($config, [
      'hover_effect',
      'hover_effects',
      'gallery_hover_effect',
      'hover',
      'effect',
    ]);
    $hover_effect = $this->map_hover_effect($raw_hover_effect);
    $hover_enabled = $this->get_bool($config, [
      'hover_effects_enabled',
      'hover_effect_enabled',
      'enable_hover_effect',
    ]);
    $hover_disabled = $this->get_bool($config, [
      'disable_hover_effect',
      'hover_effects_disabled',
    ]);

    if ($hover_enabled === false || $hover_disabled === true) {
      $hover_effect = 'none';
    } elseif ($hover_effect === null) {
      // Do not inherit Re Gallery default hover effect when Envira does not define one.
      $hover_effect = 'none';
    }

    if ($hover_effect !== null) {
      $overrides[$layout]['hoverEffect'] = $hover_effect;

      // Envira often implies text appears on hover via effect style instead of explicit title/caption mode.
      $is_hover_like_effect = strpos($hover_effect, 'overlay') !== false || strpos($hover_effect, 'zoom') !== false;
      if ($is_hover_like_effect) {
        if (!$title_visibility_explicit && !empty($overrides[$layout]['showTitle'])) {
          $overrides[$layout]['titleVisibility'] = 'onHover';
        }
        if (!$caption_visibility_explicit && !empty($overrides[$layout]['showCaption'])) {
          $overrides[$layout]['captionVisibility'] = 'onHover';
        }
      }
    }

    // Final safeguard: if titles/captions are enabled but visibility was not reliably explicit,
    // default to onHover for Envira to match its common frontend behavior.
    if (!$title_visibility_explicit && !empty($overrides[$layout]['showTitle'])) {
      $overrides[$layout]['titleVisibility'] = 'onHover';
    }
    if (!$caption_visibility_explicit && !empty($overrides[$layout]['showCaption'])) {
      $overrides[$layout]['captionVisibility'] = 'onHover';
    }

    $overrides['general']['clickAction'] = 'none';
    $gallery_link_enabled = $this->get_bool($config, ['gallery_link_enabled']);
    if ($gallery_link_enabled !== null && $gallery_link_enabled) {
      $overrides['general']['clickAction'] = 'url';
    }
    $lightbox_enabled = $this->get_bool($config, ['lightbox_enabled']);
    if ($lightbox_enabled !== null && $lightbox_enabled) {
      $overrides['general']['clickAction'] = 'lightbox';
    }

    $lightbox_image_size = $this->get_string($config, ['lightbox_image_size']);
    if ($lightbox_image_size !== null) {
      $overrides['lightbox']['isFullscreen'] = false;
      switch (strtolower($lightbox_image_size)) {
        case 'thumbnail':
          $overrides['lightbox']['width'] = 150;
          $overrides['lightbox']['height'] = 150;
          break;
        case 'medium':
          $overrides['lightbox']['width'] = 300;
          $overrides['lightbox']['height'] = 300;
          break;
        case 'medium_large':
          $overrides['lightbox']['width'] = 768;
          $overrides['lightbox']['height'] = 400;
          break;
        case 'large':
          $overrides['lightbox']['width'] = 1024;
          $overrides['lightbox']['height'] = 1024;
          break;
        case 'default':
        case 'envira_gallery_random':
        case 'full':
        default:
          $overrides['lightbox']['isFullscreen'] = true;
          break;
      }
    }
    $title_display = $this->get_string($config, ['title_display']);
    if ($title_display !== null) {
      $overrides['lightbox']['showTitle'] = true;
      $overrides['lightbox']['showDescription'] = false;
      $overrides['lightbox']['showCaption'] = false;
      $overrides['lightbox']['showButton'] = false;
      switch (strtolower($title_display)) {
        case 'over':
          $overrides['lightbox']['textPosition'] = 'bottom';
          $overrides['lightbox']['titleAlignment'] = 'left';
          break;
        case 'outside':
        case 'inside':
          $overrides['lightbox']['textPosition'] = 'below';
          $overrides['lightbox']['titleAlignment'] = 'left';
          break;
        case 'float':
        case 'float_wrap':
          $overrides['lightbox']['textPosition'] = 'below';
          $overrides['lightbox']['titleAlignment'] = 'center';
          break;
      }
    }

    $autoplay = $this->get_bool($config, ['slideshow_autoplay', 'lightbox_autoplay', 'autoplay']);
    if ($autoplay !== null) {
      $overrides['lightbox']['autoplay'] = $autoplay;
    }

    $autoplay_speed = $this->get_int($config, ['autoplay_speed', 'slideshow_speed', 'slideshow_speed_mobile']);
    if ($autoplay_speed !== null) {
      $overrides['lightbox']['slideDuration'] = max(700, $autoplay_speed);
    }

    $infinite = $this->get_bool($config, ['loop', 'infinite']);
    if ($infinite !== null) {
      $overrides['lightbox']['isInfinite'] = $infinite;
    }

    $show_arrows = $this->get_bool($config, ['arrows', 'gallery_arrows', 'lightbox_arrows']);
    if ($show_arrows !== null) {
      $overrides['lightbox']['areControlButtonsShown'] = $show_arrows;
    }

    $sorting = $this->get_string($config, ['sort_by', 'gallery_sorting', 'sorting']);
    if ($sorting !== null) {
      $sorting = strtolower($sorting);
      if (in_array($sorting, ['title', 'caption', 'date', 'default'], true)) {
        $overrides['general']['orderBy'] = $sorting;
      }
    }

    $direction = $this->get_string($config, ['sort_direction', 'sorting_direction', 'order']);
    if ($direction !== null) {
      $direction = strtolower($direction);
      if (in_array($direction, ['asc', 'desc'], true)) {
        $overrides['general']['orderDirection'] = $direction;
      }
    }

    $per_page = $this->get_int($config, ['galleries_per_page', 'items_per_page', 'posts_per_page']);
    if ($per_page !== null && $per_page > 0) {
      $overrides['general']['itemsPerPage'] = $per_page;
    }

    return $this->cleanup_overrides($overrides);
  }

  private function detect_type($config) {
    $layout = strtolower((string) $this->get_string($config, [
      'gallery_layout',
      'layout',
      'type',
      'gallery_theme',
      'view',
      'gallery_view',
      'display',
      'gallery_display',
      'gallery_style',
    ]));
    if (strpos($layout, 'automatic') !== false || strpos($layout, 'auto') !== false) {
      return 'justified';
    }
    if (strpos($layout, 'justified') !== false) {
      return 'justified';
    }

    $automatic_layout = $this->get_bool($config, ['automatic_layout', 'is_automatic_layout', 'auto_layout']);
    if ($automatic_layout === true) {
      return 'justified';
    }

    if (strpos($layout, 'masonry') !== false || strpos($layout, 'isotope') !== false) {
      return 'masonry';
    }

    $is_masonry = $this->get_bool($config, ['is_masonry', 'masonry', 'gallery_masonry']);
    if ($is_masonry) {
      return 'masonry';
    }

    return 'thumbnails';
  }

  private function map_hover_effect($value) {
    if ($value === null) {
      return null;
    }

    $value = strtolower(trim((string) $value));
    if ($value === '') {
      return null;
    }

    $direct_map = [
      'none' => 'none',
      'zoom' => 'zoom-in',
      'zoom-in' => 'zoom-in',
      'zoom-out' => 'zoom-out',
      'slide' => 'slide',
      'rotate' => 'rotate',
      'blur' => 'blur',
      'scale' => 'scale',
      'sepia' => 'sepia',
      'overlay' => 'overlay',
      'flash' => 'flash',
      'shine' => 'shine',
      'circle' => 'circle',
    ];

    if (isset($direct_map[$value])) {
      return $direct_map[$value];
    }

    if (strpos($value, 'zoom') !== false) {
      if (strpos($value, 'icon') !== false || strpos($value, 'overlay') !== false) {
        return 'overlay-icon-zoom';
      }

      return 'overlay-icon-zoom';
    }
    if (strpos($value, 'overlay') !== false || strpos($value, 'caption') !== false) {
      return 'overlay';
    }
    if (strpos($value, 'none') !== false || strpos($value, 'disable') !== false) {
      return 'none';
    }

    return null;
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

  private function get_raw($config, $keys) {
    foreach ((array) $keys as $key) {
      if (isset($config[$key]) && $config[$key] !== '') {
        return $config[$key];
      }
    }

    return null;
  }

  private function get_string($config, $keys) {
    $value = $this->get_raw($config, $keys);
    if ($value === null) {
      return null;
    }

    return sanitize_text_field((string) $value);
  }

  private function get_int($config, $keys) {
    $value = $this->get_raw($config, $keys);
    if ($value === null || !is_numeric($value)) {
      return null;
    }

    return intval($value);
  }

  private function get_bool($config, $keys) {
    $value = $this->get_raw($config, $keys);
    if ($value === null) {
      return null;
    }

    if (is_bool($value)) {
      return $value;
    }

    if (is_numeric($value)) {
      return intval($value) === 1;
    }

    $value = strtolower(trim((string) $value));
    if (in_array($value, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
      return true;
    }
    if (in_array($value, ['0', 'false', 'no', 'off', 'disabled'], true)) {
      return false;
    }

    return null;
  }
}
