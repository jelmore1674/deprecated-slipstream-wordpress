<?php
/**
 * Plugin Name: Actions Pack
 * Plugin URI: https://actions-pack.com
 * Description: Supercharge Elementor's default form widget with premium features & actions
 * Version:     2.3.9
 * Author:      Wpfolk
 * Author URI:  https://wpfolk.com
 * Text Domain: actions-pack
 * Domain Path: /languages
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define('AP_PLUGIN_FILE_URL', __FILE__);
define('AP_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ));
define('AP_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );

$error = false;

require_once( 'vendor/autoload.php' );
require_once('init.php');
/**
 * Main Elementor Extension Class
 *
 * The main class that initiates and runs the plugin.
 *
 * @since 2.0.0
 */
final class Actions_Pack {

	const MINIMUM_ELEMENTOR_VERSION = '3.2.3';
	const MINIMUM_ELEMENTOR_PRO_VERSION = '3.2.1';
	const MINIMUM_PHP_VERSION = '7.0';

	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		add_action( 'init', [ $this, 'i18n' ] );
		add_action( 'plugins_loaded', [ $this, 'init' ] );

	}

	public function i18n() {
		load_plugin_textdomain( 'actions-pack', false, basename(AP_PLUGIN_DIR_PATH ) . '/languages' );
	}

	public function init() {

		// @toDo Deactivate plugin if doesn't meet any requirement https://wordpress.stackexchange.com/questions/25910/uninstall-activate-deactivate-a-plugin-typical-features-how-to/25979#25979
		// Check if Elementor and Elementor Pro installed and activated
		if ( ! did_action( 'elementor/loaded' ) || ! function_exists( 'elementor_pro_load_plugin') ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_missing_main_plugin' ] );
			return;
		}

		// Check for required Elementor and Pro version
		if ( ! version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) || ! version_compare( ELEMENTOR_PRO_VERSION, self::MINIMUM_ELEMENTOR_PRO_VERSION, '>=' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_minimum_elementor_version' ] );
			return;
		}

		// Check for required PHP version
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_minimum_php_version' ] );
			return;
		}

		// Init Elementor Things
		add_action( 'elementor_pro/init', function (){
			// Include Files
			$this->include_files();

			// Include Common Assets files
			$this->add_assets();

			// Custom Pattern, eye button
			$this->add_common_controls();

			// Custom Template Library
			$this->add_custom_templates();

			// Add form Actions
			$this->add_form_actions();

			// Add Dynamic Tags
            $this->add_dynamic_tags();

            if( class_exists('ACF') ){
                $this->add_acf_fields();
            }
		} );

	}

	public function admin_notice_missing_main_plugin() {

		// Hide the default "Plugin activated" notice
		if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );

		$message = sprintf(
		/* translators: 1: Plugin name 2: Elementor  3: Elementor Pro*/
			esc_html__( '"%1$s" requires "%2$s" & "%3$s" to be installed and activated.', 'actions-pack' ),
			'<strong>' . esc_html__( 'Actions Pack', 'actions-pack' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'actions-pack' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor Pro', 'actions-pack' ) . '</strong>'
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );

	}

	public function admin_notice_minimum_elementor_version() {

		if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );

		$message = sprintf(
		/* translators: 1: Plugin name 2: Elementor 3: Required Elementor version 4: Elementor Pro 5: Required Elementor Pro version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater and "%4$s" version %5$s or greater.', 'actions-pack' ),
			'<strong>' . esc_html__( 'Actions Pack', 'actions-pack' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'actions-pack' ) . '</strong>',
			self::MINIMUM_ELEMENTOR_VERSION,
			'<strong>' . esc_html__( 'Elementor Pro', 'actions-pack' ) . '</strong>',
			self::MINIMUM_ELEMENTOR_PRO_VERSION
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );

	}

	public function admin_notice_minimum_php_version() {

		if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );

		$message = sprintf(
		/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'actions-pack' ),
			'<strong>' . esc_html__( 'Actions Pack', 'actions-pack' ) . '</strong>',
			'<strong>' . esc_html__( 'PHP', 'actions-pack' ) . '</strong>',
			self::MINIMUM_PHP_VERSION
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );

	}

	public function include_files(){
		include_once( 'includes/functions.php' );
		include_once( 'includes/shortcodes.php' );
	}

	public function add_assets(){
		// Elementor Preview page
		add_action( 'elementor/preview/enqueue_styles', function() {
			wp_enqueue_script('ap-user');
			wp_enqueue_style('ap-user');
		} );

		// Editor Assets
		add_action( 'elementor/editor/after_enqueue_scripts', function() {
			// editor.js
			wp_register_script( 'ap-editor',  AP_PLUGIN_DIR_URL .'assets/js/editor.js', [ 'jquery' ], false, true );
			wp_enqueue_script('ap-editor');
			// editor.css
			wp_register_style( 'ap-editor',  AP_PLUGIN_DIR_URL .'assets/css/editor.css');
			wp_enqueue_style('ap-editor');
		});
	}

	public function add_common_controls(){
		include_once( 'classes/common.php' );
		new Elementor_Forms_Common_Controls();
	}

	public function add_custom_templates(){
		if(AP_IS_GOLD){
			include_once 'classes/Templates_Manager.php';
			new \Actions_pack\Manager\Ap_Templates_Manager();
		}
	}

	public function add_form_actions(){

		$form = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' );

		// Add `Register` action to form widget
		include_once( 'actions/register.php' );
		$register = new Action_Register();
		$form->add_form_action( $register->get_name(), $register );

		// Add `Login` action to form widget
		include_once( 'actions/login.php' );
		$login = new Action_Login();
		$form->add_form_action( $login->get_name(), $login );

		// Add `Reset Password` action to form widget
		if( AP_IS_SILVER ){
			include_once( 'actions/reset-pass.php' );
			$resetPass = new Action_Reset_Pass();
			$form->add_form_action( $resetPass->get_name(), $resetPass );
		}

		// Add `Google Sheet` action to form widget
		if( AP_IS_SILVER ){
			include_once( 'actions/google-sheet.php' );
			$googleSheet = new Action_Google_Sheet();
			$form->add_form_action( $googleSheet->get_name(), $googleSheet );
		}

        // Add `Zoho CRM` action to form widget
        if( AP_IS_GOLD ){
            include_once( 'actions/zoho-crm.php' );
            $zohoCrm = new Action_Zoho_Crm();
            $form->add_form_action( $zohoCrm->get_name(), $zohoCrm );
        }

		// Add `SMS` action to form widget
		if( AP_IS_GOLD ){
			include_once( 'actions/sms.php' );
			$sms = new Action_SMS();
			$form->add_form_action( $sms->get_name(), $sms );
		}

		if( AP_IS_GOLD ){
			// Add `Profile` action to form widget
			include_once( 'actions/profile.php' );
			$profile = new Action_Profile();
			$form->add_form_action( $profile->get_name(), $profile );
		}
	}

	public function add_dynamic_tags(){
        add_action('elementor/dynamic_tags/register_tags', function( $dynamic_tags ){
            // Register Actions Pack Group
            \Elementor\Plugin::$instance->dynamic_tags->register_group( 'actions-pack', [
                'title' => 'Actions Pack'
            ]);

            $objects = self::initClasses('\Actions_Pack\Dynamic_Tags', AP_PLUGIN_DIR_PATH.'classes/Dynamic_Tags');
            foreach($objects as $object){
                $dynamic_tags->register_tag($object);
            }
        });
    }

    public function add_acf_fields(){
        acf_register_field_type( \Actions_Pack\acf\Image::class);
    }

    public static function initClasses(string $namespace, string $path = __DIR__): array {
        $finder = new Symfony\Component\Finder\Finder();
        $finder->files()->in($path)->name('*.php');
        foreach ($finder as $file) {
            $class_name = rtrim($namespace, '\\') . '\\' . $file->getFilenameWithoutExtension();
            if (class_exists($class_name)) {
                try {
                    $objects[] = new $class_name();
                }
                catch (\Throwable $e) {
                    continue;
                }
            }
        }
        return $objects ?? [];
    }
}

if( ! $error ){
	Actions_Pack::instance();
}
