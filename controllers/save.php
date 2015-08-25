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
		if(!$sku_data) return loadController($return_to, array("error"=>$db->setState("null_sku_data_error","No SKU Data Received by the Server",$_POST,"loadOptions")));		
		$fix_skus = $db->doUpdateSkus($sku_data);
		// if(array_key_exists("error", $fix_skus)) {
		// 	return loadController("save/error", $fix_skus);
		// } else
		// if(array_key_exists("success", $fix_skus)) {

		// return to previous page, with object containing keys for 'display_error' or 'display_success'
		// does not need any other data based back, errors will be recalculated, just display for user support.
		return loadController($return_to, $fix_skus, 1);
		
		// }
	break;
	default:
		// probably want to error here
}

// Include the appropriate layout view
return include_once(__DIR__."/../views/layouts/".$layout.".phtml");