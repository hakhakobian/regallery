<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Demo {
  private $item_url = "https://regallery.team/core/wp-content/uploads/";
  private $galleries = [
    [
      'title'  => 'Alpine Symphony',
      'items'       => [ 1, 2, 3, 4, 5, 6 ],
      'options' => [
        'templateType' => "my",
        'css' => '.thumbnail-gallery__image-wrapper:hover .thumbnail-gallery__title_on-hover .MuiImageListItemBar-title, .thumbnail-gallery__image-wrapper:hover .thumbnail-gallery__title_on-hover .MuiImageListItemBar-subtitle, .thumbnail-gallery__image-wrapper:hover .thumbnail-gallery__title_on-hover .MuiImageListItemBar-titleWrap, .photo-album-item__image-wrapper:hover .photo-album-item__title_on-hover .MuiImageListItemBar-title, .photo-album-item__image-wrapper:hover .photo-album-item__title_on-hover .MuiImageListItemBar-subtitle, .photo-album-item__image-wrapper:hover .photo-album-item__title_on-hover .MuiImageListItemBar-titleWrap  {
  opacity: 1;
  -webkit-transition: all .35s cubic-bezier(.22, .61, .36, 1);
  -moz-transition: all .35s cubic-bezier(.22, .61, .36, 1);
  transition: all .35s cubic-bezier(.22, .61, .36, 1);
  -webkit-transform: translateY(0);
  transform: translateY(0);
 }
 .thumbnail-gallery__image-wrapper .thumbnail-gallery__title_on-hover .MuiImageListItemBar-title, .photo-album-item__image-wrapper .photo-album-item__title_on-hover .MuiImageListItemBar-title  {
  opacity: 0;
  font-weight: 600;
  letter-spacing: .03em;
  text-transform: uppercase;
  line-height: 2em;
  transform: translateY(-35px);
  -webkit-transition: opacity .2s, -webkit-transform 0s .2s;
  transition: opacity .2s, transform 0s .2s;
 }
 .thumbnail-gallery__image-wrapper .thumbnail-gallery__title_on-hover .MuiImageListItemBar-subtitle, .photo-album-item__image-wrapper .photo-album-item__title_on-hover .MuiImageListItemBar-subtitle  {
  opacity: 0;
  font-weight: 300;
  text-transform: uppercase;
  -webkit-transform: translateY(35px);
  transform: translateY(35px);
  -webkit-transition: opacity .2s, -webkit-transform 0s .2s;
  transition: opacity .2s, transform 0s .2s;
 }
 .thumbnail-gallery__image-wrapper .thumbnail-gallery__title_on-hover .MuiImageListItemBar-titleWrap, .photo-album-item__image-wrapper .photo-album-item__title_on-hover .MuiImageListItemBar-titleWrap  {
  -webkit-transform: translateY(-50%);
  transform: translateY(-50%);
  transition: all .35s cubic-bezier(.22, .61, .36, 1);
 }
',
        'custom_css' => '',
        'type' => 'mosaic',
        'thumbnails' => [
          'fillContainer' => FALSE, #boolean
          'aspectRatio' => '1.77', #string
          'width' => 430, #number
          'height' => 380, #number
          'columns' => 3, #number
          'gap' => 10, #number
          'itemBorder' => 0, #number
          'itemBackgroundColor' => '', #string
          'itemBorderRadius' => 0, #number
          'backgroundColor' => '', #string
          'containerPadding' => 0, #number
          'padding' => 0, #number
          'paddingColor' => '', #string
          'borderRadius' => 0, #number
          'hoverEffect' => 'zoom-in', #string zoom-out | zoom-in | slide | rotate | blur | scale | sepia | overlay | overlay-icon-zoom | overlay-icon-cart | overlay-icon-plus | overlay-icon-fullscreen | flash | shine | circle | none
          'showTitle' => FALSE, #boolean
          'titleVisibility' => 'alwaysShown', #string alwaysShown | onHover
          'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
          'titlePosition' => 'bottom', #string bottom | top | center | above | below
          'titleAlignment' => 'left', #string left | center | right
          'titleColor' => '#CCCCCC', #string
          'titleFontSize' => 12, #number
          'titleFontFamily' => 'Inherit', #string
          'overlayTextBackground' => 'rgba(0, 0, 0, 0.5)', #string
          'invertTextColor' => FALSE, #boolean
          'paginationType' => 'simple', #string simple | scroll | loadMore | none
          'showCaption' => FALSE, #boolean
          'captionVisibility' => 'alwaysShown', #string alwaysShown | onHover
          'captionPosition' => 'bottom', #string bottom | top | center | above | below
          'captionSource' => 'caption', #string title | caption | alt | price | description | author | date_created | exif
          'captionFontSize' => 10, #number
          'captionFontColor' => '#CCCCCC', #string
          'showDescription' => FALSE, #boolean
          'descriptionSource' => 'description', #string title | caption | alt | price | description | author | date_created | exif
          'descriptionPosition' => 'below', #string above | below
          'descriptionFontSize' => 18, #number
          'descriptionFontColor' => '#CCCCCC', #string
          'descriptionMaxRowsCount' => 3, #number
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
          'hoverEffect' => 'zoom-in', #string zoom-out | zoom-in | slide | rotate | blur | scale | sepia | overlay | overlay-icon-zoom | overlay-icon-cart | overlay-icon-plus | overlay-icon-fullscreen | flash | shine | circle | none
          'showTitle' => TRUE, #boolean
          'titleVisibility' => 'onHover', #string alwaysShown | onHover
          'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
          'titlePosition' => 'center', #string bottom | top | center | above | below
          'titleAlignment' => 'center', #string left | center | right
          'titleColor' => '#3b3b3b', #string
          'titleFontSize' => 18, #number
          'titleFontFamily' => 'Raleway', #string
          'overlayTextBackground' => 'rgba(255, 255, 255, 0.8)', #string
          'invertTextColor' => FALSE, #boolean
          'paginationType' => 'simple', #string simple | none
          'showCaption' => TRUE, #boolean
          'captionVisibility' => 'onHover', #string alwaysShown | onHover
          'captionPosition' => 'center', #string bottom | top | center
          'captionSource' => 'caption', #string title | caption | alt | price | description | author | date_created | exif
          'captionFontSize' => 13, #number
          'captionFontColor' => 'grey', #string
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
          'hoverEffect' => 'overlay-icon-zoom', #string zoom-out | zoom-in | slide | rotate | blur | scale | sepia | overlay | overlay-icon-zoom | overlay-icon-cart | overlay-icon-plus | overlay-icon-fullscreen | flash | shine | circle | none
          'showTitle' => FALSE, #boolean
          'titleVisibility' => 'alwaysShown', #string alwaysShown | onHover
          'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
          'titlePosition' => 'bottom', #string bottom | top | center | above | below
          'titleAlignment' => 'left', #string left | center | right
          'titleColor' => '#CCCCCC', #string
          'titleFontSize' => 12, #number
          'titleFontFamily' => 'Inherit', #string
          'overlayTextBackground' => 'rgba(0, 0, 0, 0.5)', #string
          'invertTextColor' => FALSE, #boolean
          'paginationType' => 'simple', #string simple | none
          'showCaption' => FALSE, #boolean
          'captionVisibility' => 'alwaysShown', #string alwaysShown | onHover
          'captionPosition' => 'bottom', #string bottom | top | center
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
          'hoverEffect' => 'shine', #string zoom-out | zoom-in | slide | rotate | blur | scale | sepia | overlay | overlay-icon-zoom | overlay-icon-cart | overlay-icon-plus | overlay-icon-fullscreen | flash | shine | circle | none
          'showTitle' => FALSE, #boolean
          'titleVisibility' => 'alwaysShown', #string alwaysShown | onHover
          'titleSource' => 'title', #string title | caption | alt | price | description | author | date_created | exif
          'titlePosition' => 'bottom', #string bottom | top | center | above | below
          'titleAlignment' => 'left', #string left | center | right
          'titleColor' => '#CCCCCC', #string
          'titleFontSize' => 12, #number
          'titleFontFamily' => 'Inherit', #string
          'overlayTextBackground' => 'rgba(0, 0, 0, 0.5)', #string
          'invertTextColor' => FALSE, #boolean
          'paginationType' => 'scroll', #string scroll | loadMore | none
          'showCaption' => FALSE, #boolean
          'captionVisibility' => 'alwaysShown', #string alwaysShown | onHover
          'captionPosition' => 'bottom', #string bottom | top | center
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
          'textPosition' => 'bottom', #string top | bottom | above | below
          'textFontFamily' => 'Inherit', #string
          'textColor' => '#FFFFFF', #string
          'textBackground' => 'rgba(0, 0, 0, 0.5)', #string
          'invertTextColor' => FALSE, #boolean
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
          'hoverEffect' => 'zoom-in', #string zoom-out | zoom-in | slide | rotate | blur | scale | sepia | overlay | overlay-icon-zoom | overlay-icon-cart | overlay-icon-plus | overlay-icon-fullscreen | flash | shine | circle | none
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
          'openUrlInNewTab' => TRUE, #boolean
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
          'enableWatermark' => FALSE, #boolean
          'watermarkImageURL' => REACG_PLUGIN_URL . '/assets/images/icon.svg', #string
          'watermarkTransparency' => 20, #number
          'watermarkSize' => 30, #number
          'watermarkPosition' => 'middle-center', #string top-left | top-center | top-right | middle-left | middle-center | middle-right | bottom-left | bottom-center | bottom-right
          'enableSearch' => false, #boolean
          'searchPlaceholderText' => '', #string
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
          'textPosition' => 'bottom', #string top | bottom | above | below
          'textFontFamily' => 'Inherit', #string
          'textColor' => '#FFFFFF', #string
          'textBackground' => '', #string
          'invertTextColor' => FALSE, #boolean
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
      ],
    ],
  ];
  private $images = [
    [
      'key' => 1,
      'name' => '2025/10/From-frozen-to-flourishing.jpg',
      'title' => 'From frozen to flourishing',
      'caption' => 'Winter',
      'description' => 'A mountain landscape transitioning from winter frost to early signs of spring, capturing the resilience and renewal of alpine nature.',
      'alt' => 'Alpine mountains transitioning from frozen winter landscape to flourishing spring scenery',
      'action_url' => REACG_WEBSITE_URL_UTM . '&utm_medium=demo_gallery&utm_campaign=action_url',
      'exif' => 'Aperture: f/2',
    ],
    [
      'key' => 2,
      'name' => '2025/10/The-mountains-breath.jpg',
      'title' => 'The mountains breath',
      'caption' => 'Clouds',
      'description' => 'Soft clouds drift over towering mountain peaks, creating a sense of calm movement as the landscape appears to breathe.',
      'alt' => 'Mountain peaks with clouds rolling across the alpine landscape',
      'action_url' => REACG_WEBSITE_URL_UTM . '&utm_medium=demo_gallery&utm_campaign=action_url',
      'exif' => 'Aperture: f/2',
    ],
    [
      'key' => 3,
      'name' => '2025/10/Silence-for-the-song.jpg',
      'title' => 'Silence for the song',
      'caption' => 'Stillness',
      'description' => 'A quiet alpine valley bathed in soft light, where stillness and solitude define the natural atmosphere.',
      'alt' => 'Peaceful alpine valley with soft light and quiet natural surroundings',
      'action_url' => REACG_WEBSITE_URL_UTM . '&utm_medium=demo_gallery&utm_campaign=action_url',
      'exif' => 'Aperture: f/2',
    ],
    [
      'key' => 4,
      'name' => '2025/10/Whispers-of-the-Melting-Peaks.jpg',
      'title' => 'Whispers of the Melting Peaks',
      'caption' => 'Snow',
      'description' => 'Melting snow reveals rugged mountain textures, symbolizing the gentle shift from winter to warmer seasons.',
      'alt' => 'Snow-covered alpine peaks beginning to melt under soft seasonal light',
      'action_url' => REACG_WEBSITE_URL_UTM . '&utm_medium=demo_gallery&utm_campaign=action_url',
      'exif' => 'Aperture: f/2',
    ],
    [
      'key' => 5,
      'name' => '2025/10/The-tallest-peaks-welcome-renewal.jpg',
      'title' => 'The tallest peaks',
      'caption' => 'Seasons',
      'description' => 'Majestic mountain summits rise above the landscape, welcoming renewal as snow retreats and life returns.',
      'alt' => 'Tall alpine mountain peaks standing above valleys during seasonal renewal',
      'action_url' => REACG_WEBSITE_URL_UTM . '&utm_medium=demo_gallery&utm_campaign=action_url',
      'exif' => 'Aperture: f/2',
    ],
    [
      'key' => 6,
      'name' => '2025/10/When-ice-melts-into-rivers.jpg',
      'title' => 'When ice melts into rivers',
      'caption' => 'Glaciers',
      'description' => 'Glacial ice transforms into flowing rivers, carving paths through the alpine terrain and bringing life downstream.',
      'alt' => 'Glacial ice melting into rivers flowing through an alpine mountain landscape',
      'action_url' => REACG_WEBSITE_URL_UTM . '&utm_medium=demo_gallery&utm_campaign=action_url',
      'exif' => 'Aperture: f/2',
    ],
  ];

  private $date_created;
  private $redirect;

  function __construct($redirect = FALSE) {
    $this->redirect = $redirect;
    $this->date_created = gmdate('Y-m-d H:i:s');
    add_action( 'wp_ajax_reacg_import_demo', array( $this, 'import_data' ) );
  }

  /**
   * @param bool $redirect
   *
   * @return array|void
   */
  public function import_data() {
    $galleries = [];
    $attachments = [];
    if ( is_admin() && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
      $attachments = $this->import_attachments();
      $galleries = $this->import_galleries($attachments);
    }

    if ( $this->redirect ) {
      return $galleries;
    }
    else {
      if ( !empty($galleries) ) {
        /* translators: %d: number of demo galleries created */
        echo sprintf(esc_html(_n('%d demo gallery created!', '%d demo galleries created!', count($galleries), 'regallery')), count($galleries));
        if ( !empty($attachments) ) {
          /* translators: %d: number of sample images imported */
          echo ' ' . sprintf(esc_html(_n('%d sample image imported!', '%d sample images imported!', count($attachments), 'regallery')), count($attachments));
        }
      }
      else {
        esc_html_e('There was a problem with creating the demo galleries!', 'regallery');
      }
    }
    die();
  }

  /**
   * Import galleries.
   *
   * @param $attachments
   *
   * @return array
   */
  private function import_galleries($attachments) {
    $galleries = [];
    foreach ( $this->galleries as $gallery ) {
      $post_id = wp_insert_post([
                                  'post_title'  => !empty($gallery['title']) ? $gallery['title'] : '',
                                  'post_status' => !empty($gallery['status']) ? $gallery['status'] : 'publish',
                                  'post_type'   => REACG_CUSTOM_POST_TYPE,
                                ]);
      if ( ! is_wp_error( $post_id ) ) {
        $attachments_to_import = [];

        foreach ( $gallery['items'] as $item ) {
          if ( array_key_exists( $item, $attachments ) ) {
            $attachments_to_import[] = $attachments[ $item ];
          }
        }

        update_post_meta($post_id, 'images_ids', json_encode($attachments_to_import));
        update_post_meta($post_id, 'images_count', count($attachments_to_import));
        update_post_meta($post_id, 'additional_data', '');
        update_post_meta($post_id, 'gallery_timestamp', $this->date_created);

        if ( !empty($gallery['options']) ) {
          $gallery['options']['template_id'] = $post_id;
          $gallery['options']['title'] = $gallery['title'];
          add_option('reacg_options' . $post_id, json_encode($gallery['options']));
          update_post_meta($post_id, 'options_timestamp', $this->date_created);
        }
        $galleries[] = $post_id;
      }
    }

    return $galleries;
  }

  /**
   * Import attachments.
   *
   * @return array
   */
  private function import_attachments() {
    $attachments = [];
    foreach ( $this->images as $item ) {
      $attachment_id = $this->import_attachment( $item );
      if ( ! is_wp_error( $attachment_id ) && intval( $attachment_id ) > 0 ) {
        $attachments[$item['key']] = $attachment_id;
      }
    }

    return $attachments;
  }

  /**
   * Import the attachment.
   *
   * @param $item
   *
   * @return array|int|WP_Error
   */
  private function import_attachment(  $item  ) {
    // Include the php to use wp_generate_attachment_metadata().
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $item_url = $this->item_url . $item['name'];
    $response = wp_remote_get( $item_url );
    if ( is_wp_error( $response ) ) {
      return $response;
    }
    $status_code = wp_remote_retrieve_response_code( $response );
    if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
      return new WP_Error('upload_failed', "The file doesn't exist. HTTP Status Code: " . $status_code);
    }
    $contents = wp_remote_retrieve_body( $response );
    $name = pathinfo($item_url, PATHINFO_FILENAME);

    // Upload the file.
    $upload = wp_upload_bits( basename( $item_url ), null, $contents );

    if ( array_key_exists( 'error', $upload ) && false !== $upload['error'] ) {
      return new WP_Error('upload_failed', $upload['error']);
    }

    $guid = $upload['url'];
    $file = $upload['file'];
    $file_type = wp_check_filetype( basename( $file ), null );
    $attachment_args = [
      'ID'             => 0,
      'guid'           => $guid,
      'post_title'     => !empty($item['title']) ? $item['title'] : $name,
      'post_excerpt'   => !empty($item['caption']) ? $item['caption'] : '',
      'post_content'   => !empty($item['description']) ? $item['description'] : '',
      'post_date'      => '',
      'post_mime_type' => $file_type['type'],
    ];

    $attachment_args['meta_input'] = array();
    if ( !empty( $item['alt'] ) ) {
      $attachment_args['meta_input']['_wp_attachment_image_alt'] = $item['alt'];
    }
    if ( !empty( $item['action_url'] ) ) {
      $attachment_args['meta_input']['action_url'] = $item['action_url'];
    }
    if ( !empty( $item['exif'] ) ) {
      $attachment_args['meta_input']['exif'] = $item['exif'];
    }

    // Insert the uploaded file into the WordPress Media Library.
    $attachment_id = wp_insert_attachment(
      $attachment_args,
      $file,
      0,
      true
    );

    if ( is_wp_error( $attachment_id ) ) {
      return $attachment_id;
    }

    // Generate all necessary image sizes and metadata for the uploaded file.
    $attachment_meta = wp_generate_attachment_metadata( $attachment_id, $file );

    // Updates the attachment metadata in the DB.
    wp_update_attachment_metadata( $attachment_id, $attachment_meta );

    return $attachment_id;
  }
}
