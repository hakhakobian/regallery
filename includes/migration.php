<?php

defined('ABSPATH') || die('Access Denied');

require_once REACG_PLUGIN_DIR . '/includes/migration/interface-provider.php';
require_once REACG_PLUGIN_DIR . '/includes/migration/provider-envira.php';
require_once REACG_PLUGIN_DIR . '/includes/migration/provider-nextgen.php';
require_once REACG_PLUGIN_DIR . '/includes/migration/provider-foogallery.php';
require_once REACG_PLUGIN_DIR . '/includes/migration/provider-gutenberg.php';
require_once REACG_PLUGIN_DIR . '/includes/migration/engine.php';

class REACG_Migration {
  private $obj;
  private $engine;
  private $page_slug = 'reacg-migration';

  public function __construct($that) {
    $this->obj = $that;
    $this->engine = new REACG_Migration_Engine([
      new REACG_Migration_Provider_Envira(),
      new REACG_Migration_Provider_NextGEN(),
      new REACG_Migration_Provider_FooGallery(),
      new REACG_Migration_Provider_Gutenberg(),
    ]);

    add_action('admin_menu', [$this, 'add_submenu']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

    add_action('wp_ajax_reacg_migration_sources', [$this, 'ajax_sources']);
    add_action('wp_ajax_reacg_migration_galleries', [$this, 'ajax_galleries']);
    add_action('wp_ajax_reacg_migration_import', [$this, 'ajax_import']);
    add_action('wp_ajax_reacg_migration_replace_shortcode', [$this, 'ajax_replace_shortcode']);
  }

  public function add_submenu() {
    add_submenu_page(
      'edit.php?post_type=' . REACG_CUSTOM_POST_TYPE,
      __('Migrate Galleries', 'regallery'),
      __('Migrate Galleries', 'regallery'),
      'manage_options',
      $this->page_slug,
      [$this, 'render_page'],
      2
    );
  }

  public function enqueue_assets($hook) {
    if (empty($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== $this->page_slug) {
      return;
    }

    wp_enqueue_style(
      REACG_PREFIX . '_migration',
      REACG_PLUGIN_URL . '/assets/css/migration.css',
      [REACG_PREFIX . '_admin'],
      REACG_VERSION
    );

    wp_enqueue_script(
      REACG_PREFIX . '_migration',
      REACG_PLUGIN_URL . '/assets/js/migration.js',
      ['jquery'],
      REACG_VERSION,
      true
    );

    wp_localize_script(REACG_PREFIX . '_migration', 'reacg_migration', [
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce_key' => REACG_NONCE,
      'nonce' => wp_create_nonce(REACG_NONCE),
      'edit_url' => admin_url('post.php?action=edit&post='),
      'i18n' => [
        'loading' => __('Loading...', 'regallery'),
        'loading_sources' => __('Loading sources...', 'regallery'),
        'loading_galleries' => __('Loading galleries...', 'regallery'),
        'importing' => __('Importing galleries...', 'regallery'),
        'load_galleries' => __('Load galleries', 'regallery'),
        'import_selected' => __('Import selected', 'regallery'),
        'no_data' => __('No galleries found.', 'regallery'),
        'select_source' => __('Select source', 'regallery'),
        'select_galleries' => __('Select galleries to import.', 'regallery'),
        'import_done' => __('Import finished.', 'regallery'),
        'not_installed' => __('Not installed', 'regallery'),
        'all_sources' => __('All sources', 'regallery'),
        'column_title' => __('Gallery title', 'regallery'),
        'column_source' => __('Source', 'regallery'),
        'column_images' => __('Images count', 'regallery'),
        'column_status' => __('Status', 'regallery'),
        'column_replace' => __('Replace shortcode', 'regallery'),
        'status_migrated' => __('Migrated', 'regallery'),
        'status_not_migrated' => __('Not migrated', 'regallery'),
        'replace_shortcode' => __('Replace', 'regallery'),
        'replace_replaced' => __('Replaced', 'regallery'),
        'replace_not_supported' => __('Not supported', 'regallery'),
        'replacing_shortcode' => __('Replacing shortcodes...', 'regallery'),
        'replace_done' => __('Shortcodes replaced successfully.', 'regallery'),
        'replace_none_found' => __('No matching shortcodes were found.', 'regallery'),
        'items' => __('items', 'regallery'),
        'first_page' => __('First page', 'regallery'),
        'prev' => __('Previous page', 'regallery'),
        'next' => __('Next page', 'regallery'),
        'last_page' => __('Last page', 'regallery'),
        'current_page' => __('Current page', 'regallery'),
        'of' => __('of', 'regallery'),
        'error' => __('Something went wrong.', 'regallery'),
      ],
    ]);
  }

  public function render_page() {
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'regallery'));
    }
    ?>
    <div class="wrap reacg-migration-page">
      <h1><?php esc_html_e('Universal Gallery Migration', 'regallery'); ?></h1>
      <p class="description">
        <?php esc_html_e('Import galleries from Envira, NextGEN, FooGallery, and Gutenberg.', 'regallery'); ?>
      </p>

      <div id="reacg-migration-progress" class="reacg-migration-progress" style="display:none;">
        <span class="spinner is-active" aria-hidden="true"></span>
        <span id="reacg-migration-progress-text"></span>
        <div class="reacg-migration-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
          <div id="reacg-migration-progress-fill" class="reacg-migration-progress-fill"></div>
        </div>
      </div>

      <div id="reacg-migration-status" class="notice inline" style="display:none;"></div>

        <p class="search-box">
            <label class="screen-reader-text" for="reacg-migration-search"><?php esc_html_e('Search Galleries', 'regallery'); ?></label>
            <input type="search" id="reacg-migration-search" />
            <button type="button" class="button" id="reacg-migration-search-submit"><?php esc_html_e('Search Galleries', 'regallery'); ?></button>
        </p>
      <div class="tablenav top">
        <div class="alignleft actions">
          <label class="screen-reader-text" for="reacg-migration-source"><?php esc_html_e('Filter by source', 'regallery'); ?></label>
          <select id="reacg-migration-source"></select>
          <button type="button" class="button action" id="reacg-migration-load">
            <?php esc_html_e('Filter', 'regallery'); ?>
          </button>
          <label class="reacg-migration-force-new">
            <input type="checkbox" id="reacg-migration-force-new" />
            <?php esc_html_e('Force new import', 'regallery'); ?>
          </label>
        </div>
        <div class="tablenav-pages" id="reacg-migration-pages-top"></div>
      </div>

      <table class="wp-list-table widefat fixed striped table-view-list reacg-migration-table">
        <thead>
        <tr>
          <td class="manage-column column-cb check-column"><input type="checkbox" id="reacg-migration-toggle-all" /></td>
          <th scope="col" class="manage-column sorted desc" data-sort="title" aria-sort="ascending">
            <a href="#"><span><?php esc_html_e('Gallery title', 'regallery'); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a>
          </th>
          <th scope="col" class="manage-column sortable desc" data-sort="source" aria-sort="none">
            <a href="#"><span><?php esc_html_e('Source', 'regallery'); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a>
          </th>
          <th scope="col" class="manage-column sortable desc" data-sort="image_count" aria-sort="none">
            <a href="#"><span><?php esc_html_e('Images count', 'regallery'); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a>
          </th>
          <th scope="col" class="manage-column sortable desc" data-sort="status" aria-sort="none">
            <a href="#"><span><?php esc_html_e('Status', 'regallery'); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a>
          </th>
          <th scope="col" class="manage-column">
            <?php esc_html_e('Replace shortcode', 'regallery'); ?>
          </th>
        </tr>
        </thead>
        <tbody id="reacg-migration-list"></tbody>
      </table>

      <div class="tablenav bottom">
        <div class="alignleft actions">
          <button type="button" class="button button-primary" id="reacg-migration-import" disabled>
            <?php esc_html_e('Migrate', 'regallery'); ?>
          </button>
          <span id="reacg-migration-import-spinner" class="spinner" aria-hidden="true"></span>
        </div>
        <div class="tablenav-pages" id="reacg-migration-pages-bottom"></div>
      </div>

      <div id="reacg-migration-results"></div>
    </div>
    <?php
  }

  public function ajax_sources() {
    $this->authorize_request();

    wp_send_json_success([
      'sources' => $this->engine->get_sources(),
    ]);
  }

  public function ajax_galleries() {
    $this->authorize_request();

    $source = !empty($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : '';
    $page = !empty($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = !empty($_POST['per_page']) ? intval($_POST['per_page']) : 50;
    $search = !empty($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    if (empty($source)) {
      $result = $this->engine->list_all_galleries([
        'search' => $search,
      ]);
    }
    else {
      $result = $this->engine->list_galleries($source, [
      'page' => $page,
      'per_page' => $per_page,
      'search' => $search,
      ]);

      if (!is_wp_error($result)) {
        $items = !empty($result['items']) ? $result['items'] : [];
        foreach ($items as &$item) {
          $item['source'] = $source;
          $item['source_label'] = $this->get_source_label($source);
        }
        $result['items'] = $items;
      }
    }

    if (is_wp_error($result)) {
      wp_send_json_error([
        'message' => $result->get_error_message(),
      ], 400);
    }

    wp_send_json_success($result);
  }

  public function ajax_import() {
    $this->authorize_request();

    $source = !empty($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : '';
    $force_new = !empty($_POST['force_new']);

    $gallery_ids = [];
    if (!empty($_POST['gallery_ids'])) {
      $raw = wp_unslash($_POST['gallery_ids']);
      if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
          $gallery_ids = $decoded;
        } else {
          $gallery_ids = array_filter(array_map('trim', explode(',', $raw)));
        }
      } elseif (is_array($raw)) {
        $gallery_ids = $raw;
      }
    }

    if (empty($gallery_ids)) {
      wp_send_json_error([
        'message' => __('Please provide gallery IDs to import.', 'regallery'),
      ], 400);
    }

    $result = $this->engine->import_galleries($source, $gallery_ids, [
      'force_new' => $force_new,
    ]);

    if (is_wp_error($result)) {
      wp_send_json_error([
        'message' => $result->get_error_message(),
      ], 400);
    }

    wp_send_json_success($result);
  }

  public function ajax_replace_shortcode() {
    $this->authorize_request();

    $source = !empty($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : '';
    $source_gallery_id = isset($_POST['source_gallery_id'])
      ? sanitize_text_field(wp_unslash($_POST['source_gallery_id']))
      : '';
    $migrated_gallery_id = !empty($_POST['migrated_gallery_id'])
      ? intval($_POST['migrated_gallery_id'])
      : 0;

    if ($source === '' || $source_gallery_id === '') {
      wp_send_json_error([
        'message' => __('Missing source or gallery IDs.', 'regallery'),
      ], 400);
    }

    if ($migrated_gallery_id <= 0) {
      $migrated_gallery_id = $this->find_migrated_gallery_id($source, $source_gallery_id);
    }

    if ($migrated_gallery_id <= 0) {
      wp_send_json_error([
        'message' => __('No migrated gallery was found for this source gallery.', 'regallery'),
      ], 400);
    }

    $result = $this->replace_source_shortcodes($source, $source_gallery_id, $migrated_gallery_id);
    if (is_wp_error($result)) {
      wp_send_json_error([
        'message' => $result->get_error_message(),
      ], 400);
    }

    wp_send_json_success($result);
  }

  private function replace_source_shortcodes($source, $source_gallery_id, $migrated_gallery_id) {
    $source = sanitize_key($source);
    $source_gallery_id = trim((string) $source_gallery_id);

    if ($source === 'gutenberg') {
      return $this->replace_gutenberg_gallery($source_gallery_id, $migrated_gallery_id);
    }

    $replacement_shortcode = '[REACG id="' . intval($migrated_gallery_id) . '"]';
    $patterns = $this->get_shortcode_patterns($source, $source_gallery_id);
    if (empty($patterns)) {
      return new WP_Error('reacg_migration_replace_unsupported', __('Shortcode replacement is not supported for this source.', 'regallery'));
    }

    $post_types = get_post_types(['public' => true], 'names');
    if (empty($post_types)) {
      return [
        'updated_posts' => 0,
        'replaced_shortcodes' => 0,
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
      if (!$post || $post->post_content === '') {
        continue;
      }

      $updated_content = $post->post_content;
      $local_replacements = 0;

      foreach ($patterns as $pattern) {
        $count = 0;
        $updated_content = preg_replace($pattern, $replacement_shortcode, $updated_content, -1, $count);
        if ($count > 0) {
          $local_replacements += intval($count);
        }
      }

      if ($local_replacements <= 0 || $updated_content === $post->post_content) {
        continue;
      }

      wp_update_post([
        'ID' => intval($post_id),
        'post_content' => $updated_content,
      ]);

      $updated_posts += 1;
      $replaced_shortcodes += $local_replacements;
    }

    return [
      'updated_posts' => $updated_posts,
      'replaced_shortcodes' => $replaced_shortcodes,
      'replacement_shortcode' => $replacement_shortcode,
    ];
  }

  private function replace_gutenberg_gallery($source_gallery_id, $migrated_gallery_id) {
    $parsed = $this->parse_gutenberg_source_gallery_id($source_gallery_id);
    if (is_wp_error($parsed)) {
      return $parsed;
    }

    $post = get_post($parsed['post_id']);
    if (!$post) {
      return new WP_Error('reacg_migration_replace_post_not_found', __('Source post not found for Gutenberg gallery.', 'regallery'));
    }

    $content = (string) $post->post_content;
    if ($content === '') {
      return [
        'updated_posts' => 0,
        'replaced_shortcodes' => 0,
      ];
    }

    $replacement_shortcode = '[REACG id="' . intval($migrated_gallery_id) . '"]';
    $replacement_block = $this->build_reacg_gutenberg_block($migrated_gallery_id);
    $updated_content = '';
    $replacements = 0;

    if ($parsed['type'] === 'shortcode') {
      $updated_content = $this->replace_nth_gallery_shortcode($content, $parsed['index'], $replacement_block, $replacements);
    }
    else {
      $updated_content = $this->replace_nth_gallery_block($content, $parsed['index'], $replacement_block, $replacements);
    }

    if ($replacements <= 0 || $updated_content === $content) {
      return [
        'updated_posts' => 0,
        'replaced_shortcodes' => 0,
        'replacement_shortcode' => $replacement_shortcode,
        'replacement_block' => $replacement_block,
      ];
    }

    wp_update_post([
      'ID' => intval($post->ID),
      'post_content' => $updated_content,
    ]);

    return [
      'updated_posts' => 1,
      'replaced_shortcodes' => $replacements,
      'replacement_shortcode' => $replacement_shortcode,
      'replacement_block' => $replacement_block,
    ];
  }

  private function build_reacg_gutenberg_block($migrated_gallery_id) {
    $gallery_id = intval($migrated_gallery_id);
    $shortcode = '[REACG id="' . $gallery_id . '"]';
    $attributes = [
      'shortcode' => $shortcode,
      'shortcode_id' => $gallery_id,
      'hidePreview' => true,
    ];

    if (function_exists('serialize_block')) {
      $serialized = serialize_block([
        'blockName' => 'reacg/gallery',
        'attrs' => $attributes,
        'innerBlocks' => [],
        'innerHTML' => $shortcode,
        'innerContent' => [$shortcode],
      ]);

      if (is_string($serialized) && $serialized !== '') {
        return $serialized;
      }
    }

    $json_attributes = wp_json_encode($attributes);
    if (!is_string($json_attributes) || $json_attributes === '') {
      $json_attributes = '{}';
    }

    return '<!-- wp:reacg/gallery ' . $json_attributes . ' -->' . $shortcode . '<!-- /wp:reacg/gallery -->';
  }

  private function parse_gutenberg_source_gallery_id($source_gallery_id) {
    $parts = explode(':', (string) $source_gallery_id);
    $post_id = !empty($parts[0]) ? intval($parts[0]) : 0;

    if ($post_id <= 0) {
      return new WP_Error('reacg_migration_replace_invalid_source_id', __('Invalid Gutenberg gallery identifier.', 'regallery'));
    }

    $type = 'block';
    $index = 0;

    if (isset($parts[1]) && $parts[1] === 's') {
      $type = 'shortcode';
      $index = isset($parts[2]) ? max(0, intval($parts[2])) : 0;
    }
    else {
      $index = isset($parts[1]) ? max(0, intval($parts[1])) : 0;
    }

    return [
      'post_id' => $post_id,
      'type' => $type,
      'index' => $index,
    ];
  }

  private function replace_nth_gallery_block($content, $target_index, $replacement_content, &$replacements) {
    if (function_exists('parse_blocks') && function_exists('serialize_blocks')) {
      $blocks = parse_blocks((string) $content);
      if (!empty($blocks)) {
        $replacement_blocks = parse_blocks((string) $replacement_content);
        if (!empty($replacement_blocks[0])) {
          $current_index = -1;
          if ($this->replace_nth_gallery_block_in_tree($blocks, $target_index, $replacement_blocks[0], $current_index)) {
            $replacements += 1;
            return serialize_blocks($blocks);
          }
        }
      }
    }

    $pattern = '/<!--\s+wp:gallery\b[\s\S]*?(?:<!--\s+\/wp:gallery\s+-->|\/-->)/i';
    $current_index = -1;

    return preg_replace_callback($pattern, function($match) use (&$current_index, $target_index, $replacement_content, &$replacements) {
      $current_index += 1;
      if ($current_index === $target_index) {
        $replacements += 1;
        return $replacement_content;
      }
      return $match[0];
    }, $content);
  }

  private function replace_nth_gallery_block_in_tree(&$blocks, $target_index, $replacement_block, &$current_index) {
    foreach ($blocks as $i => &$block) {
      if (isset($block['blockName']) && $block['blockName'] === 'core/gallery') {
        $current_index += 1;
        if ($current_index === $target_index) {
          $block = $replacement_block;
          return true;
        }
      }

      if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
        if ($this->replace_nth_gallery_block_in_tree($block['innerBlocks'], $target_index, $replacement_block, $current_index)) {
          return true;
        }
      }
    }

    return false;
  }

  private function replace_nth_gallery_shortcode($content, $target_index, $replacement_content, &$replacements) {
    $pattern = '/' . get_shortcode_regex(['gallery']) . '/s';
    $current_index = -1;

    return preg_replace_callback($pattern, function($match) use (&$current_index, $target_index, $replacement_content, &$replacements) {
      if (empty($match[2]) || strtolower((string) $match[2]) !== 'gallery') {
        return $match[0];
      }

      $current_index += 1;
      if ($current_index === $target_index) {
        $replacements += 1;
        return $replacement_content;
      }

      return $match[0];
    }, $content);
  }

  private function get_shortcode_patterns($source, $source_gallery_id) {
    $id = preg_quote((string) $source_gallery_id, '/');

    if ($source === 'envira') {
      return [
        '/\\[envira-gallery\\b[^\\]]*\\bid\\s*=\\s*(?:"|\')?' . $id . '(?:"|\')?[^\\]]*\\]/i',
        '/\\[envira\\b[^\\]]*\\bid\\s*=\\s*(?:"|\')?' . $id . '(?:"|\')?[^\\]]*\\]/i',
      ];
    }

    if ($source === 'foo') {
      return [
        '/\\[foogallery\\b[^\\]]*\\bid\\s*=\\s*(?:"|\')?' . $id . '(?:"|\')?[^\\]]*\\]/i',
      ];
    }

    if ($source === 'nextgen') {
      return [
        '/\\[(?:ngg|nggallery)\\b[^\\]]*\\bid\\s*=\\s*(?:"|\')?' . $id . '(?:"|\')?[^\\]]*\\]/i',
        '/\\[(?:ngg|nggallery)\\b(?=[^\\]]*\\bids\\s*=\\s*(?:"|\')?' . $id . '(?:"|\')?)[^\\]]*\\]/i',
      ];
    }

    return [];
  }

  private function find_migrated_gallery_id($source, $source_gallery_id) {
    $query = new WP_Query([
      'post_type' => REACG_CUSTOM_POST_TYPE,
      'post_status' => ['publish', 'draft', 'private'],
      'posts_per_page' => 1,
      'fields' => 'ids',
      'meta_query' => [
        'relation' => 'AND',
        [
          'key' => '_reacg_migration_source',
          'value' => sanitize_key((string) $source),
        ],
        [
          'key' => '_reacg_migration_source_gallery_id',
          'value' => sanitize_text_field((string) $source_gallery_id),
        ],
      ],
    ]);

    return !empty($query->posts[0]) ? intval($query->posts[0]) : 0;
  }

  private function authorize_request() {
    if (!current_user_can('edit_posts')) {
      wp_send_json_error([
        'message' => __('Permission denied.', 'regallery'),
      ], 403);
    }

    if (empty($_POST[REACG_NONCE])) {
      wp_send_json_error([
        'message' => __('Missing nonce.', 'regallery'),
      ], 400);
    }

    $nonce = sanitize_text_field(wp_unslash($_POST[REACG_NONCE]));
    if (!wp_verify_nonce($nonce, REACG_NONCE)) {
      wp_send_json_error([
        'message' => __('Invalid nonce.', 'regallery'),
      ], 403);
    }
  }

  private function get_source_label($source_key) {
    foreach ($this->engine->get_sources() as $source) {
      if (!empty($source['key']) && $source['key'] === $source_key) {
        return !empty($source['label']) ? $source['label'] : $source_key;
      }
    }

    return $source_key;
  }
}
