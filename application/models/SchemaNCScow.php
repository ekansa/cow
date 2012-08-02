<?php

/*
Gets NCS metadata schema description, use it for making human readable search results

*/

class SchemaNCScow {

	const schemaURL = "http://cow.lhs.berkeley.edu/metadata/cowItem/0.5/cowItemType.xsd"; // url of the schema
	const fieldListURL = "http://cow.lhs.berkeley.edu/ncs/services/ddsws1-1?verb=ListFields"; // url of the field List
	
	const cacheLife = 90000; //25 hour cache life. because why not
	const cacheDir = "./NCScache/"; //directory of the XSD cache
	const XSDcacheID = "XSDschemaNCSCoW";
	const NCSfieldListID = "NCSfieldList";
	const cowNSprefix = "cow";
	
	public $xsdString; //string for the XSD file
	public $fieldListXML; //string for the field list XML dile
	
	public $nameSpaces = array("default" => "http://cow.lhs.berkeley.edu",
				 "xs" => "http://www.w3.org/2001/XMLSchema",
				 "cowconf" => "http://cow.lhs.berkeley.edu/config",
				 "meta" => "http://www.dlese.org/Metadata/ddsws" ); //namespaces for XSD file and the XML field list
	
	public $elements; //array of elements with element names and human-friendly labels
	public $fieldList; //array of fields available for querying from the NCS server
	public $schema; //array of important parts of the schema, useful for getting elements, hierarchies, occurances from data
	
	public $errors; //array of errors, useful for debugging
	
	public $doneElementTypes ; //array of done element types
	
	//read the XSD document, make an array schema
	function parseXSD(){
  
		  $xml = new DOMDocument();
		  $xml->loadXML($this->xsdString);
		  $xpath = new DOMXPath($xml);
		  foreach($this->nameSpaces as $prefix => $uri){
				$xpath->registerNamespace($prefix, $uri); //get those namespaces registered!
		  }
		  
		  $this->doRefElement = false;
		  $this->doneElementTypes = array();
		  $schema = $this->extractSchema($xpath, $xml, "recordType", self::cowNSprefix.":"."record");
		  //$schema = $this->extractSchema($xpath, $xml, "cowItemType", "cowItem");
		  
		  $this->schema = $schema;
	}//end function
	
	
	//special case for processing referenced / related elements
	 function extractSchemaRef($xpath, $xml, $elementRef, $parPath){
		  $actSchema = array();
		  $query = "(//xs:element[@name = '$elementRef']) | (//xs:element[@substitutionGroup = '$elementRef'])";
		  $result = $xpath->query($query, $xml);
		  foreach($result as $elementNode){
				$elementName = false;
				$query = "@name";
				$resultB = $xpath->query($query, $elementNode);
				foreach($resultB as $node){
					 $elementName = $node->nodeValue;
				}
				$elementType = false;
				$query = "@type";
				$resultB = $xpath->query($query, $elementNode);
				foreach($resultB as $node){
					 $elementType = $node->nodeValue;
				}
				if($elementName != false && $elementType != false){
					 //echo "bang! $elementName $elementType";
					 $newPath = $parPath."/".self::cowNSprefix.":".$elementName;
					 //get child elements from schema
					 $childSchema = $this->extractSchema($xpath, $xml, $elementType, $newPath);
					 $actSchema[$elementName] = array("element" => $elementName,
														 "type" => $elementType,
														 "displayLabel" => $elementName,
														 "public" => false,
														 "makeFacet" => false,
														 "xpath" => $newPath,
														 "children" => $childSchema,
														 "attributes" => false
														 );
				}
		  }
		  
		  return $actSchema;
	 }
	
	
	 function extractSchema($xpath, $xml, $complexType, $parPath, $childType = "element"){
  
		  $actSchema = array();
		  
		  if(!in_array($complexType, $this->doneElementTypes)){
				$doneElementTypes = $this->doneElementTypes;
				//$doneElementTypes[] = $complexType;
				$this->doneElementTypes = $doneElementTypes;
				
				//check first for extenstions, since some complexTypes extend other complexTypes
				$extensionType = false;
				$query = "//xs:complexType[@name = '$complexType']/xs:complexContent/xs:extension/@base";
				$resultCheck = $xpath->query($query, $xml);
				foreach($resultCheck as $node){
					 $extensionType = $node->nodeValue;
				}
				
				if(!$extensionType){
					 $query = "//xs:complexType[@name = '$complexType']";
				}
				else{
					 //the complex type is an extension, also query for the extension
					 $query = "(//xs:complexType[@name = '$complexType']) | (//xs:complexType[@name = '$extensionType'])";
				}
				
				$result = $xpath->query($query, $xml);
				foreach($result as $complexNode){
			  
					 if($childType == "element"){
						  $query = ".//xs:element";
						  $resultElem = $xpath->query($query, $complexNode);
						  foreach($resultElem as $elementNode){
						 
								$query = "@name";
								$elementName = false;
								$resultB = $xpath->query($query, $elementNode);
								foreach($resultB as $node){
									 $elementName = $node->nodeValue;
								}
						 
								$elementLabel = false;
								$query = "@cowconf:label";
								$resultC = $xpath->query($query, $elementNode);
								foreach($resultC as $node){
									 $elementLabel = $node->nodeValue;
								}
						 
								$elementType = false;
								$query = "@type";
								$resultD = $xpath->query($query, $elementNode);
								foreach($resultD as $node){
									 $elementType = $node->nodeValue;
								}
						 
								$elementPublic = false;
								$query = "@cowconf:public";
								$resultE = $xpath->query($query, $elementNode);
								foreach($resultE as $node){
									 $elementPublic = $node->nodeValue;
									 if($elementPublic == "1"){
										  $elementPublic = true;
									 }
									 else{
										  $elementPublic = false;
									 }
								}
						 
								$elementFacet = false;
								$query = "@cowconf:makeFacet";
								$resultF = $xpath->query($query, $elementNode);
								foreach($resultF as $node){
									 $elementFacet = $node->nodeValue;
									 if($elementFacet == "1"){
										  $elementFacet = true;
									 }
									 else{
										  $elementFacet = false;
									 }
								}
						 
								$query = "@ref";
								$elementRef = false;
								$resultB = $xpath->query($query, $elementNode);
								foreach($resultB as $node){
									 $elementRef = $node->nodeValue;
								}
						 
								if(!$elementRef){
									 $newPath = $parPath."/".self::cowNSprefix.":".$elementName;
									 //$newPath = $parPath."/".$elementName;
									 
									 //get child elements from schema
									 $childSchema = $this->extractSchema($xpath, $xml, $elementType, $newPath);
									 if(count($childSchema)<1){
										  $childSchema = false;
									 }
									 
									 //get any attributes for this element from schema
									 $attributes = $this->extractSchema($xpath, $xml, $elementType, $newPath, "attribute");
									 if(count($attributes)<1){
										  $attributes = false;
									 }
									 
									 $actSchema[$elementName] = array("element" => $elementName,
														 "type" => $elementType,
														 "displayLabel" => $elementLabel,
														 "public" => $elementPublic,
														 "makeFacet" => $elementFacet,
														 "xpath" => $newPath,
														 "children" => $childSchema,
														 "attributes" => $attributes
														 );
									 	 
								}//end case without a ref attribute
								else{
									 
									 //the schema references a relatd element, needs special treatment
									 $refSchema = $this->extractSchemaRef($xpath, $xml, $elementRef, $parPath);
									 $actSchema = $refSchema;
								}//end case with  ref attribute
								
						  }//end loop through elements
						  
					 }//end case for getting elements
					 else{
						 
						  //query for child attributes
						  $query = ".//xs:attribute";
						  $result = $xpath->query($query, $complexNode);
						  foreach($result as $attributeNode){
						 
						 $query = "@name";
						 $attributeName = false;
						 $resultB = $xpath->query($query, $attributeNode);
						 foreach($resultB as $node){
							  $attributeName = $node->nodeValue;
						 }
						 
						 $attributeLabel = false;
						 $query = "@cowconf:label";
						 $resultC = $xpath->query($query, $attributeNode);
						 foreach($resultC as $node){
							  $attributeLabel = $node->nodeValue;
						 }
						 
						 $attributeType = false;
						 $query = "@type";
						 $resultD = $xpath->query($query, $attributeNode);
						 foreach($resultD as $node){
							  $attributeType = $node->nodeValue;
						 }
						 
						 
						 $newPath = $parPath."/@".$attributeName;
						 
						 $actSchema[$attributeName] = array("attribute" => $attributeName,
											 "type" => $attributeType,
											 "displayLabel" => $attributeLabel,
											 "xpath" => $newPath,
											 "children" => false,
											 "attributes" => false
											 );
						  }
					 }
			  
			  
				}
		  }
		  return $actSchema;
	}
	
	
	
	
	
	//read the XSD document, make an array of metadata elements and human-friendly labels
	function getXSDelements(){
  
  $xml = new DOMDocument();
  $xml->loadXML($this->xsdString);
  $xpath = new DOMXPath($xml);
  foreach($this->nameSpaces as $prefix => $uri){
		$xpath->registerNamespace($prefix, $uri); //get those namespaces registered!
  }
  
  $elements = array();
  $query = "//xs:element";
  $result = $xpath->query($query, $xml);
  foreach($result as $elementNode){
		$actElement = array();
		$query = "@name";
		$elementName = false;
		$resultB = $xpath->query($query, $elementNode);
		foreach($resultB as $node){
	  //$actElement["elementName"] = $node->nodeValue;
	  $elementName = $node->nodeValue;
		}    
		
		$elementLabel = false;
		$query = "@cowconf:label";
		$resultC = $xpath->query($query, $elementNode);
		foreach($resultC as $node){
	  //$actElement["label"] = $node->nodeValue;
	  $elementLabel = $node->nodeValue;
			  }
		
		
		$elementFacet = false;
		$query = "@cowconf:makeFacet";
		$resultF = $xpath->query($query, $elementNode);
		foreach($resultF as $node){
	  $elementFacet = $node->nodeValue;
	  if($elementFacet == "1"){
			$elementFacet = true;
	  }
	  else{
			$elementFacet = false;
	  }
		}
		
		
		//$elements[] = $actElement;
		if($elementName != false && $elementLabel != false){
	  $elements[$elementName]["displayLabel"] = $elementLabel;
	  $elements[$elementName]["xpath"] = false;
	  $elements[$elementName]["makeFacet"] = $elementFacet;
		}
  }
  
  $this->elements = $elements;
	}//end function
	
	
	
	function getXSD(){
  $frontendOptions = array('lifetime' => self::cacheLife,'automatic_serialization' => true );
  $backendOptions = array('cache_dir' => self::cacheDir );
	  
  $cache = Zend_Cache::factory('Core','File',$frontendOptions,$backendOptions);
  $cache_id = self::XSDcacheID;
  
  if(!$cache_result = $cache->load($cache_id)) {
		@$xsdString = file_get_contents(self::schemaURL); //not in cache / cache expired, go get from source
		if(!$xsdString){
	  $this->recordError("XSD not retrieved");
	  $this->xsdString = false;
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
		$this->xsdString = $xsdString;
		return true;
  }
  else{
		$this->xsdString = false;
		$this->recordError("XSD invalid");
		return false;
  }
	}// end function




	function getFieldList(){
  $frontendOptions = array('lifetime' => self::cacheLife,'automatic_serialization' => true );
  $backendOptions = array('cache_dir' => self::cacheDir );
	  
  $cache = Zend_Cache::factory('Core','File',$frontendOptions,$backendOptions);
  $cache_id = self::NCSfieldListID;
  
  if(!$cache_result = $cache->load($cache_id)) {
		@$xmlString = file_get_contents(self::fieldListURL); //not in cache / cache expired, go get from source
		if(!$xmlString){
	  $this->recordError("Field List XML not retrieved");
	  $this->fieldListXML = false;
	  return false;
		}
		else{
	  $cache->save($xmlString, $cache_id); //save XSD to the cache
		}
  }
  else{
		$xmlString = $cache_result;
  }
  
  //quick validation
  @$xml = simplexml_load_string($xmlString);
  if($xml != false){
		unset($xml);
		$this->fieldListXML = $xmlString;
		return true;
  }
  else{
		$this->fieldListXML = false;
		$this->fieldListXML("Field List XML invalid");
		return false;
  }
	}// end function


	function parseFieldList(){
  $xml = new DOMDocument();
  $xml->loadXML($this->fieldListXML);
  $xpath = new DOMXPath($xml);
  foreach($this->nameSpaces as $prefix => $uri){
		$xpath->registerNamespace($prefix, $uri); //get those namespaces registered!
  }
  
  $elements = $this->elements;
  $newElements = $elements;
  $query = "//meta:fields/meta:field";
  $result = $xpath->query($query, $xml);
  foreach($result as $fieldNode){
		
		$fieldXpath = $fieldNode->nodeValue;
		$xpathXplode = explode("/", $fieldXpath);
		if(count($xpathXplode)>1){
	  $lastElement = $xpathXplode[(count($xpathXplode)-1)]; //the last element of the xpath path
	  $firstElement = $xpathXplode[1]; //not really the first, since the xpaths start with a slash
	  
	  if($firstElement == "key"){
			//it's the kind of field we'd want to query
			foreach($elements as $key => $elementArray){
		  
		  
		  if($lastElement == $key){
				$newElements[$key]["xpath"] = $fieldXpath;
		  }
			}
	  }
		}
  }
  
  $this->elements = $newElements; 
  
	}


	
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
