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

// set the default value for $layout, which is 'default', and inherit or default view_data
$layout     = !isset($layout)    || empty($layout)    ? 'default' : $layout;
$view_data  = !isset($view_data) || empty($view_data) ?  array()  : $view_data;

$html_title = 'Update';

//Switch through possible Actions
switch($action) {
	// Update Skus, i.e. show form for data editing
	case 'skus':
		$html_title .= " - Skus";
		$view_data['page_title'] = "Update Skus Form";
		$view_data['page_lead']  = "The Following SKUs Contain Issues: <small>(which cannot be autocorrected)</small>";
		// $data = $view_data;
		// $view_data = array();
		// $view_data['dumpme'] = $data;
		if(!isset($view_data)) $view_data = array();
		if(!array_key_exists("sku_parse_error", $view_data)) {
			return loadController("fix-options", $view_data, 1);
		}
		$view_data = array_merge($view_data, array(
			'products_url'    => "https://www.rageon.com/products/",
			'errors_per_page' => 20,
			'save_action'     => "save/skus"
		));
	break;
	// Update Skus, i.e. show form for data editing
	case 'colors':
		$html_title .= " - Colors";
		$view_data['page_title'] = "Update Colors Form";
		$view_data['page_lead']  = "The Following Colors Must Be Determined:";
		// $data = $view_data;
		// $view_data = array();
		// $view_data['dumpme'] = $data;
		if(!isset($view_data)) $view_data = array();
		if(!array_key_exists("color_needs_determination_error", $view_data)) {
			return loadController("fix-options", $view_data, 1);
		}
		$view_data = array_merge($view_data, array(
			'products_url'    => "https://www.rageon.com/products/",
			'errors_per_page' => 20,
			'save_action'     => "save/colors",
			'auto_advance'    => !!(isset($_COOKIE['ShopifyStandard::auto_advance:color'])&&(intval($_COOKIE['ShopifyStandard::auto_advance:color'])===1))
		));
	break;
	default:
		//should probably error out here
}

// Include the appropriate layout view
return ((include_once (__DIR__."/../views/layouts/".$layout.".phtml"))!==false);