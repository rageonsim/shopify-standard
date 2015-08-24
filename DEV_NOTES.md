Development Notes
=================

==classes/ShopifyStandard.php==
===General:===
Needs to be classed out to, preferably, DB class, ShopifyStandard(manipulation) class, and ShopifyProduct class
The CSV should really be changed to API. This should be abstracted, for ease of switching

===Prune===
(001) /* older db connection style
        if(!$this->db->options(MYSQLI_INIT_COMMAND, 'SET AUTOCOMMIT = 0'))
          return (($this->connected = null) && ($this->state = array("db"=>array("error"=>array("code"=>"set_autocommit","message"=>"Unable to set autocommit for MySQLi")))));
        if(!$this->db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5))
          return (($this->connected = null) && ($this->state = array("db"=>array("error"=>array("code"=>"set_connection_timeout","message"=>"Unable to set timeout for MySQLi")))));
        // / **
        // * Make actual Connection
        // * /
        if(!($this->connected = $this->db->real_connect($this->host, $this->user, $this->pass, $this->db_name, $this->port)))
          return (($this->connected = null) && ($this->state = array("db"=>array("error"=>array("code"=>"real_connect::"+mysqli_connect_errno(),"message"=>mysqli_connect_error())))));*/
(002) /** Method Goal 
       * load the current single products SKU (strip off trailing defining options (i'm thinking that includes the group one... 
       * the group part can be checked if a 'kind' needs it, but i think this is mainly irrelivent, or just should support the group 'kind')
       * those should be the keys. load the variant full skus (of the main product sku) as an array oF keys,
       * they should be keys for the options
       * so each variant will contain an array of key values of the 3 options as they are currently
       * Example:
       * array(
       *    //SKU Examples: ABCTT1234UXS: ABC vendor - tank top - item 1234 - Unisex - XS ExtraSmall (this should be a key in the acceptable sizes)
       *    //        ABCTT1234US: ABC vendor - tank top - item 1234 - Unisex - S Small (this should be a key in the acceptable sizes)
       *    //        ABCTT1234UM: ABC vendor - tank top - item 1234 - Unisex - M Medium (this should be a key in the acceptable sizes)
       *    "XXXTT0000" => array(
       *      "U" => array(
       *        "XS" => array(
       *          "option_1_name" => "option_1_value", // but the actual values, not the column name
       *          "option_2_name" => "option_2_value", // but the actual values, not the column name
       *          "option_3_name" => "option_3_value", // but the actual values, not the column name
       *        ),
       *        "S" => array(...), // just like above,
       *        "M" => array(...)
       *      ),
       *      "W" => array(...),
       *      "M" => array(...)
       *    )
       * );  */
(003) /* debug: check specific product
        if($prosku == "ARTTT0002") {
          return array(
            "varsku" => $varsku,
            "lastProSku" => $lastlastProSku,
            "spec"  => $spec,
            "size"  => $size,
            "opt1key" => $opt1key,
            "opt2key" => $opt2key,
            "opt3key" => $opt3key,
            "opt1name"  => $row->option_1_name,
            "opt1val"   => $row->option_1_value,
            "opt2name"  => $row->option_2_name,
            "opt2val"   => $row->option_2_value,
            "opt3name"  => $row->option_3_name,
            "opt3val"   => $row->option_3_value,
            "lastOpt1Key" => $lastOpt1Key,
            "lastOpt2Key" => $lastOpt2Key,
            "lastOpt3Key" => $lastOpt3Key,
          );
        }*/
(004) /* create if does not exist new table with suffix like '_edited'
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
(005) /* array( // verbose: key is return value..
        0 => "valid", // current size is valid, move on to checking key
        1 => "mod",   // current size needs to be changed to 2 letter code
        2 => "mutate"); // current size needs modified or is not size, and should be preserved or checked if color */
(006) // Retained for value reference:
      // array( // keeps track of value modification
      //  "valid" => false, // current size is valid, move on to checking key
      //  "mod" => false, // current size needs to be changed to 2 letter code
      //  "mutate"=> false  // current size needs modified or is not size, and should be preserved or checked if color
      // ));
(007) /**
       * Dump if options change: 
       */
      // if($mod_opts!==$org_opts) {
      //  self::diedump(array(
      //    "var_sku"  => $var_sku,
      //    "pro_sku"  => $pro_sku,
      //    "group"    => $group,
      //    "size"     => $size,
      //    "special"  => $special,
      //    "column"   => $column,
      //    "org_opts" => $org_opts,
      //    "mod_opts" => $mod_opts
      //  ), $this->state);
      // }
      // $options = $modifiedOptions;
(008) // pass by reference, and it'll be updated if it is, and true will denote the change.
      // not true, just keep going, if false, errors should be handled lower (i.e. setting an error state).
        // // stop here because it denotes error
        // if($mod_result!==false) return false;
        // // otherwise, update value with result
        // $opt_val = $mod_result;
(009) // autocorrect for capitalization... should do in future, making checks strict, for now, they're loose anyways
      // $cap_val = ucwords($value);
      // if($this->checkValueInvalid($column, $cap_val, $args)==0) {
      //  return !!($value = $cap_val);
      // }
(010) // here the value is not a valid size, check if derivable size (like group+size, by regex), 
      // or if is a color(run next column val check on current val), 
      // or needs to be moved elsewhere, preserve and write from size argument
(011) // extract args:
      // "var_sku"  => $var_sku,
      // "pro_sku"  => $pro_sku,
      // "group"    => $group,
      // "size"     => $size,
      // "special"  => $special,
      // "opt_key"  => $opt_key,
      // "org_opts" => $options,
      // "mod_opts" => &$mod_opts
(012) // update: just return true, error in autocorrectColumnValue if need
      // add state for this, may not even bother with this tho -- 
      //$this->setState("autocorrect_option_value","Value Auto Corrected from '".$org_val."' to '".$value."'", func_get_args());
      // re-process the option with the auto-corrected value.
      // returns a numerical error code, true or false on manipulation, we need to return true (due to the auto correct) unless error code
      /** this might not be true (above), no need to reprocess, just return true, with updated values (already updated due to reference) */
      // return is_bool($this->processOption($column, $org_opts, $mod_opts, $args));
(013) //if($next_val_invalid === 0) { // has to be to get here
      //
      // should be if preserve (and modify) return true, otherwise return false, and set error state in preserve function
      // another function will need to call the preserve function, because here we need to see if the data can be written to the next column
      //   should probably check for the inverse if there is data in the next column, (like, meaning they are swaped)
      //   if there is data in the next column, and it's not swapped (some fun recurssion on that test tho), try to preserve that data, error on fail
      //     which is in effect preserving this column data to a destination column, which should default to 3rd (2), but will be whatever 'next_column' is
      //    
      //    so in the preserveColumnValue...
      //    
      //    trying to write to next column (this time),
      //    check for data in that column. if there's not, go ahead and write it there, (and clear current field)
      //    if there is data there, check if it is a valid value for the current column (like swapped).
      //      if so, write current column value to next column, and let it swap them. (ie. change the current value to that next column's value)
      
      // the preserveColumnValue function should return true if current value altered (like everything else),
      // if the data was preserved, clear the column, cause it was preserved, and needs to be determined (which if empty, will happen on recursion)
      // if the data was set, (as in borrowed from the next column), data changed, so probably check for valid value again
      // ShopifyStandard::diedump($column,$value,$next_column,$args,($this->preserveColumnValue($column,$value,$args,$next_column)) ? (
      //   $this->processOption($column,$org_opts,$mod_opts,$args)
      // ) : $this->getLastState());
      // $this->setState("call_preserve_data","Call for Preservation of Data",array(
      //   "column" => intval($column),
      //   "value"  => "$value",
      //   "args"     => self::array_copy($args),
      //   "next_val_invalid" => $next_val_invalid,
      //   "modifications" => self::array_copy($this->modifications[$var_sku])
      // ), null, "preserveColumnValue");
(014) // i'm starting to no be overly sure you should check the previous column... above should have swaped if necessary... hmmm.
      // if(($column > 0) && !($prev_val_invalid = $this->checkValueInvalid(($prev_column = ($column-1)), $value))) {
      //   // should not return false; error state
      //   if($prev_val_invalid===false) {
      //     return $this->setState("prev_val_invalid_index_error", "Invalid Column '$prev_column' When Checking Previous Column",array_merge(array(
      //       "prev_val_invalid" => $prev_val_invalid,
      //       "prev_column"      => $prev_column
      //     ), $args));
      //   }
      //   // valid value of previous column
      //   // check if already modified (at this point), and see if extra data available, preserve or dismiss
      //   // if($prev_val_invalid === 0) { // has to be, the only way to get here
        
      //   return ShopifyStandard::diedump(array_merge(array(
      //     "prev_val_invalid" => $prev_val_invalid,
      //     "prev_column"      => $prev_column
      //   ), $args));

      //   return false;// for now, to debug

      // }
(015) // single word, invalid, and not next column... preserve and set?
      // unable to parse anything, or, test for single word -- either way, attempt preserve and set
        // $this->setState("single_word_preserve_error","No Spaces, need Mutation, Preserve and Set?", array(
        //   "where"   =>"single_word_preserve_error",
        //   "column"  => $column,
        //   "org_val" => $org_val,
        //   "value"   => $value,
        //   "parsed"  => $parsed,
        //   "args"    => $args
        // ));
      // should return true on modification, check to preserve and set. false if no mod possible, probably error state (should not be like above)
(016) // if(!empty($results)) {
      //   // self::diedump(array(
      //   //  'where'=>__FUNCTION__."::".__LINE__,
      //   //  '$value'=>$value,
      //   //  '$results'=>$results,
      //   //  '$test_params'=>$test_params,
      //   //  '$this->state'=>$this->state
      //   // ));
      // }
(017) // This query gets a count of sku_types:
      // $query = "SELECT DISTINCT SUBSTRING(variant_sku,4,2) as type_code, COUNT(variant_sku) as type_count, " .
      //      "(SELECT description FROM sku_standard WHERE sku_standard.sku_code = type_code) as type_desc FROM org_export GROUP BY type_code";
