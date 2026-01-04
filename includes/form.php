<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Form {
  private $obj;

  public function __construct($that) {
    $this->obj = $that;

    if ( is_admin() ) {
      $this->enqueue_scripts();
      add_action('admin_footer', array( $this, 'content' ));
    }
  }

  public function content() {
    $reasons = [
      __("Learn about Pro features", "regallery"),
      __("Technical question", "regallery"),
      __("Plugin demo", "regallery"),
      "other" => __("I have an issue", "regallery"),
    ];
    $current_user = wp_get_current_user();
    $email = $current_user->exists() ? $current_user->user_email : "";
    ?>
    <div class="reacg-form-popup-overlay" style="display: none;">
      <div class="reacg-form-popup" data-version="<?php echo esc_attr($this->obj->version); ?>">
        <div class="reacg-form-popup-header">
          <?php esc_html_e("Book a Call", 'regallery'); ?>
          <span class="reacg-form-popup-close dashicons dashicons-no"></span>
        </div>
        <div class="reacg-form-popup-body">
          <div class="reacg-note-wrapper">
            <?php esc_html_e("Choose the reason for your call so we can prepare in advance and help you faster.", 'regallery'); ?>
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
              <strong><?php esc_html_e("Please describe the issue:", 'regallery'); ?></strong>
              <textarea class="reacg-reason" rows="4"></textarea>
            </label>
          </div>
        </div>
        <div class="reacg-form-popup-footer">
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
              <?php esc_html_e("Book a Call", 'regallery'); ?>
            </a>
            <span class="spinner"></span>
            <a class="button reacg-skip">
              <?php esc_html_e("Cancel", 'regallery'); ?>
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php
  }

  public function enqueue_scripts() {
    wp_enqueue_style($this->obj->prefix . '_form', $this->obj->plugin_url . '/assets/css/form.css', [], $this->obj->version);
    wp_enqueue_script($this->obj->prefix . '_form', $this->obj->plugin_url . '/assets/js/form.js', ['jquery'], $this->obj->version);
  }
}
