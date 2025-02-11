<?php

class REACG_Elementor extends \Elementor\Widget_Base {
  /**
   * Get widget name.
   *
   * @return string
   */
  public function get_name() {
    return 'reacg-elementor';
  }

  /**
   * Get widget title.
   *
   * @return string
   */
  public function get_title() {
    return REACG_NICENAME;
  }

  /**
   * Get widget icon.
   *
   * @return string
   */
  public function get_icon() {
    return 'reacg-elementor-icon';
  }

  /**
   * Get widget category.
   *
   * @return string[]
   */
  public function get_categories() {
    return [ 'basic' ];
  }

  /**
   * Register widget controls.
   *
   * @return void
   */
  protected function register_controls() {
    $this->start_controls_section(
      'reacg_general',
      [
        'label' => __('Basic', 'reacg'),
      ]
    );

    $edit_link = add_query_arg(array( 'post_type' => 'reacg' ), admin_url('edit.php'));
    $this->add_control(
      'post_id',
      [
        'label_block' => TRUE,
        'show_label' => FALSE,
        'description' => sprintf(__('Add/edit galleries %shere%s.', 'reacg'), '<a target="_blank" href="' . $edit_link . '">', '</a>'),
        'type' => \Elementor\Controls_Manager::SELECT,
        'default' => 0,
        'options' => REACGLibrary::get_shortcodes(FALSE, TRUE, FALSE),
      ]
    );
    $this->add_control(
      'enable_options',
      [
        'label' => __('Enable options section', 'reacg'),
        'label_block' => FALSE,
        'type' => \Elementor\Controls_Manager::SWITCHER,
        'label_yes' => __('Yes', 'reacg'),
        'label_no' => __('No', 'reacg'),
        'default' => 'no',
        'description' => __( 'The options will be visible only in editor mode.', 'reacg' ),
      ]
    );

    $this->end_controls_section();
  }

  /**
   * Render widget output on the frontend.
   *
   * @return void
   */
  protected function render() {
    $settings = $this->get_settings_for_display();
    $post_id = intval($settings["post_id"]);

    echo REACGLibrary::get_rest_routs($post_id);

    if ( is_admin() ) {
      $enable_options = $settings["enable_options"] === "yes" ? 1 : 0;
      echo '<script type="text/javascript">';
      // Get inserted widget container by ID.
      echo 'var widget_cont = document.querySelector(".elementor-element-' . esc_js($this->get_id()) . '");';
      // Get the gallery container.
      echo 'var cont = widget_cont ? widget_cont.querySelector("#reacg-root' . $post_id . '") : null;';
      // Enable/disable options section depends on Elementor widget setting.
      echo 'if (cont) { cont.setAttribute("data-options-section", "' . $enable_options . '"); }';
      // Load the gallery.
      echo 'var reacgLoadApp = document.getElementById("reacg-loadApp");';
      echo 'if (reacgLoadApp) {';
      echo 'reacgLoadApp.setAttribute("data-id", "reacg-root' . $post_id . '");';
      echo 'reacgLoadApp.click();';
      echo '}';
      // Open Elementor widget settings after the gallery load.
      echo 'if (widget_cont) { widget_cont.querySelector(".elementor-editor-widget-settings").click(); }';
      echo '</script>';
    }
  }
}
