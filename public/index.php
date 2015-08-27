<?php

	/**
	 * App Index File
	 * 
	 * Funnel all requests through this file (except for ajax controller, for now)
	 *
	 * Sets app values from request: controller, action (which sets view), routing noted below
	 * 
	 * Primary functionality is to include the appropriate controller
	 *
	 **/

	require_once("../classes/ShopifyStandard.php");

	// Set Debug Mode
	define("DEBUG", true); // ideally set from config file, change to false before production

	if(DEBUG) {
		// Set memory limit to prevent out-of-memory issues
		// ini_set('memory_limit', '-1'); // Unlimited memory... bad idea
		// increase execution time to 60 seconds for debugging
		set_time_limit(30);
	}

	if(DEBUG) error_log("/*****************************************************************************************\\") &&
			  error_log("|******************************* New SoSt Request ****************************************|")  &&
			  error_log("\*****************************************************************************************/");
	// Define App Root
	define("APP_ROOT",realpath(__DIR__."/.."));
	define("WEB_ROOT",realpath(APP_ROOT."/public"));

	$_REFERER = null;
	function REFERER() {
		global $_REFERER;
		if(func_num_args()==0) {
			return is_null($_REFERER) ? REFERER(null,null) : $_REFERER;
		} elseif(func_num_args()==1) {
			$func_args = func_get_args();
			$_REFERER  = array_shift($func_args);
		} else {
			REFERER((isset($_SERVER['HTTP_REFERER'])) ? (
				(strpos($tmp = trim(substr($_SERVER['PHP_SELF'],strlen(basename(__FILE__))+1),'/'),'/')!==false) ? (
					$tmp
				) : (
					"index/$tmp"
				)
			) : (
				"index/index"
			));
		}
	}
	REFERER(/* Set Initial $_REFERER Value on Page Load */);

	$request = $_SERVER['REQUEST_URI'];
	$req_arr = explode('/', trim(strpos($request,'?') !== false ? stristr($request,'?',true) : $request,'/'), 3);
	list($controller, $action, $req_etc_raw) = (count($req_arr)==3) ? $req_arr : array_pad($req_arr, 3, "");

	// Parse Additional path from Request String
	$req_etc = "";
	foreach(explode('/', trim($req_etc_raw,'/')) as $key=>$param) {
		$sep = $key%2==0 ? '=' : '&';
		$req_etc .= $param . $sep;
	}
	parse_str(trim($req_etc,'=&'),$req_etc);
	if(count($req_etc)===1&&strcasecmp(reset($req_etc), "")===0) $req_etc = array_keys($req_etc)[0];

	// Get any Request Params
	$params  = $_REQUEST;

	// Set Defaults if necessary
	if(!empty($controller) && empty($action)) {
		$action     = $controller;
		$controller = "index";
	} else {
		$controller = strtolower(empty($controller) ? "index" : $controller);
		$action     = strtolower(empty($action) ? "index" : $action);
	}

	// Set Initial State
	$state = array();

	/** Data Example (from routing):
	 *
	 * Request: http://shopifystandard.loc/controller/action/crap/can/go/here/?param1=1&param2=2
	 *
	 * Data:
	 *	["controller"]=>
	 *		string(10) "controller"
	 *	["action"]=>
	 *		string(6) "action"
	 *	["req_etc"]=>
	 *		["crap"]=>
	 *			string(3) "can"
	 *		["go"]=>
	 *			string(4) "here"
	 *	["params"]=>
	 *		["param1"]=>
	 *			string(1) "1"
	 *		["param2"]=>
	 *			string(1) "2"
	 *
	 */

	// Load Partial Function (both below shouldl be class methods, (this one particularly should be a view helper), but for quickness, procedural style)
	if(!function_exists("loadPartial")) {
		function loadPartial($_partial, $_data = null) {
			$_partial_path = realpath(APP_ROOT."/views/partial/$_partial.phtml");
			if(!$_partial_path) {
				$_partial_path = realpath(APP_ROOT."/views/errors/404.phtml");
				error_log("// Partial View: Failed Loading: '$_partial' from '$_partial_path';");
			}
			if(!is_null($_data)) extract($_data);
			return (include $_partial_path);
		}
	}

	// Include the controller
	if(!function_exists("loadController")) {
		function loadController($contact = "index/index", $_state = null, $rewrite = 0, $clear = 0) {
			global $controller, $action, $req_etc, $params, $state;
			$state  = is_null($state)  ? array() : (is_array($state)  ? $state  : array($state));
			$_state = is_null($_state) ? array() : (is_array($_state) ? $_state : array($_state));
			if($clear===1) {
        $state = $_state;
      } else {
				ShopifYStandard::array_extend($state, $_state);
      }
			if(strpos($contact, "/")!==false) {
				if(is_numeric($rewrite) && $rewrite===1) {
					$state['set_url'] = "/$contact/";
					REFERER(($controller!=='index'?"$controller/":'').$action);
				}
				list($controller, $action, $extra) = array_pad(explode("/", $contact, 3),3,null);
				if(!is_null($extra)) {
					$state['extra'] = $extra;
				}
			} else {
				$controller = "index";
				$action     = $contact;
			}
			$controller_path = realpath("../controllers/".$controller.".php");
			if(!$controller_path) $controller_path = realpath("../controllers/index.php");
			error_log("// load controller: $controller, action: $action, controller_path: $controller_path");
			return (include $controller_path);
		}
	}
	$initialControllerLoad = loadController("$controller/$action");
