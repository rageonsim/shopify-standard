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
$state  = !isset($state)  || empty($state)  ?  array()  : $state;

// set layout to ajax specifically
$layout = 'ajax';

//Switch through possible Actions
switch($action) {
	// Example 'add' action
	case 'determine':
		switch($req_etc) {
			case "color":
				/** params_keys: var_sku, pro_sku, group, size, special, column, cur_key, org_opts, mod_opts, ajax_url, cur_val */
				extract($params,EXTR_SKIP);
				$state['request'] = array(
					"controller" => $controller,
					"action"     => $action,
					"req_etc"    => $req_etc,
					"params"	 => $params
				);
				$suggestion = $db->getColor($params);
				// $state['suggestion'] = $suggestion['suggestion'];
				// $state['from_cache'] = $suggestion['cached'];
				if(!$suggestion['suggestion']) {
					$state['erros'] = $db->getLastState(10, null, null) ?: error_get_last();
				}
				ShopifyStandard::array_extend($state, $suggestion);
				$state['color_cache'] = $db->colorCache();
			break;
		}
	break;
	// Index Action
	case 'index':
	default:
}

// Include the appropriate layout view
return include_once(__DIR__."/../views/layouts/".$layout.".phtml");