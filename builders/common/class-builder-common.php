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
        <span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Select or Create Gallery', 'regallery' ); ?>
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
    $gallery_id = (int) $gallery_id;
    $output = self::get_builder_placeholder( $gallery_id, $widget_id );
    $output .= REACGLibrary::get_rest_routs( $gallery_id, FALSE, $widget_id );

    if ( 0 !== $gallery_id ) {
      $root_id = REACGLibrary::get_root_element_id( $gallery_id, $widget_id );
      $output .= '<script type="text/javascript">';
      $output .= '(function() {';
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

    wp_enqueue_style(
      REACG_PREFIX . '_builder_modal',
      REACG_PLUGIN_URL . '/builders/common/builder-modal.css',
      ['dashicons', REACG_PREFIX . '_admin', REACG_PREFIX . '_general'],
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
      'dashicons_url' => includes_url('css/dashicons.min.css'),
      'version' => REACG_VERSION,
      'text_gallery' => __('Gallery', 'regallery'),
      'text_select_gallery' => __('Select gallery', 'regallery'),
      'text_new_gallery' => __('New Gallery', 'regallery'),
      'text_create_new' => __('Create New Gallery', 'regallery'),
      'text_enter_title' => __('Enter gallery title...', 'regallery'),
      'text_create' => __('Create', 'regallery'),
      'text_cancel' => __('Cancel', 'regallery'),
      'text_media' => __('Media & Photos', 'regallery'),
      'text_settings' => __('Layout & Settings', 'regallery'),
      'text_media_hint' => sprintf(__('Click %1$s+%2$s to add photos from your Media Library. Drag items to reorder.', 'regallery'), '<strong>', '</strong>'),
      'text_apply_save' => __('Apply & Save Gallery', 'regallery'),
      'text_saving' => __('Saving...', 'regallery'),
      'text_saved' => __('Saved', 'regallery'),
      'text_no_galleries' => __('No galleries found', 'regallery'),
    ]);
  }
}
