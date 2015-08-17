<?php
/**
 * Index Controller
 **/

// echo "Start Index Controller\n\n";
// var_dump(array(
// 	"controller" => $controller,
// 	"action"	 => $action,
// 	"req_etc"	 => $req_etc,
// 	"params"	 => $params
// ));
// echo "\n\nEnd Index Controller";

// Get database object
// Require the ShopifyStandard Class
require_once("../classes/ShopifyStandard.php");
// Make Object and test for correctness
if(!isset($db)) $db = ShopifyStandard::getInstance();
if(!($db instanceof ShopifyStandard)) die(var_dump($db));

// set the default value for $layout, which is 'default'
$layout     = !isset($layout)    || empty($layout)    ? 'default' : $layout;
$view_data  = !isset($view_data) || empty($view_data) ?  array()  : $view_data;

$html_title = '';

//Switch through possible Actions
switch($action) {
	case 'setup':
		$_use_view = "test";
		$view_data['dumpme'] = 
			array(
				//"getCSVData" => $db->getCSVData(),
				"setup_data" => $db->setupProductTables()
			);
	break;
	case 'options':
		$_use_view = "test";
		// lists count of options and values
		//$view_data['dumpme'] = $db->getOptionKeyValues();

		// gets current options for each product, and variant, with respect to the missing keys on variants
		$view_data['dumpme'] = $db->standardizeOptions("tt");
	break;
	// Fix Options
	case 'fix-options':
		// but call our main function and just return the results
		$fix = $db->doFixOptions();
		if($db->isError($fix)) {
			$error_codes = array_keys($fix);
			$error_code  = array_pop($error_codes);
			switch($error_code) {
				case "sku_parse_error":
					// sku parse error
					return loadController("update/skus", array_merge($view_data,$fix), 1);
				break;
				default:
					// dump unknown errors
					$view_data['dumpme'] = array("error_code"=>$error_code,"unknown_error"=>$fix);
				break; // uneccessary, but looks better
			}
		} else {
			// dump data if no error, for now anyways
			$view_data['dumpme'] = array("no_error"=>$fix);
		}
	break;
	case 'test':
		$view_data['dumpme'][] = array();
		/* Old Tests
			$view_data['dumpme'][] = $db->query("SELECT * FROM org_export WHERE body_html LIKE '%Make%everyone%' AND handle LIKE '3d%'")->fetch_object();
			$view_data['dumpme'][] = $db->getColorFromHex("#FECB89");
			$view_data['dumpme'][] = $db->doNewImportColorFix();
			$view_data['dumpme'][] = $db->selectProductData();

			$view_data['dumpme'] = array();
			$view_data['dumpme'][] = $testarr = array("test"=>array("subtest"=>false,"subtest2"=>false),"tast"=>array("subtast"=>false,"subtast2"=>false));
			foreach($testarr as $testkey=>&$test) {
				foreach($test as $key=>&$val) {
					$val = true;
				}
				if($testkey == "tast") {
					$db->switchKey($testarr,"tast","tust");
					// $testarr["tust"] = $test;
					// unset($testarr[$testkey]);
				}
			}
			$view_data['dumpme'][] = $testarr; */
		$view_data['dumpme'] = 'test';
	break;
	// Index Action
	case 'index':
		// $view_data['csv_data'] = 
		//$db->getCSVData();
		//$view_data['queries'] = $db->writeCSVData();
		$view_data['download'] = array(
			"url" => realpath("{WEB_ROOT}"),
			"text"=> "Click here to download generated CSV"
		);
	break;
	// unknown action
	default:
		$action = "404";
}

// Include the appropriate layout view
return ((include_once (__DIR__."/../views/layouts/".$layout.".phtml"))!==false);