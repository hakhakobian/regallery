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
        'select_not_migrated' => __('Only already migrated galleries were selected. Select at least one gallery that has not been migrated yet, or confirm importing migrated galleries too.', 'regallery'),
        'import_done' => __('Import finished.', 'regallery'),
        'force_new_alert' => __('One or more selected galleries have already been migrated. Confirm to migrate them again as new imports, or cancel to migrate only galleries that have not been migrated yet.', 'regallery'),
        'alert_confirm' => __('Migrate all selected', 'regallery'),
        'alert_cancel' => __('Only migrate not migrated', 'regallery'),
        'alert_close' => __('Close dialog', 'regallery'),
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
        'replace_already_replaced' => __('Replaced', 'regallery'),
        'replace_not_supported' => __('Not supported', 'regallery'),
        'replacing_shortcode' => __('Replacing shortcodes...', 'regallery'),
        'replace_done' => __('Shortcodes replaced successfully.', 'regallery'),
        'replace_none_found' => __('No matching gallery shortcodes or blocks were found.', 'regallery'),
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
          <input type="hidden" id="reacg-migration-force-new" value="0" />
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

      <div id="reacg-migration-alert" class="reacg-migration-alert" style="display:none;" aria-hidden="true">
        <div class="reacg-migration-alert__backdrop"></div>
        <div class="reacg-migration-alert__dialog" role="dialog" aria-modal="true" aria-labelledby="reacg-migration-alert-title" aria-describedby="reacg-migration-alert-message">
          <button type="button" class="reacg-migration-alert__close" id="reacg-migration-alert-close" aria-label="<?php esc_attr_e('Close dialog', 'regallery'); ?>">&times;</button>
          <h2 id="reacg-migration-alert-title"><?php esc_html_e('Migration notice', 'regallery'); ?></h2>
          <p id="reacg-migration-alert-message"></p>
          <div class="reacg-migration-alert__actions">
            <button type="button" class="button" id="reacg-migration-alert-cancel"><?php esc_html_e('Only migrate not migrated', 'regallery'); ?></button>
            <button type="button" class="button button-primary" id="reacg-migration-alert-confirm"><?php esc_html_e('Migrate all selected', 'regallery'); ?></button>
          </div>
        </div>
      </div>
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
    } else {
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

    $shortcode_patterns = $this->get_shortcode_patterns($source, $source_gallery_id);
    $block_namespaces = $this->get_source_block_namespaces($source);
    $block_id_attrs = $this->get_source_block_id_attributes($source);

    if (empty($shortcode_patterns) && empty($block_namespaces)) {
      return new WP_Error('reacg_migration_replace_unsupported', __('Shortcode/block replacement is not supported for this source.', 'regallery'));
    }

    $replacement_shortcode = '[REACG id="' . intval($migrated_gallery_id) . '"]';
    $replacement_block = $this->build_reacg_gutenberg_block($migrated_gallery_id);

    $post_types = $this->get_replaceable_post_types();
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

      // Replace Gutenberg blocks from this source with reacg/gallery block
      if (!empty($block_namespaces)) {
        $block_replacements = 0;
        $updated_content = $this->replace_source_blocks_in_content(
          $updated_content,
          $block_namespaces,
          $block_id_attrs,
          $source_gallery_id,
          $replacement_block,
          $block_replacements
        );
        $local_replacements += $block_replacements;
      }

      // Replace plain shortcodes (only when no block was found and replaced).
      // Use preg_replace_callback so the replacement string is treated as a
      // literal — avoiding backreference issues with preg_replace.
      if (!empty($shortcode_patterns) && $local_replacements === 0) {
        // If the post is predominantly shortcode-based (no real Gutenberg
        // blocks other than wp:shortcode wrappers), keep the replacement as a
        // plain REACG shortcode so the page stays consistent.
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

      // If a source shortcode inside a wp:shortcode block was replaced by the
      // REACG block, remove the now-redundant wp:shortcode wrapper.
      if ($local_replacements > 0) {
        $updated_content = preg_replace_callback(
          '/<!--\s+wp:shortcode\s+-->\s*(<!--\s+wp:reacg\/gallery[\s\S]*?<!--\s+\/wp:reacg\/gallery\s+-->)\s*<!--\s+\/wp:shortcode\s+-->/i',
          function ($m) { return $m[1]; },
          $updated_content
        );
      }

      // Replace FooGallery Elementor widgets in _elementor_data meta.
      $elementor_replacements = 0;
      if ($source === 'foo') {
        $elementor_replacements = $this->replace_elementor_foo_widget(
          intval($post_id),
          $source_gallery_id,
          $migrated_gallery_id
        );
        $local_replacements += $elementor_replacements;
      }

      if ($local_replacements <= 0 || ($updated_content === $post->post_content && $elementor_replacements === 0)) {
        continue;
      }

      if ($updated_content !== $post->post_content) {
        wp_update_post([
          'ID' => intval($post_id),
          // wp_update_post internally calls wp_unslash/stripslashes on content;
          // wp_slash pre-escapes so the backslashes in \u0022 etc. survive.
          'post_content' => wp_slash($updated_content),
        ]);
      }

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
    } else {
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
      'post_content' => wp_slash($updated_content),
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
    } else {
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

    return preg_replace_callback($pattern, function ($match) use (&$current_index, $target_index, $replacement_content, &$replacements) {
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

    return preg_replace_callback($pattern, function ($match) use (&$current_index, $target_index, $replacement_content, &$replacements) {
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

  /**
   * Returns true when the post content is Gutenberg-block-based, meaning it
   * contains block comments beyond simple wp:shortcode wrappers.
   * Used to decide whether a plain-shortcode replacement should stay as a
   * shortcode or be upgraded to a block.
   */
  /**
   * Replaces FooGallery Elementor widgets (widgetType "foogallery") with the
   * REACG Elementor widget (widgetType "reacg-elementor") in _elementor_data.
   * Returns the number of widgets replaced.
   */
  private function replace_elementor_foo_widget($post_id, $source_gallery_id, $migrated_gallery_id) {
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

    // Flush Elementor's cached CSS/data for this post so the change takes effect.
    if (class_exists('\Elementor\Plugin')) {
      \Elementor\Plugin::$instance->documents->get($post_id, false)?->delete_meta('_elementor_css');
      delete_post_meta($post_id, '_elementor_css');
    }

    return $count;
  }

  /**
   * Recursively walks the Elementor elements tree, replacing any
   * foogallery widget that references $source_gallery_id with a
   * reacg-elementor widget pointing to $migrated_gallery_id.
   */
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

  private function post_is_block_based($content) {
    // Strip wp:shortcode wrappers — they appear in classic-editor posts too.
    $stripped = preg_replace(
      '/<!--\s+wp:shortcode\s+-->[\s\S]*?<!--\s+\/wp:shortcode\s+-->/i',
      '',
      (string) $content
    );
    return (bool) preg_match('/<!--\s+wp:[a-zA-Z]/', $stripped);
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

  private function get_source_block_namespaces($source) {
    if ($source === 'envira') {
      return ['envira-gallery', 'envira'];
    }

    if ($source === 'foo') {
      return ['foogallery', 'foo-gallery', 'fooplugins'];
    }

    if ($source === 'nextgen') {
      return ['ngg', 'nextgen-gallery', 'imagely'];
    }

    return [];
  }

  private function get_source_block_id_attributes($source) {
    if ($source === 'nextgen') {
      return ['galleryId', 'id', 'gallery_id'];
    }

    return ['id'];
  }

  private function replace_source_blocks_in_content($content, $namespaces, $id_attrs, $source_gallery_id, $replacement_block, &$replacements) {
    if (function_exists('parse_blocks') && function_exists('serialize_blocks')) {
      $blocks = parse_blocks((string) $content);
      if (!empty($blocks)) {
        $replacement_parsed = parse_blocks((string) $replacement_block);
        $replacement_node = null;
        foreach ($replacement_parsed as $rp) {
          if (!empty($rp['blockName'])) {
            $replacement_node = $rp;
            break;
          }
        }

        if ($replacement_node) {
          $changed = $this->replace_source_blocks_in_tree($blocks, $namespaces, $id_attrs, $source_gallery_id, $replacement_node, $replacements);
          if ($changed) {
            return serialize_blocks($blocks);
          }
          // Tree walker found nothing — fall through to the regex fallback below.
        }
      }
    }

    // Regex fallback: also runs when parse_blocks found no matching block.
    // Matches any block comment in a known namespace that contains the gallery
    // ID anywhere in its JSON attributes, including self-closing blocks.
    $id_numeric = intval($source_gallery_id);
    $id_escaped = preg_quote((string) $source_gallery_id, '/');
    $id_value_pattern = ':\s*(?:' . $id_numeric . '(?=[\s,}])|"' . $id_escaped . '")';

    foreach ($namespaces as $namespace) {
      $ns = preg_quote($namespace, '/');
      $pattern = '/<!--\s+wp:' . $ns . '\/[a-z0-9_-]+\s+\{[^}]*' . $id_value_pattern . '[^}]*\}[\s\S]*?(?:<!--\s+\/wp:' . $ns . '\/[a-z0-9_-]+\s+-->|\s*\/-->)/i';
      $count = 0;
      $content = preg_replace_callback(
        $pattern,
        function () use ($replacement_block, &$count) {
          $count++;
          return $replacement_block;
        },
        $content
      );
      if ($count > 0) {
        $replacements += $count;
      }
    }

    return $content;
  }

  private function replace_source_blocks_in_tree(&$blocks, $namespaces, $id_attrs, $source_gallery_id, $replacement_node, &$replacements) {
    $changed = false;
    $id_numeric = intval($source_gallery_id);
    $id_string = (string) $source_gallery_id;

    foreach ($blocks as &$block) {
      $block_name = !empty($block['blockName']) ? (string) $block['blockName'] : '';

      if ($block_name !== '') {
        $slash_pos = strpos($block_name, '/');
        $namespace = $slash_pos !== false ? substr($block_name, 0, $slash_pos) : $block_name;

        if (in_array($namespace, $namespaces, true)) {
          $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
          $matched = false;

          foreach ($id_attrs as $id_attr) {
            if (!array_key_exists($id_attr, $attrs)) {
              continue;
            }
            $val = $attrs[$id_attr];
            if ($val === $id_numeric || $val === $id_string || (string) $val === $id_string) {
              $matched = true;
              break;
            }
          }

          if (!$matched) {
            // Fallback: check every scalar attribute value so we match even
            // if the attribute key name is different from what we expect.
            foreach ($attrs as $attr_val) {
              if (!is_int($attr_val) && !is_string($attr_val)) {
                continue;
              }
              if ($attr_val === $id_numeric || $attr_val === $id_string || (string) $attr_val === $id_string) {
                $matched = true;
                break;
              }
            }
          }

          if ($matched) {
            $block = $replacement_node;
            $replacements += 1;
            $changed = true;
            continue;
          }
        }
      }

      if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
        if ($this->replace_source_blocks_in_tree($block['innerBlocks'], $namespaces, $id_attrs, $source_gallery_id, $replacement_node, $replacements)) {
          $changed = true;
        }
      }
    }

    return $changed;
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
      'user_request',
      REACG_CUSTOM_POST_TYPE,
    ];

    return array_values(array_filter($post_types, function ($post_type) use ($excluded) {
      if (!is_string($post_type) || $post_type === '' || in_array($post_type, $excluded, true)) {
        return false;
      }

      $object = get_post_type_object($post_type);
      if (!$object) {
        return false;
      }

      return post_type_supports($post_type, 'editor') || in_array($post_type, ['wp_block', 'wp_template', 'wp_template_part'], true);
    }));
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
