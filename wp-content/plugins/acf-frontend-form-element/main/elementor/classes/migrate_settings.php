<?php
namespace Frontend_WP\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class MigrateSettings {

    function filter_elementor_data( $meta, $object_id, $meta_key ){
        if( $meta_key !== '_elementor_data' ) return $meta;

        remove_filter( 'get_post_metadata', array( $this, 'filter_elementor_data' ), 1, 3 );

        if( get_post_meta( $object_id, '_frontend_admin_version', true ) == '3.0.6' ) return $meta;
        
        $meta = get_post_meta( $object_id, '_elementor_data', true );

        if ( is_string( $meta ) && ! empty( $meta ) ) {
            $meta = json_decode( $meta, true );
        } 
        
        if ( empty( $meta ) ) {
            $meta = [];
        }else{
            foreach( $meta as $index => $section ){
                $meta[$index] = $this->iterate_page_data( $object_id, $section );
            }
            
        }
       
        $data = wp_slash( wp_json_encode( $meta ) );

        update_post_meta( $object_id, '_frontend_admin_version', '3.0.6' );
        update_metadata( 'post', $object_id, '_elementor_data', $data );
        add_filter( 'get_post_metadata', array( $this, 'filter_elementor_data' ), 1, 3 );
        
        return null;
    }

    function iterate_page_data( $post_id, $data_container ) {
        if ( isset( $data_container['elType'] ) ) {
            if ( ! empty( $data_container['elements'] ) ) {
                $data_container['elements'] = $this->iterate_page_data( $post_id, $data_container['elements'] );
            }
            if ( empty( $data_container['widgetType'] ) ) {
                return $data_container;
            }
            return $this->change_widget_settings( $data_container, $post_id, true );
        }

        if( is_array( $data_container ) ){
            foreach ( $data_container as $element_key => $element_value ) {
                $element_data = $this->iterate_page_data( $post_id, $data_container[ $element_key ] );

                if ( null === $element_data ) {
                    continue;
                }

                $data_container[ $element_key ] = $element_data;
            }
        }

        return $data_container;
    }

    function change_widget_settings( $widget, $post_id = '', $update = false ) {
        $widget_names = fea_instance()->elementor->form_widgets;
        if( is_array( $widget_names ) ){
            if( in_array( $widget['widgetType'], $widget_names ) ){
                $settings = $widget['settings'];
                $wg_id = $widget['id'];
                $wg_type = $widget['widgetType'];
            }else{
                return $widget;
            }
        }else{
            return $widget;
        }

        if( ! $post_id ){
            $post_id = get_queried_object_id();
        } 
        $module = fea_instance()->elementor;
        

        if( $wg_type != 'acf_ele_form' && $wg_type != 'acf_form_fields' ){
            $settings['main_action'] = $wg_type;
        }else{
            if( ! isset( $settings['main_action'] ) ){
                $settings['main_action'] = 'edit_post';
            }
        }

        switch( $settings['main_action'] ){
            case 'edit_post':
            case 'new_post':
                $settings['save_to_post'] = $settings['main_action'];
            break;
            case 'edit_user':
            case 'new_user':
                $settings['save_to_user'] = $settings['main_action'];
            break;
            case 'edit_product':
            case 'new_product':
                $settings['save_to_product'] = $settings['main_action'];
            break;
            case 'edit_term':
            case 'new_term':
                $settings['save_to_term'] = $settings['main_action'];
            break;	
        }

        $find = array('edit_','new_');
        $replace = array('','');
        $settings['custom_fields_save'] = str_replace( $find, $replace, $settings['main_action'] );

        $widget['settings'] = $settings;

        return $widget;

    }

    public function update_templates_meta(){
        if( get_option( 'frontend_admin_version' ) == '3.0.6' ) return;

        $templates_args = array(
            'post_type' => 'elementor_library',
            'post_status' => 'all',
            'posts_per_page' => -1,
        );
        if( $templates = get_posts( $templates_args ) ){
            foreach( $templates as $template ){
                get_post_meta( $template->ID, '_elementor_data', true );
            }
        }	

        update_option( 'frontend_admin_version', '3.0.6' );
    }

    public function call_elementor_data(){
        global $post;

        if( ! empty( $post->ID ) ) get_post_meta( $post->ID, '_elementor_data', true );
    }

	public function __construct(){
		add_filter( 'get_post_metadata', array( $this, 'filter_elementor_data' ), 1, 3 );
        add_action( 'wp', array( $this, 'call_elementor_data' ) );
        $this->update_templates_meta();
	}
	
}

new MigrateSettings();