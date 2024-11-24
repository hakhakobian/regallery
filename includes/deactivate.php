<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Deactivate {
  private $obj;
  private $wp_plugin_url = "https://wordpress.org/support/plugin/regallery";

  public function __construct($that) {
    $this->obj = $that;

    if ( strpos(esc_url($_SERVER['REQUEST_URI']), "plugins.php") !== FALSE ) {
      $this->enqueue_scripts();
      add_action('admin_footer', array( $this, 'content' ));
    }

    add_filter('plugin_action_links_' . $this->obj->main_file, array( $this, 'action_links' ));

    add_filter("plugin_row_meta", array($this, 'meta_links'), 10, 2);
  }

  public function content() {
    ?>
    <div class="reacg-deactivate-popup-overlay" style="display: none;"></div>
    <div class="reacg-deactivate-popup" style="display: none;">
      <div class="reacg-deactivate-popup-header">
        <?php esc_html_e("Please let us know why you are deactivating. Your answer will help us to improve the plugin!", 'reacg'); ?>
        <span class="reacg-deactivate-popup-close dashicons dashicons-no"></span>
      </div>
      <div class="reacg-deactivate-popup-body">
        <div>
          <label>
            <strong><?php esc_html_e("Please describe the deactivation reason:", 'reacg'); ?></strong>
            <textarea class="reacg-reason" rows="4" data-version="<?php echo esc_attr($this->obj->version); ?>"></textarea>
          </label>
        </div>
        <label>
          <input type="checkbox" class="reacg-agreement" />
          <?php esc_html_e("By submitting this form your website URL will be collected!", 'reacg'); ?>
        </label>
      </div>
      <div class="reacg-deactivate-popup-footer">
        <span class="spinner"></span>
        <a class="button button-secondary reacg-skip">
          <?php esc_html_e("Skip and Deactivate", 'reacg'); ?>
        </a>
        <a class="button button-primary button-primary-disabled reacg-submit">
          <?php esc_html_e("Submit and Deactivate", 'reacg'); ?>
        </a>
      </div>
    </div>
    <?php
  }

  public function enqueue_scripts() {
    wp_enqueue_style($this->obj->prefix . '_deactive', $this->obj->plugin_url . '/assets/css/deactivation.css', [], $this->obj->version);
    wp_enqueue_script($this->obj->prefix . '_deactive', $this->obj->plugin_url . '/assets/js/deactivation.js', [], $this->obj->version);
  }

  /**
   * Add the plugin meta links.
   *
   * @param $meta_fields
   * @param $file
   *
   * @return mixed
   */
  public function meta_links($meta_fields, $file) {
    if ( $this->obj->main_file === $file ) {
      $meta_fields[] = "<a href='" . esc_url($this->wp_plugin_url . '/#new-post') . "' target='_blank'>" . esc_html__('Ask a question', 'reacg') . "</a>";

      $rating = "<a class='reacg-rating' href='" . esc_url($this->wp_plugin_url . '/reviews#new-post') . "' target='_blank' title='" . esc_html__('Rate', 'reacg') . "'>";
      $rating .= str_repeat("<span class='dashicons dashicons-star-filled'></span>", 5);
      $rating .= "</a>";
      $meta_fields[] = $rating;
    }

    return $meta_fields;
  }

  /**
   * Add action links to the plugin.
   *
   * @param $links
   *
   * @return array
   */
  function action_links( $links ) {
    return array_merge( $links, ["<a href='" . esc_url($this->wp_plugin_url . '/#new-post') . "' target='_blank'>" . esc_html__('Help', 'reacg') . "</a>"] );
  }
}
