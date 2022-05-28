<?php
namespace Frontend_WP\Widgets;

use Frontend_WP\Widgets\ACF_Elementor_Form_Base;


	
/**

 *
 * @since 1.0.0
 */
class Edit_Term_Widget extends ACF_Frontend_Form_Widget {
	
	/**
	 * Get widget name.
	 *
	 * Retrieve acf ele form widget name.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'edit_term';
	}

	/**
	* Get widget action.
	*
	* Retrieve acf ele form widget action.
	*
	* @since 1.0.0
	* @access public
	*
	* @return string Widget action.
	*/
	public function get_form_defaults() {
		$defaults = array( 
			'custom_fields_save' => 'term',
				'save_to_term' => 'edit_term',
				'form_title' => __( 'Edit Term', FEA_NS ),
				'submit' => __( 'Update', FEA_NS ),
				'success_message' => __( 'The term has been edited successfully.', FEA_NS ),
				'field_type' => 'term_name',
			);
			foreach ( acf_frontend_get_field_type_groups( 'term' ) as $name => $group ) {
				foreach( $group['options'] as $type => $label ){
					$defaults['fields'][] = array(
						'field_type' => $type,
						'field_label' => $label,
					); 
				}		
			}
			return $defaults; 
	}

	/**
	 * Get widget title.
	 *
	 * Retrieve acf ele form widget title.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return __( 'Edit Term Form', FEA_NS );
	}

	/**
	 * Get widget icon.
	 *
	 * Retrieve acf ele form widget icon.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'fas fa-underline frontend-icon';
	}

	/**
	 * Get widget categories.
	 *
	 * Retrieve the list of categories the acf ele form widget belongs to.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return array('frontend-admin-taxonomies');
	}

}
