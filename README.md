dwca_validator
==============

Darwin Core Archive validator
by Ed Baker


Extending the tool
==================
To create an extension to the tool create a new php file n the same folder as dwcav_validator.php with a name matching the format: 

  _yourextension.php 

At the start of the file you should register a namespace for your extension by adding your chosen namespace to the array as follows:

  $namespaces[] = 'namespace_name';
  
All of the functions you create in this file should start with the namespace_name you have specified here. 

A number of hooks are available, to make use of them replace hook in the function name with the namespace_name you have chosen.

*hook_exclusions_files_core_index()
If you add fields that do not link to the index of the core file they will throw errors as this is not standard behaviour in a star-schema. If you know what you are doing (and even if you don't) you can return an array of rowType URIs from this function to surpress the errors.

*hook_terms_info()
Use this to specify instances where you need to two or more fields from the same row to properly validate the file. Return an array of the URIs of the fields you need, keyed by the rowType URI. 

*hook_term_[safe name of term uri]($file, $row, $rowType, $value, &$core_ids, [&$identifiers])
Use this to validate content against a specific uri (column identifier) specified in the meta.xml. To create the safe name remove the initial http:// and replace all / and . with _. $file and $row specify what file you are validating, mainly useful for generating an error message using dwcav_error(). $value is the value you should be validating. $core_ids is used if you need to verify that a value occurs in the core index, $identifiers can be used to check the identifiers used in other files. There is no return.

*hook_terms_freetext()
Indicate to the validator that the term URIs returned do not need validation. Optionally can indicate that they must be non-empty.
