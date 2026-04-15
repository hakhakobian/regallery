<?php

defined('ABSPATH') || die('Access Denied');

class REACG_Migration_Provider_10Web implements REACG_Migration_Provider_Interface {
  private $tables_checked = false;
  private $tables_exist = false;
  private $bwg_locations = null;
  private $bwg_options = null;
  private $attachment_cache = [];
  private $basename_index = [];
  private $basename_index_built_for = [];

  public function get_key() {
    return 'tenweb';
  }

  public function get_label() {
    return __('10Web Photo Gallery', 'regallery');
  }

  public function is_available() {
    if (!$this->is_10web_plugin_active()) {
      return false;
    }

    return post_type_exists('bwg_gallery')
      || shortcode_exists('Best_Wordpress_Gallery')
      || $this->required_tables_exist();
  }

  private function is_10web_plugin_active() {
    if (function_exists('is_plugin_active') && is_plugin_active('photo-gallery/photo-gallery.php')) {
      return true;
    }

    if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('photo-gallery/photo-gallery.php')) {
      return true;
    }

    return false;
  }

  public function list_galleries($args = []) {
    if (!$this->required_tables_exist()) {
      return new WP_Error('reacg_migration_unavailable', __('10Web Photo Gallery tables were not found.', 'regallery'));
    }

    $page = !empty($args['page']) ? max(1, intval($args['page'])) : 1;
    $per_page = !empty($args['per_page']) ? max(1, intval($args['per_page'])) : 50;
    $search = !empty($args['search']) ? sanitize_text_field($args['search']) : '';

    $items = array_merge($this->get_gallery_items($search), $this->get_used_shortcode_items($search));
    usort($items, function ($a, $b) {
      return strnatcasecmp(
        !empty($a['title']) ? (string) $a['title'] : '',
        !empty($b['title']) ? (string) $b['title'] : ''
      );
    });

    $total = count($items);
    $offset = ($page - 1) * $per_page;

    return [
      'items' => array_values(array_slice($items, $offset, $per_page)),
      'total' => $total,
      'page' => $page,
      'per_page' => $per_page,
    ];
  }

  private function get_gallery_items($search = '') {
    global $wpdb;

    $gallery_table = $this->table_name('bwg_gallery');
    $image_table = $this->table_name('bwg_image');

    $where = ' WHERE 1=1 ';
    $params = [];

    if ($search !== '') {
      $where .= ' AND g.`name` LIKE %s ';
      $params[] = '%' . $wpdb->esc_like($search) . '%';
    }

    $sql = 'SELECT g.`id`, g.`name`, COUNT(i.`id`) AS image_count
      FROM `' . esc_sql($gallery_table) . '` g
      LEFT JOIN `' . esc_sql($image_table) . '` i ON i.`gallery_id` = g.`id` AND i.`published` = 1'
      . $where .
      ' GROUP BY g.`id`, g.`name`
      ORDER BY g.`name` ASC';

    $rows = !empty($params)
      ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A)
      : $wpdb->get_results($sql, ARRAY_A);

    $items = [];
    foreach ((array) $rows as $row) {
      $gallery_id = isset($row['id']) ? intval($row['id']) : 0;
      if ($gallery_id <= 0) {
        continue;
      }

      $items[] = [
        'id' => (string) $gallery_id,
        'title' => !empty($row['name']) ? sanitize_text_field($row['name']) : __('(no title)', 'regallery'),
        'image_count' => isset($row['image_count']) ? intval($row['image_count']) : 0,
      ];
    }

    return $items;
  }

  public function get_gallery($external_id) {
    global $wpdb;

    if (!$this->required_tables_exist()) {
      return new WP_Error('reacg_migration_unavailable', __('10Web Photo Gallery tables were not found.', 'regallery'));
    }

    $source = $this->parse_source_external_id($external_id);
    $gallery_id = !empty($source['gallery_id']) ? intval($source['gallery_id']) : 0;
    if ($gallery_id <= 0) {
      return new WP_Error('reacg_migration_invalid_gallery', __('Invalid 10Web gallery.', 'regallery'));
    }

    $shortcode_options = [];
    if (!empty($source['shortcode_id'])) {
      $shortcode_options = $this->get_shortcode_options_by_id(intval($source['shortcode_id']));
    }

    $gallery_table = $this->table_name('bwg_gallery');
    $image_table = $this->table_name('bwg_image');

    $gallery_row = $wpdb->get_row($wpdb->prepare(
      'SELECT `id`, `name` FROM `' . esc_sql($gallery_table) . '` WHERE `id` = %d',
      $gallery_id
    ), ARRAY_A);

    if (empty($gallery_row)) {
      return new WP_Error('reacg_migration_not_found', __('10Web gallery not found.', 'regallery'));
    }

    $image_rows = $wpdb->get_results($wpdb->prepare(
      'SELECT `id`, `image_url`, `thumb_url`, `filetype`, `alt`, `description`, `redirect_url` FROM `' . esc_sql($image_table) . '` WHERE `gallery_id` = %d AND `published` = 1 ORDER BY `order` ASC, `id` ASC',
      $gallery_id
    ), ARRAY_A);

    $items = [];
    foreach ((array) $image_rows as $image_row) {
      $filetype = !empty($image_row['filetype']) ? (string) $image_row['filetype'] : '';
      $is_embed = stripos($filetype, 'EMBED') === 0;

      $source_path = !empty($image_row['image_url']) ? (string) $image_row['image_url'] : '';
      if ($source_path === '' && !empty($image_row['thumb_url'])) {
        $source_path = (string) $image_row['thumb_url'];
      }

      $local_paths = [];
      if (!$is_embed) {
        $local_paths = $this->collect_candidate_local_paths($image_row);
      }

      $url = $this->normalize_image_url($source_path);
      $attachment_id = 0;
      if (!$is_embed) {
        $attachment_id = $this->resolve_attachment_from_10web_image($image_row, $url);
      }

      if ($url === '' && $attachment_id <= 0) {
        continue;
      }

      $item = [];
      if ($url !== '') {
        $item['url'] = $url;
      }

      if (!empty($local_paths[0])) {
        $item['local_file'] = (string) $local_paths[0];
      }

      if ($attachment_id > 0) {
        $item['attachment_id'] = intval($attachment_id);
      }

      if (!empty($image_row['alt'])) {
        $item['alt'] = sanitize_text_field($image_row['alt']);
      }

      if (!empty($image_row['description'])) {
        $item['description'] = sanitize_textarea_field($image_row['description']);
      }

      if (!empty($image_row['redirect_url']) && $image_row['redirect_url'] !== '#') {
        $item['action_url'] = esc_url_raw($image_row['redirect_url']);
      }

      $items[] = $item;
    }
    $theme_id = $this->resolve_theme_id($shortcode_options);
    return [
      'id' => (string) $external_id,
      'title' => !empty($gallery_row['name']) ? sanitize_text_field($gallery_row['name']) : __('Imported 10Web Gallery', 'regallery'),
      'items' => $items,
      'settings' => $this->map_settings($gallery_id, $shortcode_options, $theme_id),
    ];
  }

  private function map_settings($gallery_id, $shortcode_options = [], $theme_id = 0) {
    $use_option_defaults = isset($shortcode_options) && isset($shortcode_options['use_option_defaults']) && $shortcode_options['use_option_defaults'] == 1 ? true : false;
    $options = $use_option_defaults ? $this->get_bwg_options() : [];
    $theme_options = $theme_id > 0 ? $this->get_bwg_theme_options($theme_id) : [];
    if (!empty($theme_options)) {
      $options = array_merge($options, $theme_options);
    }
    if (!empty($shortcode_options)) {
      $options = array_merge($options, $shortcode_options);
    }
    if (empty($options)) {
      return [];
    }
    $gallery_type = $this->get_default_gallery_type($shortcode_options);

    $mapped_type = $this->map_10web_type_to_regallery($gallery_type);
    if ($mapped_type === '') {
      return [];
    }

    $view_settings = $this->build_view_settings($gallery_type, $mapped_type, $options);
    if (!empty($view_settings)) {
      $settings = $view_settings;
    }

    $general_settings = $this->build_general_settings($gallery_type, $options);
    if (!empty($general_settings)) {
      $settings['general'] = $general_settings;
    }

    $lightbox_settings = $this->build_lightbox_settings($gallery_type, $options);
    if (!empty($lightbox_settings)) {
      $settings['lightbox'] = $lightbox_settings;
    }
    $settings['type'] = $mapped_type;

    return $settings;
  }

  private function get_default_gallery_type($options = null) {
    if (!is_array($options)) {
      $options = $this->get_bwg_options();
    }

    if (empty($options)) {
      return '';
    }

    if (!empty($options['gallery_type'])) {
      return strtolower(trim($options['gallery_type']));
    }

    return '';
  }

  private function map_10web_type_to_regallery($gallery_type) {
    $gallery_type = strtolower(trim((string) $gallery_type));
    if ($gallery_type === '') {
      return '';
    }

    switch ($gallery_type) {
      case 'thumbnails':
        return 'thumbnails';
      case 'thumbnails_masonry':
        return 'masonry';
      case 'thumbnails_mosaic':
        return 'mosaic';
      case 'slideshow':
        return 'slideshow';
      case 'carousel':
        return 'carousel';
      case 'blog_style':
        return 'blog';
      case 'image_browser':
        return 'thumbnails';
      default:
        return 'thumbnails';
    }
  }

  private function build_view_settings($gallery_type, $mapped_type, $options) {
    $settings = [$mapped_type => []];
    switch ($gallery_type) {
      case 'thumbnails':
        $this->assign_int($settings[$mapped_type], 'columns', $this->get_10web_int($options, $gallery_type, ['image_column_number']));
        $this->assign_int($settings[$mapped_type], 'width', $this->get_10web_int($options, $gallery_type, ['thumb_width']));
        $this->assign_int($settings[$mapped_type], 'height', $this->get_10web_int($options, $gallery_type, ['thumb_height']));
        $this->assign_int($settings[$mapped_type], 'gap', $this->get_10web_css_int($options, $gallery_type, ['thumb_margin']));
        $this->assign_int($settings[$mapped_type], 'padding', $this->get_10web_css_int($options, $gallery_type, ['thumb_padding']));
        $this->assign_int($settings[$mapped_type], 'titleFontSize', $this->get_10web_int($options, $gallery_type, ['thumb_title_font_size']));
        $this->assign_int($settings[$mapped_type], 'descriptionFontSize', $this->get_10web_int($options, $gallery_type, ['thumb_description_font_size']));
        $this->apply_10web_visibility_mode($settings[$mapped_type], 'showTitle', 'titleVisibility', $this->get_10web_string($options, $gallery_type, ['image_title', 'image_title_show_hover', 'thumb_title_pos']));
        $this->assign_string($settings[$mapped_type], 'titlePosition', $this->map_title_position($this->get_10web_string($options, $gallery_type, ['thumb_title_pos'])));
        $this->assign_bool($settings[$mapped_type], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['show_thumb_description', 'show_image_description']));
        $this->assign_string($settings[$mapped_type], 'descriptionPosition', $this->map_description_position($this->get_10web_string($options, $gallery_type, ['thumb_title_pos'])));
        $this->apply_pagination($settings[$mapped_type], $options, $gallery_type, 'image_enable_page', 'images_per_page');
        break;

      case 'thumbnails_masonry':
        $this->assign_int($settings[$mapped_type], 'columns', $this->get_10web_int($options, $gallery_type, ['masonry_image_column_number']));
        $this->assign_int($settings[$mapped_type], 'gap', $this->get_10web_css_int($options, $gallery_type, ['masonry_container_margin']));
        $this->assign_int($settings[$mapped_type], 'padding', $this->get_10web_css_int($options, $gallery_type, ['masonry_thumb_padding']));
        $this->assign_int($settings[$mapped_type], 'titleFontSize', $this->get_10web_int($options, $gallery_type, ['masonry_thumb_title_font_size']));
        $this->assign_int($settings[$mapped_type], 'descriptionFontSize', $this->get_10web_int($options, $gallery_type, ['masonry_description_font_size']));
        $this->apply_10web_visibility_mode($settings[$mapped_type], 'showTitle', 'titleVisibility', $this->get_10web_string($options, $gallery_type, ['image_title']));
        $this->apply_10web_visibility_mode($settings[$mapped_type], 'showDescription', 'descriptionVisibility', $this->get_10web_string($options, $gallery_type, ['image_description']));
        $this->apply_pagination($settings[$mapped_type], $options, $gallery_type, 'masonry_image_enable_page', 'masonry_images_per_page');
        break;

      case 'thumbnails_mosaic':
        if (isset($options['mosaic_hor_ver']) && strtolower(trim($options['mosaic_hor_ver'])) === 'horizontal') {
          $mapped_type = 'justified';
          $settings[$mapped_type] = [];
        }
        $this->assign_int($settings[$mapped_type], 'width', $this->get_10web_int($options, $gallery_type, ['mosaic_total_width']));
        $this->assign_int($settings[$mapped_type], 'rowHeight', $this->get_10web_int($options, $gallery_type, ['mosaic_thumb_size']));
        $this->assign_int($settings[$mapped_type], 'gap', $this->get_10web_css_int($options, $gallery_type, ['mosaic_container_margin']));
        $this->assign_int($settings[$mapped_type], 'padding', $this->get_10web_css_int($options, $gallery_type, ['mosaic_thumb_padding']));
        $this->assign_int($settings[$mapped_type], 'titleFontSize', $this->get_10web_int($options, $gallery_type, ['mosaic_thumb_title_font_size']));
        $this->apply_10web_visibility_mode($settings[$mapped_type], 'showTitle', 'titleVisibility', $this->get_10web_string($options, $gallery_type, ['image_title', 'mosaic_image_title_show_hover', 'mosaic_image_title']));
        $this->apply_pagination($settings[$mapped_type], $options, $gallery_type, 'mosaic_image_enable_page', 'mosaic_images_per_page');
        break;

      case 'slideshow':
        $this->assign_int($settings[$mapped_type], 'width', $this->get_10web_int($options, $gallery_type, ['slideshow_width']));
        $this->assign_int($settings[$mapped_type], 'height', $this->get_10web_int($options, $gallery_type, ['slideshow_height']));
        $this->assign_bool($settings[$mapped_type], 'autoplay', $this->get_10web_bool($options, $gallery_type, ['enable_slideshow_autoplay', 'slideshow_enable_autoplay']));
        $interval_seconds = $this->get_10web_float($options, $gallery_type, ['slideshow_interval']);
        if ($interval_seconds !== null && $interval_seconds > 0) {
          $settings[$mapped_type]['slideDuration'] = max(300, intval(round($interval_seconds * 1000)));
        }
        $this->assign_string($settings[$mapped_type], 'imageAnimation', $this->get_10web_string($options, $gallery_type, ['slideshow_effect']));
        $this->assign_int($settings[$mapped_type], 'titleFontSize', $this->get_10web_int($options, $gallery_type, ['slideshow_title_font_size']));
        $this->assign_int($settings[$mapped_type], 'descriptionFontSize', $this->get_10web_int($options, $gallery_type, ['slideshow_description_font_size']));

        if (isset($options['slideshow_filmstrip_type']) && $options['slideshow_filmstrip_type'] == 0) {
          $settings[$mapped_type]['thumbnailsPosition'] = 'none';
        } else {
          $settings[$mapped_type]['thumbnailsPosition'] = $this->map_thumbnails_position($this->get_10web_string($options, $gallery_type, ['slideshow_filmstrip_pos']));
          if (empty($settings[$mapped_type]['thumbnailsPosition'])) {
            $settings[$mapped_type]['thumbnailsPosition'] = 'bottom';
          }
          $this->assign_int($settings[$mapped_type], 'thumbnailHeight', $this->get_10web_int($options, $gallery_type, ['slideshow_filmstrip_height']));
        }
        $this->assign_bool($settings[$mapped_type], 'showTitle', $this->get_10web_bool($options, $gallery_type, ['slideshow_enable_title']));
        $this->assign_bool($settings[$mapped_type], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['slideshow_enable_description']));
        if (isset($options['slideshow_title_position']) && strpos($options['slideshow_title_position'], 'top') !== false) {
          $settings[$mapped_type]['textPosition'] = 'top';
        } else {
          $settings[$mapped_type]['textPosition'] = 'bottom';
        }
        break;
      case 'image_browser':
        $this->assign_int($settings[$mapped_type], 'width', $this->get_10web_int($options, $gallery_type, ['image_browser_width']));
        $this->assign_int($settings[$mapped_type], 'padding', $this->get_10web_css_int($options, $gallery_type, ['image_browser_padding']));
        $this->assign_int($settings[$mapped_type], 'titleFontSize', $this->get_10web_int($options, $gallery_type, ['image_browser_img_font_size']));
        $this->assign_int($settings[$mapped_type], 'descriptionFontSize', $this->get_10web_int($options, $gallery_type, ['image_browser_img_font_size']));
        $this->assign_bool($settings[$mapped_type], 'showTitle', $this->get_10web_bool($options, $gallery_type, ['image_browser_title_enable']));
        $this->assign_bool($settings[$mapped_type], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['image_browser_description_enable']));
        $settings[$mapped_type]['titleVisibility'] = 'alwaysShown';
        $settings[$mapped_type]['titlePosition'] = $this->map_title_position($this->get_10web_string($options, $gallery_type, ['image_browser_image_title_align']));
        if (empty($settings[$mapped_type]['titlePosition'])) {
          $settings[$mapped_type]['titlePosition'] = 'below';
        }
        $settings[$mapped_type]['descriptionVisibility'] = 'alwaysShown';
        $settings[$mapped_type]['descriptionPosition'] = $this->map_description_position($this->get_10web_string($options, $gallery_type, ['image_browser_image_description_align', 'image_browser_image_title_align']));
        if (empty($settings[$mapped_type]['descriptionPosition'])) {
          $settings[$mapped_type]['descriptionPosition'] = 'below';
        }
        break;

      case 'carousel':
        $this->assign_int($settings[$mapped_type], 'width', $this->get_10web_int($options, $gallery_type, ['carousel_width']));
        $this->assign_int($settings[$mapped_type], 'height', $this->get_10web_int($options, $gallery_type, ['carousel_height']));
        $this->assign_int($settings[$mapped_type], 'imagesCount', $this->get_10web_int($options, $gallery_type, ['carousel_image_column_number']));
        $this->assign_bool($settings[$mapped_type], 'autoplay', $this->get_10web_bool($options, $gallery_type, ['carousel_enable_autoplay']));
        $carousel_interval = $this->get_10web_float($options, $gallery_type, ['carousel_interval']);
        if ($carousel_interval !== null && $carousel_interval > 0) {
          $settings[$mapped_type]['slideDuration'] = max(300, intval(round($carousel_interval * 1000)));
        }
        $this->assign_int($settings[$mapped_type], 'titleFontSize', $this->get_10web_int($options, $gallery_type, ['carousel_caption_p_font_size', 'carousel_gal_title_font_size']));
        $this->assign_bool($settings[$mapped_type], 'showTitle', $this->get_10web_bool($options, $gallery_type, ['carousel_show_gallery_title', 'carousel_show_image_title']));
        $this->assign_bool($settings[$mapped_type], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['carousel_show_gallery_description', 'carousel_show_image_description']));
        $settings[$mapped_type]['titleVisibility'] = 'alwaysShown';
        $settings[$mapped_type]['titlePosition'] = 'bottom';
        break;

      case 'blog_style':
        $this->assign_int($settings[$mapped_type], 'width', $this->get_10web_int($options, $gallery_type, ['blog_style_width']));
        $this->assign_int($settings[$mapped_type], 'padding', $this->get_10web_css_int($options, $gallery_type, ['blog_style_padding']));
        $this->assign_int($settings[$mapped_type], 'titleFontSize', $this->get_10web_int($options, $gallery_type, ['blog_style_gal_title_font_size']));
        $this->assign_int($settings[$mapped_type], 'descriptionFontSize', $this->get_10web_int($options, $gallery_type, ['blog_style_img_font_size']));
        $this->assign_bool($settings[$mapped_type], 'showTitle', $this->get_10web_bool($options, $gallery_type, ['blog_style_title_enable']));
        $this->assign_bool($settings[$mapped_type], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['blog_style_description_enable']));
        $settings[$mapped_type]['titleVisibility'] = 'alwaysShown';
        $settings[$mapped_type]['titlePosition'] = 'below';
        $settings[$mapped_type]['descriptionVisibility'] = 'alwaysShown';
        $settings[$mapped_type]['descriptionPosition'] = 'below';
          
        $this->apply_pagination($settings[$mapped_type], $options, $gallery_type, 'blog_style_enable_page', 'blog_style_images_per_page');
        break;
    }
    
    return $settings;
  }

  private function build_general_settings($gallery_type, $options) {
    $settings = [];

    $this->assign_int($settings, 'itemsPerPage', $this->get_items_per_page($gallery_type, $options));
    $this->assign_string($settings, 'orderBy', $this->get_order_by($gallery_type, $options));
    $this->assign_string($settings, 'orderDirection', $this->get_order_direction($gallery_type, $options));
    $this->assign_bool($settings, 'enableSearch', $this->get_search_enabled($gallery_type, $options));
    $this->assign_string($settings, 'searchPlaceholderText', $this->get_search_placeholder($gallery_type, $options));

    $pagination_button_color = $this->format_10web_color(
      $this->get_10web_string($options, $gallery_type, [
        'page_nav_button_bg_color',
      ]),
      $this->get_10web_int($options, $gallery_type, [
        'page_nav_button_bg_transparent',
      ])
    );
    if ($pagination_button_color !== '') {
      $settings['activeButtonColor'] = $pagination_button_color;
      $settings['inactiveButtonColor'] = $pagination_button_color;
      $settings['loadMoreButtonColor'] = $pagination_button_color;
    }

    $pagination_text_color = $this->format_10web_color($this->get_10web_string($options, $gallery_type, [
      'page_nav_font_color',
    ]));
    if ($pagination_text_color !== '') {
      $settings['paginationTextColor'] = $pagination_text_color;
    }

    $pagination_text_size = $this->get_10web_float($options, $gallery_type, [
      'page_nav_font_size',
    ]);
    if ($pagination_text_size !== null && $pagination_text_size > 0) {
      $settings['paginationButtonTextSize'] = floatval($pagination_text_size);
    }

    $this->assign_int($settings, 'paginationButtonBorderRadius', $this->get_10web_css_int($options, $gallery_type, [
      'page_nav_border_radius',
    ]));

    $pagination_border_style = strtolower($this->get_10web_string($options, $gallery_type, [
      'page_nav_border_style',
    ]));
    if ($pagination_border_style === 'none') {
      $settings['paginationButtonBorderSize'] = 0;
    } else {
      $this->assign_int($settings, 'paginationButtonBorderSize', $this->get_10web_css_int($options, $gallery_type, [
        'page_nav_border_width',
      ]));
    }

    $pagination_border_color = $this->format_10web_color($this->get_10web_string($options, $gallery_type, [
      'page_nav_border_color',
    ]));
    if ($pagination_border_color !== '') {
      $settings['paginationButtonBorderColor'] = $pagination_border_color;
    }

    $click_action = $this->get_10web_string($options, $gallery_type, ['thumb_click_action']);
    $mapped_click_action = $this->map_click_action($click_action);
    if ($mapped_click_action !== '') {
      $settings['clickAction'] = $mapped_click_action;
    }

    $open_in_new_tab = $this->get_10web_bool($options, $gallery_type, ['thumb_link_target']);
    $this->assign_bool($settings, 'openUrlInNewTab', $open_in_new_tab);
    $this->assign_bool($settings, 'enableRightClickProtection', $this->get_10web_bool($options, $gallery_type, [
      'image_right_click',
    ]));

    $watermark_settings = $this->build_watermark_settings($gallery_type, $options);
    if (!empty($watermark_settings)) {
      $settings = array_merge($settings, $watermark_settings);
    }

    return $settings;
  }

  private function build_watermark_settings($gallery_type, $options) {
    $settings = [];

    $watermark_type = strtolower($this->get_10web_string($options, $gallery_type, [
      'watermark_type',
    ]));

    $watermark_image_url = $this->get_10web_string($options, $gallery_type, [
      'watermark_url',
    ]);

    if ($watermark_type === 'image' && $watermark_image_url !== '') {
      $settings['enableWatermark'] = true;
    }

    $settings['watermarkImageURL'] = esc_url_raw($watermark_image_url);

    $transparency = $this->get_10web_int($options, $gallery_type, [
      'watermark_opacity',
    ]);
    if ($transparency !== null) {
      $settings['watermarkTransparency'] = max(0, min(100, intval($transparency)));
    }

    $watermark_position = $this->map_watermark_position($this->get_10web_string($options, $gallery_type, [
      'watermark_position',
    ]));
    if ($watermark_position !== '') {
      $settings['watermarkPosition'] = $watermark_position;
    }

    return $settings;
  }

  private function build_lightbox_settings($gallery_type, $options) {
    $settings = [];

    $this->assign_bool($settings, 'isFullscreen', $this->get_10web_bool($options, $gallery_type, [
      'popup_fullscreen',
      'lightbox_fullscreen',
      'slideshow_fullscreen',
    ]));
    $this->assign_int($settings, 'width', $this->get_10web_int($options, $gallery_type, [
      'popup_width',
      'lightbox_width',
      'slideshow_lightbox_width',
    ]));
    $this->assign_int($settings, 'height', $this->get_10web_int($options, $gallery_type, [
      'popup_height',
      'lightbox_height',
      'slideshow_lightbox_height',
    ]));

    $this->assign_bool($settings, 'areControlButtonsShown', $this->get_10web_bool($options, $gallery_type, [
      'popup_enable_ctrl_btn',
      'lightbox_ctrl_btn',
      'lightbox_show_controls',
    ]));
    $this->assign_bool($settings, 'isInfinite', $this->get_10web_bool($options, $gallery_type, [
      'popup_enable_loop',
      'lightbox_loop',
      'slideshow_enable_loop',
    ]));
    $this->assign_bool($settings, 'autoplay', $this->get_10web_bool($options, $gallery_type, [
      'popup_enable_autoplay',
      'lightbox_autoplay',
      'slideshow_autoplay',
    ]));

    $duration = $this->get_10web_float($options, $gallery_type, [
      'popup_interval',
      'lightbox_interval',
      'slideshow_interval',
    ]);
    if ($duration !== null && $duration > 0) {
      $duration_ms = $duration > 100 ? $duration : $duration * 1000;
      $settings['slideDuration'] = max(300, intval(round($duration_ms)));
    }

    $this->assign_int($settings, 'titleFontSize', $this->get_10web_int($options, $gallery_type, [
      'lightbox_title_font_size',
      'slideshow_title_font_size',
    ]));
    $this->assign_int($settings, 'descriptionFontSize', $this->get_10web_int($options, $gallery_type, [
      'lightbox_description_font_size',
      'slideshow_description_font_size',
    ]));

    $text_font_family = $this->get_10web_string($options, $gallery_type, [
      'lightbox_title_font_style',
      'lightbox_description_font_style',
      'slideshow_title_font',
      'slideshow_description_font',
    ]);
    if ($text_font_family !== '') {
      $settings['textFontFamily'] = $text_font_family;
    }

    $background_color = $this->format_10web_color(
      $this->get_10web_string($options, $gallery_type, [
        'lightbox_overlay_bg_color',
        'lightbox_bg_color',
        'slideshow_cont_bg_color',
      ]),
      $this->get_10web_int($options, $gallery_type, [
        'lightbox_overlay_bg_transparent',
        'lightbox_bg_transparent',
      ])
    );
    if ($background_color !== '') {
      $settings['backgroundColor'] = $background_color;
    }

    $text_background = $this->format_10web_color(
      $this->get_10web_string($options, $gallery_type, [
        'lightbox_info_bg_color',
        'slideshow_title_background_color',
        'slideshow_description_background_color',
      ]),
      $this->get_10web_int($options, $gallery_type, [
        'lightbox_info_bg_transparent',
        'slideshow_title_opacity',
        'slideshow_description_opacity',
      ])
    );
    if ($text_background !== '') {
      $settings['textBackground'] = $text_background;
    }

    $text_color = $this->format_10web_color($this->get_10web_string($options, $gallery_type, [
      'lightbox_title_color',
      'lightbox_description_color',
      'slideshow_title_color',
      'slideshow_description_color',
    ]));
    if ($text_color !== '') {
      $settings['textColor'] = $text_color;
    }

    $this->assign_bool($settings, 'showTitle', $this->get_10web_bool($options, $gallery_type, [
      'popup_show_title',
      'lightbox_show_title',
      'show_image_title',
    ]));
    $this->assign_bool($settings, 'showDescription', $this->get_10web_bool($options, $gallery_type, [
      'popup_show_description',
      'lightbox_show_description',
      'show_image_description',
    ]));

    $title_alignment = $this->map_alignment($this->get_10web_string($options, $gallery_type, [
      'popup_title_position',
      'lightbox_title_position',
      'popup_title_alignment',
      'lightbox_title_alignment',
      'lightbox_info_align',
    ]));
    if ($title_alignment !== '') {
      $settings['titleAlignment'] = $title_alignment;
    }

    $text_position = $this->map_lightbox_text_position($this->get_10web_string($options, $gallery_type, [
      'popup_description_position',
      'lightbox_description_position',
      'popup_text_position',
      'lightbox_text_position',
      'lightbox_info_pos',
      'slideshow_title_position',
    ]));
    if ($text_position !== '') {
      $settings['textPosition'] = $text_position;
    }

    $this->assign_bool($settings, 'canDownload', $this->get_10web_bool($options, $gallery_type, [
      'popup_enable_download',
      'lightbox_enable_download',
    ]));
    $this->assign_bool($settings, 'canZoom', $this->get_10web_bool($options, $gallery_type, [
      'popup_enable_zoom',
      'lightbox_enable_zoom',
    ]));
    $this->assign_bool($settings, 'canFullscreen', $this->get_10web_bool($options, $gallery_type, [
      'popup_enable_fullscreen',
      'lightbox_enable_fullscreen',
    ]));

    $thumbnails_enabled = $this->get_10web_bool($options, $gallery_type, [
      'popup_enable_filmstrip',
      'lightbox_enable_filmstrip',
    ]);
    if ($thumbnails_enabled === false) {
      $settings['thumbnailsPosition'] = 'none';
    } else {
      $thumbnails_position = $this->map_thumbnails_position($this->get_10web_string($options, $gallery_type, [
        'popup_filmstrip_position',
        'lightbox_filmstrip_position',
        'lightbox_filmstrip_pos',
        'slideshow_filmstrip_pos',
      ]));
      if ($thumbnails_position !== '') {
        $settings['thumbnailsPosition'] = $thumbnails_position;
      }
    }

    $this->assign_int($settings, 'thumbnailBorder', $this->get_10web_int($options, $gallery_type, [
      'lightbox_filmstrip_thumb_border_width',
      'slideshow_filmstrip_thumb_border_width',
    ]));
    $this->assign_int($settings, 'thumbnailBorderRadius', $this->get_10web_css_int($options, $gallery_type, [
      'lightbox_filmstrip_thumb_border_radius',
      'slideshow_filmstrip_thumb_border_radius',
    ]));
    $this->assign_int($settings, 'thumbnailGap', $this->get_10web_css_int($options, $gallery_type, [
      'lightbox_filmstrip_thumb_margin',
      'slideshow_filmstrip_thumb_margin',
    ]));

    $thumbnail_border_color = $this->format_10web_color($this->get_10web_string($options, $gallery_type, [
      'lightbox_filmstrip_thumb_border_color',
      'slideshow_filmstrip_thumb_border_color',
    ]));
    if ($thumbnail_border_color !== '') {
      $settings['thumbnailBorderColor'] = $thumbnail_border_color;
    }

    return $settings;
  }

  private function apply_pagination(&$settings, $options, $gallery_type, $enable_key, $per_page_key) {
    $paginationType = $this->get_10web_string($options, $gallery_type, [$enable_key]);
    if ($paginationType !== null) {
      switch ($paginationType) {
        case "1":
          $settings['paginationType'] = 'simple';
          break;
        case "2":
          $settings['paginationType'] = 'loadMore';
          break;
        case "3":
          $settings['paginationType'] = 'scroll';
          break;
        default:
          $settings['paginationType'] = 'none';
          break;
      }
    }

    $items_per_page = $this->get_10web_int($options, $gallery_type, [$per_page_key]);
    if ($items_per_page !== null && $items_per_page > 0) {
      $settings['showAllItems'] = false;
    } else {
      $settings['showAllItems'] = true;
    }
  }

  private function get_items_per_page($gallery_type, $options) {
    $per_type_keys = [
      'thumbnails' => 'images_per_page',
      'thumbnails_masonry' => 'masonry_images_per_page',
      'thumbnails_mosaic' => 'mosaic_images_per_page',
      'blog_style' => 'blog_style_images_per_page',
      'carousel' => 'carousel_images_per_page',
    ];

    $key = isset($per_type_keys[$gallery_type]) ? $per_type_keys[$gallery_type] : 'images_per_page';
    return $this->get_10web_int($options, $gallery_type, [$key]);
  }

  private function get_order_by($gallery_type, $options) {
    $per_type_keys = [
      'thumbnails' => 'sort_by',
      'thumbnails_masonry' => 'masonry_sort_by',
      'thumbnails_mosaic' => 'mosaic_sort_by',
      'blog_style' => 'blog_style_sort_by',
      'image_browser' => 'image_browser_sort_by',
      'slideshow' => 'slideshow_sort_by',
      'carousel' => 'carousel_sort_by',
    ];

    $key = isset($per_type_keys[$gallery_type]) ? $per_type_keys[$gallery_type] : 'sort_by';
    $value = strtolower($this->get_10web_string($options, $gallery_type, [$key, 'sort_by', 'sorting']));

    $map = [
      'order' => 'default',
      'alt' => 'title',
      'filename' => 'title',
      'date' => 'date',
      'random' => 'default',
      'size' => 'default',
    ];

    if (isset($map[$value])) {
      return $map[$value];
    }

    return '';
  }

  private function get_order_direction($gallery_type, $options) {
    $per_type_keys = [
      'thumbnails' => 'order_by',
      'thumbnails_masonry' => 'masonry_order_by',
      'thumbnails_mosaic' => 'mosaic_order_by',
      'blog_style' => 'blog_style_order_by',
      'image_browser' => 'image_browser_order_by',
      'slideshow' => 'slideshow_order_by',
      'carousel' => 'carousel_order_by',
    ];

    $key = isset($per_type_keys[$gallery_type]) ? $per_type_keys[$gallery_type] : 'order_by';
    $value = strtolower($this->get_10web_string($options, $gallery_type, [$key, 'sort_direction', 'sorting_direction', 'order']));

    if (in_array($value, ['asc', 'desc'], true)) {
      return $value;
    }

    return 'asc';
  }

  private function get_search_enabled($gallery_type, $options) {
    $per_type_keys = [
      'thumbnails' => 'show_search_box',
      'thumbnails_masonry' => 'masonry_show_search_box',
      'thumbnails_mosaic' => 'mosaic_show_search_box',
      'blog_style' => 'blog_style_show_search_box',
      'image_browser' => 'image_browser_show_search_box',
    ];

    $key = isset($per_type_keys[$gallery_type]) ? $per_type_keys[$gallery_type] : 'show_search_box';
    return $this->get_10web_bool($options, $gallery_type, [$key]);
  }

  private function get_search_placeholder($gallery_type, $options) {
    $per_type_keys = [
      'thumbnails' => 'placeholder',
      'thumbnails_masonry' => 'masonry_placeholder',
      'thumbnails_mosaic' => 'mosaic_placeholder',
      'blog_style' => 'blog_style_placeholder',
      'image_browser' => 'image_browser_placeholder',
    ];

    $key = isset($per_type_keys[$gallery_type]) ? $per_type_keys[$gallery_type] : 'placeholder';
    return $this->get_10web_string($options, $gallery_type, [$key]);
  }

  private function get_10web_int($options, $gallery_type, $keys) {
    $value = $this->get_10web_option_value($options, $gallery_type, $keys);
    if ($value === null || $value === '') {
      return null;
    }

    if (!is_numeric($value)) {
      return null;
    }

    return intval($value);
  }

  private function get_10web_css_int($options, $gallery_type, $keys) {
    $value = $this->get_10web_option_value($options, $gallery_type, $keys);
    if ($value === null || $value === '') {
      return null;
    }

    if (is_numeric($value)) {
      return intval($value);
    }

    if (preg_match('/-?\d+/', (string) $value, $matches)) {
      return intval($matches[0]);
    }

    return null;
  }

  private function get_10web_float($options, $gallery_type, $keys) {
    $value = $this->get_10web_option_value($options, $gallery_type, $keys);
    if ($value === null || $value === '') {
      return null;
    }

    if (!is_numeric($value)) {
      return null;
    }

    return floatval($value);
  }

  private function get_10web_bool($options, $gallery_type, $keys) {
    $value = $this->get_10web_option_value($options, $gallery_type, $keys);
    if ($value === null || $value === '') {
      return null;
    }

    if (is_bool($value)) {
      return $value;
    }

    if (is_numeric($value)) {
      return intval($value) === 1;
    }

    $value = strtolower(trim((string) $value));
    if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
      return true;
    }

    if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
      return false;
    }

    return null;
  }

  private function get_10web_string($options, $gallery_type, $keys) {
    $value = $this->get_10web_option_value($options, $gallery_type, $keys);
    if ($value === null) {
      return '';
    }

    if (is_scalar($value)) {
      return trim((string) $value);
    }

    return '';
  }

  private function format_10web_color($color, $transparency = null) {
    $color = trim((string) $color);
    if ($color === '') {
      return '';
    }

    if (stripos($color, 'rgb') === 0) {
      return $color;
    }

    $hex = ltrim($color, '#');
    if (!preg_match('/^[0-9a-f]{3}([0-9a-f]{3})?$/i', $hex)) {
      return $color;
    }

    if ($transparency === null) {
      return '#' . strtoupper($hex);
    }

    $alpha = max(0, min(100, intval($transparency))) / 100;
    if ($alpha >= 1) {
      return '#' . strtoupper($hex);
    }

    if (strlen($hex) === 3) {
      $hex = preg_replace('/(.)/', '$1$1', $hex);
    }

    return sprintf(
      'rgba(%d, %d, %d, %.2f)',
      hexdec(substr($hex, 0, 2)),
      hexdec(substr($hex, 2, 2)),
      hexdec(substr($hex, 4, 2)),
      $alpha
    );
  }

  private function get_10web_option_value($options, $gallery_type, $keys) {
    if (!is_array($options)) {
      return null;
    }

    foreach ((array) $keys as $key) {
      $key = trim((string) $key);
      if ($key === '') {
        continue;
      }

      if (array_key_exists($key, $options)) {
        return $options[$key];
      }
    }

    return null;
  }

  private function assign_int(&$settings, $key, $value) {
    if ($value === null) {
      return;
    }

    $settings[$key] = intval($value);
  }

  private function assign_bool(&$settings, $key, $value) {
    if ($value === null) {
      return;
    }

    $settings[$key] = (bool) $value;
  }

  private function assign_string(&$settings, $key, $value) {
    $value = trim((string) $value);
    if ($value === '') {
      return;
    }

    $settings[$key] = $value;
  }

  private function map_direction($direction) {
    $direction = strtolower(trim((string) $direction));
    if ($direction === 'horizontal' || $direction === 'vertical') {
      return $direction;
    }

    return '';
  }

  private function map_title_position($position) {
    $position = strtolower(trim((string) $position));
    if (in_array($position, ['top', 'bottom', 'center', 'above', 'below'], true)) {
      return $position;
    }

    return '';
  }

  private function map_description_position($position) {
    $position = strtolower(trim((string) $position));
    if (in_array($position, ['above', 'below'], true)) {
      return $position;
    }
    if ($position === 'top') {
      return 'above';
    }
    if ($position === 'bottom') {
      return 'below';
    }

    return '';
  }

  private function map_alignment($alignment) {
    $alignment = strtolower(trim((string) $alignment));
    if (in_array($alignment, ['left', 'center', 'right'], true)) {
      return $alignment;
    }

    return '';
  }

  private function map_lightbox_text_position($position) {
    $position = strtolower(trim((string) $position));
    if (in_array($position, ['top', 'bottom', 'above', 'below'], true)) {
      return $position;
    }

    return '';
  }

  private function map_thumbnails_position($position) {
    $position = strtolower(trim((string) $position));
    if ($position === 'left') {
      return 'start';
    }
    if ($position === 'right') {
      return 'end';
    }
    if (in_array($position, ['top', 'bottom', 'start', 'end', 'none'], true)) {
      return $position;
    }

    return '';
  }

  private function map_watermark_position($position) {
    $position = strtolower(trim((string) $position));
    $map = [
      'top-left' => 'top-left',
      'top-center' => 'top-center',
      'top-right' => 'top-right',
      'middle-left' => 'middle-left',
      'middle-center' => 'middle-center',
      'middle-right' => 'middle-right',
      'bottom-left' => 'bottom-left',
      'bottom-center' => 'bottom-center',
      'bottom-right' => 'bottom-right',
    ];

    if (isset($map[$position])) {
      return $map[$position];
    }

    return '';
  }

  private function apply_10web_visibility_mode(&$settings, $show_key, $visibility_key, $mode) {
    $mode = strtolower(trim((string) $mode));
    if (in_array($mode, ['show'], true)) {
      $settings[$show_key] = true;
      $settings[$visibility_key] = 'alwaysShown';
      return;
    }

    if (in_array($mode, ['hover'], true)) {
      $settings[$show_key] = true;
      $settings[$visibility_key] = 'onHover';
      return;
    }

    if (in_array($mode, ['none'], true)) {
      $settings[$show_key] = false;
      return;
    }

    if ($mode !== '') {
      if ($this->visibility_mode_is_enabled($mode)) {
        $settings[$show_key] = true;
      } else {
        $settings[$show_key] = false;
        return;
      }
    }

    if (!empty($settings[$show_key])) {
      $settings[$visibility_key] = 'alwaysShown';
    }
  }

  private function get_10web_visibility($options, $gallery_type, $bool_keys, $mode_keys = []) {
    $visible = $this->get_10web_bool($options, $gallery_type, $bool_keys);
    if ($visible !== null) {
      return $visible;
    }

    $mode = $this->get_10web_string($options, $gallery_type, $mode_keys);
    if ($mode !== '') {
      return $this->visibility_mode_is_enabled($mode);
    }

    return null;
  }

  private function image_title_is_enabled($value) {
    return $this->visibility_mode_is_enabled($value);
  }

  private function visibility_mode_is_enabled($value) {
    $value = strtolower(trim((string) $value));

    if ($value === '') {
      return false;
    }

    if (in_array($value, ['none', 'hidden', 'hide', 'disable', 'disabled', 'off', '0', 'false', 'no'], true)) {
      return false;
    }

    return true;
  }

  private function map_click_action($value) {
    $value = strtolower(trim((string) $value));
    if ($value === '') {
      return '';
    }

    if (in_array($value, ['open_lightbox', 'lightbox'], true)) {
      return 'lightbox';
    }

    if (in_array($value, ['redirect_to_url', 'open_url', 'url'], true)) {
      return 'url';
    }

    if (in_array($value, ['none', 'do_nothing', 'disable'], true)) {
      return 'none';
    }

    return '';
  }

  public function get_shortcode_patterns($source_gallery_id) {
    $patterns = [];

    $source = $this->parse_source_external_id($source_gallery_id);
    $shortcode_id = !empty($source['shortcode_id']) ? intval($source['shortcode_id']) : 0;
    $gallery_id = !empty($source['gallery_id']) ? intval($source['gallery_id']) : intval($source_gallery_id);

    if ($shortcode_id > 0) {
      $sid = preg_quote((string) $shortcode_id, '/');
      $patterns[] = '/\[Best_Wordpress_Gallery\b[^\]]*\bid\s*=\s*(?:"|\')?' . $sid . '(?:"|\')?[^\]]*\]/i';
      return $patterns;
    }

    foreach ($this->get_shortcode_ids_for_gallery($gallery_id) as $shortcode_id) {
      $sid = preg_quote((string) $shortcode_id, '/');
      $patterns[] = '/\[Best_Wordpress_Gallery\b[^\]]*\bid\s*=\s*(?:"|\')?' . $sid . '(?:"|\')?[^\]]*\]/i';
    }

    $gid = preg_quote((string) $gallery_id, '/');
    $patterns[] = '/\[Best_Wordpress_Gallery\b[^\]]*\bgallery_id\s*=\s*(?:"|\')?' . $gid . '(?:"|\')?[^\]]*\]/i';

    return array_values(array_unique($patterns));
  }

  public function get_block_namespaces() {
    return ['tw'];
  }

  public function get_block_id_attributes() {
    return ['shortcode_id', 'id'];
  }

  public function prefer_shortcode_replacement() {
    return false;
  }

  public function replace_specific_gallery($source_gallery_id, $migrated_gallery_id, $replacement_shortcode, $replacement_block) {
    $source = $this->parse_source_external_id($source_gallery_id);
    $gallery_id = !empty($source['gallery_id']) ? intval($source['gallery_id']) : 0;
    if ($gallery_id <= 0) {
      return new WP_Error('reacg_migration_replace_invalid_source_id', __('Invalid 10Web gallery identifier.', 'regallery'));
    }

    $shortcode_ids = !empty($source['shortcode_id'])
      ? [intval($source['shortcode_id'])]
      : $this->get_shortcode_ids_for_gallery($gallery_id);

    $shortcode_patterns = $this->get_shortcode_patterns($source_gallery_id);
    $block_patterns = $this->get_10web_block_patterns($shortcode_ids);

    if (empty($shortcode_patterns) && empty($block_patterns)) {
      return [
        'updated_posts' => 0,
        'replaced_shortcodes' => 0,
        'replacement_shortcode' => $replacement_shortcode,
        'replacement_block' => $replacement_block,
      ];
    }

    $post_types = $this->get_replaceable_post_types();
    if (empty($post_types)) {
      return [
        'updated_posts' => 0,
        'replaced_shortcodes' => 0,
        'replacement_shortcode' => $replacement_shortcode,
        'replacement_block' => $replacement_block,
      ];
    }

    $query = new WP_Query([
      'post_type' => array_values($post_types),
      'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
      'posts_per_page' => -1,
      'fields' => 'ids',
      'no_found_rows' => true,
      'orderby' => 'ID',
      'order' => 'ASC',
    ]);

    $updated_posts = 0;
    $replaced_shortcodes = 0;

    foreach ((array) $query->posts as $post_id) {
      $post = get_post($post_id);
      if (!$post) {
        continue;
      }

      $updated_content = (string) $post->post_content;
      $local_replacements = 0;

      if ($updated_content !== '' && !empty($block_patterns)) {
        foreach ($block_patterns as $pattern) {
          $count = 0;
          $updated_content = preg_replace_callback(
            $pattern,
            function () use ($replacement_block, &$count) {
              $count++;
              return $replacement_block;
            },
            $updated_content
          );
          if ($count > 0) {
            $local_replacements += $count;
          }
        }
      }

      if ($updated_content !== '' && !empty($shortcode_patterns) && $local_replacements === 0) {
        $shortcode_replacement = $this->post_is_block_based($updated_content)
          ? $replacement_block
          : $replacement_shortcode;

        foreach ($shortcode_patterns as $pattern) {
          $count = 0;
          $updated_content = preg_replace_callback(
            $pattern,
            function () use ($shortcode_replacement, &$count) {
              $count++;
              return $shortcode_replacement;
            },
            $updated_content
          );
          if ($count > 0) {
            $local_replacements += $count;
          }
        }
      }

      if ($updated_content !== '' && $local_replacements > 0) {
        $updated_content = preg_replace_callback(
          '/<!--\s+wp:shortcode\s+-->\s*(<!--\s+wp:reacg\/gallery[\s\S]*?<!--\s+\/wp:reacg\/gallery\s+-->)\s*<!--\s+\/wp:shortcode\s+-->/i',
          function ($matches) {
            return $matches[1];
          },
          $updated_content
        );
      }

      if ($local_replacements <= 0 || $updated_content === $post->post_content) {
        continue;
      }

      wp_update_post([
        'ID' => intval($post_id),
        'post_content' => wp_slash($updated_content),
      ]);

      $updated_posts += 1;
      $replaced_shortcodes += $local_replacements;
    }

    return [
      'updated_posts' => $updated_posts,
      'replaced_shortcodes' => $replaced_shortcodes,
      'replacement_shortcode' => $replacement_shortcode,
      'replacement_block' => $replacement_block,
    ];
  }

  public function replace_post_meta_references($post_id, $source_gallery_id, $migrated_gallery_id) {
    return 0;
  }

  public function source_exists_in_posts($source_gallery_id) {
    $source = $this->parse_source_external_id($source_gallery_id);
    $patterns = $this->get_shortcode_patterns($source_gallery_id);
    $shortcode_ids = !empty($source['shortcode_id'])
      ? [intval($source['shortcode_id'])]
      : $this->get_shortcode_ids_for_gallery(!empty($source['gallery_id']) ? intval($source['gallery_id']) : 0);
    $block_patterns = $this->get_10web_block_reference_patterns($shortcode_ids);

    if (empty($patterns) && empty($block_patterns)) {
      return false;
    }

    return $this->content_reference_exists(
      ['%[Best_Wordpress_Gallery%', '%wp:tw/bwg%'],
      array_merge($patterns, $block_patterns)
    );
  }

  private function get_10web_block_patterns($shortcode_ids) {
    $patterns = [];

    foreach (array_values(array_unique(array_filter(array_map('intval', (array) $shortcode_ids)))) as $shortcode_id) {
      $sid = preg_quote((string) $shortcode_id, '/');
      $patterns[] = '/<!--\s+wp:tw\/[a-z0-9_-]+\s+\{[^}]*"shortcode_id"\s*:\s*(?:"' . $sid . '"|' . $sid . ')[^}]*\}[\s\S]*?(?:<!--\s+\/wp:tw\/[a-z0-9_-]+\s+-->|\s*\/-->)/i';
    }

    return $patterns;
  }

  private function get_10web_block_reference_patterns($shortcode_ids) {
    $patterns = [];

    foreach (array_values(array_unique(array_filter(array_map('intval', (array) $shortcode_ids)))) as $shortcode_id) {
      $sid = preg_quote((string) $shortcode_id, '/');
      $patterns[] = '/wp:tw\/[a-z0-9_-]+[^\}]*"shortcode_id"\s*:\s*(?:"' . $sid . '"|' . $sid . ')/i';
    }

    return $patterns;
  }

  private function get_replaceable_post_types() {
    $post_types = get_post_types([], 'names');
    if (empty($post_types)) {
      return [];
    }

    $excluded = [
      'attachment',
      'revision',
      'nav_menu_item',
      'custom_css',
      'customize_changeset',
      'oembed_cache',
      'wp_block',
      'wp_template',
      'wp_template_part',
      'wp_global_styles',
      'wp_navigation',
      REACG_CUSTOM_POST_TYPE,
    ];

    return array_values(array_diff($post_types, $excluded));
  }

  private function post_is_block_based($content) {
    $stripped = preg_replace(
      '/<!--\s+wp:shortcode\s+-->[\s\S]*?<!--\s+\/wp:shortcode\s+-->/i',
      '',
      (string) $content
    );

    return (bool) preg_match('/<!--\s+wp:[a-zA-Z]/', $stripped);
  }

  private function required_tables_exist() {
    if ($this->tables_checked) {
      return $this->tables_exist;
    }

    global $wpdb;

    $gallery_table = $this->table_name('bwg_gallery');
    $image_table = $this->table_name('bwg_image');

    $gallery_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $gallery_table));
    $image_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $image_table));

    $this->tables_exist = !empty($gallery_exists) && !empty($image_exists);
    $this->tables_checked = true;

    return $this->tables_exist;
  }

  private function table_name($name) {
    global $wpdb;

    return $wpdb->prefix . $name;
  }

  private function parse_source_external_id($external_id) {
    $value = trim((string) $external_id);
    if ($value === '') {
      return [
        'gallery_id' => 0,
        'shortcode_id' => 0,
      ];
    }

    if (preg_match('/^shortcode:(\d+):(\d+)$/', $value, $matches)) {
      return [
        'gallery_id' => intval($matches[2]),
        'shortcode_id' => intval($matches[1]),
      ];
    }

    return [
      'gallery_id' => intval($value),
      'shortcode_id' => 0,
    ];
  }

  private function get_used_shortcode_items($search = '') {
    global $wpdb;

    $table = $this->table_name('bwg_shortcode');
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if (empty($table_exists)) {
      return [];
    }

    // Collect unique shortcode IDs from published post content only.
    $contents = $wpdb->get_col(
      "SELECT post_content FROM {$wpdb->posts}
       WHERE post_status NOT IN ('auto-draft','trash','inherit')
         AND post_content LIKE '%[Best_Wordpress_Gallery%'
       LIMIT 500"
    );

    if (empty($contents)) {
      return [];
    }

    $shortcode_ids = [];
    foreach ((array) $contents as $content) {
      if (!is_string($content) || $content === '') {
        continue;
      }

      if (!preg_match_all('/\[Best_Wordpress_Gallery\b([^\]]*)\]/i', $content, $matches)) {
        continue;
      }

      foreach ((array) $matches[1] as $attr_string) {
        $attrs = $this->parse_shortcode_attributes($attr_string);
        if (empty($attrs['id']) || !is_numeric($attrs['id'])) {
          continue;
        }

        $shortcode_id = intval($attrs['id']);
        if ($shortcode_id > 0) {
          $shortcode_ids[$shortcode_id] = true;
        }
      }
    }

    if (empty($shortcode_ids)) {
      return [];
    }

    $image_table = $this->table_name('bwg_image');
    $items = [];

    foreach (array_keys($shortcode_ids) as $shortcode_id) {
      $shortcode_options = $this->get_shortcode_options_by_id($shortcode_id);
      $gallery_id = !empty($shortcode_options['gallery_id']) ? intval($shortcode_options['gallery_id']) : 0;
      if ($gallery_id <= 0) {
        continue;
      }

      $gallery_title = $wpdb->get_var($wpdb->prepare(
        'SELECT `name` FROM `' . esc_sql($this->table_name('bwg_gallery')) . '` WHERE `id` = %d',
        $gallery_id
      ));

      $images_count = intval($wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM `' . esc_sql($image_table) . '` WHERE `gallery_id` = %d AND `published` = 1',
        $gallery_id
      )));

      $title = !empty($gallery_title) ? sanitize_text_field($gallery_title) : __('(no title)', 'regallery');
      $title .= ' ' . sprintf(__('(Shortcode #%d)', 'regallery'), $shortcode_id);

      $items[] = [
        'id' => 'shortcode:' . $shortcode_id . ':' . $gallery_id,
        'title' => $title,
        'image_count' => $images_count,
      ];
    }

    if ($search !== '') {
      $items = array_values(array_filter($items, function ($item) use ($search) {
        $title = !empty($item['title']) ? (string) $item['title'] : '';
        return stripos($title, $search) !== false;
      }));
    }

    usort($items, function ($a, $b) {
      return strnatcasecmp(
        !empty($a['title']) ? (string) $a['title'] : '',
        !empty($b['title']) ? (string) $b['title'] : ''
      );
    });

    return $items;
  }

  private function get_shortcode_options_by_id($shortcode_id) {
    global $wpdb;

    $shortcode_id = intval($shortcode_id);
    if ($shortcode_id <= 0) {
      return [];
    }

    $table = $this->table_name('bwg_shortcode');
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if (empty($table_exists)) {
      return [];
    }

    $tagtext = $wpdb->get_var($wpdb->prepare(
      'SELECT `tagtext` FROM `' . esc_sql($table) . '` WHERE `id` = %d',
      $shortcode_id
    ));

    if (!is_string($tagtext) || $tagtext === '') {
      return [];
    }

    return $this->parse_shortcode_attributes($tagtext);
  }

  private function parse_shortcode_attributes($shortcode_text) {
    $shortcode_text = (string) $shortcode_text;
    if ($shortcode_text === '') {
      return [];
    }

    $attributes = [];
    if (!preg_match_all('/([a-zA-Z_][a-zA-Z0-9_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/i', $shortcode_text, $matches, PREG_SET_ORDER)) {
      return $attributes;
    }

    foreach ((array) $matches as $match) {
      if (empty($match[1])) {
        continue;
      }

      $key = strtolower(trim((string) $match[1]));
      if ($key === '') {
        continue;
      }

      $value = '';
      if (isset($match[2]) && $match[2] !== '') {
        $value = (string) $match[2];
      } elseif (isset($match[3]) && $match[3] !== '') {
        $value = (string) $match[3];
      } elseif (isset($match[4])) {
        $value = (string) $match[4];
      }

      $attributes[$key] = trim($value);
    }

    return $attributes;
  }

  private function get_shortcode_ids_for_gallery($source_gallery_id) {
    global $wpdb;

    $gallery_id = trim((string) $source_gallery_id);
    if ($gallery_id === '') {
      return [];
    }

    $table = $this->table_name('bwg_shortcode');
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if (empty($table_exists)) {
      return [];
    }

    $needle = '%gallery_id="' . $wpdb->esc_like($gallery_id) . '"%';
    $rows = $wpdb->get_col($wpdb->prepare(
      'SELECT `id` FROM `' . esc_sql($table) . '` WHERE `tagtext` LIKE %s',
      $needle
    ));

    return array_values(array_filter(array_map('intval', (array) $rows)));
  }

  private function normalize_image_url($image_url) {
    $image_url = trim((string) $image_url);
    if ($image_url === '') {
      return '';
    }

    $locations = $this->get_bwg_locations();

    if (preg_match('#^https?://#i', $image_url)) {
      return esc_url_raw($image_url);
    }

    if ($locations['bwg_upload_url'] !== '' && strpos($image_url, '/wp-content/uploads/') === false && strpos($image_url, 'wp-content/uploads/') !== 0) {
      return esc_url_raw(untrailingslashit($locations['bwg_upload_url']) . '/' . ltrim($image_url, '/'));
    }

    if ($locations['wp_upload_baseurl'] !== '' && strpos($image_url, '/wp-content/uploads/') !== false) {
      $parts = explode('/wp-content/uploads/', $image_url, 2);
      if (!empty($parts[1])) {
        return esc_url_raw(untrailingslashit($locations['wp_upload_baseurl']) . '/' . ltrim($parts[1], '/'));
      }
    }

    if ($locations['wp_upload_baseurl'] !== '' && strpos($image_url, 'wp-content/uploads/') === 0) {
      $relative = substr($image_url, strlen('wp-content/uploads/'));
      return esc_url_raw(untrailingslashit($locations['wp_upload_baseurl']) . '/' . ltrim($relative, '/'));
    }

    if ($locations['wp_upload_baseurl'] !== '' && strpos($image_url, 'uploads/') === 0) {
      $relative = substr($image_url, strlen('uploads/'));
      return esc_url_raw(untrailingslashit($locations['wp_upload_baseurl']) . '/' . ltrim($relative, '/'));
    }

    if (strpos($image_url, '/wp-content/') !== false || strpos($image_url, 'wp-content/') === 0) {
      return esc_url_raw(home_url('/' . ltrim($image_url, '/')));
    }

    if (strpos($image_url, 'uploads/') === 0) {
      return esc_url_raw(home_url('/' . ltrim($image_url, '/')));
    }

    $upload_dir = wp_get_upload_dir();
    $base_url = !empty($upload_dir['baseurl']) ? untrailingslashit($upload_dir['baseurl']) : '';
    if ($base_url === '') {
      return '';
    }

    return esc_url_raw($base_url . '/' . ltrim($image_url, '/'));
  }

  private function resolve_attachment_from_10web_image($image_row, $url) {
    $url = trim((string) $url);
    if ($url !== '') {
      $existing_by_url = attachment_url_to_postid($url);
      if ($existing_by_url > 0) {
        return intval($existing_by_url);
      }
    }

    $paths = $this->collect_candidate_local_paths($image_row);
    foreach ($paths as $path) {
      $attachment_id = $this->import_local_file_as_attachment($path, $url);
      if ($attachment_id > 0) {
        return intval($attachment_id);
      }
    }

    // Final fallback for valid remote/local URLs that are reachable via HTTP.
    if ($url !== '' && preg_match('#^https?://#i', $url)) {
      $attachment_id = $this->import_remote_url_as_attachment($url);
      if ($attachment_id > 0) {
        return intval($attachment_id);
      }
    }

    return 0;
  }

  private function collect_candidate_local_paths($image_row) {
    $locations = $this->get_bwg_locations();

    $raw_paths = [];
    foreach (['image_url', 'thumb_url'] as $key) {
      if (!empty($image_row[$key]) && is_string($image_row[$key])) {
        $raw_paths[] = trim($image_row[$key]);
      }
    }

    $candidates = [];
    foreach ($raw_paths as $raw_path) {
      if ($raw_path === '') {
        continue;
      }

      $decoded_path = rawurldecode($raw_path);
      $path_variants = array_values(array_unique(array_filter([$raw_path, $decoded_path])));

      foreach ($path_variants as $variant) {
        $variant = trim((string) $variant);
        if ($variant === '') {
          continue;
        }

        if (preg_match('#^https?://#i', $variant)) {
          $mapped = $this->map_url_to_local_relative_paths($variant, $locations);
          foreach ($mapped as $mapped_relative) {
            $mapped_relative = ltrim((string) $mapped_relative, '/');
            if ($mapped_relative === '') {
              continue;
            }
            $path_variants[] = $mapped_relative;
          }
          continue;
        }

        if ($variant[0] === DIRECTORY_SEPARATOR) {
          $candidates[] = $variant;
        }

        $normalized = ltrim($variant, '/');
        if ($normalized === '') {
          continue;
        }

        $relative_variants = [$normalized];

        if (strpos($normalized, 'wp-content/uploads/') === 0) {
          $relative_variants[] = substr($normalized, strlen('wp-content/uploads/'));
        }

        if (strpos($normalized, 'uploads/') === 0) {
          $relative_variants[] = substr($normalized, strlen('uploads/'));
        }

        // 10Web frequently stores thumbs while original file lives under /.original/.
        if (strpos($normalized, '/thumb/') !== false) {
          $relative_variants[] = str_replace('/thumb/', '/.original/', $normalized);
        }

        foreach ($relative_variants as $relative_path) {
          $relative_path = trim((string) $relative_path);
          if ($relative_path === '') {
            continue;
          }

          $candidates[] = ABSPATH . ltrim($relative_path, '/');
          $candidates[] = WP_CONTENT_DIR . '/' . ltrim($relative_path, '/');
          $candidates = array_merge($candidates, $this->build_base_relative_candidates(WP_CONTENT_DIR, $relative_path));

          if ($locations['wp_upload_basedir'] !== '') {
            $candidates = array_merge($candidates, $this->build_base_relative_candidates($locations['wp_upload_basedir'], $relative_path));
          }

          if ($locations['bwg_upload_dir'] !== '') {
            $candidates = array_merge($candidates, $this->build_base_relative_candidates($locations['bwg_upload_dir'], $relative_path));
          }
        }
      }
    }

    // Last-resort: locate by filename under known 10Web/uploads directories.
    foreach ($raw_paths as $raw_path) {
      $basename = wp_basename((string) $raw_path);
      $basename = trim((string) $basename);
      if ($basename === '' || $basename === '.' || $basename === '..') {
        continue;
      }

      foreach (['bwg_upload_dir', 'wp_upload_basedir'] as $dir_key) {
        if (empty($locations[$dir_key]) || !is_string($locations[$dir_key])) {
          continue;
        }

        $found = $this->find_file_by_basename($locations[$dir_key], $basename);
        if (!empty($found)) {
          $candidates[] = $found;
        }
      }
    }

    $unique = [];
    foreach ($candidates as $candidate) {
      $candidate = trim((string) $candidate);
      if ($candidate === '') {
        continue;
      }

      $normalized_candidate = wp_normalize_path($candidate);
      if (isset($unique[$normalized_candidate])) {
        continue;
      }

      if (is_file($candidate) && is_readable($candidate)) {
        $unique[$normalized_candidate] = $candidate;
      }
    }

    return array_values($unique);
  }

  private function find_file_by_basename($base_dir, $basename) {
    $base_dir = trim((string) $base_dir);
    $basename = trim((string) $basename);

    if ($base_dir === '' || $basename === '' || !is_dir($base_dir)) {
      return '';
    }

    $base_key = wp_normalize_path(untrailingslashit($base_dir));
    if (!isset($this->basename_index_built_for[$base_key])) {
      $this->build_basename_index($base_dir);
      $this->basename_index_built_for[$base_key] = true;
    }

    $key = strtolower($basename);
    if (!empty($this->basename_index[$base_key][$key])) {
      $path = $this->basename_index[$base_key][$key];
      if (is_file($path) && is_readable($path)) {
        return $path;
      }
    }

    return '';
  }

  private function build_basename_index($base_dir) {
    $base_dir = trim((string) $base_dir);
    if ($base_dir === '' || !is_dir($base_dir)) {
      return;
    }

    $base_key = wp_normalize_path(untrailingslashit($base_dir));
    if (!isset($this->basename_index[$base_key])) {
      $this->basename_index[$base_key] = [];
    }

    $roots = [$base_dir];
    $photo_gallery_subdir = untrailingslashit($base_dir) . '/photo-gallery';
    if (is_dir($photo_gallery_subdir)) {
      $roots[] = $photo_gallery_subdir;
    }

    foreach ($roots as $root_dir) {
      try {
        $iterator = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($root_dir, FilesystemIterator::SKIP_DOTS),
          RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file_info) {
          if (!$file_info->isFile()) {
            continue;
          }

          $file_path = $file_info->getPathname();
          if (!is_readable($file_path)) {
            continue;
          }

          $name_key = strtolower($file_info->getFilename());
          if (!isset($this->basename_index[$base_key][$name_key])) {
            $this->basename_index[$base_key][$name_key] = $file_path;
          }
        }
      } catch (Exception $e) {
        // Ignore indexing errors and keep other strategies.
      }
    }
  }

  private function build_base_relative_candidates($base_dir, $relative_path) {
    $base_dir = trim((string) $base_dir);
    $relative_path = ltrim(trim((string) $relative_path), '/');

    if ($base_dir === '' || $relative_path === '') {
      return [];
    }

    $base_dir = untrailingslashit($base_dir);
    $candidates = [
      $base_dir . '/' . $relative_path,
    ];

    // Avoid duplicated segments like /photo-gallery/photo-gallery/...
    $base_tail = basename($base_dir);
    if ($base_tail !== '' && strpos($relative_path, $base_tail . '/') === 0) {
      $candidates[] = $base_dir . '/' . substr($relative_path, strlen($base_tail . '/'));
    }

    // Handle wp-content prefix duplication for content-based paths.
    if (strpos($relative_path, 'wp-content/') === 0) {
      $candidates[] = $base_dir . '/' . substr($relative_path, strlen('wp-content/'));
    }

    // Handle uploads prefix duplication for upload-based paths.
    if (strpos($relative_path, 'uploads/') === 0) {
      $candidates[] = $base_dir . '/' . substr($relative_path, strlen('uploads/'));
    }

    return array_values(array_unique(array_filter($candidates)));
  }

  private function map_url_to_local_relative_paths($url, $locations) {
    $results = [];
    $url = trim((string) $url);
    if ($url === '') {
      return $results;
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
      return $results;
    }

    $path = ltrim(rawurldecode($path), '/');
    if ($path !== '') {
      $results[] = $path;
    }

    if (strpos($path, 'wp-content/uploads/') === 0) {
      $results[] = substr($path, strlen('wp-content/uploads/'));
    }

    $base_urls = [];
    foreach (['bwg_upload_url', 'wp_upload_baseurl'] as $key) {
      if (!empty($locations[$key]) && is_string($locations[$key])) {
        $base_urls[] = untrailingslashit($locations[$key]);
      }
    }

    $base_urls = array_values(array_unique($base_urls));
    foreach ($base_urls as $base_url) {
      if ($base_url === '') {
        continue;
      }

      if (stripos($url, $base_url . '/') !== 0 && strtolower($url) !== strtolower($base_url)) {
        continue;
      }

      $relative = ltrim(substr($url, strlen($base_url)), '/');
      $relative = rawurldecode($relative);
      if ($relative !== '') {
        $results[] = $relative;
      }
    }

    return array_values(array_unique(array_filter($results)));
  }

  private function import_local_file_as_attachment($file_path, $source_url = '') {
    $file_path = (string) $file_path;
    if ($file_path === '' || !is_file($file_path) || !is_readable($file_path)) {
      return 0;
    }

    $uploads = wp_get_upload_dir();
    if (!empty($uploads['error'])) {
      return 0;
    }

    $source_url = trim((string) $source_url);
    if ($source_url !== '') {
      $existing_by_url = attachment_url_to_postid($source_url);
      if ($existing_by_url > 0) {
        return intval($existing_by_url);
      }
    }

    $normalized_file = wp_normalize_path($file_path);
    $normalized_base = wp_normalize_path(untrailingslashit($uploads['basedir']));
    $relative = '';

    if ($normalized_base !== '' && strpos($normalized_file, $normalized_base . '/') === 0) {
      $relative = ltrim(substr($normalized_file, strlen($normalized_base)), '/');
      $existing_by_relative = $this->find_existing_attachment_by_relative_path($relative);
      if ($existing_by_relative > 0) {
        return intval($existing_by_relative);
      }
    } else {
      $destination_dir = !empty($uploads['path']) ? $uploads['path'] : $uploads['basedir'];
      if ($destination_dir === '') {
        return 0;
      }

      wp_mkdir_p($destination_dir);
      $filename = wp_basename($file_path);
      $unique_name = wp_unique_filename($destination_dir, $filename);
      $destination_path = trailingslashit($destination_dir) . $unique_name;

      if (!@copy($file_path, $destination_path)) {
        return 0;
      }

      $file_path = $destination_path;
      $normalized_file = wp_normalize_path($file_path);

      if ($normalized_base !== '' && strpos($normalized_file, $normalized_base . '/') === 0) {
        $relative = ltrim(substr($normalized_file, strlen($normalized_base)), '/');
      }
    }

    $filetype = wp_check_filetype(wp_basename($file_path), null);
    $mime_type = !empty($filetype['type']) ? $filetype['type'] : 'image/jpeg';
    $title = sanitize_text_field(pathinfo(wp_basename($file_path), PATHINFO_FILENAME));
    if ($title === '') {
      $title = __('Imported 10Web image', 'regallery');
    }

    $attachment_data = [
      'post_mime_type' => $mime_type,
      'post_title' => $title,
      'post_content' => '',
      'post_status' => 'inherit',
    ];

    if ($relative !== '' && !empty($uploads['baseurl'])) {
      $attachment_data['guid'] = trailingslashit($uploads['baseurl']) . ltrim($relative, '/');
    }

    $attachment_id = wp_insert_attachment($attachment_data, $file_path, 0, true);
    if (is_wp_error($attachment_id) || intval($attachment_id) <= 0) {
      return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata(intval($attachment_id), $file_path);
    if (!empty($metadata) && !is_wp_error($metadata)) {
      wp_update_attachment_metadata(intval($attachment_id), $metadata);
    }

    if ($relative !== '') {
      $this->attachment_cache[$relative] = intval($attachment_id);
    }

    return intval($attachment_id);
  }

  private function import_remote_url_as_attachment($url) {
    $url = trim((string) $url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
      return 0;
    }

    $existing = attachment_url_to_postid($url);
    if ($existing > 0) {
      return intval($existing);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp_file = download_url($url);
    if (is_wp_error($tmp_file)) {
      return 0;
    }

    $path = parse_url($url, PHP_URL_PATH);
    $file_name = $path ? wp_basename($path) : '';
    if ($file_name === '') {
      $file_name = 'reacg-10web-' . time() . '.jpg';
    }

    $file_array = [
      'name' => sanitize_file_name($file_name),
      'tmp_name' => $tmp_file,
    ];

    $attachment_id = media_handle_sideload($file_array, 0, '');
    if (is_wp_error($attachment_id)) {
      @unlink($tmp_file);
      return 0;
    }

    return intval($attachment_id);
  }

  private function find_existing_attachment_by_relative_path($relative_path) {
    $relative_path = ltrim(trim((string) $relative_path), '/');
    if ($relative_path === '') {
      return 0;
    }

    if (isset($this->attachment_cache[$relative_path])) {
      return intval($this->attachment_cache[$relative_path]);
    }

    $posts = get_posts([
      'post_type' => 'attachment',
      'post_status' => 'inherit',
      'posts_per_page' => 1,
      'fields' => 'ids',
      'meta_key' => '_wp_attached_file',
      'meta_value' => $relative_path,
      'suppress_filters' => true,
    ]);

    $attachment_id = !empty($posts[0]) ? intval($posts[0]) : 0;
    $this->attachment_cache[$relative_path] = $attachment_id;

    return $attachment_id;
  }

  private function get_bwg_locations() {
    if (is_array($this->bwg_locations)) {
      return $this->bwg_locations;
    }

    $upload_dir = wp_get_upload_dir();
    $locations = [
      'wp_upload_basedir' => !empty($upload_dir['basedir']) ? untrailingslashit($upload_dir['basedir']) : '',
      'wp_upload_baseurl' => !empty($upload_dir['baseurl']) ? untrailingslashit($upload_dir['baseurl']) : '',
      'bwg_upload_dir' => !empty($upload_dir['basedir']) ? untrailingslashit($upload_dir['basedir']) : '',
      'bwg_upload_url' => !empty($upload_dir['baseurl']) ? untrailingslashit($upload_dir['baseurl']) : '',
    ];

    $bwg_options_raw = get_option('wd_bwg_options');
    $bwg_options = null;

    if (is_string($bwg_options_raw)) {
      $bwg_options = $this->decode_json_or_safe_unserialize_array($bwg_options_raw);
    } elseif (is_array($bwg_options_raw)) {
      $bwg_options = $bwg_options_raw;
    } elseif (is_object($bwg_options_raw)) {
      $bwg_options = (array) $bwg_options_raw;
    }

    if (is_array($bwg_options)) {
      if (!empty($bwg_options['upload_dir']) && is_string($bwg_options['upload_dir'])) {
        $locations['bwg_upload_dir'] = untrailingslashit($bwg_options['upload_dir']);
      }

      if (!empty($bwg_options['upload_url']) && is_string($bwg_options['upload_url'])) {
        $locations['bwg_upload_url'] = untrailingslashit($bwg_options['upload_url']);
      }
    }

    $this->bwg_locations = $locations;

    return $this->bwg_locations;
  }

  private function get_bwg_options() {
    if (is_array($this->bwg_options)) {
      return $this->bwg_options;
    }

    $this->bwg_options = [];
    $raw = get_option('wd_bwg_options');

    if (is_string($raw)) {
      $this->bwg_options = $this->decode_json_or_safe_unserialize_array($raw);
    } elseif (is_array($raw)) {
      $this->bwg_options = $raw;
    } elseif (is_object($raw)) {
      $this->bwg_options = (array) $raw;
    }

    return $this->bwg_options;
  }

  private function resolve_theme_id($shortcode_options = []) {
    if (is_array($shortcode_options) && !empty($shortcode_options['theme_id'])) {
      $theme_id = intval($shortcode_options['theme_id']);
      if ($theme_id > 0) {
        return $theme_id;
      }
    }

    global $wpdb;

    $theme_table = $this->table_name('bwg_theme');
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $theme_table));
    if (empty($table_exists)) {
      return 0;
    }

    return intval($wpdb->get_var(
      'SELECT `id` FROM `' . esc_sql($theme_table) . '` WHERE `default_theme` = 1 ORDER BY `id` ASC LIMIT 1'
    ));
  }

  private function get_bwg_theme_options($theme_id) {
    global $wpdb;

    $theme_id = intval($theme_id);
    if ($theme_id <= 0) {
      return [];
    }

    $theme_table = $this->table_name('bwg_theme');
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $theme_table));
    if (empty($table_exists)) {
      return [];
    }

    $theme_row = $wpdb->get_row($wpdb->prepare(
      'SELECT `options` FROM `' . esc_sql($theme_table) . '` WHERE `id` = %d',
      $theme_id
    ), ARRAY_A);

    if (empty($theme_row) || empty($theme_row['options'])) {
      return [];
    }

    $raw_options = (string) $theme_row['options'];
    if ($raw_options === '') {
      return [];
    }

    $theme_options = [];
    $theme_options = $this->decode_json_or_safe_unserialize_array($raw_options);

    return $theme_options;
  }

  private function decode_json_or_safe_unserialize_array($value) {
    if (!is_string($value) || $value === '') {
      return [];
    }

    $decoded_json = json_decode($value, true);
    if (is_array($decoded_json)) {
      return $decoded_json;
    }

    return $this->safe_unserialize_array($value);
  }

  private function safe_unserialize_array($value) {
    if (!is_string($value) || $value === '' || !is_serialized($value)) {
      return [];
    }

    $unserialized = @unserialize(trim($value), ['allowed_classes' => false]);
    if (is_array($unserialized)) {
      return $unserialized;
    }

    return [];
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
        if (preg_match($regex, (string) $content)) {
          return true;
        }
      }
    }

    return false;
  }
}
