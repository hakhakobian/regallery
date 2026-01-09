<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Deactivate {
  private $obj;

  public function __construct($that) {
    $this->obj = $that;

    $request_uri = !empty($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    if ( strpos($request_uri, "plugins.php") !== FALSE ) {
      $this->enqueue_scripts();
      add_action('admin_footer', array( $this, 'content' ));
    }

    add_filter('plugin_action_links_' . $this->obj->main_file, array( $this, 'action_links' ));

    add_filter("plugin_row_meta", array($this, 'meta_links'), 10, 2);
  }

  public function content() {
    $reasons = [
      "hard_to" => __("It is hard to use", "regallery"),
      "no_feature" => __("I didn't find the features I needed", "regallery"),
      "temporary" => __("It's a temporary deactivation", "regallery"),
      "other" => __("I'm open to a quick call to fix this together", "regallery"),
    ];
    $current_user = wp_get_current_user();
    $email = $current_user->exists() ? $current_user->user_email : "";
    ?>
    <div class="reacg-deactivate-popup-overlay" style="display: none;">
      <div class="reacg-deactivate-popup" data-version="<?php echo esc_attr($this->obj->version); ?>">
        <div class="reacg-deactivate-popup-header">
          <?php esc_html_e("Quick feedback", 'regallery'); ?>
          <span class="reacg-deactivate-popup-close dashicons dashicons-no"></span>
        </div>
        <div class="reacg-deactivate-popup-body">
          <div class="reacg-note-wrapper">
            <?php esc_html_e("We are sorry to see you go!", 'regallery'); ?>😔
            <?php esc_html_e("If you have a moment, please share your thoughts: it helps us improve and make things better for everyone.", 'regallery'); ?>
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
              <strong><?php esc_html_e("Please describe the deactivation reason:", 'regallery'); ?></strong>
              <textarea class="reacg-reason" rows="4"></textarea>
            </label>
          </div>
        </div>
        <div class="reacg-deactivate-popup-footer">
          <div class="reacg-email-wrapper">
            <input type="email" name="reacg-email" placeholder="<?php esc_html_e("Please enter your email", 'regallery'); ?>" value="<?php echo esc_attr(sanitize_email($email)); ?>" />
          </div>
          <div class="reacg-agreement-wrapper">
            <label>
              <input type="checkbox" class="reacg-agreement" />
              <?php esc_html_e("By submitting this form your email and website URL will be collected!", 'regallery'); ?>
            </label>
          </div>
          <div class="reacg-buttons-wrapper">
            <a class="button button-primary reacg-submit" disabled="disabled">
              <?php esc_html_e("Submit and Deactivate", 'regallery'); ?>
            </a>
            <span class="spinner"></span>
            <a class="button reacg-skip">
              <?php esc_html_e("Skip and Deactivate", 'regallery'); ?>
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php
  }

  public function enqueue_scripts() {
    wp_enqueue_style($this->obj->prefix . '_deactive', $this->obj->plugin_url . '/assets/css/deactivation.css', [], $this->obj->version);
    wp_enqueue_script($this->obj->prefix . '_deactive', $this->obj->plugin_url . '/assets/js/deactivation.js', ['jquery'], $this->obj->version, TRUE);
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
      $meta_fields[] = "<a href='" . esc_url(REACG_WP_PLUGIN_SUPPORT_URL) . "' target='_blank'>" . esc_html__('Ask a question', 'regallery') . "</a>";

      $rating = "<a class='reacg-rating' href='" . esc_url(REACG_WP_PLUGIN_REVIEW_URL) . "' target='_blank' title='" . esc_html__('Rate', 'regallery') . "'>";
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
      "<a href='" . esc_url(add_query_arg(['utm_medium' => 'plugins_list', 'utm_campaign' => 'faq'], REACG_WEBSITE_URL_UTM . '#faq')) . "' target='_blank'>" . esc_html__('FAQ', 'regallery') . "</a>",
      "<a href='" . esc_url(REACG_WP_PLUGIN_SUPPORT_URL) . "' target='_blank'>" . esc_html__('Help', 'regallery') . "</a>",
      !REACG_PLAYGROUND ? "<a href='" . esc_url(add_query_arg(['utm_medium' => 'plugins_list', 'utm_campaign' => 'upgrade'], REACG_WEBSITE_URL_UTM . '#pricing')) . "' target='_blank' class='reacg-upgrade reacg-hidden'>" . REACG_BUY_NOW_TEXT . "</a>" : "",
    ];
    return array_merge( $links, $additional_links );
  }
}
