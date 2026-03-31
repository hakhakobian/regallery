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
    if (post_type_exists('bwg_gallery') || shortcode_exists('Best_Wordpress_Gallery')) {
      return true;
    }

    return $this->required_tables_exist();
  }

  public function list_galleries($args = []) {
    global $wpdb;

    if (!$this->required_tables_exist()) {
      return new WP_Error('reacg_migration_unavailable', __('10Web Photo Gallery tables were not found.', 'regallery'));
    }

    $page = !empty($args['page']) ? max(1, intval($args['page'])) : 1;
    $per_page = !empty($args['per_page']) ? max(1, intval($args['per_page'])) : 50;
    $search = !empty($args['search']) ? sanitize_text_field($args['search']) : '';

    $gallery_table = $this->table_name('bwg_gallery');
    $image_table = $this->table_name('bwg_image');

    $where = ' WHERE 1=1 ';
    $params = [];

    if ($search !== '') {
      $where .= ' AND `name` LIKE %s ';
      $params[] = '%' . $wpdb->esc_like($search) . '%';
    }

    $total_sql = 'SELECT COUNT(*) FROM `' . esc_sql($gallery_table) . '`' . $where;
    $total = !empty($params)
      ? intval($wpdb->get_var($wpdb->prepare($total_sql, $params)))
      : intval($wpdb->get_var($total_sql));

    $offset = ($page - 1) * $per_page;
    $rows_sql = 'SELECT `id`, `name` FROM `' . esc_sql($gallery_table) . '`' . $where . ' ORDER BY `name` ASC LIMIT %d OFFSET %d';
    $rows_params = array_merge($params, [$per_page, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($rows_sql, $rows_params), ARRAY_A);

    $items = [];
    foreach ((array) $rows as $row) {
      $gallery_id = isset($row['id']) ? intval($row['id']) : 0;
      if ($gallery_id <= 0) {
        continue;
      }

      $images_count = intval($wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM `' . esc_sql($image_table) . '` WHERE `gallery_id` = %d AND `published` = 1',
        $gallery_id
      )));

      $items[] = [
        'id' => (string) $gallery_id,
        'title' => !empty($row['name']) ? sanitize_text_field($row['name']) : __('(no title)', 'regallery'),
        'image_count' => $images_count,
      ];
    }

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $per_page,
    ];
  }

  public function get_gallery($external_id) {
    global $wpdb;

    if (!$this->required_tables_exist()) {
      return new WP_Error('reacg_migration_unavailable', __('10Web Photo Gallery tables were not found.', 'regallery'));
    }

    $gallery_id = intval($external_id);
    if ($gallery_id <= 0) {
      return new WP_Error('reacg_migration_invalid_gallery', __('Invalid 10Web gallery.', 'regallery'));
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

    return [
      'id' => (string) $gallery_id,
      'title' => !empty($gallery_row['name']) ? sanitize_text_field($gallery_row['name']) : __('Imported 10Web Gallery', 'regallery'),
      'items' => $items,
      'settings' => $this->map_settings($gallery_id),
    ];
  }

  private function map_settings($gallery_id) {
    $options = $this->get_bwg_options();
    if (empty($options)) {
      return [];
    }

    $gallery_type = $this->get_default_gallery_type();

    // If global default is not available, try shortcode-level gallery type.
    if ($gallery_type === '') {
      $gallery_type = $this->get_gallery_type_from_shortcode($gallery_id);
    }

    $mapped_type = $this->map_10web_type_to_regallery($gallery_type);
    if ($mapped_type === '') {
      return [];
    }

    $settings = [
      'type' => $mapped_type,
    ];

    $view_settings = $this->build_view_settings($gallery_type, $mapped_type, $options);
    if (!empty($view_settings)) {
      $settings = $view_settings;
    }

    $general_settings = $this->build_general_settings($gallery_type, $options);
    if (!empty($general_settings)) {
      $settings['general'] = $general_settings;
    }

    return $settings;
  }

  private function get_default_gallery_type() {
    $options = $this->get_bwg_options();
    if (empty($options)) {
      return '';
    }

    // Different plugin versions can use different keys for the global default view.
    $candidate_keys = [
      'gallery_type',
      'default_gallery_type',
      'gallery_default_view',
      'gallery_view_type',
      'view_type',
    ];

    foreach ($candidate_keys as $key) {
      if (!empty($options[$key]) && is_string($options[$key])) {
        return strtolower(trim($options[$key]));
      }
    }

    return '';
  }

  private function get_gallery_type_from_shortcode($gallery_id) {
    global $wpdb;

    $gallery_id = intval($gallery_id);
    if ($gallery_id <= 0) {
      return '';
    }

    $table = $this->table_name('bwg_shortcode');
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if (empty($table_exists)) {
      return '';
    }

    $needle = '%gallery_id="' . $wpdb->esc_like((string) $gallery_id) . '"%';
    $tagtexts = $wpdb->get_col($wpdb->prepare(
      'SELECT `tagtext` FROM `' . esc_sql($table) . '` WHERE `tagtext` LIKE %s ORDER BY `id` DESC LIMIT 5',
      $needle
    ));

    foreach ((array) $tagtexts as $tagtext) {
      $type = $this->extract_shortcode_attribute((string) $tagtext, 'gallery_type');
      if ($type !== '') {
        return strtolower($type);
      }
    }

    return '';
  }

  private function extract_shortcode_attribute($tagtext, $attribute) {
    $tagtext = (string) $tagtext;
    $attribute = trim((string) $attribute);
    if ($tagtext === '' || $attribute === '') {
      return '';
    }

    if (preg_match('/\b' . preg_quote($attribute, '/') . '\s*=\s*"([^"]+)"/i', $tagtext, $matches)) {
      return trim((string) $matches[1]);
    }

    if (preg_match('/\b' . preg_quote($attribute, '/') . '\s*=\s*\'([^\']+)\'/i', $tagtext, $matches)) {
      return trim((string) $matches[1]);
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
      case 'album_compact_preview':
      case 'album_masonry_preview':
      case 'album_extended_preview':
        return 'mosaic';
      default:
        return '';
    }
  }

  private function build_view_settings($gallery_type, $mapped_type, $options) {
    $gallery_type = strtolower(trim((string) $gallery_type));
    $settings = [
      'thumbnails',
      'masonry',
      'mosaic',
      'slideshow',
      'carousel',
      'blog',
    ];

    $this->assign_int($settings['thumbnails'], 'columns', $this->get_10web_int($options, $gallery_type, ['image_column_number']));
    $this->assign_int($settings['thumbnails'], 'width', $this->get_10web_int($options, $gallery_type, ['thumb_width']));
    $this->assign_int($settings['thumbnails'], 'height', $this->get_10web_int($options, $gallery_type, ['thumb_height']));
    $this->assign_bool($settings['thumbnails'], 'showTitle', $this->get_10web_bool($options, $gallery_type, ['showthumbs_name', 'show_image_title']));
    $this->apply_10web_title_visibility_mode($settings['thumbnails'], $this->get_10web_string($options, $gallery_type, ['image_title_show_hover']));
    $this->assign_bool($settings['thumbnails'], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['show_thumb_description', 'show_image_description']));
    $this->assign_bool($settings['thumbnails'], 'showCaption', $this->get_10web_visibility($options, $gallery_type, ['show_image_title'], ['image_title']));

    $this->assign_int($settings['masonry'], 'columns', $this->get_10web_int($options, $gallery_type, ['masonry_image_column_number']));
    $this->assign_int($settings['masonry'], 'gap', $this->get_10web_int($options, $gallery_type, ['masonry_thumb_size']));
    $this->assign_bool($settings['masonry'], 'showTitle', $this->get_10web_bool($options, $gallery_type, ['masonry_show_gallery_title', 'masonry_show_image_title']));
    $this->assign_bool($settings['masonry'], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['masonry_show_gallery_description', 'masonry_show_image_description']));
    $this->assign_bool($settings['masonry'], 'showCaption', $this->get_10web_visibility($options, $gallery_type, ['masonry_show_image_title'], ['masonry_image_title']));
    
    $this->assign_int($settings['mosaic'], 'rowHeight', $this->get_10web_int($options, $gallery_type, ['mosaic_thumb_size']));
    $this->assign_string($settings['mosaic'], 'direction', $this->map_direction($this->get_10web_string($options, $gallery_type, ['mosaic'])));
    $this->assign_bool($settings['mosaic'], 'showTitle', $this->get_10web_bool($options, $gallery_type, ['mosaic_show_gallery_title', 'mosaic_show_image_title']));
    $this->assign_bool($settings['mosaic'], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['mosaic_show_gallery_description', 'mosaic_show_image_description']));
    $this->assign_bool($settings['mosaic'], 'showCaption', $this->get_10web_visibility($options, $gallery_type, ['mosaic_show_image_title'], ['mosaic_image_title_show_hover', 'mosaic_image_title']));
        
    $this->assign_int($settings['slideshow'], 'width', $this->get_10web_int($options, $gallery_type, ['slideshow_width']));
    $this->assign_int($settings['slideshow'], 'height', $this->get_10web_int($options, $gallery_type, ['slideshow_height']));
    $this->assign_bool($settings['slideshow'], 'autoplay', $this->get_10web_bool($options, $gallery_type, ['slideshow_enable_autoplay']));
    $interval_seconds = $this->get_10web_float($options, $gallery_type, ['slideshow_interval']);
    if ($interval_seconds !== null && $interval_seconds > 0) {
      $settings['slideshow']['slideDuration'] = max(300, intval(round($interval_seconds * 1000)));
    }
    $this->assign_bool($settings['slideshow'], 'showTitle', $this->get_10web_bool($options, $gallery_type, ['slideshow_enable_title', 'slideshow_show_image_title']));
    $this->assign_bool($settings['slideshow'], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['slideshow_enable_description', 'slideshow_show_image_description']));

    $this->assign_int($settings['carousel'], 'width', $this->get_10web_int($options, $gallery_type, ['carousel_width']));
    $this->assign_int($settings['carousel'], 'height', $this->get_10web_int($options, $gallery_type, ['carousel_height']));
    $this->assign_int($settings['carousel'], 'imagesCount', $this->get_10web_int($options, $gallery_type, ['carousel_image_column_number']));
    $this->assign_bool($settings['carousel'], 'autoplay', $this->get_10web_bool($options, $gallery_type, ['carousel_enable_autoplay']));
    $carousel_interval = $this->get_10web_float($options, $gallery_type, ['carousel_interval']);
    if ($carousel_interval !== null && $carousel_interval > 0) {
      $settings['carousel']['slideDuration'] = max(300, intval(round($carousel_interval * 1000)));
    }
    $this->assign_bool($settings['carousel'], 'showTitle', $this->get_10web_bool($options, $gallery_type, ['carousel_show_gallery_title', 'carousel_show_image_title']));
    $this->assign_bool($settings['carousel'], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['carousel_show_gallery_description', 'carousel_show_image_description']));
    
    $this->assign_bool($settings['blog'], 'showTitle', $this->get_10web_bool($options, $gallery_type, ['blog_style_title_enable']));
    $this->assign_bool($settings['blog'], 'showDescription', $this->get_10web_bool($options, $gallery_type, ['blog_style_description_enable']));
    $this->assign_bool($settings['blog'], 'showCaption', false);
    
    switch ($mapped_type) {
      case 'thumbnails':
        $this->apply_pagination($settings['thumbnails'], $options, $gallery_type, 'image_enable_page', 'images_per_page');
        break;

      case 'masonry':
        $this->apply_pagination($settings['masonry'], $options, $gallery_type, 'masonry_image_enable_page', 'masonry_images_per_page');
        break;

      case 'mosaic':
        $this->apply_pagination($settings['mosaic'], $options, $gallery_type, 'mosaic_image_enable_page', 'mosaic_images_per_page');
        break;

      case 'slideshow':
        break;

      case 'carousel':
        break;

      case 'blog':
        $this->apply_pagination($settings['blog'], $options, $gallery_type, 'blog_style_enable_page', 'blog_style_images_per_page');
        break;
    }

    return $settings;
  }

  private function build_general_settings($gallery_type, $options) {
    $settings = [];

    $this->assign_int($settings, 'itemsPerPage', $this->get_items_per_page($gallery_type, $options));
    $this->assign_bool($settings, 'enableSearch', $this->get_search_enabled($gallery_type, $options));
    $this->assign_string($settings, 'searchPlaceholderText', $this->get_search_placeholder($gallery_type, $options));

    $click_action = $this->get_10web_string($options, $gallery_type, ['thumb_click_action']);
    $mapped_click_action = $this->map_click_action($click_action);
    if ($mapped_click_action !== '') {
      $settings['clickAction'] = $mapped_click_action;
    }

    $open_in_new_tab = $this->get_10web_bool($options, $gallery_type, ['thumb_link_target']);
    $this->assign_bool($settings, 'openUrlInNewTab', $open_in_new_tab);

    return $settings;
  }

  private function apply_pagination(&$settings, $options, $gallery_type, $enable_key, $per_page_key) {
    $enabled = $this->get_10web_bool($options, $gallery_type, [$enable_key]);
    if ($enabled !== null) {
      if ($enabled) {
        $settings['paginationType'] = 'simple';
        $settings['showAllItems'] = false;
      } else {
        $settings['paginationType'] = 'none';
        $settings['showAllItems'] = true;
      }
    }

    $items_per_page = $this->get_10web_int($options, $gallery_type, [$per_page_key]);
    if ($items_per_page !== null && $items_per_page > 0) {
      $settings['showAllItems'] = false;
    }
  }

  private function get_items_per_page($gallery_type, $options) {
    $per_type_keys = [
      'thumbnails' => 'images_per_page',
      'thumbnails_masonry' => 'masonry_images_per_page',
      'thumbnails_mosaic' => 'mosaic_images_per_page',
      'blog_style' => 'blog_style_images_per_page',
      'image_browser' => 'images_per_page',
      'slideshow' => 'images_per_page',
      'carousel' => 'images_per_page',
    ];

    $key = isset($per_type_keys[$gallery_type]) ? $per_type_keys[$gallery_type] : 'images_per_page';
    return $this->get_10web_int($options, $gallery_type, [$key]);
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

  private function apply_10web_title_visibility_mode(&$settings, $mode) {
    $mode = strtolower(trim((string) $mode));
    if ($mode === '') {
      return;
    }

    if (in_array($mode, ['show'], true)) {
      $settings['showTitle'] = true;
      $settings['titleVisibility'] = 'alwaysShown';
      return;
    }

    if (in_array($mode, ['hover'], true)) {
      $settings['showTitle'] = true;
      $settings['titleVisibility'] = 'onHover';
      return;
    }

    if (in_array($mode, ['none'], true)) {
      $settings['showTitle'] = false;
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

    foreach ($this->get_shortcode_ids_for_gallery($source_gallery_id) as $shortcode_id) {
      $sid = preg_quote((string) $shortcode_id, '/');
      $patterns[] = '/\[Best_Wordpress_Gallery\b[^\]]*\bid\s*=\s*(?:"|\')?' . $sid . '(?:"|\')?[^\]]*\]/i';
    }

    $gid = preg_quote((string) $source_gallery_id, '/');
    $patterns[] = '/\[Best_Wordpress_Gallery\b[^\]]*\bgallery_id\s*=\s*(?:"|\')?' . $gid . '(?:"|\')?[^\]]*\]/i';

    return array_values(array_unique($patterns));
  }

  public function get_block_namespaces() {
    return [];
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
    $patterns = $this->get_shortcode_patterns($source_gallery_id);
    if (empty($patterns)) {
      return false;
    }

    return $this->content_reference_exists(
      ['%[Best_Wordpress_Gallery%', '%wp:tw/bwg%'],
      $patterns
    );
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
      $decoded_json = json_decode($bwg_options_raw, true);
      if (is_array($decoded_json)) {
        $bwg_options = $decoded_json;
      } else {
        $unserialized = maybe_unserialize($bwg_options_raw);
        if (is_array($unserialized)) {
          $bwg_options = $unserialized;
        } elseif (is_object($unserialized)) {
          $bwg_options = (array) $unserialized;
        }
      }
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
      $decoded_json = json_decode($raw, true);
      if (is_array($decoded_json)) {
        $this->bwg_options = $decoded_json;
      } else {
        $unserialized = maybe_unserialize($raw);
        if (is_array($unserialized)) {
          $this->bwg_options = $unserialized;
        } elseif (is_object($unserialized)) {
          $this->bwg_options = (array) $unserialized;
        }
      }
    } elseif (is_array($raw)) {
      $this->bwg_options = $raw;
    } elseif (is_object($raw)) {
      $this->bwg_options = (array) $raw;
    }

    return $this->bwg_options;
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
