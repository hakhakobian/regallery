<?php
defined('ABSPATH') || die('Access Denied');

class REACG_Demo {
  private $url = "https://regallery.team/core/wp-content/uploads/2024/08/";
  private $images = [
    '1983.06.jpg',
    '1983.08.jpg',
    '1983.09.jpg',
    '1985.05.jpg',
    '1985.07.jpg',
    '1985.08.jpg',
  ];
  public function import_data() {
    $attachments = $this->import_attachments();

    $this->import_gallery($attachments);
  }

  /**
   * Import the gallery.
   *
   * @param $attachments
   *
   * @return void
   */
  private function import_gallery($attachments) {
    // Create the gallery
    $gallery = [
      'post_title' => 'Demo',
      'post_status' => 'publish',
      'post_type' => 'reacg',
    ];
    $post_id = wp_insert_post($gallery);

    // Add images to the gallery.
    if ( ! is_wp_error( $post_id ) && !empty($attachments) ) {
      update_post_meta($post_id, 'images_ids', json_encode($attachments));
      update_post_meta($post_id, 'images_count', count($attachments));
    }
  }

  /**
   * Import attachments.
   *
   * @return array
   */
  private function import_attachments() {
    $attachments = [];
    foreach ( $this->images as $image ) {
      $attachment_id = $this->import_attachment( $image );
      if ( ! is_wp_error( $attachment_id ) && intval( $attachment_id ) > 0 ) {
        $attachments[] = $attachment_id;
      }
    }

    return $attachments;
  }

  /**
   * Import the attachment.
   *
   * @param $image
   *
   * @return array|int|WP_Error
   */
  private function import_attachment(  $image  ) {
    // Include the php to use wp_generate_attachment_metadata().
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $response = wp_remote_get( $this->url . $image );
    if ( is_wp_error( $response ) ) {
      return $response;
    }
    $contents = wp_remote_retrieve_body( $response );
    $name = pathinfo($image, PATHINFO_FILENAME);

    // Upload the file.
    $upload = wp_upload_bits( basename( $image ), null, $contents );

    if ( array_key_exists( 'error', $upload ) && false !== $upload['error'] ) {
      return new WP_Error('upload_failed', $upload['error']);
    }

    $guid = $upload['url'];
    $file = $upload['file'];
    $file_type = wp_check_filetype( basename( $file ), null );
    $attachment_args = array(
      'ID'             => 0,
      'guid'           => $guid,
      'post_title'     => $name,
      'post_excerpt'   => '',
      'post_content'   => '',
      'post_date'      => '',
      'post_mime_type' => $file_type['type'],
      'meta_input' => ['_wp_attachment_image_alt' => $name],
    );
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

function reacg_create_demo_content() {
  if ( is_admin() && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
    $demo = new REACG_Demo();
    $demo->import_data();
  }
}
