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

	private function classVarByIndex($index, $prefix, $suffix = "S") {
		return strtoupper($prefix.self::VALID_KEYS[$index].$suffix);
	}

	private function validationVarByIndex($index, $prefix = "VALID_", $suffix = "S") {
		return $this->classVarByIndex($index,$prefix,$suffix);
	}

	private function autocorrectVarByIndex($index, $prefix = "AUTO_CORRECT_", $suffix = "S") {
		return $this->classVarByIndex($index,$prefix,$suffix);
	}

	// utility methods for getting validation arrays
	private function VV($index=null) { return $this->{$this->validationVarByIndex($index)}; }
	private function VK($index=null) { return self::VALID_KEYS[$index]; }
	private function AC($index=null) { return $this->{$this->autocorrectVarByIndex($index)}; }

	private function setValidation() {
		// not really static, but function similarly
		// in future, this data should be pulled from the database

		try {
			/**
			 * set Validation Arrays: VALID_{COLUMN}S
			 */

			// define column_1 valid values (key being valid, value being mutation)
			// should be named VALID_SIZES (default, but derivitive of self::VALID_KEYS)
			// private static ${$this->validationVarByIndex(0)} = array(
			$col1 = $this->validationVarByIndex(0);
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
			$col2 = $this->validationVarByIndex(1);
			if(!isset($this->$col2) || empty($this->$col2)) {
				$this->$col2 = array("Black" => "#000000","Navy" => "#000080","Dark Blue" => "#00008B","Medium Blue" => "#0000CD","Blue" => "#0000FF","Dark Green" => "#006400",
					"Green" => "#008000","Teal" => "#008080","Dark Cyan" => "#008B8B","Deep Sky Blue" => "#00BFFF","Dark Turquoise" => "#00CED1","Medium Spring Green" => "#00FA9A","Lime" => "#00FF00",
					"Spring Green" => "#00FF7F","Aqua" => "#00FFFF","Cyan" => "#00FFFF","Midnight Blue" => "#191970","Dodger Blue" => "#1E90FF","Light Sea Green" => "#20B2AA",
					"Forest Green" => "#228B22","Sea Green" => "#2E8B57","Dark Slate Gray" => "#2F4F4F","Lime Green" => "#32CD32","Medium Sea Green" => "#3CB371","Turquoise" => "#40E0D0",
					"Royal Blue" => "#4169E1","Steel Blue" => "#4682B4","Dark Slate Blue" => "#483D8B","Medium Turquoise" => "#48D1CC","Indigo" => "#4B0082","Dark Olive Green" => "#556B2F",
					"Cadet Blue" => "#5F9EA0","Cornflower Blue" => "#6495ED","Rebecca Purple" => "#663399","Medium Aqua Marine" => "#66CDAA","Dim Gray" => "#696969","Slate Blue" => "#6A5ACD",
					"Olive Drab" => "#6B8E23","Slate Gray" => "#708090","Light Slate Gray" => "#778899","Medium Slate Blue" => "#7B68EE","Lawn Green" => "#7CFC00","Chartreuse" => "#7FFF00",
					"Aquamarine" => "#7FFFD4","Maroon" => "#800000","Purple" => "#800080","Olive" => "#808000","Gray" => "#808080","Sky Blue" => "#87CEEB","Light Sky Blue" => "#87CEFA",
					"Blue Violet" => "#8A2BE2","Dark Red" => "#8B0000","Dark Magenta" => "#8B008B","Saddle Brown" => "#8B4513","Dark Sea Green" => "#8FBC8F","Light Green" => "#90EE90",
					"Medium Purple" => "#9370DB","Dark Violet" => "#9400D3","Pale Green" => "#98FB98","Dark Orchid" => "#9932CC","Yellow Green" => "#9ACD32","Sienna" => "#A0522D",
					"Brown" => "#A52A2A","Dark Gray" => "#A9A9A9","Light Blue" => "#ADD8E6","Green Yellow" => "#ADFF2F","Pale Turquoise" => "#AFEEEE","Light Steel Blue" => "#B0C4DE",
					"Powder Blue" => "#B0E0E6","Fire Brick" => "#B22222","Dark Golden Rod" => "#B8860B","Medium Orchid" => "#BA55D3","Rosy Brown" => "#BC8F8F","Dark Khaki" => "#BDB76B",
					"Silver" => "#C0C0C0","Medium Violet Red" => "#C71585","Indian Red" => "#CD5C5C","Peru" => "#CD853F","Chocolate" => "#D2691E","Tan" => "#D2B48C","Light Gray" => "#D3D3D3",
					"Thistle" => "#D8BFD8","Orchid" => "#DA70D6","Golden Rod" => "#DAA520","Pale Violet Red" => "#DB7093","Crimson" => "#DC143C","Gainsboro" => "#DCDCDC","Plum" => "#DDA0DD",
					"Burly Wood" => "#DEB887","Light Cyan" => "#E0FFFF","Lavender" => "#E6E6FA","Dark Salmon" => "#E9967A","Violet" => "#EE82EE","Pale Golden Rod" => "#EEE8AA",
					"Light Coral" => "#F08080","Khaki" => "#F0E68C","Alice Blue" => "#F0F8FF","Honey Dew" => "#F0FFF0","Azure" => "#F0FFFF","Sandy Brown" => "#F4A460","Wheat" => "#F5DEB3",
					"Beige" => "#F5F5DC","White Smoke" => "#F5F5F5","Mint Cream" => "#F5FFFA","Ghost White" => "#F8F8FF","Salmon" => "#FA8072","Antique White" => "#FAEBD7","Linen" => "#FAF0E6",
					"Light Golden RodYellow" => "#FAFAD2","Old Lace" => "#FDF5E6","Red" => "#FF0000","Fuchsia" => "#FF00FF","Magenta" => "#FF00FF","Deep Pink" => "#FF1493","Orange Red" => "#FF4500",
					"Tomato" => "#FF6347","Hot Pink" => "#FF69B4","Coral" => "#FF7F50","Dark Orange" => "#FF8C00","Light Salmon" => "#FFA07A","Orange" => "#FFA500","Light Pink" => "#FFB6C1",
					"Pink" => "#FFC0CB","Gold" => "#FFD700","Peach Puff" => "#FFDAB9","Navajo White" => "#FFDEAD","Moccasin" => "#FFE4B5","Bisque" => "#FFE4C4","Misty Rose" => "#FFE4E1",
					"Blanched Almond" => "#FFEBCD","Papaya Whip" => "#FFEFD5","Lavender Blush" => "#FFF0F5","Sea Shell" => "#FFF5EE","Cornsilk" => "#FFF8DC","Lemon Chiffon" => "#FFFACD",
					"Floral White" => "#FFFAF0","Snow" => "#FFFAFA","Yellow" => "#FFFF00","Light Yellow" => "#FFFFE0","Ivory" => "#FFFFF0","White" => "#FFFFFF"
				);
			}

			// define column_3 valid values this can be anything, so not sure yet... just needs to exist.
			// VALID_KINDS
			$col3 = $this->validationVarByIndex(2);
			if(!isset($this->$col3) || empty($this->$col3)) {
				$this->$col3 = array();
			}

			
			/**
			 * set Autocorrect Arrays: AUTO_CORRECT_{COLUMN}S
			 */

			// define column_1 auto-correct values (key being valid, value being mutation)
			// should be named AUTO_CORRECT_SIZES (default, but derivitive of self::VALID_KEYS)
			$col1 = $this->autocorrectVarByIndex(0);
			if(!isset($this->$col1) || empty($this->$col1)) {
				$this->$col1 = array(
					"Medoum" => "Medium"
				);
			}

			// define column_2 auto-correct values (key is misspelled color, value is corrected, to be mutated)
			// AUTO_CORRECT_COLORS
			$col2 = $this->autocorrectVarByIndex(1);
			if(!isset($this->$col2) || empty($this->$col2)) {
				$this->$col2 = array();
			}

			// define column_3 auto-correct values, this can be anything, so not sure yet... just needs to exist.
			// AUTO_CORRECT_KINDS
			$col3 = $this->autocorrectVarByIndex(2);
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

	public function doNewImportColorFix($table = "new_import") {
		$retval = false;
		try {
			$retval = $this->newImportColorFix();
		} catch(Exception $e) {
			$this->setState("exception:".$e->getCode(),"MySQLi Error: ".$e-getMessage());
		}
		return (count($this->errors)>0 ? $this->errors : $retval);
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
			"option_1" => $this->getOptionKeyValueByIndex(1),
			"option_2" => $this->getOptionKeyValueByIndex(2),
			"option_3" => $this->getOptionKeyValueByIndex(3)
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
			// figure out setting the prefix/mod_suffix in constructor later
			$table  = "products_".substr($old_sku,3,2);
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
		/** 
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
		);
		$error_count = array_fill_keys($error_type, 0);

		// start with just the tank tops table to limit the overwhelmingness
		$_tbl  = $prefix.$suffix.$mod_suffix;
		$query = "SELECT * FROM $table ORDER BY handle, title DESC, variant_sku";
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
			.... this might be able to be temporary down the line when it's one fluid process, just for generation of the CSV, then removed
		*/
		$_tbl = $prefix.$suffix;
		$_mod = $prefix.$suffix.$mod_suffix;
		if(!$this->query("CREATE TABLE IF NOT EXISTS $_mod SELECT * FROM $_tbl")) {
			return !($this->setState("create_mod_table","Failed Creating Mod Tables",array("table"=>$_tbl,"mod"=>$_mod)));
		}
		// use double $ to denote data (might start this as a trend in the future)
		$$_tbl = $this->loadOptions($suffix);
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
						// options array should be [0...2] corresponding keys/value with db values: {option_[1...3]_name: option_[1...3]_value}
						$modifiedOptions = $options;

						$var_sku = $pro_sku.$group.$size.$special;

						$this->modifications[$var_sku] = array_fill(0, (
							$optcount = count($modifiedOptions)), $this->modifications[0])
						    // Retained for reference:
							// array( // keeps track of value modification
							// 	"valid" => false,	// current size is valid, move on to checking key
							// 	"mod"	=> false,	// current size needs to be changed to 2 letter code
							// 	"mutate"=> false	// current size needs modified or is not size, and should be preserved or checked if color
							// ));
						// get keys, use for instead of foreach because I want to control the pointer
						for($index = 0; $index < $optcount; $index++) {
							$this->processOption($index, $options, $modifiedOptions, array(
								"var_sku" => $var_sku,
								"pro_sku" => $pro_sku,
								"group"   => $group,
								"size"    => $size,
								"special" => $special
							));
						}
						// Set options to modified values
						$options = $modifiedOptions;
					} // end of specials loop, special key with opts array
				} // end of size loop, size key with specials arr)	
			} // end of per variant loop (group key with size arr)
		} //end of per product loop

		die(var_dump(array(
			"classErrors"	=> $this->errors,
			"tableData"		=> $$_tbl
		)));

	}

	/**
	 * Move Option logic outside loop, to be called from other memebers.
	 */
	private function processOption($index, $org_opts, &$mod_opts, $pro_args) {
		// extract product variables into current scope. feel like it's better than globals, and makes modification easier.
		extract($pro_args);
		$opt_key  = array_pop(array_keys($org_opts[$index]));
		$opt_val  = &$mod_opts[$opt_key];
		$valueInvalid = false;
		if(($valueInvalid=$this->checkValueValid($index,$opt_val))!==false) {
			$mod_type = $this->howToModify[$valueInvalid]
			// apply modification or mutation
			if($valueInvalid > 0) { // 0 means valid, so don't bother with it
				$mod_method = $mod_type."Column".($index+1)."Value";
				// for future determination of what was altered
				$this->modifications[$var_sku][$index][$mod_type] = // break lnes for readabilty
					// should return true if altered, false otherwise
					$this->{$validatinoMethod}($opt_val, array(
						"var_sku"  => $var_sku,
						"pro_sku"  => $pro_sku,
						"group"    => $group,
						"size"     => $size,
						"special"  => $special,
						"index"    => $index,
						"opt_key"  => $opt_key,
						"org_opts" => $options,
						"mod_opts" => $mod_opts
					));

				// not true, just keep going, if false, errors should be handled lower (i.e. setting an error state).
				// pass by reference, and it'll be updated if it is, and true will denote the change.
				// // stop here because it denotes error
				// if($mod_result!==false) return false;
				// // otherwise, update value with result
				// $opt_val = $mod_result;
			}
			if($this->checkKeyValid($index, $opt_key)) {
				// key valid, move on
			}
		} else {
			$this->setState("invalid_index_error","Options Column ".($index+1)." Not Available.", array(
				"var_sku"  => $var_sku,
				"pro_sku"  => $pro_sku,
				"group"    => $group,
				"size"     => $size,
				"special"  => $special,
				"index"    => $index,
				"opt_key"  => $opt_key,
				"org_opts" => $options,
				"mod_opts" => $mod_opts
			));
		}
	}

	// to avoid a huge if, and make the code more readable and maintainable, define derivitive functions
	private function checkValueValid($index, $value) {
		if(null===($VVarr = $this->VV($index))) return false;
		if(array_search($value, array_keys($VVarr))) return 0;
		elseif(array_search($value, $VVarr)) return 1;
		else return 2;
	}

	private function checkKeyValid($index,$curkey) {
		if(!(array_key_exists($index, self::VALID_KEYS) && (null !== self::VALID_KEYS[$index])))
			return !($this->setState("invalid_index_error","Options Column ".($index+1)." Not Available.",array("index"=>$index,"key"=>$curkey)));
		if(empty($curkey) || is_null($curkey)) return self::VALID_KEYS[$index];
		if(strcasecmp($curkey, self::VALID_KEYS[$index])===0) return true;
		// die(var_dump(array(func_get_args())));
		return in_array($curkey, self::VALID_KEYS) ? -1 : 0;
		// check if keyed for different column
		return !($this->setState("key_determination_error","Unable to determine if key is valid.",array("index"=>$index,"key"=>$curkey)));
	}

	private function preserveColumnValue($index, $value) {
		// function to check for likeness in previously fixed data, and availability to stash safely (in last column)

	}

	/**
	 * Auto Correct Value, correct by reference, return value reflects change.
	 */
	private function autocorrectValue($column, &$value) {
		$acArr = $this->AC($column);
		$keys  = array_keys($acArr);
		$pos   = array_search($value, $keys);
		if($pos!==false) return !!($value = $acArr[$keys[$pos]]);
		return false;
	}

	/**
	 * Column 1 Mutator functions
	 */

	private function mutateColumn1Value(&$value,$args) {
		// here the value is not a valid size, check if derivable size (like group+size, by regex), 
		// or if is a color(run next index val check on current val), 
		// or needs to be moved elsewhere, preserve and write from size argument
		if(empty($value)) false;

		$orgval  = $value;
		
		extract($args);
		// extracted args:
			// "var_sku"  => $var_sku,
			// "pro_sku"  => $pro_sku,
			// "group"    => $group,
			// "size"     => $size,
			// "special"  => $special,
			// "index"    => $index,
			// "opt_key"  => $opt_key,
			// "org_opts" => $options,
			// "mod_opts" => $mod_opts

		$validKey= strtolower($this->VK($index));
		        // $size for initial column 1
		$skuVal  = @$$validKey; // the value from the sku
		$valArr  = $this->VV($index);
		$testVal = $valArr[$size];
		$acArr   = $this->AC($index);
		$crctd   = $this->autocorrectValue($index,$value);
		if($crctd) {
			$this->setState("autocorrect_option_value","Value Auto Corrected from '".$orgval."' to '".$value."'", func_get_args());
			// rerun validation bit. probably pull this function out from above. (this meaning the part in the small loop.)
			$this->modifications[$var_sku][$index]['mutate'] = false;
			die(var_dump($this->processOption($index, $options, $modifiedOptions, $modifications, array(
				"size"	  => $size,
				"group"  => $group,
				"prosku"  => $prosku
			)), $this->errors));
		}

		/**
		 * look to write data.. preserve data. get to the point. move or write. who give a fuck about features!!
		 */
		// if(empty($value)) { move toward top
		// *
		// * If the value is empty, set it based off the sku
		 

		// }


		if(true || strpos(" ", $ac_val) === false) {
			// no spaces, don't bother checking for regex
			// probably inverse this, but keeping for now for logical progression

			// die(var_dump(
				$this->setState("no_space","No Spaces, need Mutation", array(
					"where"  => "NoSpace",
					"orgval" => $orgval,
					"value"  => $value,
					"crctd"	 => $crctd,
					"validKey"=>$validKey,
					"truVal" => $skuVal,
					"valArr" => !!$valArr,
					"testVal"=> $testVal,
					"acArr"  => !!$acArr,
					"args"	 => $args
				));
			// ));
		} else {
			die(var_dump(array(
				"where"  => "Spaces",
				"value"  => $value,
				"validKey"=>$validKey,
				"truVal" => $truVal,
				"valArr" => $valArr,
				"testVal"=> $testVal,
				"acArr"  => $acArr,
				"args"	 => $args
			)));
		}


		//regex with testval
		// die(var_dump(array(
		// 	"value"  => $value,
		// 	"validKey"=>$validKey,
		// 	"truVal" => $truVal,
		// 	"valArr" => $valArr,
		// 	"testVal"=> $testVal,
		// 	"acArr"  => $acArr,
		// 	"args"	 => $args
		// )));

	}

	private function modColumn1Value(&$value, args) {
		// change size to 2 letter code
		$size_val = array_search($value,$this->{$this->validationVarByIndex(0)});
		if($size_val!==false) {
			$value = $size_val;
			return true;
		}
		return false;
	}

	/**
	 * Column 2 Mutator function
	 */

	private function mutateColumn2Value($value,$args) {
		
	}

	private function modColumn2Value($value) {

	}

	/**
	 * Column 3 Mutator functions
	 */

	private function mutateColumn3Value($value,$args) {
		
	}

	private function modColumn3Value($value) {

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

	private function getOptionKeyValueByIndex($index = 1) {
		$option = array();
		$query  = "SELECT option_{$index}_name as optkey, option_{$index}_value as optval FROM org_export";
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

	private function newImportColorFix($table = "new_import") {
		$select = "SELECT * FROM $table";
		if(!isset($this->colorx)) $this->colorx = new ColorExtractor;
		if(!($results = $this->db->query($select))) {
			return ($this->setState("query_fail","MySQLi Error: ".$this->db->error, array("query"=>$select)));
		}
		$sku_colors  = array();
		$color_cache = array();
		while($dbrow = $results->fetch_object()) {
			$image_src = strpos($dbrow->image_src, '?') !== false ? stristr($dbrow->image_src, '?', true) : $dbrow->image_src;
			$image_ext = substr($image_src, strrpos($image_src, '.'));
			$image     = null;
			if(!array_key_exists($image_src, $color_cache)) {
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
						throw new Exception("Image Extension Not Found", 1);
						
				}
				// Check for null image
				if(is_null($image)) throw new Exception("Error Retrieving Image", 2);
				// Extract most common hex color from image
				$palette = $image->extract();
				$color   = $this->getColorFromHex($palette[0]);
				$color_cache[$image_src] = $color;
			}
			$sku_colors[$dbrow->variant_sku] = $color_cache[$image_src];
		}
		foreach($sku_colors as $sku => $color) {
			$query = "UPDATE $table SET option_2_name = 'Color', option_2_value = '$color' WHERE variant_sku = $sku";

		}
	}

	public function getColorFromHex($hex) {
		$lastColor = "Black";
		foreach(self::COLOR_MAP as $color=>$hexval) {
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
		die(var_dump(func_get_args()));
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

	protected function setState($code = "shopify_standard_error", $message = "Error in ShopifyStandard", $data = null, $count = null, $caller = null) {
		$caller = is_null($caller)? debug_backtrace()[1]['function'] : $caller;
		$data   = is_null($data)  ? debug_backtrace() : $data;
		$count  = is_null($count) ? ( isset($this->errors[$caller]) ? ( isset($this->errors[$caller][$code]) ? count($this->errors[$caller][$code]) : 1 ) : 1) : $count;
		return ($this->errors[$caller][$code][$count] = array("message"=>$message,"data"=>$data)) ? $count : false;
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