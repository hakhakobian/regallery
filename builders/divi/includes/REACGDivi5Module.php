<?php
defined('ABSPATH') || die('Access Denied');

use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\VisualBuilder\Assets\PackageBuildManager;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;

class REACG_Divi5Module {
  private static $module_registered = FALSE;
  protected $localized_data = array();

  public function __construct() {
    add_action( 'init', array( $this, 'register_module' ) );
    add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'enqueue_visual_builder_assets' ) );
    add_action( 'wp_ajax_reacg_divi_preview', array( $this, 'preview' ) );
  }

  public static function register_module() {
    if ( self::$module_registered ) {
      return;
    }

    if ( class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'regallery/divi-gallery-selector' ) ) {
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

  public function enqueue_visual_builder_assets() {
    if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() ) {
      return;
    }

    if ( function_exists( 'et_builder_d5_enabled' ) && ! et_builder_d5_enabled() ) {
      return;
    }

    REACGLibrary::enqueue_scripts();

    $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

    PackageBuildManager::register_package_build(
      array(
        'name'    => 'regallery-divi',
        'version' => REACG_VERSION,
        'script'  => array(
          'src'                => REACG_PLUGIN_URL . '/builders/divi/scripts/reacg-divi-modern' . $min . '.js',
          'deps'               => array(
            'react',
            'jquery',
            'divi-module-library',
            'wp-hooks',
            'divi-rest',
          ),
          'enqueue_top_window' => false,
          'enqueue_app_window' => true,
          'data_app_window'    => $this->get_localized_data(),
        ),
      )
    );
  }

  public function get_localized_data() {
    return array_merge(
      array(
        'galleries'        => $this->get_gallery_options(),
        'settingsGroup'    => array(
          'label' => esc_html__( 'Gallery Settings', 'regallery' ),
        ),
        'plugin_url'       => REACG_PLUGIN_URL,
        'galleryId'        => array(
          'label'       => esc_html__( 'Select Gallery', 'regallery' ),
          'placeholder' => esc_html__( 'Select gallery', 'regallery' ),
        ),
        'enableOptions'    => array(
          'label' => esc_html__( 'Enable options section', 'regallery' ),
        ),
        'ajax_url'         => admin_url( 'admin-ajax.php' ),
        'nonce'            => wp_create_nonce( 'reacg_divi_builder' ),
        'loading_text'     => esc_html__( 'Loading gallery preview...', 'regallery' ),
        'empty_text'       => esc_html__( 'Select a gallery to preview it in Divi 5.', 'regallery' ),
        'error_text'       => esc_html__( 'Unable to load gallery preview. Please try again.', 'regallery' ),
      ),
      $this->localized_data
    );
  }

  public function get_gallery_options() {
    $gallery_options = REACGLibrary::get_shortcodes( false, true, false );

    foreach ( $gallery_options as $gallery_id => $gallery_label ) {
      $gallery_options[ $gallery_id ] = array(
        'label' => $gallery_label,
      );
    }

    return $gallery_options;
  }

  public function preview() {
    check_ajax_referer( 'reacg_divi_builder', 'nonce', false );

    if ( ! current_user_can( 'edit_posts' ) ) {
      wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to preview this gallery.', 'regallery' ) ), 403 );
    }

    $gallery_id     = isset( $_POST['gallery_id'] ) ? absint( $_POST['gallery_id'] ) : 0;
    $enable_options = ! empty( $_POST['enable_options'] ) && 'on' === sanitize_text_field( wp_unslash( $_POST['enable_options'] ) );

    wp_send_json_success(
      array(
        'html'        => self::get_preview_markup( $gallery_id, $enable_options ),
        'galleryData' => REACGLibrary::get_data( $gallery_id ),
        'galleryId'   => $gallery_id,
      )
    );
  }

  private static function get_preview_markup( int $gallery_id, bool $enable_options ): string {
    ob_start();
    ?>
    <div id="reacg-root<?php echo esc_attr( $gallery_id ); ?>"
         class="reacg-wrapper reacg-gallery reacg-preview"
          data-options-section="<?php echo esc_attr( (int) $enable_options ); ?>"
         data-options-container="#reacg_settings"
         data-plugin-version="<?php echo esc_attr( REACG_VERSION ); ?>"
         data-gallery-timestamp="<?php echo esc_attr( get_post_meta( $gallery_id, 'gallery_timestamp', true ) ); ?>"
         data-options-timestamp="<?php echo esc_attr( get_post_meta( $gallery_id, 'options_timestamp', true ) ); ?>"
         data-gallery-id="<?php echo esc_attr( $gallery_id ); ?>"></div>
    <?php

    return (string) ob_get_clean();
  }

  public static function render_callback( array $attrs, string $content, object $block, object $elements ): string {
    $new_attrs = $block->parsed_block['attrs'];

    $gallery_id = absint( self::get_attribute_value( $new_attrs, 'galleryId', self::get_attribute_value( $new_attrs, 'post_id', 0 ) ) );
    
    if ( empty( $gallery_id ) ) {
      return self::get_empty_markup();
    }

    return do_shortcode( REACGLibrary::get_shortcode( REACG(), $gallery_id ) );
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

  private static function get_empty_markup(): string {
    ob_start();
    ?>
    <div class="reacg-wrapper reacg-gallery reacg-preview reacg-preview--empty">
      <div class="reacg-gallery__content-placeholder">
        <?php echo esc_html__( 'Select a gallery to display it here.', 'regallery' ); ?>
      </div>
    </div>
    <?php

    return (string) ob_get_clean();
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

  private static function find_first_matching_value( array $attrs, array $attribute_names, $default_value = null ) {
    foreach ( $attribute_names as $attribute_name ) {
      $value = self::get_attribute_value( $attrs, $attribute_name, null );

      if ( null !== $value && '' !== $value ) {
        return $value;
      }
    }

    foreach ( $attrs as $value ) {
      if ( is_array( $value ) ) {
        $nested_value = self::find_first_matching_value( $value, $attribute_names, null );

        if ( null !== $nested_value && '' !== $nested_value ) {
          return $nested_value;
        }
      }
    }

    return $default_value;
  }
}

new REACG_Divi5Module();
