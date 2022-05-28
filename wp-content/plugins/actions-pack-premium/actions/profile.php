<?php

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Action_Profile extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    private static $settings , $user_id = null, $pending_data = null;

	public function __construct() {
        if( wp_doing_ajax() ){
            add_action( 'wp_ajax_ap_notify_data_changes', [ $this, 'notify_data_changes' ] );
        }
        else{
            add_filter( 'elementor_pro/forms/render/item', [ $this, 'render_field' ], 10, 3 );
            add_action( 'elementor/widget/render_content',[ $this, 'render_content' ], 10, 2);
            add_action('init', [ $this, 'update_user_field_on_approve' ]);
            add_action('init', [ $this, 'delete_pending_data_on_rejection' ]);
            add_filter( 'edit_profile_url', [ $this, 'modify_profile_url' ], 10, 3 );
            add_action('load-profile.php', [ $this, 'redirect_profile_url']);
            if ( is_admin() ){
                if( current_user_can( 'edit_users') ){
                    $this->display_edit_user_on_users_table();
                    add_filter( 'get_edit_user_link', [ $this, 'modify_edit_profile_link' ], 10, 2);
                }
            }
        }
    }

    public function modify_profile_url( $url, $user_id, $scheme ){
        $option = get_option('actions_pack');
        if( !empty( $option['profile_page_url'] ) ) {
            $url = $option['profile_page_url'];
        }
        return $url;
    }

    public function redirect_profile_url(){
        $option = get_option('actions_pack');
        if( !empty( $option['profile_page_url'] ) ) {
            if( ! current_user_can( 'manage_options' )){
                exit( wp_safe_redirect($option['profile_page_url']) );
            }
        }
    }

    public function modify_edit_profile_link( $link, $user_id){
        if( user_can( $user_id, 'manage_options' )){
            $link = add_query_arg([
                'user_id' => $user_id
            ], admin_url("user-edit.php"));
        }
        return $link;
    }

    public function display_edit_user_on_users_table(){
        $option = get_option('actions_pack');

        if( !empty( $option['profile_page_url'] ) ) {

            $url = $option['profile_page_url'];

            // Add column header name
            add_filter( 'manage_users_columns', function ( $columns ){
                return array_slice($columns, 0, 2, true) +
                    ['edit_user'=>'Edit User'] +
                    array_slice($columns, 2, NULL, true);
            });
            // Insert value in corresponding row
            add_action( 'manage_users_custom_column', function( $value, $column_name, $user_id ) use ($url){
                if($column_name === 'edit_user'){
                    $link = add_query_arg([
                        'user_id' => $user_id
                    ], $url);
                    $value = '<a href="'.$link. '" target="_blank"><span class="dashicons dashicons-edit" style="color:#082eee"></span></a>';
                }
                return $value;
            }, 10, 3 );
        }
	}

    public function notify_data_changes(){
	    $username = sanitize_user( $_POST['username'] );
	    $user_id = username_exists($username);

	    if( ! $user_id ){
	        return;
        }

	    if ( ! wp_verify_nonce($_POST['nonce'], $user_id . 'notify_data_changes') ){
	        return;
        }

	    $pending_data = get_user_meta($user_id, 'ap_pending_changes', true);
        $post_id = (int) $_POST['post_id'];
        $form_id = (string) $_POST['form_id'];
        $settings = ap_get_elementor_control_settings( $post_id, $form_id );
        $referrer = ap_get_referrer();

	    if( ! empty( $pending_data[$form_id]['user']['fields'] ) ){
	        $from = $settings['ap_profile_email_from_user'];
            $from_name = $settings['ap_profile_email_from_name_user'];
            if( !empty($pending_data[$form_id]['user']['fields']['user_email']) ){
                $to = $pending_data[$form_id]['user']['fields']['user_email']['new_value'];
            }else{
                $to = $this->get_user_field($user_id, 'user_email');
            }
            $subject = $settings['ap_profile_email_subject_user'];
            $content_type = 'html';
            $message = $settings['ap_profile_email_message_user'];
            $message = $this->replace_actions_pack_shortcode($message, $user_id, $pending_data[$form_id]['user']['key'], $username, $pending_data[$form_id]['user']['fields'], $form_id, 'user', $referrer);
            ap_send_mail($from, $from_name, $to, $subject, $content_type, $message);
        }

        if( ! empty( $pending_data[$form_id]['admin']['fields'] )){
            $from = $settings['ap_profile_email_from_admin'];
            $from_name = $settings['ap_profile_email_from_name_admin'];
            $to = $settings['ap_profile_email_to_admin'];
            $subject = $settings['ap_profile_email_subject_admin'];
            $content_type = 'html';
            $message = $settings['ap_profile_email_message_admin'];
            $message = $this->replace_actions_pack_shortcode($message, $user_id, $pending_data[$form_id]['admin']['key'], $username, $pending_data[$form_id]['admin']['fields'], $form_id, 'admin', $referrer);
            ap_send_mail($from, $from_name, $to, $subject, $content_type, $message);
        }

    }

    public function get_user_field( $user_id, $field){
        switch ($field){
            case 'user_login' :
                return get_userdata($user_id)->user_login;
            case 'new_password' :
                return '';
            case 'user_nicename':
                return get_userdata($user_id)->user_nicename;
            case 'user_email' :
                return get_userdata($user_id)->user_email;
            case 'user_url' :
                return get_userdata($user_id)->user_url;
            case 'display_name' :
                return get_userdata($user_id)->display_name;
            case 'name' :
                $first_name = trim( get_user_meta($user_id, 'first_name', true));
                $last_name = trim(get_user_meta($user_id, 'last_name', true));
                if( !empty($last_name)){
                    return $first_name . '&nbsp;' . $last_name;
                }else{
                    return $first_name;
                }

            default :
                if ( user_can( $user_id, 'dokandar' ) ){
                    $data = get_user_meta($user_id, 'dokan_profile_settings', true);
                    $value = ap_get_array_value_by_string_pattern($data, $field);

                    if( is_int($value) && ($field === 'dokan[banner]' || $field === 'dokan[gravatar]' || $field === 'dokan[icon]') ){
                        $value = wp_get_attachment_image_url($value);
                    }
                    return $value;
                }else{
                    $value = get_user_meta($user_id, $field, true);
                    //@toDo for some reason get_user_meta is called earlier and gives array output
                    if(is_array($value)){
                        $value = $value[0];
                    }
                    return $value;
                }
        }
    }

    public function update_user_field( $user_id, $field, $value ){
        switch ( $field ){
            case 'user_login':
                if( get_userdata($user_id)->user_login === $value ){
                    return true;
                }
                if( username_exists(sanitize_user($value))){
                    return new WP_Error('username_exists', __('Username already exists. Try another.' , 'actions-pack') );
                }
                global $wpdb;
                return $wpdb->update($wpdb->users, ['user_login' => $value], ['ID' => $user_id]);
            case 'new_password':
                return wp_update_user([ 'ID' => $user_id, 'user_pass' => $value ]);
            case 'user_nicename':
                return wp_update_user([ 'ID' => $user_id, 'user_nicename' => $value ]);
            case 'user_email' :
                if( get_userdata($user_id)->user_email === $value ){
                    return true;
                }
                if( email_exists($value) ){
                    return new WP_Error( 'email_exists',__('Email already exists. Try another.' , 'actions-pack') );
                }
                return wp_update_user([ 'ID' => $user_id, 'user_email' => $value ]);
            case 'user_phone' :
                if( get_user_meta($user_id, 'user_phone', true) === $value  ){
                    return true;
                }
                if( ap_channel_value_exists('user_phone', $value) ){
                    return new WP_Error( 'phone_exists', __('Phone already exists. Try another.' , 'actions-pack') );
                }
                return update_user_meta( $user_id, 'user_phone', $value );
            case 'user_url' :
                return wp_update_user([ 'ID' => $user_id, 'user_url' => $value ]);
            case 'display_name' :
                return wp_update_user([ 'ID' => $user_id, 'display_name' => $value ]);
            case 'name' :
                $first_name = strtok($value, ' ');
                $last_name = strstr($value, ' ');
                update_user_meta( $user_id, 'first_name', $first_name );
                update_user_meta( $user_id, 'last_name', $last_name );
                return true;
            default :
                if ( user_can( $user_id, 'dokandar' ) ){
                    $data = get_user_meta($user_id, 'dokan_profile_settings', true);
                    if (!empty($data)){
                        $data = ap_update_array_value_by_string_pattern($data, $field, $value);
                        return update_user_meta( $user_id, 'dokan_profile_settings', $data );
                    }else{
                        return new WP_Error('user_not_dokandar', __('You are not a registered vendor.' , 'actions-pack') );
                    }
                }else{
                    $result = update_user_meta( $user_id, $field, $value );
                    return $result;
                }
        }
    }

    public function replace_actions_pack_shortcode( $message , $user_id, $unique_string, $username, $data_to_be_approved, $form_id, $type, $referrer){
        return strtr( $message, [
            '[profile-link]' => add_query_arg( ['user_id' => $user_id], remove_query_arg(['action', 'token'],$referrer) ),
            '[first-name]' => get_user_meta($user_id, 'first_name'),
            '[last-name]' => get_user_meta($user_id, 'last_name'),
            '[username]' => $username,
            '[approval-link]' => $this->generate_approval_link( $unique_string, $username, $form_id, $type, $user_id, $referrer ),
            '[rejection-link]' => $this->generate_rejection_link( $unique_string, $username, $form_id, $type, $user_id, $referrer ),
            '[pending-data]' => $this->array_to_html_table( $data_to_be_approved )
        ]);
    }

    public function generate_approval_link( $unique_string, $username, $form_id, $type, $user_id, $referrer ){
	    $token = base64_encode( serialize([ 'unique_string' => $unique_string, 'username' => $username, 'form_id' => $form_id ]));
        if( $type === 'admin'){
            $url = add_query_arg( ['action' => 'verifyChanges', 'token' => $token, 'user_id' => $user_id], $referrer );
        }else{
            $url = add_query_arg( ['action' => 'verifyChanges', 'token' => $token], $referrer );
        }
        return $url;
    }

    public function generate_rejection_link( $unique_string, $username, $form_id, $type, $user_id, $referrer ){
        $token = base64_encode( serialize([ 'unique_string' => $unique_string, 'username' => $username, 'form_id' => $form_id ]));
        if( $type === 'admin'){
            $url = add_query_arg( ['action' => 'ignoreChanges', 'token' => $token, 'user_id' => $user_id], $referrer );
        }else{
            $url = add_query_arg( ['action' => 'ignoreChanges', 'token' => $token], $referrer );
        }
        return $url;
    }

    public function array_to_html_table($array){
        $table =  '<table rules="all" bordercolor="#4d4c4d" border="1" bgcolor="#FFFFFF" cellpadding="10"  align="left"><tr><th>Field Name</th><th>Old Value</th><th>New Value</th></tr>';
        foreach($array as $key=>$value){
            $table .= '<tr>' . '<td>'. ucwords(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $key)).'</td>' . '<td>'.$value['old_value'].'</td>' . '<td>'.$value['new_value'].'</td>' .'</tr>';
        }
        $table .= '</table>';
        return $table;
	}

    public function update_user_field_on_approve(){

        if ( empty($_GET['action'] ) || empty( $_GET['token'] ) ) {
            return;
        }

        if ( $_GET['action'] !== 'verifyChanges' ) {
            return;
        }

        $token = maybe_unserialize(base64_decode($_GET['token']));

        if (!isset($token['username']) && !isset($token['unique_string']) && !isset($token['form_id']) ) {
            return;
        }

        if( ! $user_id = username_exists( $token['username'] ) ){
            return;
        }

        $pending_data = get_user_meta($user_id, 'ap_pending_changes', true);

        if( !is_array($pending_data) ){
            return;
        }

        $form_id = (string) $token['form_id'];

        $user_fields = $admin_fields = [];
        $alert = null;

        if( array_key_exists($form_id, $pending_data)) {
            if( array_key_exists('user', $pending_data[$form_id] ) && $pending_data[$form_id]['user']['key'] === $token['unique_string'] ){
                //user approved
                $alert = 'confirmed';
                $user_fields = $pending_data[$form_id]['user']['fields'];
                unset($pending_data[$form_id]['user']);
            }
            elseif ( array_key_exists('admin', $pending_data[$form_id] ) && $pending_data[$form_id]['admin']['key'] === $token['unique_string'] ){
                // admin approved
                $alert = 'confirmed';
                $admin_fields = $pending_data[$form_id]['admin']['fields'];
                unset($pending_data[$form_id]['admin']);
            }
        }

        if( empty( $pending_data[$form_id] ) ){
            // this form is approved by both user & admin
            $alert = 'updated';
            $pending_fields = array_unique(array_merge( $user_fields , $admin_fields ), SORT_REGULAR);
            foreach( $pending_fields as $field=>$data){
                $this->update_user_field($user_id, $field, $data['new_value'] );
            }
            unset($pending_data[$form_id]);
        }

        if ( empty( $pending_data ) ){
            // All forms are approved by everyone
            delete_user_meta($user_id, 'ap_pending_changes');
        }else{
            // Other forms is yet to be approved
            update_user_meta($user_id, 'ap_pending_changes', $pending_data);
        }

        if($alert === 'confirmed'){
            ap_print_alert('success', 'Successfully Confirmed!!!', '');
        }elseif ($alert === 'updated'){
            ap_print_alert('success', 'Profile Updated Successfully!!!', '');
        }
    }

    public function delete_pending_data_on_rejection(){

        if ( empty($_GET['action'] ) || empty( $_GET['token'] ) ) {
            return;
        }

        if ( $_GET['action'] !== 'ignoreChanges' ) {
            return;
        }

        $token = maybe_unserialize(base64_decode($_GET['token']));

        if (!isset($token['username']) && !isset($token['unique_string']) && !isset($token['form_id']) ) {
            return;
        }

        if( ! $user_id = username_exists( $token['username'] ) ){
            return;
        }

        $pending_data = get_user_meta($user_id, 'ap_pending_changes', true);

        if( !is_array($pending_data) ){
            return;
        }

        $form_id = (string) $token['form_id'];

        $alert = null;

        if( array_key_exists($form_id, $pending_data)) {
            if( !empty($pending_data[$form_id]['user']['key']) && $pending_data[$form_id]['user']['key'] === $token['unique_string'] || !empty($pending_data[$form_id]['admin']['key']) && $pending_data[$form_id]['admin']['key'] === $token['unique_string'] ){
                //user approved
                $alert = 'ignored';
                unset($pending_data[$form_id]['user']);
                unset($pending_data[$form_id]['admin']);
            }
        }

        if ( empty( $pending_data ) ){
            // All forms are ignored by everyone
            delete_user_meta($user_id, 'ap_pending_changes');
        }else{
            // Other forms is yet to be approved
            update_user_meta($user_id, 'ap_pending_changes', $pending_data);
        }

        if($alert === 'ignored'){
            ap_print_alert('success', 'Changes Ignored!!!', '');
        }
    }

	public function render_field( $field, $field_index, $form_widget ){
        $settings = self::$settings = $form_widget->get_settings();

        // Return if required action is not set for this form
        if( ! in_array( $this->get_name(), $settings['submit_actions'] ) ){
            return $field;
        }

        if ( is_null( self::$user_id ) ) {
            if( !empty( $_GET['user_id'] ) ){
                if( current_user_can('edit_users') ){
                    $user_id = self::$user_id = (int) $_GET['user_id'];
                    if ( get_userdata( $user_id ) === false ) {
                        $user_id = self::$user_id = get_current_user_id();
                    }
                }else{
                    $user_id = self::$user_id = get_current_user_id();
                }
            }
            else{
                $user_id = self::$user_id = get_current_user_id();
            }
        }else{
            $user_id = self::$user_id;
        }

        // return if user don't have permission
        if ( $user_id === 0 || !current_user_can( 'edit_user', self::$user_id )) {
            return $field;
        }

        foreach ( $settings['ap_profile_fields'] as $profile_field ){

            if( $profile_field['ap_profile_form_field'] === $field['custom_id'] ){

                $form_widget->remove_render_attribute( 'input' . $field_index, 'value');

                $user_field = $profile_field['ap_profile_user_field'];
                if( $user_field === 'custom'){
                    $user_field = $profile_field['ap_profile_custom_meta'];
                }

                $default_value = $this->get_user_field($user_id, $user_field);
                $attributes = [];

                // Default Value
                if( $field['field_type'] === 'upload' ){
                    if( ctype_digit($default_value) ){
                        $default_value = wp_get_attachment_url( $default_value );
                    }
                    if( $profile_field['ap_profile_is_editable_field'] !== 'yes' ){
                        $attributes['readonly'] = 'readonly';
                        $attributes['class'] = 'disabled';
                        $attributes['tabindex'] = '-1';
                        $attributes['onclick'] = 'return false';
                    }
                    //@toDo Show url instead of "No files Chosen"
                    $attributes['value'] = explode(',', $default_value)[0];
                    $form_widget->add_render_attribute( 'input' . $field_index, $attributes); // input is the field type
                }
                else if($field['field_type'] === 'select'){
                    if( $profile_field['ap_profile_is_editable_field'] !== 'yes' ){
                        $attributes['readonly'] = 'readonly';
                        $attributes['class'] = 'disabled';
                        $attributes['tabindex'] = '-1';
                        $attributes['onclick'] = 'return false';
                    }
                    // Set new Value after submitting the form
                    $attributes['onchange'] = 'Object.keys(this).forEach((key)=>this[key].removeAttribute("selected"));Object.keys(this.selectedOptions).forEach((key)=>this.selectedOptions[key].setAttribute("selected","selected"));';
                    $form_widget->add_render_attribute( 'select' . $field_index, $attributes);
                    // Set Selected Value or Values when rendering
                    $options = preg_split( "/\\r\\n|\\r|\\n/", $field['field_options'] );
                    foreach ($options as $index=>$option){
                        if(strpos($option, '|')){
                            $options[$index] =  explode('|', $option)[1];
                        }
                    }
                    $selected_value = explode( ',', $default_value);
                    foreach ($selected_value as $value){
                        $form_widget->add_render_attribute( $field['custom_id'] . array_search(trim($value), $options), ['selected' => 'selected']);
                    }
                }
                else if($field['field_type'] === 'checkbox'){
                    if( $profile_field['ap_profile_is_editable_field'] !== 'yes' ){
                        $attributes['readonly'] = 'readonly';
                        $attributes['class'] = 'disabled';
                        $attributes['tabindex'] = '-1';
                        $attributes['onclick'] = 'return false';
                    }
                    $attributes['onchange'] = 'if(this.hasAttribute("checked")){this.removeAttribute("checked")}else{this.setAttribute("checked", "checked")}';
                    $options = preg_split( "/\\r\\n|\\r|\\n/", $field['field_options'] );
                    foreach ($options as $index=>$option){
                        $form_widget->add_render_attribute( $field['custom_id'] . $index, $attributes);
                        if(strpos($option, '|')){
                            $options[$index] =  explode('|', $option)[1];
                        }
                    }
                    $selected_values = explode( ',', $default_value);
                    foreach ($selected_values as $value){
                        $form_widget->add_render_attribute( $field['custom_id'] . array_search(trim($value), $options), ['checked'=>'checked']);
                    }
                }
                else if($field['field_type'] === 'acceptance') {
                    if( $profile_field['ap_profile_is_editable_field'] !== 'yes' ){
                        $attributes['readonly'] = 'readonly';
                        $attributes['class'] = 'disabled';
                        $attributes['tabindex'] = '-1';
                        $attributes['onclick'] = 'return false';
                    }
                    //Set Selected
                    if (!empty($default_value)) {
                        $attributes['checked'] = 'checked';
                    }
                    $attributes['onchange'] = 'if(this.hasAttribute("checked")){this.removeAttribute("checked")}else{this.setAttribute("checked", "checked")}';
                    $form_widget->add_render_attribute( 'input' . $field_index, $attributes);
                }
                else if($field['field_type'] === 'radio'){
                    if( $profile_field['ap_profile_is_editable_field'] !== 'yes' ){
                        $attributes['readonly'] = 'readonly';
                        $attributes['class'] = 'disabled';
                        $attributes['tabindex'] = '-1';
                        $attributes['onclick'] = 'return false';
                    }
                    $attributes['onchange'] = 'Array.from(this.parentElement.parentElement.querySelectorAll("input")).forEach(e => e.removeAttribute("checked"));this.setAttribute("checked", "checked")';
                    $options = preg_split( "/\\r\\n|\\r|\\n/", $field['field_options'] );
                    foreach ($options as $index=>$option){
                        $form_widget->add_render_attribute( $field['custom_id'] . $index, $attributes);
                        if(strpos($option, '|')){
                            $options[$index] =  explode('|', $option)[1];
                        }
                    }
                    // set selected value
                    $form_widget->add_render_attribute( $field['custom_id'] . array_search(trim($default_value), $options), ['checked' => 'checked']);
                }
                else if($field['field_type'] === 'textarea'){
                    if( $profile_field['ap_profile_is_editable_field'] !== 'yes' ){
                        $attributes['readonly'] = 'readonly';
                        $attributes['class'] = 'disabled';
                        $attributes['tabindex'] = '-1';
                        $attributes['onclick'] = 'return false';
                    }
                    $attributes['oninput'] = 'this.defaultValue=this.value';
                    $form_widget->add_render_attribute( 'textarea' . $field_index, $attributes);
                    $field['field_value'] = $default_value; // Hack the default value in form_field->advanced and set yours
                }
                else{
                    // Editable @toDo this should be also checked in backend
                    if( $profile_field['ap_profile_is_editable_field'] !== 'yes' ){
                        $attributes['readonly'] = 'readonly';
                        $attributes['class'] = 'disabled';
                        $attributes['tabindex'] = '-1';
                        $attributes['onclick'] = 'return false';
                    }
                    $attributes['value'] = $default_value;
                    $attributes['oninput'] = 'this.defaultValue=this.value';
                    $form_widget->add_render_attribute( 'input' . $field_index, $attributes);
                }
            }
        }

		return $field;
	}

    public function render_content( $content, $widget ){
        // Return if widget is not form type
        if( $widget->get_name() !== 'form' ){
            return $content;
        }

        // Return if required action is not set for this form
        if( ! in_array( $this->get_name(), self::$settings['submit_actions'] ) ){
            return $content;
        }

        // You don't have permission to edit profile
        if( self::$user_id === 0 || ! current_user_can( 'edit_user', self::$user_id ) ) {
            $content = __('You don\'t have permission to edit the profile.', 'actions-pack');
            return $content;
        }

	    // Enqueue Scripts and Styles
        wp_enqueue_script('ap-user');
        wp_enqueue_style('ap-user');

        // Good to define any Logic now
        if ( is_null( self::$pending_data ) ) {
            self::$pending_data  = get_user_meta(self::$user_id, 'ap_pending_changes', true);
        }

        if( empty(self::$pending_data) ){
            return $content;
        }

        $form_id = (string) $widget->get_id();

        if( !empty(self::$pending_data[$form_id]['user']['message']) ){
            ap_print_alert('warning', '', self::$pending_data[$form_id]['user']['message'] );
        }

        if( !empty(self::$pending_data[$form_id]['admin']['message']) ){
            ap_print_alert('warning', '', self::$pending_data[$form_id]['admin']['message'] );
        }

        return $content;
    }

	public function get_name() {
        return 'ap_profile';
    }

    public function get_label() {
        return __( 'Edit Profile', 'actions-pack' );
    }

    public function register_settings_section( $widget ) {

        $widget->start_controls_section(
            'ap_section_profile',
            [
                'label' => __( 'Edit Profile', 'actions-pack' ),
                'condition' => [
                    'submit_actions' => $this->get_name()
                ],
            ]
        );

        $widget->add_control(
            'ap_profile_fields_popover',
            [
                'label' => __( 'Profile Fields', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes'
            ]
        );
        $widget->start_popover();
	    $repeater = new \Elementor\Repeater();
        $repeater->add_control(
            'ap_profile_form_field',
            [
                'label' => __( 'Form Field', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [],
                'show_label' => true
            ]
        );
	    $repeater->add_control(
		    'ap_profile_user_field',
		    [
			    'label' => __( 'User Field', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::SELECT,
			    'show_label' => true,
			    'options' => ap_get_user_fields_list('edit-profile')
		    ]
	    );
	    $repeater->add_control(
		    'ap_profile_custom_meta',
		    [
			    'label' => __( 'Meta Key', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::TEXT,
			    'condition' => [
				    'ap_profile_user_field' => 'custom'
			    ],
			    'show_label' => true
		    ]
	    );
	    $repeater->add_control(
		    'ap_profile_is_editable_field',
		    [
                'label' => __( 'Editable', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_off' => __( 'No', 'actions-pack' ),
                'label_on' => __( 'Yes', 'actions-pack' ),
                'default' => 'yes',
                'return_value' => 'yes',
                'conditions'=>[
                    'relations' => 'or',
                    'terms' =>[
                        [
                            'name' => 'ap_profile_user_field',
                            'operator' => '!==',
                            'value' => 'current_password'
                        ],
                        [
                            'name' => 'ap_profile_user_field',
                            'operator' => '!==',
                            'value' => 'repeat_password'
                        ]
                    ]
                ]
		    ]
	    );
        $repeater->add_control(
            'ap_profile_require_user_approval',
            [
                'label' => __( 'Require User Approval', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_off' => __( 'No', 'actions-pack' ),
                'label_on' => __( 'Yes', 'actions-pack' ),
                'default' => 'no',
                'return_value' => 'yes',
                'conditions'=>[
                    'relations' => 'or',
                    'terms' =>[
                        [
                            'name' => 'ap_profile_user_field',
                            'operator' => '!==',
                            'value' => 'current_password'
                        ],
                        [
                            'name' => 'ap_profile_user_field',
                            'operator' => '!==',
                            'value' => 'repeat_password'
                        ],
                        [
                            'name' => 'ap_profile_user_field',
                            'operator' => '!==',
                            'value' => 'new_password'
                        ]
                    ]
                ]
            ]
        );
        $repeater->add_control(
            'ap_profile_require_admin_approval',
            [
                'label' => __( 'Require Admin Approval', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_off' => __( 'No', 'actions-pack' ),
                'label_on' => __( 'Yes', 'actions-pack' ),
                'default' => 'no',
                'return_value' => 'yes',
                'conditions'=>[
                    'relations' => 'or',
                    'terms' =>[
                        [
                            'name' => 'ap_profile_user_field',
                            'operator' => '!==',
                            'value' => 'current_password'
                        ],
                        [
                            'name' => 'ap_profile_user_field',
                            'operator' => '!==',
                            'value' => 'repeat_password'
                        ],
                        [
                            'name' => 'ap_profile_user_field',
                            'operator' => '!==',
                            'value' => 'new_password'
                        ]
                    ]
                ]
            ]
        );
	    $widget->add_control(
		    'ap_profile_fields',
		    [
			    'label' => __( '<strong>Map the form fields with your user fields</strong>', 'actions-pack' ),
			    'type' => \Elementor\Controls_Manager::REPEATER,
			    'fields' => $repeater->get_controls(),
			    'title_field' => '{{{ apUserAdditionalTitle( ap_profile_user_field, ap_profile_custom_meta ) }}}',
			    'default' => [
			        [
			            'ap_profile_user_field' => 'first_name',
                        'ap_profile_form_field' => 'name',
                        'ap_profile_is_editable_field' => 'yes',
                        'ap_profile_require_user_approval' => 'no',
                        'ap_profile_require_admin_approval' => 'no'
                    ],
                    [
                        'ap_profile_user_field' => 'user_email',
                        'ap_profile_form_field' => 'email',
                        'ap_profile_is_editable_field' => 'yes',
                        'ap_profile_require_user_approval' => 'yes',
                        'ap_profile_require_admin_approval' => 'no'
                    ]
                ],
			    'prevent_empty' => false
		    ]
	    );
        $widget->end_popover();

        $widget->add_control(
            'ap_profile_approval_settings_popover',
            [
                'label' => __( 'Approval Settings', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes'
            ]
        );
        $widget->start_popover();
        $widget->add_control(
            'ap_profile_email_settings_user',
            [
                'label' => __( 'Email Settings (User)', 'actions-pack' ),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before'
            ]
        );
        $widget->add_control(
            'ap_profile_email_settings_description_user',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => '<p style="margin-top:-15px">'.__('Email to be sent to the Users asking for their approval by clicking on a confirmation link when they change any value on their profile.', 'actions-pack').'</p>',
                'classes' => 'elementor-control-field-description'
            ]
        );
        $widget->add_control(
            'ap_profile_email_from_name_user',
            [
                'label' => __( 'From Name', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => get_bloginfo( 'name' ),
                'placeholder' => get_bloginfo( 'name' ),
                'show_label' => true
            ]
        );
        $widget->add_control(
            'ap_profile_email_from_user',
            [
                'label' => __( 'From Email', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => get_bloginfo('admin_email'),
                'placeholder' => get_bloginfo('admin_email'),
                'show_label' => true
            ]
        );
        $widget->add_control(
            'ap_profile_email_subject_user',
            [
                'label' => __( 'Subject', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Approve changes on your profile', 'actions-pack'),
                'placeholder' => __('Approve changes your profile', 'actions-pack'),
                'show_label' => true
            ]
        );
        $widget->add_control(
            'ap_profile_email_message_user',
            [
                'label' => __( 'Message', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::WYSIWYG,
                'placeholder' => __('<table bgcolor="#e2e2e4" border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td style="padding:20px 30px 20px 30px;"><table bgcolor="#ffffff" align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="padding:20px 30px 20px 30px;"><tr><td style="padding:10px 0 10px 0;">Hello [username],</td></tr><tr><td style="padding:10px 0 10px 0;">You recently requested to change following information on your profile.</td></tr><tr><td style="padding:10px 0 10px 0;">[pending-data]</td></tr><tr><td style="padding:10px 0 10px 0;">To change your profile information you need to confirm it by clicking on the button below.</td></tr><tr><td align="left" style="padding:20px 0 10px 0;"><a href="[approval-link]" target="_blank" style="text-decoration:none;"><span style="background-color:#0094c5;padding:12px;border:none;border-radius:6px;text-decoration:none;text-transform:none;color:#ffffff;font-weight:600;letter-spacing:2px;">CONFIRM</span></a><a href="[rejection-link]" target="_blank" style="text-decoration:none"><span style="padding:11px;border:1px solid;border-radius:6px;text-decoration:none;text-transform:none;color:#0094c5;font-weight:300;letter-spacing:2px;margin-left:25px;">IGNORE</span></a></td></tr></table></td></tr></table>', 'actions-pack'),
                'default' =>     __('<table bgcolor="#e2e2e4" border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td style="padding:20px 30px 20px 30px;"><table bgcolor="#ffffff" align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="padding:20px 30px 20px 30px;"><tr><td style="padding:10px 0 10px 0;">Hello [username],</td></tr><tr><td style="padding:10px 0 10px 0;">You recently requested to change following information on your profile.</td></tr><tr><td style="padding:10px 0 10px 0;">[pending-data]</td></tr><tr><td style="padding:10px 0 10px 0;">To change your profile information you need to confirm it by clicking on the button below.</td></tr><tr><td align="left" style="padding:20px 0 10px 0;"><a href="[approval-link]" target="_blank" style="text-decoration:none;"><span style="background-color:#0094c5;padding:12px;border:none;border-radius:6px;text-decoration:none;text-transform:none;color:#ffffff;font-weight:600;letter-spacing:2px;">CONFIRM</span></a><a href="[rejection-link]" target="_blank" style="text-decoration:none"><span style="padding:11px;border:1px solid;border-radius:6px;text-decoration:none;text-transform:none;color:#0094c5;font-weight:300;letter-spacing:2px;margin-left:25px;">IGNORE</span></a></td></tr></table></td></tr></table>', 'actions-pack'),
                'show_label' => true
            ]
        );

        $widget->add_control(
            'ap_profile_email_settings_admin',
            [
                'label' => __( 'Email Settings (Admin)', 'actions-pack' ),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before'
            ]
        );
        $widget->add_control(
            'ap_profile_email_settings_description_admin',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => '<p style="margin-top:-15px">'.__('Email to be sent to an Admin asking for approval by clicking on a confirmation link when users change any value on their profile.', 'actions-pack').'</p>',
                'classes' => 'elementor-control-field-description'
            ]
        );
        $widget->add_control(
            'ap_profile_email_from_name_admin',
            [
                'label' => __( 'From Name', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => get_bloginfo( 'name' ),
                'placeholder' => get_bloginfo( 'name' ),
                'show_label' => true
            ]
        );
        $widget->add_control(
            'ap_profile_email_from_admin',
            [
                'label' => __( 'From Email', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => get_bloginfo('admin_email'),
                'placeholder' => get_bloginfo('admin_email'),
                'show_label' => true
            ]
        );
        $widget->add_control(
            'ap_profile_email_to_admin',
            [
                'label' => __( 'To Email', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => get_bloginfo('admin_email'),
                'placeholder' => get_bloginfo('admin_email'),
                'show_label' => true
            ]
        );
        $widget->add_control(
            'ap_profile_email_subject_admin',
            [
                'label' => __( 'Subject', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Approve changes on user profile', 'actions-pack'),
                'placeholder' => __('Thank you for registering', 'actions-pack'),
                'show_label' => true
            ]
        );
        $widget->add_control(
            'ap_profile_email_message_admin',
            [
                'label' => __( 'Message', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::WYSIWYG,
                'placeholder' => __('<table bgcolor="#e2e2e4" border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td style="padding:20px 30px 20px 30px;"><table bgcolor="#ffffff" align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="padding:20px 30px 20px 30px;"><tr><td style="padding:10px 0 10px 0;">Hello admin,</td></tr><tr><td style="padding:10px 0 10px 0;"><a href="[profile-link]">[username]</a> requested to change following information on the profile.</td></tr><tr><td style="padding:10px 0 10px 0;">[pending-data]</td></tr><tr><td style="padding:10px 0 10px 0;">To change the information on profile you need to confirm it by clicking on the button below.</td></tr><tr><td align="left" style="padding:20px 0 10px 0;"><a href="[approval-link]" target="_blank" style="text-decoration:none;"><span style="background-color:#0094c5;padding:12px;border:none;border-radius:6px;text-decoration:none;text-transform:none;color:#ffffff;font-weight:600;letter-spacing:2px;">CONFIRM</span></a><a href="[rejection-link]" target="_blank" style="text-decoration:none"><span style="padding:11px;border:1px solid;border-radius:6px;text-decoration:none;text-transform:none;color:#0094c5;font-weight:300;letter-spacing:2px;margin-left:25px;">IGNORE</span></a></td></tr></table></td></tr></table>', 'actions-pack'),
                'default' =>     __('<table bgcolor="#e2e2e4" border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td style="padding:20px 30px 20px 30px;"><table bgcolor="#ffffff" align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="padding:20px 30px 20px 30px;"><tr><td style="padding:10px 0 10px 0;">Hello admin,</td></tr><tr><td style="padding:10px 0 10px 0;"><a href="[profile-link]">[username]</a> requested to change following information on the profile.</td></tr><tr><td style="padding:10px 0 10px 0;">[pending-data]</td></tr><tr><td style="padding:10px 0 10px 0;">To change the information on profile you need to confirm it by clicking on the button below.</td></tr><tr><td align="left" style="padding:20px 0 10px 0;"><a href="[approval-link]" target="_blank" style="text-decoration:none;"><span style="background-color:#0094c5;padding:12px;border:none;border-radius:6px;text-decoration:none;text-transform:none;color:#ffffff;font-weight:600;letter-spacing:2px;">CONFIRM</span></a><a href="[rejection-link]" target="_blank" style="text-decoration:none"><span style="padding:11px;border:1px solid;border-radius:6px;text-decoration:none;text-transform:none;color:#0094c5;font-weight:300;letter-spacing:2px;margin-left:25px;">IGNORE</span></a></td></tr></table></td></tr></table>', 'actions-pack'),
                'show_label' => true
            ]
        );
        $widget->end_popover();
        $widget->add_control(
            'ap_profile_additional_settings_popover',
            [
                'label' => __( 'Additional Settings', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes'
            ]
        );
        $widget->start_popover();
        $widget->add_control(
            'ap_profile_change_default_url',
            [
                'label' => __( 'Change Default profile URL', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::URL,
                'description' => __('Enter the URL of your newly created profile page. This will redirect the default edit profile url to the new one and adds an <strong>Edit User</strong> link on your users table.', 'actions-pack')
            ]
        );
        $widget->end_popover();
        $widget->end_controls_section();

    }

    public function run( $record, $ajax_handler ) {

	    $have_super_cap = current_user_can('edit_users');

        if( $have_super_cap ){
            $url = parse_url(ap_get_referrer());
            if(isset( $url['query'] )){
                parse_str($url['query'], $path);
                $user_id = (int) $path['user_id'];
                if ( get_userdata( $user_id ) === false ) {
                    $user_id = get_current_user_id();
                }
            }else{
                $user_id = get_current_user_id();
            }
        }
        else{
            $user_id = get_current_user_id();
        }

        if( $user_id === 0 || !current_user_can( 'edit_user', $user_id ) ){
            $ajax_handler->add_error_message(__('You don\'t have permission to edit the profile.' , 'actions-pack'));
            return;
        }

	    // FORM Widget Settings Data
	    $settings = $record->get( 'form_settings' );

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

	    $form_id = (string) $settings['id'];
	    $fields = $settings['ap_profile_fields'];
	    $user_field_list = $admin_field_list = [];

	    $password = []; // Password would be processed separately

        foreach ( $fields as $item ) {

            // Process Password Separately out of the loop
            if($item['ap_profile_user_field'] === 'current_password' || $item['ap_profile_user_field'] === 'new_password' || $item['ap_profile_user_field'] === 'repeat_password'){
                $password[$item['ap_profile_user_field']] = $form_data[$item['ap_profile_form_field']];
                continue;
            }

		    if ( (!empty($item['ap_profile_user_field']) || !empty($item['ap_profile_custom_meta']) ) && !empty($item['ap_profile_form_field']) && $item['ap_profile_is_editable_field'] === 'yes'){
                //&& !empty($form_data[$item['ap_profile_form_field']])

		        if($item['ap_profile_user_field'] === 'custom'){
				    $user_field = $item['ap_profile_custom_meta'];
			    }
			    else{
				    $user_field = $item['ap_profile_user_field'];
			    }

			    $form_field_data = @$form_data[$item['ap_profile_form_field']];

			    // Form field mapped in profile initially but later removed from form fields
			    if(!isset($form_fields[$item['ap_profile_form_field']]['field_type']) ){
			        continue;
                }

			    // Skip if form field type is `Upload` but data is not uploaded
                if( $form_fields[$item['ap_profile_form_field']]['field_type'] === 'upload' && empty($form_field_data)){
                    continue;
                }

                if( is_null( self::$pending_data ) ){
                    self::$pending_data = get_user_meta($user_id, 'ap_pending_changes', true);
                }

                if( !is_array(self::$pending_data) ) {
                    self::$pending_data = [];
                }

                if( ! $have_super_cap ){
                    if( $item['ap_profile_require_user_approval'] === 'yes' || $item['ap_profile_require_admin_approval'] === 'yes' ){

                        $old_value = $this->get_user_field($user_id, $user_field);

                        if( $old_value === $form_field_data ){
                            unset(self::$pending_data[$form_id]['user']['fields'][$user_field]);
                            unset(self::$pending_data[$form_id]['admin']['fields'][$user_field]);
                            continue;
                        }else{
                            if( $item['ap_profile_require_user_approval'] === 'yes'){
                                self::$pending_data[$form_id]['user']['fields'][$user_field]['new_value'] = $form_field_data;
                                self::$pending_data[$form_id]['user']['fields'][$user_field]['old_value'] = $old_value;
                                $user_field_list[] = $form_fields[$item['ap_profile_form_field']]['field_label'];
                            }else{
                                unset(self::$pending_data[$form_id]['user']['fields'][$user_field]);
                            }

                            if( $item['ap_profile_require_admin_approval'] === 'yes' ){
                                self::$pending_data[$form_id]['admin']['fields'][$user_field]['new_value'] = $form_field_data;
                                self::$pending_data[$form_id]['admin']['fields'][$user_field]['old_value'] = $old_value;
                                $admin_field_list[] = $form_fields[$item['ap_profile_form_field']]['field_label'];
                            }else{
                                unset(self::$pending_data[$form_id]['admin']['fields'][$user_field]);
                            }

                            continue;
                        }
                    }
                }else{
                    // I am admin and If I do any changes I don't need any Email confirmation from anyone
                    unset(self::$pending_data[$form_id]['user']['fields'][$user_field]);
                    unset(self::$pending_data[$form_id]['admin']['fields'][$user_field]);
                }

                $result = $this->update_user_field($user_id, $user_field, $form_field_data);

			    if ( is_wp_error($result) ){
			        $ajax_handler->add_error_message($result->get_error_message());
                }
	        }
        }

        $ajax_data['form_id'] = $form_id;
        $user_pending = ! empty( self::$pending_data[$form_id]['user']['fields'] );
        $admin_pending = ! empty( self::$pending_data[$form_id]['admin']['fields'] );

        if( $user_pending || $admin_pending ){
            if( $user_pending ){
                self::$pending_data[$form_id]['user']['key'] = wp_generate_password(20);
                self::$pending_data[$form_id]['user']['message'] = $ajax_data['user_pending_message'] = __('An email has been sent you with the instructions to confirm changes in your ', 'actions-pack') . implode(', ', (array) implode(' and ', array_splice($user_field_list, -2)) ) . __('. Please check your inbox.</br>', 'actions-pack');
            }else{
                unset(self::$pending_data[$form_id]['user']);
            }

            if( $admin_pending ){
                self::$pending_data[$form_id]['admin']['key'] = wp_generate_password(20);
                self::$pending_data[$form_id]['admin']['message'] = $ajax_data['admin_pending_message'] = __('<strong>Note: </strong>Changes in your ', 'actions-pack') . implode(', ', (array) implode(' and ', array_splice($admin_field_list, -2)) ) . __(' needs to be approved by our Team. Once approved it will be automatically updated.', 'actions-pack');
            }else{
                unset(self::$pending_data[$form_id]['admin']);
            }

            update_user_meta($user_id, 'ap_pending_changes', self::$pending_data);
            $ajax_data['post_id'] = (int) $_POST['post_id'];
            $ajax_data['username'] = get_userdata($user_id)->user_login;
            $ajax_data['nonce'] = wp_create_nonce($user_id . 'notify_data_changes');
        }
        else{
            if( empty(self::$pending_data) ){
                delete_user_meta($user_id, 'ap_pending_changes');
            }else{
                unset(self::$pending_data[$form_id]);
                update_user_meta($user_id, 'ap_pending_changes', self::$pending_data);
            }
        }
        $ajax_handler->add_response_data('apProfileChanges', $ajax_data);

        // Process Password Stuff here
        if( !empty($password) ){
            if( array_key_exists('current_password', $password)){
                if( empty($password['current_password']) ){
                    $ajax_handler->add_error_message(__('An error occurred with your password field. Current Password can not be empty.', 'actions-pack'));
                    return;
                }else if (! wp_check_password($password['current_password'], get_userdata($user_id)->user_pass)){
                    $ajax_handler->add_error_message(__('An error occurred with your password field. Your current password is incorrect.', 'actions-pack'));
                    return;
                }
                if(array_key_exists('new_password', $password)){
                    if(empty($password['new_password'])){
                        $ajax_handler->add_error_message(__('An error occurred with your password field. You must enter a password.', 'actions-pack'));
                        return;
                    }
                }
            }
            if(array_key_exists('repeat_password', $password)){
                if($password['new_password'] !== $password['repeat_password']){
                    $ajax_handler->add_error_message(__('New password and Repeat password fields do not match. Try it again.', 'actions-pack'));
                    return;
                }
                if(array_key_exists('new_password', $password)){
                    if(empty($password['new_password'])){
                        $ajax_handler->add_error_message(__('An error occurred with your password field. You must enter a password.', 'actions-pack'));
                        return;
                    }
                }
            }
            if( !empty($password['new_password'])){
                $this->update_user_field($user_id, 'new_password', $password['new_password']);
            }
        }

    }

	public function on_export( $element ) {
	}
}
