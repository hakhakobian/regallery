<?php
/**
 * ReGallery Divi 4 extension.
 *
 * DiviExtension enqueues scripts/builder-bundle.min.js in the Visual Builder
 * and styles/style.min.css on the frontend. On top of that, the Visual
 * Builder's top window gets the floating ReGallery panel and the admin stack;
 * those assets are tagged so Divi leaves them out of the app frame copy.
 */

defined('ABSPATH') || die('Access Denied');

require_once REACG_PLUGIN_DIR . '/builders/divi/includes/class-divi-assets.php';

class REACG_Divi extends DiviExtension {

  /**
   * The gettext domain for the extension's translations.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public $gettext_domain = REACG_PREFIX;

  /**
   * The extension's WP Plugin name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public $name = REACG_PREFIX;

  /**
   * The extension's version
   *
   * @since 1.0.0
   *
   * @var string
   */
  public $version = REACG_VERSION;

  /**
   * @param $name
   * @param $args
   */
  public function __construct( $name = REACG_PREFIX, $args = array() ) {
    $this->plugin_dir = plugin_dir_path( __FILE__ );
    $this->plugin_dir_url = plugin_dir_url( $this->plugin_dir );
    parent::__construct( $name, $args );

    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_visual_builder_assets' ), 20 );
  }

  /**
   * Visual Builder: panel + admin stack for the top window, placeholder styles
   * for the canvas. Divi 4 copies the page DOM into its app frame, so the
   * top-window-only assets are tagged with `et_fb_ignore_iframe`.
   */
  public function enqueue_visual_builder_assets() {
    if ( ! REACG_Divi_Assets::is_visual_builder() ) {
      return;
    }

    REACG_Divi_Assets::enqueue_top_window_assets();
    REACG_Divi_Assets::enqueue_app_window_assets();
    REACG_Divi_Assets::exclude_top_only_assets_from_app_frame();
  }
}

new REACG_Divi();
