<?php

class REACG_WPBakery {
  public function __construct($obj) {
    wp_enqueue_style($obj->prefix . '_admin');
    $this->widget($obj);
  }

  private function widget($obj) {
    $options = [];
    foreach ( REACGLibrary::get_shortcodes(FALSE, TRUE, FALSE) as $id => $label ) {
      $options[ $label ] = $id;
    }
    $edit_link = add_query_arg(array( 'post_type' => 'reacg' ), admin_url('edit.php'));

    vc_map( array(
              'name' => REACG_NICENAME,
              'base' => $obj->shortcode,
              'class' => '',
              'category' => 'Content',
              "icon" => "reacg-icon",
              'params' => array(
                array(
                  'type' => 'dropdown',
                  'heading' => __( 'Select Gallery', 'reacg' ),
                  'param_name' => 'id',
                  'value' => $options,
                  'description' => sprintf(__('Add/edit galleries %shere%s.', 'reacg'), '<a target="_blank" href="' . $edit_link . '">', '</a>'),
                ),
              )
            ) );
  }
}
