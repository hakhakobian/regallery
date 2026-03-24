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
    $source_has_placements = (bool) $provider->source_exists_in_posts($gallery_id);

    $saved_gallery_id = $existing
      ? $this->update_regallery($existing, $title, $attachment_ids, [
        'source' => $source_key,
        'source_gallery_id' => $gallery_id,
        'source_has_placements' => $source_has_placements,
      ], $settings)
      : $this->create_regallery($title, $attachment_ids, [
      'source' => $source_key,
      'source_gallery_id' => $gallery_id,
      'source_has_placements' => $source_has_placements,
    ], $settings);

    if (is_wp_error($saved_gallery_id)) {
      return [
        'success' => false,
        'source_gallery_id' => $gallery_id,
        'message' => $saved_gallery_id->get_error_message(),
      ];
    }

    return [
      'success' => true,
      'source_gallery_id' => $gallery_id,
      'gallery_id' => intval($saved_gallery_id),
      'images_count' => count($attachment_ids),
      'message' => $existing
        ? __('Gallery updated successfully.', 'regallery')
        : __('Gallery imported successfully.', 'regallery'),
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

    return $this->store_regallery_data($post_id, $title, $attachment_ids, $source_meta, $settings_overrides);
  }

  private function update_regallery($post_id, $title, $attachment_ids, $source_meta = [], $settings_overrides = []) {
    $post_id = intval($post_id);
    if ($post_id <= 0 || get_post_type($post_id) !== REACG_CUSTOM_POST_TYPE) {
      return new WP_Error('reacg_migration_update_failed', __('Failed to update Re Gallery post.', 'regallery'));
    }

    $updated = wp_update_post([
      'ID' => $post_id,
      'post_title' => $title,
    ], true);

    if (is_wp_error($updated)) {
      return new WP_Error('reacg_migration_update_failed', __('Failed to update Re Gallery post.', 'regallery'));
    }

    return $this->store_regallery_data($post_id, $title, $attachment_ids, $source_meta, $settings_overrides);
  }

  private function store_regallery_data($post_id, $title, $attachment_ids, $source_meta = [], $settings_overrides = []) {
    $post_id = intval($post_id);

    update_post_meta($post_id, 'images_ids', wp_json_encode($attachment_ids));
    update_post_meta($post_id, 'images_count', count($attachment_ids));
    update_post_meta($post_id, 'additional_data', '');
    update_post_meta($post_id, 'gallery_timestamp', time());
    update_post_meta($post_id, '_reacg_migration_source', sanitize_key($source_meta['source']));
    update_post_meta($post_id, '_reacg_migration_source_gallery_id', sanitize_text_field($source_meta['source_gallery_id']));
    update_post_meta($post_id, '_reacg_migration_source_has_placements', !empty($source_meta['source_has_placements']) ? '1' : '0');

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
      $source_still_exists = !empty($migrated_gallery_id)
        && $this->source_exists_in_posts($source, $source_gallery_id);
      $source_has_placements = !empty($migrated_gallery_id)
        ? $this->get_source_has_placements_flag($migrated_gallery_id)
        : false;

      if (!empty($migrated_gallery_id) && $source_still_exists && !$source_has_placements) {
        $source_has_placements = true;
        update_post_meta($migrated_gallery_id, '_reacg_migration_source_has_placements', '1');
      }

      $item['migrated'] = !empty($migrated_gallery_id);
      $item['migrated_gallery_id'] = intval($migrated_gallery_id);
      $item['replace_available'] = !empty($migrated_gallery_id) && $source_has_placements;
      $item['replaced'] = !empty($migrated_gallery_id)
        && $source_has_placements
        && !$source_still_exists;
      $decorated[] = $item;
    }

    return $decorated;
  }

  private function get_source_has_placements_flag($migrated_gallery_id) {
    $value = get_post_meta(intval($migrated_gallery_id), '_reacg_migration_source_has_placements', true);

    return $value === '1' || $value === 1 || $value === true;
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
    $provider = $this->get_provider($source);
    if (is_wp_error($provider)) {
      return false;
    }

    return (bool) $provider->source_exists_in_posts($source_gallery_id);
  }

  public function get_provider($source) {
    $key = sanitize_key((string) $source);
    if (empty($this->providers[$key])) {
      return new WP_Error('reacg_migration_source_not_found', __('Migration source is not supported.', 'regallery'));
    }

    return $this->providers[$key];
  }
}
