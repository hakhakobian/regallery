<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Posts {
  private $obj;

  public function __construct($that) {
    $this->obj = $that;
    add_action('wp_ajax_reacg_get_posts', [$this, 'get_posts'] );
  }

  private function get_taxonomies( $post_type, $saved_data ) {
    // Organize saved data.
    $saved_taxonomies = [];
    foreach ( $saved_data as $data ) {
      $term = explode(":", $data);
      $term_taxonomy = isset($term[0]) ? $term[0] : '';
      $term_id = isset($term[1]) ? $term[1] : 0;
      $saved_taxonomies[$term_taxonomy][] = $term_id;
    }

    // Get taxonomy and terms by post type.
    $taxonomies = get_object_taxonomies($post_type, 'objects');
    $result = [];
    foreach ( $taxonomies as $taxonomy ) {
      $terms = get_terms([
                           'taxonomy' => $taxonomy->name,
                           'hide_empty' => TRUE,
                         ]);
      if ( !empty($terms) ) {
        $result[$taxonomy->name] = [];
        foreach ( $terms as $term ) {
          $result[$taxonomy->name][] = [
            'id' => $term->term_id,
            'name' => $term->name,
            'selected' => !empty($saved_taxonomies[$taxonomy->name]) && in_array($term->term_id, $saved_taxonomies[$taxonomy->name]),
          ];
        }
      }
    }

    return $result;
  }

  public function get_posts() {
    if ( !wp_verify_nonce($_GET[REACG_NONCE]) ) {
      wp_die();
    }
    $select_type_defaults = ['manual', 'dynamic'];
    $select_type = !empty($_POST['select_type']) && in_array($_POST['select_type'], $select_type_defaults) ? esc_sql($_POST['select_type']) : '';

    $type = !empty($_POST['type']) && array_key_exists($_POST['type'], REACG_ALLOWED_POST_TYPES) ? esc_sql($_POST['type']) : 'post';
    $type_title = REACG_ALLOWED_POST_TYPES[$type]['title'];

    $gallery_id = !empty($_POST['gallery_id']) ? intval($_POST['gallery_id']) : 0;

    $additional_data_arr = ['taxonomies' => [], 'relation' => '', 'exclude' => [], 'exclude_without_image' => 0];
    $additional_data = get_post_meta( $gallery_id, 'additional_data', TRUE );

    if ( empty($select_type) ) {
      ob_end_clean();
      ob_start();
      ?>
      <button type="button" class="button button-hero reacg_select_type" data-select-type="manual" title="<?php echo esc_html(sprintf(__('Select %s manually', 'reacg'), $type_title)); ?>"><?php echo esc_html__("Manual selection", "reacg"); ?></button>
      <button type="button" class="button button-hero reacg_select_type" data-select-type="dynamic" <?php disabled(!empty($additional_data)); ?> title="<?php echo esc_html(!empty($additional_data) ? sprintf(__('Dynamic %s already added', 'reacg'), $type_title) : sprintf(__('Select %s dynamically', 'reacg'), $type_title)); ?>"><?php echo esc_html__("Dynamic", "reacg"); ?></button>
      <?php
      echo ob_get_clean();
    }
    elseif ( $select_type === "dynamic" ) {

      $relation = ["or" => __("OR", "reacg"), "and" => __("AND", "reacg")];

      if ( !empty($additional_data) ) {
        $additional_data_arr = json_decode($additional_data, TRUE);
      }

      $taxonomies = $this->get_taxonomies($type, $additional_data_arr['taxonomies']);

      $args = [
        'post_type' => $type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
      ];
      $posts = get_posts($args);

      ob_end_clean();
      ob_start();
      ?>
      <label for="reacg_taxanomies"><?php esc_html_e('Taxanomies', 'reacg'); ?></label>
      <select multiple="multiple" name="reacg_taxanomies[]" id="reacg_taxanomies" class="reacg_searchable_select reacg_change_listener"  style="width: 100%">
        <?php
        foreach ( $taxonomies as $taxonomy => $terms ) {
          ?>
          <optgroup label="<?php echo esc_html(ucfirst(str_replace("_", "", $taxonomy))); ?>">
            <?php
            foreach ( $terms as $term ) {
              $value = $taxonomy . ":" . $term['id'];
              $title = $term['name'];
              ?>
              <option
                value="<?php echo esc_attr($value); ?>" <?php selected(TRUE, $term['selected']); ?>><?php echo esc_html($title); ?></option>
              <?php
            }
            ?>
          </optgroup>
          <?php
        }
        ?>
      </select>
      <label for="reacg_relation"><?php esc_html_e('Relation', 'reacg'); ?></label>
      <select name="reacg_relation" id="reacg_relation" class="reacg_searchable_select reacg_change_listener" style="width: 100%">
        <?php
        foreach ( $relation as $value => $name ) {
          ?>
          <option
            value="<?php echo esc_attr($value); ?>" <?php selected($value, $additional_data_arr['relation']); ?>><?php echo esc_html($name); ?></option>
          <?php
        }
        ?>
      </select>
      <label for="reacg_exclude"><?php echo sprintf(__('Exclude %s', 'reacg'), $type_title); ?></label>
      <select multiple="multiple" name="reacg_exclude" id="reacg_exclude" class="reacg_searchable_select reacg_change_listener" style="width: 100%">
        <?php
        foreach ( $posts as $post ) {
          $title = get_the_title($post->ID);
          if ( empty($title) ) {
            $title = __('(no title)', 'reacg');
          }
          ?>
          <option
            value="<?php echo intval($post->ID); ?>" <?php selected(TRUE, in_array($post->ID, $additional_data_arr['exclude'])); ?>><?php echo esc_html($title); ?></option>
          <?php
        }
        ?>
      </select>
      <label for="reacg_exclude_without_image"><?php echo sprintf(__('Exclude %s without images', 'reacg'), $type_title); ?></label>
      <input type="checkbox"
             name="reacg_exclude_without_image"
             id="reacg_exclude_without_image"
             class="reacg_change_listener"
        <?php checked(TRUE, !empty($additional_data_arr['exclude_without_image'])); ?>/>
      <?php
      echo ob_get_clean();
    }
    elseif ( $select_type === "manual" ) {
      $s = !empty($_POST['s']) ? esc_sql($_POST['s']) : '';

      $orderby_defaults = [
        'date' => __('Date', 'reacg'),
        'title' => __('Title', 'reacg'),
        'comment_count' => __('Comment count', 'reacg'),
        'menu_order' => __('Menu order', 'reacg'),
      ];
      $order_defaults = [
        'ASC' => __('Ascending', 'reacg'),
        'DESC' => __('Descending', 'reacg'),
      ];
      $orderby = !empty($_POST['orderby']) && array_key_exists($_POST['orderby'], $orderby_defaults) ? esc_sql($_POST['orderby']) : 'date';
      $order = !empty($_POST['order']) && array_key_exists($_POST['order'], $order_defaults) ? esc_sql($_POST['order']) : 'DESC';

      $args = [
        'post_type' => $type,
        'post_status' => 'publish',
        's' => $s,
        'posts_per_page' => -1,
        'orderby' => $orderby,
        'order' => $order,
      ];

      $query = new WP_Query($args);

      ob_start();
      ?>
      <div class="media-toolbar">
        <div class="media-toolbar-secondary">
          <h2 class="media-attachments-filter-heading"><?php echo esc_html__("Order", "reacg"); ?></h2>
          <label for="media-attachment-order-by" class="screen-reader-text"><?php echo esc_html__("Order by", "reacg"); ?></label>
          <select id="media-attachment-order-by" class="posts-filters">
            <?php
            foreach ( $orderby_defaults as $key => $value ) {
              ?>
              <option <?php echo $key === $orderby ? "selected" : ""; ?> value="<?php echo esc_html($key); ?>"><?php echo esc_html($value); ?></option>
              <?php
            }
            ?>
          </select>
          <select id="media-attachment-order" class="posts-filters">
            <?php
            foreach ( $order_defaults as $key => $value ) {
              ?>
              <option <?php echo $key === $order ? "selected" : ""; ?> value="<?php echo esc_html($key); ?>"><?php echo esc_html($value); ?></option>
              <?php
            }
            ?>
          </select>
          <span class="spinner"></span>
        </div>
        <div class="media-toolbar-primary search-form">
          <label for="media-search-input" class="media-search-input-label"><?php echo esc_html__("Search", "reacg"); ?></label>
          <input type="search" id="media-search-input" class="search" value="<?php echo esc_html($s); ?>"/>
        </div>
        <div class="media-bg-overlay" style="display: none;"></div>
      </div>
      <div class="attachments-wrapper">
        <?php
        if ( $query->have_posts() ) {
        ?>
        <ul tabindex="-1" class="attachments ui-sortable ui-sortable-disabled">
      <?php
      while ($query->have_posts()) {
        $query->the_post();
        $id = get_the_ID();
        $title = get_the_title();
        $thumbnail = get_the_post_thumbnail_url($id, 'thumbnail') ?: $this->obj->plugin_url . $this->obj->no_image;
        ?>
        <li tabindex="0" role="checkbox" aria-label="<?php echo esc_html($title); ?>" title="<?php echo esc_html($title); ?>" aria-checked="false"
            data-id="<?php echo esc_attr($type . $id); ?>"
            data-title="<?php echo esc_html($title); ?>"
            data-thumbnail="<?php echo esc_url($thumbnail); ?>"
            class="attachment save-ready">
          <div class="attachment-preview js--select-attachment type-image subtype-png landscape">
            <div class="thumbnail">
              <div class="centered">
                <img src="<?php echo esc_url($thumbnail); ?>" draggable="false" alt="<?php echo esc_html($title); ?>" />
              </div>
            </div>
          </div>
          <button type="button" class="check" tabindex="-1">
            <span class="media-modal-icon"></span>
            <span class="screen-reader-text"><?php echo esc_html__("Deselect", "reacg"); ?></span>
          </button>
        </li>
        <?php
      }
      ?>
      </ul>
          <?php
        }
        else {
          ?>
          <div class="uploader-inline">
            <div class="uploader-inline-content has-upload-message">
              <h2 class="upload-message"><?php echo esc_html__("No items found.", "reacg"); ?></h2>
            </div>
          </div>
          <?php
        }
          ?>
      </div>
      <?php
      echo ob_get_clean();
      wp_reset_postdata();
    }

    wp_die();
  }
}
