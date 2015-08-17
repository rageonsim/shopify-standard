<?php
/**
 * AJAX Controller
 **/

// echo "Start AJAX Controller\n\n";
	// var_dump(array(
	// 	"controller" => $controller,
	// 	"action"	 => $action,
	// 	"req_etc"	 => $req_etc,
	// 	"params"	 => $params
	// ));
	// echo "\n\nEnd AJAX Controller";

// Get database object
// Require the ShopifyStandard Class
require_once("../classes/ShopifyStandard.php");
// Make Object and test for correctness
if(!isset($db)) $db = ShopifyStandard::getInstance();
if(!($db instanceof ShopifyStandard)) die(var_dump($db));

// set the default value for $layout, which is 'default'
$layout = !isset($layout) || empty($layout) ? 'default' : $layout;

// set layout to ajax specifically
$layout = 'ajax';

//Switch through possible Actions
switch($action) {
	// Example 'add' action
	case 'add':

	break;
	// Index Action
	case 'index':
	default:
}

// Include the appropriate layout view
return include_once(__DIR__."/../views/layouts/".$layout.".phtml");