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
        'label' => __('Gallery Settings', 'regallery'),
      ]
    );

    $this->add_control(
      'post_id',
      [
        'type' => \Elementor\Controls_Manager::HIDDEN,
        'default' => 0,
      ]
    );

    $this->add_control(
      'manage_galleries_desc',
      [
        'type' => \Elementor\Controls_Manager::RAW_HTML,
        'raw' => REACG_Builder_Common::get_builder_control_description(),
        'content_classes' => 'elementor-descriptor',
      ]
    );

    $this->end_controls_section();
  }

  /**
   * Render widget output on the frontend and in editor.
   *
   * @return void
   */
  protected function render() {
    $settings = $this->get_settings_for_display();
    $post_id = intval($settings["post_id"]);

    if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      echo REACG_Builder_Common::render_builder_editor($post_id, $this->get_id());
    } else {
      if ( $post_id !== 0 ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo REACGLibrary::get_rest_routs($post_id, FALSE, $this->get_id());
      }
    }
  }
}
