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
    if ( ! function_exists( 'bricks_is_builder' ) || ! bricks_is_builder() ) {
      return;
    }

    $is_main = function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main();

    if ( $is_main ) {
      // Builder UI document: hosts the floating panel and the admin stack.
      if ( class_exists( 'REACG' ) && method_exists( REACG(), 'register_admin_scripts' ) ) {
        REACG()->register_admin_scripts();
      }
      REACG_Builder_Common::enqueue_builder_assets();
      $deps = [ 'jquery', REACG_PREFIX . '_builder_modal', REACG_PREFIX . '_thumbnails' ];
    } else {
      // Canvas iframe: only what the preview needs. The admin stylesheets must
      // not load here, they restyle Bricks' own elements.
      REACG_Builder_Common::enqueue_placeholder_styles();
      REACGLibrary::enqueue_scripts();
      $deps = [ 'jquery', REACG_PREFIX . '_thumbnails' ];
    }

    wp_enqueue_script(
      REACG_PREFIX . '_bricks',
      REACG_PLUGIN_URL . '/builders/bricks/bricks.js',
      $deps,
      filemtime( REACG_PLUGIN_DIR . '/builders/bricks/bricks.js' ),
      true
    );
  }
}
