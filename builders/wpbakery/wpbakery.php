<?php

class REACG_WPBakery {
  private $obj;
  public function __construct($obj) {
    $this->obj = $obj;
    add_shortcode( $obj->shortcode . '_wpbakery_widget', [$this, 'render_widget'] );
    $this->widget();
  }

  private function widget() {
    $options = [];
    foreach ( REACGLibrary::get_shortcodes(FALSE, TRUE, FALSE) as $id => $label ) {
      $options[ $label ] = $id;
    }
    $edit_link = add_query_arg(array( 'post_type' => 'reacg' ), admin_url('edit.php'));

    vc_map( array(
              'name' => REACG_NICENAME,
              'base' => $this->obj->shortcode . '_wpbakery_widget',
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

  public function render_widget( $atts ) {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts, $this->obj->shortcode . '_wpbakery_widget' );

    $id = intval( $atts['id'] );

    ob_start();
    echo REACGLibrary::get_rest_routs( $id );
    if ( isset( $_GET['vc_editable'] ) && $_GET['vc_editable'] === 'true' ) {
      ?>
      <script type="text/javascript">
        var reacgLoadApp = document.getElementById('reacg-loadApp');
        if ( reacgLoadApp ) {
          reacgLoadApp.setAttribute('data-id', 'reacg-root<?php echo esc_js($id); ?>');
          reacgLoadApp.click();
        }
      </script>
      <?php
    }

    return ob_get_clean();
  }
}
