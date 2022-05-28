<?php
/**
 * Plugin Name:	Table Addons for Elementor
 * Description: Table extension for elementor page builder
 * Plugin URI:  https://iqbalbary.com/table-addons-for-elementor/
 * Version:     1.2.0
 * Author:      Iqbal Bary
 * Author URI:  https://iqbalbary.com/ 
 * License:		GPL-2.0+
 * License URI:	http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:	table-addons-for-elementor
 * Domain Path:	/languages
 */


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'TABLE_ADDONS_FOR_ELEMENTOR_VERSION', '1.0.1' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-table-addons-for-elementor-activator.php
 */
function activate_table_addons_for_elementor() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-table-addons-for-elementor-activator.php';
	Table_Addons_For_Elementor_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-table-addons-for-elementor-deactivator.php
 */
function deactivate_table_addons_for_elementor() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-table-addons-for-elementor-deactivator.php';
	Table_Addons_For_Elementor_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_table_addons_for_elementor' );
register_deactivation_hook( __FILE__, 'deactivate_table_addons_for_elementor' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-table-addons-for-elementor.php';

/**
 * Begins execution of the plugin.
 * @since    1.0.0
 */
function run_table_addons_for_elementor() {

	$plugin = new Table_Addons_For_Elementor();
	$plugin->run();

}
run_table_addons_for_elementor();
