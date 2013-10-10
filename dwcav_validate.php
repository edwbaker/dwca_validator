#!/usr/bin/php
<?php
$namespaces = array(
  'dwcav',
);
$includes = dwcav_load_plugins();
foreach ($includes as $include) {
  require_once($include);
}


/*
 * DarwinCore-Archive validator
 * ============================
 * 
 * Tool for validating the structure and content of DwC-A files
 * 
 * 
 */

//Variables we will use throughout
$errors = array();
$identifiers = array();


//Takes path to archive as a single argument from the command line
global $argv;
$archive_path = $argv[1];

//Open and extract the archive
$dir = dwcav_extract(dwcav_openfile($archive_path), $archive_path);

//Get a list of extracted files
$dir_list = scandir($dir);

//Start validation of archive
$ok_files = dwcav_archive($dir, $dir_list);
$core_file = dwcav_meta_xml($dir, $ok_files);

if (!$core_file) {
  dwcav_error('fatal', 'meta.xml', "No core file defined in meta.xml");
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
  //We need to be able to parse the meta file
  $meta = file_get_contents("$dir/meta.xml");
  $meta_xml = new SimpleXMLElement($meta);
  
  //Create a list of files defined by the meta.xml for comparison to actual files present in archive
  $present_files = array();
  
  //Check for core file data - each archive must have a core file
  if(!isset($meta_xml->core)){
    dwcav_error('error', 'meta.xml', "No core defined in meta.xml - validation stopped");
    return FALSE;
  }
  if(!isset($meta_xml->core->files->location)){
    dwcav_error('error', 'meta.xml', "No core file is defined in the meta.xml - validation stopped");
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
  $meta_columns[$id_column] = 'core';
  foreach($meta_xml->core->field as $field){
    $field = (array)$field;
    $meta_columns[$field['@attributes']['index']] = $field['@attributes']['term'];
  }
  $identifiers['core'] = array( //TODO: change core to rowType
    'is_core' => TRUE,
    'file_path' => $dir . (string)$meta_xml->core->files->location,
    'file_name' => (string)$meta_xml->core->files->location,
    'field_terminated' => (string)$meta_xml->core->attributes()->fieldsTerminatedBy,
    'field_enclosed' => (string)$meta_xml->core->attributes()->fieldsEnclosedBy,
    'num_columns' => count($meta_columns),
    'core_id_column' => $id_column,
    'columns' => $meta_columns,
    'ids' => array()
  );
  
  //Validate attributes of extensions
  foreach($meta_xml->extension as $extension){
    dwcav_xml_attributes($extension->attributes(), $extension->files->location);
    if(!isset($extension->files->location)){
      dwcav_error('error', 'meta.xml', "No file is defined for $extension->attributes()->rowType");
      continue;
    } else {
      $present_files[] = $extension->files->location;
    }
    if(!in_array($extension->files->location, $ok_files)){
      dwcav_error('error', 'meta.xml', "The " . $extension->files->location . " file is not present in the archive"); 
    }
  }
  
  //Populate $identifiers array with data on the extension files
  foreach ($meta_xml->extension as $extension) {
    //Which column (if any) links to the core file
  	$core_id_column = (array)$extension->coreid->attributes();
    $core_id_column = $core_id_column['@attributes']['index'];
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
  	  'num_columns' => count($meta_columns),
  	  'core_id_column' => $core_id_column,
  	  'id_column' => $id_column,
  	  'columns' => $meta_columns,
  	  'ids' => array(),
  	);
  }
  
  //Iterate over each row in every rowType to validate identifiers
  foreach($identifiers as $rowType => &$rowProperties){
    $row = 1; //used to provide a row number to the user
    $handle = fopen($rowProperties['file_path'], "r");
    if($handle !== FALSE){
      while(($data = fgetcsv($handle, 0, $rowProperties['field_terminated'], $rowProperties['field_enclosed'])) !== FALSE){
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
            dwcav_error('error', $rowProperties['file_name'], "row $row  has non unique identifier (same as row(s) $matched_rows)");
          } else{
            $rowProperites['ids'][] = $data[$rowProperties['core_id_column']];
            $rowProperties['ids'][$data[$rowProperties['core_id_column']]][] = $row;
          }
        } else {
          //If this file is not core, but is linked to the core, check the identifier given exists in core
          if (trim($data[$rowProperties['core_id_column']]) != "" && !array_key_exists($data[$rowProperties['core_id_column']], $identifiers['core']['ids'])) {
            //Some files are expected to break this rule, even if it is not strictly valid
          	if(!in_array($rowType, dwcav_exclusions_files_core_index())) {
          	  dwcav_error('error', $rowProperties['file_name'], "row $row - identifier not in core file");
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
            dwcav_error('info', $rowProperties['file_name'], "row $row  has non unique identifier (same as row(s) $matched_rows)");
          } else{
            $rowProperites['ids'][] = $data[$rowProperties['id_column']];
            $rowProperties['ids'][$data[$rowProperties['id_column']]][] = $row;
          
          }
        }
        $row++;
      }
    } else {
      dwcav_error('error', $rowProperties['file_name'], "Could not open file");
    }
    fclose($handle);
  }
  
  // So far we have built an $identifiers array with information on each rowType and its identifiers
  // - now we must validate the fields. Most checks only need the type of field and the value to check
  // but checks require access to multiple columns, let's get an array of these
  $multi_terms = dwcav_terms_info();
  
  //As we use rowTypes from the meta.xml here it is possible that we will parse a single file multiple times
  foreach($identifiers as $rowType => &$rowProperties){
    $handle = fopen($rowProperties['file_path'], "r");
    $row = 1;
    if($handle !== FALSE){
    	while(($data = fgetcsv($handle, 0, $rowProperties['field_terminated'], $rowProperties['field_enclosed'])) !== FALSE){
        foreach($rowProperties['columns'] as $index => $term){
          //Start with a generic check
          dwcav_term($rowProperties['file_name'], $row, $data[$index], $rowProperties['ids'], $term);
          
          //$safe_term turns a term URI into a string suitable as a name for a PHP function
          $safe_term = substr($term, 7);
          $safe_term = str_replace('.', '_', $safe_term);
          $safe_term = str_replace('/', '_', $safe_term);
          
          //Check whether any function provides further validation
          global $namespaces;
          foreach ($namespaces as $namespace) {
            $function = $namespace.'_term_' . $safe_term;
            if(function_exists($function)){
              $function($rowProperties['file_name'], $row, $data[$index], $rowProperties['ids'], $identifiers);
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
        $row++;
      }
    }else{
      dwcav_error('error', $rowProperties['file_name'], "Could not open file");
    }
    fclose($handle);
    

  }
  //are there files not listed in the meta file?
  foreach($ok_files as $ok_file){
    if(!in_array($ok_file, $present_files)){
      dwcav_error('error', 'Archive', "$ok_file is in the archive but not listed in the meta.xml");
    }
  }
  return $core_file;
}

function dwcav_term($file, $row, $value, $core_ids, $term){
  if(trim($value) == "" && $value != ""){
    dwcav_error('warning', $file, "row $row - $term has no data but contains unwanted whitespace");
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
      dwcav_error('error', 'meta.xml', "$check not defined in core attributes");
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
    dwcav_error('fatal', 'archive', 'meta.xml is not present');
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
      dwcav_error('error', 'archive', "$file is not a file");
      continue;
    }
    //Make sure it is not a directory
    if(is_dir($dir . '/' . $file)){
      dwcav_error('error', 'archive', "$file is a directory - zip should not contain directories");
      continue;
    }
    if(substr($file, -4) != '.txt'){
      dwcav_error('error', 'archive', "$file is not a text file or does not have .txt extension");
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
function dwcav_error($level, $section, $message){
  print "[$section] ($level): $message\n";
  global $errors;
  $errors[] = array(
    $level,
    $section,
    $message
  );
  if ($level == 'fatal') {
  	"The issue above prevents completion of the validation process.\n\n";
  	exit;
  }
}

/*
 * Wrapper around ZipArchive to open a zip file for extraction
 */
function dwcav_openfile($file_name){
  $zip = new ZipArchive();
  $is_open = $zip->open($file_name);
  if($is_open === TRUE){
    return $zip;
  }else{
    dwcav_error('fatal', 'archive', "Could not open the archive.");
  }
}

/*
 * Wrapper around ZipArchive to extract contents of archive
 */
function dwcav_extract(&$zip, $file_name){
  $tmp_path = sys_get_temp_dir() . '/dwca/';
  if ($zip->extractTo($tmp_path)) {
    dwcav_error('debug', 'archive', "Files extarcted to $tmp_path");
    $zip->close();
  } else {
  	dwcav_error('fatal', 'archive', "Could not extract files from archive");
  }
  //extractTo creates a new subfolder for files - return path to that subfolder
  $dir_listing = scandir($tmp_path);
  return $tmp_path . $dir_listing[2] . "/";
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

print "\n";