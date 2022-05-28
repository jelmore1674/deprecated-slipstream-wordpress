<?php

namespace Frontend_WP\Widgets;

use  Frontend_WP\Plugin ;
use  Frontend_WP\FEA_Module ;
use  Frontend_WP\Classes ;
use  Elementor\Controls_Manager ;
use  Elementor\Controls_Stack ;
use  Elementor\Widget_Base ;
use  ElementorPro\Modules\QueryControl\Module as Query_Module ;
use  Frontend_WP\Controls ;
use  Elementor\Group_Control_Typography ;
use  Elementor\Group_Control_Background ;
use  Elementor\Group_Control_Border ;
use  Elementor\Group_Control_Text_Shadow ;
use  Elementor\Group_Control_Box_Shadow ;

if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly
}

/**
 *
 * @since 1.0.0
 */
class ACF_Frontend_Form_Widget extends Widget_Base
{
    public  $form_defaults ;
    /**
     * Get widget name.
     *
     * Retrieve acf ele form widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name()
    {
        return 'acf_ele_form';
    }
    
    /**
     * Get widget defaults.
     *
     * Retrieve acf form widget defaults.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget defaults.
     */
    public function get_form_defaults()
    {
        return [
            'custom_fields_save' => 'all',
            'form_title'         => '',
            'submit'             => __( 'Update', FEA_NS ),
            'success_message'    => __( 'Your site has been updated successfully.', FEA_NS ),
            'field_type'         => 'ACF_fields',
            'fields'             => array( array(
            'field_type' => 'ACF_fields',
        ) ),
        ];
    }
    
    /**
     * Get widget title.
     *
     * Retrieve acf ele form widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title()
    {
        return __( FEA_TITLE . ' Form', FEA_NS );
    }
    
    /**
     * Get widget keywords.
     *
     * Retrieve the list of keywords the widget belongs to.
     *
     * @since 2.1.0
     * @access public
     *
     * @return array Widget keywords.
     */
    public function get_keywords()
    {
        return [
            'frontend editing',
            'edit post',
            'add post',
            'add user',
            'edit user',
            'edit site'
        ];
    }
    
    /**
     * Get widget icon.
     *
     * Retrieve acf ele form widget icon.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon()
    {
        return 'fa fa-wpforms frontend-icon';
    }
    
    /**
     * Get widget categories.
     *
     * Retrieve the list of categories the acf ele form widget belongs to.
     *
     * @since 1.0.0
     * @access public
     *
     * @return array Widget categories.
     */
    public function get_categories()
    {
        return array( 'frontend-admin-general' );
    }
    
    /**
     * Register acf ele form widget controls.
     *
     * Adds different input fields to allow the user to change and customize the widget settings.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function register_controls()
    {
        $this->register_form_structure_controls();
        $this->register_steps_controls();
        $this->register_actions_controls();
        $this->action_controls_section();
        //$this->register_progress_controls();
        do_action( FEA_PREFIX . '/permissions_section', $this );
        do_action( FEA_PREFIX . '/display_section', $this );
        $this->register_limit_controls();
        $this->register_shortcodes_section();
        $this->register_style_tab_controls();
        do_action( FEA_PREFIX . '/content_controls', $this );
        do_action( FEA_PREFIX . '/styles_controls', $this );
    }
    
    protected function register_form_structure_controls()
    {
        $this->start_controls_section( 'fields_section', [
            'label' => __( 'Form Fields', FEA_NS ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_control( 'form_title', [
            'label'       => __( 'Form Title', FEA_NS ),
            'label_block' => true,
            'type'        => Controls_Manager::TEXT,
            'default'     => $this->form_defaults['form_title'],
            'placeholder' => $this->form_defaults['form_title'],
            'dynamic'     => [
            'active' => true,
        ],
        ] );
        $this->add_control( 'show_form_title', [
            'label'        => __( 'Show Form Title', FEA_NS ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'true',
            'default'      => 'true',
        ] );
        $this->custom_fields_control();
        do_action( FEA_PREFIX . '/fields_controls', $this );
        $this->add_control( 'submit_button_text', [
            'label'       => __( 'Submit Button Text', FEA_NS ),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => $this->form_defaults['submit'],
            'placeholder' => $this->form_defaults['submit'],
            'condition'   => [
            'multi!' => 'true',
        ],
            'dynamic'     => [
            'active' => true,
        ],
        ] );
        $this->add_control( 'allow_unfiltered_html', [
            'label'        => __( 'Allow Unfiltered HTML', FEA_NS ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'true',
        ] );
        $this->end_controls_section();
        do_action( FEA_PREFIX . '/sub_fields_controls', $this );
    }
    
    public function register_steps_controls()
    {
    }
    
    protected function register_actions_controls()
    {
        $this->start_controls_section( 'actions_section', [
            'label' => __( 'Actions', FEA_NS ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_control( 'more_actions_promo', [
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => __( '<p><a target="_blank" href="https://www.frontendform.com/"><b>Go pro</b></a> to unlock more actions.</p>', FEA_NS ),
            'content_classes' => 'acf-fields-note',
        ] );
        $this->add_control( 'redirect', [
            'label'       => __( 'Redirect After Submit', FEA_NS ),
            'type'        => Controls_Manager::SELECT,
            'default'     => 'current',
            'options'     => [
            'current'     => __( 'Stay on Current Page/Post', FEA_NS ),
            'custom_url'  => __( 'Custom Url', FEA_NS ),
            'referer_url' => __( 'Referer', FEA_NS ),
            'post_url'    => __( 'Post Url', FEA_NS ),
        ],
            'render_type' => 'none',
        ] );
        $this->add_control( 'open_modal', [
            'label'        => __( 'Leave Modal Open After Submit', FEA_NS ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'true',
            'condition'    => [
            'show_in_modal' => 'true',
            'render_type'   => 'none',
        ],
        ] );
        $this->add_control( 'redirect_action', [
            'label'       => __( 'After Reload', FEA_NS ),
            'type'        => Controls_Manager::SELECT,
            'default'     => 'clear',
            'options'     => [
            'clear' => __( 'Clear Form', FEA_NS ),
            'edit'  => __( 'Edit Form', FEA_NS ),
        ],
            'condition'   => [
            'redirect' => 'current',
        ],
            'render_type' => 'none',
        ] );
        $this->add_control( 'custom_url', [
            'label'       => __( 'Custom Url', FEA_NS ),
            'type'        => Controls_Manager::URL,
            'placeholder' => __( 'Enter Url Here', FEA_NS ),
            'options'     => false,
            'show_label'  => false,
            'condition'   => [
            'redirect' => 'custom_url',
        ],
            'dynamic'     => [
            'active' => true,
        ],
            'render_type' => 'none',
        ] );
        $repeater = new \Elementor\Repeater();
        $repeater->add_control( 'param_key', [
            'label'       => __( 'Key', FEA_NS ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __( 'page_id', FEA_NS ),
            'dynamic'     => [
            'active' => true,
        ],
        ] );
        $repeater->add_control( 'param_value', [
            'label'       => __( 'Value', FEA_NS ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __( '18', FEA_NS ),
            'dynamic'     => [
            'active' => true,
        ],
        ] );
        $this->add_control( 'url_parameters', [
            'label'         => __( 'URL Parameters', FEA_NS ),
            'type'          => Controls_Manager::REPEATER,
            'fields'        => $repeater->get_controls(),
            'prevent_empty' => false,
            'title_field'   => '{{{ param_key }}}',
            'render_type'   => 'none',
        ] );
        $this->add_control( 'show_success_message', [
            'label'        => __( 'Show Success Message', FEA_NS ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', FEA_NS ),
            'label_off'    => __( 'No', FEA_NS ),
            'default'      => 'true',
            'return_value' => 'true',
            'render_type'  => 'none',
        ] );
        $this->add_control( 'update_message', [
            'label'       => __( 'Submit Message', FEA_NS ),
            'type'        => Controls_Manager::TEXTAREA,
            'default'     => $this->form_defaults['success_message'],
            'placeholder' => $this->form_defaults['success_message'],
            'dynamic'     => [
            'active' => true,
        ],
            'condition'   => [
            'show_success_message' => 'true',
        ],
        ] );
        $this->add_control( 'error_message', [
            'label'       => __( 'Error Message', FEA_NS ),
            'type'        => Controls_Manager::TEXTAREA,
            'description' => __( 'There shouldn\'t be any problems with the form submission, but if there are, this is what your users will see. If you are expeiencing issues, try and changing your cache settings and reach out to ', FEA_NS ) . 'support@frontendform.com',
            'default'     => __( 'There has been an error. Form has been submitted successfully, but some actions might not have been completed.', FEA_NS ),
            'dynamic'     => [
            'active' => true,
        ],
            'render_type' => 'none',
        ] );
        $this->end_controls_section();
    }
    
    protected function action_controls_section()
    {
        $local_actions = fea_instance()->local_actions;
        foreach ( $local_actions as $name => $action ) {
            $object = $this->form_defaults['custom_fields_save'];
            if ( $object == $name || $object == 'all' ) {
                $action->register_settings_section( $this );
            }
        }
    }
    
    public function custom_fields_control( $repeater = false )
    {
        $controls = $this;
        $continue_action = [];
        $controls_settings = [
            'label'   => __( 'Save Custom Fields to...', FEA_NS ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'post',
        ];
        
        if ( $repeater ) {
            $controls = $repeater;
            $controls_settings['condition'] = [
                'field_type'         => 'step',
                'overwrite_settings' => 'true',
            ];
        }
        
        
        if ( $this->form_defaults['custom_fields_save'] == 'all' ) {
            $custom_fields_options = array(
                'post' => __( 'Post', FEA_NS ),
                'user' => __( 'User', FEA_NS ),
                'term' => __( 'Term', FEA_NS ),
            );
            $controls_settings['options'] = $custom_fields_options;
            $controls->add_control( 'custom_fields_save', $controls_settings );
        } else {
            $controls->add_control( 'custom_fields_save', [
                'type'    => Controls_Manager::HIDDEN,
                'default' => $this->form_defaults['custom_fields_save'],
            ] );
        }
    
    }
    
    protected function register_display_controls()
    {
        $this->start_controls_section( 'display_section', [
            'label' => __( 'Display Options', FEA_NS ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_control( 'hide_field_labels', [
            'label'        => __( 'Hide Field Labels', FEA_NS ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Hide', FEA_NS ),
            'label_off'    => __( 'Show', FEA_NS ),
            'return_value' => 'true',
            'separator'    => 'before',
            'selectors'    => [
            '{{WRAPPER}} .acf-label' => 'display: none',
        ],
        ] );
        $this->add_control( 'field_label_position', [
            'label'     => __( 'Label Position', 'elementor-pro' ),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
            'top'  => __( 'Above', 'elementor-pro' ),
            'left' => __( 'Inline', 'elementor-pro' ),
        ],
            'default'   => 'top',
            'condition' => [
            'hide_field_labels!' => 'true',
        ],
        ] );
        $this->add_control( 'hide_mark_required', [
            'label'        => __( 'Hide Required Mark', 'elementor-pro' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Hide', 'elementor-pro' ),
            'label_off'    => __( 'Show', 'elementor-pro' ),
            'return_value' => 'true',
            'condition'    => [
            'hide_field_labels!' => 'true',
        ],
            'selectors'    => [
            '{{WRAPPER}} .acf-required' => 'display: none',
        ],
        ] );
        $this->add_control( 'field_instruction_position', [
            'label'     => __( 'Instruction Position', 'elementor-pro' ),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
            'label' => __( 'Above Field', 'elementor-pro' ),
            'field' => __( 'Below Field', 'elementor-pro' ),
        ],
            'default'   => 'label',
            'separator' => 'before',
        ] );
        $this->add_control( 'field_seperator', [
            'label'        => __( 'Field Seperator', 'elementor-pro' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Hide', 'elementor-pro' ),
            'label_off'    => __( 'Show', 'elementor-pro' ),
            'default'      => 'true',
            'return_value' => 'true',
            'separator'    => 'before',
            'selectors'    => [
            '{{WRAPPER}} .acf-fields>.acf-field'                        => 'border-top: none',
            '{{WRAPPER}} .acf-field[data-width]+.acf-field[data-width]' => 'border-left: none',
        ],
        ] );
        $this->end_controls_section();
    }
    
    public function register_progress_controls()
    {
        $this->start_controls_section( 'submissions_section', [
            'label' => __( 'Submissions', FEA_NS ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_control( 'save_progress_button', [
            'label'        => __( 'Save Progress Option', FEA_NS ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', FEA_NS ),
            'label_off'    => __( 'No', FEA_NS ),
            'return_value' => 'true',
        ] );
        $this->add_control( 'saved_draft_text', [
            'label'       => __( 'Save Progress Text', FEA_NS ),
            'type'        => Controls_Manager::TEXT,
            'default'     => __( 'Save Progress', FEA_NS ),
            'placeholder' => __( 'Save Progress', FEA_NS ),
            'dynamic'     => [
            'active' => true,
        ],
            'condition'   => array(
            'save_progress_button' => 'true',
        ),
        ] );
        $this->add_control( 'saved_draft_message', [
            'label'       => __( 'Progress Saved Message', FEA_NS ),
            'type'        => Controls_Manager::TEXTAREA,
            'default'     => __( 'Progress Saved', FEA_NS ),
            'placeholder' => __( 'Progress Saved', FEA_NS ),
            'dynamic'     => [
            'active' => true,
        ],
            'condition'   => array(
            'save_progress_button' => 'true',
        ),
        ] );
        $this->add_control( 'saved_submissions', [
            'label'        => __( 'Show Saved Drafts Selection', FEA_NS ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', FEA_NS ),
            'label_off'    => __( 'No', FEA_NS ),
            'return_value' => 'true',
            'condition'    => array(
            'save_progress_button' => 'true',
        ),
            'seperator'    => 'before',
        ] );
        $condition['saved_drafts'] = 'true';
        $this->add_control( 'saved_drafts_label', [
            'label'       => __( 'Edit Draft Label', FEA_NS ),
            'type'        => Controls_Manager::TEXT,
            'default'     => __( 'Edit a draft', FEA_NS ),
            'placeholder' => __( 'Edit a draft', FEA_NS ),
            'condition'   => array(
            'save_progress_button' => 'true',
            'saved_drafts'         => 'true',
        ),
        ] );
        $this->add_control( 'saved_drafts_new', [
            'label'       => __( 'New Drafts Text', FEA_NS ),
            'type'        => Controls_Manager::TEXT,
            'default'     => __( '&mdash; New Post &mdash;', FEA_NS ),
            'placeholder' => __( '&mdash; New Post &mdash;', FEA_NS ),
            'condition'   => array(
            'save_progress_button' => 'true',
            'saved_drafts'         => 'true',
        ),
        ] );
        $this->end_controls_section();
    }
    
    public function register_limit_controls()
    {
        $this->start_controls_section( 'limit_submit_section', [
            'label' => __( 'Limit Submissions', FEA_NS ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_control( 'limit_submit_promo', [
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => __( '<p><a target="_blank" href="https://www.frontendform.com/"><b>Go pro</b></a> to unlock limit submissions.</p>', FEA_NS ),
            'content_classes' => 'acf-fields-note',
        ] );
        do_action( FEA_PREFIX . '/limit_submit_settings', $this );
        $this->end_controls_section();
    }
    
    public function register_shortcodes_section()
    {
        $this->start_controls_section( 'shortcodes_section', [
            'label' => __( 'Shortcodes for Dynamic Values', FEA_NS ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_control( 'custom_field_shortcode', [
            'label'       => __( 'ACF Text Field', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[acf:field_name]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'image_field_shortcode', [
            'label'       => __( 'ACF Image Field', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[acf:field_name:image]" readonly /><br><input class="elementor-form-field-shortcode" value="[acf:field_name:image_link]" readonly /><br><input class="elementor-form-field-shortcode" value="[acf:field_name:image_id]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'post_title_shortcode', [
            'label'       => __( 'Post Title', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[post:title]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'post_id_shortcode', [
            'label'       => __( 'Post ID', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[post:id]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'post_content_shortcode', [
            'label'       => __( 'Post Content', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[post:content]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'post_excerpt_shortcode', [
            'label'       => __( 'Post Excerpt', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[post:excerpt]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'featured_image_shortcode', [
            'label'       => __( 'Featured Image', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[post:featured_image]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'post_url_shortcode', [
            'label'       => __( 'Post URL', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[post:url]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'username_shortcode', [
            'label'       => __( 'Username', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[user:username]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'user_email_shortcode', [
            'label'       => __( 'User Email', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[user:email]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'user_first_shortcode', [
            'label'       => __( 'User First Name', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[user:first_name]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'user_last_shortcode', [
            'label'       => __( 'User Last Name', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[user:last_name]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'user_role_shortcode', [
            'label'       => __( 'User Role', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[user:role]" readonly />',
            'separator'   => 'after',
        ] );
        $this->add_control( 'user_bio_shortcode', [
            'label'       => __( 'User Bio', 'elementor-pro' ),
            'type'        => Controls_Manager::RAW_HTML,
            'label_block' => true,
            'raw'         => '<input class="elementor-form-field-shortcode" value="[user:bio]" readonly />',
            'separator'   => 'after',
        ] );
        $this->end_controls_section();
    }
    
    public function register_style_tab_controls()
    {
        $this->start_controls_section( 'style_promo_section', [
            'label' => __( 'Styles', 'acf-frontend-form-elements' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ] );
        $this->add_control( 'styles_promo', [
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => __( '<p><a target="_blank" href="https://www.frontendform.com/"><b>Go Pro</b></a> to unlock styles.</p>', FEA_NS ),
            'content_classes' => 'acf-fields-note',
        ] );
        $this->end_controls_section();
    }
    
    public function get_field_type_options()
    {
        $groups = acf_frontend_get_field_type_groups();
        $fields = [
            'acf'    => $groups['acf'],
            'layout' => $groups['layout'],
        ];
        switch ( $this->form_defaults['custom_fields_save'] ) {
            case 'post':
                $fields['post'] = $groups['post'];
                break;
            case 'user':
                $fields['user'] = $groups['user'];
                break;
            case 'options':
                $fields['options'] = $groups['options'];
                break;
            case 'term':
                $fields['term'] = $groups['term'];
                break;
            case 'comment':
                $fields['comment'] = $groups['comment'];
                break;
            case 'product':
                $fields = array_merge( $fields, [
                    'product_type' => $groups['product_type'],
                    'product'      => $groups['product'],
                    'inventory'    => $groups['product_inventory'],
                    'shipping'     => $groups['product_shipping'],
                    'downloadable' => $groups['product_downloadable'],
                    'external'     => $groups['product_external'],
                    'linked'       => $groups['product_linked'],
                    'attributes'   => $groups['product_attributes'],
                    'advanced'     => $groups['product_advanced'],
                ] );
                break;
            default:
                $fields = array_merge( $fields, [
                    'post' => $groups['post'],
                    'user' => $groups['user'],
                    'term' => $groups['term'],
                ] );
        }
        return $fields;
    }
    
    /**
     * Render acf ele form widget output on the frontend.
     *
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render()
    {
        $wg_id = $this->get_id();
        global  $post ;
        $current_post_id = fea_instance()->elementor->get_current_post_id();
        $settings = $this->get_settings_for_display();
        $defaults = $new_post = $show_title = $show_content = $show_form = $display = $message = $fields = $fields_exclude = false;
        $hidden_submit = $disabled_submit = '';
        $form_attributes['data-widget'] = $wg_id;
        $form_args = array(
            'id'                    => $wg_id,
            'post_title'            => $show_title,
            'form_attributes'       => $form_attributes,
            'post_content'          => $show_content,
            'submit_value'          => $settings['submit_button_text'],
            'instruction_placement' => $settings['field_instruction_position'],
            'html_submit_spinner'   => '',
            'label_placement'       => 'top',
            'field_el'              => 'div',
            'kses'                  => !$settings['allow_unfiltered_html'],
            'html_after_fields'     => '',
        );
        $form_args = $this->get_settings_to_pass( $form_args, $settings );
        if ( isset( $settings['saved_drafts'] ) && $settings['saved_drafts'] && $settings['save_to_post'] == 'new_post' ) {
            $form_args['saved_drafts'] = [
                'saved_drafts_label' => $settings['saved_drafts_label'],
                'saved_drafts_new'   => $settings['saved_drafts_new'],
            ];
        }
        if ( isset( $settings['saved_revisions'] ) && $settings['saved_revisions'] && $settings['save_to_post'] == 'edit_post' ) {
            $form_args['saved_revisions'] = [
                'saved_revisions_label'     => $settings['saved_revisions_label'],
                'saved_revisions_edit_main' => $settings['saved_revisions_edit_main'],
            ];
        }
        if ( isset( $settings['save_progress_button'] ) && $settings['save_progress_button'] ) {
            $form_args['save_progress'] = $settings['saved_draft_text'];
        }
        if ( $settings['url_parameters'] ) {
            foreach ( $settings['url_parameters'] as $param ) {
                $form_args['redirect_params'][$param['param_key']] = $param['param_value'];
            }
        }
        if ( $settings['show_in_modal'] && $settings['open_modal'] ) {
            $form_args['redirect_params']['modal'] = 1;
        }
        if ( isset( $args['parent_form'] ) ) {
            $form_args['parent_form'] = $args['parent_form'];
        }
        
        if ( isset( $settings['user_manager'] ) && $settings['user_manager'] != 'none' ) {
            
            if ( $settings['user_manager'] == 'current_user' ) {
                $user_manager = get_current_user_id();
            } else {
                $user_manager = $settings['manager_select'];
            }
            
            $form_args['user_manager'] = $user_manager;
        }
        
        if ( !$settings['hide_field_labels'] ) {
            $form_args['label_placement'] = $settings['field_label_position'];
        }
        $form_args = apply_filters( FEA_PREFIX . '/form_args', $form_args, $settings );
        fea_instance()->form_display->render_form( $form_args );
    }
    
    public function get_settings_to_pass( $form_args, $settings )
    {
        $settings_to_pass = [
            'form_title',
            'show_form_title',
            'new_post_type',
            'new_post_status',
            'saved_draft_message',
            'new_post_terms',
            'new_terms_select',
            'new_product_status',
            'new_product_terms',
            'new_product_terms_select',
            'new_term_taxonomy',
            'username_prefix',
            'username_suffix',
            'new_user_role',
            'hide_admin_bar',
            'username_default',
            'login_user',
            'steps_tabs_display',
            'steps_counter_display',
            'counter_prefix',
            'counter_suffix',
            'validate_steps',
            'steps_display',
            'tab_links',
            'step_number',
            'display_name_default',
            'redirect',
            'custom_url',
            'error_message',
            'custom_fields_save',
            'redirect_action',
            'update_message',
            'more_actions',
            'style_messages',
            'who_can_see',
            'by_role',
            'by_user_id',
            'dynamic',
            'dynamic_manager',
            'not_allowed',
            'not_allowed_message',
            'not_allowed_content',
            'multi',
            'fields_selection',
            'first_step',
            'attribute_fields',
            'variable_fields',
            'tabs_align',
            'limiting_rules',
            'limit_reached',
            'limit_submit_message',
            'limit_submit_content',
            'limit_submissions',
            'save_all_data',
            'wp_uploader'
        ];
        $types = array(
            'post',
            'user',
            'term',
            'product'
        );
        foreach ( $types as $type ) {
            $settings_to_pass[] = "save_to_{$type}";
            $settings_to_pass[] = "{$type}_to_edit";
            $settings_to_pass[] = "url_query_{$type}";
            $settings_to_pass[] = "{$type}_select";
        }
        foreach ( $settings_to_pass as $setting ) {
            if ( isset( $settings[$setting] ) ) {
                $form_args[$setting] = $settings[$setting];
            }
        }
        $form_args['show_update_message'] = $settings['show_success_message'];
        return $form_args;
    }
    
    public function __construct( $data = array(), $args = null )
    {
        parent::__construct( $data, $args );
        
        if ( \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
            acf_enqueue_scripts();
            acf_enqueue_uploader();
        }
        
        $this->form_defaults = $this->get_form_defaults();
        fea_instance()->elementor->form_widgets[] = $this->get_name();
    }

}