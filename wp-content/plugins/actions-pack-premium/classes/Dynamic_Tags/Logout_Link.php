<?php

namespace Actions_Pack\Dynamic_Tags;

use Elementor\Controls_Manager;
use ElementorPro\Modules\DynamicTags\Tags\Base\Data_Tag;
use ElementorPro\Modules\DynamicTags\Module;


Class Logout_Link extends Data_Tag {

    public function get_name() {
        return 'ap_logout_link';
    }

    public function get_title() {
        return __( 'Logout Link', 'actions-pack' );
    }

    public function get_group() {
        return 'actions-pack';
    }

    public function get_categories() {
        return [ Module::URL_CATEGORY ];
    }

    public function register_controls()
    {
        $this->add_control(
            'redirect_to',
            [
                'label' => __('Redirect to', 'actions-pack'),
                'type' => Controls_Manager::SELECT,
                'options' =>[
                    'current_page' => 'Current Page',
                    'home_page' => 'Home Page',
                    'custom' => 'Custom URL'
                ],
                'default' => 'Home Page'
            ]
        );
        $this->add_control(
            'custom_redirect_url',
            [
                'label' => __('Type URL', 'actions-pack'),
                'type' => Controls_Manager::TEXT,
                'condition' =>[
                    'redirect_to' => 'custom'
                ],
            ]
        );
    }

    public function get_value( array $options = [] ){

        $url = '';

        if(is_user_logged_in()){
            $redirect_to = '';
            $option = $this->get_settings( 'redirect_to' );
            switch ($option){
                case 'current_page' :
                    $redirect_to = get_permalink();
                    break;
                case 'home_page' :
                    $redirect_to = home_url();
                    break;
                case 'custom' :
                    $redirect_to = $this->get_settings( 'custom_redirect_url' );
            }
            $url = wp_logout_url( $redirect_to );
        }

        return $url;
    }
}