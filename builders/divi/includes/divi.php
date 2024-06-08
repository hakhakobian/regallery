<?php

class REACG_Divi extends DiviExtension {

  /**
   * The gettext domain for the extension's translations.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public $gettext_domain = REACG_PREFIX;

  /**
   * The extension's WP Plugin name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public $name = REACG_PREFIX;

  /**
   * The extension's version
   *
   * @since 1.0.0
   *
   * @var string
   */
  public $version = REACG_VERSION;

  /**
   * @param $name
   * @param $args
   */
  public function __construct( $name = REACG_PREFIX, $args = array() ) {
    $this->plugin_dir = plugin_dir_path( __FILE__ );
    $this->plugin_dir_url = plugin_dir_url( $this->plugin_dir );
    parent::__construct( $name, $args );
  }
}

new REACG_Divi();
