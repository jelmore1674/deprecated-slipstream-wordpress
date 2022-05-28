<?php

use Elementor\Controls_Manager;
use Elementor\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Action_SMS extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    const ACTION_NAME = 'SMS';
    const ACTION_MACHINE_NAME = 'ap_sms';

    const OPTION_NAME_ACCOUNT_SID = self::ACTION_MACHINE_NAME.'_account_sid';
    const OPTION_NAME_AUTH_TOKEN = self::ACTION_MACHINE_NAME.'_auth_token';
	const OPTION_NAME_FROM_NUMBER = self::ACTION_MACHINE_NAME.'_from_number';

	public function get_name() {
        return self::ACTION_MACHINE_NAME;
    }

    public function get_label() {
        return __( self::ACTION_NAME, 'actions-pack' );
    }

    public function __construct() {

        if ( is_admin() ) {
            add_action( 'elementor/admin/after_create_settings/' . Settings::PAGE_ID, [ $this, 'register_admin_fields' ], 24 );
        }
    }

    /**
     * Register settings option in Elementor Admin page
     */
    public function register_admin_fields( Settings $settings ) {
        $settings->add_section( Settings::TAB_INTEGRATIONS, self::ACTION_MACHINE_NAME, [
            'callback' => function() {
                echo '<hr id="' . self::ACTION_MACHINE_NAME . '"><h2>' . esc_html__( self::ACTION_NAME, 'actions-pack' ) . '</h2>';
            },
            'fields' => [
	            self::OPTION_NAME_ACCOUNT_SID => [
		            'label' => __( 'Account SID', 'actions-pack' ),
		            'field_args' => [
			            'type' => 'text',
		            ],
	            ],
                self::OPTION_NAME_AUTH_TOKEN => [
                    'label' => __( 'Auth Token', 'actions-pack' ),
                    'field_args' => [
                        'type' => 'text',
                    ],
                ],
	            self::OPTION_NAME_FROM_NUMBER => [
		            'label' => __( 'From Number', 'actions-pack' ),
		            'field_args' => [
			            'type' => 'text',
			            'desc' => sprintf('%s <a href="%s" target="_blank"> %s</a> %s', __('Click', 'actions-pack'), 'https://twilio.com/referral/nWJHpb',__('here', 'actions-pack'), __('to get your Twilio Account SID, Auth Token and Phone Number.', 'actions-pack') ),
		            ],
	            ],
            ],
        ] );
    }

    /**
     * Register Settings on Elementor Editor panel
     */
    public function register_settings_section( $widget ) {

        $widget->start_controls_section(
            self::ACTION_MACHINE_NAME,
            [
                'label' => __( self::ACTION_NAME, 'actions-pack' ),
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );

        $widget->add_control(
            self::ACTION_MACHINE_NAME.'_credentials_source',
            [
                'label' => __( 'API Source', 'actions-pack' ),
                'type' => Controls_Manager::SELECT,
                'label_block' => false,
                'options' =>[
                    'default' => 'Default',
                    'custom' => 'Custom'
                ],
                'default' => 'default',
                'classes' => 'elementor_'.self::ACTION_MACHINE_NAME.'_credentials_source',
            ]
        );

        $widget->add_control(
            self::ACTION_MACHINE_NAME.'_credentials_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf('%s <a style="color: #0b76ef" href="%s" target="_blank">%s</a>. %s',__('To use default credentials, make sure you have already set the credentials', 'actions-pack'),admin_url('admin.php?page=elementor#tab-integrations'), __('here', 'actions-pack'), __('You can use this field to set a custom credential for current form only', 'actions-pack')),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
                'condition' => [
                    self::ACTION_MACHINE_NAME.'_credentials_source' => 'default'
                ]
            ]
        );

	    $widget->add_control(
		    self::ACTION_MACHINE_NAME.'_custom_source_notice',
		    [
			    'type' => Controls_Manager::RAW_HTML,
			    'raw' => sprintf('%s <a href="%s" target="_blank" style="color: #0b76ef"> %s </a> %s', __('Click', 'actions-pack'), 'https://twilio.com/referral/nWJHpb',__('here', 'actions-pack'), __('to get your Twilio Account SID, Auth Token and Phone number', 'actions-pack') ),
			    'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
			    'condition' => [
				    self::ACTION_MACHINE_NAME.'_credentials_source' => 'custom'
			    ]
		    ]
	    );

	    $widget->add_control(
		    self::OPTION_NAME_ACCOUNT_SID,
		    [
			    'label' => __( 'Account SID', 'actions-pack' ),
			    'type' => Controls_Manager::TEXT,
			    'label_block' => false,
			    'condition' => [
				    self::ACTION_MACHINE_NAME.'_credentials_source' => 'custom'
			    ]
		    ]
	    );

	    $widget->add_control(
		    self::OPTION_NAME_AUTH_TOKEN,
		    [
			    'label' => __( 'Auth Token', 'actions-pack' ),
			    'type' => Controls_Manager::TEXT,
			    'label_block' => false,
			    'condition' => [
				    self::ACTION_MACHINE_NAME.'_credentials_source' => 'custom'
			    ]
		    ]
	    );

	    $widget->add_control(
		    self::OPTION_NAME_FROM_NUMBER,
		    [
			    'label' => __( 'From Number', 'actions-pack' ),
			    'type' => Controls_Manager::TEXT,
			    'label_block' => false,
			    'placeholder' => '+10123456789',
			    'condition' => [
				    self::ACTION_MACHINE_NAME.'_credentials_source' => 'custom'
			    ]
		    ]
	    );

	    $widget->add_control(
		    self::ACTION_MACHINE_NAME.'_to_number',
		    [
			    'label' => __( 'To Number', 'actions-pack' ),
			    'type' => Controls_Manager::TEXT,
			    'label_block' => false,
			    'placeholder' => '+19876543210',
		    ]
	    );

	    $widget->add_control(
		    self::ACTION_MACHINE_NAME.'_message',
		    [
			    'label' => __( 'Message', 'actions-pack' ),
			    'type' => Controls_Manager::TEXTAREA,
			    'placeholder' => 'Your message here',
			    'default' => '[all-fields]',
			    'description' => __('You may enter any shortcode here. If you want to send form field individually, copy the shortcode that appears inside each field and paste it above.','actions-pack')
		    ]
	    );

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

	    $settings = $record->get('form_settings');

	    // Get submitted Form data and normalize it to $field['ID'] format
	    $raw_fields = $record->get('fields');
	    $submitted_fields = [];
	    foreach ($raw_fields as $id => $field) {
		    $submitted_fields[$id] = $field['value'];
	    }

	    if ($settings[self::ACTION_MACHINE_NAME.'_credentials_source'] === 'default' ){
		    $sid = get_option('elementor_'.self::OPTION_NAME_ACCOUNT_SID);
		    $token = get_option('elementor_'.self::OPTION_NAME_AUTH_TOKEN);
		    $from_number = get_option('elementor_'.self::OPTION_NAME_FROM_NUMBER);
	    }else{
		    $sid = $settings[self::OPTION_NAME_ACCOUNT_SID];
		    $token = $settings[self::OPTION_NAME_AUTH_TOKEN];
		    $from_number = $settings[self::OPTION_NAME_FROM_NUMBER];
	    }

	    $to_number = $record->replace_setting_shortcodes($settings[self::ACTION_MACHINE_NAME.'_to_number']);
	    $message = $record->replace_setting_shortcodes($settings[self::ACTION_MACHINE_NAME.'_message']);

	    $respose = ap_send_sms($sid,$token,$from_number,$to_number,$message);

	    $respose = json_decode($respose);

	     if (empty($respose) || $respose->status !== "queued"){
	     	$ajax_handler->add_error_message(__('Something went wrong. Try again later.','actions-pack'));
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
	}
}
