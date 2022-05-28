<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
defined('UNLIMITED_ELEMENTS_INC') or die('Restricted access');

class UniteCreatorEntranceAnimations{
	
	private static $instance;
	
	const CURRENT_TYPE_TEST = "from_right";
	
	const ANIMATION_APPEAR = "appear";
	const ANIMATION_SCALE_DOWN = "scale_down";
	const ANIMATION_SCALE_UP = "scale_up";
	const ANIMATION_FROM_LEFT = "from_left";
	const ANIMATION_FROM_TOP = "from_top";
	const ANIMATION_FROM_RIGHT = "from_right";
	const ANIMATION_FROM_BOTTOM = "from_bottom";
	
	
	const DISTANCE_SHORTEST = "shortest";		
	const DISTANCE_SHORT = "short";
	const DISTANCE_MEDIUM = "medium";	
	const DISTANCE_LONG = "long";	
	const DISTANCE_LONGEST = "longest";	
	
	const TIME_FASTEST = "fastest";
	const TIME_FAST = "fast";		
	const TIME_MEDIUM = "medium";		
	const TIME_SLOW = "slow";		
	const TIME_SLOWEST = "slowest";			
	const TIME_VERY_SLOW_DEBUG = "very_slow_debug";	
	
	const BLUR_SMALL = "small";
	const BLUR_MEDIUM = "medium";
	const BLUR_STRONG = "strong";
	
	
	/**
	 * get singleton instance
	 */
	private static function getInstance(){
		
		if(empty(self::$instance))
			self::$instance = new UniteCreatorEntranceAnimations();
			
		return(self::$instance);
	}
	
	
	/**
	 * get types assoc
	 */
	public static function getAnimationTypes(){
		
		$arrTypes = array();
		
		$arrTypes["none"] = __("None","unlimited-elements-for-elementor");
		
		$arrTypes[self::ANIMATION_APPEAR] = __("Appear","unlimited-elements-for-elementor");
		$arrTypes[self::ANIMATION_SCALE_DOWN] = __("Scale Down","unlimited-elements-for-elementor");
		$arrTypes[self::ANIMATION_SCALE_UP] = __("Scale Up","unlimited-elements-for-elementor");
		$arrTypes[self::ANIMATION_FROM_LEFT] = __("From Left","unlimited-elements-for-elementor");
		$arrTypes[self::ANIMATION_FROM_RIGHT] = __("From Right","unlimited-elements-for-elementor");
		$arrTypes[self::ANIMATION_FROM_TOP] = __("From Top","unlimited-elements-for-elementor");
		$arrTypes[self::ANIMATION_FROM_BOTTOM] = __("From Bottom","unlimited-elements-for-elementor");
		
		return($arrTypes);
	}
	
	
	/**
	 * add settings for elementor controls
	 */
	public static function addSettings($objSettings, $name, $param){
		
		$title = UniteFunctionsUC::getVal($param, "title");
		
		//------ animation type ----------
		
		$params = array();
		$params["origtype"] = UniteCreatorDialogParam::PARAM_DROPDOWN;
		
		$arrType = self::getAnimationTypes();
		
		$arrType = array_flip($arrType);
				
		$objSettings->addSelect($name."_type", $arrType, $title, "none", $params);
		
		//------  distance ----------
		
		$params = array();
		$params["origtype"] = UniteCreatorDialogParam::PARAM_DROPDOWN;
		$params["elementor_condition"] = array(
			$name."_type!" => "none"
		);
		
		$arrDistance = array(
			self::DISTANCE_SHORTEST=>__("Shortest","unlimited-elements-for-elementor"),
			self::DISTANCE_SHORT=>__("Short","unlimited-elements-for-elementor"),
			self::DISTANCE_MEDIUM=>__("Medium","unlimited-elements-for-elementor"),
			self::DISTANCE_LONG=>__("Long","unlimited-elements-for-elementor"),
			self::DISTANCE_LONGEST=>__("Longest","unlimited-elements-for-elementor")
		);
		
		$arrDistance = array_flip($arrDistance);
		
		$objSettings->addSelect($name."_distance", $arrDistance, __("Animation Distance","unlimited-elements-for-elementor"), self::DISTANCE_SHORT, $params);
		
		//------  speed ----------
		
		$params = array();
		$params["origtype"] = UniteCreatorDialogParam::PARAM_DROPDOWN;
		$params["elementor_condition"] = array(
			$name."_type!" => "none"
		);
		
		$arrSpeed = array(
			self::TIME_FASTEST=>__("Fastest","unlimited-elements-for-elementor"),
			self::TIME_FAST=>__("Fast","unlimited-elements-for-elementor"),
			self::TIME_MEDIUM=>__("Medium","unlimited-elements-for-elementor"),
			self::TIME_SLOW=>__("Slow","unlimited-elements-for-elementor"),
			self::TIME_SLOWEST=>__("Slowest","unlimited-elements-for-elementor"),
			self::TIME_VERY_SLOW_DEBUG=>__("Very Slow for Debug","unlimited-elements-for-elementor")
		);
		
		$arrSpeed = array_flip($arrSpeed);
		
		$objSettings->addSelect($name."_speed", $arrSpeed, __("Animation Speed","unlimited-elements-for-elementor"), self::TIME_FAST, $params);
		
		
		//------ item order----------
		
		$params = array();
		$params["origtype"] = UniteCreatorDialogParam::PARAM_DROPDOWN;
		$params["elementor_condition"] = array(
			$name."_type!" => "none"
		);
		
		$arrOrder = array(
			"down"=>__("First To Last","unlimited-elements-for-elementor"),
			"up"=>__("Last to First","unlimited-elements-for-elementor")
			//"random"=>__("Random","unlimited-elements-for-elementor")
		);
		
		$arrOrder = array_flip($arrOrder);
		
		$objSettings->addSelect($name."_order", $arrOrder, __("Animate Items Order","unlimited-elements-for-elementor"), "down", $params);

		//------ blur ----------
		
		$params = array();
		$params["origtype"] = UniteCreatorDialogParam::PARAM_DROPDOWN;
		$params["elementor_condition"] = array(
			$name."_type!" => "none"
		);
		
		$arrBlur = array(
			"none"=>__("No Blur","unlimited-elements-for-elementor"),
			self::BLUR_SMALL=>__("Small","unlimited-elements-for-elementor"),
			self::BLUR_MEDIUM=>__("Medium","unlimited-elements-for-elementor"),
			self::BLUR_STRONG=>__("Strong","unlimited-elements-for-elementor"),
		);
		
		
		$arrBlur = array_flip($arrBlur);
		
		$objSettings->addSelect($name."_blur", $arrBlur, __("Add Blur","unlimited-elements-for-elementor"), self::BLUR_MEDIUM, $params);
		
	}
	
	
	/**
	 * get blur css from blur type
	 */
	private function getBlurNum($blurType){
				
		if(empty($blurType))
			return(null);
			
		if($blurType == "none")
			return(null);
		
		$blur = 0;
		
		switch($blurType){
			case "small":
				$blur = 4;
			break;
			case "medium":
				$blur = 10;				
			break;
			case "big":
				$blur = 40;
			break;
			default:
				return(null);
			break;
		}
		
		return($blur);
	}

	/**
	 * get time by time type
	 */
	private function getTime($timeType){
		
		$time = 0;
		
		switch($timeType){
			case self::TIME_FASTEST:
				$time = 0.3;
			break;
			default:
			case self::TIME_FAST:
				$time = 0.6;
			break;
			case self::TIME_MEDIUM:
				$time = 0.9;
			break;
			case self::TIME_SLOW:
				$time = 1;
			break;
			case self::TIME_SLOWEST:
				$time = 1.4;
			break;
			case self::TIME_VERY_SLOW_DEBUG:
				$time = 3;
			break;
		}
		
		
		return($time);
	}
	
	/**
	 * get scale by distance
	 */
	private function getScale($distanceType){
		
		$scale = 0;
		switch($distanceType){
			case self::DISTANCE_SHORTEST:
				$scale = 1.1;
			break;
			default:
			case self::DISTANCE_SHORT:
				$scale = 1.3;
			break;
			case self::DISTANCE_MEDIUM:
				$scale = 1.5;
			break;
			case self::DISTANCE_LONG:
				$scale = 1.8;
			break;
			case self::DISTANCE_LONGEST:
				$scale = 2.2;
			break;
		}
		
		return($scale);
	}
	
	/**
	 * get animation step time for js
	 */
	private function getStepTime($timeType){
				
		switch($timeType){
			
			default:
			case self::TIME_FAST:
			case self::TIME_FASTEST:
				$step = 100;
			break;
			case self::TIME_MEDIUM:
				$step = 150;
			break;
			case self::TIME_SLOW:
			case self::TIME_SLOWEST:
				$step = 200;
			break;
			case self::TIME_VERY_SLOW_DEBUG:
				$step = 1500;
			break;
		}
		
		return($step);
	}
	
	/**
	 * get translate values
	 */
	private function getTranslate($distanceType){
		
		$scale = 0;
		switch($distanceType){
			case self::DISTANCE_SHORTEST:
				$scale = 50;
			break;
			default:
			case self::DISTANCE_SHORT:
				$scale = 100;
			break;
			case self::DISTANCE_MEDIUM:
				$scale = 250;
			break;
			case self::DISTANCE_LONG:
				$scale = 400;
			break;
			case self::DISTANCE_LONGEST:
				$scale = 700;
			break;
		}
		
		return($scale);
		
		
	}
	
	/**
	 * get animation time and distance values
	 */
	private function getKeyframesCss($type, $distanceType){
		
		$from = "";
		$to = "";
		
		switch($type){
			case self::ANIMATION_SCALE_DOWN:
				
				$scale = $this->getScale($distanceType);
				
				$from = "transform:scale({$scale});";
				$to = "transform:scale(1);";
				
			break;
			case self::ANIMATION_SCALE_UP:
				
				$scale = $this->getScale($distanceType);
				
				$newScale = (1 + (1-$scale));
				
				$from = "transform:scale({$newScale});";
				$to = "transform:scale(1);";
				
			break;
			case self::ANIMATION_FROM_LEFT:
				
				$translate = $this->getTranslate($distanceType);
								
            	$from = "transform: translateX(-{$translate}px);";
				$to = "transform: translateX(0px);";
            	
			break;
			case self::ANIMATION_FROM_RIGHT:
				
				$translate = $this->getTranslate($distanceType);
				
            	$from = "transform: translateX({$translate}px);";
				$to = "transform: translateX(0px);";
            	
			break;
			case self::ANIMATION_FROM_TOP:
				
				$translate = $this->getTranslate($distanceType);
				
            	$from = "transform: translateY(-{$translate}px);";
				$to = "transform: translateY(0px);";
			break;
			case self::ANIMATION_FROM_BOTTOM:
				
				$translate = $this->getTranslate($distanceType);
				
            	$from = "transform: translateY({$translate}px);";
				$to = "transform: translateY(0px);";
			break;
			case self::ANIMATION_APPEAR:
				
            	$from = "";
				$to = "";
				
			break;
		}
		
		$output = array();
		
		$output["from"] = $from;
		$output["to"] = $to;
		
		return($output);
	}
	
	
	/**
	 * put entrance animations
	 */
	public function putEntranceAnimationCss_work($arrData, $paramName, $param){
		
		if(empty($param))
			return(false);
		
		if(empty($arrData))
			return(false);

		$arrValues = UniteFunctionsUC::getVal($param, "value");
		
		$animationType = UniteFunctionsUC::getVal($arrValues, $paramName."_type");

		if($animationType == "none" || empty($animationType))
			return(false);
		
		$classItem = UniteFunctionsUC::getVal($param, "entrance_animation_item_class");
		
		if(empty($classItem))
			UniteFunctionsUC::throwError("Please specify item class in widget entrance animation attribute");
			
		$id = UniteFunctionsUC::getVal($arrData, "uc_id");
		
		//get blur
		
		$blurType = UniteFunctionsUC::getVal($arrValues, $paramName."_blur");
		
		$blurNum = $this->getBlurNum($blurType);
		
		//get time (speed)
		
		$timeType = UniteFunctionsUC::getVal($arrValues, $paramName."_speed");		
		$time = $this->getTime($timeType);
		
		//distance
		
		$distanceType = UniteFunctionsUC::getVal($arrValues, $paramName."_distance");		
		$arrKeyFrames = $this->getKeyframesCss($animationType, $distanceType);
		
		
		$from = UniteFunctionsUC::getVal($arrKeyFrames, "from");
		$to = UniteFunctionsUC::getVal($arrKeyFrames, "to");
		
		
		?>
			
@keyframes <?php echo $id?>__item-animation {
  0% {
            <?php echo $from?>
        	
        	<?php if(!empty($blurNum)):?>
        	filter: blur(<?php echo $blurNum?>px);
			<?php endif?>
			        	
    	    opacity: 0;
  }
  100% {
            <?php echo $to?>
            
        	<?php if(!empty($blurNum)):?>
            filter: blur(0px);
            <?php endif?>
            
    		opacity: 1;
  }
}
			
			
#<?php echo $id?> .<?php echo $classItem?>{
	opacity:0;
}


#<?php echo $id?> .uc-entrance-animate {
  opacity:1;
}

#<?php echo $id?> .uc-entrance-animate {
	animation: <?php echo $id?>__item-animation <?php echo $time?>s cubic-bezier(0.470, 0.000, 0.745, 0.715) both;
}

			<?php 
			
		}
		
		
		/**
		 * put entrance animation functions
		 */
		private function putEntranceAnimationJS_functions($checkRunOnce = true){
		
			if($checkRunOnce == true){
				
				$isRunOnce = HelperUC::isRunCodeOnce("ue_entrance_animations_js");

				if($isRunOnce === true){
										
					return(false);
				}
				
			}
						
			
			?>
			
  //start the animation - add animation class
  function ueStartEntranceAnimation(objElement, type, step, classItem, order){
    
    var time = 0;
    
    if(!step)
    	var step = 100;
    
    var objItems = objElement.find("."+classItem);
    
    var numItems = objItems.length;
    
    if(numItems == 0)
    	return(false);
    
    var maxTime = (numItems-1) * step; 
    
    objItems.each(function(index, item){
   		
   	  var timeoutTime = time;
   	  if(order == "up")
   	  	timeoutTime = maxTime - time;
   	  
      var objItem = jQuery(item);

      setTimeout(function(){
			
            objItem.addClass("uc-entrance-animate");

      },timeoutTime);

      time += step;
	
    });
  }
  
    //check and add animation
    function ueCheckEntranceAnimation(objElement, type, step, classItem, order){
                
        var isStarted = objElement.data("ue_entrance_animation_started");
        
        if(isStarted === true)
        	return(false);
                    
      	var isInside = ueIsElementInViewport(objElement);
      	
        if(isInside == false)
          return(false);
      	
        ueStartEntranceAnimation(objElement, type, step, classItem, order);
        
        objElement.data("ue_entrance_animation_started", true);
  }
			
			<?php 
		
		
	}
	
	
	
	
		
	/**
	 * put entrance animation js
	 */
	public function putEntranceAnimationJS_work($arrData, $paramName, $param){
		
		$classItem = UniteFunctionsUC::getVal($param, "entrance_animation_item_class");
									
		$arrValues = UniteFunctionsUC::getVal($param, "value");
		
		$animationType = UniteFunctionsUC::getVal($arrValues, $paramName."_type");
		
		if($animationType == "none" || empty($animationType))
			return(false);
		
		$isInsideEditor = UniteFunctionsUC::getVal($arrData, "uc_inside_editor");
		
		//to boolean
		$isInsideEditor = ($isInsideEditor == "yes");
		
		$id = UniteFunctionsUC::getVal($arrData, "uc_id");
	
		$timeType = UniteFunctionsUC::getVal($arrValues, $paramName."_speed");
				
		$animationStep = $this->getStepTime($timeType);
		
		$order = UniteFunctionsUC::getVal($arrValues, $paramName."_order");
		
		
			?>

/* entrance animation js */		
	
<?php 
	if($isInsideEditor == false){
		HelperHtmlUC::putJSFunc_isElementInViewport();
		$this->putEntranceAnimationJS_functions();
	}

?>
	
jQuery(document).ready(function(){
	
  <?php if($isInsideEditor == true){
		
  		HelperHtmlUC::putJSFunc_isElementInViewport(false);
  		$this->putEntranceAnimationJS_functions(false);
  }
  ?>
  
  var objElement = jQuery("#<?php echo $id?>");
    
    ueCheckEntranceAnimation(objElement,"<?php echo $animationType?>", <?php echo $animationStep?>,"<?php echo $classItem?>", "<?php echo $order?>");
    
    jQuery(window).on("scroll", function(){
    	ueCheckEntranceAnimation(objElement,"<?php echo $animationType?>", <?php echo $animationStep?>, "<?php echo $classItem?>", "<?php echo $order?>")
    });
   
});			
			<?php 
			
		}
		
	/**
	 * put entrance animation css
	 */
	public static function putEntranceAnimationCss($arrData, $paramName, $param){
		
		$objInstance = self::getInstance();
		
		$objInstance->putEntranceAnimationCss_work($arrData, $paramName, $param);
			
	}
		
	/**
	 * put entrance animation js
	 */	
	public static function putEntranceAnimationJS($arrData, $paramName, $param){
		
		$instance = self::getInstance();

		$instance->putEntranceAnimationJS_work($arrData, $paramName, $param);
		
	}
		
	
}
