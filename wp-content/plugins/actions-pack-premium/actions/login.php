<?php

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Action_Login extends \ElementorPro\Modules\Forms\Classes\Action_Base {

	public function __construct(){

		// @toDo check priority of "authenticate" and "wp_authenticate_user" and combine the logic in one function
		// Login through phone number
		add_filter( 'authenticate', [ $this, 'authenticate_phone_password' ], 20, 3 );

		// Check if they are verified via url/otp
		add_filter( 'wp_authenticate_user', [ $this, 'is_user_verified' ], 10, 1 );

		// Remember Me Duration
		if( AP_IS_GOLD ){
			add_filter( 'auth_cookie_expiration', function(){
				$option = get_option('actions_pack');
				if( empty( $option['remember_me_duration'] ) ){
					return 14 * DAY_IN_SECONDS;
				}else{
					return $option['remember_me_duration'] * DAY_IN_SECONDS;
				}
			} );
		}

		// Ajax and Page Request
		add_action( 'elementor/element/form/section_field_style/after_section_end', [ $this, 'add_custom_controls'], 10, 2);

		// Ajax request
		if( wp_doing_ajax() ){
			add_action ( 'wp_ajax_nopriv_ap_execute_login_ajax_operations', [ $this, 'execute_login_ajax_operations' ] );
			add_action ( 'wp_ajax_ap_execute_login_ajax_operations', [ $this, 'execute_login_ajax_operations' ] );
		}
		// Page request
		else{

			// Wordpress Login Page
			add_action( 'login_enqueue_scripts', function (){
				wp_enqueue_script('ap-user');
				wp_enqueue_style('ap-user');
			});

			add_action( 'elementor/widget/render_content',[$this, 'render_content'], 10, 2);

			// Redirect Default Login Page to New One
			if( AP_IS_GOLD ){
				add_action('init',function (){
					global $pagenow;
                    if( empty( get_option('actions-pack-emergency-login-url') ) ){
                        update_option('actions-pack-emergency-login-url', wp_generate_password(16));
                    }
					if( 'wp-login.php' == $pagenow && !is_user_logged_in() && empty(get_option('elementor_maintenance_mode_mode'))) {
					    if( isset($_GET['unlock']) && $_GET['unlock'] === get_option('actions-pack-emergency-login-url')){
					        return;
                        }
						$option = get_option('actions_pack');
						if( !empty( $option['default_login_page_redirection_url'] ) ) {
							wp_safe_redirect( $option['default_login_page_redirection_url'], 302 );
							exit();
						}
					}
				});
			}
		}
	}

	public function is_user_verified( $user ) {
		if ( is_a($user, 'WP_User')){

			$channels = ['email', 'phone', 'manually'];

			foreach( $channels as $channel){
				if( $channel = $this->is_channel_verified( $channel, $user) ){
					return new WP_Error(  $channel['code'], $channel['message'] );
				}
			}
			return $user;
		}
	}

	public function is_channel_verified( $channel, $user){

		$user_id = $user->ID;
		$username = $user->user_login;
		$ajax_data = [];

		$is_channel_verified = get_user_meta($user_id, 'is_'.$channel.'_verified', true);

		if( !empty( $is_channel_verified ) && $is_channel_verified !== 'yes' ) {

			$ajax_data['updateChannels'][$channel]['nonce'] = ap_create_nonce($user_id . 'update_'.$channel);

			if( $channel === 'manually'){
				// @toDo implement reminder option
				$message = sprintf(__('You can\'t login to our site now. Your profile is being verified. You will be notified once we complete the verification.', 'actions-pack') );
			}
			else if( is_numeric( $is_channel_verified ) ){ // if it is numeric, verification via OTP. the value is length of otp
				$ajax_data['notifyChannels'][$channel]['nonce'] = ap_create_nonce($user_id . 'notify_'.$channel);
				$ajax_data['verifyChannels'][$channel]['nonce'] = ap_create_nonce($user_id . 'verify_'.$channel);
				$ajax_data['verifyChannels'][$channel]['length'] = $is_channel_verified;
				$ajax_data['username'] = $username;
				$message = sprintf(__('You have not verified your %s yet. Click %sHere%s to enter the secret code we already sent you.', 'actions-pack'),
					$channel,
					'<a href="javascript:void(0)" class="ap-otp-modal-trigger" style="color: #0A31E9" data-username="' . $username . '" data-ajax="' .urlencode( json_encode( $ajax_data ) ). '">',
					'</a>'
				);
			}
			else{ // Verification via LINK
				$select =  $channel === 'phone' ? '<select class="ap-country-code"></select>' : '';
				$message = sprintf(__('You have not verified your %s yet. Click on the verification link we sent to your %s. %s %s', 'actions-pack'),
					$channel,
					$channel,
					'<div class="ap-otp-actions"><p><a href="javascript:void(0)" class="ap-otp-resend-code" data-nonce="' . ap_create_nonce($user_id . 'notify_'.$channel) . '" data-username="' . $username . '" data-channel="' . $channel . '">Resend link</a>&nbsp;|&nbsp;<a href="javascript:void(0)" class="ap-otp-change-channel">Change ' . $channel . '</a></p></div>',
					'<div class="ap-otp-update-channel">' . $select . '<input type="tel" placeholder="' . __('Enter your ', 'actions-pack') . $channel . '"><button data-nonce="' . ap_create_nonce($user_id . 'update_'.$channel) . '" data-username="' . $username . '" data-channel="' . $channel . '" type="button">Update</button></div>'
				);
			}
			return ['code' => $channel.'_not_verified', 'message' => $message];
		}
	}

	public function authenticate_phone_password( $user, $phone, $password ) {
		if ( $user instanceof WP_User ) {
			return $user;
		}

		if ( empty( $phone ) || empty( $password ) ) {
			if ( is_wp_error( $user ) ) {
				return $user;
			}

			$error = new WP_Error();

			if ( empty( $phone ) ) {
				// Uses 'empty_username' for back-compat with wp_signon().
				$error->add( 'empty_username', __( '<strong>Error</strong>: The phone field is empty.', 'actions-pack' ) );
			}

			if ( empty( $password ) ) {
				$error->add( 'empty_password', __( '<strong>Error</strong>: The password field is empty.', 'actions-pack' ) );
			}

			return $error;
		}

		if ( ! ap_is_phone( $phone ) ) {
			return $user;
		}

		// @toDo replace with ap_channel_value_exists()
		$user = get_users( [ 'meta_key' => 'user_phone', 'meta_value' => $phone, 'number' => 1, 'count_total' => false ] )[0];

		if ( ! $user ) {
			return new WP_Error(
				'invalid_phone',
				__( 'Unknown phone number. Check again or try your username.', 'actions-pack' )
			);
		}

		/** This filter is documented in wp-includes/user.php */
		$user = apply_filters( 'wp_authenticate_user', $user, $password );


		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			return new WP_Error(
				'incorrect_password',
				sprintf(
				/* translators: %s: Phone Number. */
					__( '<strong>Error</strong>: The password you entered for the phone number %s is incorrect.', 'actions-pack' ),
					'<strong>' . $phone . '</strong>'
				) .
				' <a href="' . wp_lostpassword_url() . '">' .
				__( 'Lost your password?', 'actions-pack' ) .
				'</a>'
			);
		}

		return $user;
	}

	public function execute_login_ajax_operations(){

		$user_login = sanitize_user( $_POST['username'] );
		$operation = (string) $_POST['operation'];
		$channel = (string) $_POST['channel'];

		if( is_email( $user_login ) ){
			$user_id = get_user_by('email', $user_login)->ID;
		}
		elseif ( ap_is_phone( $user_login )){
			$user_id = ap_channel_value_exists('phone', $user_login);
		}
		else{
			$user_id = username_exists( $user_login );
		}

		if( ! $user_id  ){
			wp_die(0);
		}

		if( ! ap_verify_nonce( $_POST['nonce'], $user_id . $operation ) ) {
			wp_send_json_error(__('invalid nonce', 'actions-pack'));
			wp_die(0);
		}

		$otp_attempt = (int) get_transient( $user_id . '_ap_otp_attempt');
		if ( $otp_attempt > 1){
			$expiry = get_option( '_transient_timeout_' .  $user_id . '_ap_otp_attempt' );
			$time_left = abs( time() - $expiry );
			$time_left = gmdate("i:s", $time_left );
			wp_send_json_error(sprintf( __('Too many failed attempts. Try after <span class="apCountDown">%s</span> minutes!', 'actions-pack'), $time_left ));
			wp_die(0);
		}

		switch ( $operation ){
			case 'notify_' . $channel :
				$this->notify( $user_id, $channel );
				break;
			case 'verify_otp' :
				$this->verify_otp( $user_id, $user_login );
				break;
		}
	}

	public function verify_otp( $user_id, $username ){
		$stored_otp = (int) get_user_meta( $user_id, 'ap_login_otp', true);
		$received_otp = (int) $_POST['otp'];

		if( $stored_otp === $received_otp ){
			wp_clear_auth_cookie();
			wp_set_current_user($user_id, $username);
			$remember = filter_var($_POST['remember'], FILTER_VALIDATE_BOOLEAN);
			wp_set_auth_cookie( $user_id, $remember );
			delete_user_meta( $user_id, 'ap_login_otp');
			wp_send_json_success(__('Verified', 'actions-pack'));
		}
		else{
			$otp_attempt = get_transient( $user_id . '_ap_otp_attempt');
			set_transient( $user_id . '_ap_otp_attempt', $otp_attempt+1, 1800 );
			wp_send_json_error(__('Invalid Code', 'actions-pack'));
		}
	}

	public function notify( $user_id, $channel ){

		$post_id = (int) $_POST['postId'];
		$form_id = (string) $_POST['formId'];
		$settings = $this->get_settings( $post_id, $form_id);
		$otp = get_user_meta( $user_id, 'ap_login_otp', true);

		if ( $channel === 'email'){
			if ( ! empty( $settings['ap_login_email_from'] ) && ! empty( $settings['ap_login_email_from_name'] ) && ! empty( $settings['ap_login_email_subject'] ) && ! empty( $settings['ap_login_email_message'] ) ) {
				$from         = $settings['ap_login_email_from'];
				$from_name    = $settings['ap_login_email_from_name'];
				$to           = get_userdata( $user_id )->user_email;
				$subject      = $settings['ap_login_email_subject'];
				$content_type = $settings['ap_login_email_content_type'];
				$message      = $settings['ap_login_email_message'];
				$message      = $this->replace_actions_pack_shortcode( $message, $otp );
				ap_send_mail( $from, $from_name, $to, $subject, $content_type, $message );
				wp_send_json_success( __( 'Email Sent ', 'actions-pack' ) );
			}
		}

		if ( $channel === 'sms'){
			if ($settings['ap_login_phone_credentials_source'] === 'default' ){
				// Data from SMS Action
				$sid = get_option('elementor_ap_sms_account_sid');
				$token = get_option('elementor_ap_sms_auth_token');
				$from = get_option('elementor_ap_sms_from_number');
			}
			elseif( !empty( $settings['ap_login_sms_account_sid'] ) && !empty( $settings['ap_login_sms_auth_token'] ) && !empty( $settings['ap_login_sms_from_number'] )){
				$sid = $settings['ap_login_sms_account_sid'];
				$token = $settings['ap_login_sms_auth_token'];
				$from = $settings['ap_login_sms_from_number'];
			}
			else{
				return;
			}
			$phone                    = get_user_meta($user_id, 'user_phone', true);
			$message                  = $settings['ap_login_phone_message'];
			$message                  = $this->replace_actions_pack_shortcode( $message, $otp );
			ap_send_sms( $sid, $token, $from, $phone, $message );
			wp_send_json_success( __( 'SMS Sent ', 'actions-pack' ) );
		}

	}

	public function replace_actions_pack_shortcode( $message, $otp ){
		return strtr( $message, [
			'[ap-login-otp]' => $otp,
		]);
	}

	public function generate_login_otp( $user_id, $otp_length ){
		$min = 1 . str_repeat( 0, $otp_length - 1);
		$max = str_repeat( 9, $otp_length);
		$unique_otp = wp_rand($min, $max);
		update_user_meta( $user_id, 'ap_login_otp', $unique_otp );
		return $unique_otp;
	}

	public function get_settings( $post_id, $form_id ){
		$elementor = \Elementor\Plugin::$instance;
		$document = $elementor->documents->get( $post_id );
		$form = \ElementorPro\Modules\Forms\Module::find_element_recursive( $document->get_elements_data(), $form_id );
		$widget = $elementor->elements_manager->create_element_instance( $form );
		return $widget->get_settings_for_display();
	}

	public function add_custom_controls( $element, $args ){
		//https://github.com/elementor/elementor/issues/6499
		$element->start_controls_section(
			'ap_login_otp_style',
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
								'value' => 'login'
							],
							[
								'relation' => 'or',
								'terms' =>
									[
										[
											'name' => 'ap_login_via',
											'operator' => '==',
											'value' => 'password-otp'
										],
										[
											'name' => 'ap_login_via',
											'operator' => '==',
											'value' => 'otp'
										]
									]
							]
						]
				],
			]
		);
		$element->add_control(
			'ap_login_otp_container',
			[
				'label' => __( 'Container', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);
		$element->add_control(
			'ap_login_otp_container_position',
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
			'ap_login_otp_container_margin',
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
			'ap_login_otp_container_padding',
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
				'name' => 'ap_login_otp_container_background',
				'label' => __( 'Container Background', 'actions-pack' ),
				'types' => [ 'classic', 'gradient', 'video' ],
				'selector' => '{{WRAPPER}} .ap-otp-container',
			]
		);

		$element->add_control(
			'ap_login_otp_box',
			[
				'label' => __( 'Box', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);
		$element->add_control(
			'ap_login_otp_box_width',
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
			'ap_login_otp_box_height',
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
				'name' => 'ap_login_otp_box_background',
				'label' => __( 'Box Background', 'actions-pack' ),
				'types' => [ 'classic', 'gradient', 'video' ],
				'selector' => '{{WRAPPER}} .ap-otp-box',
			]
		);
		$element->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'ap_login_otp_box_border',
				'label' => __( 'Border', 'actions-pack' ),
				'selector' => '{{WRAPPER}} .ap-otp-box',
			]
		);

		$element->add_control(
			'ap_login_otp_box_border_radius',
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
				'name' => 'ap_login_otp_box_shadow',
				'label' => __( 'Box Shadow', 'actions-pack' ),
				'selector' => '{{WRAPPER}} .ap-otp-box',
			]
		);

		$element->add_control(
			'ap_login_otp_text',
			[
				'label' => __( 'Text', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$element->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'ap_login_otp_heading',
				'label' => __( 'Typography', 'actions-pack' ),
				'scheme' => \Elementor\Core\Schemes\Typography::TYPOGRAPHY_1,
				'selector' => '{{WRAPPER}} .ap-otp-heading p',
			]
		);

		$element->add_control(
			'ap_login_otp_heading_color',
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
			'ap_login_otp_inputs',
			[
				'label' => __( 'Individual Input', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$element->add_control(
			'ap_login_otp_individual_input',
			[
				'label' => __('Size', 'actions-pack'),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'selectors' => [
					'{{WRAPPER}} .ap-otp-inputs input' => 'width: {{SIZE}}px; height: {{SIZE}}px;',
				]
			]
		);

		$element->add_control(
			'ap_login_otp_individual_input_focus_color',
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

	// @toDo Prevent printing multiple times
	public function render_content( $content, $widget ){
		if ( 'form' === $widget->get_name() ) {

			$settings = $widget->get_settings();

			if( in_array( $this->get_name(), $settings['submit_actions'] ) ){
				wp_enqueue_script('ap-user');
				wp_enqueue_style('ap-user');
				if ( is_user_logged_in() && ! \Elementor\Plugin::$instance->editor->is_edit_mode() ){
					if( AP_IS_SILVER && $settings['ap_login_logout_link'] === 'yes'){
					    $user = wp_get_current_user();
                        $user_data = get_userdata( $user->ID );
					    $redirect_to = $settings['ap_login_logout_redirect'];
                        $content = strtr( $settings['ap_login_logout_template'], [
                            '[ap-username]' => $user->user_login,
                            '[ap-firstname]' => $user_data->first_name,
                            '[ap-lastname]' => $user_data->last_name,
                            '[ap-name]' => $user_data->first_name .' '. $user_data->last_name,
                            '[ap-email]' => $user->user_email,
                            '[ap-logout-url]' => wp_logout_url( $redirect_to )
                        ]);
					}else{
                        $content = '';
                    }
				}
			}
		}
		return $content;
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
		return 'login';
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
		return __( 'Login', 'actions-pack' );
	}

	public function get_role_names() {

		global $wp_roles;

		if ( ! isset( $wp_roles ) )
			$wp_roles = new WP_Roles();

		return $wp_roles->get_names();
	}

	/**
	 * Login Settings Section
	 *
	 * Registers the Action controls
	 *
	 * @access public
	 * @param \Elementor\Widget_Base $widget
	 */
	public function register_settings_section( $widget ) {

		$widget->start_controls_section(
			'ap_section_login',
			[
				'label' => __( 'Login', 'actions-pack' ),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		$widget->add_control(
			'ap_login_via',
			[
				'label' => __( 'Login Via <span class="ap-required">*</span>', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'password' => 'ID & Password',
					'otp' => 'ID & OTP',
					'password-otp' => 'ID & Password & OTP'
				],
				'default' => 'password',
				'classes' => 'ap-upgrade',
				'description' => ( AP_IS_GOLD ? '' : AP_UPGRADE_TO_GOLD )
			]
		);

		//******************************************** User ID *********************************************************
		$widget->add_control(
			'ap_login_user_popover',
			[
				'label' => __( 'User ID <span class="ap-required">*</span>', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
				'label_off' => __( 'Default', 'actions-pack' ),
				'label_on' => __( 'Custom', 'actions-pack' ),
				'return_value' => 'yes'
			]
		);
		$widget->start_popover();
		$widget->add_control(
			'ap_login_user',
			[
				'label' => __( 'Field', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'show_label' => true,
				'options' => [],
				'description' => __('You must create a Username, Email or Phone Form field above and map that field here.', 'actions-pack')
			]
		);
		$widget->end_popover();

		// **************************************************** Password ***********************************************
		$widget->add_control(
			'ap_login_password_popover',
			[
				'label' => __( 'Password <span class="ap-required">*</span>', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
				'label_off' => __( 'Default', 'actions-pack' ),
				'label_on' => __( 'Custom', 'actions-pack' ),
				'return_value' => 'yes',
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'name' => 'ap_login_via',
							'operator' => '==',
							'value' => 'password'
						],
						[
							'name' => 'ap_login_via',
							'operator' => '==',
							'value' => 'password-otp'
						]
					]
				]
			]
		);
		$widget->start_popover();
		$widget->add_control(
			'ap_login_password',
			[
				'label' => __( 'Field', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'show_label' => true,
				'options' => [],
			]
		);
		$widget->end_popover();

		//**************************************************** OTP *****************************************************
		$widget->add_control(
			'ap_login_otp_popover',
			[
				'label' => __( 'OTP <span class="ap-required">*</span>', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
				'label_off' => __( 'Default', 'actions-pack' ),
				'label_on' => __( 'Custom', 'actions-pack' ),
				'return_value' => 'yes',
				'condition' => [
					'ap_login_via!' => 'password'
				]
			]
		);
		$widget->start_popover();
		$widget->add_control(
			'ap_login_otp_length',
			[
				'label' => __( 'OTP Length', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'show_label' => true,
				'min' => 2,
				'max' => 10,
				'step' => 1,
				'default' => 4,
			]
		);

		$widget->add_control(
			'ap_login_otp_send_via',
			[
				'label' => __( 'OTP Send Via', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::SELECT2,
				'label_block' => TRUE,
				'multiple' => true,
				'options' => [
					'email' => 'Email',
					'sms' => 'SMS'
				],
				'default' => [ 'email']
			]
		);

		//******************************************************* Email ************************************************
		$widget->add_control(
			'ap_login_email_settings',
			[
				'label' => __( 'Email Settings', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'email' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_email_from_name',
			[
				'label' => __( 'From Name', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => get_bloginfo( 'name' ),
				'placeholder' => get_bloginfo( 'name' ),
				'show_label' => true,
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'email' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_email_from',
			[
				'label' => __( 'From Email', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => get_bloginfo('admin_email'),
				'placeholder' => get_bloginfo('admin_email'),
				'show_label' => true,
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'email' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_email_subject',
			[
				'label' => __( 'Subject', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __('Your One Time Passcode', 'actions-pack'),
				'placeholder' => __('Thank you for registering', 'actions-pack'),
				'show_label' => true,
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'email' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_email_content_type',
			[
				'label' => __( 'Send As', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'plain',
				'render_type' => 'none',
				'options' => [
					'html' => __( 'HTML', 'actions-pack' ),
					'plain' => __( 'Plain', 'actions-pack' )
				],
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'email' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_email_message',
			[
				'label' => __( 'Message', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __('Your one time password is [ap-login-otp].', 'actions-pack'),
				'show_label' => true,
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'email' ] ] ]
			]
		);

		//******************************************************* SMS ************************************************
		$widget->add_control(
			'ap_login_sms_settings',
			[
				'label' => __( 'SMS Settings', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'sms' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_phone_credentials_source',
			[
				'label' => __( 'API Source', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'label_block' => false,
				'options' =>[
					'default' => 'Default',
					'custom' => 'Custom'
				],
				'default' => 'default',
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'sms' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_phone_credentials_notice',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw' => sprintf('%s <a style="color: #0b76ef" href="%s" target="_blank">%s</a>. %s',__('To use default credentials, make sure you have already set the credentials', 'actions-pack'),admin_url('admin.php?page=elementor#tab-integrations'), __('here', 'actions-pack'), __('You can use this field to set a custom credential for current form only', 'actions-pack')),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
				'condition' => [
					'ap_login_phone_credentials_source' => 'default'
				],
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'sms' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_phone_custom_source_notice',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw' => sprintf('%s <a href="%s" target="_blank" style="color: #0b76ef"> %s </a> %s', __('Click', 'actions-pack'), 'https://twilio.com/referral/nWJHpb',__('here', 'actions-pack'), __('to get your Twilio Account SID, Auth Token and Phone number', 'actions-pack') ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
				'condition' => [
					'ap_login_phone_credentials_source' => 'custom'
				],
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'sms' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_sms_account_sid',
			[
				'label' => __( 'Account SID', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'label_block' => false,
				'condition' => [
					'ap_login_phone_credentials_source' => 'custom'
				],
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'sms' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_sms_auth_token',
			[
				'label' => __( 'Auth Token', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'label_block' => false,
				'condition' => [
					'ap_login_phone_credentials_source' => 'custom'
				],
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'sms' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_sms_from_number',
			[
				'label' => __( 'From Number', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '+919876543210',
				'show_label' => true,
				'condition' =>[
					'ap_login_phone_credentials_source' => 'custom'
				],
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'sms' ] ] ]
			]
		);
		$widget->add_control(
			'ap_login_phone_message',
			[
				'label' => __( 'Message', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => sprintf( __( 'Your one time password is %s.', 'actions-pack' ),'[ap-login-otp]'),
				'placeholder' => sprintf( __( 'Your one time password is %s.', 'actions-pack' ),'[ap-login-otp]'),
				'show_label' => true,
				'conditions' => [ 'terms' => [ [ 'name' => 'ap_login_otp_send_via', 'operator' => 'contains', 'value' => 'sms' ] ] ]
			]
		);

		$widget->end_popover();

		//**************************************************************** Remember Me *********************************
		$widget->add_control(
			'ap_login_remember_me_popover',
			[
				'label' => __( 'Remember Me', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
				'label_off' => __( 'Default', 'actions-pack' ),
				'label_on' => __( 'Custom', 'actions-pack' ),
				'return_value' => 'yes',
			]
		);
		$widget->start_popover();
		$widget->add_control(
			'ap_login_remember_me',
			[
				'label' => __( 'Field', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'show_label' => true,
				'options' => [],
				'classes' => 'ap-upgrade',
				'description' => __( 'You must create a form field of type <strong>Acceptance</strong> which will be used as Remember me checkbox', 'actions-pack' ) . ( AP_IS_SILVER ? '' : AP_UPGRADE_TO_SILVER )
			]
		);
		$widget->add_control(
			'ap_login_remember_me_duration',
			[
				'label' => __( 'Duration (In Days)', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'show_label' => true,
				'min' => 1,
				'max' => 365,
				'step' => 1,
				'default' => 14,
				'condition' =>[
					'ap_login_remember_me!' => ''
				],
				'classes' => 'ap-upgrade',
				'description' => 'By default if a user doesn\'t click on remember me option, the authentication cookie 
				 is stored until the browser is closed. If the user click on remember me checkbox, default duration is
				 14 days long even if the browser is closed.' . ( AP_IS_GOLD ? '' : AP_UPGRADE_TO_GOLD ),
			]
		);
		$widget->end_popover();
		//***************************************************************** Redirection **************************
		$widget->add_control(
			'ap_login_redirection',
			[
				'label' => __( 'Redirection', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
				'label_off' => __( 'Default', 'actions-pack' ),
				'label_on' => __( 'Custom', 'actions-pack' ),
				'return_value' => 'yes',
			]
		);
		$widget->start_popover();
		$widget->add_control(
			'ap_login_role_wise_redirection',
			[
				'label' => __( 'Redirect After Login (role wise)', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);
		foreach ( $this->get_role_names() as $role_key => $role_name) {
			$widget->add_control(
				'ap_redirect_url_'.$role_key,
				[
					'label' => $role_name,
					'type' => Controls_Manager::TEXT,
					'placeholder' => __( 'https://your-link.com', 'actions-pack' ),
					'dynamic' => [
						'active' => true,
						'categories' => [
							TagsModule::POST_META_CATEGORY,
							TagsModule::TEXT_CATEGORY,
							TagsModule::URL_CATEGORY,
						],
					],
					'label_block' => true,
					'render_type' => 'none',
					'title' => __('Leave empty to reload the current page.', 'actions-pack'),
				]
			);
		}
		$widget->end_popover();

		$widget->add_control(
			'ap_login_additional_popover',
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
			'ap_login_logout_link',
			[
				'label' => __( 'Display Logout Link', 'actions-pack' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
				'label_off' => __( 'Hide', 'actions-pack' ),
				'label_on' => __( 'Show', 'actions-pack' ),
				'description' => __('A logout link will be visible in place of the Login form.', 'actions-pack')
			]
		);
        $widget->add_control(
            'ap_login_logout_redirect',
            [
                'label' => __( 'URL to redirect users after Logout', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'label_block' => true,
                'placeholder'=> __('https://yoursite.com/login', 'actions-pack'),
                'classes' => 'ap-upgrade',
                'description' => ( AP_IS_SILVER ? '' : AP_UPGRADE_TO_SILVER ),
                'title' => __('Leave empty to reload the current page.', 'actions-pack'),
                'condition' =>[
                    'ap_login_logout_link' => 'yes'
                ]
            ]
        );
        $widget->add_control(
            'ap_login_logout_template',
            [
                'label' => __( 'Logout Template', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'label_block' => true,
                'default' => 'You are Logged in as [ap-email]. <a href="[ap-logout-url]">Logout</a>',
                'condition' =>[
                    'ap_login_logout_link' => 'yes'
                ],
                'description' => __('Available shortcodes are [ap-username], [ap-firstname], [ap-lastname], [ap-name], [ap-email], [ap-logout-url]', 'actions-pack')
            ]
        );
        $emergency_login_url = add_query_arg( 'unlock', get_option("actions-pack-emergency-login-url"), wp_login_url());
        $widget->add_control(
            'ap_default_login_page_redirection_url',
            [
                'label' => __( 'URL to redirect default Login page', 'actions-pack' ),
                'label_block' => true,
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('https://yoursite.com/login', 'actions-pack'),
                'classes' => 'ap-upgrade',
                'description' => __('<span style="color: orangered">WARNING</span> : It redirects the default <strong>wp-login.php</strong> page to the new login page that you entered above. So before changing it, check if your new Login page works perfectly. In case you are locked out and not able to access your login page you can use this URL 👉 <a href="'.$emergency_login_url.'" target="_blank">'.$emergency_login_url.'</a> to access in emergency. Keep it in a safe place.','actions-pack') . ( AP_IS_GOLD ? '' : AP_UPGRADE_TO_GOLD )
            ]
        );
		$widget->end_popover();
		$widget->end_controls_section();

	}

	/**
	 * Run
	 *
	 * Runs the action after submit
	 *
	 * @access public
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 *
	 * @return mixed
	 */
	public function run( $record, $ajax_handler )
	{

		// Don't proceed if user already logged in
		if (is_user_logged_in()) {
			$ajax_handler->add_error_message(__('You are already logged-in','actions-pack'));
			$ajax_handler->is_success = false;
			return;
		}

		$settings = $record->get('form_settings');

		//  Make sure that there is a username/email/username or email field
		if (empty($settings['ap_login_user'])) {
			return;
		}

		if (empty($settings['ap_login_via'])) {
			return;
		}

		// Normalize the Form Data
		$form_data = [];
		$raw_fields = $record->get('fields');
		foreach ($raw_fields as $id => $field) {
			$form_data[$id] = $field['value'];
		}

		// Remember
		$remember = false;
		if( AP_IS_SILVER && !empty($form_data[$settings['ap_login_remember_me']]) ){
			$remember = true;
		}

		// user_login
		if ( empty( $form_data[$settings['ap_login_user']] ) ) {
			$ajax_handler->add_error_message(__('Enter your login id', 'actions-pack'));
			return;
		}
		$user_login = $form_data[$settings['ap_login_user']];
		$user_login = sanitize_user( $user_login );

		switch ( $settings['ap_login_via'] ){

			case 'password' :
				if ( empty( $form_data[ $settings['ap_login_password'] ] ) ) {
					$ajax_handler->add_error_message( __( 'Enter your password', 'actions-pack' ) );
					return;
				}
				$user = wp_signon([
					'user_login' => $user_login,
					'remember' => $remember,
					'user_password' => $form_data[$settings['ap_login_password']]
				]);
				if (is_wp_error($user)){
					switch($user->get_error_code()){
						case 'incorrect_password':
						case 'invalid_username':
							$ajax_handler->add_error_message(__('You entered wrong credentials. Try again!', 'actions-pack'));
							break;
						default:
							$ajax_handler->add_error_message($user->get_error_message());
					}
					return;
				}
				wp_set_current_user($user->ID);

				// Redirect
				$redirect_to = $settings['ap_redirect_url_'.array_shift($user->roles)];
				$redirect_to = $record->replace_setting_shortcodes( $redirect_to, true );
				if ( ! empty( $redirect_to ) && filter_var( $redirect_to, FILTER_VALIDATE_URL ) ) {
					$ajax_handler->add_response_data('redirect_url', $redirect_to );
				}else{
					$ajax_handler->add_response_data('redirect_url', ap_get_referrer() );
				}
				break;

			case 'otp' :
				if( ! AP_IS_GOLD ){
					$ajax_handler->add_error_message(__('Login through OTP is only available in Gold subscription.', 'actions-pack'));
					return;
				}

				if( is_email( $user_login ) ){
					$user_id = get_user_by('email', $user_login)->ID;
				}
				elseif ( ap_is_phone( $user_login )){
					$user_id = ap_channel_value_exists('phone', $user_login);
				}
				else{
					$user_id = username_exists( $user_login );
				}

				if( $user_id ){
					$otp_length = $settings['ap_login_otp_length'];
					$this->generate_login_otp($user_id, $otp_length);
					$ajax_data = [];
					$ajax_data['otpLength'] = (int) $settings['ap_login_otp_length'];
					$ajax_data['username'] = $user_login;
					$ajax_data['nonce'] = ap_create_nonce($user_id . 'verify_otp');
					$ajax_data['formId'] = $settings['id'];
					$ajax_data['postId'] = (int) $_POST['post_id'];
					$ajax_data['remember'] = $remember;

					$channels = $settings['ap_login_otp_send_via'];
					foreach( $channels as $channel){
						$ajax_data['notifyChannels'][$channel]['nonce'] = ap_create_nonce($user_id . 'notify_'.$channel);
					}

					// Redirect
					$redirect_to = $settings['ap_redirect_url_'.array_shift(get_userdata($user_id)->roles)];
					$redirect_to = $record->replace_setting_shortcodes( $redirect_to, true );
					if ( ! empty( $redirect_to ) && filter_var( $redirect_to, FILTER_VALIDATE_URL ) ) {
						$ajax_data['redirectTo'] = $redirect_to;
					}else{
						$ajax_data['redirectTo'] = ap_get_referrer();
					}

					$ajax_handler->add_response_data('apLoginViaOtp', $ajax_data);
				}
				else{
					$ajax_handler->add_error_message(__('User doesn\'t exit. Try again!', 'actions-pack'));
					return;
				}
				break;

			case 'password-otp':
				if( ! AP_IS_GOLD ){
					$ajax_handler->add_error_message(__('Login through Password and OTP is only available in Gold subscription.', 'actions-pack'));
					return;
				}

				if ( empty( $form_data[$settings['ap_login_password']] ) ) {
					$ajax_handler->add_error_message(__('Enter your password', 'actions-pack'));
					return;
				}

				$user_password = $form_data[ $settings['ap_login_password'] ];

				$user = wp_authenticate($user_login, $user_password);

				if ( is_wp_error( $user ) ) {
					switch($user->get_error_code()){
						case 'incorrect_password':
						case 'invalid_username':
							$ajax_handler->add_error_message(__('You entered wrong credentials. Try again!', 'actions-pack'));
							break;
						default:
							$ajax_handler->add_error_message($user->get_error_message());
					}
					return;
				}

				$user_id = $user->ID;
				$otp_length = $settings['ap_login_otp_length'];
				$this->generate_login_otp($user_id, $otp_length);
				$ajax_data = [];
				$ajax_data['otpLength'] = (int) $settings['ap_login_otp_length'];
				$ajax_data['username'] = $user_login;
				$ajax_data['nonce'] = ap_create_nonce($user_id . 'verify_otp');
				$ajax_data['formId'] = $settings['id'];
				$ajax_data['postId'] = (int) $_POST['post_id'];
				$ajax_data['remember'] = $remember;

				$channels = $settings['ap_login_otp_send_via'];
				foreach( $channels as $channel){
					$ajax_data['notifyChannels'][$channel]['nonce'] = ap_create_nonce($user_id . 'notify_'.$channel);
				}

				// Redirect
				$redirect_to = $settings['ap_redirect_url_'.array_shift(get_userdata($user_id)->roles)];
				$redirect_to = $record->replace_setting_shortcodes( $redirect_to, true );
				if ( ! empty( $redirect_to ) && filter_var( $redirect_to, FILTER_VALIDATE_URL ) ) {
					$ajax_data['redirectTo'] = $redirect_to;
				}else{
					$ajax_data['redirectTo'] = ap_get_referrer();
				}

				$ajax_handler->add_response_data('apLoginViaOtp', $ajax_data);
				break;
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
			$element['ap_login_user'],
			$element['ap_login_password']
		);
	}
}