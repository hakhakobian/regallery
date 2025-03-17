<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Deactivate {
  private $obj;

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
    $reasons = [
      "hard_to" => __("It is hard to use", "reacg"),
      "no_feature" => __("I didn't find the features I needed", "reacg"),
      "temporary" => __("It's a temporary deactivation", "reacg"),
      "other" => __("Other", "reacg"),
    ];
    $current_user = wp_get_current_user();
    $email = $current_user->exists() ? $current_user->user_email : "";
    ?>
    <div class="reacg-deactivate-popup-overlay" style="display: none;">
      <div class="reacg-deactivate-popup" data-version="<?php echo esc_attr($this->obj->version); ?>">
        <div class="reacg-deactivate-popup-header">
          <?php esc_html_e("Quick feedback", 'reacg'); ?>
          <span class="reacg-deactivate-popup-close dashicons dashicons-no"></span>
        </div>
        <div class="reacg-deactivate-popup-body">
          <div class="reacg-note-wrapper">
            <?php esc_html_e("We are sorry to see you go!", 'reacg'); ?>😔
            <?php esc_html_e("If you have a moment, please share your thoughts: it helps us improve and make things better for everyone.", 'reacg'); ?>
          </div>
          <div class="reacg-reasonType-wrapper">
            <?php
            foreach ( $reasons as $key => $reason ) {
              ?>
              <div>
                <label>
                  <input type="radio" class="reacg-reasonType" name="reacg-reasonType" value="<?php echo esc_attr($key); ?>" alt="<?php echo esc_attr($reason); ?>" />
                  <?php echo esc_attr($reason); ?>
                </label>
              </div>
              <?php
            }
            ?>
          </div>
          <div class="reacg-reason-wrapper" style="display: none;">
            <label>
              <strong><?php esc_html_e("Please describe the deactivation reason:", 'reacg'); ?></strong>
              <textarea class="reacg-reason" rows="4"></textarea>
            </label>
          </div>
        </div>
        <div class="reacg-deactivate-popup-footer">
          <div class="reacg-agreement-wrapper">
            <label>
              <input type="hidden" name="reacg-email" value="<?php echo sanitize_email($email); ?>" />
              <input type="checkbox" class="reacg-agreement" />
              <?php esc_html_e("By submitting this form your email and website URL will be collected!", 'reacg'); ?>
            </label>
          </div>
          <div class="reacg-buttons-wrapper">
            <a class="button button-secondary reacg-submit">
              <?php esc_html_e("Submit and Deactivate", 'reacg'); ?>
            </a>
            <span class="spinner"></span>
            <a class="button reacg-skip">
              <?php esc_html_e("Skip and Deactivate", 'reacg'); ?>
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php
  }

  public function enqueue_scripts() {
    wp_enqueue_style($this->obj->prefix . '_deactive', $this->obj->plugin_url . '/assets/css/deactivation.css', [], $this->obj->version);
    wp_enqueue_script($this->obj->prefix . '_deactive', $this->obj->plugin_url . '/assets/js/deactivation.js', ['jquery'], $this->obj->version);
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
      $meta_fields[] = "<a href='" . esc_url(REACG_WP_PLUGIN_SUPPORT_URL) . "' target='_blank'>" . esc_html__('Ask a question', 'reacg') . "</a>";

      $rating = "<a class='reacg-rating' href='" . esc_url(REACG_WP_PLUGIN_REVIEW_URL) . "' target='_blank' title='" . esc_html__('Rate', 'reacg') . "'>";
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
    $additional_links = [
      "<a href='" . esc_url('https://regallery.team/#faq') . "' target='_blank'>" . esc_html__('FAQ', 'reacg') . "</a>",
      "<a href='" . esc_url(REACG_WP_PLUGIN_SUPPORT_URL) . "' target='_blank'>" . esc_html__('Help', 'reacg') . "</a>",
    ];
    return array_merge( $links, $additional_links );
  }
}
