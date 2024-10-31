<?php
/**
 * Plugin Name:       Retrigger Notifications Gravity Forms
 * Plugin URI:        
 * Description:       Plugin allows to manually re-send data of a Gravity Forms entry to external API in case the data has not been sent correctly.
 * Version:           1.2
 * Author:            WPSpins
 * Author URI:        https://wpspins.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gf-retrigger
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'GF_RETRIGGER_VERSION', '1.2' );

/**
 * Activation plugin hook
 */
function activate_gf_retrigger_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-gf-retrigger-activator.php';
	GF_Retrigger_Activator::activate();
}

/**
 * Deactivation plugin hook
 */
function deactivate_gf_retrigger_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-gf-retrigger-deactivator.php';
	GF_Retrigger_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_gf_retrigger_plugin' );
register_deactivation_hook( __FILE__, 'deactivate_gf_retrigger_plugin' );

/**
 * Include core plugin
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-gf-retrigger.php';


function gf_retrigger_plugin_init() {
	$gf_retrigger = new GF_Retrigger();
	$gf_retrigger->run();
}

gf_retrigger_plugin_init();
