<?php
namespace Frontend_WP\Documents;


use Elementor\Modules\Library\Documents\Library_Document;



if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Elementor section library document.
 *
 * Elementor section library document handler class is responsible for
 * handling a document of a section type.
 *
 * @since 2.0.0
 */
class PostFormTemplate extends Library_Document {

	public static function get_properties() {
		$properties = parent::get_properties();
/* 		$properties['admin_tab_group'] = '';
		$properties['show_in_finder'] = false;
		$properties['show_on_admin_bar'] = false; 
		$properties['show_in_library'] = false; */
		return $properties;
	}

	/**
	 * Get document name.
	 *
	 * Retrieve the document name.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @return string Document name.
	 */
	public function get_name() {
		return 'post_form';
	}

	/**
	 * Get document title.
	 *
	 * Retrieve the document title.
	 *
	 * @since 2.0.0
	 * @access public
	 * @static
	 *
	 * @return string Document title.
	 */
	public static function get_title() {
		return __( 'Form', FEA_NS );
	}


		/**
	 * @since 2.1.0
	 * @access public
	 * @static
	 */
	public static function get_editor_panel_config() {
		$panel_config = parent::get_editor_panel_config();

		$panel_config['elements_categories'] = array( 'frontend_admin_templates' => array(
			'title' => __( 'FORM FIELDS', FEA_NS ),
		) );

		return $panel_config;
	}


	
}
