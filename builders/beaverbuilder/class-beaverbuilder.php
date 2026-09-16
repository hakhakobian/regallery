<?php
/**
 * ReGallery Beaver Builder Integration Manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class REACG_BeaverBuilder_Manager {

  /**
   * Hook into Beaver Builder actions.
   *
   * @return void
   */
  public static function init() {
    add_action( 'init', [ __CLASS__, 'register_module' ], 11 );
    add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_builder_assets' ], 11 );
    add_action( 'fl_builder_ui_enqueue_scripts', [ __CLASS__, 'enqueue_builder_assets' ], 11 );
  }

  /**
   * Register Beaver Builder module.
   *
   * @return void
   */
  public static function register_module() {
    if ( class_exists( 'FLBuilder' ) ) {
      require_once REACG_PLUGIN_DIR . '/builders/beaverbuilder/beaverbuilder.php';
    }
  }

  /**
   * Enqueue Beaver Builder modal assets when editing in Beaver Builder.
   *
   * @return void
   */
  public static function enqueue_builder_assets() {
    if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
      wp_enqueue_style( 'dashicons' );
      REACG_Builder_Common::enqueue_builder_assets();
      wp_enqueue_style(
        REACG_PREFIX . '_beaver_builder',
        REACG_PLUGIN_URL . '/builders/beaverbuilder/beaverbuilder.css',
        [ 'dashicons', REACG_PREFIX . '_builder_modal' ],
        REACG_VERSION
      );
      wp_enqueue_script(
        REACG_PREFIX . '_beaver_builder',
        REACG_PLUGIN_URL . '/builders/beaverbuilder/scripts/beaverbuilder.js',
        [ 'jquery', REACG_PREFIX . '_builder_modal', REACG_PREFIX . '_thumbnails' ],
        filemtime( REACG_PLUGIN_DIR . '/builders/beaverbuilder/scripts/beaverbuilder.js' ),
        true
      );
      wp_localize_script(
        REACG_PREFIX . '_beaver_builder',
        'reacg_bb_data',
        [
          'rest_nonce' => defined( 'REACG_REST_NONCE' ) ? REACG_REST_NONCE : wp_create_nonce( 'wp_rest' ),
          'plugin_url' => REACG_PLUGIN_URL,
        ]
      );
    }
  }
}
