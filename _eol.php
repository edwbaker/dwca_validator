<?php
$namespaces[] = 'eol';

function eol_term_eol_org_schema_agent_agentID($file, $row, $value, &$core_ids, $all_ids) {
  if (trim($value) == "") {return;}
  if (!array_key_exists($value, $all_ids['http://eol.org/schema/agent/Agent']['ids'])) {
  	dwcav_error('error', $file, "row $row - EoL Agent ($value) does not exist in archive");
  }
}

function eol_exclusions_files_core_index() {
	return array(
	  'http://eol.org/schema/agent/Agent',
	);
}