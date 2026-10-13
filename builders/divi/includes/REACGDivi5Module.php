<?php
/**
 * ReGallery Divi 5 module.
 *
 * Divi 5 runs the Visual Builder in two windows: the top window hosts the
 * builder UI and the settings modals, the app window (`app_window=1`) hosts the
 * page. The module script is registered for the app window through Divi's
 * PackageBuildManager; the floating ReGallery panel and the admin stack are
 * enqueued in the top window. The module's settings field opens the panel,
 * and the chosen gallery is written to the module attributes through Divi's
 * edit-post store.
 */

defined('ABSPATH') || die('Access Denied');

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\VisualBuilder\Assets\PackageBuildManager;

require_once REACG_PLUGIN_DIR . '/builders/divi/includes/class-divi-assets.php';

class REACG_Divi5Module {
  const MODULE_NAME = 'regallery/divi-gallery-selector';
  const PACKAGE_NAME = 'regallery-divi';

  private static $module_registered = FALSE;

  public function __construct() {
    add_action( 'init', array( $this, 'register_module' ) );
    add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'register_package_build' ) );
    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_window_assets' ), 20 );
    add_action( 'wp_ajax_reacg_divi_preview', array( $this, 'preview' ) );
  }

  public static function register_module() {
    if ( self::$module_registered ) {
      return;
    }

    if ( class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( self::MODULE_NAME ) ) {
      self::$module_registered = TRUE;
      return;
    }

    ModuleRegistration::register_module(
      __DIR__,
      array(
        'render_callback' => array( __CLASS__, 'render_callback' ),
      )
    );

    self::$module_registered = TRUE;
  }

  /**
   * Register the module script for the app window.
   */
  public function register_package_build() {
    if ( ! REACG_Divi_Assets::is_visual_builder() ) {
      return;
    }

    if ( function_exists( 'et_builder_d5_enabled' ) && ! et_builder_d5_enabled() ) {
      return;
    }

    PackageBuildManager::register_package_build(
      array(
        'name'    => self::PACKAGE_NAME,
        'version' => REACG_VERSION . '.' . filemtime( REACG_PLUGIN_DIR . '/builders/divi/scripts/reacg-divi-modern.js' ),
        'script'  => array(
          'src'                => REACG_PLUGIN_URL . '/builders/divi/scripts/reacg-divi-modern.js',
          'deps'               => array(
            'react',
            'jquery',
            'divi-module-library',
            'divi-field-library',
            'divi-data',
            'wp-hooks',
            'divi-rest',
            REACG_PREFIX . '_thumbnails',
          ),
          'enqueue_top_window' => false,
          'enqueue_app_window' => true,
          'data_app_window'    => $this->get_localized_data(),
        ),
      )
    );
  }

  /**
   * Top window: floating panel + admin stack. App window: bundle + placeholder styles.
   */
  public function enqueue_window_assets() {
    if ( ! REACG_Divi_Assets::is_visual_builder() ) {
      return;
    }

    if ( REACG_Divi_Assets::is_app_window() ) {
      REACG_Divi_Assets::enqueue_app_window_assets();
      return;
    }

    REACG_Divi_Assets::enqueue_top_window_assets();
  }

  public function get_localized_data() {
    return array(
      'galleries'        => $this->get_gallery_options(),
      'plugin_url'       => REACG_PLUGIN_URL,
      'brand'            => REACG_NICENAME,
      'ajax_url'         => admin_url( 'admin-ajax.php' ),
      'nonce'            => wp_create_nonce( 'reacg_divi_builder' ),
      'settingsGroup'    => esc_html__( 'Gallery', 'regallery' ),
      'fieldLabel'       => esc_html__( 'Gallery', 'regallery' ),
      'selectedGallery'  => esc_html__( 'Selected gallery:', 'regallery' ),
      'noGallery'        => esc_html__( 'No gallery selected', 'regallery' ),
      'selectOrCreate'   => esc_html__( 'Select or Create Gallery', 'regallery' ),
      'fieldDescription' => wp_kses_post( REACG_Builder_Common::get_builder_control_description() ),
      'placeholderDesc'  => esc_html__( 'No gallery selected yet. Select an existing gallery or create a new one.', 'regallery' ),
      'loadingText'      => esc_html__( 'Loading gallery preview...', 'regallery' ),
      'errorText'        => esc_html__( 'Unable to load gallery preview. Please try again.', 'regallery' ),
    );
  }

  public function get_gallery_options() {
    $gallery_options = REACGLibrary::get_shortcodes( false, true, false );
    $options = array();

    foreach ( $gallery_options as $gallery_id => $gallery_label ) {
      $options[ (string) $gallery_id ] = (string) $gallery_label;
    }

    return $options;
  }

  /**
   * AJAX: data and markup for the canvas preview of a gallery.
   */
  public function preview() {
    check_ajax_referer( 'reacg_divi_builder', 'nonce', false );

    if ( ! current_user_can( 'edit_posts' ) ) {
      wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to preview this gallery.', 'regallery' ) ), 403 );
    }

    $gallery_id = isset( $_POST['gallery_id'] ) ? (int) wp_unslash( $_POST['gallery_id'] ) : 0;
    $module_id  = isset( $_POST['module_id'] ) ? sanitize_text_field( wp_unslash( $_POST['module_id'] ) ) : '';

    wp_send_json_success(
      array(
        'galleryData'       => REACGLibrary::get_data( $gallery_id ),
        'galleryId'         => $gallery_id,
        'rootId'            => REACGLibrary::get_root_element_id( $gallery_id, $module_id ),
        'pluginVersion'     => REACG_VERSION,
        'galleryTimestamp'  => (string) get_post_meta( $gallery_id, 'gallery_timestamp', true ),
        'optionsTimestamp'  => (string) get_post_meta( $gallery_id, 'options_timestamp', true ),
      )
    );
  }

  /**
   * Frontend render.
   */
  public static function render_callback( array $attrs, string $content, object $block, object $elements ): string {
    $new_attrs = $block->parsed_block['attrs'];
    $module_id = $block->parsed_block['id'] ?? '';

    $gallery_id = (int) self::get_attribute_value( $new_attrs, 'galleryId', self::get_attribute_value( $new_attrs, 'post_id', 0 ) );

    return REACGLibrary::render_gallery( $gallery_id, $module_id );
  }

  public static function module_classnames( array $args ): array {
    return array();
  }

  public static function module_styles( array $args ): void {
    $elements = $args['elements'];

    Style::add(
      array(
        'id'            => $args['id'],
        'name'          => $args['name'],
        'orderIndex'    => $args['orderIndex'],
        'storeInstance' => $args['storeInstance'],
        'styles'        => array(
          $elements->style(
            array(
              'attrName' => 'module',
            )
          ),
          CssStyle::style(
            array(
              'selector'  => $args['orderClass'] ?? '',
              'attr'      => $args['attrs']['css'] ?? array(),
              'cssFields' => array(),
            )
          ),
        ),
      )
    );
  }

  public static function module_script_data( array $args ): array {
    return array(
      'data' => array(),
    );
  }

  private static function get_attribute_value( array $attrs, string $attribute_name, $default_value = null ) {
    if ( isset( $attrs[ $attribute_name ]['desktop']['value'] ) ) {
      return $attrs[ $attribute_name ]['desktop']['value'];
    }

    if ( isset( $attrs[ $attribute_name ]['value'] ) ) {
      return $attrs[ $attribute_name ]['value'];
    }

    if ( isset( $attrs[ $attribute_name ] ) && ! is_array( $attrs[ $attribute_name ] ) ) {
      return $attrs[ $attribute_name ];
    }

    return $default_value;
  }
}
