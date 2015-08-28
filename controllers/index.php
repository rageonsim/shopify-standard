<?php
/**
 * Index Controller
 **/

// echo "Start Index Controller\n\n";
// var_dump(array(
//  "controller" => $controller,
//  "action"   => $action,
//  "req_etc"  => $req_etc,
//  "params"   => $params
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
$state  = !isset($state) || empty($state) ?  array()  : $state;

$html_title = '';

//Switch through possible Actions
switch($action) {
  case 'setup':
    $_use_view = "test";
    $state['dumpme'] = 
      array(
        //"getCSVData" => $db->getCSVData(),
        "setup_data" => $db->setupProductTables()
      );
  break;
  case 'options':
    $_use_view = "test";
    // lists count of options and values
    //$state['dumpme'] = $db->getOptionKeyValues();

    // gets current options for each product, and variant, with respect to the missing keys on variants
    $state['dumpme'] = $db->standardizeOptions();
  break;
  // Fix Options
  case 'fix-options':
    // but call our main function and just return the results
    $fix = $db->standardizeOptions();
    if($db->isError($fix)) {
      $error_codes = array_keys($fix);
      $error_code  = array_shift($error_codes);
      switch($error_code) {
        case "sku_parse_error":
          // sku parse error
          return loadController("update/skus", $fix, 1);
        break;
        case "color_needs_determination_error":
          // ajax determination form required
          return loadController("update/colors", $fix, 1);
        break;
        default:
          // dump unknown errors
          $state['dumpme'] = array("error_code"=>$error_code,"unknown_error"=>$fix);
        break; // uneccessary, but looks better
      }
    } else {
      // dump data if no error, for now anyways
      $state['dumpme'] = array("no_error"=>$fix);
    }
  break;
  case 'test':
    $state['html_title'] = "ShopifyStandard Test";
    $state['page_title'] = "Test:";
    //$state['dumpme'][] = array();
    /* Old Tests
      $state['dumpme'][] = $db->query("SELECT * FROM org_export WHERE body_html LIKE '%Make%everyone%' AND handle LIKE '3d%'")->fetch_object();
      $state['dumpme'][] = $db->getColorFromHex("#FECB89");
      $state['dumpme'][] = $db->doNewImportColorFix();
      $state['dumpme'][] = $db->selectProductData();

      $state['dumpme'] = array();
      $state['dumpme'][] = $testarr = array("test"=>array("subtest"=>false,"subtest2"=>false),"tast"=>array("subtast"=>false,"subtast2"=>false));
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
      $state['dumpme'][] = $testarr;
      $state['dumpme'] = $db->checkValueInvalid(1,"Black",array(
        "var_sku"=>"DTGTT0006UXL",
        "pro_sku"=>"DTGTT0006",
        "group"=>"U",
        "size"=>"XL",
        "special"=>"",
        "opt_key"=>"Color",
        "org_opts"=>array(array(
            "Size"=> "X-Large"),array(
            "Color"=> "Black"),array(
            ""=> "")),
        "mod_opts"=>array(array(
            "Size"=> "XL"),array(
            "Color"=> "Black"),array(
            ""=> ""))
      )); */
    //$state['dumpme'] = unserialize($_COOKIE[ShopifyStandard::COLOR_CACHE_COOKIE]);
    
    $arr = array(
      "123" => array(
        "update" => "sql",
        "error"  => "errstr"
      ),
      "789" => "dome",
      "456" => array(
        "update" => "sql2",
        "error"  => "erstr2"
      )
    );

    $state['dumpme'] = array_filter($arr, "is_array");
    // array_column($arr,"error","update");

    
  break;
  // Index Action
  case 'index':
    // mask call to fix-options action
    return loadController("fix-options",$state);
    // $state['csv_data'] = 
    //$db->getCSVData();
    //$state['queries'] = $db->writeCSVData();
    $state['download'] = array(
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