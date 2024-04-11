<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Options {
  private $options = [
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
    'titleColor' => '#CCCCCC', #string
    'titleFontSize' => 12, #number
    'titleFontFamily' => 'Abel', #string

    'paginationType' => 'scroll', #string simple | scroll | loadMore | none
    'itemsPerPage' => 20, #number
    'activeButtonColor' => '#FFFFFF', #string
    'inactiveButtonColor' => '#00000014', #string
    'paginationButtonShape' => 'circular', #string rounded | circular
    'loadMoreButtonColor' => '#00000014', #string
    'paginationTextColor' => '#000000de', #string

    'lightbox' => array(
      'showLightbox' => TRUE, #boolean
      'isFullscreen' => TRUE, #boolean
      'width' => 800, #number
      'height' => 600, #number
      'areControlButtonsShown' => TRUE, #boolean
      'isInfinite' => TRUE, #boolean
      'padding' => 0, #number
      'canDownload' => FALSE, #boolean
      'canZoom' => TRUE, #boolean
      'autoplay' => TRUE, #boolean
      'slideDuration' => 5000, #number
      'isSlideshowAllowed' => TRUE, #boolean
      'isFullscreenAllowed' => TRUE, #boolean
      'thumbnailsPosition' => 'bottom', #string top | bottom | start | end | none
      'thumbnailWidth' => 120, #number
      'thumbnailHeight' => 90, #number
      'thumbnailBorder' => 0, #number
      'thumbnailBorderColor' => '', #string
      'thumbnailBorderRadius' => 0, #number
      'thumbnailPadding' => 0, #number
      'thumbnailGap' => 5, #number
      'captionsPosition' => 'bottom', #string top | bottom | above | below | none
      'captionFontFamily' => 'Abel', #string
      'captionColor' => '#FFFFFF', #string;
    ),
  ];
  private $name = "reacg_options";
  private $obj;

  /**
   * Validate data on allowed values.
   *
   * @param $key
   * @param $value
   *
   * @return mixed
   */
  private function validate($key, $value) {
    $number = [
      'width',
      'height',
      'columns',
      'itemsPerPage',
      'titleFontSize',
      'thumbnailWidth',
      'thumbnailHeight',
      'thumbnailGap',
      'slideDuration',
    ];
    $empty_number = [
      'gap',
      'padding',
      'borderRadius',
      'thumbnailBorder',
      'thumbnailBorderRadius',
      'thumbnailPadding',
    ];
    $boolean = [
      'showLightbox',
      'isFullscreen',
      'areControlButtonsShown',
      'isInfinite',
      'canDownload',
      'canZoom',
      'autoplay',
      'isSlideshowAllowed',
      'isFullscreenAllowed',
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
      'thumbnailsPosition' => [
        'allowed' => [ 'top', 'bottom', 'start', 'end', 'none' ],
        'default' => 'bottom',
      ],
      'captionsPosition' => [
        'allowed' => [ 'top', 'bottom', 'above', 'below', 'none' ],
        'default' => 'bottom',
      ],

    ];
    if ( in_array($key, $number) ) {
      return intval(max($value, 1));
    }
    elseif ( in_array($key, $empty_number) ) {
      return intval($value) < 0 ? '' : intval($value);
    }
    elseif ( in_array($key, $boolean) ) {
      return filter_var($value, FILTER_VALIDATE_BOOLEAN);
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
  private function add_option() {
    add_option($this->name, json_encode($this->options));
  }

  /**
   * To check the method.
   *
   * @param WP_REST_Request|NULL $request
   *
   * @return void
   */
  public function options(WP_REST_Request $request = null) {
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
   * Sanitizing and validating the given data.
   *
   * @param $data
   *
   * @return mixed
   */
  private function sanitize($data) {
    foreach ( $data as $key => $item ) {
      if ( is_array($item) ) {
        $data[$key] = $this->sanitize($item);
      }
      else {
        $item = sanitize_text_field(stripslashes($item));
        // Validate data on allowed values.
        $data[$key] = $this->validate($key, $item);
      }
    }

    return $data;
  }

  /**
   * Set options for the given gallery.
   *
   * @param WP_REST_Request $request
   *
   * @return WP_REST_Response
   */
  private function set(WP_REST_Request $request) {
    $gallery_id = REACGLibrary::get_gallery_id($request, 'gallery_id');

    $data = $request->get_body();

    if ( empty($data) ) {
      return wp_send_json(new WP_Error( 'nothing_to_save', __( 'Nothing to save.', 'reacg' ), array( 'status' => 400 ) ), 400);
    }
    $data = json_decode($data, TRUE);

    // Modify the data structure based on the new structure.
    $data = $this->modify($data);

    // Sanitizing and validating the given data.
    $data = $this->sanitize($data);

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
      $gallery_id = REACGLibrary::get_gallery_id($request, 'gallery_id');
    }

    // Get options for the gallery.
    $options = get_option($this->name . $gallery_id, FALSE);

    // Get default options if the gallery options do not exist.
    if ( $options === FALSE ) {
      $options = get_option($this->name, FALSE);
    }

    if ( !empty($options) ) {
      $options = json_decode($options, TRUE);
    }

    // Modify the data structure based on the new structure.
    $options = $this->modify($options);

    // If an option is missing add it's default value.
    $options = $this->defaults($this->options, $options);

    // Sanitizing and validating the given data.
    $options = $this->sanitize($options);

    return new WP_REST_Response( wp_send_json($options, 200), 200 );
  }

  /**
   * If an option is missing add it's default value.
   *
   * @param $default_options
   * @param $options
   *
   * @return mixed
   */
  private function defaults($default_options, $options) {
    foreach ( $default_options as $key => $option ) {
      if ( is_array($option) ) {
        // If the option is a group of options (e.g. lightbox)
        $options[$key] = $this->defaults($default_options[$key], $options[$key]);
      }
      elseif ( !isset($options[$key]) ) {
        // If an option is missing add it's default value.
        $options[$key] = $option;
      }
    }

    return $options;
  }

  /**
   * Modify the data structure based on the new structure.
   *
   * @param $options
   *
   * @return mixed
   */
  private function modify($options) {
    // Options to be moved.
    $move = [
      'lightbox' => ['showLightbox'],
    ];

    foreach ( $move as $to => $old_keys ) {
      // If the new group exists.
      if ( isset($options[$to]) ) {
        foreach ( $old_keys as $old_key ) {
          // If the old option exists.
          if ( isset($options[$old_key]) ) {
            // Move the option to the new group.
            $options[$to][$old_key] = $options[$old_key];
            // Remove the old option.
            unset($options[$old_key]);
          }
        }
      }
    }

    return $options;
  }

  /**
   * Delete the options fot the given gallery.
   *
   * @param $request
   *
   * @return WP_REST_Response
   */
  private function delete($request) {
    $gallery_id = REACGLibrary::get_gallery_id($request, 'gallery_id');

    $deleted = delete_option($this->name . $gallery_id);

    if ( $deleted === TRUE ) {
      return new WP_REST_Response( wp_send_json(__( 'Settings successfully reset.', 'reacg' ), 200), 200 );
    }
    else {
      return new WP_REST_Response( wp_send_json(__( 'Settings already reset.', 'reacg' ), 200), 200 );
    }
  }

  /**
   * Remove the options.
   *
   * @return void
   */
  private function remove_options() {
    delete_option($this->name);
  }
}
