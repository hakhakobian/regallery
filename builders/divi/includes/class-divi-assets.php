<?php
/**
 * ReGallery Divi asset helpers shared by the Divi 4 and Divi 5 integrations.
 *
 * Both Divi builders split the editor into a top window (builder UI, settings
 * modals) and an app window (the page itself inside an iframe):
 *
 * - Divi 5 loads the app window as a separate request with `app_window=1`.
 * - Divi 4 loads a single request and then moves the page DOM, including the
 *   enqueued scripts and styles, into the app frame. Elements carrying the
 *   `et_fb_ignore_iframe` class are left out of that copy.
 *
 * The floating panel and the admin stack belong to the top window only; the
 * app window needs the gallery bundle and the placeholder styles.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class REACG_Divi_Assets {

  /**
   * Handles that must not be copied into the Divi 4 app frame.
   *
   * @var string[]
   */
  private static $top_only_handles = [
    'reacg_admin',
    'reacg_posts',
    'reacg_select2',
    'reacg_builder_modal',
    'reacg_divi_editor',
    'reacg_common_admin',
    'reacg_widget_box',
    'media-views',
    'buttons',
    'wp-admin',
    'wp-auth-check',
  ];

  /**
   * Whether the current request is a Divi Visual Builder request.
   *
   * @return bool
   */
  public static function is_visual_builder() {
    return function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled();
  }

  /**
   * Whether the current request is the Divi 5 app window (canvas iframe).
   *
   * @return bool
   */
  public static function is_app_window() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    return isset( $_GET['app_window'] ) && '1' === $_GET['app_window'];
  }

  /**
   * Enqueue the floating panel and the admin stack for the builder's top window.
   *
   * @return void
   */
  public static function enqueue_top_window_assets() {
    REACG_Builder_Common::enqueue_builder_assets();

    wp_enqueue_style(
      REACG_PREFIX . '_divi_editor',
      REACG_PLUGIN_URL . '/builders/divi/styles/divi-editor.css',
      [ REACG_PREFIX . '_builder_modal' ],
      filemtime( REACG_PLUGIN_DIR . '/builders/divi/styles/divi-editor.css' )
    );
  }

  /**
   * Enqueue what the canvas needs: the gallery bundle and the placeholder styles.
   *
   * @return void
   */
  public static function enqueue_app_window_assets() {
    REACG_Builder_Common::enqueue_placeholder_styles();
    REACGLibrary::enqueue_scripts();
  }

  /**
   * Divi 4 only: keep the top-window-only assets out of the app frame copy.
   *
   * @return void
   */
  public static function exclude_top_only_assets_from_app_frame() {
    add_filter( 'style_loader_tag', [ __CLASS__, 'tag_top_only_asset' ], 10, 2 );
    add_filter( 'script_loader_tag', [ __CLASS__, 'tag_top_only_asset' ], 10, 2 );
  }

  /**
   * Add the `et_fb_ignore_iframe` class to a top-window-only asset tag.
   *
   * @param string $tag    The HTML tag.
   * @param string $handle The asset handle.
   *
   * @return string
   */
  public static function tag_top_only_asset( $tag, $handle ) {
    if ( ! in_array( $handle, self::$top_only_handles, true ) ) {
      return $tag;
    }
    if ( false !== strpos( $tag, 'et_fb_ignore_iframe' ) ) {
      return $tag;
    }
    if ( preg_match( '/<(link|script)\b[^>]*\bclass=(["\'])/', $tag ) ) {
      return preg_replace( '/(<(?:link|script)\b[^>]*\bclass=(["\']))/', '$1et_fb_ignore_iframe ', $tag, 1 );
    }
    return preg_replace( '/<(link|script)\b/', '<$1 class="et_fb_ignore_iframe"', $tag, 1 );
  }
}
