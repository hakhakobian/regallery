<?php
/**
 * ReGallery WPBakery Page Builder integration.
 *
 * The element exposes one param (`id`) rendered by a custom param type that
 * holds the gallery id and opens the shared floating panel. Both WPBakery
 * editors (backend and frontend) host the panel in their own document; the
 * frontend editor's canvas iframe renders the preview gallery.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class REACG_WPBakery {
  const PARAM_TYPE = 'reacg_gallery';

  private $obj;
  private $tag;
  private static $instance = 0;

  public function __construct($obj) {
    $this->obj = $obj;
    $this->tag = $obj->shortcode . '_wpbakery_widget';

    add_shortcode( $this->tag, [ $this, 'render_widget' ] );

    if ( function_exists( 'vc_add_shortcode_param' ) ) {
      vc_add_shortcode_param( self::PARAM_TYPE, [ $this, 'render_param' ] );
    }
    $this->widget();

    // Editor documents (backend editor in wp-admin, frontend editor parent page).
    add_action( 'vc_backend_editor_enqueue_js_css', [ $this, 'enqueue_editor_assets' ] );
    add_action( 'vc_frontend_editor_enqueue_js_css', [ $this, 'enqueue_editor_assets' ] );
    // Frontend editor canvas iframe.
    add_action( 'vc_load_iframe_jscss', [ $this, 'enqueue_canvas_assets' ] );
  }

  private function widget() {
    vc_map( array(
      'name' => REACG_NICENAME,
      'base' => $this->tag,
      'class' => '',
      'category' => 'Content',
      'icon' => 'reacg-icon',
      'description' => __( 'Display images with various visual effects in responsive gallery.', 'regallery' ),
      'params' => array(
        array(
          'type' => self::PARAM_TYPE,
          'heading' => __( 'Gallery', 'regallery' ),
          'param_name' => 'id',
          'value' => 0,
        ),
      ),
    ) );
  }

  /**
   * Custom param: hidden value + description + button that opens the panel.
   *
   * @param array  $settings
   * @param string $value
   *
   * @return string
   */
  public function render_param( $settings, $value ) {
    $param_name = isset( $settings['param_name'] ) ? $settings['param_name'] : 'id';
    $gallery_id = (int) $value;
    $galleries = REACGLibrary::get_shortcodes( FALSE, TRUE, FALSE );
    $title = isset( $galleries[ $gallery_id ] ) && $gallery_id ? $galleries[ $gallery_id ] : __( 'No gallery selected', 'regallery' );

    ob_start();
    ?>
    <div class="reacg-vc-param">
      <input type="hidden"
             name="<?php echo esc_attr( $param_name ); ?>"
             class="wpb_vc_param_value reacg-vc-param__value <?php echo esc_attr( $param_name ); ?>"
             value="<?php echo esc_attr( $gallery_id ); ?>" />
      <div class="reacg-vc-param__current">
        <span class="reacg-vc-param__label"><?php esc_html_e( 'Selected gallery:', 'regallery' ); ?></span>
        <strong class="reacg-vc-param__title"><?php echo esc_html( $title ); ?></strong>
      </div>
      <button type="button" class="button button-primary reacg-vc-param__open">
        <?php esc_html_e( 'Select or Create Gallery', 'regallery' ); ?>
      </button>
      <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted HTML built by the plugin. ?>
      <p class="reacg-vc-param__description"><?php echo REACG_Builder_Common::get_builder_control_description(); ?></p>
    </div>
    <?php
    return ob_get_clean();
  }

  /**
   * Panel + admin stack for the editor document.
   */
  public function enqueue_editor_assets() {
    REACG_Builder_Common::enqueue_builder_assets();
    $this->enqueue_script( [ 'jquery', REACG_PREFIX . '_builder_modal' ] );
    wp_enqueue_style(
      REACG_PREFIX . '_wpbakery',
      REACG_PLUGIN_URL . '/builders/wpbakery/wpbakery.css',
      [],
      filemtime( REACG_PLUGIN_DIR . '/builders/wpbakery/wpbakery.css' )
    );
  }

  /**
   * Preview assets for the frontend editor canvas iframe.
   */
  public function enqueue_canvas_assets() {
    REACG_Builder_Common::enqueue_placeholder_styles();
    REACGLibrary::enqueue_scripts();
    $this->enqueue_script( [ 'jquery', REACG_PREFIX . '_thumbnails' ] );
  }

  private function enqueue_script( $deps ) {
    wp_enqueue_script(
      REACG_PREFIX . '_wpbakery',
      REACG_PLUGIN_URL . '/builders/wpbakery/wpbakery.js',
      $deps,
      filemtime( REACG_PLUGIN_DIR . '/builders/wpbakery/wpbakery.js' ),
      true
    );
    wp_localize_script( REACG_PREFIX . '_wpbakery', 'reacg_wpbakery', [
      'tag' => $this->tag,
    ] );
  }

  /**
   * True while rendering for the frontend editor canvas (page load or the
   * single-shortcode reload WPBakery performs after a param change).
   */
  private function is_editable_context() {
    if ( function_exists( 'vc_is_page_editable' ) && vc_is_page_editable() ) {
      return TRUE;
    }
    if ( function_exists( 'vc_request_param' ) && 'vc_load_shortcode' === vc_request_param( 'action' ) ) {
      return TRUE;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context check.
    return isset( $_GET['vc_editable'] ) && 'true' === $_GET['vc_editable'];
  }

  public function render_widget( $atts ) {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts, $this->tag );
    $id = intval( $atts['id'] );
    $widget_id = 'vc' . ( ++self::$instance );

    if ( $this->is_editable_context() ) {
      return REACG_Builder_Common::render_builder_editor( $id, $widget_id );
    }

    if ( 0 === $id ) {
      return '';
    }

    return REACGLibrary::render_gallery( $id, $widget_id );
  }
}
