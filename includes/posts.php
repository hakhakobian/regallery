<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Posts {
  private $obj;

  public function __construct($that) {
    $this->obj = $that;
    add_action('wp_ajax_reacg_get_posts', [$this, 'get_posts'] );
  }

  public function get_posts() {
    if ( !wp_verify_nonce($_GET[REACG_NONCE]) ) {
      wp_die();
    }
    $s = !empty($_POST['s']) ? esc_sql($_POST['s']) : '';
    $type_defaults = array_column(REACG_ALLOWED_POST_TYPES, 'type');
    $type = !empty($_POST['type']) && in_array($_POST['type'], $type_defaults) ? esc_sql($_POST['type']) : 'post';

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

    wp_die();
  }
}
