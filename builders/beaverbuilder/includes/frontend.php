<?php

ob_start();
echo REACGLibrary::get_rest_routs(intval($settings->gallery_id));
if ( class_exists('FLBuilderModel') && FLBuilderModel::is_builder_active() ) {
  ?>
  <script type="text/javascript">
    var reacgLoadApp = document.getElementById('reacg-loadApp');
    if ( reacgLoadApp ) {
      reacgLoadApp.setAttribute('data-id', 'reacg-root<?php echo esc_js(intval($settings->gallery_id)); ?>');
      reacgLoadApp.click();
    }
  </script>
  <?php
}
echo ob_get_clean();
