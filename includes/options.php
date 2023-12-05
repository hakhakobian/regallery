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

  /**
   * Validate specific data.
   *
   * @param $key
   * @param $value
   *
   * @return mixed
   */
  private function validate($key, $value): mixed {
    $number = [
      'width',
      'height',
      'columns',
      'itemsPerPage',
    ];
    $empty_number = [
      'gap',
      'padding',
      'borderRadius',
      'titleFontSize',
    ];
    $specific = [
      'titleVisibility' => [
        'allowed' => [ 'always', 'onHover', 'none' ],
        'default' => 'onHover',
      ],
      'titlePosition' => [
        'allowed' => [ 'bottom', 'top', 'center', 'above', 'below' ],
        'default' => 'bottom',
      ],
      'titleAlignment' => [
        'allowed' => [ 'left', 'center', 'right' ],
        'default' => 'left',
      ],
      'paginationType' => [
        'allowed' => [ 'simple', 'scroll', 'loadMore', 'none' ],
        'default' => 'scroll',
      ],
      'paginationButtonShape' => [
        'allowed' => [ 'rounded', 'circular' ],
        'default' => 'circular',
      ],
    ];
    if ( in_array($key, $number) ) {
      return max($value, 1);
    }
    elseif ( in_array($key, $empty_number) ) {
      return intval($value) < 0 ? '' : $value;
    }
    elseif ( in_array($key, $specific) ) {
      return in_array($value, $specific[$key]['allowed']) ? $value : $specific[$key]['default'];
    }
    return $value;
  }

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
   * @return WP_REST_Response
   */
  private function set(WP_REST_Request $request) {
    $parameters = $request->get_url_params();

    if ( !isset($parameters['gallery_id']) ) {
      return wp_send_json(new WP_Error( 'missing_gallery', __( 'Missing gallery ID.', 'reacg' ), array( 'status' => 400 ) ), 400);
    }
    $gallery_id = (int) $parameters['gallery_id'];
    $data = $request->get_body();

    if ( empty($data) ) {
      return wp_send_json(new WP_Error( 'nothing_to_save', __( 'Nothing to save.', 'reacg' ), array( 'status' => 400 ) ), 400);
    }
    $data = (array) json_decode($data);

    array_walk($data, function (&$value) {
      $value = sanitize_text_field(stripslashes($value));
    });

    // Data validation on allowed values.
    foreach ( $data as $key => $item ) {
      $data[$key] = $this->validate($key, $item);
    }
    $options = json_encode($data);
    $old_options = get_option($this->name . $gallery_id, $options);
    $saved = update_option($this->name . $gallery_id, $options);
    $new_options = get_option($this->name . $gallery_id, $options);

    if ( $saved === TRUE || $old_options === $new_options ) {
      return $this->get(NULL, $gallery_id);
    }
    else {
      return wp_send_json(new WP_Error( 'nothing_saved', __( 'Nothing saved.', 'reacg' ), array( 'status' => 400 ) ), 400);
    }
  }

  /**
   * Get the default options or the options for the given gallery.
   *
   * @param WP_REST_Request|NULL $request
   * @param int                  $gallery_id
   *
   * @return WP_REST_Response
   */
  private function get( WP_REST_Request $request = null, int $gallery_id = 0 ) {
    if ( $gallery_id === 0 ) {
      $parameters = $request->get_url_params();
      if ( !isset($parameters['gallery_id']) ) {
        return wp_send_json(new WP_Error( 'missing_gallery', __( 'Missing gallery ID.', 'reacg' ), array( 'status' => 400 ) ), 400);
      }
      $gallery_id = (int) $parameters['gallery_id'];
    }

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

    return new WP_REST_Response( wp_send_json($options, 200), 200 );
  }

  /**
   * Delete the options fot the given gallery.
   *
   * @param $request
   *
   * @return WP_REST_Response
   */
  private function delete($request) {
    $parameters = $request->get_url_params();

    if ( !isset($parameters['gallery_id']) ) {
      return wp_send_json(new WP_Error( 'missing_gallery', __( 'Missing gallery ID.', 'reacg' ), array( 'status' => 400 ) ), 400);
    }
    $gallery_id = (int) $parameters['gallery_id'];
    $deleted = delete_option($this->name . $gallery_id);

    if ( $deleted === TRUE ) {
      return new WP_REST_Response( wp_send_json(__( 'Successfully deleted.', 'reacg' ), 200), 200 );
    }
    else {
      return wp_send_json(new WP_Error( 'nothing_deleted', __( 'Nothing deleted.', 'reacg' ), array( 'status' => 400 ) ), 400);
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
