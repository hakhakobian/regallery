<?php

class REACG_DiviModule extends ET_Builder_Module {
  public $slug = 'reacg_module';
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
    'author_uri' => REACG_PUBLIC_URL,
  );

  public function init() {
    REACGLibrary::enqueue_scripts();
    $this->name = REACG_NICENAME;
    $this->icon_path = REACG_PLUGIN_DIR . '/assets/images/icon.svg';
  }

  /**
   * Module's necessary fields.
   *
   * @return array[]
   */
  public function get_fields() {
    return array(
      'post_id' => array(
        'label'           => ' ',
        'type'            => 'select',
        'option_category' => 'basic_option',
        'options'         => REACGLibrary::get_shortcodes(FALSE, TRUE, FALSE),
        'default'         => 0,
        'tab_slug'        => 'general',
        'toggle_slug'     => 'main_content',
        'description'     => sprintf(__('Add/edit galleries %shere%s.', 'reacg'), '<a target="_blank" href="' . add_query_arg(array( 'post_type' => 'reacg' ), admin_url('edit.php')) . '">', '</a>'),
      ),
      'enable_options' => array(
        'label'           => esc_html__( 'Enable options section', 'reacg' ),
        'type'            => 'yes_no_button',
        'option_category' => 'basic_option',
        'options'         => array(
          'off' => esc_html__( 'No', 'reacg' ),
          'on'  => esc_html__( 'Yes', 'reacg' ),
        ),
        'default'         => 'off',
        'tab_slug'        => 'general',
        'toggle_slug'     => 'main_content',
        'description'     => esc_html__( 'The options will be visible only in editor mode.', 'reacg' ),
      ),
    );
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
  public function render( $attrs, $content = null, $render_slug ) {
    $post_id = intval($this->props["post_id"]);

    return REACGLibrary::get_rest_routs($post_id);
  }
}

new REACG_DiviModule();
