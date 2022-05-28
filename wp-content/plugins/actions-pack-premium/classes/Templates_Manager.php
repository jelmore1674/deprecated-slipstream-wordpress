<?php

namespace Actions_pack\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use \Elementor\Plugin;
use \Elementor\Api;

class Ap_Templates_Manager
{
	public function __construct() {
		require_once ( AP_PLUGIN_DIR_PATH.'classes/Custom_Templates.php');
		Plugin::instance()->templates_manager->register_source('Elementor\TemplateLibrary\Ap_Custom_Templates');
		add_action('elementor/ajax/register_actions', [$this, 'register_ajax'], 25);
		add_filter('option_' . Api::LIBRARY_OPTION_KEY, [$this, 'add_categories']);
	}

	public function register_ajax( $ajax ) {

		if ( ! isset( $_REQUEST['actions'] ) ) {
			return;
		}

		$axax_actions = json_decode(stripslashes($_REQUEST['actions']) , true);

		$template = false;

		foreach ( $axax_actions as $data => $action_data ) {
			if ( ! isset( $action_data['get_template_data'] ) ) {
				$template = $action_data;
			}
		}

		if ( ! isset( $template['data'] ) || empty( $template['data'] ) ) {
			return;
		}

		if ( empty( $template['data']['template_id'] ) ) {
			return;
		}

		if (false === strpos( $template['data']['template_id'], 'ap_' )) {
			return;
		}

		$ajax->register_ajax_action('get_template_data', function ( $args ){
			$template_source = Plugin::instance()->templates_manager->get_source('ap_templates');
			return $template_source->get_data($args);
		});
	}

	public function add_categories( $data )
	{
		$categories = [
			'login',
			'register',
			'profile'
		];

		if ( version_compare(ELEMENTOR_VERSION, '2.3.9', '>') ) {
			$data['types_data']['block']['categories'] = array_merge($categories, $data['types_data']['block']['categories']);
		}
		else {
			$data['categories'] = array_merge($categories, $data['categories']);
		}

		return $data;
	}
}
