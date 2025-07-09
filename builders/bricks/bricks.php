<?php

class REACGBricksElement extends \Bricks\Element {
  public $category = 'media';
  public $name = 'reacg';
  public $icon = 'reacg-icon';
  public $scripts = [ 'reacg_loadApp' ]; // JS function that run when an element is rendered on the frontend or updated in the builder.

  public function get_label() {
    return REACG_NICENAME;
  }

  public function set_controls() {
    $this->controls['gallery_id'] = [
      'tab' => 'content',
      'label' => esc_html__('Select gallery', 'reacg'),
      'type' => 'select',
      'options' => REACGLibrary::get_shortcodes(FALSE, TRUE, FALSE),
      'inline' => false,
      'clearable' => false,
      'pasteStyles' => false,
      'default' => 0,
      'placeholder' => esc_html__('Select gallery', 'reacg'),
      'description' => sprintf(__('Add/edit galleries %shere%s.', 'reacg'), '<a target="_blank" href="' . add_query_arg(array( 'post_type' => 'reacg' ), admin_url('edit.php')) . '">', '</a>'),
    ];

    $this->controls['enable_options'] = [
      'tab' => 'content',
      'type' => 'checkbox',
      'label' => esc_html__('Enable options section', 'reacg'),
      'small' => true,
      'default' => false,
      'description' => esc_html__('Visible only in editor mode.', 'reacg'),
    ];
  }

  public function enqueue_scripts() {
    wp_enqueue_script(REACG_PREFIX . '_bricks', REACG_PLUGIN_URL . '/builders/bricks/bricks.js', [ REACG_PREFIX . '_thumbnails' ], '1.0', true);
    // Set nonce to make the rest calls work in builder (the admin scripts are not available).
    wp_localize_script(REACG_PREFIX . '_bricks', 'reacg', array(
      'rest_nonce' => REACG_REST_NONCE,
    ));
  }

  public function render() {
    $settings = $this->settings;
    $gallery_id = isset($settings['gallery_id']) ? (int) $settings['gallery_id'] : 0;
    $enable_options = isset($settings['enable_options']) && $this->is_bricks_edit_context() ? (int) $settings['enable_options'] : 0;
    echo "<div {$this->render_attributes( '_root' )}>";
    echo REACGLibrary::get_rest_routs($gallery_id, $enable_options);
    echo '</div>';
  }

  private function is_bricks_edit_context() {
    // Bricks builder preview iframe.
    if (function_exists('bricks_is_builder_main') && bricks_is_builder_main()) {
      return true;
    }

    // Bricks builder main panel (non-iframe).
    if (function_exists('bricks_is_builder') && bricks_is_builder()) {
      return true;
    }
    // Bricks-related REST/AJAX calls.
    if (
      isset($_SERVER['HTTP_REFERER']) &&
      strpos($_SERVER['HTTP_REFERER'], 'bricks=run') !== false
    ) {
      return true;
    }

    return false;
  }
}
