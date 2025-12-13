<?php
/**
 * Plugin Name: ReGallery
 * Description: Photo Gallery, Image Gallery - lets you create responsive, SEO-optimized image gallery with AI generated titles, descriptions & alt text.
 * Version: 1.17.13
 * Requires at least: 4.6
 * Requires PHP: 7.0
 * Author: ReGallery Team
 * Plugin URI:  https://regallery.team/?utm_source=wordpress_plugin&utm_medium=plugin_uri
 * Author URI:  https://regallery.team/?utm_source=wordpress_plugin&utm_medium=auhor_uri
 * Text Domain: reacg
 * License: http://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || die('Access Denied');

final class REACG {
  /**
   * The single instance of the class.
   */
  protected static $_instance = null;

  public $plugin_dir = '';
  public $plugin_url = '';
  public $main_file = '';
  public $version = '1.17.13';
  public $prefix = 'reacg';
  public $shortcode = 'REACG';
  public $nicename = 'Re Gallery';
  public $author = 'Re Gallery Team';
  public $website_url = 'https://regallery.team';
  public $wp_plugin_url = "https://wordpress.org/support/plugin/regallery";
  public $nonce = 'reacg_nonce';
  public $rest_root = "";
  public $rest_nonce = "";
  public $no_image = '/assets/images/no_image.png';
  /* $abspath variable is using as defined APSPATH doesn't work in wordpress.com */
  public $abspath = '';

  public $allowed_post_types = [];
  public $woocommerce_is_active = FALSE;

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

    add_action('init', array($this, 'define_translatable_constants'));

    define('REACG_PLUGIN_DIR', $this->plugin_dir );
    define('REACG_PLUGIN_URL', $this->plugin_url );
    define('REACG_CUSTOM_POST_TYPE', 'reacg' );
    define('REACG_PREFIX', $this->prefix );
    define('REACG_NICENAME', $this->nicename );
    define('REACG_AUTHOR', $this->author );
    define('REACG_WEBSITE_URL', $this->website_url );
    define('REACG_VERSION', $this->version );
    define('REACG_WEBSITE_URL_UTM', add_query_arg(['utm_source' => 'wordpress_plugin', 'utm_content' => $this->version], $this->website_url) );
    define('REACG_NONCE', $this->nonce );
    define('REACG_WP_PLUGIN_URL', $this->wp_plugin_url );
    define('REACG_WP_PLUGIN_SUPPORT_URL', $this->wp_plugin_url . '/#new-post' );
    define('REACG_WP_PLUGIN_REVIEW_URL', $this->wp_plugin_url . '/reviews#new-post' );
  }

  /**
   * Actions.
   */
  private function add_actions() {
    add_action('init', array($this, 'post_type_gallery'), 9);
    add_action('init', array($this, 'shortcode'));
    add_action('init', array($this, 'deactivation'));
    add_action('init', array($this, 'demo'));

    // Register scripts/styles.
    add_action('wp_enqueue_scripts', array($this, 'register_frontend_scripts'));
    add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts'));
    // Add data attributes to avoid conflicts with caching plugins.
    add_filter('script_loader_tag', array($this, 'script_loader_tag'), 10 ,2 );

    // Enqueue block editor assets for Gutenberg.
    add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));

    // Register widget for Elementor.
    add_action('elementor/widgets/widgets_registered', array($this, 'register_elementor_widget'));
    // Fires after elementor editor styles are enqueued.
    add_action('elementor/editor/after_enqueue_styles', array($this, 'enqueue_elementor_styles'), 11);

    // Register Divi module.
    add_action( 'divi_extensions_init', array($this, 'initialize_divi_extension') );

    // Register WP Bakery widget.
    add_action( 'vc_before_init', array($this, 'register_wpbakery_widget') );

    // Register Beaver Builder module.
    add_action( 'init', array($this, 'register_buiver_builder_widget') );

    // Register Bricks Builder element.
    add_action( 'init', array($this, 'register_bricks_builder_element'), 11);

    // Actions on the plugin activate/deactivate.
    register_activation_hook(__FILE__, array($this, 'global_activate'));
    add_action('wpmu_new_blog', array($this, 'new_blog_added'), 10, 6);
    register_deactivation_hook( __FILE__, array($this, 'global_deactivate'));

    add_action('init', array($this, 'load_string'), 8);
    add_action('admin_init', array($this, 'do_activation_redirect'));

    require_once ($this->plugin_dir . '/includes/admin-notices.php');
    new REACG_Admin_Notices();
  }

  /**
   * Translations should be loaded at the init.
   *
   * @return void
   */
  public function define_translatable_constants() {
    $this->allowed_post_types = [
      'post' => ['title' => __('Posts', 'reacg'), 'class' => 'dashicons-admin-post'],
      'page' => ['title' => __('Pages', 'reacg'), 'class' => 'dashicons-admin-page'],
      'postdynamic' => ['title' => __('Posts', 'reacg'), 'class' => 'dashicons-admin-post reacg-dynamic'],
      'pagedynamic' => ['title' => __('Pages', 'reacg'), 'class' => 'dashicons-admin-page reacg-dynamic'],
    ];
    $this->woocommerce_is_active = class_exists( 'WooCommerce' );
    if ( $this->woocommerce_is_active ) {
      $this->allowed_post_types['product'] = [ 'title' => __('WooCommerce Products', 'reacg'), 'class' => 'dashicons-archive' ];
      $this->allowed_post_types['productdynamic'] = [ 'title' => __('WooCommerce Products', 'reacg'), 'class' => 'dashicons-archive reacg-dynamic' ];
    }

    define('REACG_ALLOWED_POST_TYPES', $this->allowed_post_types);
    define('REACG_BUY_NOW_TEXT', esc_html__('Upgrade to Pro', 'reacg') );
  }

  /**
   * Load translated strings.
   */
  public function load_string() {
    load_plugin_textdomain( 'reacg', false, plugin_basename(dirname(__FILE__)) . '/languages' );
  }

  /**
   * Register widget for Elementor.
   */
  public function register_elementor_widget() {
    if ( defined('ELEMENTOR_PATH') && class_exists('Elementor\Widget_Base') ) {
      require_once ($this->plugin_dir . '/builders/elementor/elementor.php');
      if ( \Elementor\Plugin::instance()->preview->is_preview_mode() ) {
        // Enqueue scripts only in preview mode.
        REACGLibrary::enqueue_scripts();
      }
      \Elementor\Plugin::instance()->widgets_manager->register( new REACG_Elementor() );
    }
  }

  /**
   * Enqueue Elementor widget styles.
   *
   * @return void
   */
  public function enqueue_elementor_styles() {
    wp_enqueue_style($this->prefix . '_elementor', $this->plugin_url . '/builders/elementor/styles/elementor.css', [], $this->version);
  }

  public function initialize_divi_extension() {
    if ( ! class_exists( 'ET_Builder_Element' ) ) {
      return;
    }
    require_once ($this->plugin_dir . '/builders/divi/includes/divi.php');
  }

  public function register_wpbakery_widget() {
    if ( ! function_exists( 'vc_map' ) ) {
      return;
    }
    REACGLibrary::enqueue_scripts();
    require_once ($this->plugin_dir . '/builders/wpbakery/wpbakery.php');

    new REACG_WPBakery($this);
  }

  public function register_buiver_builder_widget() {
    if ( class_exists( 'FLBuilder' ) ) {
      require_once $this->plugin_dir . '/builders/beaverbuilder/beaverbuilder.php';
    }
  }

  public function register_bricks_builder_element($elements_manager) {
    if ( !class_exists('\Bricks\Elements') ) {
      return;
    }

    \Bricks\Elements::register_element( $this->plugin_dir . '/builders/bricks/bricks.php' );
  }

  /**
   * Create custom post types.
   */
  public function post_type_gallery() {
    $this->rest_root = rest_url() . "reacg/v1/";
    $this->rest_nonce = wp_create_nonce( 'wp_rest' );
    define('REACG_REST_NONCE', $this->rest_nonce );
    require_once($this->plugin_dir . '/includes/gallery.php');
    new REACG_Gallery( $this );
    require_once($this->plugin_dir . '/includes/posts.php');
    new REACG_Posts( $this );
  }

  /**
   * Add a shortcode.
   */
  public function shortcode() {
    require_once($this->plugin_dir . '/includes/shortcode.php');
    new REACG_Shortcode( $this );
  }

  /**
   * Add a functional to the deactivation.
   */
  public function deactivation() {
    require_once($this->plugin_dir . '/includes/deactivate.php');
    new REACG_Deactivate( $this );
  }

  /**
   * Add a functional to the deactivation.
   */
  public function demo($redirect = FALSE) {
    require_once($this->plugin_dir . '/includes/demo.php');
    return new REACG_Demo($redirect);
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
      'plugin_url' => $this->plugin_url,
      'upgrade' => [
        'text' => REACG_BUY_NOW_TEXT,
        'url' => add_query_arg( ['utm_campaign' => 'upgrade'], REACG_WEBSITE_URL_UTM . '#pricing' ),
      ],
      'text' => [
        'load_more' => __('Load more', 'reacg'),
        'search' => __('Search', 'reacg'),
        'no_data' => __('There is not data.', 'reacg'),
      ],
    ) );
  }

  /**
   * Add data attributes to avoid conflicts with caching plugins.
   *
   * @param $tag
   * @param $handle
   *
   * @return array|mixed|string|string[]
   */
  public function script_loader_tag( $tag, $handle ){
    if ( $handle == $this->prefix . '_thumbnails' ) {
      return str_replace( '<script', '<script data-no-optimize="1" data-no-defer="1"', $tag );
    }

    return $tag;
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

    wp_register_style($this->prefix . '_select2', $this->plugin_url . '/assets/css/select2.min.css', [], '4.0.3');
    wp_register_style($this->prefix . '_posts', $this->plugin_url . '/assets/css/posts.css', [$this->prefix . '_select2'], $this->version);
    $required_styles[] = $this->prefix . '_posts';
    wp_register_style($this->prefix . '_admin', $this->plugin_url . '/assets/css/admin.css', $required_styles, $this->version);

    wp_register_script($this->prefix . '_select2', $this->plugin_url . '/assets/js/select2.min.js', ['jquery'], '4.0.3');
    wp_register_script($this->prefix . '_posts', $this->plugin_url . '/assets/js/posts.js', [$this->prefix . '_select2'], $this->version);
    $required_scripts[] = $this->prefix . '_posts';
    wp_register_script($this->prefix . '_admin', $this->plugin_url . '/assets/js/admin.js', $required_scripts, $this->version);
    wp_localize_script($this->prefix . '_admin', 'reacg', array(
      'insert' => __('Insert', 'reacg'),
      'update' => __('Update', 'reacg'),
      'update_thumbnail' => __('Update video cover', 'reacg'),
      'edit' => __('Edit', 'reacg'),
      'edit_thumbnail' => __('Edit video cover', 'reacg'),
      'choose_images' => __('Choose images', 'reacg'),
      'generate' => __('Generate', 'reacg'),
      'regenerate' => __('Regenerate', 'reacg'),
      'proceed' => __('Proceed', 'reacg'),
      'ai_title_is_required' => __('Title is required. Make sure it accurately describes the image.', 'reacg'),
      'ai_popup_additional_notes_label' => __('Additional Notes', 'reacg'),
      'ai_popup_additional_notes_placeholder' => __('You can add additional notes here to help generate a more accurate text.', 'reacg'),
      'ai_popup_title_heading' => __('Generative AI Title', 'reacg'),
      'ai_popup_title_field_label' => __('Generated Title', 'reacg'),
      'ai_popup_title_notice' => __('The title will be generated based on the image.', 'reacg'),
      'ai_popup_caption_heading' => __('Generative AI Caption', 'reacg'),
      'ai_popup_caption_field_label' => __('Generated Caption', 'reacg'),
      'ai_popup_caption_notice' => __('The caption will be generated based on the image.', 'reacg'),
      'ai_popup_alt_desc_notice' => __('The text will be generated based on the image title. Please ensure that the title is not empty and accurately describes the image.', 'reacg'),
      'ai_popup_description_heading' => __('Generative AI Description', 'reacg'),
      'ai_popup_description_field_label' => __('Generated Description', 'reacg'),
      'ai_popup_alt_heading' => __('Generative AI Alt', 'reacg'),
      'ai_popup_alt_field_label' => __('Generated Alt', 'reacg'),
      'ai_highlight' => __('Use our built-in AI tools to instantly generate a Description text and Alt text - ideal for better SEO and accessibility.', 'reacg'),
      'enter_license_key' => __('Please enter license key.', 'reacg'),
      'no_image' => $this->plugin_url . $this->no_image,
      'rest_nonce' => $this->rest_nonce,
      'allowed_post_types' => REACG_ALLOWED_POST_TYPES,
      'ajax_url' => wp_nonce_url(admin_url('admin-ajax.php'), -1, $this->nonce),
    ));

    // Register general styles/scripts.
    $this->register_general_scripts();
  }

  /**
   * Register frontend scripts and styles.
   */
  public function register_frontend_scripts() {
    $this->register_general_scripts();
    if ( isset($_GET['bricks']) && $_GET['bricks'] === 'run' ) {
      wp_enqueue_style($this->prefix . '_bricks', $this->plugin_url . '/builders/bricks/bricks.css', [], '1.0');
    }
  }

  /**
   * Enqueue scripts/styles for Gutenberg.
   *
   * @return void
   */
  public function enqueue_block_editor_assets() {
    $required_scripts = [
      $this->prefix . '_thumbnails',
      'wp-blocks',
      'wp-element',
      'wp-block-editor',
      'wp-components',
      $this->prefix . '_admin'
    ];
    $required_styles = [
      $this->prefix . '_general',
      'wp-edit-blocks',
      $this->prefix . '_admin'
    ];

    wp_enqueue_script($this->prefix . '_gutenberg', $this->plugin_url . '/builders/gutenberg/scripts/gutenberg.js', $required_scripts, $this->version);
    wp_localize_script($this->prefix . '_gutenberg', 'reacg_gutenberg', array(
      'title' => $this->nicename,
      'description' => __("Display images with various visual effects in responsive gallery.", "reacg"),
      'setup_wizard_description' => __("Create new gallery or select the existing one.", "reacg"),
      'create_button' => __("Create new gallery", "reacg"),
      'plugin_url' => $this->plugin_url,
      'plugin_version' => $this->version,
      'icon' => $this->plugin_url . '/assets/images/icon.svg',
      'data' => REACGLibrary::get_shortcodes($this, TRUE),
      'ajax_url' => wp_nonce_url(admin_url('admin-ajax.php'), -1, $this->nonce),
    ));
    $gallery_ids = REACGLibrary::get_galleries();
    $data = [];
    foreach ( $gallery_ids as $galleryId ) {
      $data[$galleryId] = REACGLibrary::get_data($galleryId);
    }
    wp_localize_script(REACG_PREFIX . '_gutenberg', 'reacg_data', $data);
    wp_enqueue_style($this->prefix . '_gutenberg', $this->plugin_url . '/builders/gutenberg/styles/gutenberg.css', $required_styles, $this->version);
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
   *
   * @param $activate
   *
   * @return void
   */
  public function activate($activate = TRUE) {
    // Register the custom post type on activate also to affect recreation of rewrite rules.
    $this->post_type_gallery();
    // Recreate rewrite rules after registering the new custom post type to ensure the new post permalinks work.
    global $wp_rewrite;
    $wp_rewrite->init();
    $wp_rewrite->flush_rules();

    if ( $activate ) {
      add_option( 'reacg_do_activation_redirect', true );
      // Set installation time.
      REACGLibrary::installed_time();

      // Add default options.
      require_once REACG()->plugin_dir . "/includes/options.php";
      new REACG_Options(TRUE);
    }
  }

  public function do_activation_redirect() {
    if ( get_option( 'reacg_do_activation_redirect', FALSE ) ) {
      delete_option( 'reacg_do_activation_redirect' );

      // Avoid redirect on bulk activation
      if ( ! isset( $_GET['activate-multi'] ) ) {
        if ( strpos($this->plugin_url, 'playground.wordpress.net') !== FALSE ) {
          $demo = $this->demo(TRUE);
          $galleries = $demo->import_data();
          if ( count($galleries) === 1 ) {
            // Open the created gallery only if there is only one gallery created, otherwise open the galleries list page.
            wp_safe_redirect(admin_url('post.php?post=' . $galleries[0] . '&action=edit'));
            exit;
          }
        }
        wp_safe_redirect( admin_url( 'edit.php?post_type=reacg' ) );
        exit;
      }
    }
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
