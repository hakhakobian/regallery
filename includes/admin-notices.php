<?php
defined('ABSPATH') || die('Access Denied');
class REACG_Admin_Notices {
  private $excluded_pages = [ 'plugin-install.php', 'theme-install.php', 'plugin-editor.php', 'theme-editor.php' ];

  private $notices = [ 'feedback' ];

	private $installed_time = null;

  private $ajax_action_dissmiss = "reacg_dismiss_notice";

  private $allowed_statuses = [ '', 'skipped', 'dismissed', 'reviewed' ];

  /**
   * Notice asking for a review.
   *
   * @return bool
   */
	private function feedback() {
    $notice_id = 'feedback';

    $status = get_option('reacg_notice_' . esc_sql($notice_id) . '_status', '');
    $now  = time();

    $first_period_not_passed = strtotime( '+7 days', $this->installed_time ) > $now;
    $second_period_not_passed = strtotime( '+30 days', $this->installed_time ) > $now;

    if (  ( $first_period_not_passed /*&& $status == ''*/ )
      || ( $second_period_not_passed && $status == 'skipped' )
      || $status == 'dismissed'
      || $status == 'reviewed' ) {
      return FALSE;
    }

		$dismiss_url = wp_nonce_url(add_query_arg( [
			'action' => $this->ajax_action_dissmiss,
			'notice_id' => $notice_id,
		], admin_url('admin-ajax.php') ), -1, REACG_NONCE);

		$options = [
      /* translators: %s: celebratory emoji */
			'title' => sprintf( __( "That’s awesome!%s", "regallery" ), "🎉" ),
      /* translators: 1: plugin name in bold HTML tags, 2: line break HTML tag, 3: line break HTML tag */
			'description' => sprintf( __( "We noticed you’ve been using %1\$s for a while now.%2\$sIf you’re enjoying it, we’d really appreciate it if you could share your experience by leaving a quick review on WordPress.org.%3\$sYour feedback helps us improve and helps others discover out plugin!", "regallery" ), "<strong>" . REACG_NICENAME . "</strong>", "<br />", "<br />" ),
      'dismiss_url' => add_query_arg( [
                                        'status' => !$second_period_not_passed ? 'dismissed' : 'skipped',
                                      ], $dismiss_url ),
      'classes' => [ 'reacg-notice' . $notice_id ],
      'button' => [
				'text' => __( "Yes, I’d love to!", "regallery" ),
        'classes' => [ 'button-primary', 'button' ],
        'redirect_url' => REACG_WP_PLUGIN_REVIEW_URL,
				'action_url' => add_query_arg( [
                                  'status' => 'reviewed',
                                ], $dismiss_url ),
        'spinner' => TRUE,
			],
			'button_secondary' => [
				'text' => __( "Maybe later", "regallery" ),
				'action_url' => add_query_arg( [
                                  'status' => !$second_period_not_passed ? 'dismissed' : 'skipped',
                                ], $dismiss_url ),
			],
		];

		$this->print_notice( $options );

		return TRUE;
	}

  /**
   * General notice container.
   *
   * @param $options
   *
   * @return void
   */
	private function print_notice( $options ) {
		?>
		<div class="notice reacg-keep-notice reacg-notice reacg-notice-info <?php echo esc_attr( !empty($options['classes']) ? implode(" ", $options['classes']) : "" ); ?>">
      <?php
      if ( !empty($options['dismiss_url']) ) {
        ?>
        <a data-action-url="<?php echo esc_url_raw( $options['dismiss_url'] ); ?>">
          <i class="reacg-notice-dismiss dashicons dashicons-no" role="button" aria-label="<?php esc_html__('Dismiss this notice.', 'regallery'); ?>" tabindex="0"></i>
        </a>
        <?php
      }
      ?>
      <div class="reacg-notice-icon-wrapper">
        <i class="reacg-notice-icon"></i>
			</div>
			<div class="reacg-notice-content">
				<?php if ( !empty($options['title']) ) { ?>
					<h3 class="reacg-notice-content-title"><?php echo wp_kses_post( $options['title'] ); ?></h3>
				<?php } ?>
				<?php if ( !empty($options['description']) ) { ?>
					<p><?php echo wp_kses_post( $options['description'] ); ?></p>
				<?php } ?>

				<?php if ( ! empty( $options['button']['text'] ) || ! empty( $options['button_secondary']['text'] ) ) { ?>
					<div class="reacg-notice-actions">
						<?php
						foreach ( [ $options['button'], $options['button_secondary'] ] as $button_settings ) {
              if ( !empty($button_settings['spinner']) ) {
                ?>
                <span class="spinner"></span>
                <?php
              }
							?>
              <a data-redirect-url="<?php echo !empty($button_settings['redirect_url']) ? esc_url_raw( $button_settings['redirect_url'] ) : ""; ?>"
                 data-action-url="<?php echo !empty($button_settings['action_url']) ? esc_url_raw( $button_settings['action_url'] ) : ""; ?>"
                 class="<?php echo esc_attr( !empty($button_settings['classes']) ? implode(" ", $button_settings['classes']) : "" ); ?>">
                <?php echo wp_kses_post( $button_settings['text'] ); ?>
              </a>
              <?php
						} ?>
					</div>
				<?php } ?>
			</div>
		</div>
	  <?php
  }

  /**
   * Admin notices on specific pages.
   *
   * @return void
   */
	public function admin_notices() {
    global $pagenow;
    if ( in_array( $pagenow, $this->excluded_pages )
      || !current_user_can( 'manage_options' ) ) {
      return;
    }

		$this->installed_time = REACGLibrary::installed_time();

		foreach ( $this->notices as $notice ) {
			if ( $this->$notice() ) {
        $this->enqueue_scripts();
				return;
			}
		}
	}

  /**
   * Change notice status.
   *
   * @return void
   */
  public function dismiss_notice() {
    if ( !empty( $_GET['notice_id'] )
      && !empty( $_GET[ REACG_NONCE ] )
      && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET[ REACG_NONCE ]))) ) {
      $notice_id = sanitize_key( wp_unslash( $_GET['notice_id'] ) );
      if ( ! in_array( $notice_id, $this->notices ) ) {
        return;
      }
      if ( !empty( $_GET['status'] ) ) {
        $status = sanitize_key( wp_unslash( $_GET['status'] ) );
        if ( !in_array( $status, $this->allowed_statuses ) ) {
          return;
        }
        update_option('reacg_notice_' . $notice_id . '_status', $status);
      }
    }
  }

  /**
   * Enqueue scripts.
   *
   * @return void
   */
  public function enqueue_scripts() {
    wp_enqueue_style(REACG_PREFIX . '_notice', REACG_PLUGIN_URL . '/assets/css/notice.css', [], REACG_VERSION);
    wp_enqueue_script(REACG_PREFIX . '_notice', REACG_PLUGIN_URL . '/assets/js/notice.js', ['jquery'], REACG_VERSION, TRUE);
  }

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ], 20 );
    add_action( 'wp_ajax_' . $this->ajax_action_dissmiss, [ $this, 'dismiss_notice' ] );
	}
}
