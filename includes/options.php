<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Options {
  protected $options = [
    'title' => 'Default', #string
    'template' => false, #boolean
    'template_id' => 0, #number
    'type' => 'thumbnails', #string thumbnails | mosaic | masonry | slideshow
    'thumbnails' => [
      'width' => 250, #number
      'height' => 170, #number
      'columns' => 4, #number
      'gap' => 10, #number
      'backgroundColor' => '', #string
      'containerPadding' => 0, #number
      'padding' => 0, #number
      'paddingColor' => '', #string
      'borderRadius' => 0, #number
      'hoverEffect' => 'none', #string zoom_out | zoom_in | slide | rotate | blur | scale | sepia | overlay | flash | shine | circle | none
      'titleVisibility' => 'onHover', #string alwaysShown | onHover | none
      'titlePosition' => 'bottom', #string bottom | top | center | above | below
      'titleAlignment' => 'left', #string left | center | right
      'titleColor' => '#CCCCCC', #string
      'titleFontSize' => 12, #number
      'titleFontFamily' => 'Abel', #string
      'paginationType' => 'simple', #string simple | scroll | loadMore | none
    ],
    'mosaic' => [
      'width' => 100, #number
      'direction' => 'vertical', #string horizontal | vertical
      'gap' => 10, #number
      'backgroundColor' => '', #string
      'containerPadding' => 0, #number
      'padding' => 0, #number
      'paddingColor' => '', #string
      'rowHeight' => 200, #number
      'columns' => 4, #number
      'borderRadius' => 0, #number
      'hoverEffect' => 'none', #string zoom_out | zoom_in | slide | rotate | blur | scale | sepia | overlay | flash | shine | circle | none
      'titleVisibility' => 'onHover', #string alwaysShown | onHover | none
      'titlePosition' => 'bottom', #string bottom | top | center | above | below
      'titleAlignment' => 'left', #string left | center | right
      'titleColor' => '#CCCCCC', #string
      'titleFontSize' => 12, #number
      'titleFontFamily' => 'Abel', #string
      'paginationType' => 'simple', #string simple | none
    ],
    'masonry' => [
      'width' => 100, #number
      'gap' => 10, #number
      'backgroundColor' => '', #string
      'containerPadding' => 0, #number
      'padding' => 0, #number
      'paddingColor' => '', #string
      'columns' => 4, #number
      'borderRadius' => 0, #number
      'hoverEffect' => 'none', #string zoom_out | zoom_in | slide | rotate | blur | scale | sepia | overlay | flash | shine | circle | none
      'titleVisibility' => 'onHover', #string alwaysShown | onHover | none
      'titlePosition' => 'bottom', #string bottom | top | center | above | below
      'titleAlignment' => 'left', #string left | center | right
      'titleColor' => '#CCCCCC', #string
      'titleFontSize' => 12, #number
      'titleFontFamily' => 'Abel', #string
      'paginationType' => 'scroll', #string scroll | loadMore | none
    ],
    'slideshow' => [
      'width' => 800, #number
      'height' => 600, #number
      'isInfinite' => TRUE, #boolean
      'padding' => 0, #number
      'autoplay' => FALSE, #boolean
      'slideDuration' => 5000, #number
      'imageAnimation' => 'slideH', #string fade | blur | slideH | slideV | zoom | flip | rotate
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
      'backgroundColor' => '#000000', #string;
      'captionsPosition' => 'none', #string top | bottom | above | below | none
      'captionFontFamily' => 'Abel', #string
      'captionColor' => '#FFFFFF', #string;
    ],
    'cube' => [
      'width' => 300, #number
      'height' => 300, #number
      'isInfinite' => TRUE, #boolean
      'autoplay' => TRUE, #boolean
      'slideDuration' => 3000, #number
      'backgroundColor' => '#FFFFFF', #string;
      'padding' => 0, #number;
      'shadow' => TRUE, #boolean
    ],
    'carousel' => [
      'width' => 200, #number
      'height' => 250, #number
      'backgroundColor' => '', #string;
      'padding' => 0, #number
      'autoplay' => FALSE, #boolean
      'slideDuration' => 3000, #number
      'playAndPauseAllowed' => TRUE, #boolean
      'scale' => 0.1, #float
      'imagesCount' => 3, #number
      'spaceBetween' => -10, #number
    ],
    'cards' => [
      'width' => 300, #number
      'height' => 300, #number
      'perSlideOffset' => 10, #number
      'navigationButton' => TRUE, #boolean
      'playAndPauseAllowed' => FALSE, #boolean
      'autoplay' => FALSE, #boolean
      'slideDuration' => 3000, #number
    ],
    'general' => [
      'orderBy' => 'default', #string
      'orderDirection' => 'asc', #string
      'itemsPerPage' => 20, #number
      'activeButtonColor' => '#FFFFFF', #string
      'inactiveButtonColor' => '#00000014', #string
      'paginationButtonShape' => 'circular', #string rounded | circular
      'loadMoreButtonColor' => '#00000014', #string
      'paginationTextColor' => '#000000de', #string
    ],
    'lightbox' => [
      'showLightbox' => TRUE, #boolean
      'isFullscreen' => TRUE, #boolean
      'width' => 800, #number
      'height' => 600, #number
      'areControlButtonsShown' => TRUE, #boolean
      'isInfinite' => TRUE, #boolean
      'padding' => 0, #number
      'canDownload' => FALSE, #boolean
      'canZoom' => TRUE, #boolean
      'autoplay' => FALSE, #boolean
      'slideDuration' => 5000, #number
      'imageAnimation' => 'slideH', #string fade | blur | slideH | slideV | zoom | flip | rotate
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
      'backgroundColor' => '#000000', #string;
      'captionsPosition' => 'none', #string top | bottom | above | below | none
      'captionFontFamily' => 'Abel', #string
      'captionColor' => '#FFFFFF', #string;
    ],
  ];
  protected $name = "reacg_options";

  /**
   * Validate data on allowed values.
   *
   * @param $key
   * @param $value
   *
   * @return mixed
   */
  protected function validate($key, $value) {
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
      'rowHeight',
      'imagesCount',
      'perSlideOffset',
    ];
    $empty_number = [
      'template_id',
      'gap',
      'containerPadding',
      'padding',
      'borderRadius',
      'thumbnailBorder',
      'thumbnailBorderRadius',
      'thumbnailPadding',
    ];
    $negative_number = [
      'spaceBetween',
    ];
    $boolean = [
      'template',
      'showLightbox',
      'isFullscreen',
      'areControlButtonsShown',
      'isInfinite',
      'canDownload',
      'canZoom',
      'autoplay',
      'isSlideshowAllowed',
      'isFullscreenAllowed',
      'shadow',
      'navigationButton',
      'playAndPauseAllowed',
    ];
    $float = [
      'scale',
    ];
    $specific = [
      'type' => [
        'allowed' => [ 'thumbnails', 'mosaic', 'masonry', 'slideshow', 'cube', 'carousel', 'cards' ],
        'default' => 'thumbnails',
      ],
      'direction' => [
        'allowed' => [ 'horizontal', 'vertical' ],
        'default' => 'vertical',
      ],
      'titleVisibility' => [
        'allowed' => [ 'alwaysShown', 'onHover', 'none' ],
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
        'default' => 'simple',
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
      'imageAnimation' => [
        'allowed' => [ 'fade', 'blur', 'slideH', 'slideV', 'zoom', 'flip', 'rotate' ],
        'default' => 'slideH',
      ],
      'hoverEffect' => [
        'allowed' => ['zoom_out', 'zoom_in', 'slide', 'rotate', 'blur', 'scale', 'sepia', 'overlay', 'flash', 'shine', 'circle', 'none'],
        'default' => 'none',
      ],
      'orderBy' => [
        'allowed' => [ 'default', 'title', 'caption', 'description', 'date' ],
        'default' => 'default',
      ],
      'orderDirection' => [
        'allowed' => [ 'asc', 'desc' ],
        'default' => 'asc',
      ],
    ];
    if ( in_array($key, $number) ) {
      return intval(max($value, 1));
    }
    elseif ( in_array($key, $empty_number) ) {
      return $value === '' || intval($value) < 0 ? '' : intval($value);
    }
    elseif ( in_array($key, $negative_number) ) {
      return intval($value);
    }
    elseif ( in_array($key, $float) ) {
      return floatval($value);
    }
    elseif ( in_array($key, $boolean) ) {
      return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    elseif ( array_key_exists($key, $specific) ) {
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
          $this->reset($request);
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
  protected function sanitize($data) {
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
   * Refill the changed data.
   *
   * @param $new_data
   * @param $saved_data
   *
   * @return mixed
   */
  protected function fill($new_data, $saved_data) {
    foreach ( $new_data as $key => $option ) {
      if ( is_array($option) ) {
        // If the option is a group of options.
        $saved_data[$key] = $this->fill($new_data[$key], $saved_data[$key]);
      }
      else {
        // If an option has a new value change the old one with it.
        $saved_data[$key] = $option;
      }
    }

    return $saved_data;
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

    // Get saved options and add the changed once.
    $old_options = get_option($this->name . $gallery_id, FALSE);
    if ( !empty($old_options) ) {
      $old_data = json_decode($old_options, TRUE);
      $old_data = $this->modify($old_data);
      $data = $this->fill($data, $old_data);
    }

    $options = json_encode($data);

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
  protected function defaults($default_options, $options) {
    if ( !empty($options) ) {
      foreach ( $default_options as $key => $option ) {
        if ( is_array($option) ) {
          // If the option is a group of options (e.g. lightbox).
          $options[$key] = $this->defaults($default_options[$key], isset($options[$key]) ? $options[$key] : null);
        }
        elseif ( !isset($options[$key]) ) {
          // If an option is missing add its default value.
          $options[$key] = $option;
        }
      }
    }
    else {
      // If a group of options is missing add its default value.
      $options = $default_options;
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
  protected function modify($options) {
    if ( !empty($options) ) {
      // Options to be moved.
      $move = [
        'lightbox' => [ 'showLightbox' ],
        'thumbnails' => [
          'width',
          'height',
          'columns',
          'gap',
          'backgroundColor',
          'padding',
          'paddingColor',
          'borderRadius',
          'hoverEffect',
          'titleVisibility',
          'titlePosition',
          'titleAlignment',
          'titleColor',
          'titleFontSize',
          'titleFontFamily',
          'paginationType',
        ],
        'general' => [
          'orderBy',
          'orderDirection',
          'itemsPerPage',
          'activeButtonColor',
          'inactiveButtonColor',
          'paginationButtonShape',
          'loadMoreButtonColor',
          'paginationTextColor',
        ],
      ];
      foreach ( $move as $to => $old_keys ) {
        // If the new group exists.
        if ( !isset($options[$to]) ) {
          $options[$to] = [];
        }
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
   * Reset the options fot the given gallery.
   *
   * @param $request
   *
   * @return WP_REST_Response
   */
  private function reset($request) {
    $gallery_id = REACGLibrary::get_gallery_id($request, 'gallery_id');

    // Get saved options to reset only the current type options.
    $options = get_option($this->name . $gallery_id, FALSE);
    if ( !empty($options) ) {
      $data = json_decode($options, TRUE);
      if ( isset($data[$data['type']]) ) {
        // Remove the current type options.
        unset($data[$data['type']]);
      }
      if ( isset($data['general']) ) {
        // Remove general options.
        unset($data['general']);
      }
      if ( isset($data['lightbox']) ) {
        // Remove lightbox options.
        unset($data['lightbox']);
      }
      if ( isset($data['template']) ) {
        unset($data['template']);
      }
      if ( isset($data['template_id']) ) {
        unset($data['template_id']);
      }
      if ( isset($data['title']) ) {
        unset($data['title']);
      }

      $options = json_encode($data);
      $saved = update_option($this->name . $gallery_id, $options);
      if ( $saved === TRUE ) {
        return new WP_REST_Response( wp_send_json(__( 'Settings successfully reset.', 'reacg' ), 200), 200 );
      }
    }

    return new WP_REST_Response( wp_send_json(__( 'Settings already reset.', 'reacg' ), 200), 200 );
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
