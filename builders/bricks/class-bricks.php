<?php
/**
 * ReGallery Bricks Builder Integration Manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class REACG_Bricks_Manager {

  /**
   * Hook into Bricks actions.
   *
   * @return void
   */
  public static function init() {
    add_action( 'init', [ __CLASS__, 'register_element' ], 11 );
    add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_builder_assets' ], 11 );
  }

  /**
   * Register Bricks Builder element.
   *
   * @return void
   */
  public static function register_element() {
    if ( ! class_exists( '\Bricks\Elements' ) ) {
      return;
    }

    \Bricks\Elements::register_element( REACG_PLUGIN_DIR . '/builders/bricks/bricks.php' );
  }

  /**
   * Enqueue Bricks builder modal assets when editing in Bricks.
   *
   * @return void
   */
  public static function enqueue_builder_assets() {
    if ( ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) || ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) ) {
      wp_enqueue_style( 'dashicons' );
      if ( class_exists( 'REACG' ) && method_exists( REACG(), 'register_admin_scripts' ) ) {
        REACG()->register_admin_scripts();
      }
      REACG_Builder_Common::enqueue_builder_assets();
      wp_enqueue_script(
        REACG_PREFIX . '_bricks',
        REACG_PLUGIN_URL . '/builders/bricks/bricks.js',
        [ 'jquery', REACG_PREFIX . '_builder_modal', REACG_PREFIX . '_thumbnails' ],
        REACG_VERSION,
        true
      );
    }
  }
}
