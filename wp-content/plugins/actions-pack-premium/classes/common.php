<?php

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Elementor_Forms_Common_Controls {

    public $allowed_pattern_fields = [
        'text',
        'password',
	    'number'
    ];

    public function __construct() {
        $this->register_assets();
        //add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'editor_enqueue' ] );
        add_action( 'elementor/element/form/section_form_fields/before_section_end', [ $this, 'add_form_field_controls' ], 100, 2 );
	    add_filter( 'elementor_pro/forms/render/item', [ $this, 'form_field_render' ], 99, 3 ); // Higher Priority
	    add_action( 'elementor/widget/print_template', [$this, 'modify_form_template_in_editor'], 10, 2 );
        add_action( 'elementor/element/form/section_steps_style/after_section_end', [ $this, 'add_custom_style_controls'], 10, 2);
        add_action( 'elementor/widget/render_content',[ $this, 'render_content' ], 10, 2);
    }

    public function register_assets(){
        wp_register_script( 'ap-intl-tel-input',  AP_PLUGIN_DIR_URL .'assets/lib/intl-tel-input/js/intlTelInput.js', [ 'jquery' ], false, true );
        wp_register_style( 'ap-intl-tel-input',  AP_PLUGIN_DIR_URL .'assets/lib/intl-tel-input/css/intlTelInput.css');
        wp_register_style( 'ap-common',  AP_PLUGIN_DIR_URL .'assets/css/common.css');
        wp_register_script( 'ap-common',  AP_PLUGIN_DIR_URL .'assets/js/common.js', [ 'jquery' ], false, true );
    }

    public function editor_enqueue(){
        $this->register_assets();
        wp_enqueue_style('ap-intl-tel-input');
        wp_enqueue_script('ap-intl-tel-input');
        wp_enqueue_style('ap-common');
        wp_enqueue_script('ap-common');
    }

    public function add_form_field_controls( $element, $args ) {
        $elementor = \Elementor\Plugin::instance();
        $control_data = $elementor->controls_manager->get_control_from_stack( $element->get_name(), 'form_fields' );

        if ( is_wp_error( $control_data ) ) {
            return;
        }

        $repeater = new Elementor\Repeater();

	    // Pattern Field
	    $repeater->add_control(
            'field_pattern',
            [
                'label' => __('Pattern', 'actions-pack'),
                'inner_tab' => 'form_fields_advanced_tab',
                'tab' => 'content',
                'tabs_wrapper' => 'form_fields_tabs',
                'type' => 'text',
                'conditions' => [
                    'terms' => [
                        [
                            'name' => 'field_type',
                            'operator' => 'in',
                            'value' => $this->allowed_pattern_fields,
                        ],
                    ],
                ],
            ]
        );
	    $repeater->add_control(
            'field_pattern_message',
            [
                'label' => __('Pattern Message','actions-pack'),
                'inner_tab' => 'form_fields_advanced_tab',
                'tab' => 'content',
                'tabs_wrapper' => 'form_fields_tabs',
                'type' => 'text',
                'default' => __('Please match the requested format','actions-pack'),
                'conditions' => [
                    'terms' => [
                        [
                            'name' => 'field_type',
                            'operator' => 'in',
                            'value' => $this->allowed_pattern_fields,
                        ],
                    ],
                ],
            ]
        );

        // Password Preview
	    $repeater->add_control(
		    'password_preview_toggle',
		    [
			    'label' => __('Preview Toggle', 'actions-pack'),
			    'inner_tab' => 'form_fields_advanced_tab',
			    'tab' => 'content',
			    'tabs_wrapper' => 'form_fields_tabs',
			    'type' => 'switcher',
			    'label_on' => __( 'Show', 'actions-pack' ),
			    'label_off' => __( 'Hide', 'actions-pack' ),
			    'return_value' => 'yes',
			    'conditions' => [
				    'terms' => [
					    [
						    'name' => 'field_type',
						    'operator' => '===',
						    'value' => 'password',
					    ],
				    ],
			    ],
		    ]
	    );

        // File Preview
        $repeater->add_control(
            'file_preview_toggle',
            [
                'label' => __('File Preview', 'actions-pack'),
                'inner_tab' => 'form_fields_advanced_tab',
                'tab' => 'content',
                'tabs_wrapper' => 'form_fields_tabs',
                'type' => 'switcher',
                'label_on' => __( 'Enable', 'actions-pack' ),
                'label_off' => __( 'Disable', 'actions-pack' ),
                'return_value' => 'yes',
                'conditions' => [
                    'terms' => [
                        [
                            'name' => 'field_type',
                            'operator' => '===',
                            'value' => 'upload',
                        ],
                    ],
                ],
                'description' => __('You can customize the preview image on style tab > Additional Style', 'actions-pack')
            ]
        );

        // Add Country Code
        $repeater->add_control(
            'ap_tel_country_code',
            [
                'label' => __('Country Code', 'actions-pack'),
                'inner_tab' => 'form_fields_advanced_tab',
                'tab' => 'content',
                'tabs_wrapper' => 'form_fields_tabs',
                'type' => 'switcher',
                'label_on' => __( 'Enable', 'actions-pack' ),
                'label_off' => __( 'Disable', 'actions-pack' ),
                'return_value' => 'yes',
                'conditions' => [
                    'terms' => [
                        [
                            'name' => 'field_type',
                            'operator' => '===',
                            'value' => 'tel',
                        ],
                    ],
                ],
                'description' => 'It adds country code extension with flags to telephone field. Works only on frontend.'
            ]
        );

	    // Insert above controls just before CUSTOM ID control
	    $controls = $repeater->get_controls();
	    $new_order = [];
        foreach ( $control_data['fields'] as $field_key => $field ) {
            if ( $field['name'] === 'custom_id' ) {
                $new_order['field_pattern'] = $controls['field_pattern'];
                $new_order['field_pattern_message'] = $controls['field_pattern_message'];
	            $new_order['password_preview_toggle'] = $controls['password_preview_toggle'];
                $new_order['file_preview_toggle'] = $controls['file_preview_toggle'];
                $new_order['ap_tel_country_code'] = $controls['ap_tel_country_code'];
            }
            $new_order[ $field_key ] = $field;
        }
        $control_data['fields'] = $new_order;
        $element->update_control( 'form_fields', $control_data );
    }

    /**
     * @param \ElementorPro\Modules\Forms\Widgets\Form $form_widget
     * @return array $field
     */
    public function form_field_render( $field, $field_index, $form_widget ) {

    	// Pattern
    	if ( ! empty( $field['field_pattern'] ) && in_array( $field['field_type'], $this->allowed_pattern_fields ) ) {
            $form_widget->add_render_attribute( 'input' . $field_index,
                [
                    'pattern' => $field['field_pattern'],
                    'oninvalid' => 'this.setCustomValidity("' . $field['field_pattern_message'] . '")',
                    'oninput' => 'this.setCustomValidity("")',
                ]
            );
        }

        // Password Preview Icon
        if( !empty($field['password_preview_toggle']) && $field['password_preview_toggle'] === 'yes' && $field['field_type'] === 'password' ){
            $print_label = ! in_array( $field['field_type'], [ 'hidden', 'html', 'step' ], true );
            $form_widget->add_render_attribute( 'input' . $field_index, 'class', 'elementor-field-textual' );

            ?>
            <div <?php echo $form_widget->get_render_attribute_string( 'field-group' . $field_index );?>>
                <?php if ( $print_label && $field['field_label'] ) : ?>
                    <label <?php echo $form_widget->get_render_attribute_string( 'label' . $field_index );?>><?php echo $field['field_label'];?></label>
                <?php endif; ?>

                <div style="display:flex;flex-direction: row;align-items: baseline;width: 100%;">
                    <input size="1" <?php echo $form_widget->get_render_attribute_string( 'input' . $field_index );?>>
                    <i class="far fa-eye " aria-hidden="true" style="margin-left:-34px;cursor:pointer;" onclick="this.classList.toggle('fa-eye-slash');t=this.previousElementSibling;t.type==='password'?t.type='text':t.type='password'"></i>
                </div>
            </div>
            <?php
            // Work-around to remove old html element
            $form_widget->remove_render_attribute( 'field-group' . $field_index);
            return ['field_type' => '', 'field_label' => ''];
        }

        // File Preview
        if( !empty($field['file_preview_toggle']) && $field['file_preview_toggle'] === 'yes' && $field['field_type'] === 'upload' ){
            $print_label = ! in_array( $field['field_type'], [ 'hidden', 'html', 'step' ], true );
            $placeholderImg = \Elementor\Utils::get_placeholder_image_src();

            ?>
            <div <?php echo $form_widget->get_render_attribute_string( 'field-group' . $field_index );?> style="display:flex;flex-direction:column;align-items:flex-start;">
                <?php if ( $print_label && $field['field_label'] ) : ?>
                    <label <?php echo $form_widget->get_render_attribute_string( 'label' . $field_index );?>>
                        <?php echo $field['field_label'];?>
                    </label>
                <?php endif; ?>
                <img onclick="this.parentNode.nextElementSibling.lastElementChild.click()" alt="preview" class="ap-file-preview" src="<?php echo $placeholderImg;?>"  onload="t=parentNode.nextElementSibling.lastElementChild.defaultValue;if(t){this.src=t;this.onload=null;}" style="cursor:pointer;"/>
                <div onclick="this.parentNode.nextElementSibling.lastElementChild.click()" style="cursor:pointer;position:absolute;bottom:52px;padding:6px;width:60px;height:30px;background-color:#0a0a0a;display:flex;color:white;font-weight:600;border-radius:5px;box-shadow:0px 0px 10px 0px rgba(0,0,0,0.5)"><i class="eicon-pencil"></i><span style="line-height:20px">&nbsp;<?php echo __('Edit', 'actions-pack');?></span></div>
            </div>
            <?php

            $form_widget->add_render_attribute( 'field-group' . $field_index, [
                'style' => 'display:none'
            ]);

            $form_widget->add_render_attribute( 'input' . $field_index, [
                'onchange' => 't=this.parentNode.previousElementSibling.lastElementChild.previousElementSibling;if(this.files.length){t.src=window.URL.createObjectURL(this.files[0])}else{t.src="'.$placeholderImg.'"}',
            ]);
        }

        // Country Code
        if( !empty($field['ap_tel_country_code']) && $field['ap_tel_country_code'] === 'yes' && $field['field_type'] === 'tel' ){
            wp_enqueue_style('ap-intl-tel-input');
            wp_enqueue_script('ap-intl-tel-input');
            wp_enqueue_style('ap-common');
            wp_enqueue_script('ap-common');
            $form_widget->add_render_attribute('input' . $field_index, 'class', 'ap_intl_tel_input');
        }

        return $field;
    }

    public function modify_form_template_in_editor( $template, $widget ){
	    if( $widget->get_name() === 'form' ){
	        // Password Preview
		    $old_template = '<input size="1" type="\' + item.field_type + \'" value="\' + item.field_value + \'" class="elementor-field elementor-size-\' + settings.input_size + \' \' + itemClasses + \'" name="form_field_\' + i + \'" id="form_field_\' + i + \'" \' + required + \' \' + placeholder + \' >';
		    $new_template = '<input size="1" type="\' + item.field_type + \'" value="\' + item.field_value + \'" class="elementor-field elementor-size-\' + settings.input_size + \' \' + itemClasses + \'" name="form_field_\' + i + \'" id="form_field_\' + i + \'" \' + required + \' \' + placeholder + \' >\' + ( item.password_preview_toggle ? \'<i class="ap-password-preview fa fa-eye" aria-hidden="true" style="position:absolute;right:20px;bottom:13px;cursor:pointer"></i>\' : \'\' ) + \'';
		    $template = str_replace( $old_template, $new_template, $template );

		    // File preview
            $placeholderImg = \Elementor\Utils::get_placeholder_image_src();
            $old_template = '<div class="{{ fieldGroupClasses }}">';
		    $new_template = <<<EOF
<# if ( item.field_type === 'upload' && item.file_preview_toggle) { #>
    <div class="{{ fieldGroupClasses }}" style="display:flex;flex-direction:column;align-items:flex-start;">
        <# if ( printLabel && item.field_label ) { #>
            <label class="elementor-field-label" for="form_field_{{ i }}" {{{ labelVisibility }}}>{{{ item.field_label }}}</label>
        <# } #>
        <img onclick="this.parentNode.nextElementSibling.lastElementChild.click()" alt="preview" class="ap-file-preview" src="$placeholderImg"  onload="t=parentNode.nextElementSibling.lastElementChild.defaultValue;if(t){this.src=t;this.onload=null;}" style="cursor:pointer;"/>
        <div onclick="this.parentNode.nextElementSibling.lastElementChild.click()" style="cursor:pointer;position:absolute;bottom:52px;padding:6px;width:60px;height:30px;background-color:#0a0a0a;display:flex;color:white;font-weight:600;border-radius:5px;box-shadow:0px 0px 10px 0px rgba(0,0,0,0.5)"><i class="eicon-pencil"></i><span style="line-height:20px">&nbsp;Edit</span></div>
    </div>
<# }else{ #>
    <div class="{{ fieldGroupClasses }}">
        <# if ( printLabel && item.field_label ) { #>
            <label class="elementor-field-label" for="form_field_{{ i }}" {{{ labelVisibility }}}>{{{ item.field_label }}}</label>
        <# } #>
        {{{ inputField }}}
    </div>
<# } #>
<div class="{{ fieldGroupClasses }}" style="display:none">
EOF;
            $template = str_replace( $old_template, $new_template, $template );
        }
	    return $template;
    }

    public function add_custom_style_controls( $widget, $args ){
        $widget->start_controls_section(
            'ap_common_additional_styles',
            [
                'label' => __( 'Additional Styles', 'actions-pack' ),
                'tab' => Controls_Manager::TAB_STYLE
            ]
        );

        $widget->add_control(
            'ap_common_file_preview_popover',
            [
                'label' => __( 'File Preview', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes'
            ]
        );

        $widget->start_popover();

        $widget->add_control(
            'ap_common_file_preview_description',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => 'You must enable file preview option in your form field of type upload.',
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
            ]
        );

        $widget->add_responsive_control(
            'ap_common_file_preview_width',
            [
                'label' => __( 'Width', 'actions-pack' ),
                'type' => Controls_Manager::SLIDER,
                'default' => [
                    'unit' => 'px',
                ],
                'tablet_default' => [
                    'unit' => 'px',
                ],
                'mobile_default' => [
                    'unit' => 'px',
                ],
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 1000,
                    ]
                ],
                'selectors' => [
                    '{{WRAPPER}} .ap-file-preview' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $widget->add_responsive_control(
            'ap_common_file_preview_height',
            [
                'label' => __( 'Height', 'actions-pack' ),
                'type' => Controls_Manager::SLIDER,
                'default' => [
                    'unit' => 'px',
                ],
                'tablet_default' => [
                    'unit' => 'px',
                ],
                'mobile_default' => [
                    'unit' => 'px',
                ],
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 500,
                    ]
                ],
                'selectors' => [
                    '{{WRAPPER}} .ap-file-preview' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $widget->add_responsive_control(
            'ap_common_file_preview_object_fit',
            [
                'label' => __( 'Object Fit', 'actions-pack' ),
                'type' => Controls_Manager::SELECT,
                'condition' => [
                    'ap_common_file_preview_height[size]!' => '',
                ],
                'options' => [
                    '' => __( 'Default', 'actions-pack' ),
                    'fill' => __( 'Fill', 'actions-pack' ),
                    'cover' => __( 'Cover', 'actions-pack' ),
                    'contain' => __( 'Contain', 'actions-pack' ),
                ],
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .ap-file-preview' => 'object-fit: {{VALUE}};',
                ],
            ]
        );

        $widget->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'ap_common_file_preview_border',
                'label' => __( 'Border', 'actions-pack' ),
                'selector' => '{{WRAPPER}} .ap-file-preview',
            ]
        );

        $widget->add_control(
            'ap_common_file_preview_border_radius',
            [
                'label' => __( 'Border Radius', 'actions-pack' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .ap-file-preview' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $widget->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'ap_common_file_preview_shadow',
                'label' => __( 'Box Shadow', 'actions-pack' ),
                'selector' => '{{WRAPPER}} .ap-file-preview',
            ]
        );

        $widget->end_popover();

        $widget->add_control(
            'ap_common_floating_label_popover',
            [
                'label' => __( 'Floating Label', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes',
            ]
        );

        $widget->start_popover();

        $widget->add_control(
            'ap_common_floating_label_enable',
            [
                'label' => __( 'Floating Label', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'actions-pack' ),
                'label_off' => __( 'No', 'actions-pack' ),
                'return_value' => 'yes',
                'default' => 'no',
                'separator' => 'before',
                'description' => __('You must set label for each form fields and remove placeholders. Works only on frontend.', 'actions-pack')
            ]
        );

        $widget->end_popover();

        $widget->end_controls_section();
    }

    /**
     * @param \Elementor\Widget_Base $widget
     * @return string $content
     */
    public function render_content( $content, $widget ){
        // Return if widget is not form type
        if( $widget->get_name() !== 'form' ){
            return $content;
        }

        $settings =  $widget->get_settings_for_display();

        if(!empty($settings['ap_common_floating_label_enable']) && $settings['ap_common_floating_label_enable'] === 'yes'){
            wp_enqueue_script('ap-common');
            wp_enqueue_style('ap-common');
            $widget->add_render_attribute('_wrapper', [
                'class' => 'ap-floating-label',
            ]);
        }

        // Remove Empty Divs
        $content = preg_replace("/<div\s*>\s*<\/div>/", "", $content);
        return $content;
    }
}
