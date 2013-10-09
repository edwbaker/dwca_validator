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
  //print_r($meta_xml->core);
  
  //Check for core file data
  if (!isset($meta_xml->core)) {
  	dwcav_error('error', 'meta.xml', "No core defined in meta.xml");
  	return FALSE;
  }
  //Validate core attributes
  dwcav_xml_attributes($meta_xml->core->attributes(), $meta_xml->core->files->location);
  if (!isset($meta_xml->core->files->location)) {
  	dwcav_error('error', 'meta.xml', "No core file is defined in the meta.xml");
  	return FALSE;
  }
  //Loop over extensions and check
  foreach ($meta_xml->extension as $extension) {
    dwcav_xml_attributes($extension->attributes(), $extension->files->location);
    if (!isset($extension->files->location)) {
      dwcav_error('error', 'meta.xml', "No file is defined for $extension->attributes()->rowType");
      continue;
    }
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
  	if ($file == '.' /*|| $file = '..' || $file == 'meta.xml'*/) { //Removed for DEBUG
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
  }
  if($file_ok) {
  	$ok_files[] = $file;
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