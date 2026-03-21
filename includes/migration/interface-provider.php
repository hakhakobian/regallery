<?php

defined('ABSPATH') || die('Access Denied');

interface REACG_Migration_Provider_Interface {
  public function get_key();

  public function get_label();

  public function is_available();

  public function list_galleries($args = []);

  public function get_gallery($external_id);

  public function get_shortcode_patterns($source_gallery_id);

  public function get_block_namespaces();

  public function get_block_id_attributes();

  public function prefer_shortcode_replacement();

  public function replace_specific_gallery($source_gallery_id, $migrated_gallery_id, $replacement_shortcode, $replacement_block);

  public function replace_post_meta_references($post_id, $source_gallery_id, $migrated_gallery_id);

  public function source_exists_in_posts($source_gallery_id);
}
