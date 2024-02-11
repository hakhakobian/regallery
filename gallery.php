<?php
/**
 * Plugin Name: ReGallery
 * Description: The plugin designed to suit a wide range of users, from professional photographers to hobbyists, this plugin combines unparalleled speed, lightweight architecture, and the power of React.js for a seamless, real-time interface experience.
 * Version: 1.0.0
 * Author: ReGallery team
 * Text Domain: reacg
 * License: GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') || die('Access Denied');

final class REACG {
  /**
   * The single instance of the class.
   */
  protected static $_instance = null;

  public string $plugin_dir = '';
  public string $plugin_url = '';
  public string $main_file = '';
  public string $version = '1.0.0';

  public string $prefix = 'reacg';
  public string $shortcode = 'REACG';
  public string $nicename = 'ReGallery';
  public string $nonce = 'reacg_nonce';
  public string $rest_root = "";
  public string $rest_nonce = "";
  public string $no_image = '/assets/images/no_image.png';
  /* $abspath variable is using as defined APSPATH doesn't work in wordpress.com */
  public string $abspath = '';

  /**
   * Ensures only one instance is loaded or can be loaded.
   *
   * @return  self|null
   */
  public static function instance() {
    if ( is_null( self::$_instance ) ) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }

  public function __construct() {
    $this->define_constants();
    $this->add_actions();
  }

  private function define_constants() {
    $this->plugin_dir = WP_PLUGIN_DIR . "/" . plugin_basename(dirname(__FILE__));

    require_once $this->plugin_dir . '/framework/REACGLibrary.php';

    $this->abspath = REACGLibrary::get_abspath();
    $this->plugin_url = plugins_url(plugin_basename(dirname(__FILE__)));
    $this->main_file = plugin_basename(__FILE__);
  }

  /**
   * Actions.
   */
  private function add_actions() {
    add_action('init', array($this, 'post_type_gallery'), 9);
    add_action('init', array($this, 'shortcode'));

    // Register scripts/styles.
    add_action('wp_enqueue_scripts', array($this, 'register_frontend_scripts'));
    add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts'));
    // Enqueue block editor assets for Gutenberg.
    add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));

    // Actions on the plugin activate/deactivate.
    register_activation_hook(__FILE__, array($this, 'global_activate'));
    add_action('wpmu_new_blog', array($this, 'new_blog_added'), 10, 6);
    register_deactivation_hook( __FILE__, array($this, 'global_deactivate'));
  }

  /**
   * Languages localization.
   */
  public function language_load() {

  }

  /**
   * Create custom post types.
   */
  public function post_type_gallery() {
    $this->rest_root = rest_url() . "reacg/v1/";
    $this->rest_nonce = wp_create_nonce( 'wp_rest' );
    require_once($this->plugin_dir . '/includes/gallery.php');
    new REACG_Gallery($this);
  }

  /**
   * Add a shortcode.
   */
  public function shortcode() {
    require_once($this->plugin_dir . '/includes/shortcode.php');
    new REACG_Shortcode($this);
  }

  /**
   * Register general scripts/styles.
   */
  private function register_general_scripts() {
    $required_scripts = [];
    $required_styles = [];

    $used_fonts = is_admin() ? REACGLibrary::get_fonts(FALSE) : REACGLibrary::get_used_fonts();
    if ( !empty($used_fonts) ) {
      $query = implode("|", str_replace(' ', '+', $used_fonts));

      $url = 'https://fonts.googleapis.com/css?family=' . $query;
      $url .= '&subset=greek,latin,greek-ext,vietnamese,cyrillic-ext,latin-ext,cyrillic';
      wp_register_style($this->prefix . '_fonts', $url, null, null);
      $required_styles[] = $this->prefix . '_fonts';
    }

    wp_register_style($this->prefix . '_general', $this->plugin_url . '/assets/css/general.css', $required_styles, $this->version);
    wp_register_script($this->prefix . '_thumbnails', $this->plugin_url . '/assets/js/wp-gallery.js', $required_scripts, $this->version);
    wp_localize_script( $this->prefix . '_thumbnails', 'reacg_global', array(
      'rest_root' => esc_url_raw( $this->rest_root ),
      'rest_nonce' => $this->rest_nonce,
      'text' => [
        'load_more' => __('Load more', 'reacg'),
        'no_data' => __('There is not data.', 'reacg'),
      ]
    ) );
  }

  /**
   * Register admin pages scripts/styles.
   */
  public function register_admin_scripts() {
    $required_scripts = array(
      'jquery',
      'jquery-ui-sortable',
      );
    $required_styles = array(
      'wp-admin', // admin styles
      'buttons', // buttons styles
      'media-views', // media uploader styles
      'wp-auth-check', // check all
    );

    wp_register_style($this->prefix . '_admin', $this->plugin_url . '/assets/css/admin.css', $required_styles, $this->version);

    wp_register_script($this->prefix . '_admin', $this->plugin_url . '/assets/js/admin.js', $required_scripts, $this->version);
    wp_localize_script($this->prefix . '_admin', 'reacg', array(
      'insert' => __('Insert', 'reacg'),
      'update' => __('Update', 'reacg'),
      'update_thumbnail' => __('Update thumbnail', 'reacg'),
      'edit' => __('Edit', 'reacg'),
      'edit_thumbnail' => __('Edit thumbnail', 'reacg'),
      'choose_images' => __('Choose images', 'reacg'),
      'no_image' => $this->plugin_url . $this->no_image,
    ));

    // Register general styles/scripts.
    $this->register_general_scripts();
  }

  /**
   * Register frontend scripts and styles.
   */
  public function register_frontend_scripts() {
    $this->register_general_scripts();
  }

  /**
   * Enqueue scripts/styles for Gutenberg.
   *
   * @return void
   */
  public function enqueue_block_editor_assets() {
    wp_enqueue_script($this->prefix . '_gutenberg', $this->plugin_url . '/assets/js/gutenberg.js', array( 'wp-blocks', 'wp-element' ), $this->version);
    wp_localize_script($this->prefix . '_gutenberg', 'reacg', array(
      'title' => $this->nicename,
      'icon' => $this->plugin_url . '/assets/images/icon.svg',
      'data' => REACGLibrary::get_shortcodes($this, TRUE),
    ));
    wp_enqueue_style($this->prefix . '_gutenberg', $this->plugin_url . '/assets/css/gutenberg.css', array( 'wp-edit-blocks' ), $this->version);
  }

  /**
   * Global activate.
   *
   * @param $networkwide
   */
  public function global_activate($networkwide) {
    if ( function_exists('is_multisite') && is_multisite() ) {
      // Run the activation function for each blog, if it is a network activation.
      if ( $networkwide ) {
        global $wpdb;
        // Get all blog ids.
        $blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
        foreach ( $blogids as $blog_id ) {
          switch_to_blog($blog_id);
          $this->activate();
          restore_current_blog();
        }

        return;
      }
    }
    $this->activate();
  }

  public function new_blog_added( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
    if ( is_plugin_active_for_network('gallery/gallery.php') ) {
      switch_to_blog($blog_id);
      $this->activate();
      restore_current_blog();
    }
  }

  /**
   * Activate.
   */
  public function activate($activate = TRUE) {
    require_once REACG()->plugin_dir . "/includes/options.php";
    new REACG_Options($activate);
  }

  /**
   * Global deactivate.
   *
   * @param $networkwide
   */
  public function global_deactivate($networkwide) {
    if ( function_exists('is_multisite') && is_multisite() ) {
      // Run the deactivation function for each blog, if it is a network deactivation.
      if ( $networkwide ) {
        global $wpdb;
        // Get all blog ids.
        $blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
        foreach ( $blogids as $blog_id ) {
          switch_to_blog($blog_id);
          $this->activate(false);
          restore_current_blog();
        }

        return;
      }
    }
    $this->activate(false);
  }
}

/**
 * Main instance of REACG.
 *
 * @return REACG The main instance to prevent the need to use globals.
 */
function REACG() {
  return REACG::instance();
}

REACG();
