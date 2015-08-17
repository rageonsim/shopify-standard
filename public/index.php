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

	// Set memory limit to prevent out-of-memory issues
	// ini_set('memory_limit', '-1'); // Unlimited memory... bad idea

	// Define App Root
	define("APP_ROOT",realpath(__DIR__."/.."));
	define("WEB_ROOT",realpath(APP_ROOT."/public"));
	define("REFERER", (isset($_SERVER['HTTP_REFERER'])) ? (
		(strpos($tmp = trim(substr($_SERVER['HTTP_REFERER'],strlen($_SERVER["HTTP_ORIGIN"])),'/'),'/')!==false) ? (
			$tmp
		) : (
			"index/$tmp"
		)
	) : (
		"index/index"
	));

	$request = $_SERVER['REQUEST_URI'];
	@list($controller, $action, $req_etc_raw) =
		explode('/', trim(strpos($request,'?') !== false ? stristr($request,'?',true) : $request,'/'), 3);
	// Parse Additional path from Request String
	$req_etc = "";
	foreach(explode('/', trim($req_etc_raw,'/')) as $key=>$param) {
		$sep = $key%2==0 ? '=' : '&';
		$req_etc .= $param . $sep;
	}
	parse_str(trim($req_etc,'=&'),$req_etc);

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
	 **/

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
		function loadController($contact = "index/index", $__data = null, $rewrite = 0) {
			$view_data = is_array($__data) ? $__data : array($__data);
			if(strpos($contact, "/")!==false) {
				list($controller, $action, $extra) = array_pad(explode("/", $contact, 3),3,null);
				if(!is_null($extra)) {
					$view_data['extra'] = $extra;
				}
				if(is_numeric($rewrite) && $rewrite===1) {
					$view_data['set_url'] = "/$contact/";
				}
			} else {
				$controller = "index";
				$action     = $contact;
			}
			error_log("// load controller: $controller, action: $action");
			$controller_path = realpath("../controllers/".$controller.".php");
			if(!$controller_path) $controller_path = realpath("../controllers/index.php");
			error_log("including: $controller/$action from $controller_path");
			return (include $controller_path);
		}
	}
	$initialControllerLoad = loadController("$controller/$action");
	// die(var_dump(array("initialControllerLoad"=>$initialControllerLoad)));
