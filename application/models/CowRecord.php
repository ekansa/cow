<?php

/*
Reads CoW XML

*/

class CowRecord {
    
    const baseURL = "http://cow.lhs.berkeley.edu/ncs/services/ddsws1-1?verb=GetRecord&id=";
    public $nameSpaces = array("dlese" => "http://www.dlese.org/Metadata/ddsws",
			       "cow" => "http://cow.lhs.berkeley.edu");
    public $xmlString;
    public $recordID;
    
    public $errors; //array of errors, useful for debugging
    
    
    public $recordData;
    
    
    //retrieve the data
    function getMetadataXML($recordID){
	$url = self::baseURL.$recordID;
	@$xmlString = file_get_contents($url);
	if(!$xmlString){
	    $this->recordError("XML not retrieved");
	    return false;
	}
	
	$xml = simplexml_load_string($xmlString);
	if(!$xml){
	    $this->recordError("XML not valid");
	    return false;
	}
	else{
	    unset($xml);
	    $this->xmlString = $xmlString;
	    return true;
	}
    }
    
    
    //read XML data, extract metadata and turn to PHP array
    function parseXML(){
	
	$xml = new DOMDocument();
	$xml->loadXML($this->xmlString);
	$xpath = new DOMXPath($xml);
	foreach($this->nameSpaces as $prefix => $uri){
	    $xpath->registerNamespace($prefix, $uri); //get those namespaces registered!
	}
	
	$SchemaNCScow = new SchemaNCScow;
	$SchemaNCScow->getXSD();
	if($SchemaNCScow->xsdString != false){
	    $SchemaNCScow->parseXSD();
	}
	
	if(is_array($SchemaNCScow->schema)){
	    $recordData = array();
	    $this->recordData = $recordData;
	    $this->extractData($xpath, $xml, $SchemaNCScow->schema);
	}
	
    }
    
    /*
     recursive function, reads through schema (array with tree structure),
     if elements in the schema array have NO children, run Xpath query to get value
     run xpath queries to get data on elements with no children elements and turn to PHP array
    */
    function extractData($xpath, $xml, $schema){
	
	$newRecs = array();
	foreach($schema as $elementKey => $elemArray){
	   
	    $query = "//".$elemArray["xpath"];
	    
	    $result = $xpath->query($query, $xml);
	    foreach($result as $node){
		
		$queryValue = $node->nodeValue;
		$addNewRec = false;
		
		
		if(is_array($elemArray["children"])){
		    //if there are children elements in schema, don't add query value to results
		    //query children elements
		    $this->extractData($xpath, $xml, $elemArray["children"]);
		}
		else{
		    //no child elements in schema to query, add query value to the results array
		    $addNewRec = true;
		}
		
		if($addNewRec){
		    $newRecs[] = array("path" => $elemArray["xpath"],
				  "label" => $elemArray["label"],
				  "type" => $elemArray["type"],
				  "value" => $queryValue
				  );
		}
		
		if(is_array($elemArray["attributes"])){
		    //if there are attributes, query for these also
		    $this->extractData($xpath, $xml, $elemArray["attributes"]);
		}
		
	    }
	}
	
	$recordData = $this->recordData;
	foreach($newRecs as $rec){
	    $recordData[] = $rec;
	}
	$this->recordData = $recordData;
	
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
