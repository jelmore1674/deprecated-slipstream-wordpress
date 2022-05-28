<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2012 Unite CMS, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
defined('UNLIMITED_ELEMENTS_INC') or die('Restricted access');

class UniteCreatorSettingsMultisource{
	
	private $settings;
	private $objAddon;
	private $arrPostFields;
	
	
	public function __construct(){
		
		//for autocomplete
		$this->objAddon	= new UniteCreatorAddon();
		
		$this->objAddon = null;
		
		$this->arrPostFields = array(
			"none"=>__("None","unlimited-elements-for-elementor"),
			"post_title"=>__("Post Title","unlimited-elements-for-elementor"),
			"post_excerpt"=>__("Post Excerpt","unlimited-elements-for-elementor") ,
			"post_content"=>__("Post Content","unlimited-elements-for-elementor"),
			"post_image"=>__("Post Featured Image","unlimited-elements-for-elementor"),
			"post_date"=>__("Post Date","unlimited-elements-for-elementor"),
			"post_link"=>__("Post Url","unlimited-elements-for-elementor"),
			"post_meta"=>__("Post Meta Field","unlimited-elements-for-elementor")
		);
		
		$this->arrPostFields = array_flip($this->arrPostFields);
		
	}
	
	
	/**
	 * set the settings
	 */
	public function setSettings(UniteCreatorSettings $settings){

		$this->settings = $settings;
		$this->objAddon = GlobalsProviderUC::$activeAddonForSettings;
		
	}
	
	
	/**
	 * add items multisource
	 */
	public function addItemsMultisourceSettings($name, $value, $title, $param){
		
		UniteFunctionsUC::validateNotEmpty($this->settings, "settings object");
		
		
		//------ items source ------
		
		$arrSource = array();
		
		$arrSource["items"] = __("Items", "unlimited-elements-for-elementor");
		$arrSource["posts"] = __("Posts", "unlimited-elements-for-elementor");
		
		$isWooActive = UniteCreatorWooIntegrate::isWooActive();
		if($isWooActive == true)
			$arrSource["products"] = __("Products", "unlimited-elements-for-elementor");
		
		$arrSource["terms"] = __("Terms", "unlimited-elements-for-elementor");
		
		$isAcfExists = UniteCreatorAcfIntegrate::isAcfActive();
		
		if($isAcfExists == true)
			$arrSource["acf_repeater"] = __("ACF Repeater", "unlimited-elements-for-elementor");
		
		$hasInstagram = HelperProviderCoreUC_EL::isInstagramSetUp();
		
		if($hasInstagram)
			$arrSource["instagram"] = __("Instagram", "unlimited-elements-for-elementor");
		
		$arrSource = array_flip($arrSource);

		$params = array();
		$params["origtype"] = UniteCreatorDialogParam::PARAM_DROPDOWN;
		
		$this->settings->addSelect($name."_source", $arrSource, __("Items Source", "unlimited-elements-for-elementor"), "items", $params);
		
		
		$this->addMultisourceConnectors_posts($name);
		
	}
	
	/**
	 * add multisource connectors
	 */
	private function addMultisourceConnectors_posts($name){
		
		if(empty($this->objAddon))
			return(false);

		$hasItems = $this->objAddon->isHasItems();

		if($hasItems == false)
			return(false);
		
		$paramsItems = $this->objAddon->getParamsItems();
		
		if(empty($paramsItems))
			return(false);
		
		// --- hr before fields source
			
		$params = array();
		$params["origtype"] = UniteCreatorDialogParam::PARAM_HR;
		
		//$params["elementor_condition"] = $arrCustomOnlyCondition;
		
		$this->settings->addHr($name."_before_posts_fields_connect",$params);
						
		// --- items source select 
		
		foreach($paramsItems as $itemParam){
			
			$this->putParamConnector_post($name, $itemParam);
			
		}
		
	}
	
	/**
	 * get post param connector
	 */
	private function putParamConnector_post($fieldName, $param){
		
		$title = UniteFunctionsUC::getVal($param, "title");
		
		if(empty($title))
			return(false);
			
		$name = UniteFunctionsUC::getVal($param, "name");
		
		if(empty($name))
			return(false);
		
		
		//add the select param
				
		$params = array();
		$params["origtype"] = UniteCreatorDialogParam::PARAM_DROPDOWN;
		
		$text = $title. " ".__("Source", "unlimited-elements-for-elementor");
		
		$this->settings->addSelect($fieldName."_post_field_source_$name", $this->arrPostFields, $text, "none", $params);
		
		
	}
	
	
}