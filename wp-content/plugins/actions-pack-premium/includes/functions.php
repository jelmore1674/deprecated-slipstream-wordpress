<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 *  Write anything to debug.log file E.g write_log("My first log message")
 */
if (!function_exists('write_log')) {

    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }
}


/**
 * Checks if a channel exists
 * @return integer
 */
function ap_channel_value_exists( $channel, $channelValue ) {
    $user_id = get_users
    (
        [
            'meta_key' => 'user_' . $channel,
            'meta_value' => $channelValue,
            'number' => 1,
            'count_total' => false,
            'fields' => 'ids'
        ]
    );

    if ( $user_id ) {
        return (int) $user_id[0];
    }
    else{
        return false;
    }
}


/**
 * Checks if it is a valid phone number
 * @toDo Improvise validity check.
 * https://stackoverflow.com/questions/14894899/what-is-the-minimum-length-of-a-valid-international-phone-number
 */
function ap_is_phone( $phone ){

    $phone = filter_var($phone, FILTER_SANITIZE_NUMBER_INT);
    $phone = str_replace("-", "", $phone);

    if (strlen($phone) < 10 || strlen($phone) > 14) {
        return false;
    }

    return $phone;
}


/**
 * Send Email
 */
function ap_send_mail( $from, $from_name, $to, $subject, $content_type, $message ){

    $headers = sprintf( 'From: %s <%s>' . "\r\n", $from_name, $from );

    if ( $content_type === 'html' ) {
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    }

    $response = wp_mail($to, $subject, $message, $headers);
    return $response;
}


/**
 * Send SMS (Currently supports Twilio only)
 */
function ap_send_sms($sid, $token, $from, $to, $message){

    $request = [
        'body' => [
            'From' => $from,
            'To' => $to,
            'Body' => $message
        ],
        'headers' => array(
            'Content-type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token)
        ),
    ];

    $url = "https://api.twilio.com/2010-04-01/Accounts/".$sid."/SMS/Messages.json";
    $response = wp_remote_post($url, $request);
    return wp_remote_retrieve_body($response);
}


// https://wordpress.stackexchange.com/questions/333367/handling-nonces-for-actions-from-guests-to-logged-in-users
if ( ! function_exists( 'ap_create_nonce' ) ) {
    /**
     * Creates a cryptographic token tied to a specific action, user, user session,
     * and window of time.
     *
     * @since 2.0.3
     * @since 4.0.0 Session tokens were integrated with nonce creation
     *
     * @param string|int $action Scalar value to add context to the nonce.
     * @return string The token.
     */
    function ap_create_nonce( $action = -1 ) {
        $user = wp_get_current_user();
        $uid  = (int) $user->ID;
        $logged_in = '1-';

        $token = wp_get_session_token();
        $i     = wp_nonce_tick();

        if ( ! $uid ) {
            // Prefix when logged-out nonce
            $logged_in = '0-';

            /** This filter is documented in wp-includes/pluggable.php */
            $uid = apply_filters( 'nonce_user_logged_out', $uid, $action );

            // Use IP instead of user_id
            $uid = $_SERVER['REMOTE_ADDR'];
            $token = $_SERVER['REMOTE_ADDR'];
        }

        return $logged_in . substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
    }
}

if ( ! function_exists( 'ap_verify_nonce' ) ) {
    /**
     * Verify that correct nonce was used with time limit.
     *
     * The user is given an amount of time to use the token, so therefore, since the
     * UID and $action remain the same, the independent variable is the time.
     *
     * @since 2.0.3
     *
     * @param string     $nonce  Nonce that was used in the form to verify
     * @param string|int $action Should give context to what is taking place and be the same when nonce was created.
     * @return false|int False if the nonce is invalid, 1 if the nonce is valid and generated between
     *                   0-12 hours ago, 2 if the nonce is valid and generated between 12-24 hours ago.
     */
    function ap_verify_nonce( $nonce, $action = -1 ) {
        $nonce = (string) $nonce;
        $user  = wp_get_current_user();
        $uid   = (int) $user->ID;
        if ( ! $uid ) {
            /**
             * Filters whether the user who generated the nonce is logged out.
             *
             * @since 3.5.0
             *
             * @param int    $uid    ID of the nonce-owning user.
             * @param string $action The nonce action.
             */
            $uid = apply_filters( 'nonce_user_logged_out', $uid, $action );
        }

        if ( empty( $nonce ) ) {
            return false;
        }

        $token = wp_get_session_token();
        $i     = wp_nonce_tick();

        // Check if nonce is for logged_in or logged_out ('1-' and '0-' respectively)
        if ( substr( $nonce, 0, 2 ) == '0-' ) {
            // Use IP instead of user_id and session token
            $uid = $_SERVER[ 'REMOTE_ADDR' ];
            $token = $_SERVER['REMOTE_ADDR'];
        }

        // Remove nonce prefix
        $nonce = substr( $nonce, 2 );

        // Nonce generated 0-12 hours ago
        $expected = substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );

        if ( hash_equals( $expected, $nonce ) ) {
            return 1;
        }

        // Nonce generated 12-24 hours ago
        $expected = substr( wp_hash( ( $i - 1 ) . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
        if ( hash_equals( $expected, $nonce ) ) {
            return 2;
        }

        /**
         * Fires when nonce verification fails.
         *
         * @since 4.4.0
         *
         * @param string     $nonce  The invalid nonce.
         * @param string|int $action The nonce action.
         * @param WP_User    $user   The current user object.
         * @param string     $token  The user's session token.
         */
        do_action( 'wp_verify_nonce_failed', $nonce, $action, $user, $token );

        // Invalid nonce
        return false;
    }
}

add_action ( 'wp_ajax_ap_update_option_value', function (){
    $options = get_option('actions_pack');
    foreach($_POST['optionValuePairs'] as $option => $value){
        $options[$option] = $value;
    }
    update_option('actions_pack',$options);
    wp_send_json_success('Successfully Updated');
});

/*
 * Update Elementor Form control settings from outside
 */
function ap_elementor_replace_recursive( $elements, $widget_id, $settings ) {
    foreach ( $elements as &$element) {
        if ( $element['id'] === $widget_id ) {
            foreach( $settings as $setting => $value){
                $element['settings'][$setting] = $value;
            }
            return $elements;
        }
        if ( ! empty( $element['elements'] ) ) {
            $subelements = ap_elementor_replace_recursive( $element['elements'], $widget_id, $settings );
            if ( $subelements ) {
                // Inject result back into our array
                $element["elements"] = $subelements;
                return $elements;
            }
        }
    }
}

function ap_get_elementor_control_settings($post_id, $widget_id){
    $elementor = \Elementor\Plugin::$instance;
    $document = $elementor->documents->get( $post_id );
    $widget = \ElementorPro\Modules\Forms\Module::find_element_recursive( $document->get_elements_data(), $widget_id );
    $widget = $elementor->elements_manager->create_element_instance( $widget );
    return $widget->get_settings_for_display();
}

function ap_update_elementor_control_settings($post_id, $widget_id, $settings){
    $elementor = \Elementor\Plugin::$instance;
    $document = $elementor->documents->get( $post_id );
    $elements = $document->get_elements_data();
    $elements = ap_elementor_replace_recursive($elements, $widget_id, $settings);
    $json_value = wp_slash(wp_json_encode( $elements ));
    return update_metadata( 'post', $post_id, '_elementor_data', $json_value );
}

function ap_error_occurred( $ajax_handler, $stop_next_action, $message ){
    // Stop Executing Next Actions
    if( AP_IS_GOLD && $stop_next_action === 'yes'){
        wp_send_json_error([
            'message' => $message,
            'data' => '',
        ]);
    }
    else{
        // Only show error Message
        $ajax_handler->add_error_message( $message );
    }
}

// This is specially for Elementor Form. Since they have 'referrer' field instead of '_wp_http_referer'
// Note: The correct spelling is `referrer` that is used by Elementor
function ap_get_referrer(){
    if ( ! function_exists( 'wp_validate_redirect' ) ) {
        return false;
    }
    if( ! empty( $_REQUEST['referrer'] ) ){
        $ref = wp_unslash( $_REQUEST['referrer'] );
    } elseif ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
        $ref = wp_unslash( $_REQUEST['_wp_http_referer'] );
    } elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
        $ref = wp_unslash( $_SERVER['HTTP_REFERER'] );
    }else{
        $ref = false;
    }
    if ( $ref && wp_unslash( $_SERVER['REQUEST_URI'] ) !== $ref && home_url() . wp_unslash( $_SERVER['REQUEST_URI'] ) !== $ref ) {
        return wp_validate_redirect( $ref, false );
    }
    return false;
}

/*
 * type = info or success or warning
 */
function ap_print_alert( $type = 'info', $title = 'I am the Title', $description = 'I am the Description'){
    ob_start();
    if( is_admin() ){
        ?>
        <div class="notice notice-<?php echo $type ?> is-dismissible">
            <h3><?php echo $title ?></h3>
            <p><?php echo $description ?></p>
        </div>
        <?php
    }
    else {
        ?>
        <div class="elementor-alert elementor-alert-<?php echo $type ?>" style="transition:opacity 1s;margin-bottom:15px;" role="alert">
            <span class="elementor-alert-title"><?php echo $title ?></span>
            <span class="elementor-alert-description"><?php echo $description ?></span>
            <button type="button" class="elementor-alert-dismiss" onclick="t=this.parentElement;t.style.opacity=0;t.addEventListener('transitionend',function(){t.remove()})">
                <span aria-hidden="true">×</span>
                <span class="elementor-screen-only">Dismiss alert</span>
            </button>
        </div>
        <?php
    }
    echo ob_get_clean();
}

/*
 * Get all possible user fields
 * @return array
 */
function ap_get_user_fields_list( $filter = '' ) {
    $fields = [];

    // All Meta Keys
    global $wpdb;
    $metas = $wpdb->get_results( "SELECT distinct $wpdb->usermeta.meta_key FROM $wpdb->usermeta" );
    foreach ($metas as $meta){
        $fields[$meta->meta_key] = $meta->meta_key;
    }

    // Machine Reserved Keys
    $reserved_fields = [
        'ap_data',
        'ap_pending_changes',
        'user_phone',
        'is_email_verified',
        'is_phone_verified',
        'is_manually_verified',
        'ap_pending_data',
        'rich_editing',
        'syntax_highlighting',
        'comment_shortcuts',
        'admin_color',
        'use_ssl',
        'show_admin_bar_front',
        'locale',
        'wp_capabilities',
        'wp_user_level',
        'dismissed_wp_pointers',
        'show_welcome_panel',
        'wp_dashboard_quick_press_last_post_id',
        'session_tokens',
        'elementor_introduction',
        'elementor_preferences',
        'wp_user-settings',
        'wp_user-settings-time',
        'wp_elementor_connect_common_data'
    ];

    $fields = array_diff($fields, $reserved_fields);

    // Extra Fields
    switch ($filter){
        case 'register' :
            $fields['name'] = 'Name';
            $fields['custom'] = 'Custom Meta';
            break;
        case 'edit-profile' :
            $fields['user_login'] = 'User Login';
            $fields['current_password'] = 'Current Password';
            $fields['new_password'] = 'New Password';
            $fields['repeat_password'] = 'Repeat Password';
            $fields['user_nicename'] = 'User Nicename';
            $fields['user_email'] = 'User Email';
            $fields['user_phone'] = 'User Phone';
            $fields['user_url'] = 'User URL';
            $fields['display_name'] = 'Display Name';
            $fields['name'] = 'Name';
            $fields['custom'] = 'Custom Meta';
            break;
        default :
            $fields = [];
    }

    return $fields;
}


/*
 * Make Users Table Responsive and interactive
 */
add_action( 'admin_enqueue_scripts', function($hook){
    if ( 'users.php' != $hook ) {
        return;
    }
    ?>
    <style>
        .tooltip:hover .tooltip-text {
            color: #e4e8ec;
            background-color: black;
            visibility: visible;
            opacity: 1;
            transition: opacity 1s;
            font-size: 16px;
            position: absolute;
            padding-bottom: 5px;
            padding-left: 5px;
            padding-right: 5px;
            border-radius: 5px;
            margin-left: 5px;
            white-space: pre-wrap;
            width:100px;
        }
        .tooltip-text {
            visibility: hidden;
            opacity: 0;
            transition: opacity 1s
        }
        }
        .tooltip-text:hover {
            visibility: visible
        }
        .tooltip {
            cursor: help
        }
    </style>
    <?php
});


/*
 * @return non-array value
 */
function ap_get_array_value_by_string_pattern($array, $str) {
    preg_match_all("#\[(.*?)\]#", $str, $keys, PREG_PATTERN_ORDER);
    if( !empty($keys[1]) ){
        foreach($keys[1] as $key){
            if( !empty($array[$key]) ){
                $array = $array[$key];
            }
        }
    }
    if( is_array($array)){
        return "";
    }else{
        return $array;
    }
}


/*
 * @return array
 */
function ap_update_array_value_by_string_pattern($array, $pattern, $value) {
    $matched = preg_match_all('/\[([^\[\]]+)\]/', $pattern, $keys);

    if ($matched !== false) {
        $arrayKeys = $keys[1];
        $arrayPointer = &$array;

        foreach ($arrayKeys as $k) {
            $arrayPointer = &$arrayPointer[$k];
        }

        $arrayPointer = $value;
        unset($arrayPointer);
    }
    return $array;
}