<?php

use Elementor\Controls_Manager;
use Elementor\Settings;
use ElementorPro\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Action_Zoho_Crm extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    const OPTION_NAME_CLIENT_ID = 'ap_zoho_crm_client_id';
    const OPTION_NAME_CLIENT_SECRET = 'ap_zoho_crm_client_secret';
    const OPTION_NAME_ACCESS_TOKEN = 'ap_zoho_crm_access_token';
    const OPTION_NAME_REFRESH_TOKEN = 'ap_zoho_crm_refresh_token';
    const OPTION_NAME_EXPIRES_IN = 'ap_zoho_crm_expires_in';
    const OPTION_NAME_API_DOMAIN = 'ap_zoho_crm_api_domain';
    const OPTION_NAME_ACCOUNTS_SERVER = 'ap_zoho_crm_accounts_server';

    public function get_name() {
        return 'ap_zoho_crm';
    }

    public function get_label() {
        return __( 'Zoho CRM', 'actions-pack' );
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

        add_action( 'wp_ajax_ap_zoho_crm_credentials_validate', [ $this, 'credentials_validate' ] );
        add_action( 'wp_ajax_ap_zoho_crm_get_module_names', [ $this, 'get_module_names' ] );
        add_action( 'wp_ajax_ap_zoho_crm_get_module_fields', [ $this, 'get_module_fields' ] );
    }

    /**
     * Register and Enqueue scripts and styles required for this action
     */
    public function enqueue_assets(){
        wp_register_script( 'ap_zoho_crm',  AP_PLUGIN_DIR_URL .'assets/js/zoho-crm.js', [ 'jquery' ], false, true );
        wp_enqueue_script('ap_zoho_crm');
    }

    public function get_settings( $post_id, $form_id ){
        $elementor = \Elementor\Plugin::$instance;
        $document = $elementor->documents->get( $post_id );
        $form = \ElementorPro\Modules\Forms\Module::find_element_recursive( $document->get_elements_data(), $form_id );
        $widget = $elementor->elements_manager->create_element_instance( $form );
        return $widget->get_settings_for_display();
    }

    /*
     * Validate credentials and generate Access Token , Refresh Token, Expires-in for first Time
     */
    public function credentials_validate(){
        $accounts_server = (string) $_POST['accounts_server'];
        $client_id = (string) $_POST['client_id'];
        $client_secret = (string) $_POST['client_secret'];
        $code = (string) $_POST['code'];
        $redirect_url = admin_url('admin-ajax.php');
        $url_get_token = $accounts_server.'/oauth/v2/token?client_id='.$client_id.'&grant_type=authorization_code&client_secret='.$client_secret.'&redirect_uri='.$redirect_url.'&code='.$code;
        $response = wp_remote_post($url_get_token, array());

        if( 200 === wp_remote_retrieve_response_code( $response )){
            $response = json_decode( wp_remote_retrieve_body( $response ) );

            if(property_exists($response, 'access_token')){
                $access_token = $response->access_token;
                $refresh_token = $response->refresh_token;
                $api_domain = $response->api_domain;
                $expires_in = $response->expires_in;
                $source = (string) $_POST['source'];
                if(  $source === 'default' ){
                    update_option('elementor_'.self::OPTION_NAME_ACCOUNTS_SERVER, $accounts_server);
                    update_option('elementor_'.self::OPTION_NAME_API_DOMAIN, $api_domain);
                    update_option('elementor_'.self::OPTION_NAME_ACCESS_TOKEN, $access_token);
                    update_option('elementor_'.self::OPTION_NAME_REFRESH_TOKEN, $refresh_token);
                    update_option('elementor_'.self::OPTION_NAME_EXPIRES_IN, time() + $expires_in);
                    wp_send_json_success('success');
                }
                else{
                    wp_send_json_success([
                        'access_token' => $access_token,
                        'refresh_token' => $refresh_token,
                        'api_domain' => $api_domain,
                        'expires_in' => $expires_in,
                        'accounts_server' => $accounts_server,
                    ]);
                }
            }
        }
    }

    /*
     * Get Module Names
     */
    function get_module_names(){
        $source = (string) $_POST['source'];

        if( 'default' === $source){
            $api_domain = get_option('elementor_ap_zoho_crm_api_domain');
            $access_token = get_option('elementor_ap_zoho_crm_access_token');
            $expires_in = get_option('elementor_ap_zoho_crm_expires_in');
            if( time() > $expires_in){
                $accounts_server = get_option('elementor_ap_zoho_crm_accounts_server');
                $client_id = get_option('elementor_ap_zoho_crm_client_id');
                $client_secret = get_option('elementor_ap_zoho_crm_client_secret');
                $refresh_token = get_option('elementor_ap_zoho_crm_refresh_token');
                $response = $this->renew_access_token($accounts_server, $client_id, $client_secret, $refresh_token);
                $access_token = $response->access_token;
                $expires_in = $response->expires_in;
                update_option('elementor_ap_zoho_crm_access_token', $access_token);
                update_option('elementor_ap_zoho_crm_expires_in', time() + $expires_in);
            }
        }
        else{
            $settings = json_decode(wp_unslash($_POST['customApiData']));
            $api_domain = $settings->api_domain;
            $access_token = $settings->access_token;
            if( time() > $settings->expires_in ){
                $response = $this->renew_access_token( $settings->accounts_server, $settings->client_id, $settings->client_secret, $settings->refresh_token);
                $access_token = $response->access_token;
                $expires_in = $response->expires_in;
                ap_update_elementor_control_settings($settings->post_id, $settings->form_id, [
                    'ap_zoho_crm_access_token' => $access_token,
                    'ap_zoho_crm_expires_in' => time() + $expires_in
                ]);
            }
        }

        $response = wp_remote_get($api_domain . '/crm/v2/settings/modules',
            [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                ]
            ]
        );

        if( 200 === wp_remote_retrieve_response_code( $response )){
            $response = json_decode( wp_remote_retrieve_body( $response ) );
            if(property_exists($response, 'modules')){
                $modules = $response->modules;
                $module_names = [];
                foreach ($modules as $module){
                    if( $module->editable ){
                        $module_names[$module->api_name] = $module->module_name;
                    }
                }
                wp_send_json_success($module_names);
            }
        }

    }

    /*
     * Get Module Fields
     */
    function get_module_fields(){
        $module = (string) $_POST['module'];
        $source = (string) $_POST['source'];

        if( 'default' === $source){
            $api_domain = get_option('elementor_ap_zoho_crm_api_domain');
            $access_token = get_option('elementor_ap_zoho_crm_access_token');
            $expires_in = get_option('elementor_ap_zoho_crm_expires_in');
            if( time() > $expires_in){
                $accounts_server = get_option('elementor_ap_zoho_crm_accounts_server');
                $client_id = get_option('elementor_ap_zoho_crm_client_id');
                $client_secret = get_option('elementor_ap_zoho_crm_client_secret');
                $refresh_token = get_option('elementor_ap_zoho_crm_refresh_token');
                $response = $this->renew_access_token($accounts_server, $client_id, $client_secret, $refresh_token);
                $access_token = $response->access_token;
                $expires_in = $response->expires_in;
                update_option('elementor_ap_zoho_crm_access_token', $access_token);
                update_option('elementor_ap_zoho_crm_expires_in', time() + $expires_in);
            }
        }
        else{
            $settings = json_decode(wp_unslash($_POST['customApiData']));
            $api_domain = $settings->api_domain;
            $access_token = $settings->access_token;
            if( time() > $settings->expires_in ){
                $response = $this->renew_access_token( $settings->accounts_server, $settings->client_id, $settings->client_secret, $settings->refresh_token);
                $access_token = $response->access_token;
                $expires_in = $response->expires_in;
                ap_update_elementor_control_settings($settings->post_id, $settings->form_id, [
                    'ap_zoho_crm_access_token' => $access_token,
                    'ap_zoho_crm_expires_in' => time() + $expires_in
                ]);
            }
        }

        $response = wp_remote_get($api_domain . '/crm/v2/settings/fields?module=' . $module,
            [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                ]
            ]
        );

        if( 200 === wp_remote_retrieve_response_code( $response )){
            $response = json_decode( wp_remote_retrieve_body( $response ) );
            if(property_exists($response, 'fields')){
                $fields = $response->fields;
                $module_fields = '';
                foreach ($fields as $field){
                    $module_fields .= '<option value="' . $field->api_name .'">' . $field->field_label . '</option>';
                }
                wp_send_json_success($module_fields);
            }
        }
    }

    /**
     * Renew Access Token
     */
    public function renew_access_token( $accounts_server, $client_id, $client_secret, $refresh_token ){
        $request = [
            'body' => [],
            'headers' => array(
                'Content-type' => 'application/x-www-form-urlencoded',
            ),
        ];

        $response = wp_remote_post( $accounts_server . '/oauth/v2/token?client_id=' . $client_id . '&client_secret=' . $client_secret . '&refresh_token=' . $refresh_token . '&grant_type=refresh_token&location=in', $request );

        if( 200 === wp_remote_retrieve_response_code( $response )){
            $response = json_decode( wp_remote_retrieve_body( $response ) );
            if(property_exists($response, 'access_token')){
                return $response;
            }
        }
        else{
            wp_send_json_error();
        }
    }

    /**
     * Post data to endpoint server
     */
    public function post_to_zoho_crm( $api_domain, $module, $access_token, $data, $trigger, $update_duplicate){
        $body = '{"data":['.json_encode($data).'], "trigger":'.json_encode($trigger).'}';
        $request = [
            'headers' => array(
                'Content-length' => strlen( $body ),
                'Content-type' => 'application/json',
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
            ),
            'body' => $body,
        ];
        $url = $api_domain.'/crm/v2/'.$module. ( $update_duplicate ? '/upsert' : '');

        $response = wp_remote_post( $url, $request );

        if( is_wp_error($response)){
            wp_send_json_error([
                'message' => 'Unable to resolve API',
            ]);
        }

        $response = json_decode( wp_remote_retrieve_body( $response ) );

        if( property_exists($response, 'data')){

            $data = $response->data[0];
            $code = $data->code;

            if( $code !== 'SUCCESS' ){
                switch ( $code ){
                    case 'MANDATORY_NOT_FOUND' :
                        $message = __('Mandatory Field <strong>', 'actions-pack') . str_replace( '_', ' ', $data->details->api_name) .__('</strong> is Missing', 'actions-pack');
                        break;
                    case 'INVALID_DATA' :
                        $message = __('Invalid Data was Entered for Field: <strong>', 'actions-pack') . $data->details->api_name .'</strong>';
                        break;
                    case 'DUPLICATE_DATA' :
                        $message = '<strong>' . $data->details->api_name . '</strong> ' . __('already exists.', 'actions-pack');
                        break;
                    default :
                        $message = $code;
                }
                wp_send_json_error([
                    'message' => $message,
                    'data' => $data->details
                ]);
            }
        }
        else{
            wp_send_json_error([
                'message' => $response->message,
                'data' => $response->code
            ]);
        }
    }

    /*
     * Retrieve Form Meta Data
     */
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
     * Settings option in Elementor Admin page
     */
    public function register_admin_fields( Settings $settings ) {
        $settings->add_section( Settings::TAB_INTEGRATIONS, 'ap_zoho_crm', [
            'label' => __( '<hr id="ap_zoho_crm">Zoho CRM', 'actions-pack' ),
            'callback' => function() {
                echo sprintf( __('Enable Zoho CRM API <a href="%s" target="_blank">here</a>, add a client, choose <strong>Server-based Applications</strong>, give a name, enter your home page url, paste <span class="ap_zoho_crm_redirect_uri"><strong>%s</strong></span> as redirect URI and get the credentials.', 'actions-pack'), 'https://api-console.zoho.com', admin_url('admin-ajax.php'));
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
                'ap_zoho_crm_credentials_validate' => [
                    'field_args' => [
                        'type' => 'raw_html',
                        'html' => '<div><button class="button elementor-button-spinner">'.__('Validate Credentials', 'actions-pack').'</button></div>'
                    ]
                ]
            ],
        ] );
    }

    /**
     * Settings on Elementor Editor panel
     */
    public function register_settings_section( $widget ) {

        $widget->start_controls_section(
            'ap_section_zoho_crm',
            [
                'label' => __( 'Zoho CRM', 'actions-pack' ),
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );

        $widget->add_control(
            'ap_zoho_crm_api_popover',
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
            'ap_zoho_crm_credentials_source',
            [
                'label' => __( 'API Source', 'actions-pack' ),
                'type' => Controls_Manager::SELECT,
                'label_block' => false,
                'options' =>[
                    'default' => 'Default',
                    'custom' => 'Custom'
                ],
                'default' => 'default',
                'classes' => 'elementor_ap_zoho_crm_credentials_source',
            ]
        );

        $widget->add_control(
            'ap_zoho_crm_credentials_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf('%s <a style="color: #0b76ef" href="%s" target="_blank">%s</a>. %s',__('To use default credentials, make sure you have already set the credentials', 'actions-pack'),admin_url('admin.php?page=elementor#tab-integrations'), __('here', 'actions-pack'), __('You can use this field to set a custom credential for current form only', 'actions-pack')),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
                'condition' => [
                    'ap_zoho_crm_credentials_source' => 'default'
                ]
            ]
        );

        $widget->add_control(
            'ap_zoho_crm_custom_credentials_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf( __('Enable Zoho CRM API <a  style="color: #0b76ef" href="%s" target="_blank">here</a>, add a client, choose <strong>Server-based Applications</strong>, give a name, enter your home page url, paste <span class="ap_zoho_crm_redirect_uri"><strong>%s</strong></span> as redirect URI and get the credentials.', 'actions-pack'), 'https://api-console.zoho.com', admin_url('admin-ajax.php')),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
                'condition' => [
                    'ap_zoho_crm_credentials_source' => 'custom'
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
                    'ap_zoho_crm_credentials_source!' => 'default'
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
                    'ap_zoho_crm_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            self::OPTION_NAME_ACCESS_TOKEN,
            [
                'type' => Controls_Manager::HIDDEN,
                'condition' => [
                    'ap_zoho_crm_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            self::OPTION_NAME_REFRESH_TOKEN,
            [
                'type' => Controls_Manager::HIDDEN,
                'condition' => [
                    'ap_zoho_crm_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            self::OPTION_NAME_EXPIRES_IN,
            [
                'type' => Controls_Manager::HIDDEN,
                'condition' => [
                    'ap_zoho_crm_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            self::OPTION_NAME_API_DOMAIN,
            [
                'type' => Controls_Manager::HIDDEN,
                'condition' => [
                    'ap_zoho_crm_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            self::OPTION_NAME_ACCOUNTS_SERVER,
            [
                'type' => Controls_Manager::HIDDEN,
                'condition' => [
                    'ap_zoho_crm_credentials_source!' => 'default'
                ]
            ]
        );

        $widget->add_control(
            'ap_zoho_crm_credentials_validate',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf( '<button class="elementor-button elementor-button-default" style="float: right"><i class="fa fa-refresh"></i>%s</button>', __( 'Validate Credentials', 'actions-pack' ) ),
                'condition' => [
                    'ap_zoho_crm_credentials_source!' => 'default'
                ],
                'content_classes' => 'elementor_ap_zoho_crm_credentials_validate'
            ]
        );

        $widget->end_popover();

        $widget->add_control(
            'ap_zoho_crm_module_settings',
            [
                'label' => __( 'Module Settings <span class="ap-required">*</span>', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::POPOVER_TOGGLE,
                'label_off' => __( 'Default', 'actions-pack' ),
                'label_on' => __( 'Custom', 'actions-pack' ),
                'return_value' => 'yes',
            ]
        );
        $widget->start_popover();

        $widget->add_control(
            'ap_zoho_crm_module',
            [
                'label' => __( 'Zoho Module', 'actions-pack' ),
                'type' => Controls_Manager::SELECT,
                'label_block' => false,
                'options' => [],
                'default' => '',
                'classes' => 'elementor_ap_zoho_crm_credentials_source',
            ]
        );

        $widget->add_control(
            'ap_zoho_crm_update_duplicate',
            [
                'label' => __( 'Update Duplicate', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_off' => __( 'No', 'actions-pack' ),
                'label_on' => __( 'yes', 'actions-pack' ),
                'default' => 'no',
                'return_value' => 'yes',
                'description' => __('When a duplicate entry is found no error will be thrown. The old record will be updated with new information.', 'actions-pack')
            ]
        );

        $widget->add_control(
            'ap_zoho_crm_trigger_actions',
            [
                'label' => __( 'Trigger Actions', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'approval'  => __( 'Approval', 'actions-pack' ),
                    'workflow'  => __( 'Workflow', 'actions-pack' ),
                    'blueprint'  => __( 'Blueprint', 'actions-pack' )
                ],
                'default' => [ 'workflow' ],
                'description' => __('Automated actions like workflow, approval and blueprint can be triggered for all the records inserted or updated.', 'actions-pack')
            ]
        );

        $widget->end_popover();

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'ap_zoho_crm_form_field',
            [
                'label' => __( 'Form Field', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [],
                'show_label' => true,
            ]
        );

        $repeater->add_control(
            'ap_zoho_crm_form_meta',
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
                    'ap_zoho_crm_form_field' => 'meta'
                ]
            ]
        );

        $repeater->add_control(
            'ap_zoho_crm_module_field',
            [
                'label' => __( 'Module Field', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => []
            ]
        );

        $widget->add_control(
            'ap_zoho_crm_fields_mapping',
            [
                'label' => __( 'Fields Mapping', 'actions-pack' ),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'title_field' => '{{{ apZohoCrmFieldsMappingTitle( ap_zoho_crm_module_field ) }}}',
                'default' => [
                    [
                        'ap_zoho_crm_form_field' => 'name',
                        'ap_zoho_crm_module_field' => 'Last_Name',
                    ],
                    [
                        'ap_zoho_crm_form_field' => 'email',
                        'ap_zoho_crm_module_field' => 'Email',
                    ]
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
        $source = $form_settings['ap_zoho_crm_credentials_source'];

        if( $source === 'default'){
            $access_token = get_option('elementor_ap_zoho_crm_access_token');
            $expires_in = get_option('elementor_ap_zoho_crm_expires_in');
            $api_domain = get_option('elementor_ap_zoho_crm_api_domain');
            if(time() > $expires_in){
                $accounts_server = get_option('elementor_ap_zoho_crm_accounts_server');
                $client_id = get_option('elementor_ap_zoho_crm_client_id');
                $client_secret = get_option('elementor_ap_zoho_crm_client_secret');
                $refresh_token = get_option('elementor_ap_zoho_crm_refresh_token');
                $response = $this->renew_access_token($accounts_server, $client_id, $client_secret, $refresh_token);
                $access_token = $response->access_token;
                $expires_in = $response->expires_in;
                update_option('elementor_ap_zoho_crm_access_token', $access_token);
                update_option('elementor_ap_zoho_crm_expires_in', time() + $expires_in);
            }
        }
        else{
            $access_token = $form_settings['ap_zoho_crm_access_token'];
            $expires_in = $form_settings['ap_zoho_crm_expires_in'];
            $api_domain = $form_settings['ap_zoho_crm_api_domain'];
            if(time() > $expires_in){
                $accounts_server = $form_settings['ap_zoho_crm_accounts_server'];
                $client_id = $form_settings['ap_zoho_crm_client_id'];
                $client_secret = $form_settings['ap_zoho_crm_client_secret'];
                $refresh_token = $form_settings['ap_zoho_crm_refresh_token'];
                $response = $this->renew_access_token( $accounts_server, $client_id, $client_secret, $refresh_token);
                $access_token = $response->access_token;
                $expires_in = $response->expires_in;
                $post_id = (int) $_POST['post_id'];
                $form_id = (string) $_POST['form_id'];
                ap_update_elementor_control_settings($post_id, $form_id, [
                    'ap_zoho_crm_access_token' => $access_token,
                    'ap_zoho_crm_expires_in' => time() + $expires_in
                ]);
            }
        }

        $module = $form_settings['ap_zoho_crm_module'];
        $zoho_fields_mapping = $form_settings['ap_zoho_crm_fields_mapping'];
        $data = [];
        if(!empty($zoho_fields_mapping)){
            foreach($zoho_fields_mapping as $item){
                if( $item['ap_zoho_crm_form_field'] === 'meta'){
                    if( !empty($item['ap_zoho_crm_module_field']) ){
                        $data[$item['ap_zoho_crm_module_field']] = $this->get_meta_data($item['ap_zoho_crm_form_meta']);
                    }
                }
                else{
                    if( !empty($item['ap_zoho_crm_module_field']) && !empty($item['ap_zoho_crm_form_field'])){
                        $data[$item['ap_zoho_crm_module_field']] = $form_data[$item['ap_zoho_crm_form_field']];
                    }
                }
            }
        }
        $trigger = $form_settings['ap_zoho_crm_trigger_actions'];
        $update_duplicate = $form_settings['ap_zoho_crm_update_duplicate'];
        

        // Post to Zoho CRM
        $this->post_to_zoho_crm( $api_domain, $module, $access_token, $data, $trigger, $update_duplicate);
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
            $element[self::OPTION_NAME_EXPIRES_IN],
            $element[self::OPTION_NAME_API_DOMAIN],
            $element[self::OPTION_NAME_ACCOUNTS_SERVER]
        );
    }

}
