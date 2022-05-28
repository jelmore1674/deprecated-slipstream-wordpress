<?php

use Elementor\Controls_Manager;
use ElementorPro\Modules\Popup\Document;
use ElementorPro\Modules\QueryControl\Module as QueryControlModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Action_Reset_Pass extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    public $url_query;

	public function __construct(){
		// AJAX and PAGE Request
		add_action( 'elementor/element/form/section_field_style/after_section_end', [ $this, 'add_custom_controls'], 10, 2);
        add_action( 'elementor-pro/forms/pre_render', [ $this, 'maybe_print_popup' ], 10, 2 );
		// Ajax request
		if( wp_doing_ajax() ){
			add_action ( 'wp_ajax_nopriv_ap_execute_reset_pass_ajax_operations', [ $this, 'execute_reset_pass_ajax_operations' ] );
			add_action ( 'wp_ajax_ap_execute_reset_pass_ajax_operations', [ $this, 'execute_reset_pass_ajax_operations' ] );
            add_action( 'elementor_pro/forms/validation', [ $this, 'form_validate' ], 999999, 2 ); // Higer the Priority
		}else{
			// Page Request
			add_action( 'elementor/widget/render_content',[$this, 'render_content'], 10, 2);
			add_filter( 'elementor_pro/forms/render/item', [ $this, 'form_field_render' ], -999999, 3 ); // Lower Priority
		}
	}

	public function get_settings( $post_id, $form_id ){
		$elementor = \Elementor\Plugin::$instance;
		$document = $elementor->documents->get( $post_id );
		$form = \ElementorPro\Modules\Forms\Module::find_element_recursive( $document->get_elements_data(), $form_id );
		$widget = $elementor->elements_manager->create_element_instance( $form );
		return $widget->get_settings_for_display();
	}

	public function execute_reset_pass_ajax_operations(){
		$user_input = sanitize_user( $_POST['username'] );
		$operation = (string) $_POST['operation'];
		$channel = (string) $_POST['channel'];

		if( is_email( $user_input ) ){
			$user_id = email_exists( $user_input );
		}
		elseif ( ap_is_phone( $user_input )){
			$user_id = ap_channel_value_exists('phone', $user_input );
		}
		else{
			$user_id = username_exists( $user_input );
		}

		if( ! $user_id  ){
            wp_send_json_error(__('User does not exist.', 'actions-pack'));
		}

		if( ! wp_verify_nonce( $_POST['nonce'], $user_id . $operation ) ) {
			wp_send_json_error(__('Invalid nonce', 'actions-pack'));
		}

		$reset_pass_attempt = (int) get_transient( $user_id . '_ap_reset_pass_attempt');
		if ( $reset_pass_attempt > 1){
			$expiry = get_option( '_transient_timeout_' .  $user_id . '_ap_reset_pass_attempt' );
			$time_left = abs( time() - $expiry );
			$time_left = gmdate("i:s", $time_left );
			wp_send_json_error(sprintf( __('Too many failed attempts. Try after <span class="apCountDown">%s</span> minutes!', 'actions-pack'), $time_left ));
		}

		switch ( $operation ){
			case 'notify_' . $channel :
				$this->notify( $user_input, $user_id, $channel );
				break;
			case 'verify_otp' :
				$this->verify_otp( $user_input, $user_id );
				break;
		}
	}

	public function notify( $user_input, $user_id, $channel ){
		$post_id = (int) $_POST['postId'];
		$form_id = (string) $_POST['formId'];

		$settings = $this->get_settings( $post_id, $form_id);
		if( AP_IS_GOLD ){
			$reset_pass_through = $settings['ap_reset_pass_through'];
		}else{
			$reset_pass_through = 'link';
		}

        $user = get_userdata( $user_id );

		if ( $channel === 'email'){
			if ( ! empty( $settings['ap_reset_pass_email_from'] ) && ! empty( $settings['ap_reset_pass_email_from_name'] ) && ! empty( $settings['ap_reset_pass_email_subject'] ) && ! empty( $settings['ap_reset_pass_email_message'] ) ) {
				$from         = $settings['ap_reset_pass_email_from'];
				$from_name    = $settings['ap_reset_pass_email_from_name'];
				$to           = $user->user_email;
				$subject      = $settings['ap_reset_pass_email_subject'];
				$content_type = $settings['ap_reset_pass_email_content_type'];
				$message      = $settings['ap_reset_pass_email_message'];
				$message      = $this->replace_actions_pack_shortcode( $message, $reset_pass_through, $user_input, $user, $user_id, $post_id );
				ap_send_mail( $from, $from_name, $to, $subject, $content_type, $message );
				wp_send_json_success( __( 'Email Sent ', 'actions-pack' ) );
			}
		}

		if ( AP_IS_GOLD && $channel === 'sms'){
			if ($settings['ap_reset_pass_phone_credentials_source'] === 'default' ){
				// Data from SMS Action
				$sid = get_option('elementor_ap_sms_account_sid');
				$token = get_option('elementor_ap_sms_auth_token');
				$from = get_option('elementor_ap_sms_from_number');
			}
			elseif( !empty( $settings['ap_reset_pass_sms_account_sid'] ) && !empty( $settings['ap_reset_pass_sms_auth_token'] ) && !empty( $settings['ap_reset_pass_sms_from_number'] )){
				$sid = $settings['ap_reset_pass_sms_account_sid'];
				$token = $settings['ap_reset_pass_sms_auth_token'];
				$from = $settings['ap_reset_pass_sms_from_number'];
			}
			else{
				return;
			}
			$phone                    = get_user_meta($user_id, 'user_phone', true);
			$message                  = $settings['ap_reset_pass_phone_message'];
			$message                  = $this->replace_actions_pack_shortcode( $message, $reset_pass_through, $user_input, $user, $user_id, $post_id );
			ap_send_sms( $sid, $token, $from, $phone, $message );
			wp_send_json_success( __( 'SMS Sent ', 'actions-pack' ) );
		}
	}

	public function verify_otp( $user_input, $user_id ){
		$stored_otp = (int) get_user_meta( $user_id, 'ap_reset_pass_otp', true);
		$received_otp = (int) $_POST['otp'];

		if( $stored_otp === $received_otp ){
			delete_user_meta( $user_id, 'ap_reset_pass_otp');
			$post_id = (int) $_POST['postId'];
			$form_id = (string) $_POST['formId'];
			$user = get_userdata( $user_id);
			wp_send_json_success( [
				'message' => __('Verified', 'actions-pack'),
				'data' => [
					'apResetPass'=>[
						'formId' => $form_id,
						'redirectTo' => $this->generate_reset_pass_link( $user_input, $user, $user_id, $post_id)
					]
				]
			] );
		}
		else{
			$otp_attempt = get_transient( $user_id . '_ap_reset_pass_attempt');
			set_transient( $user_id . '_ap_reset_pass_attempt', $otp_attempt+1, 1800 );
			wp_send_json_error(__('Invalid Code', 'actions-pack'));
		}
	}

	public function replace_actions_pack_shortcode( $message, $reset_pass_through, $user_input, $user, $user_id, $post_id ){

		return strtr( $message, [
			'[ap-reset-pass]' => $reset_pass_through === 'link' ? $this->generate_reset_pass_link( $user_input, $user, $user_id, $post_id ) : get_user_meta( $user_id, 'ap_reset_pass_otp', true ),
			'[ap-username]' => $user->user_login,
			'[ap-firstname]' => ($first_name = get_user_meta( $user_id, 'first_name', true)) ? $first_name : '',
			'[ap-lastname]' =>  ($last_name = get_user_meta( $user_id, 'last_name', true)) ? $last_name : '',
		]);

	}

	public function generate_reset_pass_link( $user_input, $user, $user_id, $post_id ){
		$reset_pass_link = add_query_arg( [
			'action' => 'rpass',
			'user' => rawurlencode($user_input),
			'key' => get_password_reset_key( $user ),
			'nonce' => wp_create_nonce( $user_id ),
		], ap_get_referrer());

		if( get_post_meta( $post_id, '_elementor_template_type', true ) === 'popup' ){
			// Mocking Plugin::elementor()->frontend->create_action_hash
			$reset_pass_link = $reset_pass_link . '#' . rawurlencode( sprintf( 'action=%1$s&settings=%2$s', 'popup:open', base64_encode( wp_json_encode( [ 'id' => $post_id ]) ) ) );
		}
		return $reset_pass_link;
	}

	public function generate_reset_pass_otp( $user_id, $otp_length ){
		$min = 1 . str_repeat( 0, $otp_length - 1);
		$max = str_repeat( 9, $otp_length);
		$unique_otp = wp_rand($min, $max);
		update_user_meta( $user_id, 'ap_reset_pass_otp', $unique_otp );
		return $unique_otp;
	}

	public function add_custom_controls( $element, $args ){
		//https://github.com/elementor/elementor/issues/6499
		$element->start_controls_section(
			'ap_reset_pass_otp_style',
			[
				'label' => __( 'OTP', 'actions-pack' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'conditions' => [
					'relation' => 'and',
					'terms' =>
						[
							[
								'name' => 'submit_actions',
								'operator' => 'contains',
								'value' => 'reset-pass'
							],
							[
								'name' => 'ap_reset_pass_through',
								'operator' => '=',
								'value' => 'otp'
							]
						]
				],
			]
		);
		$element->add_control(
			'ap_reset_pass_otp_container',
			[
				'label' => __( 'Container', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);
		$element->add_control(
			'ap_reset_pass_otp_container_position',
			[
				'label' => __( 'Position', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''  => __( 'Default', 'actions-pack' ),
					'absolute' => __( 'Absolute', 'actions-pack' ),
					'fixed' => __( 'Fixed', 'actions-pack' ),
				],
				'prefix_class' => 'ap-',
				'frontend_available' => true,
			]
		);
		$element->add_control(
			'ap_reset_pass_otp_container_margin',
			[
				'label' => __( 'Margin', 'actions-pack' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .ap-otp-container' => 'margin-top: {{TOP}}{{UNIT}}; margin-right: {{RIGHT}}{{UNIT}}; margin-bottom: {{BOTTOM}}{{UNIT}}; margin-left: {{LEFT}}{{UNIT}};',
				],
			]
		);
		$element->add_control(
			'ap_reset_pass_otp_container_padding',
			[
				'label' => __( 'Padding', 'actions-pack' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .ap-otp-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$element->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			[
				'name' => 'ap_reset_pass_otp_container_background',
				'label' => __( 'Container Background', 'actions-pack' ),
				'types' => [ 'classic', 'gradient', 'video' ],
				'selector' => '{{WRAPPER}} .ap-otp-container',
			]
		);

		$element->add_control(
			'ap_reset_pass_otp_box',
			[
				'label' => __( 'Box', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);
		$element->add_control(
			'ap_reset_pass_otp_box_width',
			[
				'label' => __('Box Width', 'actions-pack'),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => ['%', 'px', 'vw'],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 2000,
						'step' => 5,
					],
					'%' => [
						'min' => 0,
						'max' => 100,
					],
					'vw' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .ap-otp-container .ap-otp-box' => 'width: {{SIZE}}{{UNIT}};',
				]
			]
		);
		$element->add_control(
			'ap_reset_pass_otp_box_height',
			[
				'label' => __('Box Height', 'actions-pack'),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => ['%', 'px', 'vh'],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 2000,
						'step' => 5,
					],
					'%' => [
						'min' => 0,
						'max' => 100,
					],
					'vh' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .ap-otp-container .ap-otp-box' => 'height: {{SIZE}}{{UNIT}};',
				]
			]
		);
		$element->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			[
				'name' => 'ap_reset_pass_otp_box_background',
				'label' => __( 'Box Background', 'actions-pack' ),
				'types' => [ 'classic', 'gradient', 'video' ],
				'selector' => '{{WRAPPER}} .ap-otp-box',
			]
		);
		$element->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'ap_reset_pass_otp_box_border',
				'label' => __( 'Border', 'actions-pack' ),
				'selector' => '{{WRAPPER}} .ap-otp-box',
			]
		);

		$element->add_control(
			'ap_reset_pass_otp_box_border_radius',
			[
				'label' => __( 'Border Radius', 'actions-pack' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .ap-otp-box' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$element->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'ap_reset_pass_otp_box_shadow',
				'label' => __( 'Box Shadow', 'actions-pack' ),
				'selector' => '{{WRAPPER}} .ap-otp-box',
			]
		);

		$element->add_control(
			'ap_reset_pass_otp_text',
			[
				'label' => __( 'Text', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$element->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'ap_reset_pass_otp_heading',
				'label' => __( 'Typography', 'actions-pack' ),
				'scheme' => \Elementor\Core\Schemes\Typography::TYPOGRAPHY_1,
				'selector' => '{{WRAPPER}} .ap-otp-heading p',
			]
		);

		$element->add_control(
			'ap_reset_pass_otp_heading_color',
			[
				'label' => __( 'Color', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'scheme' => [
					'type' => \Elementor\Core\Schemes\Color::get_type(),
					'value' => \Elementor\Core\Schemes\Color::COLOR_1,
				],
				'selectors' => [
					'{{WRAPPER}} .ap-otp-heading p' => 'color: {{VALUE}}',
				],
			]
		);

		$element->add_control(
			'ap_reset_pass_otp_inputs',
			[
				'label' => __( 'Individual Input', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$element->add_control(
			'ap_reset_pass_otp_individual_input',
			[
				'label' => __('Size', 'actions-pack'),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'selectors' => [
					'{{WRAPPER}} .ap-otp-inputs input' => 'width: {{SIZE}}px; height: {{SIZE}}px;',
				]
			]
		);
		$element->add_control(
			'ap_reset_pass_otp_individual_input_focus_color',
			[
				'label' => __( 'Focus Color', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'scheme' => [
					'type' => \Elementor\Core\Schemes\Color::get_type(),
					'value' => \Elementor\Core\Schemes\Color::COLOR_1,
				],
				'selectors' => [
					'{{WRAPPER}} .ap-otp-inputs input:focus' => 'border:1px solid {{VALUE}}; box-shadow: 0 0 5px {{VALUE}};',
				],
			]
		);

		$element->end_controls_section();
	}

	public function render_content( $content, $widget ){
		if ( 'form' === $widget->get_name() ) {

			$settings = $widget->get_settings();

			if( in_array( $this->get_name(), $settings['submit_actions'] ) ){

				wp_enqueue_script('ap-user');
				wp_enqueue_style('ap-user');
				if ( $settings['ap_reset_pass_hide_form'] === 'yes' && is_user_logged_in() && ! \Elementor\Plugin::$instance->editor->is_edit_mode() ){
					$content = '';
				}
			}
		}
		return $content;
	}

	public function form_field_render( $field, $field_index, $form_widget ) {
		$settings = $form_widget->get_settings();

		if( in_array( $this->get_name(), $settings['submit_actions'] ) && ! \Elementor\Plugin::$instance->editor->is_edit_mode() ){

			if( isset( $_GET['action'] ) && $_GET['action'] === 'rpass' && isset( $_GET['user'])  && isset( $_GET['key'] ) && isset( $_GET['nonce'] ) ){
				// Remove Email Field and Show only Password field
			    if( $field['custom_id'] === $settings['ap_reset_pass_login_id'] ){
					echo '<style>.ap-userid{display:none}</style>';
                    $form_widget->remove_render_attribute( 'field-group' . $field_index);
                    return ['field_type' => '', 'field_label' => ''];
				}
			}else{
			    // Remove Password field & show only Email field
				if( !empty($field['custom_id']) && $field['custom_id'] === $settings['ap_reset_pass_new_password'] ){
					echo '<style>.ap-password{display:none}</style>';
                    $form_widget->remove_render_attribute( 'field-group' . $field_index);
                    return ['field_type' => '', 'field_label' => ''];
				}
			}
		}

		return $field;
	}

    public function maybe_print_popup( $settings, $widget ) {
        if ( ! is_array( $settings['submit_actions'] ) || ! in_array( 'reset-pass', $settings['submit_actions'] ) ) {
            return;
        }
        if ( empty( $popup_id = $settings['ap_reset_pass_instruction_message_popup']) ) {
            return;
        }
        \ElementorPro\Modules\Popup\Module::add_popup_to_location( $popup_id );
    }

	/**
     * Get Name
     *
     * Return the action name
     *
     * @access public
     * @return string
     */
    public function get_name() {
        return 'reset-pass';
    }

    /**
     * Get Label
     *
     * Returns the action label
     *
     * @access public
     * @return string
     */
    public function get_label() {
        return __( 'Reset Password', 'actions-pack' );
    }

    /**
     * `Reset Password` Settings Section
     *
     * Registers the Action controls
     *
     * @access public
     * @param \Elementor\Widget_Base $widget
     */
    public function register_settings_section( $widget ) {


        $widget->start_controls_section(
            'ap_section_reset_pass',
            [
                'label' => __( 'Reset Password', 'actions-pack' ),
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );

        $widget->add_control(
            'ap_reset_pass_login_id',
            [
                'label' => __( 'Login ID <span class="ap-required">*</span>', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [],
            ]
        );

	    $widget->add_control(
		    'ap_reset_pass_new_password',
		    [
			    'label' => __( 'Password <span class="ap-required">*</span>', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'options' => [],
			    'description' => __( '<span style="color: #ef0a1d">Note</span>: The password field will be automatically hidden on frontend and only be visible when users click on the reset password link.', 'actions-pack')
		    ]
	    );

	    $widget->add_control(
		    'ap_reset_pass_through',
		    [
			    'label' => __( 'Reset Through', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'multiple' => false,
			    'options' => [
				    'link' => 'Link',
				    'otp' => 'OTP',
			    ],
			    'default' => 'link',
			    'classes' => 'ap-upgrade',
			    'description' => ( AP_IS_GOLD ? '' : AP_UPGRADE_TO_GOLD )
		    ]
	    );

	    $widget->add_control(
		    'ap_reset_pass_otp_length',
		    [
			    'label' => __( 'OTP Length', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::NUMBER,
			    'show_label' => true,
			    'min' => 2,
			    'max' => 10,
			    'step' => 1,
			    'default' => 4,
			    'condition' =>[
			    	'ap_reset_pass_through' => 'otp'
			    ]
		    ]
	    );

	    $widget->add_control(
		    'ap_reset_pass_send_via',
		    [
			    'label' => __( 'Link/OTP Send Via', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT2,
			    'label_block' => FALSE,
			    'multiple' => true,
			    'options' => [
				    'email' => 'Email',
				    'sms' => 'SMS'
			    ],
			    'default' => [ 'email'],
			    'classes' => 'ap-upgrade',
			    'description' => ( AP_IS_GOLD ? '' : AP_UPGRADE_TO_GOLD )
		    ]
	    );

	    //******************************************************* Email ************************************************
	    $widget->add_control(
		    'ap_reset_pass_email_popover',
		    [
			    'label' => __( 'Email Settings', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
			    'label_off' => __( 'Default', 'actions-pack' ),
			    'label_on' => __( 'Custom', 'actions-pack' ),
			    'return_value' => 'yes',
			    'conditions' => [
			    	'relation' => 'and',
				    'terms' => [
				    	[
						    'name' => 'ap_reset_pass_send_via',
						    'operator' => 'contains',
						    'value' => 'email'
					    ]
				    ]
			    ]
		    ]
	    );
	    $widget->start_popover();
	    $widget->add_control(
		    'ap_reset_pass_email_from_name',
		    [
			    'label' => __( 'From Name', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'default' => get_bloginfo( 'name' ),
			    'placeholder' => get_bloginfo( 'name' ),
			    'show_label' => true,
		    ]
	    );
        $widget->add_control(
            'ap_reset_pass_email_from',
            [
                'label' => __( 'From', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => get_bloginfo('admin_email'),
                'placeholder' => get_bloginfo('admin_email'),
            ]
        );

        $widget->add_control(
            'ap_reset_pass_email_subject',
            [
                'label' => __( 'Subject', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Your password reset link',
            ]
        );
	    $widget->add_control(
		    'ap_reset_pass_email_content_type',
		    [
			    'label' => __( 'Send As', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'default' => 'plain',
			    'render_type' => 'none',
			    'options' => [
				    'html' => __( 'HTML', 'actions-pack' ),
				    'plain' => __( 'Plain', 'actions-pack' )
			    ],
		    ]
	    );
        $widget->add_control(
            'ap_reset_pass_email_message',
            [
                'label' => __( 'Message', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Hi, Use the following link/otp [ap-reset-pass] to reset your password','actions-pack'),
                'placeholder' => __('Hi, Use the following link/otp [ap-reset-pass] to reset your password','actions-pack'),
                'show_label' => true,
            ]
        );
        $widget->end_popover();
	    //******************************************************* SMS ************************************************
	    $widget->add_control(
		    'ap_reset_pass_sms_popover',
		    [
			    'label' => __( 'SMS Settings', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
			    'label_off' => __( 'Default', 'actions-pack' ),
			    'label_on' => __( 'Custom', 'actions-pack' ),
			    'return_value' => 'yes',
			    'conditions' => [
				    'relation' => 'and',
				    'terms' => [
					    [
						    'name' => 'ap_reset_pass_send_via',
						    'operator' => 'contains',
						    'value' => 'sms'
					    ]
				    ]
			    ]
		    ]
	    );
	    $widget->start_popover();
	    
	    $widget->add_control(
		    'ap_reset_pass_phone_credentials_source',
		    [
			    'label' => __( 'API Source', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'label_block' => false,
			    'options' =>[
				    'default' => 'Default',
				    'custom' => 'Custom'
			    ],
			    'default' => 'default',
		    ]
	    );
	    $widget->add_control(
		    'ap_reset_pass_phone_credentials_notice',
		    [
			    'type' => \Elementor\Controls_Manager::RAW_HTML,
			    'raw' => sprintf('%s <a style="color: #0b76ef" href="%s" target="_blank">%s</a>. %s',__('To use default credentials, make sure you have already set the credentials', 'actions-pack'),admin_url('admin.php?page=elementor#tab-integrations'), __('here', 'actions-pack'), __('You can use this field to set a custom credential for current form only', 'actions-pack')),
			    'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
			    'condition' => [
				    'ap_reset_pass_phone_credentials_source' => 'default',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_reset_pass_phone_custom_source_notice',
		    [
			    'type' => \Elementor\Controls_Manager::RAW_HTML,
			    'raw' => sprintf('%s <a href="%s" target="_blank" style="color: #0b76ef"> %s </a> %s', __('Click', 'actions-pack'), 'https://twilio.com/referral/nWJHpb',__('here', 'actions-pack'), __('to get your Twilio Account SID, Auth Token and Phone number', 'actions-pack') ),
			    'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
			    'condition' => [
				    'ap_reset_pass_phone_credentials_source' => 'custom',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_reset_pass_sms_account_sid',
		    [
			    'label' => __( 'Account SID', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'label_block' => false,
			    'condition' => [
				    'ap_reset_pass_phone_credentials_source' => 'custom',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_reset_pass_sms_auth_token',
		    [
			    'label' => __( 'Auth Token', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'label_block' => false,
			    'condition' => [
				    'ap_reset_pass_phone_credentials_source' => 'custom',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_reset_pass_sms_from_number',
		    [
			    'label' => __( 'From Number', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'placeholder' => '+919876543210',
			    'show_label' => true,
			    'condition' =>[
				    'ap_reset_pass_phone_credentials_source' => 'custom',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_reset_pass_phone_message',
		    [
			    'label' => __( 'Message', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXTAREA,
			    'default' => __('Hi, Use the following link/otp [ap-reset-pass] to reset your password','actions-pack'),
			    'placeholder' => __('Hi, Use the following link/otp [ap-reset-pass] to reset your password','actions-pack'),
			    'show_label' => true,
		    ]
	    );
	    $widget->end_popover();
	    //********************************************* Additional Options *********************************************
	    $widget->add_control(
		    'ap_reset_pass_additional_popover',
		    [
			    'label' => __( 'Additional Settings', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
			    'label_off' => __( 'Default', 'actions-pack' ),
			    'label_on' => __( 'Custom', 'actions-pack' ),
			    'return_value' => 'yes',
		    ]
	    );
	    $widget->start_popover();

        $widget->add_control(
            'ap_reset_pass_instruction_message',
            [
                'label' => __( 'Reset Password Instruction', 'actions-pack' ),
                'type' => Controls_Manager::SELECT,
                'multiple' => false,
                'options' => [
                    'text' => 'Text Message',
                    'popup' => 'Display Popup'
                ],
                'default' => 'text',
                'render_type' => 'none',
                'label_block' => true,
            ]
        );
        $widget->add_control(
            'ap_reset_pass_instruction_message_text',
            [
                'label' => __( 'Text Message', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'label_block' => true,
                'default' => __('Please check your Email for the instructions to reset your password.', 'actions-pack'),
                'condition' => [
                    'ap_reset_pass_instruction_message' => 'text',
                ],
            ]
        );
        $widget->add_control(
            'ap_reset_pass_instruction_message_popup',
            [
                'label' => __( 'Popup', 'actions-pack' ),
                'type' => QueryControlModule::QUERY_CONTROL_ID,
                'label_block' => true,
                'autocomplete' => [
                    'object' => QueryControlModule::QUERY_OBJECT_LIBRARY_TEMPLATE,
                    'query' => [
                        'posts_per_page' => 20,
                        'meta_query' => [
                            [
                                'key' => Document::TYPE_META_KEY,
                                'value' => 'popup',
                            ],
                        ],
                    ],
                ],
                'condition' => [
                    'ap_reset_pass_instruction_message' => 'popup',
                ],
            ]
        );
	    $widget->add_control(
		    'ap_reset_pass_hide_form',
		    [
			    'label' => __( 'Hide Form', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Yes', 'actions-pack' ),
			    'label_off' => __( 'No', 'actions-pack' ),
			    'return_value' => 'yes',
			    'default' => 'no',
			    'separator' => 'before',
			    'description' => __('Hide this Form for logged-in users. The option does not work in Editor mode.', 'actions-pack')
		    ]
	    );

	    $widget->end_popover();

        $widget->end_controls_section();

    }

    /*
     * Conditionally Remove Required fields error
     */
    public function form_validate( $record, $ajax_handler ){
        $url = parse_url(ap_get_referrer());
        if(isset( $url['query'] )){
            parse_str($url['query'], $this->url_query);
        }
        if( isset( $this->url_query['action'] ) && $this->url_query['action'] === 'rpass' && isset( $this->url_query['key'] ) && isset( $this->url_query['user'] )){
            $login_field_id = $record->get_form_settings('ap_reset_pass_login_id');
            unset($ajax_handler->errors[$login_field_id]);
        }else{
            $password_field_id = $record->get_form_settings('ap_reset_pass_new_password');
            unset($ajax_handler->errors[$password_field_id]);
        }
    }

    /**
     * Run
     *
     * Runs the action after submit
     *
     * @access public
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
     */
    public function run( $record, $ajax_handler )
    {
        // Don't proceed if user already logged in
        if (is_user_logged_in()) {
            $ajax_handler->add_error_message(__('You are already logged-in', 'actions-pack'));
            $ajax_handler->is_success = false;
            return;
        }

        $settings = $record->get('form_settings');

        // Normalize the Form Data
	    $form_data = [];
	    $raw_fields = $record->get('fields');
	    foreach ($raw_fields as $id => $field) {
		    $form_data[$id] = $field['value'];
	    }

	    // Basic data
	    $post_id = (int) $_POST['post_id'];

	    if( isset( $this->url_query['action'] ) && $this->url_query['action'] === 'rpass' && isset( $this->url_query['key'] ) && isset( $this->url_query['user'] ) ){
	    	// New Password Set
		    if ( empty( $new_pass = $form_data[$settings['ap_reset_pass_new_password']] )  ) {
			    $ajax_handler->add_error_message(__('Blank password is not allowed.', 'actions-pack'));
			    return;
		    }

		    $user_input = sanitize_user( $this->url_query['user'] );

            if( is_email( $user_input ) ){
                $user_id = email_exists( $user_input );
            }
            elseif ( ap_is_phone( $user_input )){
                $user_id = ap_channel_value_exists('phone', $user_input);
            }
            else{
                $user_id = username_exists( $user_input );
            }

		    if( $user_id && wp_verify_nonce( $this->url_query['nonce'], $user_id ) && !is_wp_error( check_password_reset_key( $this->url_query['key'], get_userdata($user_id)->user_login ) )){
			    wp_set_password( $new_pass, $user_id);
                $ajax_handler->is_success = true; // Don't remove it
		    }
		    else{
			    $ajax_handler->add_error_message(__('Invalid or Expired link', 'actions-pack'));
			    return;
		    }
	    }
	    else{
	    	// User Login Check and send notification
	    	if( empty( $user_input = $form_data[$settings['ap_reset_pass_login_id']]) ){
			    $ajax_handler->add_error_message(__('Enter your login ID. Try again!'));
	    		return;
		    }
	    	
            $user_input = sanitize_user($user_input);
	    	
		    if( is_email( $user_input ) ){
			    $user_id = email_exists( $user_input );
		    }
		    elseif ( ap_is_phone( $user_input )){
			    $user_id = ap_channel_value_exists('phone', $user_input );
		    }
		    else{
			    $user_id = username_exists( $user_input );
		    }
		    if( $user_id ){
			    $ajax_data = [];
			    $ajax_data['username'] = $user_input;
			    $ajax_data['nonce'] = wp_create_nonce($user_id . 'verify_otp');
			    $ajax_data['formId'] = $settings['id'];
			    $ajax_data['postId'] = $post_id;

			    $channels = $settings['ap_reset_pass_send_via'];
			    foreach( $channels as $channel){
				    $ajax_data['notifyChannels'][$channel]['nonce'] = wp_create_nonce($user_id . 'notify_'.$channel);
			    }

			    $message = $settings['ap_reset_pass_instruction_message_text'];

			    if( AP_IS_GOLD && $settings['ap_reset_pass_through'] === 'otp' ){
				    $otp_length   = (int) $settings['ap_reset_pass_otp_length'];
				    $this->generate_reset_pass_otp( $user_id, $otp_length);
				    $ajax_data['otpLength'] = $otp_length;
				    $message = '';
			    }

                if( empty($ajax_handler->errors) ){
                    $ajax_handler->add_response_data('apResetPass', $ajax_data);
                    $data = $ajax_handler->data;
                    // Don't redirect if there is any redirect actions added
                    unset($data['redirect_url']);
                    if( !empty($settings['ap_reset_pass_instruction_message_popup'])){
                        $message = '';
                        $data['popup'] = [
                            'action' => 'open',
                            'id' => $settings['ap_reset_pass_instruction_message_popup']
                        ];
                    }
                    wp_send_json_success([
                        'message' => $message,
                        'data' => $data,
                    ]);
                }
		    }
		    else{
			    $ajax_handler->add_error_message(__('User doesn\'t exist. Try again!', 'actions-pack'));
			    return;
		    }

	    }

	}

    /**
     * On Export
     *
     * Clears form settings on export
     * @access Public
     * @param array $element
     */
    public function on_export( $element ) {
        unset(
            $element['ap_reset_pass_login_id'],
            $element['ap_reset_pass_link_to_set_new_pass'],
            $element['ap_reset_pass_email_from'],
            $element['ap_reset_pass_email_subject'],
            $element['ap_reset_pass_email_message']
        );
    }
}
