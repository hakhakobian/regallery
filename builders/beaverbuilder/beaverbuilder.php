<?php
defined('ABSPATH') || die('Access Denied');

FLBuilder::register_module(
  'REACGBBModule',
  [
    'general' => [
      'title' => esc_html__('Gallery Settings', 'regallery'),
      'sections' => [
        'general' => [
          'title' => '',
          'fields' => [
            'gallery_id' => [
              'type' => 'text',
              'label' => esc_html__('Gallery ID', 'regallery'),
              'default' => '0',
              'preview' => [
                'type' => 'none',
              ],
            ],
            'manage_galleries_desc' => [
              'type' => 'raw',
              'content' => REACG_Builder_Common::get_builder_control_description(),
            ],
          ],
        ],
      ],
    ],
  ]
);

class REACGBBModule extends FLBuilderModule {
  public function __construct() {
    parent::__construct([
      'name'            => REACG_NICENAME,
      'description'     => esc_html__('Display responsive photo galleries.', 'regallery'),
      'category'        => esc_html__('Media', 'regallery'),
      'icon'            => 'reacg-bb-icon',
      'dir'             => REACG_PLUGIN_DIR . '/builders/beaverbuilder/',
      'url'             => REACG_PLUGIN_URL . '/builders/beaverbuilder/',
      'editor_export'   => true,
      'enabled'         => true,
      'partial_refresh' => true,
    ]);
  }

  public function enqueue_scripts() {
    wp_enqueue_style(
      REACG_PREFIX . '_beaver_builder',
      REACG_PLUGIN_URL . '/builders/beaverbuilder/beaverbuilder.css',
      [ 'dashicons' ],
      REACG_VERSION
    );

    // Editor-only modal assets are loaded once by REACG_BeaverBuilder_Manager.
  }
}
