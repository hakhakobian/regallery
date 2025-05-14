<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Shortcode {
  private $obj;

  public function __construct($that) {
    $this->obj = $that;

    add_shortcode($that->shortcode, array($this, 'content'));
  }

  public function content( $params = array() ) {
    if ( (is_admin() && defined('DOING_AJAX') && !DOING_AJAX) || !isset($params['id']) ) {
      return false;
    }

    $id = (int) $params['id'];
    ob_start();
    REACGLibrary::get_rest_routs($id);

    return str_replace(array( "\r\n", "\n", "\r" ), '', ob_get_clean());
  }
}
