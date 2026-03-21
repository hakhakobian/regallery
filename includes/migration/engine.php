<?php

defined('ABSPATH') || die('Access Denied');

class REACG_Migration_Engine {
  /** @var REACG_Migration_Provider_Interface[] */
  private $providers = [];

  public function __construct($providers = []) {
    foreach ((array) $providers as $provider) {
      if ($provider instanceof REACG_Migration_Provider_Interface) {
        $this->providers[$provider->get_key()] = $provider;
      }
    }
  }

  public function get_sources() {
    $sources = [];
    foreach ($this->providers as $provider) {
      $sources[] = [
        'key' => $provider->get_key(),
        'label' => $provider->get_label(),
        'available' => (bool) $provider->is_available(),
      ];
    }

    return $sources;
  }

  public function list_galleries($source, $args = []) {
    $provider = $this->get_provider($source);
    if (is_wp_error($provider)) {
      return $provider;
    }

    if (!$provider->is_available()) {
      return new WP_Error('reacg_migration_unavailable', __('Selected source is not available on this site.', 'regallery'));
    }

    $result = $provider->list_galleries($args);
    if (is_wp_error($result)) {
      return $result;
    }

    $result['items'] = $this->decorate_items_with_migration_status(
      $provider->get_key(),
      !empty($result['items']) ? $result['items'] : []
    );

    return $result;
  }

  public function list_all_galleries($args = []) {
    $all_items = [];

    foreach ($this->providers as $provider) {
      if (!$provider->is_available()) {
        continue;
      }

      $provider_result = $provider->list_galleries($args);
      if (is_wp_error($provider_result)) {
        continue;
      }

      $items = !empty($provider_result['items']) ? $provider_result['items'] : [];
      $items = $this->decorate_items_with_migration_status($provider->get_key(), $items);

      foreach ($items as $item) {
        $item['source'] = $provider->get_key();
        $item['source_label'] = $provider->get_label();
        $all_items[] = $item;
      }
    }

    usort($all_items, function ($a, $b) {
      return strnatcasecmp(
        !empty($a['title']) ? (string) $a['title'] : '',
        !empty($b['title']) ? (string) $b['title'] : ''
      );
    });

    return [
      'items' => $all_items,
      'total' => count($all_items),
      'page' => 1,
      'per_page' => count($all_items),
    ];
  }

  public function import_galleries($source, $gallery_ids = [], $args = []) {
    $provider = $this->get_provider($source);
    if (is_wp_error($provider)) {
      return $provider;
    }

    if (!$provider->is_available()) {
      return new WP_Error('reacg_migration_unavailable', __('Selected source is not available on this site.', 'regallery'));
    }

    $results = [];
    foreach ((array) $gallery_ids as $raw_gallery_id) {
      $gallery_id = sanitize_text_field((string) $raw_gallery_id);
      if ($gallery_id === '') {
        continue;
      }

      $results[] = $this->import_single_gallery($provider, $gallery_id, $args);
    }

    return [
      'source' => $source,
      'results' => $results,
      'imported' => count(array_filter($results, function ($row) {
        return !empty($row['success']);
      })),
      'failed' => count(array_filter($results, function ($row) {
        return empty($row['success']);
      })),
    ];
  }

  private function import_single_gallery($provider, $gallery_id, $args) {
    $source_key = $provider->get_key();
    $existing = $this->find_existing_import($source_key, $gallery_id);
    if ($existing && empty($args['force_new'])) {
      return [
        'success' => true,
        'source_gallery_id' => $gallery_id,
        'gallery_id' => intval($existing),
        'message' => __('Gallery already imported. Reusing existing gallery.', 'regallery'),
      ];
    }

    $source_gallery = $provider->get_gallery($gallery_id);
    if (is_wp_error($source_gallery)) {
      return [
        'success' => false,
        'source_gallery_id' => $gallery_id,
        'message' => $source_gallery->get_error_message(),
      ];
    }

    $items = !empty($source_gallery['items']) && is_array($source_gallery['items']) ? $source_gallery['items'] : [];
    $settings = !empty($source_gallery['settings']) && is_array($source_gallery['settings']) ? $source_gallery['settings'] : [];
    $attachment_ids = [];

    foreach ($items as $item) {
      $attachment_id = $this->resolve_attachment_id($item);
      if ($attachment_id) {
        $attachment_ids[] = $attachment_id;
      }
    }

    $attachment_ids = array_values(array_unique(array_filter(array_map('intval', $attachment_ids))));

    $title = !empty($source_gallery['title']) ? sanitize_text_field($source_gallery['title']) : __('Imported Gallery', 'regallery');

    $created = $this->create_regallery($title, $attachment_ids, [
      'source' => $source_key,
      'source_gallery_id' => $gallery_id,
    ], $settings);

    if (is_wp_error($created)) {
      return [
        'success' => false,
        'source_gallery_id' => $gallery_id,
        'message' => $created->get_error_message(),
      ];
    }

    return [
      'success' => true,
      'source_gallery_id' => $gallery_id,
      'gallery_id' => intval($created),
      'images_count' => count($attachment_ids),
      'message' => __('Gallery imported successfully.', 'regallery'),
    ];
  }

  private function resolve_attachment_id($item) {
    if (!empty($item['attachment_id'])) {
      $attachment_id = intval($item['attachment_id']);
      if ($attachment_id > 0) {
        $this->apply_attachment_item_meta($attachment_id, $item);
        return $attachment_id;
      }
    }

    if (!empty($item['url'])) {
      $url = esc_url_raw($item['url']);
      if (!empty($url)) {
        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id) {
          $this->apply_attachment_item_meta($attachment_id, $item);
          return intval($attachment_id);
        }

        return $this->sideload_url_to_attachment($url, $item);
      }
    }

    return 0;
  }

  private function apply_attachment_item_meta($attachment_id, $item) {
    $attachment_id = intval($attachment_id);
    if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
      return;
    }

    if (!empty($item['alt'])) {
      $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
      if ($existing_alt === '') {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($item['alt']));
      }
    }

    if (!empty($item['action_url'])) {
      update_post_meta($attachment_id, 'action_url', esc_url_raw($item['action_url']));
    }

    $update = ['ID' => $attachment_id];
    $should_update = false;

    if (!empty($item['caption'])) {
      $attachment = get_post($attachment_id);
      if ($attachment && $attachment->post_excerpt === '') {
        $update['post_excerpt'] = sanitize_text_field($item['caption']);
        $should_update = true;
      }
    }

    if (!empty($item['description'])) {
      $attachment = isset($attachment) ? $attachment : get_post($attachment_id);
      if ($attachment && $attachment->post_content === '') {
        $update['post_content'] = sanitize_textarea_field($item['description']);
        $should_update = true;
      }
    }

    if ($should_update) {
      wp_update_post($update);
    }
  }

  private function sideload_url_to_attachment($url, $item = []) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp_file = download_url($url);
    if (is_wp_error($tmp_file)) {
      return 0;
    }

    $file_name = wp_basename(parse_url($url, PHP_URL_PATH));
    if (empty($file_name)) {
      $file_name = 'reacg-migration-' . time() . '.jpg';
    }

    $file_array = [
      'name' => $file_name,
      'tmp_name' => $tmp_file,
    ];

    $attachment_id = media_handle_sideload($file_array, 0, !empty($item['description']) ? sanitize_text_field($item['description']) : '');

    if (is_wp_error($attachment_id)) {
      @unlink($tmp_file);
      return 0;
    }

    if (!empty($item['alt'])) {
      update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($item['alt']));
    }
    if (!empty($item['action_url'])) {
      update_post_meta($attachment_id, 'action_url', esc_url_raw($item['action_url']));
    }

    return intval($attachment_id);
  }

  private function create_regallery($title, $attachment_ids, $source_meta = [], $settings_overrides = []) {
    $post_id = wp_insert_post([
      'post_title' => $title,
      'post_status' => 'publish',
      'post_type' => REACG_CUSTOM_POST_TYPE,
    ]);

    if (is_wp_error($post_id) || !$post_id) {
      return new WP_Error('reacg_migration_create_failed', __('Failed to create Re Gallery post.', 'regallery'));
    }

    update_post_meta($post_id, 'images_ids', wp_json_encode($attachment_ids));
    update_post_meta($post_id, 'images_count', count($attachment_ids));
    update_post_meta($post_id, 'additional_data', '');
    update_post_meta($post_id, 'gallery_timestamp', time());
    update_post_meta($post_id, '_reacg_migration_source', sanitize_key($source_meta['source']));
    update_post_meta($post_id, '_reacg_migration_source_gallery_id', sanitize_text_field($source_meta['source_gallery_id']));

    if (!class_exists('REACG_Options')) {
      require_once REACG_PLUGIN_DIR . '/includes/options.php';
    }

    $options_obj = new REACG_Options(false);
    $options = $options_obj->get_options(0);
    if (!empty($settings_overrides) && is_array($settings_overrides)) {
      $options = array_replace_recursive($options, $this->sanitize_settings_overrides($settings_overrides));
    }
    $options['template_id'] = $post_id;
    $options['title'] = $title;

    $option_name = 'reacg_options' . $post_id;
    $encoded_options = wp_json_encode($options);

    $added = add_option($option_name, $encoded_options, '', false);
    if (!$added) {
      update_option($option_name, $encoded_options, false);
    }
    update_post_meta($post_id, 'options_timestamp', time());

    return intval($post_id);
  }

  private function sanitize_settings_overrides($settings) {
    $sanitized = [];

    foreach ((array) $settings as $key => $value) {
      $key = trim((string) $key);
      if ($key === '') {
        continue;
      }

      if (is_array($value)) {
        $sanitized[$key] = $this->sanitize_settings_overrides($value);
        continue;
      }

      if (is_bool($value)) {
        $sanitized[$key] = $value;
        continue;
      }

      if (is_numeric($value)) {
        $sanitized[$key] = strpos((string) $value, '.') !== false ? floatval($value) : intval($value);
        continue;
      }

      $sanitized[$key] = sanitize_text_field((string) $value);
    }

    return $sanitized;
  }

  private function find_existing_import($source, $source_gallery_id) {
    $query = new WP_Query([
      'post_type' => REACG_CUSTOM_POST_TYPE,
      'post_status' => ['publish', 'draft', 'private'],
      'posts_per_page' => 1,
      'fields' => 'ids',
      'meta_query' => [
        'relation' => 'AND',
        [
          'key' => '_reacg_migration_source',
          'value' => sanitize_key($source),
        ],
        [
          'key' => '_reacg_migration_source_gallery_id',
          'value' => sanitize_text_field((string) $source_gallery_id),
        ],
      ],
    ]);

    return !empty($query->posts[0]) ? intval($query->posts[0]) : 0;
  }

  private function decorate_items_with_migration_status($source, $items) {
    $decorated = [];
    foreach ((array) $items as $item) {
      $source_gallery_id = isset($item['id']) ? (string) $item['id'] : '';
      $migrated_gallery_id = $source_gallery_id !== ''
        ? $this->find_existing_import($source, $source_gallery_id)
        : 0;

      $item['migrated'] = !empty($migrated_gallery_id);
      $item['migrated_gallery_id'] = intval($migrated_gallery_id);
      $item['replaced'] = !empty($migrated_gallery_id)
        && !$this->source_exists_in_posts($source, $source_gallery_id);
      $decorated[] = $item;
    }

    return $decorated;
  }

  /**
   * Returns true if any post still contains a shortcode or block referencing
   * this source gallery, i.e. the Replace action has not yet been run.
   *
   * Uses a two-step check: a broad LIKE query to find candidate posts, then
   * an exact PHP regex on those posts to avoid false positives from IDs that
   * are substrings of other IDs (e.g. ID "5" matching "[foogallery id="15"]").
   */
  private function source_exists_in_posts($source, $source_gallery_id) {
    $source = sanitize_key($source);
    $source_gallery_id = trim((string) $source_gallery_id);
    $id_numeric = intval($source_gallery_id);

    // Regex patterns that exactly verify the gallery ID in post content.
    $verify_regexes = [];

    // Broad LIKE patterns used only to narrow down candidate posts efficiently.
    $like_clauses = [];

    if ($source === 'envira') {
      // Shortcodes: [envira-gallery id="X"] or [envira id="X"]
      $like_clauses[] = '%[envira-gallery%';
      $like_clauses[] = '%[envira %';
      $like_clauses[] = '%wp:envira%';
      $verify_regexes[] = '/\[envira(?:-gallery)?\b[^\]]*\bid\s*=\s*["\']?' . preg_quote($source_gallery_id, '/') . '["\'\s\]]/i';
      $verify_regexes[] = '/wp:envira[^\}]*\b' . preg_quote((string) $id_numeric, '/') . '\b/i';
    } elseif ($source === 'foo') {
      // Shortcodes: [foogallery id="X"]
      $like_clauses[] = '%[foogallery%';
      $like_clauses[] = '%wp:fooplugins%';
      $like_clauses[] = '%wp:foogallery%';
      $verify_regexes[] = '/\[foogallery\b[^\]]*\bid\s*=\s*["\']?' . preg_quote($source_gallery_id, '/') . '["\'\s\]]/i';
      $verify_regexes[] = '/wp:(?:fooplugins|foogallery)[^\}]*\b' . preg_quote((string) $id_numeric, '/') . '\b/i';

      // Also check Elementor _elementor_data meta for a foogallery widget.
      if ($this->elementor_foo_widget_exists($id_numeric)) {
        return true;
      }
    } elseif ($source === 'nextgen') {
      $like_clauses[] = '%[ngg%';
      $like_clauses[] = '%[nggallery%';
      $like_clauses[] = '%wp:ngg%';
      $like_clauses[] = '%wp:nextgen-gallery%';
      $like_clauses[] = '%wp:imagely%';
      $verify_regexes[] = '/\[ngg(?:allery)?\b[^\]]*\bid\s*=\s*["\']?' . preg_quote($source_gallery_id, '/') . '["\'\s\]]/i';
      $verify_regexes[] = '/wp:(?:ngg|nextgen-gallery|imagely)[^\}]*\b' . preg_quote((string) $id_numeric, '/') . '\b/i';
    } elseif ($source === 'gutenberg') {
      // Gutenberg source: the source_gallery_id encodes post_id:index.
      // We can't reliably detect which specific indexed block was replaced,
      // so always show the Replace link for Gutenberg items.
      return true;
    } else {
      return false;
    }

    global $wpdb;

    // Collect candidate post IDs via broad LIKE queries.
    $candidate_ids = [];
    foreach ($like_clauses as $like) {
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

    // Fetch the content of candidate posts and verify with exact regex.
    $ids_placeholder = implode(',', array_map('intval', $candidate_ids));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
    $contents = $wpdb->get_col(
      "SELECT post_content FROM {$wpdb->posts} WHERE ID IN ({$ids_placeholder})"
    );

    foreach ($contents as $content) {
      foreach ($verify_regexes as $regex) {
        if (preg_match($regex, $content)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Returns true if any post's _elementor_data meta still contains a
   * foogallery Elementor widget referencing the given gallery ID.
   */
  private function elementor_foo_widget_exists($source_gallery_id) {
    global $wpdb;
    $id_numeric = intval($source_gallery_id);

    // Broad LIKE to find candidate posts — the gallery_id value is an integer
    // stored as a string in JSON, so we can search for its numeric form.
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
      $raw = get_post_meta(intval($post_id), '_elementor_data', true);
      if (empty($raw) || !is_string($raw)) {
        continue;
      }
      $data = json_decode($raw, true);
      if (is_array($data) && $this->elementor_tree_has_foo_widget($data, $id_numeric)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Recursively checks whether the Elementor elements tree contains a
   * foogallery widget referencing the given gallery ID.
   */
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

  private function get_provider($source) {
    $key = sanitize_key((string) $source);
    if (empty($this->providers[$key])) {
      return new WP_Error('reacg_migration_source_not_found', __('Migration source is not supported.', 'regallery'));
    }

    return $this->providers[$key];
  }
}
