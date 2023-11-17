<?php
defined('ABSPATH') || die('Access Denied');

class AIG_Shortcode {
  private $obj;

  public function __construct($that) {
    $this->obj = $that;
    AIGLibrary::enqueue_scripts($this->obj);

    add_shortcode($that->shortcode, array($this, 'content'));
  }

  public function content( $params = array() ) {
    if ( (is_admin() && defined('DOING_AJAX') && !DOING_AJAX) || !isset($params['id']) ) {
      return false;
    }

    $id = (int) $params['id'];
    ob_start();
    AIGLibrary::get_rest_routs($this->obj->rest_root, $id);

    return str_replace(array( "\r\n", "\n", "\r" ), '', ob_get_clean());
  }
}
