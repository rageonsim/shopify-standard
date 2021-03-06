<?php
/**
 * Update Controller
 **/

// echo "Start Update Controller\n\n";
	// var_dump(array(
	// 	"controller" => $controller,
	// 	"action"	 => $action,
	// 	"req_etc"	 => $req_etc,
	// 	"params"	 => $params
	// ));
	// echo "\n\nEnd Update Controller";

// Get database object
// Require the ShopifyStandard Class
require_once("../classes/ShopifyStandard.php");
// Make Object and test for correctness
if(!isset($db)) $db = ShopifyStandard::getInstance();
if(!($db instanceof ShopifyStandard)) die(var_dump($db));

// set the default value for $layout, which is 'default', and inherit or default state
$layout = !isset($layout) || empty($layout) ? 'default' : $layout;
$state  = !isset($state)  || empty($state)  ?  array()  : $state;

$html_title = 'Update';

//Switch through possible Actions
switch($action) {
	// Update Skus, i.e. show form for data editing
	case 'skus':
		$html_title .= " - Skus";
		$state['page_title'] = "Update Skus Form";
		$state['page_lead']  = "The Following SKUs Contain Issues: <small>(which cannot be autocorrected)</small>";
		// $data = $state;
		// $state = array();
		// $state['dumpme'] = $data;
		if(!isset($state)) $state = array();
		// if(!array_key_exists("sku_parse_error", $state)) {
		// 	return loadController("fix-options", $state, 1);
		// }
		$state = array_merge($state, array(
			'products_url'    => "https://www.rageon.com/products/",
			'errors_per_page' => 20,
			'save_action'     => "save/skus"
		));
	break;
	// Update Skus, i.e. show form for data editing
	case 'colors':
		$html_title .= " - Colors";
		$state['page_title'] = "Update Colors Form";
		$state['page_lead']  = "The Following Colors Must Be Determined:";
		// $data = $state;
		// $state = array();
		// $state['dumpme'] = $data;
		if(!array_key_exists("color_needs_determination_error", $state)) {
			return loadController("fix-options", $state, 1);
		}
		
		$to_edit = array_column($state['color_needs_determination_error'],null,"var_sku");
		$duplicates = array();
		$remove     = array();
		foreach($state['color_needs_determination_error'] as $_sku => $_error) {
			if(in_array($_sku,$duplicates)&&!in_array($_sku, $remove)) {
				$remove[] = $_sku;
			} elseif(!in_array($_sku,$duplicates)) {
				$duplicates[] = $_sku;
			}
		}
		$state['color_needs_determination_error'] = array_values(array_diff_key($to_edit, array_flip($remove)));
		$state = array_merge($state, array(
			'products_url'    => "https://www.rageon.com/products/",
			'errors_per_page' => 20,
			'save_action'     => "save/colors",
			'auto_advance'    => !!(isset($_COOKIE['ShopifyStandard::auto_advance:color'])&&(intval($_COOKIE['ShopifyStandard::auto_advance:color'])==1))
		));
	break;
	default:
		$html_title = "404 Not Found";
		$state['_use_view']  = "404";
		$state['page_title'] = "404 Not Found";
		$state['page_lead']  = "The Following Colors Must Be Determined:";
}

// Include the appropriate layout view
return ((include_once (__DIR__."/../views/layouts/".$layout.".phtml"))!==false);