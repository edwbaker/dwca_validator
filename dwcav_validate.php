#!/usr/bin/php
<?php
$namespaces = array(
  'dwcav',
);
$includes = dwcav_load_plugins();
foreach ($includes as $include) {
  require_once($include);
}

$verbose = FALSE; //Tells you all that's going on
$debug   = FALSE;  //Like verbose, but just stuff useful for checking on the validator

/*
 * DarwinCore-Archive validator
 * ============================
 * 
 * Tool for validating the structure and content of DwC-A files
 * 
 * 
 */

//Variables we will use throughout
$errors = array(
  'validator' => array(),
  'archive' => array(),
);
$identifiers = array();
$field_uris = array();
$freetext = dwcav_terms_freetext();
$print_info = FALSE;
$web = FALSE;
$multi_terms = array();


//Takes path to archive as a single argument from the command line
global $argv;
$archive_path = $argv[1];
if (isset($argv[2])) {
	if ($argv[2] == 'info') {
	  $print_info = TRUE;
	}
}

global $_GET;
if (isset($_GET['url'])) {
  $archive_path = $_GET['url'];
  $web = TRUE;
}

//Open and extract the archive
$dir = dwcav_extract(dwcav_openfile($archive_path), $archive_path);

//Get a list of extracted files
$dir_list = scandir($dir);

//Start validation of archive
$ok_files = dwcav_archive($dir, $dir_list);
$core_file = dwcav_meta_xml($dir, $ok_files);

if (!$core_file) {
  dwcav_error('fatal', 'meta.xml', "No core file defined in meta.xml", "");
}





//=======================================================================


function dwcav_load_plugins() {
  $files = scandir('.');
  $includes = array();
  foreach ($files as $file) {
  	if (substr($file, 0, 1) == '_' && substr($file, -4) == '.php') {
  	  $includes[] = $file;
  	}
  }
  return $includes;
}


/*
 * This function performs a validation of the meta.xml and also bootstraps the
 * $identifiers array by building a list of all the identifiers used in the archive.
 * This is essential for validating the linking between different files.
 */
function dwcav_meta_xml($dir, $ok_files){
  global $freetext;
  global $namespaces;
  //We need to be able to parse the meta file
  $meta = file_get_contents("$dir/meta.xml");
  $meta_xml = new SimpleXMLElement($meta);
  
  //Create a list of files defined by the meta.xml for comparison to actual files present in archive
  $present_files = array();
  
  //Check for core file data - each archive must have a core file
  if(!isset($meta_xml->core)){
    dwcav_error('error', 'meta.xml', "No core defined in meta.xml - validation stopped", "");
    return FALSE;
  }
  if(!isset($meta_xml->core->files->location)){
    dwcav_error('error', 'meta.xml', "No core file is defined in the meta.xml - validation stopped", "");
    return FALSE;
  }else{
    $present_files[] = $core_file = $meta_xml->core->files->location;
  }
  
  //Validate core attributes
  dwcav_xml_attributes($meta_xml->core->attributes(), $meta_xml->core->files->location);
  
  //Populate the $identifiers array with data on the core file
  $id_column = (array)$meta_xml->core->id->attributes(); //set to column number of identifier (unique)
  $id_column = $id_column['@attributes']['index'];
  $meta_columns = array();
  $id_term = (array)$meta_xml->core;
  $id_term = (array)$id_term['field'][0]->attributes()->term;
  $meta_columns[$id_column] = 'https://purl.org/dc/terms/identifier';//Peculiar to our archives
  
  foreach($meta_xml->core->field as $field){
    $field = (array)$field;
    $meta_columns[$field['@attributes']['index']] = $field['@attributes']['term'];
  }
  $identifiers[$meta_columns[$id_column]] = array( //TODO: change core to rowType (actually - we don't define this in meta)
    'is_core' => TRUE,
    'file_path' => $dir . (string)$meta_xml->core->files->location,
    'file_name' => (string)$meta_xml->core->files->location,
    'field_terminated' => (string)$meta_xml->core->attributes()->fieldsTerminatedBy,
    'field_enclosed' => (string)$meta_xml->core->attributes()->fieldsEnclosedBy,
    'header_rows' => (int)$meta_xml->core->attributes()->ignoreHeaderLines,
    'num_columns' => count($meta_columns),
    'core_id_column' => $id_column,
    'columns' => $meta_columns,
    'ids' => array()
  );
  
  //Validate attributes of extensions
  foreach($meta_xml->extension as $extension){
    dwcav_xml_attributes($extension->attributes(), $extension->files->location);
    if(!isset($extension->files->location)){
      dwcav_error('error', 'meta.xml', "No file is defined for $extension->attributes()->rowType", "");
      continue;
    } else {
      $present_files[] = $extension->files->location;
    }
    if(!in_array($extension->files->location, $ok_files)){
      dwcav_error('error', 'meta.xml', "The " . $extension->files->location . " file is not present in the archive". ""); 
    }
  }
  
  //Populate $identifiers array with data on the extension files
  foreach ($meta_xml->extension as $extension) {
    //Which column (if any) links to the core file
  	$core_id_column = (array)$extension->coreid->attributes();
  	if (isset($core_id_column['@attributes']['index'])) {
      $core_id_column = $core_id_column['@attributes']['index'];
  	} else {
  	  $core_id_column = NULL;
  	}
    $meta_columns = array();
    $id_column = FALSE;
    foreach($extension->field as $field){
      $field = (array)$field;
      $meta_columns[$field['@attributes']['index']] = $field['@attributes']['term'];
      if ($field['@attributes']['term'] == "http://purl.org/dc/terms/identifier") {
      	$id_column = $field['@attributes']['index'];
      }
    }
    $rowType = (string)$extension->attributes()->rowType;
  
  	$identifiers[$rowType] = array(
  	  'is_core' => FALSE,
  	  'file_path' => $dir . (string)$extension->files->location,
  	  'file_name' => (string)$extension->files->location,
  	  'field_terminated' => (string)$extension->attributes()->fieldsTerminatedBy,
  	  'field_enclosed' => (string)$extension->attributes()->fieldsEnclosedBy,
  	    'header_rows' => (int)$meta_xml->core->attributes()->ignoreHeaderLines,
  	  'num_columns' => count($meta_columns),
  	  'core_id_column' => $core_id_column,
  	  'id_column' => $id_column,
  	  'columns' => $meta_columns,
  	  'ids' => array(),
  	);
  }
  
  //Iterate over each row in every rowType to validate identifiers
  foreach($identifiers as $rowType => &$rowProperties){
    $row = 0; //used to provide a row number to the user
    $handle = fopen($rowProperties['file_path'], "r");
    if($handle !== FALSE){
    	if ($rowProperties['field_terminated'] == '\t') {
    	  $rowProperties['field_terminated'] = "\t";
    	}
      while(($data = fgetcsv($handle, 0, $rowProperties['field_terminated']/*, $rowProperties['field_enclosed']*/)) !== FALSE){
      	$row++;
      	if ($row <= $identifiers[$rowType]['header_rows']) {
      		continue;
      	}
        //The core file has slightly different requirements
        if ($rowProperties['is_core'] === TRUE) {
          //Check for duplicate core identifiers     	       
          if(array_key_exists($data[$rowProperties['core_id_column']], $rowProperties['ids'])){
            $matched_rows = '';
            $i = 0;
            foreach($rowProperties['ids'][$data[$rowProperties['core_id_column']]] as $same_id){
              if($i != 0){
                $matched_rows .= ', ';
              }
              $matched_rows .= $same_id;
            }
            $i++;
            dwcav_error('error', $rowProperties['file_name'], "row $row  has non unique identifier (same as row(s) $matched_rows)", "");
          } else{
            $rowProperites['ids'][] = $data[$rowProperties['core_id_column']];
            $rowProperties['ids'][$data[$rowProperties['core_id_column']]][] = $row;
          }
        } else {
          //If this file is not core, but is linked to the core, check the identifier given exists in core
          $core_type ='';
          foreach ($identifiers as $id => $stuff) {
          	if ($stuff['is_core']) {
          	  $core_type = $id;
          	  break;
          	}
          }
          if (!is_null($rowProperties['core_id_column'])) {
          if (trim($data[$rowProperties['core_id_column']]) != "" && !array_key_exists($data[$rowProperties['core_id_column']], $identifiers[$core_type]['ids'])) {
            //Some files are expected to break this rule, even if it is not strictly valid
          	if(!in_array($rowType, dwcav_exclusions_files_core_index())) {
          	  dwcav_error('error', $rowProperties['file_name'], "identifier not in core file", $row);
          	}
          }
          }
          //Duplicate IDs in extensions isn't so bad - but give an info just in case
          if(array_key_exists($data[$rowProperties['id_column']], $rowProperties['ids'])){
            $matched_rows = '';
            $i = 0;
            foreach($rowProperties['ids'][$data[$rowProperties['id_column']]] as $same_id){
              if($i != 0){
                $matched_rows .= ', ';
              }
              $matched_rows .= $same_id;
            }
            $i++;
            dwcav_error('info', $rowProperties['file_name'], "row $row  has non unique identifier (same as row(s) $matched_rows)", "");
          } else{
            $rowProperites['ids'][] = $data[$rowProperties['id_column']];
            $rowProperties['ids'][$data[$rowProperties['id_column']]][] = $row;
          
          }
        }
      }
    } else {
      dwcav_error('error', $rowProperties['file_name'], "Could not open file", "");
    }
    fclose($handle);
  }
  
  // So far we have built an $identifiers array with information on each rowType and its identifiers
  // - now we must validate the fields. Most checks only need the type of field and the value to check
  // but checks require access to multiple columns, let's get an array of these
  $multi_terms = dwcav_terms_info();
  $free_text = dwcav_terms_freetext();
  
  //As we use rowTypes from the meta.xml here it is possible that we will parse a single file multiple times
  global $field_uris;
  dwcav_row_iterate($identifiers);

  //are there files not listed in the meta file?
  foreach($ok_files as $ok_file){
    if(!in_array($ok_file, $present_files)){
      dwcav_error('error', 'Archive', "$ok_file is in the archive but not listed in the meta.xml", "");
    }
  }
  
  //What have we validated?
  foreach ($field_uris as $field_uri => &$data) {
    foreach ($namespaces as $namespace) {
      $function = $namespace."_term_".dwcav_safe_term($field_uri);
      if (function_exists($function)) {
      	$field_uris[$field_uri] = array('standard' => $namespace);
      }
      if (array_key_exists($field_uri, $free_text)) {
      	$field_uris[$field_uri] = array('freetext' => '');
      }
    }
  }
  global $verbose;
  global $debug;
  $deprecated = dwcav_terms_deprecated();
  foreach ($field_uris as $field_uri => $data) {
  	if (in_array($field_uri, $deprecated)) {
  	  dwcav_error('info', 'archive', "$field_uri appears to be deprecated", "");
  	}
  }
  if ($verbose || $debug) {
  foreach ($field_uris as $field_uri => $data) {
  	$validated = FALSE;
      if (isset($data['standard'])) {
        if ($verbose) {
          print "$field_uri is validated by ".$data['standard']."\n";
        }
      	$validated = TRUE;
      }
      if (isset($data['freetext'])) {
        if ($verbose) {
          print "$field_uri is freetext so only has basic validation\n";
        }
          $validated = TRUE;
        }
      if (!$validated) {
        dwcav_error('info', 'validator', "$field_uri has no validation function available", "");
      }
    }
  }
  

  
  return $core_file;
}

function dwcav_row_iterate(&$identifiers) {
  foreach($identifiers as $rowType => &$rowProperties){
    $handle = fopen($rowProperties['file_path'], "r");
    $row = 0;
    if($handle !== FALSE){
      if ($rowProperties['field_terminated'] == "\t" ) {
      	dwcav_row_iterate_tsv($handle, $rowType, $rowProperties, $row, $identifiers);
      } else {
        dwcav_row_iterate_csv($handle, $rowType, $rowProperties, $row, $identifiers);
      }
    }else{
      dwcav_error('error', $rowProperties['file_name'], "Could not open file", "");
    }
    fclose($handle);
  }
}

function dwcav_row_iterate_tsv(&$handle, $rowType, &$rowProperties, $row, &$identifiers) {
    	while(($data = fgetcsv($handle, 0, $rowProperties['field_terminated'])) !== FALSE){
		  dwcav_row_iterate_do($data, $rowType, $rowProperties, $row, $identifiers);
      }
}

function dwcav_row_iterate_csv(&$handle, $rowType, &$rowProperties, $row, &$identifiers) {
    	while(($data = fgetcsv($handle, 0, $rowProperties['field_terminated'], $rowProperties['field_enclosed'])) !== FALSE){
		  dwcav_row_iterate_do($data, $rowType, $rowProperties, $row, $identifiers);
      }
}

function dwcav_row_iterate_do(&$data, $rowType, &$rowProperties, &$row, &$identifiers) {
  $row++;
  global $multi_terms;
  global $field_uris;
  if ($row <= $identifiers[$rowType]['header_rows']) {
  	return;
  }
        foreach($rowProperties['columns'] as $index => $term){
          if (!array_key_exists($term, $field_uris)) {
          	$field_uris[$term] = array();
          }
          //Start with a generic check - this also performs validation on freetext
          //print $data[$index]."\n";
          dwcav_term($rowProperties['file_name'], $rowType, $row, $data[$index], $rowProperties['ids'], $term);
          
          //$safe_term turns a term URI into a string suitable as a name for a PHP function
          $safe_term = dwcav_safe_term($term);
          
          //Check whether any function provides further validation
          global $namespaces;
          foreach ($namespaces as $namespace) {
            $function = $namespace.'_term_' . $safe_term;
            if(function_exists($function)){
              $function($rowProperties['file_name'], $rowType, $row, $data[$index], $rowProperties['ids'], $identifiers);
            }
          }
          
          
          //check for multi-field checks
          foreach($multi_terms as $function => $terms){
            $params = array();
            //Only check the first term - no need to run this multiple times
            if($terms[0] == $term){
              foreach($terms as $m_term){
                foreach($rowProperties['columns'] as $meta_index => $meta_term){
                  if($m_term == $meta_term){
                    $params[$m_term] = $data[$meta_index];
                  }
                }
              }
            }
            //check we have all the data we need, then run the check
            if(sizeof($terms) == sizeof($params)){
              $function($rowProperties['file_name'], $row, $params);
            }
          }
        }
}

function dwcav_term($file, $rowType, $row, $value, $core_ids, $term){
  if(trim($value) == "" && $value != ""){
    dwcav_error('warning', $file, "$term has no data but contains unwanted whitespace", $row);
  }
  global $freetext;
  if (trim($value) == "" && array_key_exists($term, $freetext)){
  	$level = $freetext[$term]['empty'];
  	if ($level != "") {
  	  dwcav_error($level, $file, "$term is empty", $row);
  	}
  }
}




/*
 * The @attributes of a core or extension in the meta.xml are checked for presence and conformance
 * 
 * $attributes
 * $file is a file name passed to aid the user in locating the error
 */
function dwcav_xml_attributes($attributes, $file){
  $array = (array)$attributes;
  $array = $array['@attributes'];
  
  //Required attributes
  $checks = array(
    'encoding',
    'linesTerminatedBy',
    'fieldsTerminatedBy',
    'fieldsEnclosedBy',
    'ignoreHeaderLines',
    'rowType'
  );
  foreach($checks as $check){
    if(!isset($array[$check])){
      dwcav_error('error', 'meta.xml', "$check not defined in core attributes", "");
    }else{
      //The attribute is present, we can proceed to validate it
      $function = 'dwcav_xml_attributes_' . $check;
      if(function_exists($function)){
        $function($array[$check], $file);
      }
    }
  }
}

/*
 * Function to perform some basic checks on the integrity of the archive
 * 
 * $dir is the path to the extracted filed
 * $dir_list is a list of files to check
 */
function dwcav_archive($dir, $dir_list){ 
  //Verify that a meta.xml is present, if not it is impossible to verify the file
  if(!in_array('meta.xml', $dir_list)){
    dwcav_error('fatal', 'archive', "meta.xml is not present at $dir", "");
  }
  
  //Generate a list of files tthat should be checked for DwC-A compliance against meta.xml
  $ok_files = array(); 
  foreach($dir_list as $file){
    if($file == '.' || $file == '..' || $file == 'meta.xml'){
      $file_ok = FALSE;
      continue;
    }
    //Check is a normal file
    if(!is_file($dir . '/' . $file)){
      dwcav_error('error', 'archive', "$file is not a file", "");
      continue;
    }
    //Make sure it is not a directory
    if(is_dir($dir . '/' . $file)){
      dwcav_error('error', 'archive', "$file is a directory - zip should not contain directories", "");
      continue;
    }
    if(substr($file, -4) != '.txt'){
      dwcav_error('error', 'archive', "$file is not a text file or does not have .txt extension", "");
      continue;
    }
    $ok_files[] = $file;
  }
  return $ok_files;
}

/*
 * Handle any problems that we discover with the archive during validation
 * 
 * $level is one of fatal, error, warning, info [or debug]
 * $section indicates what file in the archive the error occurs with (or wehtehr the issue is with the archive itself)
 * $message is for a description of the error for the user
 */
function dwcav_error($level, $section, $message, $row=''){
  //print "[$section] ($level): $message\n";
  global $errors;
    if (isset($errors[$section][$message]['rows'])) {
      $errors[$section][$message]['rows'] .= ", $row";
    } else {
      $errors[$section][$message]['rows'] = $row;
    }
    $errors[$section][$message]['level'] = $level;
  
  if ($level == 'fatal') {
  	dwcav_error_print();
  	print "The issue above prevents completion of the validation process.\n\n";
  	exit;
  }
}

function dwcav_error_print() {
  global $errors;
  global $print_info;
  global $web;
  if (!$web){
    print "\n\n\n";
  }
  $section_length = 0;
  foreach($errors as $section => $data){
  	if (strlen($section) > $section_length){
  	  $section_length = strlen($section);
  	}
  }
  
  foreach ($errors as $section => $section_data){
  	foreach ($section_data as $message => $data) {
  	 if ($data['level'] == 'info' && !$print_info) {
  	 	continue;
  	 }
  	 $label = str_pad($section, $section_length+2, " ", STR_PAD_BOTH);
  	 $level = str_pad($data['level'], strlen('warning')+2, " ", STR_PAD_BOTH);
  	 print "[$label] ($level) $message ";
  	 if ($data['rows'] != "") {
  	 	print "(rows: ";
  	 	print $data['rows'];
  	 	print ")";
  	 }
  	 $line_ending = ($web ? "<br/>" : "\n");
  	  print $line_ending;
  	}
  	
  }
}

/*
 * Wrapper around ZipArchive to open a zip file for extraction
 */
function dwcav_openfile($file_name){
  if (filter_var($file_name, FILTER_VALIDATE_URL)){
  	$tmp_path = sys_get_temp_dir() . '/'.time().'.zip';
  	$handle = fopen($tmp_path, "w");
  	$options = array(
      CURLOPT_FILE    => $handle,
      CURLOPT_TIMEOUT =>  28800, // set this to 8 hours so we dont timeout on big files
      CURLOPT_URL     => $file_name,
    );
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    curl_exec($ch);
    fclose($handle);
    $file_name = $tmp_path;
  }
  
  	  print "$file_name\n";
	
  $zip = new ZipArchive();
  $is_open = $zip->open($file_name);
  if($is_open === TRUE){
    return $zip;
  }else{
    dwcav_error('fatal', 'archive', "Could not open the archive.", "");
  }
}

/*
 * Wrapper around ZipArchive to extract contents of archive
 */
function dwcav_extract(&$zip, $file_name){
  $tmp_path = sys_get_temp_dir() . '/dwcav/'.time().'/';
  array_map('unlink', glob("$tmp_path*"));
  
  if ($zip->extractTo($tmp_path)) {
    dwcav_error('debug', 'archive', "Files extracted to $tmp_path", "");
    $zip->close();
  } else {
  	dwcav_error('fatal', 'archive', "Could not extract files from archive", "");
  }
  //extractTo creates a new subfolder for files - return path to that subfolder
  $dir_listing = scandir($tmp_path);
  $i=0;
  $ignore = array('.', '_');
  foreach ($dir_listing as $try) {
  	if (!in_array(substr($try, 0, 1), $ignore)){
  	  return $tmp_path.$dir_listing[$i].'/';
  	}
  	$i++;
  }
  return FALSE;
}

/*
 * Helper function to get list of all freetext fields
 */
function dwcav_terms_freetext() {
  global $namespaces;
  $return = array();
  foreach ($namespaces as $namespace) {
  	if ($namespace == 'dwcav') {
  	  continue;
  	}
  	$function = $namespace."_terms_freetext";
  	if (function_exists($function)) {
  	  $return = array_merge($return, $function());
  	}
  }
  return $return;
}

/*
 * Helper function to get list of all files that do not require a link to the core file
 */
function dwcav_exclusions_files_core_index() {
  global $namespaces;
  $return = array();
  foreach ($namespaces as $namespace) {
  	if ($namespace == 'dwcav') {
  	  continue;
  	}
  	$function = $namespace."_exclusions_files_core_index";
  	if (function_exists($function)) {
  	  $return = array_merge($return, $function());
  	}
  }
  return $return;
}

/*
 * Helper function to get list of all validations trhat require 2 or more fields
 */
function dwcav_terms_info() {
  global $namespaces;
  $return = array();
  foreach ($namespaces as $namespace) {
  	if ($namespace == 'dwcav') {
  	  continue;
  	}
  	$function = $namespace."_terms_info";
  	if (function_exists($function)) {
  	  $return = array_merge($return, $function());
  	}
  }
  return $return;
}

function dwcav_terms_deprecated() {
  global $namespaces;
  $return = array();
  foreach ($namespaces as $namespace) {
  	if ($namespace == 'dwcav') {
  	  continue;
  	}
  	$function = $namespace."_terms_deprecated";
  	if (function_exists($function)) {
  	  $return = array_merge($return, $function());
  	}
  }
  return $return;
}

function dwcav_safe_term($term) {
  $safe_term = substr((string)$term, 7);
  $safe_term = str_replace('.', '_', $safe_term);
  $safe_term = str_replace('/', '_', $safe_term);
  $safe_term = str_replace('#', '_', $safe_term);
  return $safe_term;
}



dwcav_error_print();

print "\n";