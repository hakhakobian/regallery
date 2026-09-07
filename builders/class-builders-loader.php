<?php
/**
 * ReGallery Builders Loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once __DIR__ . '/common/class-builder-common.php';
require_once __DIR__ . '/elementor/class-elementor.php';
require_once __DIR__ . '/bricks/class-bricks.php';

class REACG_Builders_Loader {

  /**
   * Initialize all registered builders.
   *
   * @return void
   */
  public static function init() {
    REACG_Elementor_Manager::init();
    REACG_Bricks_Manager::init();
  }
}
