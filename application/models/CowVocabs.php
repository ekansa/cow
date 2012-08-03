<?php

/*
Interacts with the ASN API
to get linked data about science achievement benchmarks
*/

class CowVocabs {
 
	 public $errors; //array of errors, useful for debugging
	 public $vocabTerms; //array of vocabulary terms, sorted in order


	 public $vocabs = array(
		  "language" => "vocabs/language.xsd",
		  "metadataLanguage" => "vocabs/language.xsd",
		  "smdForumPrimary" => "vocabs/smdForum.xsd",
		  //"vocabs/missionOrProgram.xsd",
		  "educationalLevel" => "vocabs/edLevel.xsd",
		  //"vocabs/audienceRefinement.xsd",
		  "format" => "vocabs/format.xsd",
		  "topicsSubjects" => "vocabs/topicsSubjects.xsd",
		  "resourceType" => "vocabs/resourceType.xsd",
		  //"vocabs/accessRestrictions.xsd",
		  //"vocabs/propertyRights.xsd",
		  "instructionalStrategies" => "vocabs/instructionalStrategies.xsd",
		  "assessments" => "vocabs/assessments.xsd",
		  "stateStandards" => "vocabs/stateAbbreviations.xsd",
		  "learningTime" => "vocabs/learningTime.xsd"
		  //"vocabs/materialsCost.xsd",
		  //"vocabs/accessibility.xsd",
		  //"vocabs/licenseName.xsd"
	 );
	 
	 public $nameSpaces = array(
				 "xs" => "http://www.w3.org/2001/XMLSchema",
				 "cowconf" => "http://cow.lhs.berkeley.edu/config"); //namespaces for XSD file and the XML field list
	 
	 const cacheLife = 900000; //250 hour cache life. because why not
	 const cacheDir = "./VocabsCache/"; //directory of the cache of ASN data
    const XSDprefix = "http://cow.lhs.berkeley.edu/metadata/cowItem/1.0rc2/";
	 
	 
	 
	 //adds displayValue to list of values (from facet results or from result items)
	 function sortValues($valueList){
		  
		  $vocabTerms = $this->vocabTerms;
		  if(is_array($valueList)){
				
				if(!is_array($vocabTerms)){
					 $vocabTerms = array();
					 foreach($valueList as $valArray){
						  $value = $valArray["value"];
						  $vocabTerms[$value] = array("value" => $value);
					 }
					 ksort($vocabTerms); //sort on the key
				}
				
				$newValueList = array();
				foreach($vocabTerms as $term){
					 foreach($valueList as $valArray){
						  if($valArray["value"] == $term["value"]){
								$newValueList[] = $valArray;
								break;
						  }
					 }
				}
				unset($valueList);
				$valueList = $newValueList;
				unset($newValueList);
		  }
		  
		  return $valueList;
	 }
	 
	 
	 
	 
	 
	 
	 //adds displayValue to list of values (from facet results or from result items)
	 function addDisplayValues($valueList){
		  
		  $vocabTerms = $this->vocabTerms;
		  if(is_array($vocabTerms) && is_array($valueList)){
				$newValueList = array();
				foreach($valueList as $valArray){
					 foreach($vocabTerms as $term){
						  if($valArray["value"] == $term["value"]){
								if($term["displayValue"] != false){
									 $valArray["displayValue"] = $term["displayValue"];
								}
								break;
						  }
					 }
					 $newValueList[] = $valArray;
				}
				unset($valueList);
				$valueList = $newValueList;
				unset($newValueList);
		  }
		  
		  return $valueList;
	 }
	 
	 //returns a displayValue from a single value
	 function getDisplayValue($value){
		  $output = false;
		  $vocabTerms = $this->vocabTerms;
		  if(is_array($vocabTerms)){
				foreach($vocabTerms as $term){
					 if($value == $term["value"]){
						  if($term["displayValue"] != false){
								$output = $term["displayValue"];
						  }
						  break;
					 }
				}
		  }
		  return $output;
	 }
	 
	 
	 
	 
	 function getVocabTerms($vocabKey){
		  
		  $XSDstring = $this->getVocab($vocabKey);
		  if($XSDstring != false){
				$vocabTerms = array();
				$xml = new DOMDocument();
				$xml->loadXML($XSDstring);
				$xpath = new DOMXPath($xml);
				foreach($this->nameSpaces as $prefix => $uri){
					 $xpath->registerNamespace($prefix, $uri); //get those namespaces registered!
				}
				$query = "//xs:enumeration";
				$result = $xpath->query($query, $xml);
				foreach($result as $node){
					 $value = false;
					 $query = "@value";
					 $vresult = $xpath->query($query, $node);
					 foreach($vresult as $vnode){
						  $value = $vnode->nodeValue;
					 }
					 $displayValue = false;
					 $query = "@cowconf:label";
					 $vresult = $xpath->query($query, $node);
					 foreach($vresult as $vnode){
						  $displayValue = $vnode->nodeValue;
					 }
					 $vocabTerms[] = array("value" => $value, "displayValue" => $displayValue);
				}
				$this->vocabTerms = $vocabTerms;
		  }
		  else{
				$this->vocabTerms = false;
		  }
	 }//end function
	 
	 //get the vocabulary
	 function getVocab($vocabKey){
		  $vocabs = $this->vocabs;
		  if(array_key_exists($vocabKey, $vocabs)){
				$vocabFile = $vocabs[$vocabKey];
				$XSDstring = $this->getXSD($vocabKey, $vocabFile);
				return $XSDstring;
		  }
		  else{
				return false;
		  } 
	 }
	 
	 //get vocabulary from remote server or from cache
	 function getXSD($XSDkey, $XSDfile){
		  $frontendOptions = array('lifetime' => self::cacheLife,'automatic_serialization' => true );
		  $backendOptions = array('cache_dir' => self::cacheDir );
			  
		  $cache = Zend_Cache::factory('Core','File',$frontendOptions,$backendOptions);
		  $cache_id = $XSDkey;
		  
		  if(!$cache_result = $cache->load($cache_id)) {
				@$xsdString = file_get_contents(self::XSDprefix.$XSDfile); //not in cache / cache expired, go get from source
				if(!$xsdString){
					 $this->recordError("$XSDfile not retrieved");
					 return false;
				}
				else{
					 $cache->save($xsdString, $cache_id); //save XSD to the cache
				}
		  }
		  else{
				$xsdString = $cache_result;
		  }
		  
		  //quick validation
		  @$xml = simplexml_load_string($xsdString);
		  if($xml != false){
				unset($xml);
				return $xsdString;
		  }
		  else{
				$this->recordError("$XSDfile invalid");
				return false;
		  }
	 }// end function
	 


	 //record errors 
	 function recordError($errorMessage){
  
		  if(!is_array($this->errors)){
				$errors = array();
		  }
		  else{
				$errors = $this->errors;
		  }
		
		  $errors[] = $errorMessage;
		  $this->errors = $errors;
  
	 }//end function

}//end class

?>
