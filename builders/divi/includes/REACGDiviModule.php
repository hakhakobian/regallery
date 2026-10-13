<?php
/**
 * ReGallery Divi 4 module.
 *
 * The Visual Builder renders the module through the component registered in
 * scripts/builder-bundle.min.js; this class provides the field definition and
 * the frontend render. The gallery is picked in the floating ReGallery panel,
 * which the custom `reacg_gallery_picker` field opens from the settings modal.
 */

defined('ABSPATH') || die('Access Denied');

class REACG_DiviModule extends ET_Builder_Module {
  public $icon_path = REACG_PLUGIN_DIR . '/assets/images/icon.svg';
  public $slug = 'reacg_module';
  public $name = REACG_NICENAME;
  /**
   * Visual Builder support status.
   *
   * @var string
   */
  public $vb_support = 'on';

  /**
   * Module footer credit.
   *
   * @var array
   */
  protected $module_credits = array(
    'module_uri' => '',
    'author'     => REACG_AUTHOR,
    'author_uri' => REACG_WEBSITE_URL_UTM . '&utm_medium=divi&utm_campaign=author_uri',
  );

  public function init() {
    REACGLibrary::enqueue_scripts();
  }

  /**
   * Module's necessary fields.
   *
   * @return array[]
   */
  public function get_fields() {
    return array(
      'post_id' => array(
        'label'           => esc_html__( 'Gallery', 'regallery' ),
        // Custom field type registered by the builder bundle (API.registerModalFields).
        'type'            => 'reacg_gallery_picker',
        'option_category' => 'basic_option',
        'default'         => '0',
        'tab_slug'        => 'general',
        'toggle_slug'     => 'main_content',
      ),
    );
  }

  /**
   * Render module output (frontend).
   *
   * @param $attrs
   * @param $content
   * @param $render_slug
   *
   * @return bool|string|null
   */
  public function render( $attrs, $content, $render_slug ) {
    $post_id = intval( $this->props['post_id'] );

    return REACGLibrary::render_gallery( $post_id );
  }
}

new REACG_DiviModule();
