<?php
$namespaces[] = 'purl';

function purl_terms_freetext() {
  return array(
    'http://purl.org/dc/terms/bibliographicCitation' => array('empty' => 'error'),
    'http://purl.org/dc/terms/title'  => array('empty' => 'error'),
    'https://purl.org/dc/terms/identifier' => array('empty' => 'error'),  //TODO: https:// check
    'http://purl.org/dc/terms/identifier' => array('empty' => 'error'),
    'http://purl.org/dc/terms/description' => array('empty' => 'error'),
    'http://purl.org/dc/terms/creator' => array('empty' => ''),
    'http://purl.org/dc/terms/subject' => array('empty' => ''),
    'http://purl.org/dc/terms/date' => array('empty' => ''),
  );
}

function purl_term_purl_org_dc_terms_type($file, $rowType, $row, $value, &$core_ids) {
  //TODO: check this is in an appropriate allowed values list
}

function purl_term_purl_org_dc_terms_format($file, $rowType, $row, $value, &$core_ids) {
  $patterns = array(
    '#^[-\w]+/[-\w]+$#',
  );
  $match = FALSE;
  foreach ($patterns as $pattern) {
  	if (preg_match($pattern, $value)) {
  	  $match = TRUE;
  	  break;
  	}
  }
  if (!$match) {
  	dwcav_error('warning', $file, "$value is not in a known format (MIME)", $row);
  }
}

function purl_term_purl_org_dc_terms_modified($file, $rowType, $row, $value, &$core_ids) {
  if(trim($value) == ""){return;}
  if (!strtotime($value)) {
  	dwcav_error('info', $file, "PHP could not convert modified to a timestamp. Is it ok?", $row);
  }
}

function purl_term_purl_org_dc_terms_created($file, $rowType, $row, $value, &$core_ids) {
  if(trim($value) == ""){return;}
  if (!strtotime($value)) {
  	dwcav_error('info', $file, "PHP could not convert created to a timestamp. Is it ok?", $row);
  }
}

function purl_term_purl_org_dc_terms_language($file, $rowType, $row, $value, &$core_ids) {
  if(trim($value) == ""){return;}
  //TODO: Populate this list properly
  $allowed_values = array(
    'en',
    'eng',
  );
  if (!in_array($value, $allowed_values)){
  	dwcav_error('error', $file, "$value is not a valid language code", $row);
  }
}

function purl_term_purl_org_ontology_bibo_issn($file, $rowType, $row, $value, &$core_ids) {
  if(trim($value) == ""){return;}
  $string = $value;
  $value = explode(':', $value);
  $patterns = array(
    '/[0-9]{4}-[0-9]{3}[0-9x]/i',
  );
  $match = FALSE;
  foreach ($patterns as $pattern) {
  	if (preg_match($pattern, $value[2])) {
  	  $match = TRUE;
  	  break;
  	}
  }
  if ($value[0] != "urn" || $value[1] != "ISSN"){
  	$match = FALSE;
  }
  if (!$match) {
  	dwcav_error('error', $file, "$string is not a valid ISSN of the form urn:ISSN:xxxx-xxxx", $row);
  }
}

function purl_term_purl_org_ontology_bibo_isbn($file, $rowType, $row, $value, &$core_ids) {
  if(trim($value) == ""){return;}
  $string = $value;
  $value = explode(':', $value);
  $value[2] = str_replace(" ", "", $value[2]);
  $value[2] = str_replace("-", "", $value[2]);
  $patterns = array(
    '/^[0-9]{9}[0-9X]$/i',
    '/^[0-9]{12}[0-9X]$/i',
  );
  $match = FALSE;
  foreach ($patterns as $pattern) {
  	if (preg_match($pattern, $value[2])) {
  	  $match = TRUE;
  	  break;
  	}
  }
  if ($value[0] != "urn" || $value[1] != "ISBN"){
  	$match = FALSE;
  }
  if (!$match) {
  	dwcav_error('error', $file, "$string is not a valid ISBN of the form urn:ISBN:[number]", $row);
  }
}

function purl_term_purl_org_dc_terms_rights($file, $rowType, $row, $value, $core_ids) {
 if (filter_var($value, FILTER_VALIDATE_URL)) {
  	dwcav_error('info', $file, "URL in rights field - should this be in the licence field?", $row);
  	return;
  }
}

function purl_term_purl_org_dc_terms_source($file, $rowType, $row, $value, $core_ids) {
 if (!filter_var($value, FILTER_VALIDATE_URL)) {
  	dwcav_error('info', $file, "source should be a URL", $row);
  	return;
  }
}


function purl_term_purl_org_ontology_bibo_doi($file, $rowType, $row, $value, &$core_ids) {
  if(trim($value) == ""){return;}
  //See: http://stackoverflow.com/questions/27910/finding-a-doi-in-a-document-or-page
  $patterns = array(
    '/\b(10[.][0-9]{4,}(?:[.][0-9]+)*\/(?:(?!["&\'])\S)+)\b/',
    '/\b(10[.][0-9]{4,}(?:[.][0-9]+)*\/(?:(?!["&\'])[[:graph:]])+)\b/',
  );
  $match = FALSE;
  foreach ($patterns as $pattern) {
  	if (preg_match($pattern, $value)) {
  	  $match = TRUE;
  	  break;
  	}
  }
  if (!$match) {
  	dwcav_error('error', $file, "$value is not a valid DOI", $row);
  }
}

function purl_term_purl_org_dc_terms_license($file, $rowType, $row, $value, &$core_ids) {
  if(trim($value) == ""){return;}
  //TODO: Populate this list properly
  $expected_values = array(
    'http://creativecommons.org/licenses/by-nc/3.0/',
    'http://creativecommons.org/licenses/by-sa/3.0/', 
    'http://creativecommons.org/licenses/by/3.0/',
    'http://creativecommons.org/licenses/by-nc-sa/3.0/',
  );
  if (!filter_var($value, FILTER_VALIDATE_URL)) {
  	dwcav_error('error', $file, "$value is not a valid licence URL", $row);
  	return;
  }
  if (!in_array($value, $expected_values)){
  	dwcav_error('info', $file, "$value is not a licence I know about", $row);
  }
}

