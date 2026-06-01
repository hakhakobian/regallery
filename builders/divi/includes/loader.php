<?php
defined('ABSPATH') || die('Access Denied');

/**
 * Load the appropriate Divi module based on version detection
 */
if ( defined('ET_CORE_VERSION') ) {
  // Parse Divi version
  $divi_version = (int) explode('.', ET_CORE_VERSION)[0];
  
  // Load Divi 5+ module
  if ( $divi_version >= 5 ) {
    require_once (REACG_PLUGIN_DIR . '/builders/divi/includes/REACGDivi5Module.php');
  } else {
    // Load Divi 4 module
    require_once (REACG_PLUGIN_DIR . '/builders/divi/includes/REACGDiviModule.php');
  }
} else {
  // Fallback to Divi 4 module if version constant is not defined
  require_once (REACG_PLUGIN_DIR . '/builders/divi/includes/REACGDiviModule.php');
}
