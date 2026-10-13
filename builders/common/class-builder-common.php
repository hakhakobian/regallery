<?php
/**
 * ReGallery Common Builder Helpers & Modal Manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class REACG_Builder_Common {

  /**
   * Get the admin URL to manage galleries in dashboard.
   *
   * @return string
   */
  public static function get_manage_galleries_url() {
    return add_query_arg( [ 'post_type' => REACG_CUSTOM_POST_TYPE ], admin_url( 'edit.php' ) );
  }

  /**
   * Get generic builder sidebar description text (HTML) explaining live options & dashboard link.
   *
   * @return string
   */
  public static function get_builder_control_description() {
    $edit_link = self::get_manage_galleries_url();
    /* translators: 1: opening anchor tag, 2: closing anchor tag */
    return sprintf(
      __( 'Gallery settings and media are configured in the floating options panel.<br><br>Click on the gallery or placeholder in the canvas to edit.<br><br>Manage all galleries in %1$sDashboard%2$s.', 'regallery' ),
      '<a style="text-decoration: underline;" target="_blank" href="' . esc_url( $edit_link ) . '">',
      '</a>'
    );
  }

  /**
   * Render placeholder HTML for visual page builders when no gallery is selected.
   *
   * @param int|string $gallery_id Gallery post ID.
   * @param string     $widget_id  Widget or block instance ID.
   *
   * @return string HTML output.
   */
  public static function get_builder_placeholder( $gallery_id, $widget_id = '' ) {
    $icon_url = REACG_PLUGIN_URL . '/assets/images/icon.svg';
    $is_hidden = (int) $gallery_id !== 0;
    $placeholder_class = 'reacg-builder-placeholder' . ( $is_hidden ? ' reacg-hidden' : '' );

    ob_start();
    ?>
    <div class="<?php echo esc_attr( $placeholder_class ); ?>" data-widget-id="<?php echo esc_attr( $widget_id ); ?>">
      <div class="reacg-builder-placeholder-icon">
        <img src="<?php echo esc_url( $icon_url ); ?>" alt="<?php echo esc_attr( REACG_NICENAME ); ?>" />
      </div>
      <div class="reacg-builder-placeholder-title"><?php echo esc_html( REACG_NICENAME ); ?></div>
      <p class="reacg-builder-placeholder-desc"><?php esc_html_e( 'No gallery selected yet. Select an existing gallery or create a new one.', 'regallery' ); ?></p>
      <button type="button" class="reacg-builder-open-modal-btn button button-primary">
        <?php // Inline SVG: builder canvases (e.g. Bricks) do not load dashicons. ?>
        <svg class="reacg-builder-open-modal-btn__icon" aria-hidden="true" viewBox="0 0 20 20" focusable="false"><path d="M16 4h1.96c.57 0 1.04.47 1.04 1.04v12.92c0 .57-.47 1.04-1.04 1.04H5.04C4.47 19 4 18.53 4 17.96V16H2.04C1.47 16 1 15.53 1 14.96V2.04C1 1.47 1.47 1 2.04 1h12.92c.57 0 1.04.47 1.04 1.04V4zM6 6v11h11V6H6zm1 9l2.5-3 1.5 2 2.5-4L15 15H7zM3 3v11h1V5.04C4 4.47 4.47 4 5.04 4H14V3H3z"/></svg>
        <?php esc_html_e( 'Select or Create Gallery', 'regallery' ); ?>
      </button>
    </div>
    <?php
    return ob_get_clean();
  }

  /**
   * Render complete output for visual page builders in editor mode.
   * Includes placeholder, rest routes gallery container, and live reload bootstrap script.
   *
   * @param int|string $gallery_id Gallery post ID.
   * @param string     $widget_id  Widget or block instance ID.
   *
   * @return string HTML output.
   */
  public static function render_builder_editor( $gallery_id, $widget_id = '' ) {
    $gallery_id = REACGLibrary::gallery_exists( $gallery_id ) ? (int) $gallery_id : 0;
    $output = self::get_builder_placeholder( $gallery_id, $widget_id );

    // No gallery yet: only the placeholder. A gallery root with id 0 would be
    // mounted by the bundle and show the empty "no data" state under it. The
    // builder re-renders this widget once a gallery is selected.
    if ( 0 === $gallery_id ) {
      return $output;
    }

    $output .= REACGLibrary::get_rest_routs( $gallery_id, FALSE, $widget_id );

    if ( 0 !== $gallery_id ) {
      $root_id = REACGLibrary::get_root_element_id( $gallery_id, $widget_id );
      $output .= '<script type="text/javascript">';
      $output .= '(function() {';
      $output .= '  var root = document.getElementById("' . esc_js( $root_id ) . '");';
      $output .= '  if (root && typeof window.reacg_mount_gallery === "function") {';
      $output .= '    window.reacg_mount_gallery(root);';
      $output .= '    return;';
      $output .= '  }';
      $output .= '  var reacgLoadApp = document.getElementById("reacg-loadApp");';
      $output .= '  if (reacgLoadApp) {';
      $output .= '    reacgLoadApp.setAttribute("data-id", "' . esc_js( $root_id ) . '");';
      $output .= '    reacgLoadApp.click();';
      $output .= '  }';
      $output .= '})();';
      $output .= '</script>';
    }

    return $output;
  }

  /**
   * Enqueue the canvas placeholder styles. Call this wherever a builder renders
   * the canvas (preview iframe or editor document).
   *
   * @return void
   */
  public static function enqueue_placeholder_styles() {
    wp_enqueue_style(
      REACG_PREFIX . '_builder_placeholder',
      REACG_PLUGIN_URL . '/builders/common/builder-placeholder.css',
      [],
      filemtime( REACG_PLUGIN_DIR . '/builders/common/builder-placeholder.css' )
    );
  }

  /**
   * Enqueue all required assets for builder editors (Elementor, Divi, Bricks, BB, WPBakery).
   *
   * @return void
   */
  public static function enqueue_builder_assets() {
    if ( !is_admin() && !current_user_can('edit_posts') ) {
      return;
    }

    if ( class_exists('REACG') && method_exists(REACG(), 'register_admin_scripts') ) {
      REACG()->register_admin_scripts();
    }

    wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-sortable');

    // wp_enqueue_style('wp-admin');
    // wp_enqueue_style('buttons');
    wp_enqueue_style('media-views');
    wp_enqueue_style('dashicons');
    // wp_enqueue_style('wp-auth-check');

    wp_enqueue_style(REACG_PREFIX . '_select2');
    wp_enqueue_style(REACG_PREFIX . '_posts');
    // wp_enqueue_style(REACG_PREFIX . '_widget_box');
    // wp_enqueue_style(REACG_PREFIX . '_common_admin');
    wp_enqueue_style(REACG_PREFIX . '_admin');
    wp_enqueue_style(REACG_PREFIX . '_general');

    wp_enqueue_script(REACG_PREFIX . '_select2');
    wp_enqueue_script(REACG_PREFIX . '_posts');
    // wp_enqueue_script(REACG_PREFIX . '_widget_box');
    wp_enqueue_script(REACG_PREFIX . '_admin');
    wp_enqueue_script(REACG_PREFIX . '_thumbnails');

    self::enqueue_placeholder_styles();

    wp_enqueue_style(
      REACG_PREFIX . '_builder_modal',
      REACG_PLUGIN_URL . '/builders/common/builder-modal.css',
      ['dashicons', REACG_PREFIX . '_admin', REACG_PREFIX . '_general', REACG_PREFIX . '_builder_placeholder'],
      filemtime( REACG_PLUGIN_DIR . '/builders/common/builder-modal.css' )
    );

    wp_enqueue_script(
      REACG_PREFIX . '_builder_modal',
      REACG_PLUGIN_URL . '/builders/common/builder-modal.js',
      ['jquery', 'jquery-ui-sortable', REACG_PREFIX . '_admin', REACG_PREFIX . '_thumbnails'],
      filemtime( REACG_PLUGIN_DIR . '/builders/common/builder-modal.js' ),
      true
    );

    $raw_shortcodes = REACGLibrary::get_shortcodes(REACG(), TRUE, TRUE);
    $galleries = json_decode($raw_shortcodes, true);

    wp_localize_script(REACG_PREFIX . '_builder_modal', 'reacg_builder_data', [
      'galleries' => is_array($galleries) ? $galleries : [],
      'ajax_url' => wp_nonce_url(admin_url('admin-ajax.php'), REACG_NONCE, REACG_NONCE),
      'nonce' => wp_create_nonce(REACG_NONCE),
      'rest_nonce' => defined('REACG_REST_NONCE') ? REACG_REST_NONCE : wp_create_nonce('wp_rest'),
      'plugin_url' => REACG_PLUGIN_URL,
      'version' => REACG_VERSION,
      'text_brand' => REACG_NICENAME,
      'text_gallery' => __('Gallery', 'regallery'),
      'text_select_gallery' => __('Select gallery', 'regallery'),
      'text_new_gallery' => __('+ New', 'regallery'),
      'text_create_new' => __('Create New Gallery', 'regallery'),
      'text_enter_title' => __('Enter gallery title...', 'regallery'),
      'text_create' => __('Create', 'regallery'),
      'text_cancel' => __('Cancel', 'regallery'),
      'text_close' => __('Close', 'regallery'),
      'text_drag_to_move' => __('Drag to move panel', 'regallery'),
      'text_no_title' => __('(no title)', 'regallery'),
      'text_create_failed' => __('Unable to create gallery.', 'regallery'),
      'text_empty_state' => sprintf(
        /* translators: 1: opening strong tag, 2: closing strong tag */
        __('No gallery selected. Choose an existing gallery above or click %1$s+ New%2$s to create one.', 'regallery'),
        '<strong>',
        '</strong>'
      ),
      'text_media' => __('Media', 'regallery'),
      'text_settings' => __('Settings', 'regallery'),
      'text_saving' => __('Saving...', 'regallery'),
      'text_saved' => __('Saved', 'regallery'),
      'text_no_galleries' => __('No galleries found', 'regallery'),
      'text_select_or_create' => __('Select or Create Gallery', 'regallery'),
      'text_selected_gallery' => __('Selected gallery:', 'regallery'),
      'text_no_gallery_selected' => __('No gallery selected', 'regallery'),
      'text_placeholder_desc' => __('No gallery selected yet. Select an existing gallery or create a new one.', 'regallery'),
      'text_field_description' => wp_kses_post( self::get_builder_control_description() ),
    ]);
  }
}
