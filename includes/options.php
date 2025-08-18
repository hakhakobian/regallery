<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Options {
  protected $options = [
    'title' => 'Default', #string
    'template' => false, #boolean
    'template_id' => 0, #number
    'css' => '', #string
    'custom_css' => '', #string
    'type' => 'mosaic', #string thumbnails | mosaic | masonry | slideshow | cube | carousel | cards | blog
    'thumbnails' => [
      'fullWidth' => FALSE, #boolean
      'ratio' => '1.77', #string
      'width' => 430, #number
      'height' => 380, #number
      'columns' => 3, #number
      'gap' => 10, #number
      'backgroundColor' => '', #string
      'containerPadding' => 0, #number
      'padding' => 0, #number
      'paddingColor' => '', #string
      'borderRadius' => 0, #number
      'hoverEffect' => 'zoom-in', #string zoom-out | zoom-in | slide | rotate | blur | scale | sepia | overlay | overlay-icon-zoom | overlay-icon-cart | overlay-icon-plus | flash | shine | circle | none
      'titleVisibility' => 'none', #string alwaysShown | onHover | none
      'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
      'titlePosition' => 'bottom', #string bottom | top | center | above | below
      'titleAlignment' => 'left', #string left | center | right
      'titleColor' => '#CCCCCC', #string
      'titleFontSize' => 12, #number
      'titleFontFamily' => 'Inherit', #string
      'paginationType' => 'simple', #string simple | scroll | loadMore | none
      'showCaption' => FALSE, #boolean
      'captionSource' => 'caption', #string title | caption | alt | price | description | author | date_created | exif
      'captionFontSize' => 10, #number
      'captionFontColor' => '#CCCCCC', #string
    ],
    'mosaic' => [
      'width' => 100, #number
      'direction' => 'vertical', #string horizontal | vertical
      'gap' => 5, #number
      'backgroundColor' => '', #string
      'containerPadding' => 0, #number
      'padding' => 0, #number
      'paddingColor' => '', #string
      'rowHeight' => 200, #number
      'columns' => 3, #number
      'borderRadius' => 0, #number
      'hoverEffect' => 'overlay', #string zoom-out | zoom-in | slide | rotate | blur | scale | sepia | overlay | overlay-icon-zoom | overlay-icon-cart | overlay-icon-plus | flash | shine | circle | none
      'titleVisibility' => 'none', #string alwaysShown | onHover | none
      'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
      'titlePosition' => 'bottom', #string bottom | top | center | above | below
      'titleAlignment' => 'left', #string left | center | right
      'titleColor' => '#CCCCCC', #string
      'titleFontSize' => 12, #number
      'titleFontFamily' => 'Inherit', #string
      'paginationType' => 'simple', #string simple | none
      'showCaption' => FALSE, #boolean
      'captionSource' => 'caption', #string title | caption | alt | price | description | author | date_created | exif
      'captionFontSize' => 10, #number
      'captionFontColor' => '#CCCCCC', #string
    ],
    'justified' => [
      'width' => 100, #number
      'gap' => 2, #number
      'backgroundColor' => '', #string
      'containerPadding' => 0, #number
      'padding' => 0, #number
      'paddingColor' => '', #string
      'rowHeight' => 300, #number
      'borderRadius' => 0, #number
      'hoverEffect' => 'overlay-icon-zoom', #string zoom-out | zoom-in | slide | rotate | blur | scale | sepia | overlay | overlay-icon-zoom | overlay-icon-cart | overlay-icon-plus | flash | shine | circle | none
      'titleVisibility' => 'none', #string alwaysShown | onHover | none
      'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
      'titlePosition' => 'bottom', #string bottom | top | center | above | below
      'titleAlignment' => 'left', #string left | center | right
      'titleColor' => '#CCCCCC', #string
      'titleFontSize' => 12, #number
      'titleFontFamily' => 'Inherit', #string
      'paginationType' => 'simple', #string simple | none
      'showCaption' => FALSE, #boolean
      'captionSource' => 'caption', #string title | caption | alt | price | description | author | date_created | exif
      'captionFontSize' => 10, #number
      'captionFontColor' => '#CCCCCC', #string
    ],
    'masonry' => [
      'width' => 100, #number
      'gap' => 20, #number
      'backgroundColor' => '', #string
      'containerPadding' => 0, #number
      'padding' => 0, #number
      'paddingColor' => '', #string
      'columns' => 3, #number
      'borderRadius' => 0, #number
      'hoverEffect' => 'shine', #string zoom-out | zoom-in | slide | rotate | blur | scale | sepia | overlay | overlay-icon-zoom | overlay-icon-cart | overlay-icon-plus | flash | shine | circle | none
      'titleVisibility' => 'none', #string alwaysShown | onHover | none
      'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
      'titlePosition' => 'bottom', #string bottom | top | center | above | below
      'titleAlignment' => 'left', #string left | center | right
      'titleColor' => '#CCCCCC', #string
      'titleFontSize' => 12, #number
      'titleFontFamily' => 'Inherit', #string
      'paginationType' => 'scroll', #string scroll | loadMore | none
      'showCaption' => FALSE, #boolean
      'captionSource' => 'caption', #string title | caption | alt | price | description | author | date_created | exif
      'captionFontSize' => 10, #number
      'captionFontColor' => '#CCCCCC', #string
    ],
    'slideshow' => [
      'width' => 1080, #number
      'height' => 608, #number
      'isFullCoverImage' => TRUE, #boolean
      'isInfinite' => TRUE, #boolean
      'padding' => 0, #number
      'autoplay' => FALSE, #boolean
      'slideDuration' => 5000, #number
      'imageAnimation' => 'slideH', #string fade | blur | slideH | slideV | zoom | flip | rotate
      'isSlideshowAllowed' => TRUE, #boolean
      'isFullscreenAllowed' => TRUE, #boolean
      'thumbnailsPosition' => 'none', #string top | bottom | start | end | none
      'thumbnailWidth' => 120, #number
      'thumbnailHeight' => 90, #number
      'thumbnailBorder' => 0, #number
      'thumbnailBorderColor' => '', #string
      'thumbnailBorderRadius' => 0, #number
      'thumbnailPadding' => 0, #number
      'thumbnailGap' => 5, #number
      'backgroundColor' => '#000000', #string
      'textPosition' => 'none', #string top | bottom | above | below | none
      'textFontFamily' => 'Inherit', #string
      'textColor' => '#FFFFFF', #string
      'showTitle' => TRUE, #bool
      'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
      'titleFontSize' => 1.6, #float
      'titleAlignment' => 'left', #string
      'showDescription' => TRUE, #bool
      'descriptionSource' => 'description', #string title | caption | alt | price | description | author | date_created | exif
      'descriptionFontSize' => 1.3, #float
      'descriptionMaxRowsCount' => 1, #number
      'showCaption' => FALSE, #boolean
      'captionSource' => 'caption', #string title | caption | alt | price | description | author | date_created | exif
      'captionFontSize' => 1.5, #float
      'captionFontColor' => '#CCCCCC', #string
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
      'width' => 600, #number
      'height' => 420, #number
      'backgroundColor' => '', #string;
      'padding' => 0, #number
      'autoplay' => FALSE, #boolean
      'slideDuration' => 3000, #number
      'playAndPauseAllowed' => FALSE, #boolean
      'scale' => 0.1, #float
      'imagesCount' => 3, #number
      'spaceBetween' => -20, #number
    ],
    'cards' => [
      'width' => 500, #number
      'height' => 500, #number
      'perSlideOffset' => 20, #number
      'navigationButton' => TRUE, #boolean
      'playAndPauseAllowed' => FALSE, #boolean
      'autoplay' => FALSE, #boolean
      'slideDuration' => 3000, #number
    ],
    'blog' => [
      'imageWidth' => 50, #number
      'imageWidthType' => '%', #string % | px | vw | rem | em
      'imageHeight' => 400, #number
      'imageHeightType' => 'px', #string px | vh | rem | em
      'imagePosition' => 'staggered', #string left | right | staggered | listed
      'spacing' => 0, #number
      'backgroundColor' => '', #string;
      'containerPadding' => 0, #number
      'imageRadius' => 0, #number
      'hoverEffect' => 'zoom-in', #string zoom-out | zoom-in | slide | rotate | blur | scale | sepia | overlay | overlay-icon-zoom | overlay-icon-cart | overlay-icon-plus | flash | shine | circle | none
      'showTitle' => TRUE, #boolean
      'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
      'titleFontSize' => 36, #number
      'titleColor' => '#000000', #string
      'titleAlignment' => 'left', #string left | center | right
      'showDescription' => TRUE, #boolean
      'descriptionSource' => 'description', #string title | caption | alt | price | description | author | date_created | exif
      'descriptionFontSize' => 23, #number
      'descriptionColor' => '#333333', #string
      'descriptionMaxRowsCount' => 4, #number
      'showButton' => TRUE, #boolean
      'buttonText' => 'View more', #string
      'openInNewTab' => FALSE, #boolean
      'buttonAlignment' => 'left', #string left | center | right
      'buttonFontSize' => 20, #number
      'buttonColor' => '#afafaf80', #string
      'buttonTextColor' => '#000000de', #string
      'textFontFamily' => 'Inherit', #string
      'textVerticalAlignment' => 'center', #string bottom | top | center
      'textHorizontalSpacing' => 35, #number
      'textVerticalSpacing' => 35, #number
      'paginationType' => 'simple', #string simple | scroll | loadMore | none
      'showCaption' => FALSE, #boolean
      'captionSource' => 'caption', #string title | caption | alt | price | description | author | date_created | exif
      'captionFontSize' => 26, #number
      'captionFontColor' => '#CCCCCC', #string
      'buttonUrlSource' => 'action_url', #string action_url | item_url | checkout_url
    ],
    'general' => [
      'clickAction' => 'lightbox', #string none | lightbox | url
      'openUrlInNewTab' => FALSE, #boolean
      'actionUrlSource' => 'action_url', #string action_url | item_url | checkout_url
      'orderBy' => 'default', #string
      'orderDirection' => 'asc', #string
      'itemsPerPage' => 20, #number
      'activeButtonColor' => '#FFFFFF', #string
      'inactiveButtonColor' => '#D7D7D7', #string
      'loadMoreButtonColor' => '#D7D7D7', #string
      'paginationTextColor' => '#000000de', #string
      'paginationButtonTextSize' => 0.875, #float
      'paginationButtonBorderRadius' => 4, #number
      'paginationButtonBorderSize' => '', #number
      'paginationButtonBorderColor' => '', #number
      'loadMoreButtonText' => '', #string
      'paginationButtonClass' => '', #string
    ],
    'lightbox' => [
      'isFullscreen' => TRUE, #boolean
      'width' => 800, #number
      'height' => 600, #number
      'areControlButtonsShown' => TRUE, #boolean
      'isInfinite' => TRUE, #boolean
      'padding' => 70, #number
      'showCounter' => TRUE, #boolean
      'canShare' => FALSE, #boolean
      'canDownload' => FALSE, #boolean
      'canZoom' => TRUE, #boolean
      'autoplay' => FALSE, #boolean
      'slideDuration' => 5000, #number
      'imageAnimation' => 'slideH', #string fade | blur | slideH | slideV | zoom | flip | rotate
      'isSlideshowAllowed' => TRUE, #boolean
      'isFullscreenAllowed' => TRUE, #boolean
      'thumbnailsPosition' => 'none', #string top | bottom | start | end | none
      'thumbnailWidth' => 120, #number
      'thumbnailHeight' => 90, #number
      'thumbnailBorder' => 0, #number
      'thumbnailBorderColor' => '', #string
      'thumbnailBorderRadius' => 0, #number
      'thumbnailPadding' => 0, #number
      'thumbnailGap' => 5, #number
      'backgroundColor' => 'rgba(0, 0, 0, 0.2)', #string;
      'textPosition' => 'none', #string top | bottom | above | below | none
      'textFontFamily' => 'Inherit', #string
      'textColor' => '#FFFFFF', #string
      'showTitle' => TRUE, #bool
      'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
      'titleFontSize' => 1.6, #float
      'titleAlignment' => 'left', #string
      'showDescription' => TRUE, #bool
      'descriptionSource' => 'description', #string title | caption | alt | price | description | author | date_created | exif
      'descriptionFontSize' => 1.3, #float
      'descriptionMaxRowsCount' => 1, #number
      'showCaption' => FALSE, #boolean
      'captionSource' => 'caption', #string title | caption | alt | price | description | author | date_created | exif
      'captionFontSize' => 1.5, #float
      'captionFontColor' => '#CCCCCC', #string
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
      'descriptionFontSize',
      'descriptionMaxRowsCount',
      'buttonFontSize',
      'imageWidth',
      'imageHeight',
      'captionFontSize',
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
      'spacing',
      'imageRadius',
      'textHorizontalSpacing',
      'textVerticalSpacing',
      'paginationButtonBorderRadius',
      'paginationButtonBorderSize',
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
      'showCounter',
      'canShare',
      'canDownload',
      'canZoom',
      'autoplay',
      'isSlideshowAllowed',
      'isFullscreenAllowed',
      'shadow',
      'navigationButton',
      'playAndPauseAllowed',
      'openUrlInNewTab',
      'showTitle',
      'showDescription',
      'showButton',
      'openInNewTab',
      'isFullCoverImage',
      'showCaption',
      'fullWidth',
    ];
    $float = [
      'scale',
      'titleFontSize',
      'descriptionFontSize',
      'captionFontSize',
      'paginationButtonTextSize',
    ];
    $style = [
      'css',
      'custom_css',
    ];
    $specific = [
      'type' => [
        'allowed' => [ 'thumbnails', 'mosaic', 'justified', 'masonry', 'slideshow', 'cube', 'carousel', 'cards', 'blog' ],
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
      'titleSource' => [
        'allowed' => [ 'title', 'caption', 'alt', 'price', 'description', 'author', 'date_created', 'exif' ],
        'default' => 'title',
      ],
      'captionSource' => [
        'allowed' => [ 'title', 'caption', 'alt', 'price', 'description', 'author', 'date_created', 'exif' ],
        'default' => 'caption',
      ],
      'descriptionSource' => [
        'allowed' => [ 'title', 'caption', 'alt', 'price', 'description', 'author', 'date_created', 'exif' ],
        'default' => 'description',
      ],
      'textVerticalAlignment' => [
        'allowed' => [ 'bottom', 'top', 'center' ],
        'default' => 'center',
      ],
      'titleAlignment' => [
        'allowed' => [ 'left', 'center', 'right' ],
        'default' => 'left',
      ],
      'buttonAlignment' => [
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
      'textPosition' => [
        'allowed' => [ 'top', 'bottom', 'above', 'below', 'none' ],
        'default' => 'bottom',
      ],
      'imageAnimation' => [
        'allowed' => [ 'fade', 'blur', 'slideH', 'slideV', 'zoom', 'flip', 'rotate' ],
        'default' => 'slideH',
      ],
      'hoverEffect' => [
        'allowed' => ['zoom-out', 'zoom-in', 'slide', 'rotate', 'blur', 'scale', 'sepia', 'overlay', 'overlay-icon-zoom', 'overlay-icon-cart', 'overlay-icon-plus', 'flash', 'shine', 'circle', 'none'],
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
      'clickAction' => [
        'allowed' => [ 'none', 'lightbox', 'url' ],
        'default' => 'lightbox',
      ],
      'sizeTypeWidth' => [
        'allowed' => [ '%', 'px', 'vw', 'rem', 'em' ],
        'default' => '%',
      ],
      'sizeTypeHeight' => [
        'allowed' => [ 'px', 'vh', 'rem', 'em' ],
        'default' => 'px',
      ],
      'imagePosition' => [
        'allowed' => [ 'left', 'right', 'staggered', 'listed' ],
        'default' => 'staggered',
      ],
      'actionUrlSource' => [
        'allowed' => [ 'action_url', 'item_url', 'checkout_url' ],
        'default' => 'action_url',
      ],
      'buttonUrlSource' => [
        'allowed' => [ 'action_url', 'item_url', 'checkout_url' ],
        'default' => 'action_url',
      ],
      'ratio' => [
        'allowed' => [ '1', '1.33', '1.5', '1.77', '0.75', '0.66', '0.56' ],
        'default' => '1.77',
      ],
    ];
    if ( in_array($key, $float) ) {
      return floatval($value);
    }
    elseif ( in_array($key, $empty_number) ) {
      return $value === '' || intval($value) < 0 ? '' : intval($value);
    }
    elseif ( in_array($key, $negative_number) ) {
      return intval($value);
    }
    elseif ( in_array($key, $number) ) {
      return intval(max($value, 1));
    }
    elseif ( in_array($key, $boolean) ) {
      return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    elseif ( in_array($key, $style) ) {
      return wp_strip_all_tags($value);
    }
    elseif ( array_key_exists($key, $specific) ) {
      return in_array($value, $specific[$key]['allowed']) ? $value : $specific[$key]['default'];
    }

    return $value;
  }

  public function __construct($activate, $gallery_id = 0) {
    if ( $activate ) {
      $this->add_option();
    }
    elseif ( $gallery_id ) {
      $this->remove_option($gallery_id);
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
      if ( is_array($option) && isset($saved_data[$key]) ) {
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
      /* Update the options timestamp on options save to prevent data from being read from the cache.*/
      update_post_meta($gallery_id, 'options_timestamp', time());
      return $this->get(NULL, $gallery_id);
    }
    else {
      return wp_send_json(new WP_Error( 'nothing_saved', __( 'Nothing saved.', 'reacg' ), array( 'status' => 400 ) ), 400);
    }
  }

  /**
   * Get the default options or the options for the given gallery.
   *
   * @param $gallery_id
   *
   * @return mixed
   */
  public function get_options($gallery_id) {
    // Get options for the gallery.
    $options = get_option($this->name . $gallery_id, FALSE);

    // Get default options
    // if the gallery options do not exist
    // or fetching direct with gallery ID 0 on selecting Default template.
    if ( $options === FALSE || !$gallery_id ) {
      return $this->options;
    }

    if ( !empty($options) ) {
      $options = json_decode($options, TRUE);
    }

    // Modify the data structure based on the new structure.
    $options = $this->modify($options);

    // In case of types that previously had no lightbox functionality change their default value to none.
    $this->options['general']['clickAction'] = in_array((!isset($options['type']) ? 'thumbnails' : $options['type']), ['thumbnails', 'mosaic', 'masonry']) ? 'lightbox' : 'none';

    // If an option is missing add its default value.
    $options = $this->defaults($this->options, $options);

    // Sanitizing and validating the given data.
    $options = $this->sanitize($options);

    return $options;
  }

  /**
   * Get options rout.
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

    $options = $this->get_options($gallery_id);

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
      // Renamed options.
      $renamed = [
        'slideshow' => [
          ['old' => 'captionsPosition', 'new' => 'textPosition'],
          ['old' => 'captionFontFamily', 'new' => 'textFontFamily'],
          ['old' => 'captionColor', 'new' => 'textColor'],
        ],
        'lightbox' => [
          ['old' => 'captionsPosition', 'new' => 'textPosition'],
          ['old' => 'captionFontFamily', 'new' => 'textFontFamily'],
          ['old' => 'captionColor', 'new' => 'textColor'],
        ],
      ];
      foreach ( $renamed as $key => $group ) {
        if ( isset($options[$key]) ) {
          foreach ( $group as $value ) {
            if ( isset($options[$key][$value['old']]) ) {
              $options[$key][$value['new']] = $options[$key][$value['old']];
              unset($options[$key][$value['old']]);
            }
          }
        }
      }

      // Renamed options values.
      $renamed_values = [
        'thumbnails' => [
          'hoverEffect' =>  ['old' => 'overlay-icon', 'new' => 'overlay-icon-zoom'],
        ],
        'mosaic' => [
          'hoverEffect' =>  ['old' => 'overlay-icon', 'new' => 'overlay-icon-zoom'],
        ],
        'masonry' => [
          'hoverEffect' =>  ['old' => 'overlay-icon', 'new' => 'overlay-icon-zoom'],
        ],
        'blog' => [
          'hoverEffect' =>  ['old' => 'overlay-icon', 'new' => 'overlay-icon-zoom'],
        ],
      ];
      foreach ( $renamed_values as $key => $group ) {
        if ( isset($options[$key]) ) {
          foreach ( $group as $option => $value ) {
            if ( isset($options[$key][$option]) && $options[$key][$option] === $value['old'] ) {
              $options[$key][$option] = $value['new'];
            }
          }
        }
      }

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

      if ( isset($options['lightbox']['showLightbox']) ) {
        $options['general']['clickAction'] = in_array((!isset($options['type']) ? 'thumbnails' : $options['type']), ['thumbnails', 'mosaic', 'masonry']) && $options['lightbox']['showLightbox'] ? 'lightbox' : 'none';
        unset($options['lightbox']['showLightbox']);
      }
      if ( !empty($options['type']) && $options['type'] === "mosaic"
        && !empty($options['mosaic']) && $options['mosaic']['direction'] === "horizontal" ) {
        $options['type'] = "justified";
        $options['justified'] = $options['mosaic'];
        unset($options['mosaic']['direction']);
        unset($options['mosaic']['rowHeight']);
        unset($options['justified']['direction']);
        unset($options['justified']['columns']);
      }
      if ( isset($options['general']['paginationButtonShape']) ) {
        unset($options['general']['paginationButtonShape']);
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
      if ( isset($data['css']) ) {
        unset($data['css']);
      }

      $options = json_encode($data);
      $saved = update_option($this->name . $gallery_id, $options);
      if ( $saved === TRUE ) {
        /* Update the options timestamp on options reset to prevent data from being read from the cache.*/
        update_post_meta($gallery_id, 'options_timestamp', time());
        return new WP_REST_Response( wp_send_json(__( 'Settings successfully reset.', 'reacg' ), 200), 200 );
      }
    }

    return new WP_REST_Response( wp_send_json(__( 'Settings already reset.', 'reacg' ), 200), 200 );
  }

  /**
   * Remove the options by gallery ID.
   *
   * @param $gallery_id
   *
   * @return void
   */
  private function remove_option($gallery_id) {
    delete_option($this->name . $gallery_id);
  }
}
