<?php
namespace AdvancedPostQueries;

use Elementor\Plugin as EL;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Plugin {
		
		/**
	 * Instance
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @static
	 *
	 * @var ACF_Elementor_Form The single instance of the class.
	 */
	private static $_instance = null;

	/**
	 * Instance
	 *
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @static
	 *
	 * @return ACF_Elementor_Form An instance of the class.
	 */
	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;

	}
	
	/**
	 * @return \Elementor\Plugin
	 */

	public static function elementor() {
		return \Elementor\Plugin::$instance;
	}
	
	public static function get_current_post_id() {
		if ( isset( EL::$instance->documents ) ) {
			return EL::$instance->documents->get_current()->get_main_id();
		}

		return get_the_ID();
	}

	public function add_modules(){
		require_once ( __DIR__ . '/helpers.php' );		
		require_once ( __DIR__ . '/advanced-post-queries/module.php' );		
		//require_once ( __DIR__ . '/users-widget/module.php' );		
	}

	public function __construct() {
		$this->add_modules();	
	}	
	
}

Plugin::instance();