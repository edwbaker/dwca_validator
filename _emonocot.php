<?php
$namespaces[] = 'emonocot';

function emonocot_terms_required() {
  return array(
    'rowtype' => array(
      'term',
    ),
  );
}

function emonocot_exclusions_files_core_index() {
	return array(
	  'http://www.w3.org/ns/oa#Annotationt',
	  'http://www.w3.org/ns/oa#hasBody',
	);
}

function emonocot_term_www_w3_org_ns_oa_hasTarget($file, $rowType, $row, $value, &$core_ids) {
 if(trim($value) == "") {
 	dwcav_error('error', $file, "hasTarget cannot be empty", $row);
 	return;
 }
  if (!filter_var($value, FILTER_VALIDATE_URL)) {
  	dwcav_error('error', $file, "hasTarget should be a URL", $row);
  }
}

function emonocot_term_www_w3_org_ns_oa_hasBody($file, $rowType, $row, $value, &$core_ids) {
 if(trim($value) == "") {
 	dwcav_error('error', $file, "hasBody cannot be empty", $row);
 	return;
 }
}
