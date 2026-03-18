<?php

defined('ABSPATH') || die('Access Denied');

interface REACG_Migration_Provider_Interface {
  public function get_key();

  public function get_label();

  public function is_available();

  public function list_galleries($args = []);

  public function get_gallery($external_id);
}
