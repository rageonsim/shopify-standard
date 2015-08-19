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
		if(!isset(self::$_instance) || is_null(self::$_instance) || (self::$_instance instanceof ShopifyStandard)) {
			self::$_instance = New ShopifyStandard($in_path,$out_path,$gen_csv);
		}
		// if(!empty(self::$_instance->errors)) return self::$_instance->errors;
		return self::$_instance;
	}

	/**
	 * Private Class Variables
	 */

	private $debug      = true; // should be a boolean representing whether to display certain debug info. false for production. (duh).

	private $user 		= 'root';
	private $pass 		= 'root';
	private $db_name	= 'shopify_standard';
	private $host		= 'localhost';
	private $port		= 3306;
	private $db 		= null;
	private $csv_path	= '../assets/products_export_08-04-2015.csv';
	private $csv_cols	= array();
	private $csv_data	= array();
	private $csv_handle = null;
	private $errors     = array();
	private $colorx 	= null;
	private $product_types = array();
	/** product data in array with keys being the type_code */
	private $product_data  = array();
	private $modifications = array( 0 => array(// keeps track of value modification
		"valid" => false,	// current size is valid, move on to checking key
		"mod"	=> false,	// current size needs to be changed to 2 letter code
		"mutate"=> false	// current size needs modified or is not size, and should be preserved or checked if color
	));


	/**
	 * Protected Constructor and Clone (protect for singleton)
	 */
	protected function __clone() {} // unecessary anyway
	protected function __construct($in_path,$out_path,$gen_csv=true) {
		if($this->debug) error_log("////////////////////////////////// START RUN (".microtime().") ShopifyStandard CLASS //////////////////////////////");
		$this->in_path  = is_null($in_path) ? $this->csv_path : $in_path;
		$this->out_path = is_null($out_path)? $this->csv_path."_edited_".time().".csv" : $out_path;
		$this->gen_csv  = (bool)$gen_csv;
		
		return $this->connect() && $this->setValidation();
	}

	/**
	 * Public Class Variables
	 */

	public $connected = false;

	/**
	 * Class Constant Variables
	 */

	const COL_MAP = array(
		"handle"									=> "Handle",
		"title"										=> "Title",
		"body_html"									=> "Body (HTML)",
		"vendor"									=> "Vendor",
		"type"										=> "Type",
		"tags"										=> "Tags",
		"published"									=> "Published",
		"option_1_name"								=> "Option1 Name",
		"option_1_value"							=> "Option1 Value",
		"option_2_name"								=> "Option2 Name",
		"option_2_value"							=> "Option2 Value",
		"option_3_name"								=> "Option3 Name",
		"option_3_value"							=> "Option3 Value",
		"variant_sku"								=> "Variant SKU",
		"variant_grams"								=> "Variant Grams",
		"variant_inventory_tracker"					=> "Variant Inventory Tracker",
		"variant_inventory_qty"						=> "Variant Inventory Qty",
		"variant_inventory_policy"					=> "Variant Inventory Policy",
		"variant_fulfillment_service"				=> "Variant Fulfillment Service",
		"variant_price"								=> "Variant Price",
		"variant_compare_at_price"					=> "Variant Compare At Price",
		"variant_requires_shipping"					=> "Variant Requires Shipping",
		"variant_taxable"							=> "Variant Taxable",
		"variant_barcode"							=> "Variant Barcode",
		"image_src"									=> "Image Src",
		"image_alt_text"							=> "Image Alt Text",
		"gift_card"									=> "Gift Card",
		"google_shopping_mpn"						=> "Google Shopping / MPN",
		"google_shopping_gender"					=> "Google Shopping / Gender",
		"google_shopping_age_group"					=> "Google Shopping / Age Group",
		"google_shopping_google_product_category"	=> "Google Shopping / Google Product Category",
		"seo_title"									=> "SEO Title",
		"seo_description"							=> "SEO Description",
		"google_shopping_adwords_grouping"			=> "Google Shopping / AdWords Grouping",
		"google_shopping_adwords_labels"			=> "Google Shopping / AdWords Labels",
		"google_shopping_condition"					=> "Google Shopping / Condition",
		"google_shopping_custom_product"			=> "Google Shopping / Custom Product",
		"google_shopping_custom_label_0"			=> "Google Shopping / Custom Label 0",
		"google_shopping_custom_label_1"			=> "Google Shopping / Custom Label 1",
		"google_shopping_custom_label_2"			=> "Google Shopping / Custom Label 2",
		"google_shopping_custom_label_3"			=> "Google Shopping / Custom Label 3",
		"google_shopping_custom_label_4"			=> "Google Shopping / Custom Label 4",
		"variant_image"								=> "Variant Image",
		"variant_weight_unit"						=> "Variant Weight Unit"
	);
	
	// valid keys for the column of index+1, verbose for readibility
	const VALID_KEYS = array(
		0	=> "Size",
		1	=> "Color",
		2	=> "Kind"
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
	private function VV($column=null) { return $this->{$this->validationVarByColumn($column)}; }
	private function VK($index=null) { return self::VALID_KEYS[$index]; }
	private function AC($column=null) { return $this->{$this->autocorrectVarByColumn($column)}; }

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
					"XXS"	=> "XX-Small",
					"XS"	=> "X-Small",
					"S"		=> "Small",
					"SM"	=> "Small",
					"M"		=> "Medium",
					"MD"	=> "Medium",
					"L"		=> "Large",
					"LG"	=> "Large",
					"XL"	=> "X-Large",
					"2XL"	=> "XX-Large",
					"2X"	=> "XX-Large",
					"3XL"	=> "XXX-Large",
					"3X"	=> "XXX-Large",
					"4XL"	=> "XXXX-Large",
					"4X"	=> "XXXX-Large",
					"5XL"	=> "XXXXX-Large",
					"5X"	=> "XXXXX-Large",
					"OS"    => "One-Size"
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
					"Medoum" => "Medium"
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
	 * Public Methods
	 */

	public function connect() {
		/**
		 * Initialize the MySQLi Connection
		 */
		if(!($this->connected = ($this->db = new mysqli($this->host, $this->user, $this->pass, $this->db_name, $this->port)))) {
			return (($this->connected = null) && ($this->setState("init_connect","Unable to initialize MySQLi")));
		}
		$this->db->query("SET NAMES utf8");
		/* older db connection style
			if(!$this->db->options(MYSQLI_INIT_COMMAND, 'SET AUTOCOMMIT = 0'))
				return (($this->connected = null) && ($this->errors = array("db"=>array("error"=>array("code"=>"set_autocommit","message"=>"Unable to set autocommit for MySQLi")))));
			if(!$this->db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5))
				return (($this->connected = null) && ($this->errors = array("db"=>array("error"=>array("code"=>"set_connection_timeout","message"=>"Unable to set timeout for MySQLi")))));
			// / **
			// * Make actual Connection
			// * /
			if(!($this->connected = $this->db->real_connect($this->host, $this->user, $this->pass, $this->db_name, $this->port)))
				return (($this->connected = null) && ($this->errors = array("db"=>array("error"=>array("code"=>"real_connect::"+mysqli_connect_errno(),"message"=>mysqli_connect_error())))));*/
		/**
		 * All good, return connected state (which should be true), add new validation...
		 */
		return $this->connected;

	}

	public function query($query) {
		return $this->db->query($query);
	}

	public function getCSVData() {
		if(empty($this->csv_data)) {
			try {
				$this->getDataFromExport();
			} catch(Exception $e) {
				$this->setState("csv_data_unavailable_".$e->getCode(),"CSV Data Unavailable: ".$e->getMessage());
			}
			if(count($this->errors) > 0) return $this->errors;
		}
		return $this->csv_data;
	}

	public function writeCSVData() {
		return $this->setDataFromExport(null) or $this->errors;
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
		return @$this->getProductData()[$sku_code];
	}

	public function getOptionKeyValues() {
		return array(
			"option_1" => $this->getOptionKeyValueByColumn(1),
			"option_2" => $this->getOptionKeyValueByColumn(2),
			"option_3" => $this->getOptionKeyValueByColumn(3)
		);
	}

	public function standardizeOptions($suffix = "tt", $prefix = "products_", $mod_suffix = '_edited') {
		return $this->loadOptions($suffix, $prefix, $mod_suffix);
	}

	public function switchKey(&$arr, $oldkey, $newkey) {
		if(!array_key_exists($oldkey, $arr)) {
			return !($this->setState("key_not_found","Key ".$oldkey." Not Available.",array("oldkey"=>$oldkey,"newkey"=>$newkey,"arr"=>$arr)));
		}
		if(array_key_exists($newkey, $arr)) {
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

	public function diedump() {
		die(var_dump(func_get_args()))&&exit(1);
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

	public function doFixOptions() {
		return $this->fixOptions();
	}

	public function doUpdateSkus($sku_data) {
		return $this->updateSkus($sku_data);
	}

	public function testSkuAvailable($new_sku, $old_sku, $prefix = 'products_') {
		$sku = $new_sku;
		$tablesq= "SHOW TABLES LIKE '$prefix%'";
		$tbl_res= $this->query($tablesq);
		$tables = array();
		while($row = $tbl_res->fetch_assoc()) {
			array_push($tables,array_pop($row));
		}
		array_unshift($tables, 'org_export');
		$tablesArr = array_chunk($tables, 20);
		$available = array();
		foreach($tablesArr as $tableset) {
			$count  = "SELECT COUNT(variant_sku) as count FROM ".implode(",", $tableset)." WHERE variant_sku LIKE '$sku'";
			$result = $this->query($count);
			if(!$result) continue; /** @todo: report this error */
			$result = $result->fetch_assoc();
			$available[] = intval($result['count']);
			$result->free_result();
		}
		return (array_sum($available)==0);
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

	public function getSkuValid($sku) {
		$regex = $this->getSkuValidRegex();
		return (($tmp=preg_match($regex,$sku,$matches)) ? $matches : $tmp);
	}

	public function isLastColumn($column) {
		if($column<0) return false;
		return ((array_key_exists($column,self::VALID_KEYS))&&(count(self::VALID_KEYS)-1 == $column));
	}

	public function getLastColumn() {
		return end(array_keys(self::VALID_KEYS));
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
			 * @todo: figure out setting the prefix/mod_suffix in constructor later
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

	private function loadOptions($suffix = "tt", $prefix = "products_", $mod_suffix = "_edited") {
		/** Method Goal 
		 * load the current single products SKU (strip off trailing defining options (i'm thinking that includes the group one... 
		 * the group part can be checked if a 'kind' needs it, but i think this is mainly irrelivent, or just should support the group 'kind')
		 * those should be the keys. load the variant full skus (of the main product sku) as an array oF keys,
		 * they should be keys for the options
		 * so each variant will contain an array of key values of the 3 options as they are currently
		 * Example:
		 * array(
		 * 		//SKU Examples: ABCTT1234UXS: ABC vendor - tank top - item 1234 - Unisex - XS ExtraSmall (this should be a key in the acceptable sizes)
		 * 		//				ABCTT1234US: ABC vendor - tank top - item 1234 - Unisex - S Small (this should be a key in the acceptable sizes)
		 * 		//				ABCTT1234UM: ABC vendor - tank top - item 1234 - Unisex - M Medium (this should be a key in the acceptable sizes)
		 * 		"XXXTT0000" => array(
		 * 			"U" => array(
		 * 				"XS" => array(
		 * 					"option_1_name" => "option_1_value", // but the actual values, not the column name
		 * 					"option_2_name" => "option_2_value", // but the actual values, not the column name
		 * 					"option_3_name" => "option_3_value", // but the actual values, not the column name
		 * 				),
		 * 				"S" => array(...), // just like above,
		 * 				"M" => array(...)
		 * 			),
		 * 			"W" => array(...),
		 * 			"M" => array(...)
		 * 		)
		 * );  */

		// define local error types
		$error_type = array(
			"database_select_error",
			"sku_parse_error",
			"create_mod_table"
		);
		$error_count = array_fill_keys($error_type, 0);


		$_org = $prefix.$suffix;
		$_tbl = $prefix.$suffix.$mod_suffix;
		if(!$this->query("CREATE TABLE IF NOT EXISTS $_tbl SELECT * FROM $_org")) {
			return !($this->setState($error_type[2],"Failed Creating Mod Tables",array("table"=>$_tbl,"mod"=>$_mod)));
		}

		// start with just the tank tops table to limit the overwhelmingness
		$query = "SELECT * FROM $_tbl ORDER BY handle, title DESC, variant_sku";
		if(!($$_tbl = $this->query($query))) {
			$this->setState($error_type[0],"Error Querying Data from Database",++$error_count[$error_type[0]], array($this->db->error));
			return $this->errors;
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
			/* debug: check specific product
				if($prosku == "ARTTT0002") {
					return array(
						"varsku" => $varsku,
						"lastProSku" => $lastlastProSku,
						"spec"	=> $spec,
						"size"	=> $size,
						"opt1key"	=> $opt1key,
						"opt2key"	=> $opt2key,
						"opt3key"	=> $opt3key,
						"opt1name"	=> $row->option_1_name,
						"opt1val"		=> $row->option_1_value,
						"opt2name"	=> $row->option_2_name,
						"opt2val"		=> $row->option_2_value,
						"opt3name"	=> $row->option_3_name,
						"opt3val"		=> $row->option_3_value,
						"lastOpt1Key"	=> $lastOpt1Key,
						"lastOpt2Key"	=> $lastOpt2Key,
						"lastOpt3Key"	=> $lastOpt3Key,
					);
				}*/
		}
		// check for any errors and (for now) dump the errors array. (future should redirect to an interface form for the frontend user to fix these)
		if(array_sum($error_count)>0) {
			foreach($error_type as $i=>$type_code) {
				if($error_count[$type_code] > 0) return array( $type_code => $this->getState($type_code) );
			}
		}
		// return data array if everything goes well
		return $this->product_opts;

	}

	private function fixOptions($suffix = "tt", $prefix = "products_", $mod_suffix = '_edited') {
		/* create if does not exist new table with suffix like '_edited'
			check first option if valid size
				if so, check for correct key: value (change value to google appropriate size)
			 if not, check if is color
			 	if so, check col2 data (and do a similar process as below)
			 	if not, check for data in column 3
						if not, put the data there
						if so, make sure col3 is not duplicate data (from column 1 or 2)
							if so, overwrite data in col3 (it's duplicate anyway)
							if not, check if col3 is color
							  if so, put in col2 and put col1 in col3
							  if not, error for now
			check second opton (unless manipulated previously) for a color
				if so, check for correct keys
			 if not, check for value in col3
					if not, stash col2 org data to there, and write color to col2
					if so, make sure col3 data is not duplicate (and follow above process from there)
			now 3rd column should be populated unless the only data already associated was color and/or size
			
			also... check for known mixed or mis-matched values. like Men's Large, Black Ceramic (if both kinds are Ceramic, black is the color, ceramic won't show)
			
			used fixed values to update (replace would be better) into suffixed table
			.... actually, now that i think about it, create if not exists, fill with old data, and run update query on that.
			.... this might be able to be temporary down the line when it's one fluid process, just for generation of the CSV, then removed */
		$_tbl = $prefix.$suffix;
		// use double $ to denote data (might start this as a trend in the future)
		$$_tbl = $this->loadOptions($suffix, $prefix, $mod_suffix);
		// check for errors, and deal with them first if required
		if($this->isError($$_tbl)) return $$_tbl;


		// ended up using this format for return data and uses values to call mutation functions and set state
		$this->howToModify = array_keys($this->modifications[0]); // .. produces
			/* array( // verbose: key is return value..
				0 => "valid",	// current size is valid, move on to checking key
				1 => "mod",		// current size needs to be changed to 2 letter code
				2 => "mutate");	// current size needs modified or is not size, and should be preserved or checked if color */

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
							$opt_count = count($mod_opts)), $this->modifications[0]);
						    // Retained for value reference:
								// array( // keeps track of value modification
								// 	"valid" => false,	// current size is valid, move on to checking key
								// 	"mod"	=> false,	// current size needs to be changed to 2 letter code
								// 	"mutate"=> false	// current size needs modified or is not size, and should be preserved or checked if color
								// ));
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
						// Set options to modified values

						/**
						 * Dump if options change: 
						 */
						// if($mod_opts!==$org_opts) {
						// 	$this->diedump(array(
						// 		"var_sku"  => $var_sku,
						// 		"pro_sku"  => $pro_sku,
						// 		"group"    => $group,
						// 		"size"     => $size,
						// 		"special"  => $special,
						// 		"column"   => $column,
						// 		"org_opts" => $org_opts,
						// 		"mod_opts" => $mod_opts
						// 	), $this->errors);
						// }
						// $options = $modifiedOptions;
					} // end of specials loop, special key with opts array
				} // end of size loop, size key with specials arr)	
			} // end of per variant loop (group key with size arr)
		} //end of per product loop

		$this->diedump(array(
			"where"			=> "fixOptions after loop",
			"classErrors"	=> $this->errors,
			"tableData"		=> $$_tbl
		));

	}

	/**
	 * Move Option logic outside loop, to be called from other memebers.
	 */
	private function processOption($column, $org_opts, &$mod_opts, $pro_args) {
		// original option properties
		$org_opt  = &$org_opts[$column];
		$org_key  = @array_pop(array_keys($org_opt));
		$org_val  = &$org_opt[$org_key];
		// current (modified) option propterties
		$cur_opt  = &$mod_opts[$column];
		$cur_key  = @array_pop(array_keys($cur_opt));
		$cur_val  = &$cur_opt[$cur_key];
		$valueInvalid = false;
		$ret_val  = false;
		// extract product variables into current scope. feel like it's better than globals, and makes modification easier.
		extract($pro_args,EXTR_SKIP||EXTR_REFS);
		if(($valueInvalid=$this->checkValueInvalid($column,$cur_val))!==false) {
			$mod_type = $this->howToModify[$valueInvalid];
			// apply modification or mutation
			if($valueInvalid > 0) { // 0 means valid, so don't bother with it
				$mod_method = $mod_type."ColumnValue";
				// for future determination of what was altered
				$modification = &$this->modifications[$var_sku][$column][$mod_type];
				 // should return true if altered, false otherwise
				$modification = $this->{$mod_method}($column, $cur_val, array(
					"var_sku"  => $var_sku,
					"pro_sku"  => $pro_sku,
					"group"    => $group,
					"size"     => $size,
					"special"  => $special,
					"opt_key"  => $cur_key,
					"org_opts" => $org_opts,
					"mod_opts" => &$mod_opts
				));
				// update return value
				$retval = $modification || $retval;

				// pass by reference, and it'll be updated if it is, and true will denote the change.
					// not true, just keep going, if false, errors should be handled lower (i.e. setting an error state).
						// // stop here because it denotes error
						// if($mod_result!==false) return false;
						// // otherwise, update value with result
						// $opt_val = $mod_result;
			}
			if($this->checkKeyValid($column, $cur_key)) {
				// key valid, move on
			}
		} else {
			$this->setState("invalid_column_error","Options Column ".($column+1)." Not Available.", array(
				"var_sku"  => $var_sku,
				"pro_sku"  => $pro_sku,
				"group"    => $group,
				"size"     => $size,
				"special"  => $special,
				"column"   => $column,
				"opt_key"  => $cur_key,
				"org_opts" => $org_opts,
				"mod_opts" => $mod_opts
			));
			$retval = false;
		}
		return $retval;
	}

	// to avoid a huge if, and make the code more readable and maintainable, define derivitive functions
	private function checkValueInvalid($column, $value) {
		if(null===($VVarr = $this->VV($column))) return false;
		if(array_search($value, array_keys($VVarr))) return 0;
		elseif(array_search($value, $VVarr)) return 1;
		else return 2;
	}

	private function checkKeyValid($column,$curkey) {
		if(!(array_key_exists($column, self::VALID_KEYS) && (null !== self::VALID_KEYS[$column])))
			return !($this->setState("invalid_column_error","Options Column ".($column+1)." Not Available.",array("column"=>$column,"key"=>$curkey)));
		if(empty($curkey) || is_null($curkey)) return self::VALID_KEYS[$column];
		if(strcasecmp($curkey, self::VALID_KEYS[$column])===0) return true;
		// $this->diedump(func_get_args();
		return in_array($curkey, self::VALID_KEYS) ? -1 : 0;
		// check if keyed for different column
		return !($this->setState("key_determination_error","Unable to determine if key is valid.",array("column"=>$column,"key"=>$curkey)));
	}

	private function preserveColumnValue($column, $value, $args = array()) {
		// function to check for likeness in previously fixed data, and availability to stash safely (in last column)

	}

	/**
	 * Auto Correct Value, correct by reference, return value reflects change.
	 */
	private function autocorrectColumnValue($column, &$value, $args = array()) {
		$org_val = $value;
		$ac_arr  = $this->AC($column);
		$keys    = array_keys($ac_arr);
		$pos     = array_search($value, $keys);
		if($pos === false) return false;
		$ac_val  = $ac_arr[$keys[$pos]];
		if(strcasecmp($ac_val, "_determine_")==0) return $this->determineColumnValue($column, $value, $args);
		$value   = $ac_val;
		return (strcmp($org_val, $value) !== 0);
	}

	private function determineColumnValue($column, &$value, $args = array()) {
		// if last column, nothing to determine
		if($this->isLastColumn($column)) return false;
		// extract args into scope
		extract($args,EXTR_SKIP);
		$val_var = strtolower($this->VK($column));
		if(isset($$val_var)) { // double $ on purpose, looking for var passed in args with same name ($size for col1)
		        // $size for initial column 1
			$sku_val  = @$$val_var; // the value from the sku
			$val_arr  = $this->VV($column);
			$test_val = $val_arr[$sku_val];
			$value 	  = $test_val;
			// should take care of switching for key (proper value)
			return $this->modColumnValue($column, $value, $args);
		} // not passed in args, determine elsewehere -- look for determinite function
		elseif(method_exists($this, ($det_method = "determine".ucfirst($val_var)))) { 
			return $this->{$det_method}($value, $args);
		} else {
			// cannot be determined, error out, return false... yatta yatta
			return !$this->setState("indeterminate_value_error","Unable to Determine Proper Value for ".ucfirst($val_var), array(
				"column" => $column,
				"value"  => $value,
				"args"   => $args
			));
		}
	}

	private function determineColor(&$value, $pro_args, $suffix = 'tt', $prefix = "products_", $mod_suffix = "_edited") {
		$org_val = $value;
		// extract pro_args into scope
		extract($pro_args,EXTR_SKIP);
		$_tbl    = $prefix.$suffix.$mod_suffix;
		$select  = "SELECT variant_image FROM $_tbl WHERE variant_sku = '$var_sku' LIMIT 1";
		if(!isset($this->colorx)) $this->colorx = new ColorExtractor;
		if(!isset($this->color_cache)) $this->color_cache = array();
		if(!($$_tbl = $this->query($select))) {
			return ($this->setState("query_fail_error","MySQLi Error: ".$this->db->error, array("query"=>$select))&&false);
		}
		$image_src = @array_pop($$_tbl->fetch_array());
		$image_src = strpos($image_src, '?') !== false ? stristr($image_src, '?', true) : $image_src;
		$image_ext = substr($image_src, strrpos($image_src, '.'));
		$image     = null;
		// Cache color for image to reduce processing and unexpected variations on variants
		$this->color_cache = isset($this->color_cache) ? $this->color_cache : array();
		if(!array_key_exists($image_src, $this->color_cache)) {
			switch($image_ext) {
				case ".png":
					$image = $this->colorx->loadPng($image_src);
				break;
				case ".jpg":
				case ".jpeg":
					$image = $this->colorx->loadJpeg($image_src);
				break;
				case ".gif":
					$image = $this->colorx->loadGif($image_src);
				break;
				default:
					return !$this->setState("image_extension_error","Image Extension Not Found", array(
						'var_sku'   => $var_sku,
						'_tbl'      => $_tbl,
						"$_tbl"     => $$_tbl,
						'image_src' => $image_src,
						'image_ext' => $image_ext,
						'cache'     => $this->color_cache
					));
				break; // end switch image_ext
			}
			// Check for null image
			if(is_null($image)) {
				return !$this->setState("image_read_error","Unable to Read Image: ".basename($image_src), array(
					'var_sku'   => $var_sku,
					'_tbl'      => $_tbl,
					"$_tbl"     => $$_tbl,
					'image_src' => $image_src,
					'image_ext' => $image_ext,
					'cache'     => $this->color_cache
				), null, "display_error");
			}

			// Extract most common hex color from image
			$palette = $image->extract();
			if(!(is_array($palette) && (count($palette)>0)))  {
				return !$this->setState("image_color_extract_error","Unable to Color from Image: ".basename($image_src), array(
					'var_sku'   => $var_sku,
					'palette'	=> $palette,
					'_tbl'      => $_tbl,
					"$_tbl"     => $$_tbl,
					'image_src' => $image_src,
					'image_ext' => $image_ext,
					'cache'     => $this->color_cache
				));
			}
			// pull most prominent (or only at this time) HEX color value off palette
			$hex     = strtoupper(array_shift($palette));
			if(!($tmp = preg_match("/^#[A-Z0-9]+$/", $hex))) {
				return !$this->setState("invalid_hex_error","Invalid Hex Color Code: $hex", array(
					'var_sku'   => $var_sku,
					'palette'   => $palette,
					'hex'		=> $hex,
					'_tbl'      => $_tbl,
					"$_tbl"     => $$_tbl,
					'image_src' => $image_src,
					'image_ext' => $image_ext,
					'cache'     => $this->color_cache
				));
			}
			// Get Human Friendly Color Name from HEX
			$color   = $this->getColorFromHex($hex);
			if(($invalid = $this->checkValueInvalid(array_search("Color", self::VALID_KEYS), $color)))  {
				return !$this->setState("invalid_color_error","Invalid Color Value: $color", array(
					'var_sku'   => $var_sku,
					'palette'   => $palette,
					'hex'		=> $hex,
					'color'		=> $color,
					'invalid'	=> $invalid,
					'_tbl'      => $_tbl,
					"$_tbl"     => $$_tbl,
					'image_src' => $image_src,
					'image_ext' => $image_ext,
					'cache'     => $this->color_cache
				));
			}
			// cache color
			$this->color_cache[$image_src] = $color;
		}
		// update value with color from cache, and compare to original value to return boolean mutation value
		return (($value = $this->color_cache[$image_src])!==$org_val);
	}

	private function determineKind(&$value, $var_sku, $suffix = 'tt', $prefix = 'products_', $mod_suffix = "_edited") {
		// Kind can be anything, and shouldn't make it here really, but if so, return true
		return true;
	}

	/**
	 * Column Mutator functions
	 */

	private function mutateColumnValue($column, &$value, $args = array()) {
		// here the value is not a valid size, check if derivable size (like group+size, by regex), 
		// or if is a color(run next column val check on current val), 
		// or needs to be moved elsewhere, preserve and write from size argument

		// if last column -- which, for now, should not need mutated
		if($this->isLastColumn($column)) return false;
		// stash original value, in case
		$org_val = $value;
		// if empty determine from sku / image
		if(empty($value)) return $this->determineColumnValue($column, $value, $args);
		// extract args:
			// "var_sku"  => $var_sku,
			// "pro_sku"  => $pro_sku,
			// "group"    => $group,
			// "size"     => $size,
			// "special"  => $special,
			// "opt_key"  => $opt_key,
			// "org_opts" => $options,
			// "mod_opts" => &$mod_opts
		if(!empty($args)) extract($args, EXTR_SKIP||EXTR_REFS);
		// check if can be auto-corrected (simple for now, to be extended)
		if($this->autocorrectColumnValue($column,$value)) {
			// add state for this, may not even bother with this tho
			$this->setState("autocorrect_option_value","Value Auto Corrected from '".$org_val."' to '".$value."'", func_get_args());
			// re-process the option with the auto-corrected value.
			$this->diedump($this->processOption($column, $org_opts, $mod_opts, $args), $args);
			return !!$this->processOption($column, $org_opts, $mod_opts, array(
				"var_sku" => $var_sku,
				"pro_sku" => $pro_sku,
				"group"   => $group,
				"size"    => $size,
				"special" => $special
			));
		}
		/**
		 * look to write data.. preserve data. get to the point. move or write. who give a fuck about features!!
		 */

		// check if value is valid for next column
		if(!$this->isLastColumn($column) && !($next_val_invalid = $this->checkValueInvalid(($next_column = ($column+1)), $value))) {
			// should not return false; error state
			if($next_val_invalid===false) {
				return $this->setState("next_val_invalid_index_error", "Invalid Column '$next_column' When Checking Next Column",array_merge(array(
					"next_val_invalid" => $next_val_invalid,
					"next_column"      => $next_column
				), $args));
			}
			// valid next column value

			return false; // for now, to debug
		}

		if(($column > 0) && !($prev_val_invalid = $this->checkValueInvalid(($prev_column = ($column-1)), $value))) {
			// should not return false; error state
			if($prev_val_invalid===false) {
				return $this->setState("prev_val_invalid_index_error", "Invalid Column '$prev_column' When Checking Previous Column",array_merge(array(
					"prev_val_invalid" => $prev_val_invalid,
					"prev_column"      => $prev_column
				), $args));
			}
			// valid value of previous column
			// check if already modified (at this point), and see if extra data available, preserve or dismiss

			return false;// for now, to debug

		}

		// try to determine if valid value can be parsed from value (stash rest... or, if not, try stashing all
		// try parsing original value for typical parts for preservation
		$parsed = $this->parseColumnValue($column, $value, $args);
		// unable to parse anything, or, test for single word -- either way, attempt preserve and set
		if(($parsed===false) || ($parsed === $org_val)) { // single word, invalid, and not next column... preserve and set?
			$this->setState("single_word_preserve_error","No Spaces, need Mutation, Preserve and Set?", array(
				"where"   =>"single_word_preserve_error",
				"column"  => $column,
				"org_val" => $org_val,
				"value"   => $value,
				"parsed"  => $parsed,
				"args"	  => $args,
				"errors"  => $this->errors
			));
			// should return true on modification, check to preserve and set. false if no mod possible, probably error state (should not be like above)
			return false;
		}

		/**
		 do something about the parsed values
		 */


		$this->diedump(array(
			"where"   => "end of mutateColumnValue",
			"column"  => $column,
			"org_val" => $org_val,
			"value"   => $value,
			"parsed"  => $parsed,
			"1"		  => "======================= args ==========================",
			"args"	  => $args,
			"2"		  => "======================= errors ==========================",
			"errors"  => $this->errors
		));

	}

	private function parseColumnValue($column, $value, $args = array()) {
		extract($args,EXTR_SKIP);
		$test_params = array(); // array of test cases
		$results = array(); // array with key of word position, value of matches
		// check variable value for column based off valid key
		// fill test params based on this, if guess available, use that, if not, check all valid values
		if(isset(${($val_var = strtolower($this->VK($column)))})) { // means passed in args
			// test value can be set from SKU, add that to test params
			$test_params[] = $$val_var; // the value from the sku, exported from pro_args, $size in case 0
			$valid_values  = $this->VV($column);
			$test_params[] = $valid_values[$sku_val];
		} else {
			$test_params = array_keys($this->VV($column));
		}
		// check by word, split by space
		$val_words   = preg_split("/[\s,]+/", $value);
		// return original value if has no spaces, as the other validation would have picked up single words
		if(count($val_words)===1) return array_pop($val_words); // pop off array to test later, error if different?
		/** * @todo: improve checking regex */
		// build loose regex, could be improved
		$regex   = "/(".implode("|", $test_params).")/";
		// loop through each word to check for matches
		foreach($exp_val as $word_pos => $word) {
			$matches = array();
			$matched = preg_match($regex, $word, $matches);
			if($matched) {
				$results[$word_pos] = $matches;
			}
		}
		if(!empty($results)) {
			// do something about it matching
			$this->diedump($value,$results,$test_params, $this->errors);
		}
		return !empty($results) ? $results : false;
	}

	private function modColumnValue($column, &$opt_val, $args = array()) {
		// change size to 2 letter code
		$key_val = array_search($opt_val,$this->{$this->validationVarByColumn($column)});
		if($key_val!==false) {
			$opt_val = $key_val;
			return true;
		}
		return false;
	}


	// End Mutotr Functions /////////////////

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
			// This query gets a count of sku_types:
			// $query = "SELECT DISTINCT SUBSTRING(variant_sku,4,2) as type_code, COUNT(variant_sku) as type_count, " .
			//			"(SELECT description FROM sku_standard WHERE sku_standard.sku_code = type_code) as type_desc FROM org_export GROUP BY type_code";
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
		return (count($this->errors)==0);
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

	protected function setState($code = "shopify_standard_error", $message = "Error in ShopifyStandard", $data = null, $count = null, $caller = null) {
		$caller = is_null($caller)? debug_backtrace()[1]['function'] : $caller;
		$data   = is_null($data)  ? debug_backtrace() : $data;
		$count  = is_null($count) ? ( isset($this->errors[$caller]) ? ( isset($this->errors[$caller][$code]) ? count($this->errors[$caller][$code]) : 1 ) : 1) : $count;
		try {
			$this->errors[$caller][$code][$count] = array(
				"message" => $message,
				"data"    => $data
			);
		} catch (Exception $e) {
			$this->diedump($code, $message, $caller, $count, $data, $e);
		}
		return $count;
	}

	protected function getState($code = "shopify_standard_error", $index = null, $caller = null, $just_data = true) {
		$caller = is_null($caller) ? debug_backtrace()[1]['function'] : $caller;
		if(is_null($this->errors[$caller][$code])) return array("getError_error"=>array(
			"caller" => $caller,
			"code"   => $code,
			"index"  => $index,
			"errors" => $this->errors
		));

		return is_null($index) ? (
			!!$just_data ? (
				array_map(function($arr) {
					return $arr['data'];
				}, $this->errors[$caller][$code])
			) : (
				$this->errors[$caller][$code]
			)
		) : ( 
			array_key_exists($index,$this->errors[$caller][$code]) ? (
				!!$just_data ? (
					$this->errors[$caller][$code][$index]['data']
				) : (
					$this->errors[$caller][$code][$index]
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
		error_log("////////////////////////////////// END RUN (".microtime().") ShopifyStandard CLASS ///////////////////////////////");
	}
}