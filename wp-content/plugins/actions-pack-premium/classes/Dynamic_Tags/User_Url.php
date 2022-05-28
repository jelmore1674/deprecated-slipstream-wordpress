<?php

namespace Actions_Pack\Dynamic_Tags;

use Elementor\Controls_Manager;
use ElementorPro\Modules\DynamicTags\Tags\Base\Data_Tag;
use ElementorPro\Modules\DynamicTags\Module;


Class User_Url extends Data_Tag {

    public function get_name() {
        return 'ap_user_url';
    }

    public function get_title() {
        return __( 'User URL', 'actions-pack' );
    }

    public function get_group() {
        return 'actions-pack';
    }

    public function get_categories() {
        return [ Module::URL_CATEGORY ];
    }

    public function register_controls() {
        $this->add_control(
            'user_type',
            [
                'label' => __( 'User Type', 'actions-pack' ),
                'type' => Controls_Manager::SELECT,
                'options' =>[
                    'loggedin-user' => 'Logged-In User',
                    'post-author' => 'Post Author',
                    'author-archive' => 'Author Archive'
                ],
                'default' => 'loggedin-user'
            ]
        );
    }

    public function get_value( array $options = [] ){

        $url = '#';

        $user_type = $this->get_settings( 'user_type' );

        if( !empty($user_type) )
        {
            switch($user_type)
            {
                case 'loggedin-user' :
                    if(is_user_logged_in()){
                        $url = get_author_posts_url(get_current_user_id());
                    }
                    break;
                case 'post-author' :
                    if(in_the_loop()){
                        $url = get_author_posts_url(get_the_author_meta('ID'));
                    }
                    break;
                case 'author-archive' :
                    if(is_author()){
                        $url = get_author_posts_url(get_queried_object_id());
                    }
                    break;
            }
        }

        return $url;
    }
}