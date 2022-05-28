<?php
namespace Frontend_WP\Widgets;

use Frontend_WP\Widgets\ACF_Elementor_Form_Base;


	
/**

 *
 * @since 1.0.0
 */
class Duplicate_Post_Widget extends ACF_Frontend_Form_Widget {
	
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
		return 'duplicate_post';
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
			'custom_fields_save' => 'post',
				'save_to_post' => 'duplicate_post',
				'form_title' => __( 'Duplicate Post', FEA_NS ),
				'submit' => __( 'Publish', FEA_NS ),
				'success_message' => __( 'Your post has been duplicated successfully.', FEA_NS ),
				'field_type' => 'title',
			);
			foreach ( acf_frontend_get_field_type_groups( 'post' ) as $name => $group ) {
				$i = 0;
				foreach( $group['options'] as $type => $label ){
					if( $type == 'post_type' ) continue;
					$defaults['fields'][] = array(
						'field_type' => $type,
						'field_label' => $label,
					); 
					$i++;
					if( $i == 7 ) break;
				}		
			}
			return $defaults; 
	}

	/**
	 * Get widget keywords.
	 *
	 * Retrieve the list of keywords the widget belongs to.
	 *
	 * @since 2.1.0
	 * @access public
	 *
	 * @return array Widget keywords.
	 */
	public function get_keywords() {
		return ['frontend duplicate post', 'duplicate post', 'duplicate', 'clone', 'copy post'];
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
		return __( 'Post Duplicate Form', FEA_NS );
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
		return 'fas fa-clone frontend-icon';
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
		return array('frontend-admin-posts');
	}

}
