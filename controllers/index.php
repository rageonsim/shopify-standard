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
  case 'setup':
    $_use_view = "test";
    $state['page_title'] = "Setup Product Tables";
    $state['page_lead']  = "For Initial Setup. Writes from Latest CSV. To Clear Old Entries Before Writing. <a href='/update' title='Clear Old Products & Refill'>UPDATE</a>.";
    $state['dumpme']     =  $db->setupProductTables();
  break;
  case 'update':
    // Set memory limit to prevent out-of-memory issues
    ini_set('memory_limit', '-1'); // Unlimited memory... bad idea
    // increase execution time to 60 seconds for debugging
    set_time_limit(180);
    // use blank view
    $_use_view = "test";
    $state['html_title'] = "Update from Shopify";
    $state['page_title'] = "Update Product Tables";
    $state['page_lead']  = "Clear Old Entries Before Re-Writing From Latest CSV.";
    $update_response     = $db->updateProductTables(true, false);
    $state['dumpme']     = $update_response===true ? date(DATE_W3C) : $update_response;
  break;
  // Fix Options
  case 'fix-options':
    set_time_limit(-1); // unlimited, bad idea
    $_use_view = 'test'; // blank
    // but call our main function and just return the results
    if(isset($params['type_code'])) {
      $fix = $db->standardizeOptions($params['type_code']);
    } else {
      $fix = $db->standardizeOptions();
    }
    if($fix!==true&&$db->isError($fix)) {
      $error_codes = array_keys($fix);
      $error_code  = array_shift($error_codes);
      switch($error_code) {
        case "sku_update_error":
          if(isset($fix['sku_update_error'])) {
            $state['display_error'] = isset($state['display_error']) ? array_merge($state['display_error'],$fix['sku_update_error']) : $fix['sku_update_error'];
            unset($fix['sku_update_error']);
          }
        // no break;
        case "sku_save_error":
          if(isset($fix['sku_save_error'])) {
            $state['display_error'] = isset($state['display_error']) ? array_merge($state['display_error'],$fix['sku_save_error']) : $fix['sku_save_error'];
            unset($fix['sku_save_error']);
          }
        // no break;
        case "sku_parse_error":
          // sku parse error
          return loadController("update/skus", $fix, 1);
        break;
        case "color_update_error":
          if(isset($fix['color_update_error'])) {
            $state['display_error'] = isset($state['display_error']) ? array_merge($state['display_error'],$fix['color_update_error']) : $fix['color_update_error'];
            unset($fix['color_update_error']);
          }
        // no break;
        case "color_save_error":
          if(isset($fix['color_save_error'])) {
            $state['display_error'] = isset($state['display_error']) ? array_merge($state['display_error'],$fix['color_save_error']) : $fix['color_save_error'];
            unset($fix['color_save_error']);
          }
        // no break;
        case "ajax_determine_color_error":
        case "color_needs_determination_error":
          if(isset($fix['ajax_determine_color_error'])) {
            if(isset($fix['color_needs_determination_error'])) {
              $fix['color_needs_determination_error'] = array_merge($fix['color_needs_determination_error'], $fix['ajax_determine_color_error']);
            } else {
              $fix['color_needs_determination_error'] = $fix['ajax_determine_color_error'];
            }
          }
          // ajax determination form required
          if(!isset($state['color_needs_determination_error'])&&!isset($fix['color_needs_determination_error'])) {
            return loadController("errors", $fix, 1);
          } else {
            return loadController("update/colors", $fix, 1);
          }
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
  case 'write-export':
    $state['html_title'] = "Write Export";
    $state['page_title'] = "Write CSV Export";
    $state['page_lead']  = "A CSV to import into Shopify should be generated below:";
    $type_code = isset($_GET['type']) ? $_GET['type'] : null;
    $state['exp_info']   = $db->writeExportFile($type_code);
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
      // array_column($arr,"error","update");
    $arr = array();
    // $state['dumpme'] = unserialize(current($_COOKIE));
    // $state['dumpme'] = null ?: '' ?: 'hello';
    // $state['dumpme'] = $db->getTableBySku("SAEX00100UOS", "products_X0_edited", "products_","_edited");
    // $state['dumpme'] = $db->getProductDataBySkuFullSearch("Red Green Yellow, Grey",false,"colunn_2_value");
    // $arr = array_fill(0, 16, 0);
    // array_walk($arr, function(&$val,$key) {
    //   $tmp_val = sprintf("%02d",$key+1);
    //   $val = '"'.$tmp_val.'"    => "'.$tmp_val.'",';
    // });
    // $state['dumpme'] = "\n".implode("\n",$arr)."\n";
    
    // $state['dumpme'] = $db->getBoolCols();
    
    // $state['dumpme'] = array();
    // foreach($db->getAllTables('products_', false, '_edited', true) as $edited) {
    //   $state['dumpme'][$edited] = $db->query("SELECT * FROM $edited WHERE variant_sku LIKE '%-_'");
    // }
    // $state['dumpme'] = array_filter($state['dumpme'], function($val) {
    //   return count($val)!=0;
    // });
    
    // // $sku = "AOPBA1037UOSAPT";
    // $sku = "AOPBA0082UOSAPT";
    
    // $csv_data = $db->getCSVData();
    // $csv_data = array_column($csv_data, null, 'variant_sku');
    // $state['dumpme']['from_csv'] = ($csv_text = $csv_data[$sku]['body_html']);

    // $state['dumpme']['from_db'] = ($db_text = $db->getProductDataBySku($sku)['body_html']);
    // // $state['dumpme']['db_decode'] = ($db_text = html_entity_decode($text));
    // // $state['dumpme'][] = $db->fixTextEncoding($text,true,false,false,true);
    // // $state['dumpme'][] = $db->fixTextEncoding($text,true,false,false,null);
    // $state['dumpme']['csv_false_true_true_true'] = $db->fixTextEncoding($csv_text,false,true,true,true);
    // $state['dumpme']['csv_true_true_true_true'] = $db->fixTextEncoding($csv_text,true,true,true,true);
    // //$state['dumpme'][] = $db->fixTextEncoding($text,false,true,false,null);
    
    // $state['dumpme'] = $db->fixTableType();
    
    $state['dumpme'] = array_filter(array(null)) ?: "asdf";

  break;
  // set prices from original tables on edited tables
  case 'set-org-prices':
    $state['dumpme'] = $db->setPricesFromUnedited();
  break;
  // set prices from original tables on edited tables
  // case 'allow-null-bool':
  //   $state['dumpme'] = $db->allowNullBool();
  // break;
  // unknown action
  default:
    $state['page_title'] = "404 Page Not Found";
    $action = "404";
}

// Include the appropriate layout view
return ((include_once (__DIR__."/../views/layouts/".$layout.".phtml"))!==false);