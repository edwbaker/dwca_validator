<?php
$namespaces[] = 'purl';

function purl_term_purl_org_dc_terms_language($file, $row, $value, &$core_ids) {
  if(trim($value) == ""){return;}
  //TODO: Populate this list properly
  $allowed_values = array(
    'en',
    'eng',
  );
  if (!in_array($value, $allowed_values)){
  	dwcav_error('error', $file, "row $row - $value is not a valid language code");
  }
}

function purl_term_purl_org_ontology_bibo_issn($file, $row, $value, &$core_ids) {
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
  	dwcav_error('error', $file, "row $row - $string is not a valid ISSN of the form urn:ISSN:xxxx-xxxx");
  }
}

function purl_term_purl_org_ontology_bibo_isbn($file, $row, $value, &$core_ids) {
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
  	dwcav_error('error', $file, "row $row - $string is not a valid ISBN of the form urn:ISBN:[number]");
  }
}


function purl_term_purl_org_ontology_bibo_doi($file, $row, $value, &$core_ids) {
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
  	dwcav_error('error', $file, "row $row - $value is not a valid DOI");
  }
}

function purl_term_purl_org_dc_terms_license($file, $row, $value, &$core_ids) {
  if(trim($value) == ""){return;}
  //TODO: Populate this list properly
  $expected_values = array(
    'http://creativecommons.org/licenses/by-nc/3.0/',
    'http://creativecommons.org/licenses/by-sa/3.0/', 
    'http://creativecommons.org/licenses/by/3.0/',
    'http://creativecommons.org/licenses/by-nc-sa/3.0/',
  );
  if (!filter_var($value, FILTER_VALIDATE_URL)) {
  	dwcav_error('error', $file, "row $row - $value is not a valid licence URL");
  	return;
  }
  if (!in_array($value, $expected_values)){
  	dwcav_error('info', $file, "row $row - $value is not a licence I know about");
  }
}

