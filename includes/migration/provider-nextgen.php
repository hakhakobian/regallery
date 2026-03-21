<?php

defined('ABSPATH') || die('Access Denied');

class REACG_Migration_Provider_NextGEN implements REACG_Migration_Provider_Interface {
  public function get_key() {
    return 'nextgen';
  }

  public function get_label() {
    return 'NextGEN Gallery';
  }

  public function is_available() {
    global $wpdb;

    $galleries_table = $wpdb->prefix . 'ngg_gallery';
    $pictures_table = $wpdb->prefix . 'ngg_pictures';

    return $this->table_exists($galleries_table) && $this->table_exists($pictures_table);
  }

  public function list_galleries($args = []) {
    global $wpdb;

    $page = !empty($args['page']) ? max(1, intval($args['page'])) : 1;
    $per_page = !empty($args['per_page']) ? max(1, intval($args['per_page'])) : 50;
    $search = !empty($args['search']) ? sanitize_text_field($args['search']) : '';

    $offset = ($page - 1) * $per_page;
    $galleries_table = $wpdb->prefix . 'ngg_gallery';
    $pictures_table = $wpdb->prefix . 'ngg_pictures';

    $where = 'WHERE 1=1';
    $params = [];
    if ($search !== '') {
      $where .= ' AND (g.title LIKE %s OR g.name LIKE %s)';
      $like = '%' . $wpdb->esc_like($search) . '%';
      $params[] = $like;
      $params[] = $like;
    }

    $sql = "
      SELECT g.gid, g.title, g.name, COUNT(p.pid) AS image_count
      FROM {$galleries_table} g
      LEFT JOIN {$pictures_table} p ON p.galleryid = g.gid AND p.exclude != 1
      {$where}
      GROUP BY g.gid
      ORDER BY g.gid DESC
      LIMIT %d OFFSET %d
    ";
    $params[] = $per_page;
    $params[] = $offset;
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

    $count_sql = "SELECT COUNT(*) FROM {$galleries_table} g {$where}";
    $count_params = array_slice($params, 0, max(0, count($params) - 2));
    if (!empty($count_params)) {
      $total = intval($wpdb->get_var($wpdb->prepare($count_sql, $count_params)));
    } else {
      $total = intval($wpdb->get_var($count_sql));
    }

    $items = [];
    foreach ((array) $rows as $row) {
      $title = !empty($row['title']) ? $row['title'] : (!empty($row['name']) ? $row['name'] : __('(no title)', 'regallery'));
      $items[] = [
        'id' => (string) intval($row['gid']),
        'title' => $title,
        'image_count' => intval($row['image_count']),
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

    $gallery_id = intval($external_id);
    if (!$gallery_id) {
      return new WP_Error('reacg_migration_invalid_gallery', __('Invalid NextGEN gallery.', 'regallery'));
    }

    $galleries_table = $wpdb->prefix . 'ngg_gallery';
    $pictures_table = $wpdb->prefix . 'ngg_pictures';

    $gallery = $wpdb->get_row(
      $wpdb->prepare("SELECT gid, title, name, path FROM {$galleries_table} WHERE gid = %d", $gallery_id),
      ARRAY_A
    );

    if (empty($gallery)) {
      return new WP_Error('reacg_migration_not_found', __('NextGEN gallery not found.', 'regallery'));
    }

    $pictures = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT pid, filename, alttext, description, meta_data FROM {$pictures_table} WHERE galleryid = %d AND exclude != 1 ORDER BY sortorder ASC, pid ASC",
        $gallery_id
      ),
      ARRAY_A
    );

    $items = [];
    foreach ((array) $pictures as $picture) {
      $url = $this->build_picture_url($gallery['path'], $picture['filename']);
      $attachment_id = $url ? attachment_url_to_postid($url) : 0;
      $item = [
        'title' => !empty($picture['alttext']) ? $picture['alttext'] : '',
        'description' => !empty($picture['description']) ? $picture['description'] : '',
      ];

      if ($attachment_id) {
        $item['attachment_id'] = intval($attachment_id);
      } elseif (!empty($url)) {
        $item['url'] = esc_url_raw($url);
      }

      $meta = maybe_unserialize($picture['meta_data']);
      if (is_array($meta) && !empty($meta['meta_data']['title'])) {
        $item['title'] = sanitize_text_field($meta['meta_data']['title']);
      }

      $items[] = $item;
    }

    $title = !empty($gallery['title']) ? $gallery['title'] : (!empty($gallery['name']) ? $gallery['name'] : __('Imported NextGEN Gallery', 'regallery'));

    return [
      'id' => (string) $gallery_id,
      'title' => $title,
      'items' => $items,
    ];
  }

  public function get_shortcode_patterns($source_gallery_id) {
    $id = preg_quote((string) $source_gallery_id, '/');

    return [
      '/\\[(?:ngg|nggallery)\\b[^\\]]*\\bid\\s*=\\s*(?:"|\')?' . $id . '(?:"|\')?[^\\]]*\\]/i',
      '/\\[(?:ngg|nggallery)\\b(?=[^\\]]*\\bids\\s*=\\s*(?:"|\')?' . $id . '(?:"|\')?)[^\\]]*\\]/i',
    ];
  }

  public function get_block_namespaces() {
    return ['ngg', 'nextgen-gallery', 'imagely'];
  }

  public function get_block_id_attributes() {
    return ['galleryId', 'id', 'gallery_id'];
  }

  public function prefer_shortcode_replacement() {
    return false;
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
      ['%[ngg%', '%[nggallery%', '%wp:ngg%', '%wp:nextgen-gallery%', '%wp:imagely%'],
      [
        '/\\[ngg(?:allery)?\\b[^\\]]*\\bid\\s*=\\s*["\']?' . preg_quote($source_gallery_id, '/') . '["\'\\s\\]]/i',
        '/wp:(?:ngg|nextgen-gallery|imagely)[^\\}]*\\b' . preg_quote((string) $id_numeric, '/') . '\\b/i',
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

  private function table_exists($table_name) {
    global $wpdb;

    $table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
    return $table === $table_name;
  }

  private function build_picture_url($path, $filename) {
    if (empty($path) || empty($filename)) {
      return '';
    }

    $relative_path = ltrim((string) $path, '/');
    if (strpos($relative_path, 'http://') === 0 || strpos($relative_path, 'https://') === 0) {
      return trailingslashit($relative_path) . ltrim((string) $filename, '/');
    }

    if (strpos($relative_path, 'wp-content/') === 0) {
      return content_url('/' . substr($relative_path, strlen('wp-content/')) . '/' . ltrim((string) $filename, '/'));
    }

    return site_url('/' . $relative_path . '/' . ltrim((string) $filename, '/'));
  }
}
