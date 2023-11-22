<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Options {
  private string $name = "reacg_options";
  private $obj;
  private array $options = [
    'title' => 'Default', #string
    'template' => false, #boolean
    'width' => 200, #number
    'height' => 150, #number
    'columns' => 4, #number
    'gap' => 10, #number
    'backgroundColor' => '', #string
    'padding' => 0, #number
    'paddingColor' => '', #number
    'borderRadius' => 0, #number

    'titleVisibility' => 'onHover', #string always | onHover | none
    'titlePosition' => 'bottom', #string; bottom | top | center | above | below
    'titleAlignment' => 'left', #string left | center | right
    'titleColor' => '#FFFFFF', #string
    'titleFontSize' => 12, #number
    'titleFontFamily' => 'Abel', #string

    'paginationType' => 'scroll', #string simple | scroll | loadMore | none
    'itemsPerPage' => 20, #number
    'activeButtonColor' => '#FFFFFF', #string
    'inactiveButtonColor' => '#00000014', #string
    'paginationButtonShape' => 'circular', #string rounded | circular
    'loadMoreButtonColor' => '#000000de', #string
    'paginationTextColor' => '#000000de', #string

    'useLightbox' => TRUE, #boolean
  ];

  public function __construct($activate) {
    if ( $activate ) {
      $this->add_option();
    }
    else {
      $this->remove_options();
    }
  }

  /**
   * Set options default values.
   *
   * @return void
   */
  private function add_option(): void {
    add_option($this->name, json_encode($this->options));
  }

  /**
   * To check the method.
   *
   * @param WP_REST_Request|NULL $request
   *
   * @return void
   */
  public function options(WP_REST_Request $request = null): void {
    if ( !is_null($request) ) {
      switch ( $request->get_method() ) {
        case "GET": {
          $this->get($request);
          break;
        }
        case "POST":
        case "PUT":
        case "PATCH": {
          $this->set($request);
          break;
        }
        case "DELETE": {
          $this->delete($request);
          break;
        }
      }
    }
  }

  /**
   * Set options for the given gallery.
   *
   * @param WP_REST_Request $request
   *
   * @return WP_Error|WP_REST_Response
   */
  private function set(WP_REST_Request $request) {
    $parameters = $request->get_url_params();

    if ( !isset($parameters['gallery_id']) ) {
      return new WP_Error( '404', __( 'Missing gallery ID.', 'reacg' ) );
    }
    $gallery_id = (int) $parameters['gallery_id'];
    $data = $request->get_body();

    if ( empty($data) ) {
      return new WP_Error( '404', __( 'Nothing to save.', 'reacg' ) );
    }
    $data = (array) json_decode($data);
    array_walk($data, function (&$value) {
      $value = sanitize_text_field(stripslashes($value));
    });
    $options = json_encode($data);
    $old_options = get_option($this->name . $gallery_id, $options);
    $saved = update_option($this->name . $gallery_id, $options);
    $new_options = get_option($this->name . $gallery_id, $options);

    if ( $saved === TRUE ) {
      return new WP_REST_Response( TRUE, 200 );
    }
    elseif ( $old_options === $new_options ) {
      return new WP_Error( '304', __( 'Nothing changed.', 'reacg' ) );
    }
    else {
      return new WP_Error( '403', __( 'Nothing saved.', 'reacg' ) );
    }
  }

  /**
   * Get the default options or the options for the given gallery.
   *
   * @param WP_REST_Request|NULL $request
   *
   * @return WP_Error|WP_REST_Response|null
   */
  private function get( WP_REST_Request $request = null) {
    $parameters = $request->get_url_params();

    if ( !isset($parameters['gallery_id']) ) {
      return new WP_Error( '404', __( 'Missing gallery ID.', 'reacg' ) );
    }
    $gallery_id = (int) $parameters['gallery_id'];
    $options = get_option($this->name . $gallery_id, FALSE);
    if ( $options === FALSE ) {
      $options = get_option($this->name, FALSE);
    }

    if ( !empty($options) ) {
      $options = json_decode($options);
    }

    foreach ( $this->options as $key => $value ) {
      if ( !isset($options->$key) ) {
        $options->$key = $value;
      }
    }

    return new WP_REST_Response( wp_send_json($options), 200 );
  }

  /**
   * Delete the options fot the given gallery.
   *
   * @param $request
   *
   * @return WP_Error|WP_REST_Response
   */
  private function delete($request) {
    $parameters = $request->get_url_params();

    if ( !isset($parameters['gallery_id']) ) {
      return new WP_Error( '404', __( 'Missing gallery ID.', 'reacg' ) );
    }
    $gallery_id = (int) $parameters['gallery_id'];
    $deleted = delete_option($this->name . $gallery_id);

    if ( $deleted === TRUE ) {
      return new WP_REST_Response( TRUE, 200 );
    }
    else {
      return new WP_Error( '403', __( 'Nothing deleted.', 'reacg' ) );
    }
  }

  /**
   * Remove the options.
   *
   * @return void
   */
  private function remove_options(): void {
    delete_option($this->name);
  }
}
