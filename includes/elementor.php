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
    return 'ReGallery';
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
        'label' => __('General', 'reacg'),
      ]
    );

    $edit_link = add_query_arg(array( 'post_type' => 'reacg' ), admin_url('edit.php'));
    $this->add_control(
      'post_id',
      [
        'label_block' => TRUE,
        'show_label' => FALSE,
        'description' => sprintf(__('Add/edit galleries %s.', 'reacg'), '<a target="_blank" href="' . $edit_link . '">' . __('here', 'reacg') . '</a>'),
        'type' => \Elementor\Controls_Manager::SELECT,
        'default' => 0,
        'options' => REACGLibrary::get_shortcodes(FALSE, TRUE, FALSE),
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
    echo '<script type="text/javascript">';
    echo 'document.getElementById("reacg-loadApp").setAttribute("data-id", "reacg-root' . $post_id . '");';
    echo 'document.getElementById("reacg-loadApp").click();';
    echo '</script>';
    echo REACGLibrary::get_rest_routs($post_id);
  }
}
