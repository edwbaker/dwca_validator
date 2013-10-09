#!/usr/bin/php
<?php

print "DwC-A Validator by Ed Baker\n";

//Get file
global $argv;
$file_name = $argv[1];

$zip = dwcav_openfile($file_name);
$dir = dwcav_extract($zip, $file_name);
$dir_list = scandir($dir);

//Start validation of archive
$ok_files  = dwcav_archive($dir, $dir_list);
$core_file = dwcav_meta_xml($dir, $ok_files);

$errors = array();

//Delve into the meta.xml
function dwcav_meta_xml($dir, $ok_files) {
  $meta = file_get_contents("$dir/meta.xml");
  $meta_xml  = new SimpleXMLElement($meta);
  $present_files = array();
  //print_r($meta_xml->core);
  
  //Check for core file data
  if (!isset($meta_xml->core)) {
  	dwcav_error('error', 'meta.xml', "No core defined in meta.xml - validation stopped");
  	return FALSE;
  }
  if (!isset($meta_xml->core->files->location)) {
  	dwcav_error('error', 'meta.xml', "No core file is defined in the meta.xml - validation stopped");
  	return FALSE;
  } else {
    $present_files[] = $core_file = $meta_xml->core->files->location;
  }
  //Validate core attributes
  dwcav_xml_attributes($meta_xml->core->attributes(), $meta_xml->core->files->location);
  //print_r($meta_xml->core);exit;
  
  $id_column = (array)$meta_xml->core->id->attributes();  //set to column number of identifier (unique)
  $id_column = $id_column['@attributes']['index'];
  $meta_columns = array();
  $meta_columns[$id_column] = 'core';
  foreach ($meta_xml->core->field as $field) {
    $field = (array)$field;
    $meta_columns[$field['@attributes']['index']] = $field['@attributes']['term'];
  }
  $file_columns = count($meta_columns);
 
  
  //Time to dig into the core file itself
  $file_path = $dir.$meta_xml->core->files->location;
  $row = 1;
  $handle = fopen($file_path, "r");
  $identifiers = array();
  if ($handle !== FALSE) {
    while (($data = fgetcsv($handle, 0, $meta_xml->core->attributes()->fieldsTerminatedBy, $meta_xml->core->attributes()->fieldsEnclosedBy)) !== FALSE) {
      $columns = count($data);
      if ($columns != $file_columns) {
      	dwcav_error('error', $meta_xml->core->files->location, "Row $row has $columns columns, $file_columns expected");
      }
      //Checking for unique identifiers
      if (in_array($data[$id_column], $identifiers)) {
      	$matched_rows = '';
      	$i=0;
      	foreach($identifiers[$data[$id_column]] as $same_id) {
      	  if ($i != 0) {
      	   $matched_rows .= ', ';
      	  }
      	  $matched_rows .= $same_id;
      	}
        dwcav_error('error', $meta_xml->core->files->location, "row $row  has non unique identifier (same as row(s) $matched_rows)");
      } else {
      	$identifiers[] = $data[$id_column]; 
      	$identifiers[$data[$id_column]][] = $row;;
      }
      $row++;
    }
  } else {
    dwcav_error('error', $meta_xml->core->files->location, "Could not open file");
  }
  fclose($handle);
  
  //Now we have all identifiers we need to through again TODO: (add other files into a loop here)
  $multi_terms = dwcav_terms_info();
  $handle = fopen($file_path, "r");
  $row = 1;
  if ($handle !== FALSE) {
    while (($data = fgetcsv($handle, 0, $meta_xml->core->attributes()->fieldsTerminatedBy, $meta_xml->core->attributes()->fieldsEnclosedBy)) !== FALSE) {
      foreach ($meta_columns as $index => $term ) {
        $safe_term = substr($term, 7);
        $safe_term = str_replace('.', '_', $safe_term);
        $safe_term = str_replace('/', '_', $safe_term);
        dwcav_term($meta_xml->core->files->location, $row, $data[$index], $identifiers, $term);
        $function = 'dwcav_term_'.$safe_term;
        if (function_exists($function)) {
          $function($meta_xml->core->files->location, $row, $data[$index], $identifiers);	
        }
        //check for multi-field checks
        foreach ($multi_terms as $function => $terms) {
          $params = array();
          if ($terms[0] == $term) {
            foreach ($terms as $m_term) {
              foreach ($meta_columns as $meta_index => $meta_term) {
                if ($m_term == $meta_term) {
                  $params[$m_term] = $data[$meta_index];
                }
              }
            }
          }
          if (sizeof($terms) == sizeof($params)) {
            $function($meta_xml->core->files->location, $row, $params);
          }
        }
      }
      $row++;
    }
  } else {
    dwcav_error('error', $meta_xml->core->files->location, "Could not open file");
  }
  fclose($handle);
  
  
  //Loop over extensions and check
  foreach ($meta_xml->extension as $extension) {
    dwcav_xml_attributes($extension->attributes(), $extension->files->location);
    if (!isset($extension->files->location)) {
      dwcav_error('error', 'meta.xml', "No file is defined for $extension->attributes()->rowType");
      continue;
    } else {
      $present_files[] = $extension->files->location;	    
    }
    if (!in_array($extension->files->location, $ok_files)) {
      dwcav_error('error', 'meta.xml', "The ".$extension->files->location." file is not present in the archive");
    }
  }
  //are there files not listed in the meta file?
  foreach ($ok_files as $ok_file) {
    if (!in_array($ok_file, $present_files)) {
      dwcav_error('error', 'Archive', "$ok_file is in the archive but not listed in the meta.xml");
    }
  }
  return $core_file;
}

function dwcav_term($file, $row, $value, $core_ids, $term) {
  if (trim($value) == "" && $value != "") {
    dwcav_error('warning', $file, "row $row - $term has no data but contains unwanted whitespace");	  
  }
}

function dwcav_terms_name_parent($file, $row, $params) {
  if (isset($params['http://rs.tdwg.org/dwc/terms/acceptedNameUsageID']) && isset($params['http://rs.tdwg.org/dwc/terms/parentNameUsageID'])) {
    if (trim($params['http://rs.tdwg.org/dwc/terms/acceptedNameUsageID']) == "" && trim($params['http://rs.tdwg.org/dwc/terms/parentNameUsageID']) == "") { 
      dwcav_error('error', $file, "row $row - no parentNameUsageID or acceptedNameUsageID (requires one or other)");	    
    }
    if (trim($params['http://rs.tdwg.org/dwc/terms/acceptedNameUsageID']) != "" && trim($params['http://rs.tdwg.org/dwc/terms/parentNameUsageID']) != "") { 
      dwcav_error('error', $file, "row $row - has both parentNameUsageID and acceptedNameUsageID (requires one or other)");	    
    }
  }
}

function dwcav_terms_info() {
  return array (
    'dwcav_terms_name_parent' => array(
      'http://rs.tdwg.org/dwc/terms/acceptedNameUsageID',
      'http://rs.tdwg.org/dwc/terms/parentNameUsageID',
      )
  );
}

function dwcav_term_rs_tdwg_org_dwc_terms_acceptedNameUsageID($file, $row, $value, $core_ids) {
  if (trim($value) == '') {
    return;
  }
  if (!in_array($value, $core_ids)) {
    dwcav_error('error', $file, "row $row acceptedNameUsageID ($value) does not exist.");
  }
}

function dwcav_term_rs_tdwg_org_dwc_terms_taxonomicStatus($file, $row, $value, $core_ids) {
  if (trim($value) == "") {
    dwcav_error('warning', $file, "row $row has no taxonomicStatus");	
    return;
  }
  $allowed_values = array(
    'accepted',
  );
  if (!in_array($value, $allowed_values)) {
    dwcav_error('error', $file, "row $row unknown value ($value) for taxonomicStatus");
  }
}

function dwcav_term_rs_tdwg_org_dwc_terms_taxonRank($file, $row, $value, $core_ids) {
  if (trim($value) == "") {
    dwcav_error('warning', $file, "row $row has no taxonRank");	  
    return;
  }
  $allowed_values = array(
    'Order',
    'Family',
    'Genus',
    'Species',
  );
  if (!in_array($value, $allowed_values)) {
    dwcav_error('error', $file, "row $row unknown value ($value) for taxonRank");
  }
}

function dwcav_term_rs_tdwg_org_dwc_terms_parentNameUsageID($file, $row, $value, $core_ids) {
  if (trim($value) == '') {
    return;
  }
  if (!in_array($value, $core_ids)) {
    dwcav_error('error', $file, "row $row parentNameUsageID ($value) does not exist.");
  }
}

function dwcav_xml_attributes($attributes, $file) {
  $array = (array) $attributes;
  $array = $array['@attributes'];
  $checks = array(
    'encoding',
    'linesTerminatedBy',
    'fieldsTerminatedBy',
    'fieldsEnclosedBy',
    'ignoreHeaderLines',
    'rowType',
  );
  
  foreach ($checks as $check) {
  	if (!isset($array[$check])) {
  	  dwcav_error('error', 'meta.xml', "$check not defined in core attributes");
  	} else {
  	$function = 'dwcav_xml_attributes_'.$check;
  	  if (function_exists($function)) {
  	  	//$array[$check] = 't'; //uncomment to give error for each attribute
  	  	$function($array[$check], $file);
  	  }
  	}
  }
}

function dwcav_xml_attributes_ignoreHEaderLines($check, $file) {
  if(!is_int((int)$check)) {
  	dwcav_error('error', 'meta.xml', "ignoreHeaderLines is not an integer for $file in meta.xml");
  	return;
  }
  if ($check > 1) {
  	dwcav_error('info', 'meta.xml', "ignoreHEaderLines seems rather large, are you sure it's correct?");
  }
}

function dwcav_xml_attributes_rowType($check, $file) {
  if (!filter_var($check, FILTER_VALIDATE_URL)) {
  	dwcav_error('error', 'meta.xml', "rowType for $file in meta.xml is not a valid URL");
  }	
}

function dwcav_xml_attributes_linesTerminatedBy($check, $file) {
  $normal_values = array(
    '\r\n',
    '\r',
    '\n',
  );
  if (!in_array($check, $normal_values)) {
  	dwcav_error('info', 'meta.xml', "Non-standard value for linesTerminatedBy in $file");
  }
}

function dwcav_xml_attributes_fieldsEnclosedBy($check, $file) {
  $normal_values = array(
    "",
    "'",
    '"',
  );
  if (!in_array($check, $normal_values)) {
  	dwcav_error('info', 'meta.xml', "Non-standard value for fieldsEnclosedBy in $file");
  }
}

function dwcav_xml_attributes_fieldsTerminatedBy($check, $file) {
  $normal_values = array(
    "\t",
    ",",
  );
  if (!in_array($check, $normal_values)) {
  	dwcav_error('info', 'meta.xml', "Non-standard value for fieldsTerminatedBy in $file");
  }
}

//Check overall archive is ok
function dwcav_archive($dir, $dir_list) {
  global $errors;
  $ok_files = array();
  //meta.xml is present;
  if (in_array('meta.xml', $dir_list)){
  	print "OK!\n";
  } else {
  	dwcav_error('error', 'archive', 'meta.xml is not present');
  }
  foreach ($dir_list as $file) {
  	$file_ok = TRUE;
  	if ($file == '.' || $file == '..' || $file == 'meta.xml') { 
  		$file_ok = FALSE;
  		continue;
  	}
  	//Check is a file
  	if (!is_file($dir.'/'.$file)) {
  	  $file_ok = FALSE;
  	  dwcav_error('error', 'archive', "$file is not a file");
  	}
  	if (is_dir($dir.'/'.$file)) {
  	  $file_ok = FALSE;
  	  dwcav_error('error', 'archive', "$file is a directory - zip should not contain directories");
  	}
  	if (substr($file, -4 ) != '.txt') {
  		$file_ok = FALSE;
  	  dwcav_error('error', 'archive', "$file is not a text file or does not have .txt extension");
  	}
  	if($file_ok) {
  	  $ok_files[] = $file;
        }
  }
  return $ok_files;
}

function dwcav_val_meta_xml() {
  global $errors;

  	
}

function dwcav_error($level, $section, $message) {
	print "[$section] ($level): $message\n";
	global $errors;
	$errors[] = array($level, $section, $message);
}

function dwcav_openfile($file_name) {
  print "Trying to open ".$file_name."...";
  $zip = new ZipArchive;
  $is_open = $zip->open($file_name);
  if ($is_open === TRUE) {
  	print "OK!\n";
  	return $zip;
  } else {
  	print "FAIL!\n";
  	exit;
  }
}

function dwcav_extract(&$zip, $file_name) {
  print "Extracting to ";
  $tmp_path = sys_get_temp_dir().'/dwca/';
  print $tmp_path."\n";
  $zip->extractTo($tmp_path);
  $zip->close();
  $dir_listing = scandir($tmp_path);
  return $tmp_path.$dir_listing[2]."/";
}

print "\n";