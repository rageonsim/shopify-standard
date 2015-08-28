<?php
/**
 * Save Controller
 **/

// echo "Start Save Controller\n\n";
	// var_dump(array(
	// 	"controller" => $controller,
	// 	"action"	 => $action,
	// 	"req_etc"	 => $req_etc,
	// 	"params"	 => $params
	// ));
	// echo "\n\nEnd Save Controller";

// Get database object
// Require the ShopifyStandard Class
require_once("../classes/ShopifyStandard.php");
// Make Object and test for correctness
if(!isset($db)) $db = ShopifyStandard::getInstance();
if(!($db instanceof ShopifyStandard)) die(var_dump($db));

// set the default value for $layout, which is 'default'
$layout = !isset($layout) || empty($layout) ? 'default' : $layout;
$state  = !isset($state)  || empty($state)  ?  array()  : $state;

$state  = array();
$html_title = 'Save';

//Switch through possible Actions
switch($action) {
	// Save Skus, posted from update form
	case 'skus':
		$sku_data  = $_POST['skus'];
		$return_to = isset($_POST['return_to']) ? trim($_POST['return_to'],'/') : REFERER();
		if(!$sku_data) return loadController($return_to, array("error"=>$db->setState("null_sku_data_error","No SKU Data Received by the Server",$_POST,null,"loadOptions")));
		$fix_skus = $db->doUpdateSkus($sku_data);
		ShopifyStandard::diedump(compact(explode('$','$sku_data$return_to$fix_skus')));
		return loadController($return_to, $fix_skus, 1, 1);
	break;
	case 'colors':
		// clear state
		$state      = array();
		$color_data = $_POST['colors'];
		$return_to  = isset($_POST['return_to']) ? trim($_POST['return_to'], '/') : REFERER();
		$auto_adv   = isset($_POST['auto_advance']) || (!!(isset($_COOKIE['ShopifyStandard::auto_advance:color'])&&(intval($_COOKIE['ShopifyStandard::auto_advance:color'])==1)));
		if(!$color_data) return loadController($return_to, array("error"=>$db->setState("null_color_data_error","No Color Data Recieved by the Server",$_POST,null,"loadOptions")));
		if($auto_adv) setcookie("ShopifyStandard::auto_advance:color",
			1, // set data to '1' in order to denote auto-advance is on. otherwise destroy the cookie, but not dealing with that now.
		time()+86400,"/",$_SERVER['HTTP_HOST']);
		$fix_colors = $db->doUpdateColors($color_data);
		// ShopifyStandard::diedump($return_to, $fix_colors,$color_data)&&exit(1);
		return loadController($return_to, $fix_colors, 1, 1);
	default:
		// probably want to set error properties here, shows 404.
}

// Include the appropriate layout view
return include_once(__DIR__."/../views/layouts/".$layout.".phtml");