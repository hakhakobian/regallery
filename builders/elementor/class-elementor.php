<?php
/**
 * ReGallery Elementor Integration Manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class REACG_Elementor_Manager {

  /**
   * Hook into Elementor actions.
   *
   * @return void
   */
  public static function init() {
    add_action( 'elementor/widgets/widgets_registered', [ __CLASS__, 'register_widget' ] );
    add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'enqueue_editor_styles' ], 11 );
    add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_editor_scripts' ], 11 );
    add_action( 'elementor/preview/enqueue_styles', [ __CLASS__, 'enqueue_editor_styles' ], 11 );
  }

  /**
   * Register Elementor widget.
   *
   * @return void
   */
  public static function register_widget() {
    if ( defined( 'ELEMENTOR_PATH' ) && class_exists( 'Elementor\Widget_Base' ) ) {
      require_once REACG_PLUGIN_DIR . '/builders/elementor/elementor.php';

      if ( \Elementor\Plugin::instance()->preview->is_preview_mode() ) {
        REACGLibrary::enqueue_scripts();
        wp_enqueue_style(
          REACG_PREFIX . '_elementor',
          REACG_PLUGIN_URL . '/builders/elementor/styles/elementor.css',
          [],
          REACG_VERSION
        );
        wp_enqueue_script(
          REACG_PREFIX . '_elementor_preview',
          REACG_PLUGIN_URL . '/builders/elementor/scripts/elementor.js',
          [ 'jquery' ],
          REACG_VERSION,
          true
        );
      }

      \Elementor\Plugin::instance()->widgets_manager->register( new REACG_Elementor() );
    }
  }

  /**
   * Enqueue Elementor editor styles and modal styles.
   *
   * @return void
   */
  public static function enqueue_editor_styles() {
    REACG_Builder_Common::enqueue_builder_assets();
    wp_enqueue_style(
      REACG_PREFIX . '_elementor',
      REACG_PLUGIN_URL . '/builders/elementor/styles/elementor.css',
      [],
      REACG_VERSION
    );
  }

  /**
   * Enqueue Elementor editor scripts and modal scripts.
   *
   * @return void
   */
  public static function enqueue_editor_scripts() {
    REACG_Builder_Common::enqueue_builder_assets();
    wp_enqueue_script(
      REACG_PREFIX . '_elementor',
      REACG_PLUGIN_URL . '/builders/elementor/scripts/elementor.js',
      [ 'jquery', REACG_PREFIX . '_builder_modal' ],
      REACG_VERSION,
      true
    );
  }
}
