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
      'type' => 'text',
      'default' => 0,
    ];

    $this->controls['manage_galleries_desc'] = [
      'tab' => 'content',
      'type' => 'info',
      'content' => REACG_Builder_Common::get_builder_control_description(),
    ];
  }

  public function enqueue_scripts() {
    wp_enqueue_style('dashicons');
    wp_enqueue_script(REACG_PREFIX . '_bricks', REACG_PLUGIN_URL . '/builders/bricks/bricks.js', [ 'jquery', REACG_PREFIX . '_thumbnails' ], REACG_VERSION, true);
    // Set nonce to make the rest calls work in builder (the admin scripts are not available).
    wp_localize_script(REACG_PREFIX . '_bricks', 'reacg', array(
      'rest_nonce' => REACG_REST_NONCE,
      'plugin_url' => REACG_PLUGIN_URL,
    ));

    if ( $this->is_bricks_edit_context() ) {
      REACG_Builder_Common::enqueue_builder_assets();
    }
  }

  public function render() {
    $settings = $this->settings;
    $gallery_id = isset($settings['gallery_id']) ? (int) $settings['gallery_id'] : 0;

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo "<div " . $this->render_attributes( '_root' ) . ">";

    if ( $this->is_bricks_edit_context() ) {
      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      echo REACG_Builder_Common::render_builder_editor($gallery_id, $this->id);
    } else {
      if ( $gallery_id !== 0 ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo REACGLibrary::get_rest_routs($gallery_id, FALSE, $this->id);
      }
    }
    echo "</div>";
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

    // Bricks front-end preview should not be treated as edit mode.
    if ( isset($_GET['bricks_preview']) && '' !== (string) $_GET['bricks_preview'] ) {
      return false;
    }

    // Bricks-related REST/AJAX calls.
    if ( !empty($_SERVER['HTTP_REFERER']) ) {
      $referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
      $query = wp_parse_url( $referer, PHP_URL_QUERY );
      $params = [];

      if ( !empty( $query ) ) {
        parse_str( $query, $params );
      }

      $is_bricks_run = isset( $params['bricks'] ) && 'run' === $params['bricks'];

      if ( $is_bricks_run ) {
        return true;
      }
    }

    return false;
  }
}
