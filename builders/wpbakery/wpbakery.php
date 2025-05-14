<?php

class REACG_WPBakery {
  public function __construct($obj) {
//        add_shortcode( 'reacg_wpbakery_widget', [$this, 'render_widget'] );
    $this->widget($obj);
    //    $obj->shortcode();
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
//              'base' => 'reacg_wpbakery_widget',
              'class' => '',
              'category' => 'Content',
              "icon" => "reacg-icon",
//              'js_view' => 'VcShortcodeView',
//              'custom_markup' => '<div class="reacg-gallery reacg-preview"></div>',
              'params' => array(
                array(
                  'type' => 'dropdown',
                  'heading' => __( 'Select Gallery', 'reacg' ),
                  'param_name' => 'id',
                  'value' => $options,
                  'description' => sprintf(__('Add/edit galleries %shere%s.', 'reacg'), '<a target="_blank" href="' . $edit_link . '">', '</a>'),
                ),
                array(
                  'type' => 'checkbox',
                  'heading' => __( 'Enable options section', 'reacg' ),
                  'param_name' => 'enable_options',
                  'description' => __( 'The options will be visible only in editor mode.', 'reacg' )
                )
              )
            ) );
  }

  public function render_widget( $atts ) {
    $atts = shortcode_atts( array(
                              'id' => 0,
                              'enable_options' => ''
                            ), $atts, 'reacg_wpbakery_widget' );

    $post_id = intval( $atts['id'] );
    $enable_options = $atts['enable_options'] ? 1 : 0;

    ob_start();
//    var_dump($_GET);die();
    if ( isset( $_GET['vc_editable'] ) && $_GET['vc_editable'] === 'true' ) {
      die('aaaaaaaaa');
      return 'aaaaaaaaaa';

      echo '<div style="border: 2px dashed #ccc; padding: 20px; text-align:center;">';
      echo '<strong>Gallery Preview:</strong><br>';
      echo 'Gallery ID: ' . esc_html( $post_id );
      echo '</div>';
    } else {
    echo REACGLibrary::get_rest_routs( $post_id );

//    if ( is_admin() ) {
//      ?>
<!--      <script type="text/javascript">-->
<!--        var cont = document.querySelector('#reacg-root--><?php //echo esc_js($post_id); ?>//');
//        if (cont) {
//          cont.setAttribute('data-options-section', '<?php //echo esc_js($enable_options); ?>//');
//          cont.setAttribute('data-plugin-version', '<?php //echo esc_js(REACG_VERSION); ?>//');
//          cont.setAttribute('data-gallery-timestamp', Date.now());
//          cont.setAttribute('data-options-timestamp', Date.now());
//        }
//        var reacgLoadApp = document.getElementById('reacg-loadApp');
//        if (reacgLoadApp) {
//          reacgLoadApp.setAttribute('data-id', 'reacg-root<?php //echo esc_js($post_id); ?>//');
//          reacgLoadApp.click();
//        }
//      </script>
//      <?php
//    }
    }

    return ob_get_clean();
  }
}
