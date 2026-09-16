<?php
/**
 * ReGallery Gutenberg Integration Manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class REACG_Gutenberg_Manager {

  /**
   * Register Gutenberg integration hooks.
   *
   * @return void
   */
  public static function init() {
    add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_editor_assets' ] );
    add_action( 'enqueue_block_assets', [ __CLASS__, 'enqueue_block_assets' ] );
  }

  /**
   * Enqueue assets required by the gallery block in the editor canvas and frontend.
   *
   * @return void
   */
  public static function enqueue_block_assets() {
    // Always load inside the editor iframe so responsive previews work correctly.
    // On the frontend, only load when the gallery block exists.
    if ( is_admin() || ( function_exists( 'has_block' ) && has_block( 'reacg/gallery' ) ) ) {
      REACG()->register_frontend_scripts();
      REACGLibrary::enqueue_scripts();
    }

    // Canvas-only editor styles also need to be available in the WP 7.1+ iframe.
    if ( is_admin() ) {
      wp_enqueue_style( 'dashicons' );
      wp_enqueue_style(
        REACG_PREFIX . '_gutenberg_canvas',
        REACG_PLUGIN_URL . '/builders/gutenberg/styles/gutenberg.css',
        [ REACG_PREFIX . '_general' ],
        REACG_VERSION
      );
    }
  }

  /**
   * Enqueue and configure the Gutenberg block registration script.
   *
   * @return void
   */
  public static function enqueue_editor_assets() {
    REACG()->register_admin_scripts();
    REACG_Builder_Common::enqueue_builder_assets();

    $script_handle = REACG_PREFIX . '_gutenberg';
    $required_scripts = [
      REACG_PREFIX . '_builder_modal',
      REACG_PREFIX . '_thumbnails',
      'wp-blocks',
      'wp-element',
      'wp-block-editor',
      'wp-components',
      REACG_PREFIX . '_admin',
    ];
    $required_styles = [
      REACG_PREFIX . '_builder_modal',
      REACG_PREFIX . '_general',
      'wp-edit-blocks',
      REACG_PREFIX . '_admin',
    ];

    wp_enqueue_script(
      $script_handle,
      REACG_PLUGIN_URL . '/builders/gutenberg/scripts/gutenberg.js',
      $required_scripts,
      REACG_VERSION,
      true
    );
    wp_localize_script( $script_handle, 'reacg_gutenberg', [
      'title' => REACG_NICENAME,
      'description' => __( 'Display images with various visual effects in responsive gallery.', 'regallery' ),
      'setup_wizard_description' => __( 'Create new gallery or select the existing one.', 'regallery' ),
      'gallery_images_section_title' => __( 'Media', 'regallery' ),
      'create_button' => __( 'Create', 'regallery' ),
      'gallery_title_placeholder' => __( 'Enter gallery title', 'regallery' ),
      'plugin_url' => REACG_PLUGIN_URL,
      'plugin_version' => REACG_VERSION,
      'icon' => REACG_PLUGIN_URL . '/assets/images/icon.svg',
      'data' => REACGLibrary::get_shortcodes( REACG(), true ),
      'ajax_url' => wp_nonce_url( admin_url( 'admin-ajax.php' ), REACG_NONCE, REACG_NONCE ),
      'control_description' => REACG_Builder_Common::get_builder_control_description(),
      'text_select_or_create' => __( 'Select or Create Gallery', 'regallery' ),
      'text_edit_in_modal' => __( 'Edit Gallery in Modal', 'regallery' ),
      'text_no_gallery_selected' => __( 'No gallery selected yet. Select an existing gallery or create a new one.', 'regallery' ),
      'text_settings' => __( 'Gallery Settings', 'regallery' ),
    ] );

    $data = [];
    foreach ( REACGLibrary::get_galleries() as $gallery_id ) {
      $data[ $gallery_id ] = REACGLibrary::get_data( $gallery_id );
    }
    wp_localize_script( $script_handle, 'reacg_data', $data );
    wp_enqueue_style(
      $script_handle,
      REACG_PLUGIN_URL . '/builders/gutenberg/styles/gutenberg.css',
      $required_styles,
      REACG_VERSION
    );
  }
}
