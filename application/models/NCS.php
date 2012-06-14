<?php

/*
Interacts with the NCS API

*/

class NCS {
 
    public $requestParams; //request parameters, send to CoW, to be translated into NCS request
    public $NCSparams; //request params for the NCS search
    public $NCSrequestURL ; //request LINK to the NCS
    public $NCSresponse; //response from NCS search
    
    
    //data from response
    public $totalNumResults;
    public $numReturned;
    public $offset;
    public $numPerPage;
    
    public $currentPage;
    public $lastPage;
    public $nextPage;
    public $prevPage;
    
    public $lastUpdated;
    public $results; //search results;
    
    
    //const baseURL = "http://nsdl.org/dds-search";
    const baseURL = "http://cow.lhs.berkeley.edu/ncs/services/ddsws1-1";
    const defaultNumReturn = 10;
    const defaultStartNum = 0;
    
    public $paramMapping = array("q" => array("NCSparam" => "q",
					      "verb" => "Search",
					      "About" => "Search")
				
				);
    
    public $NCSnamespaces = array("ddsws" => "http://www.dlese.org/Metadata/ddsws",
				  "xsi" => "http://www.w3.org/2001/XMLSchema-instance",
				  "nsdl_dc" => "http://ns.nsdl.org/nsdl_dc_v1.02/",
				  "dc" => "http://purl.org/dc/elements/1.1/",
				  "dct" => "http://purl.org/dc/terms/",
				  "para" => "http://ns.nsdl.org/ncs/comm_para",
				  "col" => "http://collection.dlese.org",
				  "com" => "http://ns.nsdl.org/ncs/comm_anno"
				  );
    
    
    
    function getBaseURL(){
	return self::baseURL;
    }

    
    function prepNCSsearch(){
	
	$this->lastUpdated = false;
	$this->lastPage = false;
	
	$paramMapping = $this->paramMapping;
	$requestParams = $this->requestParams;
	$NCSparams = array();
	foreach($requestParams as $paramKey => $paramVal){
	    if(array_key_exists($paramKey, $paramMapping)){
		$NCSparams[$paramMapping[$paramKey]["NCSparam"]] = $paramVal; //set the NCS corresponding parameter with the request 
		$NCSparams["verb"] = $paramMapping[$paramKey]["verb"];
	    }   
	}
	
	$this->currentPage = 1;
	if(isset($requestParams["page"])){
	    if(is_numeric($requestParams["page"])){
		$this->currentPage = $requestParams["page"];
	    }
	}
	
	
	if(isset($requestParams["n"])){
	    if(is_numeric($requestParams["n"])){
		 $NCSparams["n"] = $requestParams["n"];
		 $this->numPerPage = $requestParams["n"];
	    }
	}
	
	
	//add defaults for required parameters if they are not already set
	if(!isset($NCSparams["n"])){
	    $NCSparams["n"] = self::defaultNumReturn;
	    $this->numPerPage = self::defaultNumReturn;
	}
	
	if($this->currentPage > 1){
	    $NCSparams["s"] = (($this->currentPage - 1) * $NCSparams["n"])-1;
	}
	
	if(!isset($NCSparams["s"])){
	    $NCSparams["s"] = self::defaultStartNum;
	}
	
	if(isset($requestParams["sort"])){
	    $NCSparams["sort"] = $requestParams["sort"];
	}
	
	$this->NCSparams = $NCSparams;
    }


    function NCSsearch(){
	$NCSparams = $this->NCSparams;
	
	$NCSrequestURL = self::baseURL;
	
	$firstLoop = true;
	foreach($NCSparams as $paramKey => $paramVal){
	    
	    $actParam = $paramKey."=".urlencode($paramVal);
	    if($firstLoop){
		$NCSrequestURL .=  "?".$actParam;
	    }
	    else{
		$NCSrequestURL .=  "&".$actParam;
	    }
	    
	    $firstLoop = false;
	}
	
	$this->NCSrequestURL = $NCSrequestURL;
	$this->NCSresponse = file_get_contents($NCSrequestURL);

    }

    
    function parseXMLnumbers($NCSresponse){
	
	if($NCSresponse){
	    
	    $xml = simplexml_load_string($NCSresponse);
	    foreach($this->NCSnamespaces as $prefix => $nsURI){
		$xml->registerXPathNamespace($prefix, $nsURI); //register all the needed namespaces for XPATH
	    }
	
	    //get number of results
	    if($xml->xpath("/ddsws:DDSWebService/ddsws:Search/ddsws:resultInfo/ddsws:totalNumResults")){
		foreach($xml->xpath("/ddsws:DDSWebService/ddsws:Search/ddsws:resultInfo/ddsws:totalNumResults") as $xresult) {
		    $this->totalNumResults = (string)$xresult;
		}
	    }
	    else{
		$this->totalNumResults = 0;
	    }
	    //get number returned
	    if($xml->xpath("/ddsws:DDSWebService/ddsws:Search/ddsws:resultInfo/ddsws:numReturned")){
		foreach($xml->xpath("/ddsws:DDSWebService/ddsws:Search/ddsws:resultInfo/ddsws:numReturned") as $xresult) {
		    $this->numReturned = (string)$xresult;
		}
	    }
	    else{
		$this->numReturned = 0;
	    }
	    //get offset
	    if($xml->xpath("/ddsws:DDSWebService/ddsws:Search/ddsws:resultInfo/ddsws:offset")){
		foreach($xml->xpath("/ddsws:DDSWebService/ddsws:Search/ddsws:resultInfo/ddsws:offset") as $xresult) {
		    $this->offset = (string)$xresult;
		}
	    }
	    else{
		$this->offset = false;
	    }
	    
	    if($this->numReturned > 0 && $this->totalNumResults > 0 ){
		$lastPage = intval($this->totalNumResults/$this->numPerPage);
		// if there's a remainder, add a page. For example, 13 items should result in two pages.
		if ($this->totalNumResults % $this->numPerPage) {
		    $lastPage = $lastPage + 1;
		}
		$this->lastPage = $lastPage;
		
		if($this->currentPage < $this->lastPage){
		    $this->nextPage = $this->currentPage + 1;
		}
		else{
		    $this->nextPage = false;
		}
		
		if($this->currentPage > 1){
		    $this->prevPage = $this->currentPage - 1;
		}
		else{
		    $this->prevPage = false;
		}
		
	    }
	    
	    
	}
    }


    
    function parseXMLresults($NCSresponse){
	
	if($NCSresponse){
	    
	    $xml = simplexml_load_string($NCSresponse);
	    foreach($this->NCSnamespaces as $prefix => $nsURI){
		$xml->registerXPathNamespace($prefix, $nsURI); //register all the needed namespaces for XPATH
	    }
	
	    //get the records
	    $records = array();
	    if($xml->xpath("//ddsws:results/ddsws:record")){
		foreach($xml->xpath("//ddsws:results/ddsws:record") as $record) {
		    foreach($this->NCSnamespaces as $prefix => $nsURI){
			$record->registerXPathNamespace($prefix, $nsURI); //register all the needed namespaces for XPATH
		    }
		    
		    $actResult = array();
		    if($record->xpath("./ddsws:metadata/nsdl_dc:nsdl_dc/dc:identifier[@xsi:type='dct:URI']")){
			$actResult["category"] = "resource";
			
			foreach($record->xpath("./ddsws:metadata/nsdl_dc:nsdl_dc/dc:identifier[@xsi:type='dct:URI']") as $xresult){
			    $actResult["uri"] = (string)$xresult;
			}
			
			foreach($record->xpath("./ddsws:metadata/nsdl_dc:nsdl_dc/dc:title") as $xresult){
			    $actResult["title"] = (string)$xresult;
			}
			
			if($record->xpath("./ddsws:metadata/nsdl_dc:nsdl_dc/dc:creator")){
			    foreach($record->xpath("./ddsws:metadata/nsdl_dc:nsdl_dc/dc:creator") as $xresult){
				$actResult["authors"][] = (string)$xresult;
			    }    
			}
			elseif($record->xpath("./ddsws:metadata/nsdl_dc:nsdl_dc/dc:publisher")){
			    foreach($record->xpath("./ddsws:metadata/nsdl_dc:nsdl_dc/dc:publisher") as $xresult){
				$actResult["authors"][] = (string)$xresult;
			    }
			}
			
			
			foreach($record->xpath("./ddsws:metadata/nsdl_dc:nsdl_dc/dc:description") as $xresult){
			    $actResult["summary"] = (string)$xresult;
			}
			
			foreach($record->xpath("./ddsws:metadata/nsdl_dc:nsdl_dc/dct:dateSubmitted") as $xresult){
			    $pubTime = (string)$xresult;
			    $actResult["published"] = date("Y-m-d\TH:i:s\-07:00", strtotime($pubTime));
			}
			
			foreach($record->xpath("./ddsws:head/ddsws:fileLastModified") as $xresult){
			    $updatedTime = (string)$xresult;
			    $actResult["updated"] = date("Y-m-d\TH:i:s\-07:00", strtotime($updatedTime));
			}
			
		    }
		    elseif($record->xpath("./ddsws:metadata/para:commParadata/para:usageDataResourceURL")){
			//we have a "paradata" record
			    
			$actResult["category"] = "paradata";
			    
			foreach($record->xpath("./ddsws:metadata/para:commParadata/para:usageDataResourceURL") as $xresult){
			    $actResult["uri"] = (string)$xresult;
			}
			
			if($record->xpath("./ddsws:metadata/para:commParadata/para:paradataTitle/para:string[@language='en']")){
			    foreach($record->xpath("./ddsws:metadata/para:commParadata/para:paradataTitle/para:string[@language='en']") as $xresult){
				$actResult["title"] = (string)$xresult;
			    }
			}
			elseif($record->xpath("./ddsws:metadata/para:commParadata/para:usageDataProvidedForName/para:string[@language='en']")){
			    foreach($record->xpath("./ddsws:metadata/para:commParadata/para:usageDataProvidedForName/para:string[@language='en']") as $xresult){
				$actResult["title"] = (string)$xresult;
			    }
			}
			
			if($record->xpath("./ddsws:head/ddsws:collection")){
			    foreach($record->xpath("./ddsws:head/ddsws:collection") as $xresult){
				$actResult["authors"][] = (string)$xresult;
			    }    
			}
			
			if($record->xpath("./ddsws:metadata/para:commParadata/para:paradataDescription/para:string[@language='en']")){
			    foreach($record->xpath("./ddsws:metadata/para:commParadata/para:paradataDescription/para:string[@language='en']") as $xresult){
				$actResult["summary"] = (string)$xresult;
			    }
			}
			elseif($record->xpath("./ddsws:metadata/para:commParadata/para:usageDataProvidedForName/para:string[@language='en']")){
			    foreach($record->xpath("./ddsws:metadata/para:commParadata/para:usageDataProvidedForName/para:string[@language='en']") as $xresult){
				$actResult["summary"] = (string)$xresult;
			    }
			}
			
			foreach($record->xpath("./ddsws:metadata/para:commParadata//@dateTimeEnd") as $xresult){
			    $pubTime = (string)$xresult;
			    $actResult["published"] = date("Y-m-d\TH:i:s\-07:00", strtotime($pubTime));
			}
			
			foreach($record->xpath("./ddsws:head/ddsws:fileLastModified") as $xresult){
			    $updatedTime = (string)$xresult;
			    $actResult["updated"] = date("Y-m-d\TH:i:s\-07:00", strtotime($updatedTime));
			}
    
		    }
		    elseif($record->xpath("./ddsws:metadata/col:collectionRecord")){
			//we have a "collection" record
			$actResult["category"] = "collection";
			foreach($record->xpath("./ddsws:metadata/col:collectionRecord//general/url") as $xresult){
			    $actResult["uri"] = (string)$xresult;
			}
			
			if($record->xpath("./ddsws:metadata/col:collectionRecord//general/title")){
			    foreach($record->xpath("./ddsws:metadata/col:collectionRecord//general/title") as $xresult){
				$actResult["title"] = (string)$xresult;
			    }
			}
			
			if($record->xpath("./ddsws:head/ddsws:collection")){
			    foreach($record->xpath("./ddsws:head/ddsws:collection") as $xresult){
				$actResult["authors"][] = (string)$xresult;
			    }    
			}
			
			foreach($record->xpath("./ddsws:metadata/col:collectionRecord//general/description") as $xresult){
			    $actResult["summary"] = (string)$xresult;
			}
			
			foreach($record->xpath("./ddsws:metadata/col:collectionRecord//collection/dateTime") as $xresult){
			    $pubTime = (string)$xresult;
			    $actResult["published"] = date("Y-m-d\TH:i:s\-07:00", strtotime($pubTime));
			}
			
			foreach($record->xpath("./ddsws:head/ddsws:fileLastModified") as $xresult){
			    $updatedTime = (string)$xresult;
			    $actResult["updated"] = date("Y-m-d\TH:i:s\-07:00", strtotime($updatedTime));
			}
			
		    }
		    elseif($record->xpath("./ddsws:metadata/com:comm_anno")){
			//we have a "collection" record
			$actResult["category"] = "community annotation";
			foreach($record->xpath("./ddsws:metadata/com:comm_anno/com:url") as $xresult){
			    $actResult["uri"] = (string)$xresult;
			}
			
			if($record->xpath("./ddsws:metadata/com:comm_anno/com:title")){
			    foreach($record->xpath("./ddsws:metadata/com:comm_anno/com:title") as $xresult){
				$actResult["title"] = (string)$xresult;
			    }
			}
			
			$urlArray = explode("/", $actResult["uri"]);
			$urlBase = $urlArray[0]."//".$urlArray[1].$urlArray[2]."/";
			$actResult["authors"][] = $urlBase;
			
			
			$actResult["summary"] = $actResult["title"];
			
			foreach($record->xpath("./ddsws:head/ddsws:fileLastModified") as $xresult){
			    $updatedTime = (string)$xresult;
			    $actResult["updated"] = date("Y-m-d\TH:i:s\-07:00", strtotime($updatedTime));
			}
			
			
			
		    }
		    else{
			$actResult["uri"] = false;
		    }
		    
		    
		    if(isset($actResult["updated"])){
			if(!$this->lastUpdated){
			    $this->lastUpdated = $actResult["updated"];
			}
			else{
			    if(strtotime($this->lastUpdated) < strtotime($actResult["updated"])){
				$this->lastUpdated = $actResult["updated"];
			    }
			}
		    }
		    
		    if(!isset($actResult["authors"])){
			$actResult["authors"][] = "Unspecified NCS resource contributor";
		    }
		    
		    $records[] = $actResult;
		}//end loop
	    }//case with records
	    
	    if(count($records)>0){
		$this->results = $records;
	    }
	    else{
		$this->results = false;
	    }
	}
    }

    


}//end class








?>
