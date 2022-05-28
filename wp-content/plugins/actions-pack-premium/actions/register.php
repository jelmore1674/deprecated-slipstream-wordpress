<?php

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Action_Register extends \ElementorPro\Modules\Forms\Classes\Action_Base
{

    public $metas, $settings;

    private static $options = null;

    public function __construct(){

        // Execute both for Ajax and Page request
	    add_action( 'elementor/element/form/section_field_style/after_section_end', [ $this, 'add_custom_controls'], 10, 2);

	    if( wp_doing_ajax() ){ // Ajax request
		    add_action( 'wp_ajax_nopriv_ap_verify_via_otp', [ $this, 'verify_via_otp' ] );
            add_action ( 'wp_ajax_nopriv_ap_execute_user_ajax_operations', [ $this, 'execute_ajax_operations' ] );
            add_action ( 'wp_ajax_ap_execute_user_ajax_operations', [ $this, 'execute_ajax_operations' ] );
	    }
	    else{ // Page request

	        $this->verify_via_link();

		    $this->register_assets();

		    add_action( 'elementor/widget/render_content',[$this, 'render_content'], 10, 2);

		    if ( is_admin() ){
			    if ( current_user_can('list_users') ){
				    $this->display_meta_columns_in_user_table();
			    }

			    if( current_user_can( 'edit_users') ){
				    $this->add_custom_bulk_actions_to_user_table();
                    $this->display_meta_fields_on_user_profile();
			    }
		    }
	    }
    }

//************************************************************** METHODS ( START )***************************************************************************//
    public function register_assets(){
	    // user.js
	    wp_register_script( 'ap-user',  AP_PLUGIN_DIR_URL .'assets/js/user.js', [ 'jquery', 'wp-i18n' ], false, true);
	    wp_localize_script( 'ap-user', 'apUser', [ 'ajaxUrl' => admin_url('admin-ajax.php'), ]);
	    wp_set_script_translations("ap-user", "actions-pack", AP_PLUGIN_DIR_PATH . 'languages');
	    // user.css
	    wp_register_style('ap-user', AP_PLUGIN_DIR_URL .'assets/css/user.css');
    }

    public function get_settings( $post_id, $form_id ){
		$elementor = \Elementor\Plugin::$instance;
		$document = $elementor->documents->get( $post_id );
		$form = \ElementorPro\Modules\Forms\Module::find_element_recursive( $document->get_elements_data(), $form_id );
		$widget = $elementor->elements_manager->create_element_instance( $form );
		return $widget->get_settings_for_display();
    }

	public function generate_channel_verification_key( $user_id, $channel, $verification_via, $otp_length = '',  $verification_redirection= ''){

		if( $verification_via === 'link'){
			$unique_string = wp_generate_password( 20 );
			update_user_meta( $user_id, $channel.'_verification_string', $unique_string );
			$token = base64_encode( serialize([ 'unique_string' => $unique_string, 'username' => get_userdata( $user_id)->user_login ]));
			return add_query_arg( [ 'action' => 'verify', 'channel' => $channel, 'token' => $token ], $verification_redirection);
		}
		else{ // OTP
			$min = 1 . str_repeat( 0, $otp_length - 1);
			$max = str_repeat( 9, $otp_length);
			$unique_otp = wp_rand($min, $max);
			update_user_meta( $user_id, $channel.'_verification_otp', $unique_otp );
			return $unique_otp;
		}

	}

	public function replace_actions_pack_short_codes ( $message, $username, $password, $is_verification, $channel, $user_id,  $verification_via, $otp_length,  $verification_redirection=''){
    	return strtr( $message, [
		    '[ap-autogen-username]' => $username,
		    '[ap-autogen-password]' => $password,
			'[ap-verification]' => $is_verification ? $this->generate_channel_verification_key($user_id, $channel, $verification_via, $otp_length,  $verification_redirection) : 'XXXXXX',
		]);
	}

	public function replace_form_data_short_codes( $message, $form_data) {

		return preg_replace_callback( '/(\[field[^]]*id="(\w+)"[^]]*\])/',
			function( $matches ) use ($form_data) {
				$value = '';

				if ( isset( $form_data[ $matches[2]] ) ) {
					$value = $form_data[ $matches[2]];
				}
				return $value;
			}, $message );
	}

	public function execute_ajax_operations(){

        // @toDo More sanitize
        $username = sanitize_user( $_POST['username'] );
		$operation = (string) $_POST['operation'];
		$channel = (string) $_POST['channel'];
		$otp = (int) $_POST['otp'];
		$channelValue = (string) $_POST['channelValue'];

        if( ! $user_id = username_exists( $username )){
	        wp_die(0);
        }

		if( ! ap_verify_nonce( $_POST['nonce'], $user_id . $operation ) ) {
		    wp_send_json_error('invalid nonce');
			wp_die(0);
		}

		switch ( $operation ){
			case 'update_fields' :
				$this->update_fields( $user_id );
				break;
			case 'notify_' . $channel :
				$this->notify( $user_id, $channel );
				break;
			case 'verify_' . $channel :
				$this->verify_via_otp( $user_id, $channel, $otp );
				break;
			case 'update_' . $channel :
				$this->update_channel( $user_id, $channel, $channelValue);
				break;
		}

	}

	public function update_channel( $user_id, $channel, $channelValue){
		if( get_user_meta($user_id, 'is_'.$channel.'_verified', true) === 'yes' ) {
			wp_send_json_error('Already Verified');
		}

		$ap_data = get_user_meta( $user_id, 'ap_data', true);
		$counter = $ap_data['channel_counter'];

		// @toDo Limit individual channel wise
		if ( $counter < 6 ) {
			if($channel === 'email'){
				if ( is_email( $channelValue ) ){
					$user_id = wp_update_user([ 'ID' => $user_id, 'user_email' => $channelValue ]);
					if( is_wp_error($user_id) ){
						wp_send_json_error($user_id->get_error_message());
					}
				}
				else{
					wp_send_json_error(__('Invalid Email Address', 'actions-pack'));
				}
			}
			else{
				if( ap_is_phone( $channelValue ) ){
					if( ! ap_channel_value_exists( $channel, $channelValue )){
						update_user_meta( $user_id, 'user_' . $channel, $channelValue);
					}else{
						wp_send_json_error(sprintf( __('Sorry, that %s is already used!', 'actions-pack'), $channel));
					}
				}
				else{
					wp_send_json_error(__('Invalid Phone Number', 'actions-pack'));
				}
			}
		}
		else{
			wp_send_json_error(__('Limit Exceeded', 'actions-pack'));
        }

		$this->notify( $user_id, $channel );
		$ap_data['channel_counter'] = $counter + 1;
		update_user_meta( $user_id, 'ap_data', $ap_data );
		wp_send_json_success( __('Updated', 'actions-pack') );
	}

	public function update_fields( $user_id ){

		$ap_data = get_user_meta( $user_id, 'ap_data', true);

		$counter = $ap_data['update_counter'];
		if ( $counter < 1 ){
			$post_id = $ap_data['post_id'];
			$form_id = $ap_data['form_id'];
			$form_data = $ap_data['form_data'];
			$settings = $this->get_settings( $post_id, $form_id);

			$additional_fields = $settings['ap_user_additional_fields'];

			foreach ( $additional_fields as $item ) {
				if ( (!empty($item['ap_register_additional_user_field']) || !empty($item['ap_register_additional_user_field_custom']) ) && !empty($item['ap_register_additional_form_field']) && !empty($form_data[$item['ap_register_additional_form_field']])){

					if($item['ap_register_additional_user_field'] === 'custom'){
						$user_meta = $item['ap_register_additional_user_field_custom'];
					}
					else{
						$user_meta = $item['ap_register_additional_user_field'];
					}

					$user_data = $form_data[$item['ap_register_additional_form_field']];

					if($user_meta === 'name'){
						// Divide Name field to first_name and last_name
						$first_name = strtok($user_data, ' ');
						$last_name = strstr($user_data, ' ');
						update_user_meta( $user_id, 'first_name', $first_name );
						update_user_meta( $user_id, 'last_name', $last_name );
					}
					elseif ( strpos($user_meta, "dokan") !== false ){
                            $data = get_user_meta($user_id, 'dokan_profile_settings', true);
                            if( !empty($data) ){
                                $data = ap_update_array_value_by_string_pattern($data, $user_meta, $user_data);
                                return update_user_meta( $user_id, 'dokan_profile_settings', $data );
                            }
                        }
					else{
						update_user_meta( $user_id, $user_meta, $user_data );
					}

				}
			}

			$ap_data['update_counter'] = $counter + 1;
			update_user_meta( $user_id, 'ap_data', $ap_data );
			wp_send_json_success( __('Updated', 'actions-pack') );
		}
		else{
			wp_send_json_error('Already Updated');
        }

    }

    public function resend_channel_verification_from_dashboard( $user_id, $channel ){

        $ap_data = get_user_meta( $user_id, 'ap_data', true);
        if(empty($ap_data)){
            return __('Something went wrong. Please try again!', 'actions-pack');
        }
        $post_id = $ap_data['post_id'];
        $form_id = $ap_data['form_id'];
        $form_data = $ap_data['form_data'];
        $username = $ap_data['username'];
        $password = $ap_data['password'];
        $settings = $this->get_settings( $post_id, $form_id);

        if ( $channel === 'email'){

                if ( ! empty( $settings['ap_register_email_from'] ) && ! empty( $settings['ap_register_email_from_name'] ) && ! empty( $settings['ap_register_email_subject'] ) && ! empty( $settings['ap_register_email_message'] ) ) {
                    $from         = $settings['ap_register_email_from'];
                    $from_name    = $settings['ap_register_email_from_name'];
                    $to           = get_userdata( $user_id )->user_email;
                    $subject      = $settings['ap_register_email_subject'];
                    $content_type = $settings['ap_register_email_content_type'];

                    $message                  = $settings['ap_register_email_message'];
                    $message                  = $this->replace_form_data_short_codes( $message, $form_data );
                    $verification_redirection = ! empty( $settings['ap_register_email_verification_redirection'] ) ? $settings['ap_register_email_verification_redirection'] : get_home_url();
                    $verification_via         = $settings['ap_register_email_verification_via'];
                    $otp_length               = $settings['ap_register_email_otp_length'];
                    $is_verification          = AP_IS_GOLD ? $settings['ap_register_enable_email_verification'] : false;
                    $message                  = $this->replace_actions_pack_short_codes( $message, $username, $password, $is_verification, 'email', $user_id, $verification_via, $otp_length, $verification_redirection );
                    return ap_send_mail( $from, $from_name, $to, $subject, $content_type, $message );
                }
            }


        if( $channel === 'phone'){
                if ($settings['ap_register_phone_credentials_source'] === 'default' ){
                    // Data from SMS Action
                    $sid = get_option('elementor_ap_sms_account_sid');
                    $token = get_option('elementor_ap_sms_auth_token');
                    $from = get_option('elementor_ap_sms_from_number');
                }
                elseif( !empty( $settings['ap_register_sms_account_sid'] ) && !empty( $settings['ap_register_sms_auth_token'] ) && !empty( $settings['ap_register_sms_from_number'] )){
                    $sid = $settings['ap_register_sms_account_sid'];
                    $token = $settings['ap_register_sms_auth_token'];
                    $from = $settings['ap_register_sms_from_number'];
                }
                else{
                    return false;
                }
                $phone = get_user_meta($user_id, 'user_phone', true);
                $message = $settings['ap_register_phone_message'];
                $message = $this->replace_form_data_short_codes( $message, $form_data);
                $verification_redirection = !empty( $settings['ap_register_phone_verification_redirection'] ) ? $settings['ap_register_phone_verification_redirection'] : get_home_url();
                $verification_via = $settings['ap_register_phone_verification_via'];
                $otp_length = $settings['ap_register_phone_otp_length'];
                $is_verification = AP_IS_GOLD ? $settings['ap_register_enable_phone_verification'] : false;
                $message = $this->replace_actions_pack_short_codes( $message, $username, $password, $is_verification, 'phone', $user_id,  $verification_via, $otp_length,  $verification_redirection);
                return ap_send_sms( $sid, $token, $from, $phone, $message );
        }

    }

    public function notify( $user_id, $channel ){

	    if( get_user_meta($user_id, 'is_'.$channel.'_verified', true) === 'yes' ) {
		    wp_send_json_error('Already Verified');
	    }

	    $ap_data = get_user_meta( $user_id, 'ap_data', true);
	    if(empty($ap_data)){
            wp_send_json_error(__('Something went wrong. Please try again!', 'actions-pack'));
        }
	    $post_id = $ap_data['post_id'];
	    $form_id = $ap_data['form_id'];
	    $form_data = $ap_data['form_data'];
	    $username = $ap_data['username'];
	    $password = $ap_data['password'];
	    $settings = $this->get_settings( $post_id, $form_id);

	    if ( $channel === 'email'){
		    $counter = $ap_data['email_counter'];
		    if ( $counter < 3 ) {
			    if ( ! empty( $settings['ap_register_email_from'] ) && ! empty( $settings['ap_register_email_from_name'] ) && ! empty( $settings['ap_register_email_subject'] ) && ! empty( $settings['ap_register_email_message'] ) ) {
				    $from         = $settings['ap_register_email_from'];
				    $from_name    = $settings['ap_register_email_from_name'];
				    $to           = get_userdata( $user_id )->user_email;
				    $subject      = $settings['ap_register_email_subject'];
				    $content_type = $settings['ap_register_email_content_type'];

				    $message                  = $settings['ap_register_email_message'];
				    $message                  = $this->replace_form_data_short_codes( $message, $form_data );
				    $verification_redirection = ! empty( $settings['ap_register_email_verification_redirection'] ) ? $settings['ap_register_email_verification_redirection'] : ap_get_referrer();
				    $verification_via         = $settings['ap_register_email_verification_via'];
				    $otp_length               = $settings['ap_register_email_otp_length'];
				    $is_verification          = AP_IS_GOLD ? $settings['ap_register_enable_email_verification'] : false;
				    $message                  = $this->replace_actions_pack_short_codes( $message, $username, $password, $is_verification, 'email', $user_id, $verification_via, $otp_length, $verification_redirection );
				    ap_send_mail( $from, $from_name, $to, $subject, $content_type, $message );

				    $counter ++;
				    $ap_data['email_counter'] = $counter;
				    update_user_meta( $user_id, 'ap_data', $ap_data );
				    wp_send_json_success( __( 'Email Sent ', 'actions-pack' ) . '(' . $counter . '/3)' );
			    }else{
                    wp_send_json_error(__('Email Notification not configured properly', 'actions-pack'));
                }
		    }
		    else{
			    wp_send_json_error(__('Limit Exceeded', 'actions-pack'));
		    }
	    }

        if( $channel === 'phone'){
            $counter = $ap_data['phone_counter'];
            if ( $counter < 3 ) {
                if ($settings['ap_register_phone_credentials_source'] === 'default' ){
                    // Data from SMS Action
                    $sid = get_option('elementor_ap_sms_account_sid');
                    $token = get_option('elementor_ap_sms_auth_token');
                    $from = get_option('elementor_ap_sms_from_number');
                }
                elseif( !empty( $settings['ap_register_sms_account_sid'] ) && !empty( $settings['ap_register_sms_auth_token'] ) && !empty( $settings['ap_register_sms_from_number'] )){
                    $sid = $settings['ap_register_sms_account_sid'];
                    $token = $settings['ap_register_sms_auth_token'];
                    $from = $settings['ap_register_sms_from_number'];
                }
                else{
                    return;
                }
                $phone = get_user_meta($user_id, 'user_phone', true);
                $message = $settings['ap_register_phone_message'];
                $message = $this->replace_form_data_short_codes( $message, $form_data);
                $verification_redirection = !empty( $settings['ap_register_phone_verification_redirection'] ) ? $settings['ap_register_phone_verification_redirection'] : ap_get_referrer();
                $verification_via = $settings['ap_register_phone_verification_via'];
                $otp_length = $settings['ap_register_phone_otp_length'];
                $is_verification = AP_IS_GOLD ? $settings['ap_register_enable_phone_verification'] : false;
                $message = $this->replace_actions_pack_short_codes( $message, $username, $password, $is_verification, 'phone', $user_id,  $verification_via, $otp_length,  $verification_redirection);
                ap_send_sms( $sid, $token, $from, $phone, $message );
                $counter++;
                $ap_data['phone_counter'] = $counter;
                update_user_meta( $user_id, 'ap_data', $ap_data );
                wp_send_json_success( __('SMS Sent ', 'actions-pack') . '(' . $counter . '/3)' );
            }
            else{
                wp_send_json_error(__('Limit Exceeded', 'actions-pack'));
            }
        }

    }
    
    public function verify_via_link(){
		if ( empty($_GET['action']) || empty($_GET['token']) || empty($_GET['channel']) ) {
			return;
		}

		if ($_GET['action'] === 'verify') {
			$token = maybe_unserialize(base64_decode($_GET['token']));

			if (!isset($token['username']) && !isset($token['unique_string'])) {
				return;
			}

			$channel = $_GET['channel'];

			if ( ($user_id = username_exists( $token['username'])) && get_user_meta($user_id, $channel.'_verification_string', true) === $token['unique_string'] ) {

			    // Set the verified_via_link to true so the user can now log in
				update_user_meta($user_id, 'is_'.$channel.'_verified', 'yes');
				update_user_meta($user_id, $channel.'_verification_string', '');
			}
		}
	}

    public function verify_via_otp( $user_id, $channel, $otp ){
	    if( get_user_meta($user_id, 'is_'.$channel.'_verified', true) === 'yes' ) {
		    wp_send_json_error('Already Verified');
	    }

	    if( (int) get_user_meta($user_id, $channel.'_verification_otp', true) === $otp){
		    update_user_meta($user_id, 'is_'.$channel.'_verified', 'yes');
		    delete_user_meta($user_id, $channel.'_verification_otp');
		    wp_send_json_success( ucfirst( $channel) . __(' Verified', 'actions-pack'));
	    }else{
		    wp_send_json_error( __('Invalid Code. Try again.', 'actions-pack'));
        }
    }

    public function verify_user_manually( $user_id ){
        if( is_null( self::$options ) ){
            $options = self::$options = get_option('actions_pack');
        }else{
            $options = self::$options;
        }
        if( ! is_array($options) ){
            return;
        }
        $from = $options['manual_verification_email_from'];
        $from_name = $options['manual_verification_email_from_name'];
        $subject = $options['manual_verification_email_subject'];
        $content_type = $options['manual_verification_email_content_type'];;
        $message = $options['manual_verification_email_message'];
        if( get_user_meta( $user_id, 'is_manually_verified', 'yes') !== 'yes'){
            update_user_meta( $user_id, 'is_manually_verified', 'yes');
            $user = get_userdata($user_id);
            $to = $user->user_email;
            $message = strtr( $message, [
                '[ap-username]' => $user->user_login,
                '[ap-firstname]' => $user->first_name,
                '[ap-lastname]' => $user->last_name,
            ]);
            ap_send_mail($from, $from_name, $to, $subject, $content_type, $message);
        }
    }

    public function get_list_of_metas_from_register_widget(){
        $options = get_option('actions_pack');
        if ( ! empty($options['register_user_metas']) ){
            $metas = explode(',' , rtrim($options['register_user_metas'], ',') );
            if( empty($metas) ){
                $metas = false;
            }
        }else{
            $metas = false;
        }
        return $metas;
    }

	public function display_meta_fields_on_user_profile(){
        $metas = $this->get_list_of_metas_from_register_widget();

        if( !$metas ){
            return;
        }

        // Existing Metas on User Profile
        $exiting_metas = ['name', 'first_name', 'last_name', 'nickname', 'description', 'rich_editor', 'admin_color'];
        $metas = array_diff($metas, $exiting_metas );

		add_action( 'edit_user_profile', function ( $user ) use ( $metas ){
		   // Verification
            if( in_array('is_email_verified', $metas) || in_array('is_phone_verified', $metas) || in_array('is_manually_verified', $metas) ){
                echo '<h3>Verification</h3><table class="form-table" role="presentation">';
                foreach ($metas as $index => $meta){
                    if( $meta === 'is_email_verified' || $meta === 'is_phone_verified' || $meta === 'is_manually_verified' ){
                        $selected = get_user_meta($user->ID, $meta, true);
                        ?>
                        <tr>
                            <th><label for="<?php echo $meta ?>"><?php echo ucwords(preg_replace('/[^A-Za-z0-9\-]/', ' ', $meta)) ?></label></th>
                            <td>
                                <select name="<?php echo $meta ?>" id="<?php echo $meta ?>">
                                    <option value="" <?php if($selected === ''){echo("selected");}?>>--Select--</option>
                                    <option value="yes" <?php if($selected === 'yes'){echo("selected");}?>>Yes</option>
                                    <option value="no" <?php if($selected === 'no'){echo("selected");}?>>No</option>
                                </select>
                            </td>
                        </tr>
                        <?php
                        unset($metas[$index]);
                    }
                }
                echo '</table>';
            }

		    // Additional Fields
            echo '<h3>Additional Fields</h3><table class="form-table" role="presentation">';
            foreach ($metas as $index => $meta){
                //TODO: Does not work in case of space within meta key. Because Wordpress replaces spaces with underscore
                ?>
                <tr>
                    <th><label for="<?php echo $meta ?>"><?php echo ucwords(preg_replace('/[^A-Za-z0-9\-]/', ' ', $meta)) ?></label></th>
                    <td>
                        <input type="text" id="<?php echo $meta ?>" name="<?php echo $meta ?>" size="20" value="<?php echo esc_attr( get_the_author_meta( $meta, $user->ID ) ); ?>">
                    </td>
                </tr>
                <?php
            }
            echo '</table>';
        });

		add_action( 'edit_user_profile_update', function ( $user_id ) use ( $metas ){
            foreach ($metas as $meta){
                if( $meta === 'is_manually_verified' && $_POST[$meta] ==='yes' ){
                    $this->verify_user_manually($user_id);
                }else{
                    //TODO: Does not work in case of space within meta key. Because Wordpress replaces spaces with underscore
                    if( isset( $_POST[$meta]) ){
                        update_user_meta( $user_id, $meta, $_POST[$meta] );
                    }
                }
            }
        });
	}
	
    public function display_meta_columns_in_user_table(){
        $metas = $this->get_list_of_metas_from_register_widget();

        if( !$metas ){
           return;
        }

        // Add column header name
        add_filter( 'manage_users_columns', function ( $columns ) use( $metas ) {
            foreach ($metas as $meta){
	            $meta_heading = ucwords(preg_replace('/[^A-Za-z0-9\-]/', ' ', $meta));
	            $columns[$meta] = $meta_heading;
            }
            return $columns;
        });

        // Insert value in corresponding row
        add_action( 'manage_users_custom_column', function( $value, $column_name, $user_id ) use ( $metas ) {
            foreach ($metas as $meta){
                if ( $column_name === $meta){
                    if( $meta === 'is_email_verified' || $meta === 'is_phone_verified' || $meta === 'is_manually_verified' ){
                        $verification_status = get_user_meta($user_id, $meta,true);
	                    if($verification_status === 'yes'){
	                        $value = '<span class="dashicons dashicons-yes-alt tooltip" style="color:#2cf50a"><span class="tooltip-text">Verified</span></span>';
                        }else if( $verification_status === '' ){
                            $value = '<span class="dashicons dashicons-dismiss tooltip" style="color:#bbbbbe"><span class="tooltip-text">Verification not initiated</span></span>';
                        }else{
                            $value = '<span class="dashicons dashicons-dismiss tooltip" style="color:#ee0808"><span class="tooltip-text">Not Verified</span></span>';
                        }
                    }
                    else{
                        $value = get_user_meta($user_id, $meta,true);
                    }
                }
            }
            return $value;
        }, 10, 3 );
    }

    public function add_custom_bulk_actions_to_user_table(){
        $metas = $this->get_list_of_metas_from_register_widget();

        add_filter( 'bulk_actions-users', function ( $bulk_actions ) use ( $metas ){
            if( $metas ){
                // Manual verification Bulk Action
                if( in_array('is_manually_verified', $metas ) ){
                    $bulk_actions['ap_verify_users'] = __( 'Mark Manually verified', 'actions-pack');
                }
                // Email bulk action
                if( in_array('is_email_verified', $metas ) ){
                    $bulk_actions['ap_mark_email_verified'] = __( 'Mark Email Verified', 'actions-pack');
                    $bulk_actions['ap_mark_email_not_verified'] = __( 'Mark Email Not Verified', 'actions-pack');
                    $bulk_actions['ap_send_verification_email'] = __( 'Send Verification Email', 'actions-pack');
                }
                // Phone bulk action
                if( in_array('is_phone_verified', $metas ) ){
                    $bulk_actions['ap_mark_phone_verified'] = __( 'Mark Phone Verified', 'actions-pack');
                    $bulk_actions['ap_mark_phone_not_verified'] = __( 'Mark Phone Not Verified', 'actions-pack');
                    $bulk_actions['ap_send_verification_phone'] = __( 'Send Verification SMS', 'actions-pack');
                }
            }
		    return $bulk_actions;
        });

	    add_filter( 'handle_bulk_actions-users', function ( $redirect_to, $doaction, $user_ids ) use ( $metas ){
	        $redirect_to = admin_url('users.php');
		    if( $doaction === 'ap_verify_users'){
                if( $metas ){
                    // Manually verify users when clicked on "Verify Manually" bulk action
                    if( in_array('is_manually_verified', $metas) ){
                        $total_verified_users = 0;
                        foreach ( $user_ids as $user_id ) {
                            $this->verify_user_manually($user_id);
                            $total_verified_users++;
                        }
                        $redirect_to = add_query_arg( 'ap_verified_users', $total_verified_users, $redirect_to );
                    }
                }
            }
		    else if( $doaction === 'ap_mark_email_verified'){
                if( $metas ){
                    // Mark Email as verified.
                    if( in_array('is_email_verified', $metas) ){
                        $total_verified_users = 0;
                        foreach ( $user_ids as $user_id ) {
                            if( get_user_meta( $user_id, 'is_email_verified', 'yes') !== 'yes'){
                                update_user_meta( $user_id, 'is_email_verified', 'yes');
                                $total_verified_users++;
                            }
                        }
                        $redirect_to = add_query_arg( 'ap_marked_email_verified', $total_verified_users, $redirect_to );
                    }
                }
            }
            else if( $doaction === 'ap_mark_email_not_verified'){
                if( $metas ){
                    // Mark Email as not verified.
                    if( in_array('is_email_verified', $metas) ){
                        $total_verified_users = 0;
                        foreach ( $user_ids as $user_id ) {
                            if( get_user_meta( $user_id, 'is_email_verified', 'yes') !== 'no'){
                                if( ! empty(get_user_meta( $user_id, 'ap_data', 'yes'))){
                                    update_user_meta( $user_id, 'is_email_verified', 'no');
                                    $total_verified_users++;
                                }
                            }
                        }
                        $redirect_to = add_query_arg( 'ap_marked_email_not_verified', $total_verified_users, $redirect_to );
                    }
                }
            }
            else if( $doaction === 'ap_send_verification_email'){
                if( $metas ){
                    // Send Verification Email
                    if( in_array('is_email_verified', $metas) ){
                        $total_verified_users = 0;
                        foreach ( $user_ids as $user_id ) {
                            if( get_user_meta( $user_id, 'is_email_verified', 'yes') === 'no'){
                                $this->resend_channel_verification_from_dashboard($user_id, 'email');
                                $total_verified_users++;
                            }
                        }
                        $redirect_to = add_query_arg( 'ap_sent_verification_email', $total_verified_users, $redirect_to );
                    }
                }
            }
            else if( $doaction === 'ap_mark_phone_verified'){
                if( $metas ){
                    // Mark Email as verified.
                    if( in_array('is_phone_verified', $metas) ){
                        $total_verified_users = 0;
                        foreach ( $user_ids as $user_id ) {
                            if( get_user_meta( $user_id, 'is_phone_verified', 'yes') !== 'yes'){
                                update_user_meta( $user_id, 'is_phone_verified', 'yes');
                                $total_verified_users++;
                            }
                        }
                        $redirect_to = add_query_arg( 'ap_marked_phone_verified', $total_verified_users, $redirect_to );
                    }
                }
            }
            else if( $doaction === 'ap_mark_phone_not_verified'){
                if( $metas ){
                    // Mark Email as not verified.
                    if( in_array('is_phone_verified', $metas) ){
                        $total_verified_users = 0;
                        foreach ( $user_ids as $user_id ) {
                            if( get_user_meta( $user_id, 'is_phone_verified', 'yes') !== 'no'){
                                if( ! empty(get_user_meta( $user_id, 'ap_data', 'yes'))){
                                    update_user_meta( $user_id, 'is_phone_verified', 'no');
                                    $total_verified_users++;
                                }
                            }
                        }
                        $redirect_to = add_query_arg( 'ap_marked_phone_not_verified', $total_verified_users, $redirect_to );
                    }
                }
            }
            else if( $doaction === 'ap_send_verification_phone'){
                if( $metas ){
                    // Send Verification Email
                    if( in_array('is_phone_verified', $metas) ){
                        $total_verified_users = 0;
                        foreach ( $user_ids as $user_id ) {
                            if( get_user_meta( $user_id, 'is_phone_verified', 'yes') === 'no'){
                                $this->resend_channel_verification_from_dashboard($user_id, 'phone');
                                $total_verified_users++;
                            }
                        }
                        $redirect_to = add_query_arg( 'ap_sent_verification_phone', $total_verified_users, $redirect_to );
                    }
                }
            }
		    return $redirect_to;
        }, 10, 3 );

	    add_action( 'admin_notices', function (){
		    if ( ! empty( $_REQUEST['ap_verified_users'] ) ) {
			    $users_count = intval( $_REQUEST['ap_verified_users'] );
			    printf( '<div id="message" class="updated notice is-dismissible"><p>' .
			            _n( '%s user verified.',
				            '%s users verified.',
				            $users_count,
				            'actions-pack'
			            ) . '</p></div>', $users_count );
		    }
		    else if ( ! empty( $_REQUEST['ap_marked_email_verified'] ) ) {
                $users_count = intval( $_REQUEST['ap_marked_email_verified'] );
                printf( '<div id="message" class="updated notice is-dismissible"><p>' .
                    _n( '%s Email marked as Verified.',
                        '%s Emails marked as Verified.',
                        $users_count,
                        'actions-pack'
                    ) . '</p></div>', $users_count );
            }
            else if ( ! empty( $_REQUEST['ap_marked_email_not_verified'] ) ) {
                $users_count = intval( $_REQUEST['ap_marked_email_not_verified'] );
                printf( '<div id="message" class="updated notice is-dismissible"><p>' .
                    _n( '%s Email marked as Not Verified.',
                        '%s Emails marked as Not Verified.',
                        $users_count,
                        'actions-pack'
                    ) . '</p></div>', $users_count );
            }
            else if ( isset( $_REQUEST['ap_sent_verification_email'] ) ) {
                $users_count = intval( $_REQUEST['ap_sent_verification_email'] );
                if($users_count === 0){
                    printf( '<div id="message" class="error notice is-dismissible"><p>Sorry! Email could not be sent. You must need to mark those users unverified first.</p></div>');
                }else{
                    printf( '<div id="message" class="updated notice is-dismissible"><p>' .
                        _n( 'Verification Email has been sent to %s user.',
                            'Verification Email has been sent to %s users',
                            $users_count,
                            'actions-pack'
                        ) . '</p></div>', $users_count );
                }

            }
            else if ( ! empty( $_REQUEST['ap_marked_phone_verified'] ) ) {
                $users_count = intval( $_REQUEST['ap_marked_phone_verified'] );
                printf( '<div id="message" class="updated notice is-dismissible"><p>' .
                    _n( '%s Phone marked as Verified.',
                        '%s Phone marked as Verified.',
                        $users_count,
                        'actions-pack'
                    ) . '</p></div>', $users_count );
            }
            else if ( ! empty( $_REQUEST['ap_marked_phone_not_verified'] ) ) {
                $users_count = intval( $_REQUEST['ap_marked_phone_not_verified'] );
                printf( '<div id="message" class="updated notice is-dismissible"><p>' .
                    _n( '%s Phone marked as Not Verified.',
                        '%s Phone marked as Not Verified.',
                        $users_count,
                        'actions-pack'
                    ) . '</p></div>', $users_count );
            }
            else if ( isset( $_REQUEST['ap_sent_verification_phone'] ) ) {
                $users_count = intval( $_REQUEST['ap_sent_verification_phone'] );
                if($users_count === 0){
                    printf( '<div id="message" class="error notice is-dismissible"><p>Sorry! SMS could not be sent. You must need to mark those users unverified first.</p></div>');
                }else {
                    printf('<div id="message" class="updated notice is-dismissible"><p>' .
                        _n('Verification SMS has been sent to %s user.',
                            'Verification SMS has been sent to %s users',
                            $users_count,
                            'actions-pack'
                        ) . '</p></div>', $users_count);
                }
            }
        } );
    }

	public function get_role_names() {

		global $wp_roles;

		if ( ! isset( $wp_roles ) )
			$wp_roles = new WP_Roles();
		$roles = $wp_roles->get_names();
		$roles['let-users-choose'] = __('Let Users Choose', 'actions-pack');
		return $roles;
	}

    public function username_auto_generate( $username ){
	    $username = substr(preg_replace("/[^a-z]/", "", strtolower(sanitize_user($username, true))),0, 8);
	    static $i;
	    if ( null === $i ) {
		    $i = rand(1,99);
	    } else {
		    $i ++;
	    }
	    if ( ! username_exists( $username ) ) {
		    return $username;
	    }
	    $new_username = sprintf( '%s%s', $username, $i );
	    if ( ! username_exists( $new_username ) ) {
		    return $new_username;
	    } else {
		    return call_user_func( __FUNCTION__, $username );
	    }
    }

	public function add_custom_controls( $element, $args ){
        //https://github.com/elementor/elementor/issues/6499
        $element->start_controls_section(
            'ap_register_otp_style',
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
		                    'value' => 'register'
	                    ],
	                    [
                            'relation' => 'or',
                            'terms' =>
                            [
	                            [
		                            'name' => 'ap_register_email_verification_via',
		                            'operator' => '==',
		                            'value' => 'otp'
	                            ],
	                            [
		                            'name' => 'ap_register_phone_verification_via',
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
			'ap_register_otp_container',
			[
				'label' => __( 'Container', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);
		$element->add_control(
			'ap_register_otp_container_position',
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
			'ap_register_otp_container_margin',
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
			'ap_register_otp_container_padding',
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
				'name' => 'ap_register_otp_container_background',
				'label' => __( 'Container Background', 'actions-pack' ),
				'types' => [ 'classic', 'gradient', 'video' ],
				'selector' => '{{WRAPPER}} .ap-otp-container',
			]
		);

		$element->add_control(
			'ap_register_otp_box',
			[
				'label' => __( 'Box', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);
		$element->add_control(
			'ap_register_otp_box_width',
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
			'ap_register_otp_box_height',
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
				'name' => 'ap_register_otp_box_background',
				'label' => __( 'Box Background', 'actions-pack' ),
				'types' => [ 'classic', 'gradient', 'video' ],
				'selector' => '{{WRAPPER}} .ap-otp-box',
			]
		);
		$element->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'ap_register_otp_box_border',
				'label' => __( 'Border', 'actions-pack' ),
				'selector' => '{{WRAPPER}} .ap-otp-box',
			]
		);

		$element->add_control(
			'ap_register_otp_box_border_radius',
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
				'name' => 'ap_register_otp_box_shadow',
				'label' => __( 'Box Shadow', 'actions-pack' ),
				'selector' => '{{WRAPPER}} .ap-otp-box',
			]
		);

		$element->add_control(
			'ap_register_otp_text',
			[
				'label' => __( 'Text', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$element->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'ap_register_otp_heading',
				'label' => __( 'Typography', 'actions-pack' ),
				'scheme' => \Elementor\Core\Schemes\Typography::TYPOGRAPHY_1,
				'selector' => '{{WRAPPER}} .ap-otp-heading p',
			]
		);

		$element->add_control(
			'ap_register_otp_heading_color',
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
			'ap_register_otp_inputs',
			[
				'label' => __( 'Individual Input', 'actions-pack' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$element->add_control(
			'ap_register_otp_individual_input',
			[
				'label' => __('Size', 'actions-pack'),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'selectors' => [
					'{{WRAPPER}} .ap-otp-inputs input' => 'width: {{SIZE}}px; height: {{SIZE}}px;',
				]
			]
		);

		$element->add_control(
			'ap_register_otp_individual_input_focus_color',
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

				if ( AP_IS_SILVER && $settings['ap_register_hide_form'] === 'yes' && is_user_logged_in() && ! \Elementor\Plugin::$instance->editor->is_edit_mode() ){
					$content = '';
				}
            }
		}
		return $content;
    }

//************************************************************** METHODS ( END )***************************************************************************//

//**************************************************************  ACTION CONTROL ***************************************************************************//
	public function get_name() {
		return 'register';
	}

	public function get_label() {
		return __('Register', 'actions-pack');
	}

    public function register_settings_section( $widget ) {

        $widget->start_controls_section(
            'ap_section_register',
            [
                'label' => __( 'Register', 'actions-pack' ),
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );
//************************************************************** USER ROLES ( START )***************************************************************************//
        $widget->add_control(
            'ap_register_user_role_popover',
            [
                'label' => __( 'User Role <span class="ap-required">*</span>', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes'
            ]
        );
        $widget->start_popover();
        $widget->add_control(
            'ap_register_user_role',
            [
                'label' => __( 'User Role', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'subscriber',
                'show_label' => true,
                'options' => $this->get_role_names(),
                'classes' => 'ap-upgrade',
                'description' => AP_IS_SILVER ? null : AP_UPGRADE_TO_SILVER,
            ]
        );
        $widget->add_control(
            'ap_register_let_users_choose',
            [
                'label' => __( 'Form Field', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'show_label' => true,
                'options' => [],
                'classes' => 'ap-upgrade',
                'condition' => [
                        'ap_register_user_role' => 'let-users-choose'
                ],
                'description' => AP_IS_GOLD ? null : AP_UPGRADE_TO_GOLD,
            ]
        );
        $widget->end_popover();
//************************************************************** USER ROLE ( END ) ***************************************************************************//

//********************************************************* USERNAME ( START ) ***************************************************************************//
        $widget->add_control(
		    'ap_register_username_popover',
		    [
			    'label' => __( 'Username <span class="ap-required">*</span>', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
			    'label_off' => __( 'Default', 'actions-pack' ),
			    'label_on' => __( 'Custom', 'actions-pack' ),
			    'return_value' => 'yes'
		    ]
	    );
	    $widget->start_popover();
	    $widget->add_control(
            'ap_register_username',
            [
                'label' => __( 'Form Field', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'show_label' => true,
                'options' => []
            ]
        );
	    $widget->add_control(
		    'ap_register_username_auto_generate',
		    [
			    'label' => __( 'Auto Generate', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Enable', 'actions-pack' ),
			    'label_off' => __( 'Disable', 'actions-pack' ),
			    'return_value' => 'yes',
			    'classes' => 'ap-upgrade',
			    'description' => 'Automatically generates a username using the above Form Field. Use shortcode [ap-autogen-username] to send it over email or message.' . ( AP_IS_GOLD ? null : AP_UPGRADE_TO_GOLD )
		    ]
	    );
	    $widget->end_popover();
//************************************************************** USERNAME ( END ) ***************************************************************************//

//******************************************************** PASSWORD ( START ) ****************************************************************************//
	    $widget->add_control(
		    'ap_register_password_popover',
		    [
			    'label' => __( 'Password <span class="ap-required">*</span>', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
			    'label_off' => __( 'Default', 'actions-pack' ),
			    'label_on' => __( 'Custom', 'actions-pack' ),
			    'return_value' => 'yes',
		    ]
	    );
	    $widget->start_popover();
	    $widget->add_control(
            'ap_register_password',
            [
                'label' => __( 'Form Field', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'option' => [],
                'show_label' => true,
                'condition' =>[
                    'ap_register_password_auto_generate!' => 'yes'
                ],
            ]
        );
	    $widget->add_control(
		    'ap_register_enable_confirm_password',
		    [
			    'label' => __( 'Confirm Password', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Enable', 'actions-pack' ),
			    'label_off' => __( 'Disable', 'actions-pack' ),
			    'return_value' => 'yes',
			    'condition' =>[
				    'ap_register_password_auto_generate!' => 'yes'
			    ],
                'classes' => 'ap-upgrade',
                'description' => ( AP_IS_SILVER ? '' : AP_UPGRADE_TO_SILVER )
		    ]
	    );
	    $widget->add_control(
		    'ap_register_password_confirm',
		    [
			    'label' => __( 'Form Field', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'options' => [],
			    'show_label' => true,
			    'condition' =>[
			    	'ap_register_enable_confirm_password' => 'yes',
                    'ap_register_password_auto_generate!' => 'yes'
			    ]
		    ]
	    );
	    $widget->add_control(
		    'ap_register_password_auto_generate',
		    [
			    'label' => __( 'Auto Generate', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Enable', 'actions-pack' ),
			    'label_off' => __( 'Disable', 'actions-pack' ),
			    'return_value' => 'yes',
			    'classes' => 'ap-upgrade',
			    'description' => __('It automatically generate a random password. Use shortcode [ap-autogen-password] to send it over email or message.','actions-pack') . ( AP_IS_GOLD ? '' : AP_UPGRADE_TO_GOLD )
		    ]
	    );
	    $widget->add_control(
		    'ap_register_password_auto_generate_length',
		    [
			    'label' => __( 'Length', 'actions-pack' ),
			    'type' => Controls_Manager::SLIDER,
			    'size_units' => ['px'],
			    'range' => [
				    'px' => [
					    'min' => 5,
					    'max' => 20,
					    'step' => 1,
				    ],
			    ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'condition'=>[
                   'ap_register_password_auto_generate' => 'yes'
                ]
		    ]
	    );
	    $widget->end_popover();
//************************************************************** PASSWORD ( END ) ***************************************************************************//

//************************************************************** EMAIL ( START )***************************************************************************//
	    $widget->add_control(
		    'ap_register_email_popover',
		    [
			    'label' => __( 'Email', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
			    'label_off' => __( 'Default', 'actions-pack' ),
			    'label_on' => __( 'Custom', 'actions-pack' ),
			    'return_value' => 'yes',
		    ]
	    );
	    $widget->start_popover();
	    $widget->add_control(
		    'ap_register_user_email',
		    [
			    'label' => __( 'Form Field', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'options' => [],
			    'show_label' => true,
		    ]
	    );
	    $widget->add_control(
		    'ap_register_enable_email_verification',
		    [
			    'label' => __( 'Verification', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Enable', 'actions-pack' ),
			    'label_off' => __( 'Disable', 'actions-pack' ),
			    'return_value' => 'yes',
			    'default' => 'no',
                'classes' => 'ap-upgrade',
                'description' => ( AP_IS_GOLD ? '' : AP_UPGRADE_TO_GOLD )
		    ]
	    );
	    $widget->add_control(
		    'ap_register_email_verification_via',
		    [
			    'label' => __( 'Verification Via', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'default' => 'link',
			    'show_label' => true,
			    'options' => [
				    'link'=> 'Link',
				    'otp' => 'OTP'
			    ],
			    'condition' =>[
				    'ap_register_enable_email_verification' => 'yes',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_register_email_verification_notice',
		    [
			    'type' => \Elementor\Controls_Manager::RAW_HTML,
			    'raw' => sprintf( __('You must enable the %s option below and paste the shortcode %s in the message box to send a unique link or otp to verify user\'s Email ID.','actions-pack'),'<strong>notification</strong>','<strong>[ap-verification]</strong>'),
			    'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
			    'condition' => [
				    'ap_register_enable_email_verification' => 'yes',
			    ]
		    ]
	    );
	    $widget->add_control(
		    'ap_register_email_otp_length',
		    [
			    'label' => __( 'OTP Length', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::NUMBER,
			    'min' => 2,
			    'max' => 10,
			    'step' => 1,
			    'default' => 4,
			    'condition' =>[
				    'ap_register_enable_email_verification' => 'yes',
				    'ap_register_email_verification_via' => 'otp',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_register_email_verification_redirection',
		    [
			    'label' => __( 'Redirection URL', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'placeholder' => 'https://site.com/login',
			    'default' => '',
			    'show_label' => true,
			    'condition' =>[
				    'ap_register_email_verification_via' => 'link',
				    'ap_register_enable_email_verification' => 'yes',
			    ],
			    'title' => __('Where users should be redirected after successful verification of Email ID. Leave empty for current page.', 'actions-pack')
		    ]
	    );
	    $widget->add_control(
		    'ap_register_enable_email_notification',
		    [
			    'label' => __( 'Notification', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Enable', 'actions-pack' ),
			    'label_off' => __( 'Disable', 'actions-pack' ),
			    'return_value' => 'yes',
			    'default' => 'no',
			    'classes' => 'ap-upgrade',
			    'description' => ( AP_IS_SILVER ? null : AP_UPGRADE_TO_SILVER )
		    ]
	    );
	    $widget->add_control(
		    'ap_register_email_from_name',
		    [
			    'label' => __( 'From Name', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'default' => get_bloginfo( 'name' ),
			    'placeholder' => get_bloginfo( 'name' ),
			    'show_label' => true,
			    'condition' =>[
				    'ap_register_enable_email_notification' => 'yes',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_register_email_from',
		    [
			    'label' => __( 'From Email', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'default' => get_bloginfo('admin_email'),
			    'placeholder' => get_bloginfo('admin_email'),
			    'show_label' => true,
			    'condition' =>[
				    'ap_register_enable_email_notification' => 'yes',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_register_email_subject',
		    [
			    'label' => __( 'Subject', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'default' => __('Thank you for registering', 'actions-pack'),
			    'placeholder' => __('Thank you for registering', 'actions-pack'),
			    'show_label' => true,
			    'condition' =>[
				    'ap_register_enable_email_notification' => 'yes',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_register_email_content_type',
		    [
			    'label' => __( 'Send As', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'default' => 'plain',
			    'render_type' => 'none',
			    'options' => [
				    'html' => __( 'HTML', 'actions-pack' ),
				    'plain' => __( 'Plain', 'actions-pack' ),
			    ],
			    'condition' =>[
				    'ap_register_enable_email_notification' => 'yes',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_register_email_message',
		    [
			    'label' => __( 'Message', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXTAREA,
			    'default' => __('Thank you for registering to our site.', 'actions-pack'),
			    'description' => __( 'To send form fields, copy the shortcode that appears inside advanced section of each field and paste it above.', 'actions-pack' ),
			    'show_label' => true,
			    'condition' =>[
				    'ap_register_enable_email_notification' => 'yes',
			    ],
		    ]
	    );
	    $widget->end_popover();
//************************************************************** EMAIL ( END ) ***************************************************************************//

//************************************************************** PHONE ( START ) ***************************************************************************//
	    $widget->add_control(
		    'ap_register_phone_popover',
		    [
			    'label' => __( 'Phone', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
			    'label_off' => __( 'Default', 'actions-pack' ),
			    'label_on' => __( 'Custom', 'actions-pack' ),
			    'return_value' => 'yes',
		    ]
	    );
	    $widget->start_popover();
	    $widget->add_control(
		    'ap_register_user_phone',
		    [
			    'label' => __( 'Form Field', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'show_label' => true,
			    'options' =>[]
		    ]
	    );
	    $widget->add_control(
		    'ap_register_enable_phone_verification',
		    [
			    'label' => __( 'Verification', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Enable', 'actions-pack' ),
			    'label_off' => __( 'Disable', 'actions-pack' ),
			    'return_value' => 'yes',
			    'default' => 'no',
                'classes' => 'ap-upgrade',
                'description' => AP_IS_GOLD ? '' : AP_UPGRADE_TO_GOLD,
		    ]
	    );
	    $widget->add_control(
		    'ap_register_phone_verification_via',
		    [
			    'label' => __( 'Verification Via', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'default' => 'link',
			    'show_label' => true,
			    'options' => [
				    'link'=> 'Link',
				    'otp' => 'OTP'
			    ],
			    'condition' =>[
				    'ap_register_enable_phone_verification' => 'yes',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_register_phone_verification_notice',
		    [
			    'type' => \Elementor\Controls_Manager::RAW_HTML,
			    'raw' => sprintf( __('You must enable the %s option below and paste the shortcode %s in the message box to send a unique link or otp to verify user\'s Phone Number.','actions-pack'),'<strong>notification</strong>','<strong>[ap-verification]</strong>'),
			    'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
			    'condition' => [
				    'ap_register_enable_phone_verification' => 'yes',
			    ]
		    ]
	    );
	    $widget->add_control(
		    'ap_register_phone_otp_length',
		    [
			    'label' => __( 'OTP Length', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::NUMBER,
			    'min' => 2,
			    'max' => 10,
			    'step' => 1,
			    'default' => 4,
			    'condition' =>[
				    'ap_register_enable_phone_verification' => 'yes',
				    'ap_register_phone_verification_via' => 'otp',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_register_phone_verification_redirection',
		    [
			    'label' => __( 'Redirection URL', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'placeholder' => 'https://site.com/login',
			    'show_label' => true,
			    'condition' =>[
				    'ap_register_enable_phone_verification' => 'yes',
				    'ap_register_phone_verification_via' => 'link',
			    ],
			    'title' => __('Where users should be redirected after successful verification of Phone Number. Leave empty for current page.', 'actions-pack')
		    ]
	    );
	    $widget->add_control(
		    'ap_register_enable_phone_notification',
		    [
			    'label' => __( 'Notification', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Enable', 'actions-pack' ),
			    'label_off' => __( 'Disable', 'actions-pack' ),
			    'return_value' => 'yes',
			    'default' => 'no',
			    'classes' => 'ap-upgrade',
			    'description' => ( AP_IS_SILVER ? '' : AP_UPGRADE_TO_SILVER )
		    ]
	    );
	    $widget->add_control(
		    'ap_register_phone_credentials_source',
		    [
			    'label' => __( 'SMS API', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'label_block' => false,
			    'options' =>[
				    'default' => 'Default',
				    'custom' => 'Custom'
			    ],
			    'default' => 'default',
			    'condition' =>[
				    'ap_register_enable_phone_notification' => 'yes',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_register_phone_credentials_notice',
		    [
			    'type' => \Elementor\Controls_Manager::RAW_HTML,
			    'raw' => sprintf('%s <a style="color: #0b76ef" href="%s" target="_blank">%s</a>. %s',__('To use default credentials, make sure you have already set the credentials', 'actions-pack'),admin_url('admin.php?page=elementor#tab-integrations'), __('here', 'actions-pack'), __('You can use this field to set a custom credential for current form only', 'actions-pack')),
			    'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
			    'condition' => [
				    'ap_register_phone_credentials_source' => 'default',
				    'ap_register_enable_phone_notification' => 'yes',
			    ]
		    ]
	    );
	    $widget->add_control(
		    'ap_register_phone_custom_source_notice',
		    [
			    'type' => \Elementor\Controls_Manager::RAW_HTML,
			    'raw' => sprintf('%s <a href="%s" target="_blank" style="color: #0b76ef"> %s </a> %s', __('Click', 'actions-pack'), 'https://twilio.com/referral/nWJHpb',__('here', 'actions-pack'), __('to get your Twilio Account SID, Auth Token and Phone number', 'actions-pack') ),
			    'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
			    'condition' => [
				    'ap_register_phone_credentials_source' => 'custom',
				    'ap_register_enable_phone_notification' => 'yes',
			    ]
		    ]
	    );
	    $widget->add_control(
		    'ap_register_sms_account_sid',
		    [
			    'label' => __( 'Account SID', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'label_block' => false,
			    'condition' => [
				    'ap_register_phone_credentials_source' => 'custom',
				    'ap_register_enable_phone_notification' => 'yes',
			    ]
		    ]
	    );
	    $widget->add_control(
		    'ap_register_sms_auth_token',
		    [
			    'label' => __( 'Auth Token', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'label_block' => false,
			    'condition' => [
				    'ap_register_phone_credentials_source' => 'custom',
				    'ap_register_enable_phone_notification' => 'yes',
			    ]
		    ]
	    );
	    $widget->add_control(
		    'ap_register_sms_from_number',
		    [
			    'label' => __( 'From Number', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'placeholder' => '+919876543210',
			    'show_label' => true,
			    'condition' =>[
				    'ap_register_phone_credentials_source' => 'custom',
				    'ap_register_enable_phone_notification' => 'yes',
			    ],
		    ]
	    );
	    $widget->add_control(
		    'ap_register_phone_message',
		    [
			    'label' => __( 'Message', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXTAREA,
			    'default' => sprintf( __( 'Thank you for registering. Please verify your Phone number using this %s link/otp before you login to our site.', 'actions-pack' ),'[ap-verification]'),
			    'placeholder' => sprintf( __( 'Thank you for registering. Please verify your Phone number using this %s link/otp before you login to our site.', 'actions-pack' ),'[ap-verification]'),
			    'description' => __( 'To send form fields, copy the shortcode that appears inside advanced section of each field and paste it above.', 'actions-pack' ),
			    'show_label' => true,
			    'condition' =>[
			    	'ap_register_enable_phone_notification' => 'yes',
			    ],
		    ]
	    );
		$widget->end_popover();
//************************************************************** PHONE ( END ) ***************************************************************************//

//************************************************************** ADDITIONAL FIELDS ( START ) ***************************************************************************//
        $widget->add_control(
            'ap_register_additional_fields_popover',
            [
                'label' => __( 'Additional Fields', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes',
            ]
        );
        $widget->start_popover();
        $repeater = new \Elementor\Repeater();
	    $repeater->add_control(
		    'ap_register_additional_user_field',
		    [
			    'label' => __( 'User Field', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'show_label' => true,
			    'options' => ap_get_user_fields_list('register')
		    ]
	    );
	    $repeater->add_control(
		    'ap_register_additional_user_field_custom',
		    [
			    'label' => __( 'Meta Key', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'condition' => [
				    'ap_register_additional_user_field' => 'custom'
			    ],
			    'show_label' => true,
		    ]
	    );
	    $repeater->add_control(
		    'ap_register_additional_form_field',
		    [
			    'label' => __( 'Form Field', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'options' => [],
			    'show_label' => true,
		    ]
	    );
	    $widget->add_control(
		    'ap_user_additional_fields',
		    [
			    'label' => __( 'Fields Mapping', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::REPEATER,
			    'fields' => $repeater->get_controls(),
			    'title_field' => '{{{ apUserAdditionalTitle( ap_register_additional_user_field, ap_register_additional_user_field_custom ) }}}',
			    'default' => [
			            [
                            'ap_register_additional_user_field' => '',
                            'ap_register_additional_form_field' => ''
                        ]
                ],
			    'prevent_empty' => false,
			    'classes' => 'ap-upgrade',
		    ]
	    );
	    $widget->add_control(
		    'ap_user_additional_fields_upgrade_notice',
		    [
			    'type' => \Elementor\Controls_Manager::RAW_HTML,
			    'raw' => ( AP_IS_SILVER ? '' : AP_UPGRADE_TO_SILVER ),
		    ]
	    );
	    $widget->end_popover();
//*********************************************************** ADDITIONAL FIELDS ( END ) ***********************************************************************//

//*********************************************************** Manual Verification ***********************************************************************//
        $widget->add_control(
            'ap_register_manual_verification_popover',
            [
                'label' => __( 'Manual Verification', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes',
            ]
        );
        $widget->start_popover();
        $widget->add_control(
            'ap_register_enable_manual_verification',
            [
                'label' => __( 'Manual Verification', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __( 'Enable', 'actions-pack' ),
                'label_off' => __( 'Disable', 'actions-pack' ),
                'return_value' => 'yes',
                'default' => 'no',
                'separator' => 'before',
                'classes' => 'ap-upgrade',
                'description' => __('If you enable this option, users can\'t login to your site until you manually verify them <a href="'.admin_url('users.php').'" target="_blank">here.</a> The option to verify users is there in Bulk actions drop-down menu.', 'actions-pack'). ( AP_IS_GOLD ? '' : AP_UPGRADE_TO_GOLD ),
            ]
        );
        $widget->add_control(
            'ap_register_manual_verification_email_from_name',
            [
                'label' => __( 'From Name', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => get_bloginfo( 'name' ),
                'placeholder' => get_bloginfo( 'name' ),
                'show_label' => true,
                'condition' =>[
                    'ap_register_enable_manual_verification' => 'yes',
                ],
            ]
        );
        $widget->add_control(
            'ap_register_manual_verification_email_from',
            [
                'label' => __( 'From Email', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => get_bloginfo('admin_email'),
                'placeholder' => get_bloginfo('admin_email'),
                'show_label' => true,
                'condition' =>[
                    'ap_register_enable_manual_verification' => 'yes',
                ],
            ]
        );
        $widget->add_control(
            'ap_register_manual_verification_email_subject',
            [
                'label' => __( 'Subject', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Your Account has been successfully verified', 'actions-pack'),
                'placeholder' => __('Your Account has been successfully verified', 'actions-pack'),
                'show_label' => true,
                'condition' =>[
                    'ap_register_enable_manual_verification' => 'yes',
                ],
            ]
        );
        $widget->add_control(
            'ap_register_manual_verification_email_content_type',
            [
                'label' => __( 'Send As', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'plain',
                'render_type' => 'none',
                'options' => [
                    'html' => __( 'HTML', 'actions-pack' ),
                    'plain' => __( 'Plain', 'actions-pack' ),
                ],
                'condition' =>[
                    'ap_register_enable_manual_verification' => 'yes',
                ],
            ]
        );
        $widget->add_control(
            'ap_register_manual_verification_email_message',
            [
                'label' => __( 'Message', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('We completed verifying your Account. Now you will be able to login to our site.', 'actions-pack'),
                'placeholder' => __( 'We completed verifying your Account. Now you will be able to login to our site.', 'actions-pack' ),
                'show_label' => true,
                'condition' =>[
                    'ap_register_enable_manual_verification' => 'yes',
                ],
            ]
        );
        $widget->end_popover();
//*********************************************************** ADDITIONAL SETTINGS ( START ) ***********************************************************************//
        $widget->add_control(
            'ap_register_additional_settings_popover',
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
		    'ap_register_auto_login',
		    [
			    'label' => __( 'Auto-Login', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Enable', 'actions-pack' ),
			    'label_off' => __( 'Disable', 'actions-pack' ),
			    'return_value' => 'yes',
			    'default' => 'no',
			    'separator' => 'before',
			    'classes' => 'ap-upgrade',
			    'description' => __('It allows users to login automatically as soon as they complete the registration process. Redirection option below should be enabled to auto refresh the page after login.', 'actions-pack') . ( AP_IS_GOLD ? null : AP_UPGRADE_TO_GOLD ),
		    ]
	    );
	    $widget->add_control(
		    'ap_register_redirection',
		    [
			    'label' => __( 'Redirection', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Enable', 'actions-pack' ),
			    'label_off' => __( 'Disable', 'actions-pack' ),
			    'return_value' => 'yes',
			    'default' => 'no',
			    'separator' => 'before',
                'classes' => 'ap-upgrade',
			    'description' => __('The page where should the users be redirected after filling up the registration form.','actions-pack') . ( AP_IS_GOLD ? null : AP_UPGRADE_TO_GOLD )
		    ]
	    );
	    $widget->add_control(
		    'ap_register_redirection_url',
		    [
			    'label' => __( 'Redirect To', 'actions-pack' ),
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
                'title' => __('Leave empty for current page redirection.', 'actions-pack'),
                'condition' =>[
                        'ap_register_redirection' => 'yes'
                ]
		    ]
	    );
	    $widget->add_control(
		    'ap_register_hide_form',
		    [
			    'label' => __( 'Hide Form', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SWITCHER,
			    'label_on' => __( 'Yes', 'actions-pack' ),
			    'label_off' => __( 'No', 'actions-pack' ),
			    'return_value' => 'yes',
			    'default' => 'no',
			    'separator' => 'before',
			    'classes' => 'ap-upgrade',
			    'description' => __('Hide this Form for logged-in users. The option does not work for Editor mode.', 'actions-pack'). ( AP_IS_SILVER ? '' : AP_UPGRADE_TO_SILVER ),
		    ]
	    );

        $widget->add_control(
            'ap_register_stop_actions_on_error',
            [
                'label' => __( 'Stop Other Actions', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __( 'Enable', 'actions-pack' ),
                'label_off' => __( 'Disable', 'actions-pack' ),
                'return_value' => 'yes',
                'default' => 'no',
                'separator' => 'before',
                'classes' => 'ap-upgrade',
                'description' => __('If you enable this option, other Actions won\'t be executed on failure of user registration.' , 'actions-pack'). ( AP_IS_GOLD ? '' : AP_UPGRADE_TO_GOLD ),
            ]
        );
	    $widget->end_popover();
//*********************************************************** OTHER SETTINGS ( END ) ***********************************************************************//
        $widget->end_controls_section();
    }

    public function run( $record, $ajax_handler ) {

        // FORM Widget Settings Data
        $settings = $record->get( 'form_settings' );
        $stop_next_action = $settings['ap_register_stop_actions_on_error'];

        // Don't proceed if user already logged in
        if (is_user_logged_in()) {
            ap_error_occurred( $ajax_handler, $stop_next_action, __('You are already logged-in', 'actions-pack') );
            return;
        }

        // Get submitted Form data
	    $form_data = [];
	    $raw_form_data = $record->get( 'fields' );
        foreach ( $raw_form_data as $id => $field ) {
	        $form_data[ $id ] = $field['value'];
        }

        // Get all fields from ELEMENTOR -> FORM WIDGET -> FORM FIELDS
	    $form_fields = [];
	    $raw_form_fields = $record->get_form_settings('form_fields');
        foreach ($raw_form_fields as $form_field){
            $form_fields[ $form_field['custom_id'] ] = $form_field;
        }

	    // Role
        if( !empty($settings['ap_register_let_users_choose']) && AP_IS_GOLD){
            if( empty($form_data[$settings['ap_register_let_users_choose']])){
                ap_error_occurred( $ajax_handler, $stop_next_action, __('User Role can\'t be empty.', 'actions-pack') );
                return;
            }
            $selected_role = strtolower($form_data[$settings['ap_register_let_users_choose']]);

            $allowed_roles = [];
            $options = preg_split( "/\\r\\n|\\r|\\n/", $form_fields[$settings['ap_register_let_users_choose']]['field_options']);
            foreach ( $options as $option ) {
                if (false !== strpos($option, '|')) {
                    list($label, $value) = explode('|', $option);
                    $allowed_roles[strtolower(esc_attr($value))] = esc_html($label);
                }else{
                    $allowed_roles[strtolower(esc_attr($option))] = esc_html($option);
                }
            }

            if( array_key_exists($selected_role, $allowed_roles) && wp_roles()->is_role( $selected_role )){
                $role = $selected_role;
            }else{
                ap_error_occurred( $ajax_handler, $stop_next_action, __('User role doesn\'t exist.', 'actions-pack') );
                return;
            }
        }else{
            $role = AP_IS_SILVER ? $settings['ap_register_user_role'] : 'subscriber';
        }
        
	    // Username
        if( !empty( $settings['ap_register_username_popover'] ) && !empty($form_data[$settings['ap_register_username']])){
	        if( AP_IS_GOLD && $settings['ap_register_username_auto_generate'] === 'yes'){
		        $username = $this->username_auto_generate($form_data[$settings['ap_register_username']]);
	        }
	        else{
		        $username = $form_data[$settings['ap_register_username']];
		        // In backend also validate the username as per pattern set
		        if ( 1 !== preg_match( "'".$form_fields[$settings['ap_register_username']]['field_pattern']."'", $username ) ) {
                    ap_error_occurred( $ajax_handler, $stop_next_action, $form_fields[$settings['ap_register_username']]['field_pattern_message'] );
			        return;
		        }
	        }
        }else{
            ap_error_occurred( $ajax_handler, $stop_next_action, __('Username field can not be empty.', 'actions-pack') );
            return;
        }

        // Password
        if( !empty($settings['ap_register_password_popover']) ){

	        if( AP_IS_GOLD && $settings['ap_register_password_auto_generate'] === 'yes' ){
		        $length = $settings['ap_register_password_auto_generate_length']['size'];
		        $password = wp_generate_password($length);
	        }
	        else{
		        if( empty( $form_data[$settings['ap_register_password']] ) ){
                    ap_error_occurred( $ajax_handler, $stop_next_action, __('Password field can not be empty.', 'actions-pack') );
                    return;
		        }
		        $password = $form_data[$settings['ap_register_password']];
		        // In backend also validate the password as per pattern set
		        if ( 1 !== preg_match( "'".$form_fields[$settings['ap_register_password']]['field_pattern']."'", $password ) ) {
                    ap_error_occurred( $ajax_handler, $stop_next_action, $form_fields[$settings['ap_register_password']]['field_pattern_message'] );
                    return;
		        }
		        // In backend check if Confirmation Password field matches with Password Field
		        if ( AP_IS_SILVER && !empty($form_data[$settings['ap_register_password_confirm']]) ) {
			        if ( $password !== $form_data[$settings['ap_register_password_confirm']] || empty( $form_data[$settings['ap_register_password_confirm']]) ) {
                        ap_error_occurred( $ajax_handler, $stop_next_action, __('Both Password fields should be matched', 'actions-pack') );
                        return;
			        }
		        }
	        }
        }else{
            ap_error_occurred( $ajax_handler, $stop_next_action, __('Password field can not be empty.', 'actions-pack') );
            return;
        }


	    // Email
        $email = '';
        if( !empty($settings['ap_register_user_email']) ){
            if(empty($form_data[$settings['ap_register_user_email']])){
                ap_error_occurred( $ajax_handler, $stop_next_action, __('Email field is required!', 'actions-pack') );
                return;
            }
            else{
                $email = $form_data[$settings['ap_register_user_email']];
            }
        }


	    // Phone
        $phone = '';
        if( !empty($settings['ap_register_user_phone'])){
            if(empty( $form_data[$settings['ap_register_user_phone']])){
                ap_error_occurred( $ajax_handler, $stop_next_action, __('Phone number is required!', 'actions-pack') );
                return;
            }
            else{
                $phone = $form_data[$settings['ap_register_user_phone']];
                if(ap_channel_value_exists('phone', $phone )){
                    ap_error_occurred( $ajax_handler, $stop_next_action, __('Sorry, that phone number is already used!', 'actions-pack') );
                    return;
                }
            }
        }


	    // Now create the user
	    $user_id = wp_insert_user([
		        'role'          => $role,
                'user_login'	=> $username,
                'user_pass'	 	=> $password,
                'user_email'    => $email
        ]);

        if ( is_wp_error($user_id) ){
            ap_error_occurred( $ajax_handler, $stop_next_action, $user_id->get_error_message() );
            return;
	    }

	    if ( !empty($phone) ){
		    update_user_meta($user_id, 'user_phone', $phone );
	    }

	    // @toDo Delete on first successful Login
        // Store until verification finished by user
	    $ap_data = [
		    'post_id' => (int) $_POST['post_id'],
		    'form_id' => $settings['id'],
		    'form_data' => $form_data,
            'username' => $username,
            'password' => $password,
            'update_counter' => 0,
            'email_counter' => 0,
            'phone_counter' => 0,
            'channel_counter' => 0,
	    ];
	    update_user_meta($user_id, 'ap_data', $ap_data);

	    // Channel notification and verification
        $channels = ['email', 'phone'];
	    foreach( $channels as $channel){
	        if( !empty($settings['ap_register_user_'.$channel]) ){
                $ajax_data['updateChannels'][$channel]['nonce'] = ap_create_nonce($user_id . 'update_'.$channel);
                if ( AP_IS_SILVER && $settings['ap_register_enable_'.$channel.'_notification'] === 'yes' ){
                    $ajax_data['notifyChannels'][$channel]['nonce'] = ap_create_nonce($user_id . 'notify_'.$channel);
                    if ( AP_IS_GOLD && $settings['ap_register_enable_'.$channel.'_verification'] === 'yes' ){
                        $is_channel_verified = 'no';
                        if($settings['ap_register_'.$channel.'_verification_via'] === 'otp'){
                            $otp_length = (int) $settings['ap_register_'.$channel.'_otp_length'];
                            $is_channel_verified = $otp_length; // Numeric value means "no" and its an otp
                            $ajax_data['verifyChannels'][$channel]['nonce'] = ap_create_nonce($user_id . 'verify_'.$channel);
                            $ajax_data['verifyChannels'][$channel]['length'] = $otp_length;
                        }
                        update_user_meta( $user_id, 'is_'.$channel.'_verified', $is_channel_verified );
                    }
                }
            }
	    }

	    // Manual Verification
        if( AP_IS_GOLD && $settings['ap_register_enable_manual_verification'] === 'yes' ){
            update_user_meta( $user_id, 'is_manually_verified', 'no');
        }

	    // Additional User Fields
	    if( AP_IS_SILVER && !empty( $settings['ap_user_additional_fields'] ) ){
		    $ajax_data['updateFields']['nonce'] = ap_create_nonce($user_id . 'update_fields' );
	    }

        // Auto Login
	    if (AP_IS_GOLD && $settings['ap_register_auto_login'] === 'yes'){
		    wp_set_current_user($user_id, $username);
		    wp_set_auth_cookie( $user_id );
	    }

	    // Redirect
        if( AP_IS_GOLD && $settings['ap_register_redirection'] === 'yes'){
	        $redirect_to = $settings['ap_register_redirection_url'];
	        $redirect_to = $record->replace_setting_shortcodes( $redirect_to, true );
	        if ( ! empty( $redirect_to ) && filter_var( $redirect_to, FILTER_VALIDATE_URL ) ) {
		        $ajax_data['redirectTo'] = $redirect_to;
	        }else{
		        $ajax_data['redirectTo'] = ap_get_referrer();
	        }
        }

	    // Other necessary data
	    $ajax_data['username'] = $username;
	    $ajax_data['formId'] = $settings['id'];

	    $ajax_handler->add_response_data('apRegistrationSuccess', $ajax_data);
    }

    public function on_export( $element ) {
        unset(
            $element['ap_register_username'],
            $element['ap_register_email'],
            $element['ap_register_password']
        );
    }
}