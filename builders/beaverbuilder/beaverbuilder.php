<?php

FLBuilder::register_module(
  'REACGBBModule',
  [
   'general' => [
     'title' => esc_html__('General', 'regallery'),
     'sections' => [
       '' => [
         'fields' => [
           'gallery_id' => [
             'type' => 'select',
             'label' => esc_html__('Select gallery', 'regallery'),
             'options' => REACGLibrary::get_shortcodes(FALSE, TRUE, FALSE),
             /* translators: 1: opening anchor tag, 2: closing anchor tag */
             'description' => sprintf(__('Add/edit galleries %1$shere%2$s.', 'regallery'), '<a style="text-decoration: underline;" target="_blank" href="' . add_query_arg(array( 'post_type' => REACG_CUSTOM_POST_TYPE ), admin_url('edit.php')) . '">', '</a>'),
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
                          'category'        => esc_html__('Media', 'regallery'),
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
