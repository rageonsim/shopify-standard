<?php
/**
 * ShopifyStandard
 */

require 'vendor/autoload.php';

use League\ColorExtractor\Client as ColorExtractor;

class ShopifyStandard {

  /**
   * Protected Instance Variable
   */

  protected static $_instance = null;

  /**
   * Public Instance Factory
   **/
  public static function getInstance($in_path = null,$out_path = null,$gen_csv=true) {
    if(!isset(self::$_instance) || is_null(self::$_instance) || !(self::$_instance instanceof ShopifyStandard)) {
      self::$_instance = New ShopifyStandard($in_path,$out_path,$gen_csv);
    }
    // if(!empty(self::$_instance->states)) return self::$_instance->states;
    return self::$_instance;
  }

  /**
   * Private Class Variables
   */

  protected $debug      = true; // should be a boolean representing whether to display certain debug info. false for production. (duh).
  // protected $debug_sku  = "last"; // "AOPTT1167"; // product sku without variant codes to dump after last variant, any string to dump after all variants

  private $user          = 'root';
  private $pass          = 'root';
  private $db_name       = 'shopify_standard';
  private $host          = 'localhost';
  private $port          = 3306;
  private $db            = null;
  private $csv_path      = '../assets/products_export_08-04-2015.csv';
  private $csv_cols      = array();
  private $csv_data      = array();
  private $csv_handle    = null;
  private $states        = array();
  private $states_data   = array();
  private $max_errors    = 1000;
  private $processable_states = array(
    "database_select_error",
    "sku_parse_error",
    "create_mod_table_error",
    "preservation_error",
    "color_needs_determination_error",
    "indeterminate_value_error"
  );
  private $colorx        = null;
  private $color_cache   = null;
  private $product_types = array();
  /** product data in array with keys being the type_code */
  private $product_data  = array();
  private $modifications = array( 0 => array(// keeps track of value modification
    "valid" => false, // current size is valid, move on to checking key
    "mod" => false, // current size needs to be changed to 2 letter code
    "mutate"=> false  // current size needs modified or is not size, and should be preserved or checked if color
  ));


  /**
   * Protected Constructor and Clone (protect for singleton)
   */
  protected function __clone() {} // unecessary anyway
  protected function __construct($in_path,$out_path,$gen_csv=true) {
    if($this->debug) error_log("////////////////////////////////// START RUN (".date("Y-m-d H:i:s").") ShopifyStandard CLASS //////////////////////////////");
    if($this->debug) register_shutdown_function("ShopifyStandard::diedump");
    $this->in_path  = is_null($in_path) ? $this->csv_path : $in_path;
    $this->out_path = is_null($out_path)? $this->csv_path."_edited_".time().".csv" : $out_path;
    $this->gen_csv  = (bool)$gen_csv;
    self::init_static();
    
    return $this->connect() && $this->setValidation();
  }

  /**
   * Public Class Variables
   */

  public $connected = false;

  /**
   * Class Constant Variables
   */
  
  const COLOR_CACHE_COOKIE = "ShopifyStandard::color_cache";

  const COL_MAP = array(
    "handle"                                  => "Handle",
    "title"                                   => "Title",
    "body_html"                               => "Body (HTML)",
    "vendor"                                  => "Vendor",
    "type"                                    => "Type",
    "tags"                                    => "Tags",
    "published"                               => "Published",
    "option_1_name"                           => "Option1 Name",
    "option_1_value"                          => "Option1 Value",
    "option_2_name"                           => "Option2 Name",
    "option_2_value"                          => "Option2 Value",
    "option_3_name"                           => "Option3 Name",
    "option_3_value"                          => "Option3 Value",
    "variant_sku"                             => "Variant SKU",
    "variant_grams"                           => "Variant Grams",
    "variant_inventory_tracker"               => "Variant Inventory Tracker",
    "variant_inventory_qty"                   => "Variant Inventory Qty",
    "variant_inventory_policy"                => "Variant Inventory Policy",
    "variant_fulfillment_service"             => "Variant Fulfillment Service",
    "variant_price"                           => "Variant Price",
    "variant_compare_at_price"                => "Variant Compare At Price",
    "variant_requires_shipping"               => "Variant Requires Shipping",
    "variant_taxable"                         => "Variant Taxable",
    "variant_barcode"                         => "Variant Barcode",
    "image_src"                               => "Image Src",
    "image_alt_text"                          => "Image Alt Text",
    "gift_card"                               => "Gift Card",
    "google_shopping_mpn"                     => "Google Shopping / MPN",
    "google_shopping_gender"                  => "Google Shopping / Gender",
    "google_shopping_age_group"               => "Google Shopping / Age Group",
    "google_shopping_google_product_category" => "Google Shopping / Google Product Category",
    "seo_title"                               => "SEO Title",
    "seo_description"                         => "SEO Description",
    "google_shopping_adwords_grouping"        => "Google Shopping / AdWords Grouping",
    "google_shopping_adwords_labels"          => "Google Shopping / AdWords Labels",
    "google_shopping_condition"               => "Google Shopping / Condition",
    "google_shopping_custom_product"          => "Google Shopping / Custom Product",
    "google_shopping_custom_label_0"          => "Google Shopping / Custom Label 0",
    "google_shopping_custom_label_1"          => "Google Shopping / Custom Label 1",
    "google_shopping_custom_label_2"          => "Google Shopping / Custom Label 2",
    "google_shopping_custom_label_3"          => "Google Shopping / Custom Label 3",
    "google_shopping_custom_label_4"          => "Google Shopping / Custom Label 4",
    "variant_image"                           => "Variant Image",
    "variant_weight_unit"                     => "Variant Weight Unit"
  );
  
  // valid keys for the column of index+1, verbose for readibility
  const VALID_KEYS = array(
    0 => "Size",
    1 => "Color",
    2 => "Kind"
  );

  private function classVarByColumn($column, $prefix, $suffix = "S") {
    return strtoupper($prefix.self::VALID_KEYS[$column].$suffix);
  }

  private function validationVarByColumn($column, $prefix = "VALID_", $suffix = "S") {
    return $this->classVarByColumn($column,$prefix,$suffix);
  }

  private function autocorrectVarByColumn($column, $prefix = "AUTO_CORRECT_", $suffix = "S") {
    return $this->classVarByColumn($column,$prefix,$suffix);
  }

  // utility methods for getting validation arrays
  public function VV($column=null) { return $this->{$this->validationVarByColumn($column)}; }
  public function VK($index=null) { return is_null($index) ? self::VALID_KEYS : self::VALID_KEYS[$index]; }
  public function AC($column=null) { return $this->{$this->autocorrectVarByColumn($column)}; }

  private function setValidation() {
    // not really static, but function similarly
    // in future, this data should be pulled from the database

    try {
      /**
       * set Validation Arrays: VALID_{COLUMN}S
       */

      // define column_1 valid values (key being valid, value being mutation)
      // should be named VALID_SIZES (default, but derivitive of self::VALID_KEYS)
      // private static ${$this->validationVarByColumn(0)} = array(
      $col1 = $this->validationVarByColumn(0);
      if(!isset($this->$col1) || empty($this->$col1)) {
        $this->$col1 = array(
          "XXS" => "XX-Small",
          "XS"  => "X-Small",
          "S"   => "Small",
          "SM"  => "Small",
          "M"   => "Medium",
          "MD"  => "Medium",
          "L"   => "Large",
          "LG"  => "Large",
          "XL"  => "X-Large",
          "2XL" => "XX-Large",
          "2X"  => "XX-Large",
          "3XL" => "XXX-Large",
          "3X"  => "XXX-Large",
          "4XL" => "XXXX-Large",
          "4X"  => "XXXX-Large",
          "5XL" => "XXXXX-Large",
          "5X"  => "XXXXX-Large",
          "OS"  => "One-Size"
        );
      }
      
      // define column_2 valid values (key is valid color, value is hex, to be mutated, retruned from color script)
      // VALID_COLORS
      $col2 = $this->validationVarByColumn(1);
      if(!isset($this->$col2) || empty($this->$col2)) {
        // php array return value from file include
        $valid_colors_path = realpath(APP_ROOT."/assets/VALID_COLORS");
        if($valid_colors_path === false) throw new Exception("Unable to find VALID_COLORS definition", 1);
        $this->$col2 = include($valid_colors_path);
      }

      // define column_3 valid values this can be anything, so not sure yet... just needs to exist.
      // VALID_KINDS
      $col3 = $this->validationVarByColumn(2);
      if(!isset($this->$col3) || empty($this->$col3)) {
        $this->$col3 = array();
      }

      
      /**
       * set Autocorrect Arrays: AUTO_CORRECT_{COLUMN}S
       */

      // define column_1 auto-correct values (key being valid, value being mutation)
      // should be named AUTO_CORRECT_SIZES (default, but derivitive of self::VALID_KEYS)
      $col1 = $this->autocorrectVarByColumn(0);
      if(!isset($this->$col1) || empty($this->$col1)) {
        $this->$col1 = array(
          "Medoum" => "Medium",
          "Medum"  => "Medium",
          "Larger" => "Large",
          "Lage"   => "Large",
          "Default Title"
               => "_determine_"
        );
      }

      // define column_2 auto-correct values (key is misspelled color, value is corrected, to be mutated)
      // AUTO_CORRECT_COLORS
      $col2 = $this->autocorrectVarByColumn(1);
      if(!isset($this->$col2) || empty($this->$col2)) {
        $this->$col2 = array(
          "Grey"  => "Gray",
          "Multi" => "_determine_"
        );
      }

      // define column_3 auto-correct values, this can be anything, so not sure yet... just needs to exist.
      // AUTO_CORRECT_KINDS
      $col3 = $this->autocorrectVarByColumn(2);
      if(!isset($this->$col3) || empty($this->$col3)) {
        $this->$col3 = array();
      }
    }catch(Exception $e) {
      return error_log(var_export($e,true))&&false;
    }
    return true;
  }

  /**
   * Public Static Properties
   */
  public static $start_time; // set as getrusage() in static init;
  public static $died_num;
  public static $died_filter_code;
  public static $died_filter_group;

  /** 
   * Public Static Methods
   */
  
  public static function init_static() {
    self::$start_time         = getrusage();
    self::$died_num           = -1; // null to default
    self::$died_filter_code   = null; // null to default
    self::$died_filter_group  = null; // "preserveColumnValue";
  }
  
  public static function findFile($pattern, $flags = 0) {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::findFile($dir.'/'.basename($pattern), $flags));
        }
        return $files;
    }


  public static function diedump() {
    if(func_num_args()==0&&is_null(error_get_last())) return null;
    $dump_data = func_num_args()==0 ? (
      array(
        "error_message" => error_get_last(),
        "states_data"    => self::getInstance()->getLastState(
          !is_null(self::$died_num)           ? self::$died_num           : 10, 
          !is_null(self::$died_filter_code)   ? self::$died_filter_code   : null, 
          !is_null(self::$died_filter_group)  ? self::$died_filter_group  : null
        )
      )
    ) : (
      func_num_args()>1 ? (
        func_get_args()
      ) : (
        func_get_arg(0)
      )
    );
    $dump_val  = func_num_args() ? (
      array(($line = debug_backtrace()[0]["line"])=>$dump_data)
    ) : (
      array(($line = error_get_last()['line'])=>$dump_data)
    );
    ob_start();
      var_dump($dump_val);
    $dump = ob_get_clean();
    error_log("DieDumped (with ".func_num_args()." args) on line: ". $line);
    die("<pre>$dump</pre>")&&exit(1);
  }

  public static function array_extend(&$arr) {
    return ($arr = array_merge($arr, call_user_func_array("array_merge", array_slice(func_get_args(),1))));
  }

  public static function runtime($index = "stime") {
    $ru = getrusage();
    return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
        -  (self::$start_time["ru_$index.tv_sec"]*1000 + intval(self::$start_time["ru_$index.tv_usec"]/1000));
  }

  public static function array_copy($arr) {
    $newArray = array();
    foreach($arr as $key => $value) {
      if(is_array($value)) $newArray[$key] = self::array_copy($value);
      else if(is_object($value)) $newArray[$key] = clone $value;
      else $newArray[$key] = $value;
    }
    return $newArray;
  }

  public static function array_keys_multi(array $array, $max_depth = -1, $depth = 1) {
    $keys = array();
    foreach ($array as $key => $value) {
      $keys[] = $key;
      if ($depth!=$max_depth&&is_array($array[$key])) {
        $keys = array_merge($keys, self::array_keys_multi($array[$key],$max_depth,($depth+1)));
      }
    }
    return $keys;
  }


  /**
   * Public Instance Methods
   */

  public function connect() {
    /**
     * Initialize the MySQLi Connection
     */
    if(!($this->connected = ($this->db = new mysqli($this->host, $this->user, $this->pass, $this->db_name, $this->port)))) {
      return (($this->connected = null) && ($this->setState("init_connect","Unable to initialize MySQLi")));
    }
    $this->db->query("SET NAMES utf8");
    // @prune(1);
    /**
     * All good, return connected states (which should be true), add new validation...
     */
    return $this->connected;

  }

  public function query($query) {
    return $this->db->query($query);
  }

  public function debug() {
    return (isset($this->debug)&&boolval($this->debug));
  }

  public function getLastState($how_many = 1, $filter_code = null, $filter_group = null) {
    if(empty($this->states_data)) return null;
    $filtered_states_data = $this->states_data;
    array_walk($filtered_states_data, function(&$states_data, $states_num, $filters) {
      extract($filters);
      if(!is_null($filter_code)) {
        $filter_code = is_array($filter_code) ? $filter_code : array($filter_code);
        foreach($filter_code as $code) {
          if(strcasecmp($states_data['code'],$code)!==0) $states_data = null;
        }
      }
      if(!is_null($filter_group)) {
        $filter_group = is_array($filter_group) ? $filter_group : array($filter_group);
        foreach($filter_group as $group) {
          if(strcasecmp($states_data['group'],$group)!==0) $states_data = null;
        }
      }
    }, array(
      "filter_code"   => $filter_code,
      "filter_group" => $filter_group
    ));
    $filtered_states_data = array_filter($filtered_states_data);
    $ret_arr = array();
    foreach(array_reverse($filtered_states_data, true) as $states_num => $states_data) {
      extract($states_data);
      $ret_arr[$states_num] = $this->getState($code,$index,$group,false);
      if(count($ret_arr)==$how_many) break;
    }
    return $ret_arr;
  }

  public function getCSVData() {
    if(empty($this->csv_data)) {
      try {
        $this->getDataFromExport();
      } catch(Exception $e) {
        $this->setState("csv_data_unavailable_".$e->getCode(),"CSV Data Unavailable: ".$e->getMessage());
      }
      if(count($this->states) > 0) return $this->states;
    }
    return $this->csv_data;
  }

  public function writeCSVData() {
    return $this->setDataFromExport(null) or $this->states;
  }

  public function setupProductTables() {
    return array(
      "createExportTables" => $this->createExportTables(),
      "getDataFromExport"  => $this->getDataFromExport(),
      "setDataFromExport"  => $this->setDataFromExport()
    );
  }

  public function selectProductData($sku_code = null, $prefix = "products_") {
    if(is_null($sku_code)) return $this->getProductData($prefix);
    $product_data = $this->getProductData();
    return (isset($product_data[$sku_code]) ? $product_data[$sku_code] : false);
  }

  public function getProductDataBySkuFullSearch($sku) {
    $tables = $this->getAllTables("products_", false, false);
    $tablesArr = array_chunk($tables, 20);
    $sku_data = array();
    foreach($tablesArr as $tableset) {
      $select  = "SELECT * FROM ".implode(",", $tableset)." WHERE variant_sku = '$sku' LIMIT 1";
      $result = $this->query($select);
      if(!$result) continue;
      $row = $result->fetch_assoc();
      $sku_data[] = $row;
      $result->free_result();
    }
    return empty($sku_data) ? false : $sku_data;
  }

  public function getProductDataBySku($sku) {
    $sku_data = $this->getSkuValid($sku);
    $err_data = array(
      "sku"=>$sku,
      "sku_data"=>$sku_data
    );
    if(!$sku_data) return $this->setState("invalid_sku_error", "The SKU '$sku' passed to getProductDataBySku is Invalid", $err_data, null, "display_error") && false;
    try {
      list(/* $sku */, $vendor, $type, $id, $group, $size, $special) = $sku_data;
    } catch(Exception $e) {
      return $this->setState("sku_parts_error", "The SKU '$sku' passed to getProductDataBySku has Invalid Parts", $err_data, null, "display_error") && false;
    }
    $tbl_data = $this->selectProductData($type);
    if($tbl_data===false) return $this->getProductDataBySkuFullSearch($sku);
    $tbl_skus = null;
    if(is_array($tbl_data)) $tbl_skus = array_column($tbl_data,null,"variant_sku");
    if(!is_array($tbl_skus)||!array_key_exists($sku, $tbl_skus)) {
      return $this->setState("sku_row_not_found_error", "Table Data Could Not Be Found for SKU '$sku'", self::array_extend($err_data, array(
        "vendor"=>isset($vendor)?$vendor:null,
        "type"=>isset($type)?$type:null,
        "id"=>isset($id)?$id:null,
        "group"=>isset($group)?$group:null,
        "size"=>isset($size)?$size:null,
        "special"=>isset($special)?$special:null,
        "tbl_skus"=>$tbl_skus
      )), null, "display_error") && false;
    }
    return $tbl_skus[$sku];
  }

  public function getOptionKeyValues() {
    return array(
      "option_1" => $this->getOptionKeyValueByColumn(1),
      "option_2" => $this->getOptionKeyValueByColumn(2),
      "option_3" => $this->getOptionKeyValueByColumn(3)
    );
  }

  public function standardizeOptions($suffix = -1/*"tt"*/, $prefix = "products_", $mod_suffix = '_edited') {
    if(!is_string($suffix)) { // all tables
      $tables = $this->getAllTables($prefix, true, $mod_suffix, false);
      $fix = false;
      foreach($tables as $suffix) {
        $fix = $this->fixTableOptions($suffix, $prefix, $mod_suffix);
        if($fix===true) continue;
        if($this->isError($fix)) return $fix;
      }
      return $ret_val;
    } else {
      return $this->fixTableOptions($suffix, $prefix, $mod_suffix);
    }
    
  }

  public function switchKey(&$arr, $oldkey, $newkey, $column = null) {
    if(!array_key_exists($oldkey, $arr)&&!array_key_exists($newkey,$arr)) {
      return !($this->setState("key_not_found","Key '".$oldkey."' Not Available.",array("oldkey"=>$oldkey,"newkey"=>$newkey,"arr"=>$arr)));
    }
    if(array_key_exists($newkey, $arr)) {
      if(!is_null($column)&&strcasecmp($newkey,$this->VK($column))===0) return false;
      return !($this->setState("new_key_exists","Key ".$newkey." Already Exists; Data will be overwritten.", array(
        "oldkey" => $oldkey,
        "newkey" => $newkey,
        "arr"    => $arr
      )));
    }
    $arr[$newkey] = $arr[$oldkey];
    unset($arr[$oldkey]);
    return true; // true could be !!1 and false could be !!0, same chanacter count, I like it... just a thought.
  }

  public function nextVendorId($vendor) {
    $max_id  = "SELECT MAX(SUBSTRING(variant_sku,6,4)) as max_id FROM org_export WHERE variant_sku LIKE '$vendor%' AND CAST(SUBSTRING(variant_sku,6,4) as UNSIGNED)!=0";
    $result  = $this->query($max_id);
    if(!$result) return false;
    $result  = $result->fetch_assoc();
    $next_id = intval($result['max_id']) + 1;
    return $next_id;
  }

  /**
   * ShopifyStandard::arrayToListString()
   *
   * @todo: Make the quote options work, (remove quotes when respectively false)
   */
  public function arrayToListString($arr, $key_quotes = true, $val_quotes = true) {
    return str_replace(array('},{',':"','"'),array(', ',': "',"'"),trim(json_encode($arr),"{[]}"));
  }

  public function isError($retdata, $suffix = "_error") {
    return !empty(
      array_filter(
        array_map(function($key) {
          return stristr($key, "_error");
        }, array_keys($retdata))
      )
    );
  }

  public function doUpdateSkus($sku_data) {
    return $this->updateSkus($sku_data);
  }

  public function doUpdateColors($color_data) {
    return $this->updateColors($color_data);
  }

  public function getAllTables($prefix = 'products_', $just_suffix = false, $exclude_suffix = '_edited', $require_not_exclude = false) {
    $tablesq= "SHOW TABLES LIKE '$prefix%".(!!$require_not_exclude&&!empty($exclude_suffix)?"$exclude_suffix'":"'");
    $tbl_res= $this->query($tablesq);
    $tables = array();
    while($row = $tbl_res->fetch_assoc()) {
      $table = array_pop($row);
      if(!$require_not_exclude&&!!$exclude_suffix&&!!preg_match("/$exclude_suffix\$/",$table)) continue;
      array_push($tables,$table);
    }
    if($just_suffix) {
      array_walk($tables, function(&$table, $index, $prefix) {
        $table = substr($table,strlen($prefix));
      }, $prefix);
    }
    return $tables;
  }
// strip this part out to get all tables. run thru them in standardizeOptions function, and call each table for that, loop if no errors, error out after whatever...
  public function testSkuAvailable($new_sku, $old_sku, $prefix = 'products_') {
    $sku = $new_sku;
    $tables    = $this->getAllTables($prefix, false, false);
    array_unshift($tables, 'org_export');
    $tablesArr = array_chunk($tables, 20);
    $available = array();
    foreach($tablesArr as $tableset) {
      $count  = "SELECT COUNT(variant_sku) as count FROM ".implode(",", $tableset)." WHERE variant_sku LIKE '$sku'";
      $result = $this->query($count);
      if(!$result) continue; /** @todo: report this error */
      $row = $result->fetch_assoc();
      $available[] = intval($row['count']);
      $result->free_result();
    }
    return (array_sum($available)==0);
  }

  public function getValueValidTrue($column) {
    $VVarr = $this->VV($column);
    return array_filter(array_map(function($_key,$_value, $column) {
      return ($_key == array_search($_value, $this->VV($column)))?$_key:false;
    }, array_keys($VVarr), array_values($VVarr),array_fill(0, count($VVarr), $column)));
  }

  public function getSkuValidRegex() {
    $matches = array();
    $validskusizes = implode("|", array_filter(
      array_keys($this->VALID_SIZES),
      function($val){
        return strlen($val)==2;
      }
    ));
    return "/([A-Z]{3})([a-zA-Z0-9]{2})(\d{4})([A-Z])(".$validskusizes.")(.*)/";
  }

  public function getValueValidRegex($column, $strict = false) {
    $test_valid = ($strict) ? $this->getValueValidTrue($column) : array_keys($this->VV($column));
    //return "/^((".implode("|",$test_valid).")(,\s)?)+$/"; // allow comma-separated list for columns greater than 0
    return '/^(?:(?:'.implode("|", $test_valid).')'.(boolval($column)?'(?:,\s)?)+':')').'$/';
  }

  public function getSkuValid($sku) {
    $regex = $this->getSkuValidRegex();
    return (($tmp=preg_match($regex,$sku,$matches)) ? $matches : $tmp);
  }

  public function getValueValid($column, $value, $strict = false) {
    $regex = $this->getValueValidRegex($column, $strict);
    return (($tmp=preg_match($regex,$value,$matches)) ? $matches : $tmp);
  }

  public function isLastColumn($column) {
    if($column<0) return false;
    return ((array_key_exists($column,self::VALID_KEYS))&&(count(self::VALID_KEYS)-1 == $column));
  }

  public function getLastColumn() {
    return array_keys(self::VALID_KEYS)[(count(self::VALID_KEYS)-1)];
  }

  public function getColor($pro_args) {
    $det_res = $this->determineColor($pro_args['cur_val'], $pro_args);
    return array(
      "suggestion" => $det_res['suggestion'],
      "cached"     => (is_array($det_res)&&isset($det_res['cached'])&&(bool)($det_res['cached']))
    );
  }

  public function colorCache() {
    $cookie_data = isset($_COOKIE[self::COLOR_CACHE_COOKIE]) ? (array)unserialize($_COOKIE[self::COLOR_CACHE_COOKIE]) : array();
    switch(func_num_args()) {
      case 0:
        return $cookie_data;
      break;
      case 1:
        return isset($cookie_data[($arg=((string)func_get_arg(0)))]) ? $cookie_data[$arg] : false;
      break;
      case 2:
        $cookie_data[(string)func_get_arg(0)] = ($color=(string)func_get_arg(1));
        return (setcookie(self::COLOR_CACHE_COOKIE, serialize($cookie_data), time()+86400, '/', $_SERVER['HTTP_HOST'])) ? $color : false;
      break;
      default:
        return false;
      break;
    }
  }

  public function getColorFromHex($hex) {
    $color_map = $this->VV(array_search('Color', self::VALID_KEYS));
    $lastColor = "Black";
    foreach($color_map as $color=>$hexval) {
      $strcomp = strcasecmp($hex, $hexval);
      if($strcomp == 0) {
        return $color;
      } elseif($strcomp < 0) {
        return $lastColor;
      }
      $lastColor = $color;
    }
    return $lastColor;
  }


  /**
   * Private Methods
   */

  private function updateSkus($sku_data) {
    // should be an array with the old sku as the key, and the parts as the elements
    if(!is_array($sku_data)) {
      $this->setState($code="null_sku_data_error","No SKU Data Received by the Server",array("sku_data"=>$sku_data),null,"display_error");
      return array("display_error" => $this->getState($code,null,"display_error",false));
    }
    // do the actual updating
    $indexes = array_keys($sku_data);
    foreach($sku_data as $old_sku => $new_sku_pieces) {
      $index = array_search($old_sku,$indexes);
      $new_sku = implode("",$new_sku_pieces);
      if(!$this->testSkuAvailable($new_sku,$old_sku)) {
        $this->setState("sku_save_error","New SKU Already Taken!", array(
          "Old SKU" => $old_sku,
          "New SKU" => $new_sku
        ), $index, "display_error");
        continue;
      }
      if(!($tmp = $this->getSkuValid($new_sku))) {
        $this->setState("sku_save_warning","Invalid New SKU Value:", array(
          "Old SKU" => $old_sku,
          "New SKU" => $new_sku,
          "Cause"   => $tmp===flase ? "System Error" : "Invalid New SKU"
        ), $index, "display_warning");
        continue;
      }
      /**
       * @todo: figure out setting the prefix/mod_suffix in constructor, later
       */
      $prefix = "products_"; $mod_suffix = "_edited";
      $table  = $prefix.substr($old_sku,3,2).$mod_suffix;
      $update = "UPDATE $table SET variant_sku = '$new_sku' WHERE variant_sku = '$old_sku'";
      $result = $this->query($update);
      if($result!==false && $this->db->affected_rows===1) {
        $this->setState("sku_save_success","$old_sku Successfully Change to $new_sku", $index, array(), "display_success");
      } else {
        $this->setState("sku_save_error","SKU to Update: $old_sku => $new_sku", array(
          "Cause" => $this->db->error
        ), $index, "display_error");
      }
    }
    return array(
      "display_error"   => $this->getState("sku_save_error",   null, "display_error",   false),
      "display_warning" => $this->getState("sku_save_warning", null, "display_warning", false),
      "display_success" => $this->getState("sku_save_success", null, "display_success", false)
    );

  }

  private function updateColors($color_data) {
    if(!is_array($color_data)) {
      $this->setState($code="null_color_data_error","No Color Data Received by the Server", array("color_data"=>$color_data),null,"display_error");
      return array("display_error" => $this->getLastState(1,"null_color_data_error","display_error"));
    }
    $skus = array_keys($color_data);
    // should be class vars...
    $prefix = "products_"; $mod_suffix = "_edited";
    $column = array_search('Color', $this->VK());
    if($column===false) {
      $this->setState("indeterminate_column_error", "Unable to find a valid 'Color' column.",array("color_data"=>$color_data),null,"display_error");
      return $this->getLastState(-1,"indeterminate_column_error");
    }
    foreach($color_data as $sku => $color_pieces) {
      $index = array_search($sku, $skus);
      $color = implode(", ",$color_pieces); // if using multiple inputs, one input should be validated with comma-space separation of valid values already, so no affect
      //tests before writing
      if(!($tmp = $this->getValueValid($column,$color))) {
        $this->setState("color_update_error", "Invaid Color: '$color'", array("Sku"=>$sku,"Color"=>$color,"Details"=>($tmp===false?"System Error":"Invalid Color")),$index,"display_error");
      }
      $table  = $this->getTableBySku($sku, $prefix.substr($sku,3,2).$mod_suffix, $prefix, $mod_suffix);
      $update = "UPDATE $table SET option_".($column+1)."_value = '$color' WHERE variant_sku = '$sku'";
      $result = $this->db->query($update);
      if($result !== false && $this->db->affected_rows===1) {
        $this->setState("color_save_success", "Successfully Updated $sku Color to '$color'", array(), $index, "display_success");
      } else {
        $this->setState("color_save_error", "Error Updating $sku Color to '$color'", array(
          "Cause"   => $this->db->error,
          "Rows"    => $this->db->affected_rows,
          "Details" => var_export($result,true),
          "Update"  => $update
        ), $index, "display_error");
      }
    }
    return array_filter(array(
      "display_error"   => $this->getState("color_save_error",   null, "display_error",   false),
      "display_warning" => $this->getState("color_save_warning", null, "display_warning", false),
      "display_success" => $this->getState("color_save_success", null, "display_success", false)
    ));
  }

  private function loadOptions($suffix = "tt", $prefix = "products_", $mod_suffix = "_edited") {
    /** Method Goal @prune(2); */

    // define local error types
    $error_type = $this->processable_states;
    // array(
    //   "database_select_error",
    //   "sku_parse_error",
    //   "create_mod_table_error"
    // );
    $error_count = array_fill_keys($error_type, 0);


    $_org = $prefix.$suffix;
    $_tbl = $prefix.$suffix.$mod_suffix;
    // for now, ignore wholesale products, those will be handled differently, but we do not want to mess them up right now.
    if(!$this->query("CREATE TABLE IF NOT EXISTS $_tbl (PRIMARY KEY(variant_sku)) SELECT * FROM $_org WHERE handle NOT LIKE '%-wholesale'")) {
      return !($this->setState($error_type[2],"Failed Creating Mod Tables",array("table"=>$_tbl,"mod"=>$_mod)));
    }

    // start with just the tank tops table to limit the overwhelmingness
    $query = "SELECT * FROM $_tbl WHERE handle NOT LIKE '%-wholesale' ORDER BY handle, title DESC, variant_sku";
    if(!($$_tbl = $this->query($query))) {
      $this->setState($error_type[0],"Error Querying Data from Database",++$error_count[$error_type[0]], array($this->db->error));
      return $this->states;
    }

    $this->product_opts = array();
    $lastProSku  = "";
    $lastTitle   = "";
    $lastOpt1Key = "";
    $lastOpt2Key = "";
    $lastOpt3Key = "";
    $regex       = $this->getSkuValidRegex();
    while($row = $$_tbl->fetch_object()) {
      $matches = $this->getSkuValid($row->variant_sku);
      if(!$matches) {
        // guess based on sub-string for error data
        $varsku = $row->variant_sku;
        $vendor = substr($varsku, 0,3);
        $type   = substr($varsku, 3, 2);
        $id     = substr($varsku, 5, 4);
        $group  = substr($varsku, 9, 1);
        $size   = substr($varsku, 10, 2);
        $special= substr($varsku, 12);
        // concat product sku
        $prosku = $vendor . $type . $id;
        // Create options keys
        $opt1key = $prosku==$lastProSku&&empty($row->option_1_name) ? $lastOpt1Key : ($lastOpt1Key = $row->option_1_name);
        $opt2key = $prosku==$lastProSku&&empty($row->option_2_name) ? $lastOpt2Key : ($lastOpt2Key = $row->option_2_name);
        $opt3key = $prosku==$lastProSku&&empty($row->option_3_name) ? $lastOpt3Key : ($lastOpt3Key = $row->option_3_name);
        // push error, give data required to fix sku from interface
        $this->setState($error_type[1], "Unable to Parse SKU: ".$row->variant_sku, array(
          "varsku"  => $row->variant_sku,
          "regex"   => $regex,
          "matches" => $matches,
          "matched" => !!$matches,
          "vendor"  => $vendor,
          "type"    => $type,
          "id"      => empty($id) ? $this->nextVendorId($vendor) : $id,
          "idsugg"  =>((!is_numeric($id) || (intval($id)==0)) && ($idsugg = $this->nextVendorId($vendor))) ? (
                  sprintf("'%'.04d' &lt;next free vendor ID&gt;", $idsugg)
                ) : (
                  "'0001'"
                ),
          "group"   => $group,
          "size"    => $size,
          "special" => $special,
          "title"   => empty($row->title) ? $lastTitle: $row->title,
          "handle"  => $row->handle,
          "options" => array(
            array($opt1key => $row->option_1_value),
            array($opt2key => $row->option_2_value),
            array($opt3key => $row->option_3_value)
          )
        ), ++$error_count[$error_type[1]]);
        // update last product, now that we're done with it here
        if($lastProSku!=$prosku) $lastProSku = $prosku;
        if($lastTitle != $row->title) $lastTitle = $row->title;
        continue;
      }
      // SKU matched correct format, set appropriate local variables
      list($varsku,$vendor,$type,$id,$group,$size,$special) = $matches;
      $prosku = $vendor . $type . $id;
      // Create options arrays
      //  find appropriate key
      $opt1key = $prosku==$lastProSku&&empty($row->option_1_name) ? $lastOpt1Key : ($lastOpt1Key = $row->option_1_name);
      $opt2key = $prosku==$lastProSku&&empty($row->option_2_name) ? $lastOpt2Key : ($lastOpt2Key = $row->option_2_name);
      $opt3key = $prosku==$lastProSku&&empty($row->option_3_name) ? $lastOpt3Key : ($lastOpt3Key = $row->option_3_name);
      // update last product, now that we're done with it
      // $lastlastProSku = $lastProSku; // for debugging
      if($lastProSku!=$prosku) $lastProSku = $prosku;
      if($lastTitle != $row->title) $lastTitle = $row->title;
      // $this->product_opts[$prosku][$spec][$size] = array(
      $this->product_opts[$prosku][$group][$size][$special] = array(
        array($opt1key => $row->option_1_value),
        array($opt2key => $row->option_2_value),
        array($opt3key => $row->option_3_value)
      );
      /* debug: @prune(3) */
    }
    // check for any errors and (for now) dump the errors array. (future should redirect to an interface form for the frontend user to fix these)
    // now defers to user interface. returns one error set at a time.
    if(array_sum($error_count)>0) {
      foreach($error_type as $i=>$type_code) {
        if($error_count[$type_code] > 0) return array( $type_code => $this->getState($type_code) );
      }
    }
    // return data array if everything goes well
    return $this->product_opts;

  }

  private function fixTableOptions($suffix = "tt", $prefix = "products_", $mod_suffix = '_edited') {
    /** @prune(4); */

    $_tbl = $prefix.$suffix;
    // use double $ to denote data (might start this as a trend in the future)
    $$_tbl = $this->loadOptions($suffix, $prefix, $mod_suffix);
    // check for errors, and deal with them first if required
    if($this->isError($$_tbl)) return $$_tbl;


    // ended up using this format for return data and uses values to call mutation functions and set states
    $this->howToModify = array_keys($this->modifications[0]); // @prune(5);

    // No errors, loop thru data and call required methods to fix it.
    foreach($$_tbl as $pro_sku => &$variants) {
      // each product (will only need to write keys for first row... dumb, but that's how it is.)

      // loop over variants
      foreach($variants as $group => &$sizes) {
        //each set of sizes for a particular group (usually is only 1 of these, but this would be the 'U', 'M', 'W', 'K', etc...)

        // loop thru sizes and get array of specialization (like RTS, usually blank) (keyed with size 2-digit code)
        foreach($sizes as $size => &$specials) {
          // loop thru specials and get array of options
          foreach($specials as $special => &$options) {
            // stash original options
            $org_opts = $options;
            // options array should be [0...2] corresponding keys/value with db values: {option_[1...3]_name: option_[1...3]_value}
            $mod_opts = &$options;
            // concatenate variant sku
            $var_sku  = $pro_sku.$group.$size.$special;
            // prep modifications array for variant sku
            $this->modifications[$var_sku] = array_fill(0, (
              $opt_count = count($mod_opts)), $this->modifications[0]); // @prune(6);
            // get keys, use for instead of foreach because I want to control the pointer
            for($column = 0; $column < $opt_count; $column++) {
              $this->processOption($column, $org_opts, $mod_opts, array(
                "var_sku" => $var_sku,
                "pro_sku" => $pro_sku,
                "group"   => $group,
                "size"    => $size,
                "special" => $special
              ));
            }
            // Die on specific product (defined at top of class, if defined)
            if($this->debug && isset($this->debug_sku)) {
              if(!isset($this->hitit)) $this->hitit = false;
              if($this->hitit && $pro_sku!=$this->debug_sku) self::diedump(array(
                "Progress:"         =>  ($pro_cnt=array_search($pro_sku,array_keys($this->product_opts)))."/".count($this->product_opts) . 
                                        "(".count(array_diff(self::array_keys_multi(array_slice($this->product_opts,0,($pro_cnt+1)),3),self::array_keys_multi($this->product_opts,2))).
                                        "/".count(array_diff(self::array_keys_multi($this->product_opts,3),self::array_keys_multi($this->product_opts,2))).")",
                "Error Total:"      =>  array_sum($error_counts=array_combine(array_keys($this->states), array_map("count", array_values($this->states)))),
                "Error Groups:"     =>  $error_counts,
                "Error Codes:"      =>  array_diff(self::array_keys_multi($this->states, 2),array_keys($error_counts)),
                "Product Variants:" =>  $this->product_opts[$pro_sku],
                "Variant"           =>  $var_sku,
                "org_opts"          =>  $org_opts,
                "mod_opts"          =>  $mod_opts,
                "Error Data:"       =>  $this->states
                                        // self::getInstance()->getLastState(
                                        //   !is_null(self::$died_num)           ? self::$died_num           : 10, 
                                        //   !is_null(self::$died_filter_code)   ? self::$died_filter_code   : null, 
                                        //   !is_null(self::$died_filter_group)  ? self::$died_filter_group  : null
                                        // )
              ));
              if($pro_sku == $this->debug_sku) $this->hitit = true; // blow, if set but did not match, dump at end
              if(count($this->product_opts) - intval(array_search($pro_sku,array_keys($this->product_opts))) == 1) $this->hitit = true;
            }
            // @prune(7);
            if(count($this->states_data)>=$this->max_errors) break;
          } // end of specials loop, special key with opts array
          if(count($this->states_data)>=$this->max_errors) break;
        } // end of size loop, size key with specials arr)  
        if(count($this->states_data)>=$this->max_errors) break;
      } // end of per variant loop (group key with size arr)
      if(count($this->states_data)>=$this->max_errors) break;
    } //end of per product loop

    // if($_SERVER['REQUEST_METHOD'] === 'POST') {
      //   self::diedump(array(
      //     "where"     => "fixOptions after loop",
      //     "Error Total:"      =>  array_sum($error_counts=array_combine(array_keys($this->states), array_map("count", array_values($this->states)))),
      //     "Error Groups:"     =>  $error_counts,
      //     "Error Codes:"      =>  array_diff(self::array_keys_multi($this->states, 2),array_keys($error_counts)),
      //     "Error Data: "    => $this->states,
      //     "tableData"   => $$_tbl
      //   ));
      // }

    $updates = $this->updateManipulatedOptions($$_tbl, $suffix, $prefix, $mod_suffix);

    /** Need array of processable errors, which should return that state. if no defined ones are there, then push others (probably to be added, or ignored, we'll see) */

    if(count($this->states)>0) {
      foreach($this->states as $group=>$data) {
        foreach($data as $code => $error) {
          if(in_array($code, $this->processable_states)) return array(
            $code => $this->getState($code, null, $group, true),
            "automation_details" => array(
              "error"   => $this->getLastState(-1, "write_options_modifications_error"),
              "success" => $this->getLastState(-1, "write_options_modifications_success")
            )
          );
        }
      }
      foreach($this->states as $group=>$data) {
        foreach($data as $code => $error) {
          return array(
            $code => $this->getState($code, null, $group, true),
            "automation_details" => array(
              "error"   => $this->getLastState(-1, "write_options_modifications_error"),
              "success" => $this->getLastState(-1, "write_options_modifications_success")
            )
          );
        }
      }
    }
    return true;
  }

  /**
   * Move Option logic outside loop, to be called from other memebers.
   */
  private function processOption($column, $org_opts, &$mod_opts, $pro_args) {
    // if(count($this->states)>10 || self::runtime()>10000) error_log("Script Died After ".(self::runtime()/1000)." Seconds")&&self::diedump($this->states);
    // original option properties
    $org_opt  = &$org_opts[$column];
    $org_keys = array_keys($org_opt);
    $org_key  = array_pop($org_keys);
    $org_val  = &$org_opt[$org_key];
    // current (modified) option propterties
    $cur_opt  = &$mod_opts[$column];
    $cur_keys = array_keys($cur_opt);
    $cur_key  = array_pop($cur_keys);
    $cur_val  = &$cur_opt[$cur_key];
    $valueInvalid = false;
    $ret_val  = false;
    // extract product variables into current scope. feel like it's better than globals, and makes modification easier.
    $this->changed_key = false;
    extract($pro_args,EXTR_SKIP|EXTR_REFS);
    // Set process data
    $proc_data = array(
      "var_sku"  => $var_sku,
      "pro_sku"  => $pro_sku,
      "group"    => $group,
      "size"     => $size,
      "special"  => $special,
      "column"   => $column,
      "cur_key"  => $cur_key,
      "org_opts" => $org_opts,
      "mod_opts" => &$mod_opts
    );
    // check for valid value, skip if error (and add error states)
    if(($valueInvalid=$this->checkValueInvalid($column,$cur_val))===false) {
      $this->setState("invalid_column_error","Options Column ".($column+1)." Not Available.", $proc_data);
    }
    if($valueInvalid) { // 0 means valid, >0 means invalid, false is key error
      $mod_type = $this->howToModify[$valueInvalid];
      // apply modification or mutation
      $mod_method = $mod_type."ColumnValue";
      // for future determination of what was altered
      $modification =& $this->modifications[$var_sku][$column][$mod_type];
      // should return true if altered, false otherwise
      $modification = $this->{$mod_method}($column, $cur_val, $proc_data);
      //if($org_opts!==$mod_opts) ShopifyStandard::diedump($org_opts,$mod_opts);
      // update return value
      $ret_val = $modification || $ret_val; // @prune(8);
    }
    // check key for validity (was going to do this first, but might need old key for preservation above)
    if(!$this->checkKeyValid($column, $cur_key)) {
      $ret_val = $this->switchKey($cur_opt, $cur_key, $this->VK($column), $column) || $ret_val;
    }

    return $ret_val;
  }

  // to avoid a huge if, and make the code more readable and maintainable, define derivitive functions
  public function checkValueInvalid($column, $value) {
    if(null===($VVarr = $this->VV($column))) return false;
    if($this->getValueValid($column, $value, true)) return 0;
    elseif($this->getValueValid($column,$value,false)||array_search($value, $VVarr)!==false) return 1;
    else return 2;
  }

  private function checkKeyValid($column,$curkey) {
    if(!(array_key_exists($column, self::VALID_KEYS) && (null !== self::VALID_KEYS[$column])))
      return !($this->setState("invalid_column_error","Options Column ".($column+1)." Not Available.",array("column"=>$column,"key"=>$curkey)));
    if(empty($curkey) || is_null($curkey)) return false;
    if(strcasecmp($curkey, self::VALID_KEYS[$column])===0) return true;
    // self::diedump(func_get_args();
    return in_array($curkey, self::VALID_KEYS) ? -1 : 0;
    // check if keyed for different column
    return !($this->setState("key_determination_error","Unable to determine if key is valid.",array("column"=>$column,"key"=>$curkey)));
  }

  private function preserveColumnValue($column, &$value, &$args = array(), $dest_col = null) {
    // function to check for likeness in previously fixed data, and availability to stash safely (in last column)
    $dest_col = is_null($dest_col) ? $this->getLastColumn() : $dest_col;
    // error out if the destination column is greater than the last column
    if($dest_col > $this->getLastColumn()) return $this->setState("preservation_error","Unable to preserve data",array_merge(array(
      "column"   => intval($column),
      "value"    => "$value",
      "dest_col" => intval($dest_col)
    ),$args))&&false;//,null,"display_warning") && false;
    // extract args into current scope (by reference)
    if(!empty($args)) extract($args,EXTR_SKIP|EXTR_REFS);
    // get current value of destination column
    $dest_keys = array_keys($mod_opts[$dest_col]);
    $dest_key  = array_pop($dest_keys);
    $dest_val  = &$mod_opts[$dest_col][$dest_key];
    // check for empty value, if so, write and clear, return true
    if(empty($dest_val)) {
      $dest_val = $value;
      $value    = '';
      return true;
    }
    // value not empty, check for swap, if dest_val valid for this column, swap and return true
    if(!($invalid_next = $this->checkValueInvalid($column,$dest_val))) {
      $tmp      = $value;
      $value    = $dest_val;
      $dest_val = $tmp;
      return true;
    }
    // check if needs modded to be be valid value for current column
    // set states to check shit out... probably remove soon
    $tmp_mod_opts = self::array_copy($mod_opts);
    $tmp_mod_opts[$column][$cur_key] = $dest_val;
    $tmp_mod_opts[$dest_col][$dest_key] = $value;
    if($this->processOption($column,$org_opts,$tmp_mod_opts,$args)) {
      $tmp_key  = array_key_exists($cur_key,$tmp_mod_opts[$column]) ? $cur_key : $this->VK($column);
      $value    = $tmp_mod_opts[$column][$tmp_key];
      $dest_val = $tmp_mod_opts[$dest_col][$dest_key];
      return true;
    }
    return $this->preserveColumnValue($dest_col,$dest_val,$args, ($dest_col+1));
  }

  /**
   * Auto Correct Value, correct by reference, return value reflects change.
   */
  private function autocorrectColumnValue($column, &$value, &$args = array()) {
    $org_val = $value;
    // autocorrect for capitalization... @prune(9);
    
    // look for comma, and lack of space, if has comma, and matches this regex, needs space after comma
    if(strpos($value, ',')!==false) { // if comma and didn't pass before, must need space
      $tmp_val = preg_replace("/,([^\s])/",", $1",$value);
      if(!is_null($tmp_val)) {
        if(strcmp($value,$tmp_val)!==0) {
          return !!($value = $tmp_val);
        }
      }
    }

    // look in autocorrect array
    $ac_arr  = $this->AC($column);
    $keys    = array_keys($ac_arr);
    $pos     = array_search($value, $keys);
    if($pos === false) return false;
    $ac_val  = $ac_arr[$keys[$pos]];
    if(strcasecmp($ac_val, "_determine_")==0) return $this->determineColumnValue($column, $value, $args);
    $value   = $ac_val;
    return (strcmp($org_val, $value) !== 0);
  }

  private function determineColumnValue($column, &$value, &$args = array()) {
    // if last column, nothing to determine
    if($this->isLastColumn($column)) return !$this->setState;
    // extract args into scope
    extract($args,EXTR_SKIP|EXTR_REFS);
    $val_var = strtolower($this->VK($column));
    if(isset($$val_var)) { // double $ on purpose, looking for var passed in args with same name ($size for col1)
            // $size for initial column 1
      $sku_val  = @$$val_var; // the value parsed from the sku
      $val_arr  = $this->VV($column);
      $test_val = $val_arr[$sku_val];
      $value    = $test_val;
      // should take care of switching for key (proper value)
      return $this->modColumnValue($column, $value, $args);
    } // not passed in args, determine elsewehere -- look for determinite function
    elseif(method_exists($this, ($det_method = "determine".str_replace(" ","",ucwords($val_var))))) { 
      // ShopifyStandard::diedump($org_opts,$mod_opts);
      return !!$this->setState($val_var."_needs_determination_error","The ".ucwords($val_var)." '$value' is Not Valid", self::array_extend($args, array(
        "ajax_url" => "/ajax/determine/".$val_var,
        "cur_val"  => $value
      )));
      // takes too long to do on one request. Throw to view, and ajax it
      // return $this->{$det_method}($value, $args);
    } else {
      // cannot be determined, error out, return false... yatta yatta
      return !$this->setState("indeterminate_value_error","Unable to Determine Proper Value for ".ucfirst($val_var), array(
        "column" => $column,
        "value"  => $value,
        "args"   => $args
      ));
    }
  }

  public function getImageSrcFromSku($var_sku, $prefix = 'products_', $mod_suffix = '_edited') {
    list(/* $var_sku */, $vendor, $type, $id, $group, $size, $special) = $this->getSkuValid($var_sku);
    $pro_sku = $vendor.$type.$id;
    $_tbl    = $prefix.strtolower($type).$mod_suffix;
    $_tbl    = "org_export";
    $select  = "SELECT IF(variant_image='',(SELECT DISTINCT image_src FROM $_tbl WHERE variant_sku LIKE '$pro_sku%' AND image_src != '' LIMIT 1), variant_image) as variant_image FROM $_tbl WHERE variant_sku = '$var_sku' LIMIT 1";
    if(!($_tbl_res = $this->query($select)) || $_tbl_res->num_rows!=1) {
      $tables = $this->getAllTables("products_", false, false);
      $img_data = "";
      foreach($tables as $table) {
        $select = "SELECT IF(variant_image='',(SELECT DISTINCT image_src FROM $table WHERE variant_sku LIKE '$pro_sku%' AND image_src != '' LIMIT 1), variant_image) as variant_image FROM $table WHERE variant_sku = '$var_sku' LIMIT 1";
        $result = $this->query($select);
        if(!$result) continue;
        $row = $result->fetch_assoc();
        if(!(!!$row&&is_array($row)&&count($row)==1)) continue;
        $img_data = current($row);
        $result->free_result();
        break;
      }
      if(empty($img_data)) return ($this->setState("query_fail_error","MySQLi Error: ".$this->db->error, array("query"=>$select))&&false);
      $image_src = $img_data;
    } else {
      $$_tbl      = $_tbl_res->fetch_array();
      $image_src  = array_pop($$_tbl);
    }
    return (!empty($image_src = (strpos($image_src, '?') !== false ? stristr($image_src, '?', true) : $image_src)) ? $image_src : null);
  }

  /**
   * determineColor
   * 
   * @return bool true on modify, false otherwise
   **/
  private function determineColor(&$value, $pro_args, $suffix = 'tt', $prefix = "products_", $mod_suffix = "_edited") {
    $start_runtime = self::runtime();
    $org_val = $value;
    // extract pro_args into scope
    extract($pro_args,EXTR_SKIP|EXTR_REFS);
    if(!isset($this->colorx)) $this->colorx = new ColorExtractor;
    $image_src  = $this->getImageSrcFromSku($var_sku);
    // getting the remote images proves too long, use local cache (from other project, an API would be cool, but I'll find the image this way for now)
    $glob_path  = APP_ROOT."/assets/images/*/".basename($image_src);
    $local_imgs = self::findFile($glob_path);
    $local_img  = reset($local_imgs);
    $image_path = !is_null($local_img) ? $local_img : $image_src;
    $image_ext  = substr($image_path, strrpos($image_path, '.'));
    $image_obj  = null;
    $err_data = array(
      'var_sku'    => $var_sku,
      'image_src'  => $image_src,
      'local_imgs' => $local_imgs,
      'local_img'  => $local_img,
      'image_path' => $image_path,
      'image_ext'  => $image_ext,
      'cache'      => $this->colorCache()
    );
    // Cache color for image to reduce processing and unexpected variations on variants
    $color_cache = "";
    if(!($color_cache = $this->colorCache($image_path))) {
      switch($image_ext) {
        case ".png":
          $image = $this->colorx->loadPng($image_path);
        break;
        case ".jpg":
        case ".jpeg":
          $image = $this->colorx->loadJpeg($image_path);
        break;
        case ".gif":
          $image = $this->colorx->loadGif($image_path);
        break;
        default:
          return !$this->setState("image_extension_error","Image Extension Not Found", $err_data);
        break; // end switch image_ext
      }
      // Check for null image
      if(is_null($image)) {
        return !$this->setState("image_read_error","Unable to Read Image: ".basename($image_src), $err_data, null, "display_error");
      }

      // Extract most common hex color from image
      $palette = $image->extract(3);
      if(!(is_array($palette) && (count($palette)==3)))  {
        return !$this->setState("image_color_extract_error","Unable to Color from Image: ".basename($image_src), self::array_extend($err_data, array(
          'palette' => $palette
        )));
      }
      // pull most prominent (or only at this time) HEX color value off palette
      $hex      = array_map('strtoupper', $palette);
      $filtered = preg_grep('/^#[A-Z0-9]+$/', $hex);
      if(count($filtered)!==count($hex)) {
        self::array_extend($err_data, array(
          'hex'      => $hex,
          'filtered' => $filtered,
          'regex'    => $this->getValueValidRegex(1, true)
        ));
        return !$this->setState("invalid_hex_error","Invalid Hex Color Code: $hex", $err_data);
      }
      // Get Human Friendly Color Name from HEX
      $color   = implode(", ", array_map(array(ShopifyStandard::getInstance(), 'getColorFromHex'), $hex));
      // Validate Colors
      if(!($valid = $this->getValueValid(array_search("Color", self::VALID_KEYS), $color)))  {
        return !$this->setState("invalid_color_error","Invalid Color Value: $color", self::array_extend($err_data, array(
          'color'   => $color,
          'valid'   => $valid
        )));
      }
      // cache color
      $value = $this->colorCache($image_path, $color);
      // self::diedump($start_runtime, self::runtime(), debug_backtrace());
      // update value with color from cache, and compare to original value to return boolean mutation value
    } else {
      $value = $color_cache;
    }
    return (($value!==$org_val) ? array("cached"=>!!$color_cache,"suggestion"=>"$value") : false);
  }

  private function determineKind(&$value, $var_sku, $suffix = 'tt', $prefix = 'products_', $mod_suffix = "_edited") {
    // Kind can be anything, and shouldn't make it here really, but if so, return true
    return true;
  }

  /**
   * Column Mutator functions
   */

  private function mutateColumnValue($column, &$value, &$args = array()) {
    // @prune(10);

    // if last column -- which, for now, should not need mutated
    if($this->isLastColumn($column)) return false;
    // stash original value, in case
    $org_val = $value;
    // if empty determine from sku / image
    if(empty($value)) return $this->determineColumnValue($column, $value, $args);
    // extract args: @prune(11);
    if(!empty($args)) extract($args, EXTR_SKIP|EXTR_REFS);

    // check if can be auto-corrected (simple for now, to be extended)
    if($this->autocorrectColumnValue($column,$value,$args)) return true;
    // @prune(12);
    // check if value is valid for next column
    if(!$this->isLastColumn($column) && !($next_val_invalid = $this->checkValueInvalid(($next_column = ($column+1)), $value))) {
      // should not return false; error states
      if($next_val_invalid===false) {
        return $this->setState("next_val_invalid_index_error", "Invalid Column '$next_column' When Checking Next Column",array_merge(array(
          "next_val_invalid" => $next_val_invalid,
          "next_column"      => $next_column
        ), $args)) && false;
      }
      // valid next column value
      // @prune(13);
      return ($this->preserveColumnValue($column,$value,$args,$next_column)) ? (
        $this->processOption($column,$org_opts,$mod_opts,$args)
      ) : false;
    }

    // @prune(14);
    
    // try to determine if valid value can be parsed from value (stash rest... or, if not, try stashing all
    // try parsing original value for typical parts for preservation
    if($parsed = $this->parseColumnValue($column, $value, $args)) {
      // do something about it matching
      $matches = array_column($parsed, 0);
      $extras  = array_diff($this->getValueWords($value), $matches);

      // preserve exta values
      $this->preserveColumnValue($column, $value, $args);
      // update value & re-process
      $value = implode(", ", $matches);

      // return whether actually modified (although it should be)
      return (strcasecmp($value,$org_val)==0); 
    } else { // @prune(15)
      return ($this->preserveColumnValue($column,$value,$args)) ? (
        $this->processOption($column,$org_opts,$mod_opts,$args)
      ) : false;
    }

    // should now be unreachable
      if(isset($this->skip_some)&&$this->skip_some==2) {
        self::diedump(array(
          "where"   => "end of mutateColumnValue",
          "column"  => $column,
          "org_val" => $org_val,
          "value"   => $value,
          "parsed"  => $parsed,
          "matches" => $matches,
          "extras"  => $extras,
          "1"       => "======================= args ==========================",
          "args"    => $args,
          "2"       => "======================= errors ==========================",
          "errors"  => $this->states
        ));
      } else {
        $this->skip_some = isset($this->skip_some) ? ++$this->skip_some : 1;
      }
    return (strcasecmp($value,$org_val)==0);
  }

  private function parseColumnValue($column, $value, $args = array()) {
    extract($args,EXTR_SKIP|EXTR_REFS);
    $test_params = array(); // array of test cases
    $results = array(); // array with key of word position, value of matches
    // check variable value for column based off valid key
    // fill test params based on this, if guess available, use that, if not, check all valid values
    if(isset(${($val_var = strtolower($this->VK($column)))})) { // means passed in args
      // test value can be set from SKU, add that to test params
      $test_params[] = $sku_val = $$val_var; // the value from the sku, exported from pro_args, $size in case 0
      $valid_values  = $this->VV($column);
      $test_params[] = $test_val = $valid_values[$sku_val];
      // check for proper value, if different than 2 digit sku code
      if(!in_array($prop_val = array_search($test_val, $valid_values), $test_params)) $test_params[] = $prop_val;
    } else {
      $test_params = array_keys($this->VV($column));
    }
    // check by word, split by space
    $val_words   = $this->getValueWords($value);
    // return original value if has no spaces, as the other validation would have picked up single words
    if(count($val_words)===1) return false; // pop off array to test later, error if different?.. nah, just return false
    /** * @todo: improve checking regex */
    // build loose regex, could be improved
    $regex   = "/(".implode("|", $test_params).")/";
    // loop through each word to check for matches
    foreach($val_words as $word_pos => $word) {
      $matches = array();
      $matched = preg_match($regex, $word, $matches);
      if($matched) {
        $results[$word_pos] = $matches;
      }
    }
    // @prune(17)
    return !empty($results) ? $results : false;
  }

  public function getValueWords($value) {
    return preg_split("/[\s,]+/", $value);
  }

  private function modColumnValue($column, &$opt_val, &$args = array()) {
    $VVarr = $this->VV($column);
    // look for invalid short code
    if(array_key_exists($opt_val,$VVarr)) {
      // needs to be switched for valid short code below
      $value = $VVarr[$value];
    }
    // change size word to 1-3 letter valid google code
    $key_val = array_search($opt_val,$VVarr);
    if($key_val!==false) {
      $opt_val = $key_val;
      return true;
    }
    return false;
  }

  private function getTableBySku($sku, $guess = null, $prefix = "products_", $mod_suffix = null) {
    $table   = false;
    $tables  = $this->getAllTables($prefix, false, is_null($mod_suffix)?false:$mod_suffix, !is_null($mod_suffix));
    if(is_string($guess)) {
      array_unshift($tables, $guess);
    }
    foreach($tables as $_table) {
        $result = $this->query("SELECT * FROM $_table WHERE variant_sku = '$sku' LIMIT 1");
      if(!!$result && isset($result->num_rows) && $result->num_rows>=0) {
        $table = $_table;
        break;
      }
    }
    return $table;
  }

  private function updateManipulatedOptions($product_opts, $suffix = "tt", $prefix = "products_", $mod_suffix = "_edited") {
    // array_column for 'valid', and array diff the skus to get only non-valid options
    // update those.
    $modifications = array_slice($this->modifications,1);
    array_walk($modifications, function(&$opts_mod_arr, $sku) {
      foreach($opts_mod_arr as $column => &$mods) {
        $mods = !!array_search(true, $mods, true); // false or 0 unmodified;
      }
      if(count(array_filter($opts_mod_arr))==0) $opts_mod_arr = false;
      return $opts_mod_arr;
      
    });
    $to_update = array_filter($modifications);
    $updates   = array();
    $sku_valid = false;
    foreach($to_update as $sku => $columns) {
      $sku_valid = $this->getSkuValid($sku);
      if(!$sku_valid) continue;
      list($var_sku,$vendor,$type,$id,$group,$size,$special) = $sku_valid;
      $pro_sku = $vendor.$type.$id;
      if(!isset($product_opts[$pro_sku])) continue;
      $options = $product_opts[$pro_sku][$group][$size][$special];
      $table   = $this->getTableBySku($sku, $prefix.$type.$mod_suffix, $prefix, $mod_suffix);
      // ShopifyStandard::diedump(compact("sku","var_sku","vendor","type","id","group","size","special","columns","options","table"));
      $col_ups = array();
      foreach($options as $column => $col_data) {
        if(!$columns[$column]) continue;
        $col_ups[] = "option_".($column+1)."_name  = '" . key($col_data) . "'";
        $col_ups[] = "option_".($column+1)."_value = '" . current($col_data). "'";
      }
      $update = "UPDATE $table SET ". implode(', ', $col_ups) . " WHERE variant_sku = '$sku'";
      $data   = false;
      $result = $this->query($update);
      if($result!==false) {
        $code    = "write_options_modifications_success";
        $message = "Successfully Saved Modification for '$sku': ".(count($col_ups)/2)." Columns Modified.";
        $updates[$sku] = $data = $result;
      } else {
        $code    = "write_options_modifications_error";
        $message = "Error writing modications for '$sku': ".(count($col_ups)/2)." Columns to Modify.";
        $updates[$sku] = $data = array("error"=>$this->db->error,"result"=>$result);
      }
      $this->setState($code, $message, compact(explode("|","sku_valid|var_sku|pro_sku|options|table|col_ups|update|data")));
    }
    return $updates;
  }

  /**
   * ///////////////////////////// End Mutotr Functions /////////////////////////////
   */

  private function getCSVHandle($filepath = null, $mode = "r") {
    if(is_null($this->csv_handle)) {
      $this->setCSVHandle($filepath, $mode);
    }
    return $this->csv_handle;
  }

  private function setCSVHandle($filepath = null, $mode = "r") {
    if(isset($filepath)) {
      $this->csv_path = realpath($filepath) ? $filepath : $this->csv_path;
    }
    $this->csv_handle = fopen(realpath($this->csv_path), $mode);
    return ($this->csv_handle !== false);
  }

  private function getDataFromExport($filename = null) {
    if($this->getCSVHandle($filename) === false) {
      $this->setState("csv_file_handle","CSV File Handle Unavailable");
      throw new Exception("CSV File Handle Unavailable", 1);
    }
    $this->csv_data = array();
    $row = 1;
    $sql_cols = array_keys(ShopifyStandard::COL_MAP);
    $csv_cols = array_values(ShopifyStandard::COL_MAP);
    while(($data = fgetcsv($this->csv_handle)) !== false) {
      // Do not add column headers to the data array (this should be when row is 1)
      if($data==$csv_cols) { $row++; continue; }
      // get column count (for loop, should be 44)
      $cols = count($data);
      $tempdata = ShopifyStandard::COL_MAP;
      for($col=0; $col < $cols; $col++) {
        $tempdata[$sql_cols[$col]] = $data[$col];
      }
      // push the tempdata to the class csv data array
      $this->csv_data[$row] = $tempdata;
      // increment the row
      $row++;
    }
  }

  private function getProductData($prefix = 'products_') {
    if(empty($this->product_data)) {
      // remove csv data if loaded, cause that's a TON of data with both
      if(!empty($this->csv_data)) unset($this->csv_data);
      // Fill each sku type data into array with key of sku code
      foreach($this->getProductTypeCodes() as $sku_code) {
        $results = $this->query("SELECT * FROM $prefix$sku_code");
        if(!$results) continue;
        for($rownum = 1; $rownum <= $results->num_rows; $rownum++) {
          foreach($results->fetch_assoc() as $column=>$row) {
            $this->product_data[$sku_code][$rownum][$column] = $row;

          }
        }
      }
    }
    return $this->product_data;
  }

  private function getProductTypes() {
    if(empty($this->product_types)) {
      // @prune(17);
      $query = "SELECT sku_code as type_code, google_shopping_google_product_category as type_desc FROM sku_standard";
      $types = array();
      if($results = $this->query($query)) {
        while($row = $results->fetch_assoc()) {
          if(strlen($row['type_code'])!=2) continue;
          $types[$row['type_code']] = $row['type_desc'];
        }
        $results->close();
      }
      $this->product_types = $types;
    }
    return $this->product_types;
  }

  private function getProductTypeCodes() {
    return array_keys($this->getProductTypes());
  }

  private function createExportTables($prefix = "products_") {
    $types = $this->getProductTypeCodes();
    foreach($types as $type) {
      $this->query("CREATE TABLE IF NOT EXISTS " . $prefix.$type . " LIKE org_export");
    }

  }

  private function setDataFromExport($prefix = 'products_', $table = 'org_export') {
    foreach($this->csv_data as $row=>$data) {
      //Build a query string for each row, determine table from sku
      $query = array();
      foreach($data as $col => $val) {
        if(!is_null($prefix) && $col == "variant_sku") $table = $prefix.substr($data['variant_sku'],3,2);
        $query[] = $col . " = '" . $this->db->real_escape_string($val) . "',";
      }
      $query = "REPLACE INTO {$table} SET ".str_replace(array("&nbsp;","Â"), array(" ",""),trim(implode(" ", $query),","));
      if(!$this->db->query($query)) {
        $this->setState("query_fail","MySQLi Error: ".$this->db->error, array("query"=>$query));
      }
    }
    return (count($this->states)==0);
  }

  private function getOptionKeyValueByColumn($column = 1) {
    $option = array();
    $query  = "SELECT option_{$column}_name as optkey, option_{$column}_value as optval FROM org_export";
    if($result = $this->query($query)) {
      while($row = $result->fetch_object()) {
        if(!isset($option[$row->optkey][$row->optval])) {
          $option[$row->optkey][$row->optval] = 1;
        } else {
          $option[$row->optkey][$row->optval]++;
        }
      }
    }
    foreach($option as $optkey=>$optvals) {
      foreach($optvals as $valkey => $val) {
        sort($option[$optKey][$valkey]);
      }
      ksort($option[$optkey]);
    }
    ksort($option);
    return $option;
  }

  private function newImportGoogleCategoryFix($prefix = 'products_') {

  }

  private function newImportDescriptionFix($prefix = 'products_') {
    $query = "UPDATE $table SET body_html = title WHERE body_html = '' AND title != ''";
    $this->db->query($query);
  }

  protected function setState($code = "shopify_standard_error", $message = "Error in ShopifyStandard", $data = null, $count = null, $group = null) {
    $group = is_null($group)? debug_backtrace()[1]['function'] : $group;
    $data   = is_null($data)  ? debug_backtrace() : self::array_copy($data);
    $count  = is_null($count) ? (
      (isset($this->states[$group])) ? (
        (isset($this->states[$group][$code])) ? (
          count($this->states[$group][$code]) ) : ( 0 ) ) : ( 0 ) ) : ( is_null($count) ? 0 : $count );
    try {
      if(!isset($this->states[$group])) $this->states[$group] = array();
      if(!isset($this->states[$group][$code])) $this->states[$group][$code] = array();
      $this->states[$group][$code][$count] = array(
        "message" => $message,
        "data"    => $data
      );
      $this->states_data[] = array(
        "code"   => $code,
        "index"  => $count,
        "group" => $group
      );
    } catch (Exception $e) {
      self::diedump($code, $message, $group, $count, $data, $e);
    }
    return $count;
  }

  protected function getState($code = "shopify_standard_error", $index = null, $group = null, $just_data = true) {
    $group = is_null($group) ? debug_backtrace()[1]['function'] : $group;
    if(!isset($this->states[$group])) {
      $this->setState("get_null_group_error", "Error group '$group' not defined",func_get_args());
      return false;
    }
    if(!isset($this->states[$group][$code]) || is_null($this->states[$group][$code])) {
      $this->setState("get_null_code_error", "Error code '$code' in '$group' not defined", array("getError_error"=>array(
        "group" => $group,
        "code"   => $code,
        "index"  => $index
      )));
      return false;
    }

    return is_null($index) ? (
      !!$just_data ? (
        array_map(function($arr) {
          return $arr['data'];
        }, $this->states[$group][$code])
      ) : (
        $this->states[$group][$code]
      )
    ) : ( 
      array_key_exists($index,$this->states[$group][$code]) ? (
        !!$just_data ? (
          $this->states[$group][$code][$index]['data']
        ) : (
          $this->states[$group][$code][$index]
        )
      ) : (
        -1
      )
    );
  }

  /**
   * Class Destruct Method
   * 
   * Destroy things that need it after last use of class
   */

  public function __destruct() {
    if(!!$this->csv_handle) fclose($this->csv_handle);
    unset($this->csv_data);
    if(!is_null($this->db)) $this->db->close();
    // Segment the error log
    error_log("////////////////////////////////// END RUN (".(self::runtime()/1000)."s) ShopifyStandard CLASS ///////////////////////////////");
  }
}