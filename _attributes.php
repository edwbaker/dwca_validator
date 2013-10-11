<?php

function dwcav_xml_attributes_ignoreHEaderLines($check, $file){
  if(!is_int((int)$check)){
    dwcav_error('error', 'meta.xml', "ignoreHeaderLines is not an integer for $file in meta.xml", "");
    return;
  }
  if($check > 1){
    dwcav_error('info', 'meta.xml', "ignoreHEaderLines seems rather large, are you sure it's correct?", "");
  }
}

function dwcav_xml_attributes_rowType($check, $file){
  if(!filter_var($check, FILTER_VALIDATE_URL)){
    dwcav_error('error', 'meta.xml', "rowType for $file in meta.xml is not a valid URL", "");
  }
}

function dwcav_xml_attributes_linesTerminatedBy($check, $file){
  $normal_values = array(
    '\r\n',
    '\r',
    '\n'
  );
  if(!in_array($check, $normal_values)){
    dwcav_error('info', 'meta.xml', "Non-standard value for linesTerminatedBy in $file", "");
  }
}

function dwcav_xml_attributes_fieldsEnclosedBy($check, $file){
  $normal_values = array(
    "",
    "'",
    '"'
  );
  if(!in_array($check, $normal_values)){
    dwcav_error('info', 'meta.xml', "Non-standard value for fieldsEnclosedBy in $file", "");
  }
}

function dwcav_xml_attributes_fieldsTerminatedBy($check, $file){
  $normal_values = array(
    "\t",
    ","
  );
  if(!in_array($check, $normal_values)){
    dwcav_error('info', 'meta.xml', "Non-standard value for fieldsTerminatedBy in $file", "");
  }
}