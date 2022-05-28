<?php
namespace Frontend_WP;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if( ! class_exists( 'Frontend_WP_Gutenberg' ) ) :

	class Frontend_WP_Gutenberg{

	public function register_blocks() {
		$asset_file = require_once( __DIR__ . '/assets/js/acf-frontend-form.asset.php');
		wp_register_script( 'acf-frontend-form', FEA_URL . 'main/gutenberg/assets/js/acf-frontend-form.js', $asset_file['dependencies'],
		$asset_file['version'] );
	
		register_block_type('acf-frontend/form', [
			'editor_script' => 'acf-frontend-form',
			'render_callback' => array( $this, 'render_form_block' ),
			'attributes' => [
				'formID' => [
					'type' => 'number',
					'default' => 0
				],
				'editMode' => [
					'type' => 'boolean',
					'default' => 0
				]
			]
		]);
	}

	public function render_form_block($attr, $content) {
		$render = '';
		if ( $attr['formID'] == 0 ){
			return __( 'Please Select a Form', FEA_NS );
		}
		if ( get_post_type( $attr['formID'] ) == 'admin_form' ){
			ob_start();
			if( is_admin() ) $attr['editMode'] = true;
			fea_instance()->form_display->render_form( $attr['formID'], $attr['editMode'] );
			$render = ob_get_contents();
			ob_end_clean();	
		}
		return $render;
	}

	function add_block_categories( $block_categories ) {
		return array_merge(
			$block_categories,
			[
				[
					'slug'  => 'acf-frontend',
					'title' => esc_html__( FEA_TITLE, FEA_NS ),
					'icon'  => 'feedback', 
				],
			]
		);
	}

	public function __construct() {
		add_filter( 'block_categories_all', array( $this, 'add_block_categories' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
	}
}

fea_instance()->gutenberg = new Frontend_WP_Gutenberg();

endif;	