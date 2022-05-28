<?php
namespace Frontend_WP\Classes;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class ModalWindow{

	public function get_icon( $icon, $attributes = [], $tag = 'i' ){
		if ( empty( $icon['library'] ) ) {
			return false;
		}
		$output = '';
		// handler SVG Icon
		if ( 'svg' === $icon['library'] ) {
			$output = \Elementor\Icons_Manager::render_svg_icon( $icon['value'] );
		} else {
			$output = $this->render_icon_html( $icon, $attributes, $tag );
		}

		return $output . ' ';
	}

	public function render_icon_html( $icon, $attributes = [], $tag = 'i' ) {
		$icon_types = \Elementor\Icons_Manager::get_icon_manager_tabs();
		if ( isset( $icon_types[ $icon['library'] ]['render_callback'] ) && is_callable( $icon_types[ $icon['library'] ]['render_callback'] ) ) {
			return call_user_func_array( $icon_types[ $icon['library'] ]['render_callback'], [ $icon, $attributes, $tag ] );
		}

		if ( empty( $attributes['class'] ) ) {
			$attributes['class'] = $icon['value'];
		} else {
			if ( is_array( $attributes['class'] ) ) {
				$attributes['class'][] = $icon['value'];
			} else {
				$attributes['class'] .= ' ' . $icon['value'];
			}
		}
		return '<' . $tag . ' ' . \Elementor\Utils::render_html_attributes( $attributes ) . '></' . $tag . '>';
	}

	public function modal_preview( $content, $element ){
		if (!$content)
		return '';

		//$id_item = $element->get_id();
		$content = '<# if ( settings.show_in_modal ) {
			var iconHTML = elementor.helpers.renderIcon( view, settings.modal_button_icon, {}, "i" , "object" );
			#><button class="modal-button edit-button" onClick="openModal(\'{{id}}' .get_the_ID(). '\')" >
			<# if ( iconHTML && iconHTML.rendered ) { #>
				<span class="elementor-accordion-icon-closed">{{{ iconHTML.value }}}</span>
			<# } #>
			{{ settings.modal_button_text }}</button>
			<div id="modal_{{id}}' .get_the_ID(). '" class="fea-modal edit-modal">
				<div class="fea-modal-content"> 
					<div class="fea-modal-inner"> 
					<span onClick="closeModal(\'{{id}}' .get_the_ID(). '\')" class="acf-icon -cancel close"></span>
						<div class="content-container">' . $content . '</div>
					</div>
				</div>
			</div><# } 
		else { #>' . $content . '<# } #>';
		return $content;
	}
	public function modal_render( $template, $element ){
		$wg_id = $element->get_id();
		$settings = $element->get_settings_for_display();

		if( ! isset( $settings['show_in_modal'] ) || ! $settings['show_in_modal'] ){
			return $template;
		}else{
			$before = $this->before_element_render( $settings, $wg_id );
			$after = $this->after_element_render( $settings, $wg_id );
			return $before.$template.$after;
		}   
	}
	public function before_element_render( $settings, $wg_id  ){
		if( ! isset( $settings['show_in_modal'] ) || ! $settings['show_in_modal'] ){
			return;
		}else{
			global $hide_modal;
			if( ! $hide_modal ){
				 echo '<style>
					.modal{display:none}.show{display:block}
				</style>'; 
				wp_enqueue_style( 'fea-modal' );	
				wp_enqueue_style( 'acf-global' );	
				wp_enqueue_script( 'fea-modal' ); 
				$hide_modal = true;
			}
			$show_modal = 'hide';
			//to do: make modal show on page reload
			/* if( isset( $_GET['modal'] ) ){
				if( isset( $_GET['updated'] ) && $_GET['updated'] != 'true' ){
					$modal_instance = explode( '_', $_GET['updated'] );
					if( is_array( $modal_instance ) && count( $modal_instance ) > 1 && $modal_instance[0] == $wg_id && $modal_instance[1] == get_the_ID() ){
						$show_modal = 'show';
					}
				}			
			} */

			$modal_num = acf_frontend_get_random_string();
            
			$before = '<div class="modal-button-container"><button class="modal-button open-modal" data-modal="' .$modal_num. '" >'; 
			if( $settings['modal_button_icon']['value'] ){
				$before .= $this->get_icon( $settings['modal_button_icon'], ['aria-hidden' => 'true'] );
			}
			$before .= $settings['modal_button_text']. '</button></div>';
			
			$before .= '<div id="modal_' .$modal_num. '" class="fea-modal edit-modal">
					<div class="fea-modal-content"> 
						<div class="fea-modal-inner"> 
						<span data-modal="' .$modal_num. '" class="acf-icon -cancel close-modal"></span>
							<div class="content-container">';

			return $before;
					
		} 
	}
	public function after_element_render( $settings, $wg_id  ){
		if( ! isset( $settings['show_in_modal'] ) || ! $settings['show_in_modal'] ){
			return;
		}
		$after = '</div>
			</div>
		</div>
		</div>';

		return $after;
	}
		
	public function modal_controls( $element, $args ) { 
		$element->start_controls_section(
			'modal_section',
			[
				'label' => __( 'Modal Window', FEA_NS ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

/* 		$element->add_control(
			'modal_feature_notice',
			[
				'show_label' => false,
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw' => sprintf( '<h2>%s</h2></br><p>%s</p>', __( FEA_TITLE.' Feature', FEA_NS ), __( 'Please note: this feature will moved to a dedicated plugin on April 20th, 2022. We will add a download link soon.', FEA_NS ) ),
			]
		); */
		
		$element->add_control(
			'show_in_modal',
			[
				'label' => __( 'Show in Modal', FEA_NS ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __( 'Yes', FEA_NS ),
				'label_off' => __( 'No',FEA_NS ),
				'return_value' => 'true',
			]
		);
			
		$default_text = __( 'Open Modal', FEA_NS );

		$element->add_control(
			'modal_button_text',
			[
				'label' => __( 'Modal Button Text', FEA_NS ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => $default_text,
				'placeholder' => $default_text,
				'condition' => [
					'show_in_modal' => 'true',
				],
				'dynamic' => [
					'active' => true,
				],		
			]
		);		
		$element->add_control(
			'modal_button_icon',
			[
				'label' => __( 'Modal Button Icon', FEA_NS ),
				'type' => \Elementor\Controls_Manager::ICONS,
				'condition' => [
					'show_in_modal' => 'true',
				],
			]
		);
				
		$element->end_controls_section();
		
		//Modal Button Style
		$element->start_controls_section(
			'style_modal_button_section',
			[
				'label' => __( 'Modal Button', 'acf-frontend-form-elements' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_in_modal' => 'true',
				],
			]
		);
        
        $element->add_control(
			'style_modal_button_spacing',
			[
				'label' => __( 'Spacing', 'elementor-pro' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'default' => [
					'size' => 0,
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 60,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .modal-button-container' => 
                        'padding-top: {{SIZE}}{{UNIT}};',
				],
			]
		);
				
        $element->add_responsive_control(
			'modal_button_align',
			[
				'label' => __( 'Horizontal Align', 'elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'center',
				'options' => [
					'flex-start' => __( 'Start', 'elementor' ),
					'center' => __( 'Center', 'elementor' ),
					'flex-end' => __( 'End', 'elementor' ),
				],
				'selectors' => [
					'{{WRAPPER}} .modal-button-container' => 
                        'display: flex;
                        justify-content: {{VALUE}}',
				],
			]
		);	
        
		$element->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'modal_button_typography',
				'label' => __( 'Typography', 'elementor' ),
				'selector' => '{{WRAPPER}} .modal-button',
			]
		);
		
		$element->add_group_control(
			\Elementor\Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'modal_button_text_shadow',
				'selector' => '{{WRAPPER}} .modal-button',
			]
		);
        
        $element->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'modal_button_box_shadow',
				'selector' => '{{WRAPPER}} .modal-button',
			]
		);
		
		$element->add_responsive_control(
			'modal_button_text_padding',
			[
				'label' => __( 'Padding', 'elementor-pro' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%', 'em'],
				'selectors' => [
					'{{WRAPPER}} .modal-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);	
        
        $element->add_responsive_control(
			'modal_button_text_margin',
			[
				'label' => __( 'Margin', 'elementor-pro' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%', 'em'],
				'selectors' => [
					'{{WRAPPER}} .modal-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
				
		$element->add_control(
			'modal_button_border_radius',
			[
				'label' => __( 'Border Radius', 'elementor-pro' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%'],
				'default' => [
					'top' => 'o',
					'bottom' => 'o',
					'left' => 'o',
					'right' => 'o',
					'isLinked' => 'true',
				],
				'selectors' => [
					'{{WRAPPER}} .modal-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
        
        $element->start_controls_tabs( 'tabs_modal_button_style' );
		
		// Start Normal tab
		$element->start_controls_tab(
			'tab_modal_button_normal',
			[
				'label' => __( 'Normal', 'elementor-pro' ),
			]
		);

		$element->add_control(
			'modal_button_text_color',
			[
				'label' => __( 'Text Color', 'elementor' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .modal-button' => 'fill: {{VALUE}}; color: {{VALUE}};',
				],
			]
		);

		$element->add_control(
			'modal_button_background_color',
			[
				'label' => __( 'Background Color', 'elementor' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .modal-button' => 'background-color: {{VALUE}};',
				],
			]
		);

		$element->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'modal_button_border',
				'label' => __( 'Border', 'elementor' ),
				'selector' => '{{WRAPPER}} .modal-button',
			]
		);

		$element->end_controls_tab(); 
		// End Normal tab
		
		// Start on hover tab
		$element->start_controls_tab(
			'tab_modal_button_hover',
			[
				'label' => __( 'Hover', 'elementor-pro' ),
			]
		);

		$element->add_control(
			'modal_button_hover_text_color',
			[
				'label' => __( 'Text Color', 'elementor' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .modal-button:hover' => 'fill: {{VALUE}}; color: {{VALUE}};',
				],
			]
		);

		$element->add_control(
			'modal_button_hover_background_color',
			 [
			   'label' => __( 'Background Color', 'elementor' ),
			   'type' => \Elementor\Controls_Manager::COLOR,
			   'description' => 'To add a different border for On Hover change this 
					setting. To make the border disappear completely on hover make 
					the width zero.',
			   'selectors' => [
				   '{{WRAPPER}} .modal-button:hover' => 'background-color: {{VALUE}};',
			   ],
			 ]
		 );
		 
		$element->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'modal_button_hover_border',
				'label' => __( 'Border', 'elementor' ),
				'selector' => '{{WRAPPER}} .modal-button:hover',
			]
		);
		
		// End on hover tab
		$element->end_controls_tab();
        
		// End Normal vs Hover tab group
		$element->end_controls_tabs(); 
        
        // End modal button styles
		$element->end_controls_section();	
		
		// Modal Window Styles
		$element->start_controls_section(
			'style_modal_section',
			[
				'label' => __( 'Modal Window', 'acf-frontend-form-elements' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_in_modal' => 'true',
				],
			]
		);		
		
		$element->add_control(
			'modal_window_background_color',
			[
				'label' => __( 'Background Color', 'elementor' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .edit-modal .fea-modal-content' => 'background-color: {{VALUE}};',
				],
			]
		);
			
		$element->add_responsive_control(
			'modal_window_size',
			[
				'label' => __( 'Modal Width', 'elementor' ) . ' (%)',
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 20,
				'max' => 100,
				'required' => true,
				'device_args' => [
					\Elementor\Controls_Stack::RESPONSIVE_TABLET => [
						'max' => 100,
						'required' => false,
					],
					\Elementor\Controls_Stack::RESPONSIVE_MOBILE => [
						'max' => 100,
						'required' => false,
					],
				],
				'min_affected_device' => [
					\Elementor\Controls_Stack::RESPONSIVE_DESKTOP => \Elementor\Controls_Stack::RESPONSIVE_TABLET,
					\Elementor\Controls_Stack::RESPONSIVE_TABLET => \Elementor\Controls_Stack::RESPONSIVE_TABLET,
				],
				'selectors' => [
					'{{WRAPPER}} .edit-modal .fea-modal-content' => 'width: {{VALUE}}%',
				],
			]
		);
		
		$element->add_responsive_control(
			'modal_content_size',
			[
				'label' => __( 'Modal Content', 'elementor' ) . ' (%)',
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 20,
				'max' => 100,
				'required' => true,
				'device_args' => [
					\Elementor\Controls_Stack::RESPONSIVE_TABLET => [
						'max' => 100,
						'required' => false,
					],
					\Elementor\Controls_Stack::RESPONSIVE_MOBILE => [
						'max' => 100,
						'required' => false,
					],
				],
				'min_affected_device' => [
					\Elementor\Controls_Stack::RESPONSIVE_DESKTOP => \Elementor\Controls_Stack::RESPONSIVE_TABLET,
					\Elementor\Controls_Stack::RESPONSIVE_TABLET => \Elementor\Controls_Stack::RESPONSIVE_TABLET,
				],
				'selectors' => [
					'{{WRAPPER}} .edit-modal .fea-modal-content .fea-modal-inner' => 'width: {{VALUE}}%',
				],
			]
		);
		
		$element->add_responsive_control(
			'modal_inner_align',
			[
				'label' => __( 'Horizontal Align', 'elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'center',
				'options' => [
					'flex-start' => __( 'Start', 'elementor' ),
					'center' => __( 'Center', 'elementor' ),
					'flex-end' => __( 'End', 'elementor' ),
				],
				'selectors' => [
					'{{WRAPPER}} .edit-modal .fea-modal-content' => 'justify-content: {{VALUE}}',
				],
			]
		);

		$element->end_controls_section();	
		
	}

	public function __construct() {
		add_action( 'elementor/element/common/_section_style/after_section_end', array( $this, 'modal_controls' ), 10, 2 );
		add_action( 'elementor/widget/print_template', array( $this, 'modal_preview' ), 20, 2 );
		add_action( 'elementor/widget/render_content', array( $this, 'modal_render' ), 10, 2 );	
	}

}

new ModalWindow();

