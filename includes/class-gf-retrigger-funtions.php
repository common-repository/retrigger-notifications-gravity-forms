<?php

class GF_Retrigger_Funtions {

	public function __construct() {
		add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'add_resend_feeds_meta_box' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'gf_donate_notice' ) );
		add_filter( 'gform_entry_list_bulk_actions', array( $this, 'add_actions' ), 10, 2 );
		add_action( 'gform_entry_list_action', array( $this, 'resend_zapier_action' ), 10, 3 );
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		add_action( 'rest_api_init', array( $this, 'gf_test_webhook_api' ) );
	}
	public function add_modal(){
		echo '<div id="resend_zapier_feeds">';
		$form_id = rgget( 'id' );
		$feeds   = self::get_zapier_feeds( $form_id );
		if ( empty( $feeds ) ) {
			echo sprintf( __( 'There are no zaps configured for form id %s', 'gf-retrigger' ), $form_id );
		} else {
			foreach ( $feeds as $k => $feed ) {
				?>
                <input type="checkbox" class="gform_notifications"
                       name="zapier_hooks[]"
                       value="<?php echo esc_attr( $feed['id'] ); ?>"
                       id="zapier_<?php echo esc_attr( $feed['id'] ); ?>"/>
				<?php if ( ! empty( $feed['name'] ) ) { ?>
                    <label for="zapier_<?php echo esc_attr( $feed['id'] ); ?>"><?php echo esc_html( $feed['name'] ); ?></label>
				<?php } elseif ( ! empty( $feed['meta']['feedName'] ) ) { ?>
                    <label for="zapier_<?php echo esc_attr( $feed['id'] ); ?>"><?php echo esc_html( $feed['meta']['feedName'] ); ?></label>
				<?php } ?>
                <br/><br/>
				<?php
			}
		}
		echo '</div>';
	}
	static public function check_zapier_plugin() {
		if ( method_exists( 'GFZapier', 'process_feed' ) ) {    // for version <4.0
			return true;
		} elseif ( method_exists( 'GF_Zapier', 'process_feed' ) ) {    // for version >=4.0
			return true;
		}

		return false;
	}

	static public function get_zapier_feeds( $form_id ) {
		if ( method_exists( 'GFZapierData', 'get_feed_by_form' ) ) {    // for version <4.0
			return GFZapierData::get_feed_by_form( $form_id, true );
		} elseif ( class_exists( 'GF_Zapier' ) ) {    // for version >=4.0
			$obj_webhook = GF_Zapier::get_instance();

			return $obj_webhook->get_feeds( $form_id );
		}

		return false;
	}

	static public function process_zapier_feed( $feed, $entry, $form ) {
		if ( method_exists( 'GFZapier', 'process_feed' ) ) {    // for version <4.0
			return GFZapier::process_feed( $feed, $entry, $form );
		} elseif ( method_exists( 'GF_Zapier', 'process_feed' ) ) {    // for version >=4.0
			$obj_webhook = GF_Zapier::get_instance();

			return $obj_webhook->process_feed( $feed, $entry, $form );
		}

		return false;
	}

	public function add_resend_feeds_meta_box( $meta_boxes, $entry, $form ) {
		if ( ! isset( $meta_boxes['resend_zapier_feed'] ) && self::check_zapier_plugin() ) {
			$meta_boxes['resend_zapier_feed'] = array(
				'title'         => esc_html__( 'Resend Zapier Feeds', 'gf-retrigger' ),
				'callback'      => array( 'GF_Retrigger_Funtions', 'meta_box_resend_zapier' ),
				'context'       => 'side',
				'callback_args' => array( $entry, $form ),
			);
		}

		if ( ! isset( $meta_boxes['resend_webhook_feed'] ) && class_exists( 'GF_Webhooks' ) ) {
			$meta_boxes['resend_webhook_feed'] = array(
				'title'         => esc_html__( 'Resend Webhook Feeds', 'gf-retrigger' ),
				'callback'      => array( 'GF_Retrigger_Funtions', 'meta_box_resend_webhook' ),
				'context'       => 'side',
				'callback_args' => array( $entry, $form ),
			);
		}

		return $meta_boxes;
	}
	/**
	 * Add Resend Zapier Feeds in bulk
	 *
	 * @param  array $actions Actions.
	 * @param  int   $form_id Form ID.
	 * @return array
	 */
	public function add_actions( $actions, $form_id ) {
		$actions['resend_zapier']  = esc_html__( 'Resend Zapier Feeds', 'gf-retrigger' );
		$actions['resend_webhook'] = esc_html__( 'Resend Webhook Feeds', 'gf-retrigger' );
		return $actions;
	}
	/**
	 * Add JS to admin footer.
	 *
	 * @return void
	 */
	public function admin_footer() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#doaction').click(function() {
					// this sibling select.
					var action = $(this).siblings('select').val();
					if (action == 'resend_zapier' | action == 'resend_webhook' ) {
						// confirm.
						var confirm = window.confirm('<?php esc_html_e( 'These entries will be resent to all feeds configured, Are you sure you want to resend the feeds?', 'gf-retrigger' ); ?>');
						if (confirm) {
							return true;
						}
						return false;
					}
				});
			});
		</script>
		<?php
	}
	/**
	 * Resend Zapier Feeds in bulk.
	 *
	 * @param  mixed $action Action name.
	 * @return void
	 */
	public function resend_zapier_action( $action, $entry_ids, $form_id ) {
		if ( 'resend_zapier' === $action ) {
			foreach ( $entry_ids as $entry_id ) {
				$entry = GFAPI::get_entry( $entry_id );
				$feeds = self::get_zapier_feeds( $form_id );
				if ( ! empty( $feeds ) ) {
					$form = GFAPI::get_form( $form_id );
					foreach ( $feeds as $feed ) {
						$status = self::process_zapier_feed( $feed, $entry, $form );
						if ( is_wp_error( $status ) ) {
							GFCommon::log_debug( __METHOD__ . '(): Entry ID = ' . $entry_id . 'ERROR MESSAGE ' . $status->get_error_message() );
							$message = esc_html__( 'Zapier feeds have not been resent successfully  for entry ID ' . $entry_id, 'gf-retrigger' );
							echo '<div id="message" class="alert error"><p>' . $message . '</p></div>';
						} else {
							// send entry id to log.
							GFCommon::log_debug( __METHOD__ . '(): Entry ID = ' . $entry_id );
							$message = esc_html__( 'Zapier feeds have been resent successfully for entry ID ' . $entry_id, 'gf-retrigger' );
							echo '<div id="message" class="alert success"><p>' . $message . '</p></div>';
						}
					}
				}
			}
		} elseif ( 'resend_webhook' === $action ) {
			if ( ! class_exists( 'GF_Webhooks' ) ) {
				GFCommon::log_debug( __METHOD__ . '(): Webhook plugin not found' );
				$message = esc_html__( 'Webhook plugin not found.', 'gf-retrigger' );
				echo '<div id="message" class="alert error"><p>' . $message . '</p></div>';
				return;
			}
			$obj_webhook = GF_Webhooks::get_instance();
			foreach ( $entry_ids as $entry_id ) {
				$entry = GFAPI::get_entry( $entry_id );
				$feeds = $obj_webhook->get_feeds( $form_id );
				if ( ! empty( $feeds ) ) {
					$form = GFAPI::get_form( $form_id );
					foreach ( $feeds as $feed ) {
						$status = $obj_webhook->process_feed( $feed, $entry, $form );
						if ( is_wp_error( $status ) ) {
							GFCommon::log_debug( __METHOD__ . '(): Entry ID = ' . $entry_id . 'ERROR MESSAGE ' . $status->get_error_message() );
							$message = esc_html__( 'Webhook feeds have not been resent successfully for entry ID ' . $entry_id, 'gf-retrigger' );
							echo '<div id="message" class="alert error"><p>' . $message . '</p></div>';
						} else {
							// send entry id to log.
							GFCommon::log_debug( __METHOD__ . '(): Entry ID = ' . $entry_id );
							$message = esc_html__( 'Webhook feeds have been resent successfully for entry ID ' . $entry_id, 'gf-retrigger' );
							echo '<div id="message" class="alert success"><p>' . $message . '</p></div>';
						}
					}
				}
			}
		}
	}
	/**
	 * Resend Zapier Feeds in bulk.
	 *
	 * @param  mixed $args Arguments.
	 * @return void
	 */
	static public function meta_box_resend_zapier( $args ) {
		GFCommon::log_debug( __METHOD__ . '(): Starting.' );
		$form  = $args['form'];
		$entry = $args['entry'];

		$form_id = $form['id'];
		$feeds   = self::get_zapier_feeds( $form_id );

		if ( empty( $feeds ) ) {
			echo sprintf( __( 'There are no zaps configured for form id %s', 'gf-retrigger' ), $form_id );
			GFCommon::log_debug( __METHOD__ . '(): No feeds found.' );
		} else {
			foreach ( $feeds as $k => $feed ) {
				?>
                <input type="checkbox" class="gform_notifications"
                       name="zapier_hooks[]"
                       value="<?php echo esc_attr( $feed['id'] ); ?>"
                       id="zapier_<?php echo esc_attr( $feed['id'] ); ?>"/>
				<?php if ( ! empty( $feed['name'] ) ) { ?>
                    <label for="zapier_<?php echo esc_attr( $feed['id'] ); ?>"><?php echo esc_html( $feed['name'] ); ?></label>
				<?php } elseif ( ! empty( $feed['meta']['feedName'] ) ) { ?>
                    <label for="zapier_<?php echo esc_attr( $feed['id'] ); ?>"><?php echo esc_html( $feed['meta']['feedName'] ); ?></label>
				<?php } ?>
                <br/><br/>
				<?php
			}

			$action         = 'gf_resend_data_to_zapier';
			$sent_to_zapier = false;

			if ( ! $sent_to_zapier && rgpost( 'action' ) == $action && self::check_zapier_plugin() ) {

				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

				$zapier_hooks = rgpost( 'zapier_hooks' );

				if ( empty( $zapier_hooks ) ) {
					echo __( 'You must select at least one type of notification to resend.', 'gf-retrigger' ) . '</br></br>';
				} else {

					foreach ( $feeds as $feed ) {
						if ( in_array( $feed['id'], (array) $zapier_hooks ) ) {
							$sent_to_zapier = self::process_zapier_feed( $feed, $entry, $form );
							if ( is_wp_error( $sent_to_zapier ) ) {
								GFCommon::log_debug( __METHOD__ . '(): Entry ID = ' . $entry_id . 'ERROR MESSAGE ' . $sent_to_zapier->get_error_message() );
							} else {
								// send entry id to log.
								GFCommon::log_debug( __METHOD__ . '(): Entry ID = ' . $entry_id );
							}
						}
					}

					if ( $sent_to_zapier ) {
						echo __( 'Data has been sent to Zapier hook.', 'gf-retrigger' ) . '</br></br>';
					} else {
						echo __( 'Cannot send entries to Zapier feeds, please check the Zapier hook setting again.', 'gf-retrigger' ) . '</br></br>';
					}
				}
			}

			if ( ! $sent_to_zapier ) {
				printf( '<input type="submit" value="%s" class="button" onclick="jQuery(\'#action\').val(\'%s\');" />', __( 'Resend Zapier Feeds', 'gf-retrigger' ), $action );
				GFCommon::log_debug( __METHOD__ . '(): Resend Zapier Feeds button displayed.' );
			}
		}
	}
	/**
	 * Display the resend webhook checkbox
	 *
	 * @param  mixed $args Arguments.
	 * @return void
	 */
	static public function meta_box_resend_webhook( $args ) {
		$form    = $args['form'];
		$entry   = $args['entry'];
		$form_id = $form['id'];

		$obj_webhook = GF_Webhooks::get_instance();
		$feeds       = $obj_webhook->get_feeds( $form_id );

		if ( empty( $feeds ) ) {
			echo sprintf( __( 'There are no webhook configured for form id %s', 'gf-retrigger' ), $form_id );
			GFCommon::log_debug( __METHOD__ . '(): No feeds found.' );
		} else {

			foreach ( $feeds as $k => $feed ) {
				?>
                <input type="checkbox" class="gform_notifications"
                       name="webhook_hooks[]"
                       value="<?php echo esc_attr( $feed['id'] ); ?>"
                       id="webhook_<?php echo esc_attr( $feed['id'] ); ?>"/>
                <label for="webhook_<?php echo esc_attr( $feed['id'] ); ?>"><?php echo esc_html( $feed['meta']['feedName'] ); ?></label>
                <br/><br/>
				<?php
			}

			$action          = 'gf_resend_data_to_webhook';
			$sent_to_webhook = false;

			if ( ! $sent_to_webhook && rgpost( 'action' ) == $action && method_exists( 'GF_Webhooks', 'process_feed' ) ) {

				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

				$webhook_hooks = rgpost( 'webhook_hooks' );

				if ( empty( $webhook_hooks ) ) {
					echo __( 'You must select at least one type of notification to resend.', 'gf-retrigger' ) . '</br></br>';
					GFCommon::log_debug( __METHOD__ . '(): You must select at least one type of notification to resend.' );
				} else {

					foreach ( $feeds as $feed ) {
						if ( in_array( $feed['id'], (array) $webhook_hooks ) ) {
							$sent_to_webhook = $obj_webhook->process_feed( $feed, $entry, $form );
						}
					}

					if ( $sent_to_webhook ) {
						echo __( 'Data has been sent to Webhooks.', 'gf-retrigger' ) . '</br></br>';
						GFCommon::log_debug( __METHOD__ . '(): Data has been sent to Webhooks.' );
					} else {
						echo __( 'Cannot send entries to Webhook feeds, please check the Webhook setting again.', 'gf-retrigger' ) . '</br></br>';
						GFCommon::log_debug( __METHOD__ . '(): Cannot send entries to Webhook feeds, please check the Webhook setting again.' . print_r( $sent_to_webhook, true ) );
					}
				}

			}

			if ( ! $sent_to_webhook ) {
				printf( '<input type="submit" value="%s" class="button" onclick="jQuery(\'#action\').val(\'%s\');" />', __( 'Resend Webhook Feeds', 'gf-retrigger' ), $action );
				GFCommon::log_debug( __METHOD__ . '(): Resend Webhook Feeds button displayed.' );
			}
		}
	}
	/**
	 * Display admin notice
	 *
	 * @return void
	 */
	public function gf_donate_notice() {
		$gf_admin_pages = array( 'gf_edit_forms', 'gf_new_form', 'gf_entries', 'gf_settings', 'gf_export', 'gf_addons', 'gf_system_status', 'gf_help'  );
		if ( isset( $_GET['page'] ) && in_array(  $_GET['page'], $gf_admin_pages, true ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
				<?php esc_html_e( 'Thanks for installing Retrigger Notifications Gravity Forms. You can support my work by donating.', 'gf-retrigger' ); ?>
				<a target="__blank" href="https://wpspins.com/support-our-work/"><?php esc_html_e( 'Click this banner to see donation form', 'gf-retrigger' ); ?></a>
				</p>
			</div>
			<?php
		}
	}
	/**
	 * Test REST API domain.com/wp-json/gf/v1/test-webhook-api
	 *
	 * @return void
	 */
	public function gf_test_webhook_api() {
		// register test webhook api rest route.
		register_rest_route(
			'gf/v1',
			'/test-webhook-api',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'gf_test_webhook_api_callback' ),
			)
		);
	}
	/**
	 * Test webhook api callback.
	 *
	 * @param  mixed $request request.
	 * @return void
	 */
	public function gf_test_webhook_api_callback( $request ) {
		GFCommon::log_debug( __METHOD__ . '(): Through test webhook the request details : ' . print_r( $request, true ) );
	}

}
