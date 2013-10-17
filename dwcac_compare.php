#!/usr/bin/php
<?php 

$hash1 = md5_file(dwcac_openfile($argv[1]));
$hash2 = md5_file(dwcac_openfile($argv[2]));

//print "$hash1\n$hash2\n";

if ($hash1 != $hash2) {
  print "Archives are not the same.\n";
}

function dwcac_openfile($file_name){
  if (filter_var($file_name, FILTER_VALIDATE_URL)){
  	$tmp_path = sys_get_temp_dir() . '/'.time().'.zip';
  	$handle = fopen($tmp_path, "w");
  	$options = array(
      CURLOPT_FILE    => $handle,
      CURLOPT_TIMEOUT =>  28800, // set this to 8 hours so we dont timeout on big files
      CURLOPT_URL     => $file_name,
    );
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    curl_exec($ch);
    fclose($handle);
    $file_name = $tmp_path;
    return $file_name;
  }
  
  return $file_name;
}

?>