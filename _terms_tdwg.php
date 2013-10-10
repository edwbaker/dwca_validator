<?php
$namespaces[] = 'tdwg';

function tdwg_terms_info(){
  return array(
    'tdwg_terms_name_parent' => array(
      'http://rs.tdwg.org/dwc/terms/acceptedNameUsageID',
      'http://rs.tdwg.org/dwc/terms/parentNameUsageID'
    )
  );
}

function tdwg_terms_name_parent($file, $row, $params){
  if(isset($params['http://rs.tdwg.org/dwc/terms/acceptedNameUsageID']) && isset($params['http://rs.tdwg.org/dwc/terms/parentNameUsageID'])){
    if(trim($params['http://rs.tdwg.org/dwc/terms/acceptedNameUsageID']) == "" && trim($params['http://rs.tdwg.org/dwc/terms/parentNameUsageID']) == ""){
      dwcav_error('error', $file, "row $row - no parentNameUsageID or acceptedNameUsageID (requires one or other)");
    }
    if(trim($params['http://rs.tdwg.org/dwc/terms/acceptedNameUsageID']) != "" && trim($params['http://rs.tdwg.org/dwc/terms/parentNameUsageID']) != ""){
      dwcav_error('error', $file, "row $row - has both parentNameUsageID and acceptedNameUsageID (requires one or other)");
    }
  }
}



function tdwg_term_rs_tdwg_org_dwc_terms_decimalLatitude($file, $row, $value, &$core_ids){
  if(trim($value) == ""){return;}
  if (floatval($value) < - 90.0 || floatval($value) > 90.0) {
  	dwcav_error('error', $file, "row $row - decimalLatiude should be between -90 and 90");
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_decimalLongitude($file, $row, $value, &$core_ids){
  if(trim($value) == ""){return;}
  if (floatval($value) < - 180.0 || floatval($value) > 180.0) {
  	dwcav_error('error', $file, "row $row - decimalLongitude should be between -180 and 180");
  }
}


function tdwg_term_rs_tdwg_org_dwc_terms_namePublishedInID($file, $row, $value, &$core_ids){
  if(trim($value) == ""){return;}
  if(!filter_var($value, FILTER_VALIDATE_URL)){
    dwcav_error('error', $file, "row $row - namePublishedInID should be a valid URL");
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_acceptedNameUsageID($file, $row, $value, &$core_ids){
  if(trim($value) == ''){return;}
  if(!array_key_exists($value, $core_ids)){
    dwcav_error('error', $file, "row $row acceptedNameUsageID ($value) does not exist.");
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_typeStatus($file, $row, $value, &$core_ids){
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
    dwcav_error('error', $file, "row $row unknown value ($value) for typeStatus");
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_taxonomicStatus($file, $row, $value, &$core_ids){
  if(trim($value) == ""){
    dwcav_error('warning', $file, "row $row has no taxonomicStatus");
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
    dwcav_error('error', $file, "row $row unknown value ($value) for taxonomicStatus");
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_nomenclaturalStatus($file, $row, $value, &$core_ids){
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
    dwcav_error('error', $file, "row $row unknown value ($value) for nomenclaturalStatus");
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_taxonRank($file, $row, $value, $core_ids){
  if(trim($value) == ""){
    dwcav_error('warning', $file, "row $row has no taxonRank");
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
    dwcav_error('error', $file, "row $row unknown value ($value) for taxonRank");
  }
}

function tdwg_term_rs_tdwg_org_dwc_terms_parentNameUsageID($file, $row, $value, &$core_ids){
  if(trim($value) == ''){return;}
  if(!array_key_exists($value, $core_ids)){
    dwcav_error('error', $file, "row $row parentNameUsageID ($value) does not exist.");
  }
}
