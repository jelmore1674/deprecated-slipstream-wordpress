<?php

if ( file_exists( dirname( __FILE__ ) . '/vendor/freemius' ) ) {
    
    if ( !function_exists( 'ap_fs' ) ) {
        function ap_fs()
        {
            global  $ap_fs ;
            
            if ( !isset( $ap_fs ) ) {
                require_once 'vendor/freemius/wordpress-sdk/start.php';
                /*Always check with Freemius Dashboard*/
                $data = [
                    'id'               => '5039',
                    'slug'             => 'actions-pack',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_0e63d2e822678a3063302690c349a',
                    'is_premium'       => true,
                    'is_premium_only'  => true,
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'is_org_compliant' => false,
                    'menu'             => array(
                    'first-path' => 'plugins.php',
                    'contact'    => true,
                    'support'    => false,
                ),
                ];
                $ap_fs = fs_dynamic_init( $data );
            }
            
            return $ap_fs;
        }
        
        /* Init Freemius. */
        ap_fs();
        /* Signal that SDK was initiated */
        do_action( 'ap_fs_loaded' );
    }
    
    
    if ( did_action( 'ap_fs_loaded' ) ) {
        global  $ap_fs ;
        /* Generate error it is not premium */
        if ( !$ap_fs->can_use_premium_code() ) {
            $error = true;
        }
        /* Upgrade URL */
        $upgrade_url = $ap_fs->get_upgrade_url();
        /* Don't show cancel subscription option */
        $ap_fs->add_filter( 'show_deactivation_subscription_cancellation', '__return_false' );
        /* Check plan */
        
        if ( $ap_fs->is_plan( 'silver', true ) ) {
            define( 'AP_IS_SILVER', TRUE );
            define( 'AP_IS_GOLD', FALSE );
        } else {
            
            if ( $ap_fs->is_plan( 'gold', true ) ) {
                define( 'AP_IS_SILVER', TRUE );
                define( 'AP_IS_GOLD', TRUE );
            } else {
                define( 'AP_IS_SILVER', FALSE );
                define( 'AP_IS_GOLD', FALSE );
            }
        
        }
        
        define( 'AP_UPGRADE_TO_SILVER', '<div class="ap-upgrade-message elementor-control-raw-html elementor-panel-alert elementor-panel-alert-warning">The feature or some of the options are not available in your plan. <a href="' . $upgrade_url . '"style="color:#2cabfa"target="_blank">Upgrade</a> to <strong>SILVER</strong> now.</div>' );
        define( 'AP_UPGRADE_TO_GOLD', '<div class="ap-upgrade-message elementor-control-raw-html elementor-panel-alert elementor-panel-alert-warning">The feature or some of the options are not available in your plan. <a href="' . $upgrade_url . '"style="color:#2cabfa"target="_blank">Upgrade</a> to <strong>GOLD</strong> now.</div>' );
        /* Add plugin action links */
        add_filter(
            'plugin_action_links_' . basename( AP_PLUGIN_DIR_PATH ) . '/plugin.php',
            function ( $links ) {
            global  $ap_fs ;
            $links['account'] = '<a href="' . $ap_fs->get_account_url() . '"style="color:#6e72de;font-weight:700">' . __( 'Account', 'actions-pack' ) . '</a>';
            $links['support'] = '<a href="' . $ap_fs->contact_url() . '"style="color:#25cdea;font-weight:700">' . __( 'Support', 'actions-pack' ) . '</a>';
            if ( !AP_IS_GOLD ) {
                $links['upgrade'] = '<a href="' . $ap_fs->get_upgrade_url() . '"style="color:#ea7927;font-weight:700">' . __( 'Upgrade', 'actions-pack' ) . '</a>';
            }
            return $links;
        },
            10,
            1
        );
        /* Uninstall Logic */
        $ap_fs->add_action( 'after_uninstall', 'ap_uninstall_logic' );
    }

} else {
    define( 'AP_IS_SILVER', true );
    define( 'AP_IS_GOLD', true );
    define( 'AP_UPGRADE_TO_SILVER', '' );
    define( 'AP_UPGRADE_TO_GOLD', '' );
    register_uninstall_hook( AP_PLUGIN_FILE_URL, 'ap_uninstall_logic' );
}

function ap_uninstall_logic()
{
    delete_option( 'actions_pack' );
    delete_option( 'elementor_ap_google_sheet_auth_code' );
    delete_option( 'elementor_ap_google_sheet_client_id' );
    delete_option( 'elementor_ap_google_sheet_client_secret' );
    delete_option( 'elementor_ap_google_sheet_credentials_validate' );
    delete_option( 'elementor_ap_sms_account_sid' );
    delete_option( 'elementor_ap_sms_auth_token' );
    delete_option( 'elementor_ap_sms_from_number' );
}
