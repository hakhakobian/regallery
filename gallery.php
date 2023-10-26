<?php
/**
 * Plugin Name: AI Gallery
 * Plugin URI: https://10web.io/plugins/wordpress-gallery/
 * Description: This plugin is a fully responsive gallery plugin with advanced functionality.  It allows having different image galleries for your posts and pages. You can create unlimited number of galleries, combine them into albums, and provide descriptions and tags.
 * Version: 1.0.0
 * Author: Gallery Team
 * Author URI:
 * Text Domain: ai-gallery
 * License: GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') || die('Access Denied');

$bwg = 0;
final class AIG {
  /**
   * The single instance of the class.
   */
  protected static $_instance = null;

  public string $plugin_link = 'https://my_website.io/ai-gallery/';
  public string $utm_source = '?utm_source=ai_gallery&utm_medium=free_plugin';
  public string $plugin_dir = '';
  public string $plugin_url = '';
  public string $main_file = '';
  public string $version = '1.0.0';

  public string $prefix = 'aig';
  public string $shortcode = 'AIG';
  public string $nicename = 'AI Gallery';
  public string $nonce = 'aig_nonce';
  public string $rest_root = "";
  public string $rest_nonce = "";
//  public $is_pro = TRUE;
//  public $options = array();
  public string $upload_dir = '';
  public string $upload_url = '';
  /* $abspath variable is using as defined APSPATH doesn't work in wordpress.com */
  public string $abspath = '';

  /**
   * Ensures only one instance is loaded or can be loaded.
   *
   * @return  self|null
   */
  public static function instance(): ?AIG {
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

    require_once $this->plugin_dir . '/framework/AIGLibrary.php';

    $this->abspath = AIGLibrary::get_abspath();
    $this->plugin_url = plugins_url(plugin_basename(dirname(__FILE__)));
    $this->main_file = plugin_basename(__FILE__);
//    $upload_dir = wp_upload_dir();
//    if ( isset($upload_dir->url) ) {
//      $this->upload_url = $upload_dir->url;
//    }
  }

  /**
   * Actions.
   */
  private function add_actions(): void {
    add_action('init', array($this, 'language_load'), 9);
    add_action('init', array($this, 'post_type_gallery'), 9);
    add_action('init', array($this, 'shortcode'));
//    add_action('admin_menu', array( $this, 'admin_menu' ) );


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
  public function language_load(): void {
    $this->rest_root = rest_url();
    $this->rest_nonce = wp_create_nonce( 'wp_rest' );
    load_plugin_textdomain('aig', FALSE, basename(dirname(__FILE__)) . '/languages');
  }

  /**
   * Create custom post types.
   */
  public function post_type_gallery(): void {
    require_once($this->plugin_dir . '/includes/gallery.php');
    new AIG_Gallery($this);
  }

  /**
   * Add a shortcode.
   */
  public function shortcode(): void {
    require_once($this->plugin_dir . '/includes/shortcode.php');
    new AIG_Shortcode($this);
  }

  /**
   * Plugin menu.
   */
  public function admin_menu(): void {
    $permissions = 'manage_options';
    $parent_slug = 'galleries_' . $this->prefix;

    add_menu_page($this->nicename, $this->nicename, $permissions, $parent_slug, array($this , 'admin_pages'), $this->plugin_url . '/images/icons/icon.svg');
    add_submenu_page($parent_slug, __('Add Galleries/Images', 'aig'), __('Add Galleries/Images', 'aig'), $permissions, $parent_slug, array($this , 'admin_pages'));

//    add_submenu_page(NULL, __('Generate Shortcode', 'aig'), __('Generate Shortcode', 'aig'), $permissions, 'shortcode_' . $this->prefix, array($this , 'admin_pages'));
  }

  /**
   * Register general scripts/styles.
   */
  private function register_general_scripts(): void {
    $required_scripts = [];
    $required_styles = [];

    $used_fonts = is_admin() ? AIGLibrary::get_fonts(FALSE) : AIGLibrary::get_used_fonts();
    if ( !empty($used_fonts) ) {
      $query = implode("|", str_replace(' ', '+', $used_fonts));

      $url = 'https://fonts.googleapis.com/css?family=' . $query;
      $url .= '&subset=greek,latin,greek-ext,vietnamese,cyrillic-ext,latin-ext,cyrillic';
      wp_register_style($this->prefix . '_fonts', $url, null, null);
      $required_styles[] = $this->prefix . '_fonts';
    }

    wp_register_style($this->prefix . '_general', $this->plugin_url . '/assets/css/general.css', $required_styles, $this->version);
    wp_register_script($this->prefix . '_thumbnails', $this->plugin_url . '/assets/js/wp-gallery.js', $required_scripts, $this->version);
    wp_localize_script( $this->prefix . '_thumbnails', 'aig_global', array(
      'rest_root' => esc_url_raw( $this->rest_root ),
      'rest_nonce' => $this->rest_nonce,
    ) );
  }

  /**
   * Register admin pages scripts/styles.
   */
  public function register_admin_scripts(): void {
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
    wp_localize_script($this->prefix . '_admin', 'aig', array(
      'insert' => __('Insert', 'aig'),
      'update' => __('Update', 'aig'),
      'edit_image' => __('Edit image', 'aig'),
      'choose_images' => __('Choose images', 'aig'),
    ));

    // Register general styles/scripts.
    $this->register_general_scripts();
  }

  /**
   * Register frontend scripts and styles.
   */
  public function register_frontend_scripts(): void {
    $this->register_general_scripts();
  }

  /**
   * Enqueue scripts/styles for Gutenberg.
   *
   * @return void
   */
  public function enqueue_block_editor_assets(): void {

    wp_enqueue_script($this->prefix . '_gutenberg', $this->plugin_url . '/assets/js/gutenberg.js', array( 'wp-blocks', 'wp-element' ), $this->version);
    wp_localize_script($this->prefix . '_gutenberg', 'aig', array(
      'title' => $this->nicename,
      'titleSelect' => sprintf(__('Select %s', 'aig'), $this->nicename),
      'iconUrl' => $this->plugin_url . '/assets/images/shortcode_new.jpg',
      'iconSvgUrl' => $this->plugin_url . '/assets/images/icon.svg',
      'shortcodeUrl' => add_query_arg(array('action' => 'shortcode_bwg'), admin_url('admin-ajax.php')),
      'data' => AIGLibrary::get_shortcodes($this),
    ));
    wp_enqueue_style($this->prefix . '_gutenberg', $this->plugin_url . '/assets/css/gutenberg.css', array( 'wp-edit-blocks' ), $this->version);
  }

  /**
   * Global activate.
   *
   * @param $networkwide
   */
  public function global_activate($networkwide): void {
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

  public function new_blog_added( $blog_id, $user_id, $domain, $path, $site_id, $meta ): void {
    if ( is_plugin_active_for_network('gallery/gallery.php') ) {
      switch_to_blog($blog_id);
      $this->activate();
      restore_current_blog();
    }
  }

  /**
   * Activate.
   */
  public function activate($activate = TRUE): void {
    require_once AIG()->plugin_dir . "/includes/options.php";
    new AIG_Options($activate);
  }

  /**
   * Global deactivate.
   *
   * @param $networkwide
   */
  public function global_deactivate($networkwide): void {
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

  /**
   * Prevent adding shortcode conflict with some builders.
   */
//  private function before_shortcode_add_builder_editor() {
//    if ( defined('ELEMENTOR_VERSION') && did_action( 'elementor/loaded' ) ) {
//      add_action('elementor/editor/footer', array( $this, 'global_script' ));
//    }
//    if ( class_exists('FLBuilder') ) {
//      add_action('wp_enqueue_scripts', array( $this, 'global_script' ));
//    }
//  }
//
//  public function enqueue_elementor_widget_scripts() {
//    wp_enqueue_script(AIG()->prefix . 'elementor_widget_js', plugins_url('js/bwg_elementor_widget.js', __FILE__), array( 'jquery' ));
//  }

  /*
   * Change image editors library.
   *
   * Changes the order of use of the image editor library.
   * First, "WP_Image_Editor_GD" is used.
   * */
//  function bwg_change_image_editors_library() {
//    return array( 'WP_Image_Editor_GD', 'WP_Image_Editor_Imagick' );
//  }
}

/**
 * Main instance of AIG.
 *
 * @return AIG The main instance to prevent the need to use globals.
 */
function AIG() {
  return AIG::instance();
}

AIG();