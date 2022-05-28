<?php

use Elementor\Controls_Manager;
use Elementor\Settings;
use ElementorPro\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Action_Google_Sheet extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    const OPTION_NAME_CLIENT_ID = 'ap_google_sheet_client_id';
    const OPTION_NAME_CLIENT_SECRET = 'ap_google_sheet_client_secret';
    const OPTION_NAME_ACCESS_TOKEN = 'ap_google_sheet_access_token';
    const OPTION_NAME_REFRESH_TOKEN = 'ap_google_sheet_refresh_token';
    const OPTION_NAME_EXPIRES_IN = 'ap_google_sheet_expires_in';

    public function get_name() {
        return 'ap_google_sheet';
    }

    public function get_label() {
        return __( 'Google Sheet', 'actions-pack' );
    }

    public function __construct() {

        if ( is_admin() ) {
            if(!empty($_GET["page"]) && $_GET["page"] == "elementor") {
                $this->enqueue_assets();
            }
            add_action('elementor/admin/after_create_settings/' . Settings::PAGE_ID, [$this, 'register_admin_fields'], 24);
        }
        add_action( 'elementor/editor/after_enqueue_scripts', function() {
            $this->enqueue_assets();
        } );

        add_action( 'wp_ajax_ap_google_sheet_credentials_validate', [ $this, 'credentials_validate' ] );
    }

    /**
     * Register and Enqueue scripts and styles required for this action
     */
    public function enqueue_assets(){
        wp_register_script( 'ap_google_sheet',  AP_PLUGIN_DIR_URL .'assets/js/google-sheet.js', [ 'jquery' ], false, true );
        wp_enqueue_script('ap_google_sheet');
    }

    /**
     * Renew Access Token
     */
    public function renew_access_token( $client_id, $client_secret, $refresh_token ){
        $request = [
            'body' => [],
            'headers' => array(
                'Content-type' => 'application/x-www-form-urlencoded',
            ),
        ];

        $response = wp_remote_post( 'https://www.googleapis.com/oauth2/v4/token?client_id=' . $client_id . '&client_secret=' . $client_secret . '&refresh_token=' . $refresh_token . '&grant_type=refresh_token', $request );

        if( is_wp_error($response) ){
            wp_send_json_error([
                'message' => 'Could not resolve API host. Try again',
            ]);
        }

        $response = json_decode( wp_remote_retrieve_body( $response ) );

        if( property_exists($response, 'error') ){
            wp_send_json_error([
                'message' => $response->error_description,
            ]);
        }

        return $response;
    }

    /**
     * Post data to endpoint server
     */
    public function post_to_google_sheet($sheet_id, $sheet_name, $row, $access_token){

        $body = '{"majorDimension":"ROWS", "values":[[' . $row . ']]}';
        $request = [
            'headers' => array(
                'Content-length' => strlen( $body ),
                'Content-type' => 'application/json',
                'Authorization' => 'OAuth ' . $access_token,
            ),
            'body' => $body,
        ];
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $sheet_id . '/values/'.$sheet_name.'!A1:append?includeValuesInResponse=false&insertDataOption=INSERT_ROWS&responseDateTimeRenderOption=SERIAL_NUMBER&responseValueRenderOption=FORMATTED_VALUE&valueInputOption=USER_ENTERED';

        $response = wp_remote_post($url, $request);

        if( is_wp_error($response) ){
            wp_send_json_error([
                'message' => __('Could not resolve API host. Try again!!!', 'actions-pack'),
            ]);
        }

        $response = json_decode( wp_remote_retrieve_body( $response ) );

        if(empty($response)){
            wp_send_json_error([
                'message' => __('Something Went Wrong. Try Again!!!', 'actions-pack'),
            ]);
        }

        if( property_exists($response, 'error') ){
            wp_send_json_error([
                'message' => $response->error->message,
            ]);
        }

        return $response;
    }

    public function charToInt($char){
        $l = strlen($char);
        $n = 0;
        for($i = 0; $i < $l; $i++)
            $n = $n*26 + ord($char[$i]) - 0x40;

        return $n;
    }

    public function get_meta_data( $meta_type ) {
        $value = '';
        switch ( $meta_type ) {
            case 'date':
                $value = date_i18n(get_option('date_format'));
                break;

            case 'time':
                $value = date_i18n(get_option('time_format'));
                break;

            case 'page_url':
                $value = esc_url_raw( $_POST['referrer'] );
                break;

            case 'user_agent':
                $value = sanitize_textarea_field( $_SERVER['HTTP_USER_AGENT'] );
                break;

            case 'remote_ip':
                $value = Utils::get_client_ip();
                break;

            case 'credit':
                $value = sprintf(__('Powered by Actions Pack (%s)', 'actions-pack'), 'https://actions-pack.com');
                break;
        }

        return $value;
    }
    
    /**
     * Get comma separated user input values in string format which is mapped to columns in Google sheet
     */
    public function get_row( $record, $mapped_fields, $submitted_fields){
        foreach ( $mapped_fields as $item) {
            if( $item['ap_google_sheet_column_id'] === 'custom'){
                $item['ap_google_sheet_column_id'] = $this->charToInt( $item['ap_google_sheet_column_id_custom'] );
            }

            if( $item['ap_google_sheet_form_field'] === 'meta' ){
                $array[$item['ap_google_sheet_column_id']] = $item['ap_google_sheet_meta_data'];
                $submitted_fields[$item['ap_google_sheet_meta_data']] = $this->get_meta_data($item['ap_google_sheet_meta_data']);
            }
            else{
                $array[$item['ap_google_sheet_column_id']] = $item['ap_google_sheet_form_field'];
            }
        }

        $row = '';
        for ($i = 1; $i <= max(array_keys($array)); $i++){

            if( array_key_exists($i, $array)){
                $row .= '"'.$submitted_fields[$array[$i]].'",';
            }
            else{
                $row .= '"",';
            }
        }
        $row = rtrim($row, ',');

        return $row;
    }

    /**
     * Register settings option in Elementor Admin page
     */
    public function register_admin_fields( Settings $settings ) {
        $settings->add_section( Settings::TAB_INTEGRATIONS, 'ap_google_sheet', [
            'label' => __( '<hr id="ap_google_sheet">Google Sheet', 'actions-pack' ),
            'callback' => function() {
                echo sprintf( __('Enable Google Sheets API <a href="%s" target="_blank">here</a>, enter a project name, select application type as web server, paste <span class="ap-google-sheet-redirect-uri"><strong>%s</strong></span> as redirect URI and get the credentials.', 'actions-pack'), 'https://developers.google.com/sheets/api/quickstart/php#step_1_turn_on_the', admin_url('admin-ajax.php'));
            },
            'fields' => [
                self::OPTION_NAME_CLIENT_ID => [
                    'label' => __( 'Client ID', 'actions-pack' ),
                    'field_args' => [
                        'type' => 'text',
                    ]
                ],
                self::OPTION_NAME_CLIENT_SECRET => [
                    'label' => __( 'Client Secret', 'actions-pack' ),
                    'field_args' => [
                        'type' => 'text',
                    ]
                ],
                self::OPTION_NAME_ACCESS_TOKEN => [
                    'field_args' => [
                        'type' => 'hidden',
                    ]
                ],
                self::OPTION_NAME_REFRESH_TOKEN => [
                    'field_args' => [
                        'type' => 'hidden',
                    ]
                ],
                self::OPTION_NAME_EXPIRES_IN => [
                    'field_args' => [
                        'type' => 'hidden',
                    ]
                ],
                'ap_google_sheet_credentials_validate' => [
                    'field_args' => [
                        'type' => 'raw_html',
                        'html' => '<style>.elementor_'.self::OPTION_NAME_ACCESS_TOKEN.',.elementor_'.self::OPTION_NAME_REFRESH_TOKEN.',.elementor_'.self::OPTION_NAME_EXPIRES_IN.'{display:none}</style><div><button class="button elementor-button-spinner">'.__('Validate Credentials', 'actions-pack').'</button></div>'
                    ]
                ]
            ],
        ] );
    }

    /**
     * Register Settings on Elementor Editor pannel
     */
    public function register_settings_section( $widget ) {

        $widget->start_controls_section(
            'ap_section_google_sheet',
            [
                'label' => __( 'Google Sheet', 'actions-pack' ),
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );

        $widget->add_control(
            'ap_google_sheet_api_popover',
            [
                'label' => __( 'API Credentials <span class="ap-required">*</span>', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes',
            ]
        );
        $widget->start_popover();

        $widget->add_control(
            'ap_google_sheet_credentials_source',
            [
                'label' => __( 'API Source', 'actions-pack' ),
                'type' => Controls_Manager::SELECT,
                'label_block' => false,
                'options' =>[
                    'default' => 'Default',
                    'custom' => 'Custom'
                ],
                'default' => 'default',
                'classes' => 'elementor_ap_google_sheet_credentials_source',
            ]
        );

        $widget->add_control(
            'ap_google_sheet_credentials_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf('%s <a style="color: #0b76ef" href="%s" target="_blank">%s</a>. %s',__('To use default credentials, make sure you have already set the credentials', 'actions-pack'),admin_url('admin.php?page=elementor#tab-integrations'), __('here', 'actions-pack'), __('You can use this field to set a custom credential for current form only', 'actions-pack')),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
                'condition' => [
                    'ap_google_sheet_credentials_source' => 'default'
                ]
            ]
        );

        $widget->add_control(
            'ap_google_sheet_custom_credentials_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf( __('Enable Google Sheets API <a href="%s" target="_blank" style="color:cornflowerblue">here</a>, enter a project name, select application type as web server, paste <span class="ap-google-sheet-redirect-uri" style="font-size:10px"><strong>%s</strong></span> as redirect URI and get the credentials.', 'actions-pack'), 'https://developers.google.com/sheets/api/quickstart/php#step_1_turn_on_the', admin_url('admin-ajax.php')),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
                'condition' => [
                    'ap_google_sheet_credentials_source' => 'custom'
                ]
            ]
        );

        $widget->add_control(
            self::OPTION_NAME_CLIENT_ID,
            [
                'label' => __( 'Client ID', 'actions-pack' ),
                'type' => Controls_Manager::TEXT,
                'label_block' => false,
                'classes' => 'elementor_'.self::OPTION_NAME_CLIENT_ID,
                'condition' => [
                    'ap_google_sheet_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            self::OPTION_NAME_CLIENT_SECRET,
            [
                'label' => __( 'Client Secret', 'actions-pack' ),
                'type' => Controls_Manager::TEXT,
                'label_block' => false,
                'classes' => 'elementor_'.self::OPTION_NAME_CLIENT_SECRET,
                'condition' => [
                    'ap_google_sheet_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            self::OPTION_NAME_ACCESS_TOKEN,
            [
                'type' => Controls_Manager::HIDDEN,
                'classes' => 'elementor_'.self::OPTION_NAME_CLIENT_SECRET,
                'condition' => [
                    'ap_google_sheet_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            self::OPTION_NAME_REFRESH_TOKEN,
            [
                'type' => Controls_Manager::HIDDEN,
                'classes' => 'elementor_'.self::OPTION_NAME_CLIENT_SECRET,
                'condition' => [
                    'ap_google_sheet_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            self::OPTION_NAME_EXPIRES_IN,
            [
                'type' => Controls_Manager::HIDDEN,
                'classes' => 'elementor_'.self::OPTION_NAME_CLIENT_SECRET,
                'condition' => [
                    'ap_google_sheet_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            'ap_google_sheet_credentials_validate',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf( '<button class="elementor-button elementor-button-default" style="float: right"><i class="fa fa-refresh"></i>%s</button>', __( 'Validate Credentials', 'actions-pack' ) ),
                'condition' => [
                    'ap_google_sheet_credentials_source!' => 'default'
                ],
                'content_classes' => 'elementor_ap_google_sheet_credentials_validate'
            ]
        );

        $widget->end_popover();

        $widget->add_control(
            'ap_google_spreadsheet_popover',
            [
                'label' => __( 'Spreadsheet Details <span class="ap-required">*</span>', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes',
            ]
        );
        $widget->start_popover();

        $widget->add_control(
            'ap_google_sheet_url',
            [
                'label' => __( 'Spreadsheet URL', 'actions-pack' ),
                'type' => Controls_Manager::TEXTAREA,
                'placeholder' => 'https://docs.google.com/spreadsheets/d/18_qKwKzyFCHtyJ0/edit#gid=11503',
                'rows' => 4,
            ]
        );

        $widget->add_control(
            'ap_google_sheet_id',
            [
                'type' => Controls_Manager::HIDDEN,
            ]
        );

        $widget->add_control(
            'ap_google_sheet_name',
            [
                'label' => __( 'Sheet Name', 'actions-pack' ),
                'type' => Controls_Manager::TEXT,
                'default' => 'Sheet1',
                'placeholder' => 'Sheet1 or Sheet2'
            ]
        );

        $widget->end_popover();

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'ap_google_sheet_form_field',
            [
                'label' => __( 'Form Field', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [],
                'show_label' => true,
            ]
        );

        $repeater->add_control(
            'ap_google_sheet_meta_data',
            [
                'label' => __( 'Meta Data', 'actions-pack' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'date' => __( 'Date', 'actions-pack' ),
                    'time' => __( 'Time', 'actions-pack' ),
                    'page_url' => __( 'Page URL', 'actions-pack' ),
                    'user_agent' => __( 'User Agent', 'actions-pack' ),
                    'remote_ip' => __( 'Remote IP', 'actions-pack' ),
                    'credit' => __( 'Credit', 'actions-pack' ),
                ],
                'render_type' => 'none',
                'condition' => [
                    'ap_google_sheet_form_field' => 'meta'
                ]
            ]
        );

        $repeater->add_control(
            'ap_google_sheet_column_id',
            [
                'label' => __( 'Sheet Column', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'custom' => 'CUSTOM', 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z'
                ],
                'show_label' => true,
            ]
        );

        $repeater->add_control(
            'ap_google_sheet_column_id_custom',
            [
                'label' => __( 'Column ID', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'show_label' => true,
                'condition' =>[
                    'ap_google_sheet_column_id' => 'custom'
                ],
                'placeholder' => 'E.g. AB or AC'
            ]
        );

        $widget->add_control(
            'ap_google_sheet_mapping',
            [
                'label' => __( 'Field Mapping', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'ap_google_sheet_form_field' => 'name',
                        'ap_google_sheet_column_id' => 1,
                    ],
                    [
                        'ap_google_sheet_form_field' => 'email',
                        'ap_google_sheet_column_id' => 2,
                    ],
                    [
                        'ap_google_sheet_form_field' => 'message',
                        'ap_google_sheet_column_id' => 3,
                    ],
                ],
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

        // Form data
        $raw_fields = $record->get('fields');
        $form_data = [];
        foreach ($raw_fields as $id => $field) {
            $form_data[$id] = $field['value'];
        }

        // Form Settings
        $form_settings = $record->get('form_settings');
        $source = $form_settings['ap_google_sheet_credentials_source'];

        if( $source === 'default'){
            $client_id = get_option('elementor_ap_google_sheet_client_id');
            $client_secret = get_option('elementor_ap_google_sheet_client_secret');
            $access_token = get_option('elementor_ap_google_sheet_access_token');
            $refresh_token = get_option('elementor_ap_google_sheet_refresh_token');
            $expires_in = get_option('elementor_ap_google_sheet_expires_in');
            if(time() > $expires_in){
                $response = $this->renew_access_token($client_id, $client_secret, $refresh_token);
                $access_token = $response->access_token;
                $expires_in = $response->expires_in;
                update_option('elementor_ap_google_sheet_access_token', $access_token);
                update_option('elementor_ap_google_sheet_expires_in', time() + $expires_in);
            }
        }
        else{
            $client_id = $form_settings['ap_google_sheet_client_id'];
            $client_secret = $form_settings['ap_google_sheet_client_secret'];
            $access_token = $form_settings['ap_google_sheet_access_token'];
            $refresh_token = $form_settings['ap_google_sheet_refresh_token'];
            $expires_in = $form_settings['ap_google_sheet_expires_in'];
            if(time() > $expires_in){
                $response = $this->renew_access_token($client_id, $client_secret, $refresh_token);
                $access_token = $response->access_token;
                $expires_in = $response->expires_in;
                $post_id = (int) $_POST['post_id'];
                $form_id = (string) $_POST['form_id'];
                ap_update_elementor_control_settings($post_id, $form_id, [
                    'ap_google_sheet_access_token' => $access_token,
                    'ap_google_sheet_expires_in' => time() + $expires_in
                ]);
            }
        }

        // Post to Google Sheet
        $sheet_id = $form_settings['ap_google_sheet_id'];
        $sheet_name = $form_settings['ap_google_sheet_name'];
        $row = $this->get_row($record, $form_settings['ap_google_sheet_mapping'], $form_data);
        $this->post_to_google_sheet( $sheet_id, $sheet_name, $row, $access_token );
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
            $element[self::OPTION_NAME_CLIENT_ID],
            $element[self::OPTION_NAME_CLIENT_SECRET],
            $element[self::OPTION_NAME_ACCESS_TOKEN],
            $element[self::OPTION_NAME_REFRESH_TOKEN],
            $element[self::OPTION_NAME_EXPIRES_IN]
        );
    }

}
