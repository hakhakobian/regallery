<?php
/**
 * Divi 5 Module Class
 * 
 * Handles the module registration for Divi 5+
 */

class REACG_Divi5Module extends ET_Builder_Module {
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
        // Initialize settings modal toggles with translatable strings
        $this->settings_modal_toggles = array(
          'general'  => array(
            'toggles' => array(
              'main_content' => array(
                'title' => esc_html__( 'Re Gallery', 'regallery' ),
              ),
            ),
          ),
        );
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
        'label'           => esc_html__( 'Select Gallery', 'regallery' ),
        'type'            => 'select',
        'option_category' => 'basic_option',
        'options'         => REACGLibrary::get_shortcodes(FALSE, TRUE, FALSE),
        'default'         => 0,
        'tab_slug'        => 'general',
        'toggle_slug'     => 'main_content',
        /* translators: 1: opening anchor tag, 2: closing anchor tag */
        'description'     => sprintf(__('Add/edit galleries %1$shere%2$s.', 'regallery'), '<a style="text-decoration: underline;" target="_blank" href="' . add_query_arg(array( 'post_type' => REACG_CUSTOM_POST_TYPE ), admin_url('edit.php')) . '">', '</a>'),
      ),
      'enable_options' => array(
        'label'           => esc_html__( 'Enable options section', 'regallery' ),
        'type'            => 'yes_no_button',
        'option_category' => 'basic_option',
        'options'         => array(
          'off' => esc_html__( 'No', 'regallery' ),
          'on'  => esc_html__( 'Yes', 'regallery' ),
        ),
        'default'         => 'off',
        'tab_slug'        => 'general',
        'toggle_slug'     => 'main_content',
        'description'     => esc_html__( 'The options will be visible only in editor mode.', 'regallery' ),
      ),
    );
  }

  /**
   * Get settings modal toggle definitions for Divi 5
   */
  public function get_settings_modal_toggles() {
    return $this->settings_modal_toggles;
  }

  /**
   * Render module output.
   *
   * @param $attrs
   * @param $content
   * @param $render_slug
   *
   * @return bool|string|null
   */
  public function render( $attrs, $content, $render_slug ) {
    $post_id = intval( $this->props["post_id"] );

    return REACGLibrary::get_rest_routs( $post_id );
  }
}

new REACG_Divi5Module();
