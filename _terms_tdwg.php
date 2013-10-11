<?php
$namespaces[] = 'tdwg';

function tdwg_terms_info(){
  return array(
    'tdwg_terms_name_parent' => array(
      'http://rs.tdwg.org/dwc/terms/acceptedNameUsageID',
      'http://rs.tdwg.org/dwc/terms/parentNameUsageID',
    )
  );
}

function tdwg_terms_freetext() {
  return array(
    'http://rs.tdwg.org/dwc/terms/scientificName' => array('empty' => 'error'),
    'http://rs.tdwg.org/dwc/terms/scientificNameAuthorship' => array('empty' => 'info'),
    'http://rs.tdwg.org/dwc/terms/vernacularName' => array('empty' => 'error'),
    'http://rs.tdwg.org/dwc/terms/catalogNumber' => array('empty' => 'error'),
    'http://rs.tdwg.org/dwc/terms/taxonRemarks' => array('empty' => ''),
    'http://rs.tdwg.org/dwc/terms/namePublishedIn' => array('empty' => ''),
  );
}


function tdwg_terms_name_parent($file, $row, $params){
  if(isset($params['http://rs.tdwg.org/dwc/terms/acceptedNameUsageID']) && isset($params['http://rs.tdwg.org/dwc/terms/parentNameUsageID'])){
    if(trim($params['http://rs.tdwg.org/dwc/terms/acceptedNameUsageID']) == "" && trim($params['http://rs.tdwg.org/dwc/terms/parentNameUsageID']) == ""){
      dwcav_error('error', $file, "no parentNameUsageID or acceptedNameUsageID (requires one or other)", $row);
    }
    if(trim($params['http://rs.tdwg.org/dwc/terms/acceptedNameUsageID']) != "" && trim($params['http://rs.tdwg.org/dwc/terms/parentNameUsageID']) != ""){
      dwcav_error('error', $file, "has both parentNameUsageID and acceptedNameUsageID (requires one or other)", $row);
    }
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_taxonID($file, $rowType, $row, $value, &$core_ids){
  if (trim($value) == "") {
  	dwcav_error('info', $file, "no taxonID", $row);
  	return;
  }
}

function tdwg_term_rs_tdwg_org_ac_terms_furtherInformationURL($file, $rowType, $row, $value, &$core_ids){
  if (!filter_var($value, FILTER_VALIDATE_URL)) {
  	dwcav_error('error', $file, "$value is not a valid furtherInformationURL", $row);
  	return;
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_institutionCode($file, $rowType, $row, $value, &$core_ids){
  if(trim($value) == ""){return;}
  if(strlen((string)$value) > 5) {
  	dwcav_error('info', $file, "institutionCode longer than 5 - is this correct?", $row);
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_collectionCode($file, $rowType, $row, $value, &$core_ids){
  if(trim($value) == ""){return;}
  if(strlen((string)$value) > 5) {
  	dwcav_error('info', $file, "collectionCode longer than 5 - is this correct?", $row);
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_decimalLatitude($file, $rowType, $row, $value, &$core_ids){
  if(trim($value) == ""){return;}
  if (floatval($value) < - 90.0 || floatval($value) > 90.0) {
  	dwcav_error('error', $file, "decimalLatiude should be between -90 and 90", $row);
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_decimalLongitude($file, $rowType, $row, $value, &$core_ids){
  if(trim($value) == ""){return;}
  if (floatval($value) < - 180.0 || floatval($value) > 180.0) {
  	dwcav_error('error', $file, "decimalLongitude should be between -180 and 180", $row);
  }
}


function tdwg_term_rs_tdwg_org_dwc_terms_namePublishedInID($file, $rowType, $row, $value, &$core_ids){
  if(trim($value) == ""){return;}
  if(!filter_var($value, FILTER_VALIDATE_URL)){
    dwcav_error('error', $file, "namePublishedInID should be a valid URL", $row);
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_acceptedNameUsageID($file, $rowType, $row, $value, &$core_ids){
  if(trim($value) == ''){return;}
  if(!array_key_exists($value, $core_ids)){
    dwcav_error('error', $file, "acceptedNameUsageID ($value) does not exist.", $row);
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_typeStatus($file, $rowType, $row, $value, &$core_ids){
  if(trim($value) == ""){
    return;
  }
  $allowed_values = array(
    'Holotype',
    'Paratype',
    'Neotype',
    'Syntype',
    'Lectotype',
    'Paralectotype',
    'Hapantotype', 
  );
  if(!in_array($value, $allowed_values)){
    dwcav_error('error', $file, "unknown value ($value) for typeStatus", $row);
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_taxonomicStatus($file, $rowType, $row, $value, &$core_ids){
  if(trim($value) == ""){
    dwcav_error('warning', $file, "no taxonomicStatus", $row);
    return;
  }
  $allowed_values = array(
    'accepted',
    'synonym',
    'homotypicSynonym',
    'heterotypicSynonym',
    'proParteSynonym',
    'misapplied'
  );
  if(!in_array($value, $allowed_values)){
    dwcav_error('error', $file, "unknown value ($value) for taxonomicStatus", $row);
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_nomenclaturalStatus($file, $rowType, $row, $value, &$core_ids){
  $allowed_values = array(
    '',
    'illegitium',
    'superfluum',
    'reciciendum',
    'nudum',
    'invalidum',
    'orthographia'
  );
  if(!in_array($value, $allowed_values)){
    dwcav_error('error', $file, "unknown value ($value) for nomenclaturalStatus", $row);
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_taxonRank($file, $rowType, $row, $value, $core_ids){
  if(trim($value) == ""){
    dwcav_error('warning', $file, "no taxonRank", $row);
    return;
  }
  $allowed_values = array(
    'Order',
    'Family',
    'Subfamily',
    'Genus',
    'Subgenus',
    'Section',
    'Subsection', //?
    'Species',
    'Subspecies',
    'Variety',
    'Form',
    'Infraspecies' //?
  );
  if(!in_array($value, $allowed_values)){
    dwcav_error('error', $file, "unknown value ($value) for taxonRank", $row);
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_parentNameUsageID($file, $rowType, $row, $value, &$core_ids){
  if(trim($value) == ''){return;}
  if(!array_key_exists($value, $core_ids)){
    dwcav_error('error', $file, "parentNameUsageID ($value) does not exist.", $row);
  }
}
