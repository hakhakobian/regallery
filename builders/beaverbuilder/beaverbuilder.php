<?php

FLBuilder::register_module(
  'REACGBBModule',
  [
   'general' => [
     'title' => esc_html__('General', 'reacg'),
     'sections' => [
       '' => [
         'fields' => [
           'gallery_id' => [
             'type' => 'select',
             'label' => esc_html__('Select gallery', 'reacg'),
             'options' => REACGLibrary::get_shortcodes(FALSE, TRUE, FALSE),
             'description' => sprintf(__('Add/edit galleries %shere%s.', 'reacg'), '<a style="text-decoration: underline;" target="_blank" href="' . add_query_arg(array( 'post_type' => 'reacg' ), admin_url('edit.php')) . '">', '</a>'),
           ],
         ],
       ],
     ],
   ],
 ],
);

class REACGBBModule extends FLBuilderModule {
  public function __construct() {
    parent::__construct([
                          'name'            => REACG_NICENAME,
                          'description'     => '',
                          'category'        => esc_html__('Media', 'reacg'),
                          'icon'            => 'reacg-bb-icon',
                          'dir'             => REACG_PLUGIN_DIR . '/builders/beaverbuilder/',
                          'url'             => REACG_PLUGIN_DIR . '/builders/beaverbuilder/',
                          'editor_export'   => true,
                          'enabled'         => true,
                          'partial_refresh' => true,
                        ]);
  }

  public function enqueue_scripts() {
    wp_enqueue_style(REACG_PREFIX . '_beaver_builder', REACG_PLUGIN_URL . '/builders/beaverbuilder/beaverbuilder.css', [], '1.0');
  }
}
