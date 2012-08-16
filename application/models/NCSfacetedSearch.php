<?php

/*
Interacts with the NCS API

*/

class NCSfacetedSearch {
 
    public $JSONrequestURI; //request uri for the current search in JSON format
    public $AtomRequestURI; //request uri for the current search in Atom format
    
    public $localBaseURI; //local base URI for queries
    public $requestURI; //request URI
    public $requestParams; //request parameters, send to CoW, to be translated into NCS request
    public $NCSparams; //request params for the NCS search
    public $NCSrequestURL ; //request LINK to the NCS
    public $NCSresponse; //response from NCS search
    
    public $schemaToFacetsArray; //schema elements to request for facet counts
    public $schemaArray; //schema as an array, to help with querying query results
    
    //existing filters applied to the results
    public $existingFilters;
    public $facets;
    
    //data from response
    public $totalNumResults;
    public $numReturned;
    public $offset;
    public $numPerPage;
    
    //Pagination, integer numbers
    public $currentPage; 
    public $lastPage;
    public $nextPage;
    public $prevPage;
    
    //JSON pagination, links
    public $firstPageURI;
    public $prevPageURI;
    public $nextPageURI;
    public $lastPageURI;
    
	 public $currentSorting; //array of the current sorting
	 public $currentSortOptions; //array of available sort options
    public $lastUpdated; //last updated time
    public $results; //search results;
    
    public $displayAllResultMetadata; //show metadata elements marked in the NCS schema as not public
    
	 public $elementValueLimit; //limits attribute query to an element with a specific value, used tp fix bug: https://github.com/lhs/COW-metadata-configs/issues/2
	 
	 public $numericSortFacets;
	 public $facetSorting;
	 
    const baseURL = "http://cow.lhs.berkeley.edu/ncs/services/ddsws1-1";
	 const imageBaseURL = "http://dev.nasa-digital-library.pantheon.berkeley.edu/sites/default/files/images/resource_images/";
    const NCSuserKey = "1344019002616";
    const defaultNumReturn = 10;
    const defaultStartNum = 0;
    
    private $removeValue; //used in constructing links, false if not removing, otherwise the value of a current request parameter to be removed
    
    
    public $paramConfig = array("qq" => array("displayLabel" => "Keyword search",
					      "pathDelimiter" => false),
				"asnId" => array("displayLabel" => "Benchmarks for Science Literacy",
					      "pathDelimiter" => false),
				"NCSq" => array("displayLabel" => "NCS service query",
					      "pathDelimiter" => false)
				);
    
    public $NCSnamespaces = array("ddsws" => "http://www.dlese.org/Metadata/ddsws",
				  "xsi" => "http://www.w3.org/2001/XMLSchema-instance",
				  "nsdl_dc" => "http://ns.nsdl.org/nsdl_dc_v1.02/",
				  "dc" => "http://purl.org/dc/elements/1.1/",
				  "dct" => "http://purl.org/dc/terms/",
				  "para" => "http://ns.nsdl.org/ncs/comm_para",
				  "col" => "http://collection.dlese.org",
				  "com" => "http://ns.nsdl.org/ncs/comm_anno",
				  "cow" => "http://cow.lhs.berkeley.edu"
				  );
    
	 public $sortFields = array(
		  "title" => array("display" => "Title", "NCS" => "/key//record/general/title"), 
		  "url" => array("display" => "URL", "NCS" => "/key//record/general/url"),  
		  "date" => array("display" => "Date", "NCS" => "/key//record/authorshipRightsAccessRestrictions/date"),
		  "relevance" => array("display" => "Relevance", "NCS" => false)
	 );
  
    public $multiValueParentElements = array("contributor",
															"organization",
															"person"); //something of a hack. these are elements that have children but can be repeated
    
    function getBaseURL(){
		  return self::baseURL;
    }


	 //cleans the request parameters of blank values
	 function requestParameterClean(){
		  $requestParams = $this->requestParams;
		  $cleanParams = array();
		  foreach($requestParams as $paramKey => $paramVals){
				if(is_array($paramVals)){
					 //value for the parameter is an array
					 $cleanVals = array();
					 foreach($paramVals as $val){
						  if(strlen($val)>0 && $val != " "){
								$cleanVals[] = $val;
						  }
					 }
					 if(count($cleanVals)>0){
						  $cleanParams[$paramKey] = $cleanVals;
					 }
				}
				else{
					 //value for the parameter is a simple value
					 if(strlen($paramVals)>0 && $paramVals != " "){
						  $cleanParams[$paramKey] = $paramVals;
					 }
					 
				}
				
		  }
		  
		  $this->requestParams = $cleanParams;
	 }

    
    function prepNCSsearch(){
	
		  $this->lastUpdated = false;
		  $this->lastPage = false;
		  
		  //set pagination to false
		  $this->firstPageURI = false;
		  $this->prevPageURI = false;
		  $this->nextPageURI = false;
		  $this->lastPageURI = false;
		  
		  //default to only metadata elements marked as public
		  $this->displayAllResultMetadata = false;
		  
		  $this->requestParameterClean(); //clean request parameters of blank values
		  $requestParams = $this->requestParams;
		  
		  $NCSparams = array();
		  $NCSparams["verb"] = "Search";
		  $NCSparams["facet"] = "on";
		  $NCSparams["facet.maxResults"] = 1000; //return all the facets for a field
		  
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
		      $NCSparams["s"] = (($this->currentPage - 1) * $NCSparams["n"]);
		  }
	
		  if(!isset($NCSparams["s"])){
		      $NCSparams["s"] = self::defaultStartNum;
		  }
	
		  $sorting = false;
		  $sortFields = $this->sortFields;
		  $currentSorting = array();
		  if(isset($requestParams["sortDescendingBy"])){
		      if(array_key_exists($requestParams["sortDescendingBy"], $sortFields)){
					 if($sortFields[$requestParams["sortDescendingBy"]]["NCS"] != false){  //false is for relevance sorting, the default for the service
						  $NCSparams["sortDescendingBy"] = $sortFields[$requestParams["sortDescendingBy"]]["NCS"];
					 }
					 $currentSorting["displayLabel"] = $sortFields[$requestParams["sortDescendingBy"]]["display"];
					 $currentSorting["order"] = "Descending";
					 $sorting = true;
				}
		  }
		  elseif(isset($requestParams["sortAscendingBy"])){
				if(array_key_exists($requestParams["sortAscendingBy"], $sortFields)){
					 if($sortFields[$requestParams["sortAscendingBy"]]["NCS"] != false){  //false is for relevance sorting, the default for the service
						  $NCSparams["sortAscendingBy"] = $sortFields[$requestParams["sortAscendingBy"]]["NCS"];
					 }
					 $currentSorting["displayLabel"] = $sortFields[$requestParams["sortAscendingBy"]]["display"];
					 $currentSorting["order"] = "Ascending";
					 $sorting = true;
				}
		  }
		  else{
				//do nothing
		  }
		  
		  if(!$sorting && !isset($requestParams["qq"])){
				//default sorting
				$NCSparams["sortDescendingBy"] = "/key//general/authorshipRightsAccessRestrictions/date";
				$currentSorting = array("displayLabel" => "Date", "order" => "Descending");
		  }
		  elseif(!$sorting && isset($requestParams["qq"])){
				unset($NCSparams["sortDescendingBy"]);
				unset($NCSparams["sortAscendingBy"]);
				$currentSorting = array("displayLabel" => "Relevance", "order" => "Descending");
		  }
		  
		  $this->currentSorting = $currentSorting;
		  
		  $facetSorting = array();
		  if(isset($requestParams["facetSort"])){
				$this->numericSortFacets = false;
				$facetSorting["currentFacetSort"] = "Terms";
		  }
		  else{
				$this->numericSortFacets = true;
				$facetSorting["currentFacetSort"] = "Facet Count";
		  }
		  $this->facetSorting = $facetSorting;
		  
		  $this->NCSparams = $NCSparams;
    }



    //adds facet categories to the search
    function addFacetCategories($NCSrequestURL){
	
		  $requestParams = $this->requestParams;
		  $schemaToFacetsArray = $this->schemaToFacetsArray;
		  
		  if(!stristr($NCSrequestURL, "?")){
				$paramSep = "?";
		  }
		  else{
				$paramSep = "&";
		  }
	
		  $NCSrequestURL .= $paramSep."ky=".self::NCSuserKey; //add the NCS user key for this repository service
		  $paramSep = "&";
		
		  if(is_array($this->schemaToFacetsArray)){
				
				$facetsArray = array("authorshipRightsAccessRestrictions" => "",
						"format" => "",
						"assessments" => "",
						"standards" => "",
						"educationalLevel" => ""
						//"topicsSubjects" => ""
						
						);
				
				//$this->schemaToFacetsArray = $facetsArray;
				
				$educationLevel = false;
				
				foreach($this->schemaToFacetsArray as $key => $elementArray){
					 if(isset($elementArray["makeFacet"])){
						  if($elementArray["makeFacet"]){
								$NCSrequestURL .= $paramSep."facet.category=".urlencode($key);
								if(stristr($key, "educationalLevel")){
									 $educationLevel = true;
								}
						  }
					 }
				}
				
				if(!$educationLevel){
					 //add educationl level just in case it's not there.
					 $NCSrequestURL .= $paramSep."facet.category=educationalLevel";
				}
				
				
				//start to build parmaters for filtering on facets
				$NCSquery = "";
				$dillDown = false;
				foreach($requestParams as $paramKey => $paramVals){
					 if(array_key_exists($paramKey, $schemaToFacetsArray)){
						  
						  if(!is_array($paramVals)){
								//A request parameter can be an array, so treat every request an array just to keep consistent.
								$paramVals = array(0 => $paramVals);
						  }
						  
						  foreach($paramVals as $paramVal){
								$encodedVal = urlencode($paramVal);
								$encodedVal = str_replace("%5C%27", "%27", $encodedVal); //fix apostrophe encoding issue
								$dillDown = true;
								$NCSrequestURL .= "&f.drilldown.category=".$paramKey;
								$NCSrequestURL .= "&f.drilldown.".$paramKey.".path=".$encodedVal;
								if($schemaToFacetsArray[$paramKey]["xpath"] != false){
									 $xpath = "(".$schemaToFacetsArray[$paramKey]["xpath"].":\"".$encodedVal."\")";
									 if(strlen($NCSquery)<1){
										  $NCSquery = $xpath;
									 }
									 else{
										  $NCSquery .= "".$xpath;
									 }
								}
						  }
					 }   
				}
				
				
				//don't show "holding" items
				$noHolding = "!dcsstatus:Holding";
				if(strlen($NCSquery)>1){
					 $NCSquery .= "+AND+".$noHolding;	 
				}
				else{
					 $NCSquery = "*:*+AND+".$noHolding;
				}
				
				
				$asnId = false;
				if(isset($requestParams["asnId"])){
					 $asnId = true;
					 $xpath = "(/key//educational/benchmarks/asnId:\"".urlencode($requestParams["asnId"])."\"+OR+\"".urlencode($requestParams["asnId"])."\")";
					 if(strlen($NCSquery)>1){
						  $NCSquery .= "+AND+".$xpath;	 
					 }
					 else{
						  $NCSquery = $xpath;
					 }
				}
				
				
				if(isset($requestParams["NCSq"])){
					 $NCSquery = $requestParams["NCSq"]; //allows direct querying of the NCS repository, overrights exiting NCSqueries
				}
				elseif(isset($requestParams["qq"])){
			  
					 $encodedVal = urlencode($requestParams["qq"]);
					 $encodedVal = str_replace("%5C%27", "%27", $encodedVal); //fix apostrophe encoding issue
			  
					 if($dillDown || strlen($NCSquery)<1){ //if NCSquery is not used yet, no need to wrap the key-word search in a boolean
						  $NCSquery = $encodedVal; //allows key-word searches to be passed to the NCS repository
					 }
					 else{
						  $NCSquery = "(".$NCSquery.")+AND+(".$encodedVal.")"; //add a key-word search to the existing query to be passed to the NCS repository
					 }
			  
					 $NCSrequestURL .= $paramSep."q=".$NCSquery; //add the q parameter to the NCS request
				}
				else{
					 //"q" parameter not yet used in the request	 
					 if(!$dillDown || $asnId){
						  $NCSrequestURL .= $paramSep."q=".$NCSquery; //only do this if no drill down, otherwise don't add a q parameter
					 }
				}
				
		  }
		  
		  return $NCSrequestURL;
    }//end function




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
		  
		  $NCSrequestURL = $this->addFacetCategories($NCSrequestURL); //add facet categories
		  
		  $this->NCSrequestURL = $NCSrequestURL;
		  @$this->NCSresponse = file_get_contents($NCSrequestURL);
		  if(!$this->NCSresponse){
				echo "<h1>PROBLEM!</h1>";
				echo $NCSrequestURL;
		  }
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
			  
					 //make JSON pagination
					 $this->makeLocalBaseURI(); //make a local base URI to prep for constructing links to query on facets
					 $this->removeValue = false;
					 
					 $this->firstPageURI = $this->constructQueryURI("page", null);
			  
					 if($this->prevPage != false){
						  $this->prevPageURI = $this->constructQueryURI("page", $this->prevPage);
					 }
					 if($this->nextPage != false){
						  $this->nextPageURI = $this->constructQueryURI("page", $this->nextPage);
					 }
					 if($this->lastPage != false){
						  $this->lastPageURI = $this->constructQueryURI("page", $this->lastPage);
					 }
				}
		  }
    }//end function


    //this function constructs a local base URI to use for making links to queries that filter on facets
    function makeLocalBaseURI(){
	
		  $ServicePrefix = "http://".$_SERVER["SERVER_NAME"];
		  
		  if(strstr($this->requestURI, "?")){
				$uriX = explode("?", $this->requestURI);
				$ServicePrefix .= $uriX[0];
				$requestSuffix = "?".$uriX[1];
				$requestSuffix = str_replace("[", "%5B", $requestSuffix);
				$requestSuffix = str_replace("]", "%5D", $requestSuffix);
		  }
		  else{
				$ServicePrefix .= $this->requestURI;
				$requestSuffix = "";
		  }
		  
		  $this->JSONrequestURI = str_replace("-atom", "-json", $ServicePrefix).$requestSuffix;
		  $this->AtomRequestURI = str_replace("-json", "-atom", $ServicePrefix).$requestSuffix;
		  
		  $this->localBaseURI = $ServicePrefix;
		  return $ServicePrefix;
    }


    //this function generates a query URI
    function constructQueryURI($paramKey, $paramValue, $hierachyDown = false, $pageReset = true){
	
		  // if $hierachyDown = true, then we're displaying facets to remove
		  
		  $queryURI = $this->localBaseURI;
		  $requestParams = $this->requestParams;
		  
		  //get rid of request parameters specific to ZEND
		  unset($requestParams["controller"]);
		  unset($requestParams["action"]);
		  unset($requestParams["module"]);
		  
		  if($paramKey == "sortAscendingBy" || $paramKey == "sortDescendingBy"){
				//we're making new sorting, so remove the old
				unset($requestParams["sortAscendingBy"]);
				unset($requestParams["sortDescendingBy"]);
		  }
		  
		  if($pageReset){
				unset($requestParams["page"]); //remove a parameter for paging. new searches start on new pagination
		  }
	
	
		  if(array_key_exists($paramKey, $requestParams)){
				if(!is_array($requestParams[$paramKey])){
					 $currParamArray = array( 0=> $requestParams[$paramKey]);
				}
				else{
					 $currParamArray = $requestParams[$paramKey];
				}
				
				$updatedParamArray = array();
				$sameFound = false;
				foreach($currParamArray as $actExistingVal){
					 if($this->samePath($paramValue, $actExistingVal) && !$hierachyDown){
					 	//the same query path as already used as a filter, now update for the querying deeper down that path
					 	$updatedParamArray[] = $paramValue;
					 	$sameFound = true;
					 }
					 elseif($this->samePath($actExistingVal, $paramValue) && $hierachyDown){
						  	//case where we're making links to remove a level of the hierachy, when making links to remove current filters
						  $updatedParamArray[] = $paramValue;
						  $sameFound = true;
					 }
					 elseif(($this->removeValue != false) && ($actExistingVal == $this->removeValue)){
						  $sameFound = true; //we're removing a value from the request parameter's array of values
					 }
					 else{
						  $updatedParamArray[] = $actExistingVal;
					 }
				}//end loop checking existing array
				
				if(!$sameFound){
					 //the existing query path was not found, so add it
					 $updatedParamArray[] = $paramValue;
				}
				
				if(count($updatedParamArray) == 1){
					 //add request parameter as single value
					 $requestParams[$paramKey] = $updatedParamArray[0];
				}
				elseif(count($updatedParamArray) > 1){
					 //add request parameter as array of query values
					 $requestParams[$paramKey] = $updatedParamArray;
				}
				else{
					 unset($requestParams[$paramKey]);
				}
		  }
		  else{
				//the paramkey not already queried, add request parameter as a single value
				$requestParams[$paramKey] = $paramValue;
		  }
		  
		  
		  //remove a parameter, if it's value is null (but not in cases where we're removing a parameter's value from the query)
		  if($paramValue == null && !$this->removeValue){
				unset($requestParams[$paramKey]);
		  }
		  
		  
		  $paramSep = "?";
		  foreach($requestParams as $key => $paramValue){
				if(!is_array($paramValue)){
					 //not an array, add query value
					 $queryURI .= $paramSep.$key."=".urlencode($paramValue);
				}
				else{
					 if(count($paramValue) == 1){
						  //only one element to request parameter array, treat as a single value
						  $queryURI .= $paramSep.$key."=".urlencode($paramValue[0]);
					 }
					 else{
						  //request parameter has an array of query values
						  foreach($paramValue as $value){
								$queryURI .= $paramSep.$key.urlencode("[]")."=".urlencode($value);
								$paramSep = "&";
						  }
					 }
				}
				
				$paramSep = "&";
		  }
		  
		  return $queryURI;
    }


    //function checks to see if the new path (for a hierarchic search term)
    //is going "deeper" into the hierarchy for an existing search-term already requested
    function samePath($newPath, $oldPath){
	
		  $samePath = false;
		  $newLen = strlen($newPath);
		  $oldLen = strlen($oldPath);
		  if($oldLen <= $newLen){
				if(substr($newPath, 0, $oldLen) == $oldPath){
					 $samePath = true;
				}
		  }
		  return $samePath;
    }


    //read NCS XML data to get facet information
    function parseXMLfacets($NCSresponse){
	
		  if($NCSresponse){
				
				$VocabObj = new CowVocabs; //needed to add display values to facets
				
				$xml = simplexml_load_string($NCSresponse);
				foreach($this->NCSnamespaces as $prefix => $nsURI){
					 $xml->registerXPathNamespace($prefix, $nsURI); //register all the needed namespaces for XPATH
				}
		  
				if($xml->xpath("/ddsws:DDSWebService/ddsws:Search/ddsws:resultInfo/ddsws:offset")){
					 $facets = array();
			  
					 $schemaToFacetsArray = $this->schemaToFacetsArray;
					 $this->makeLocalBaseURI(); //make a local base URI to prep for constructing links to query on facets
					 $this->removeValue = false;
					 $requestParams = $this->requestParams;
			  
					 foreach($xml->xpath("/ddsws:DDSWebService/ddsws:Search/ddsws:facetResults/ddsws:facetResult[@count != '0']") as $xresult) {
						  
						  foreach($this->NCSnamespaces as $prefix => $nsURI){
								$xresult->registerXPathNamespace($prefix, $nsURI); //register all the needed namespaces for XPATH
						  }
						  
						  $actFacet = array();
						  foreach($xresult->xpath("./@category") as $xresultB) {
								$actFacet["category"] = (string)$xresultB;
								$actCategory = $actFacet["category"];
						  }
						  
						  if(array_key_exists($actCategory, $schemaToFacetsArray)){
								$actFacet["displayLabel"] = $schemaToFacetsArray[$actCategory]["displayLabel"];
						  }
							  
						  $actFacet["pathDelimiter"] = false;
						  $actDelim = false;
						  foreach($xresult->xpath("./@pathDelimiter") as $xresultB) {
								$actFacet["pathDelimiter"] = (string)$xresultB;
								$actDelim = $actFacet["pathDelimiter"];
						  }
						  
						  $actResults = array();
						  foreach($xresult->xpath("ddsws:result") as $xresultB) {
								$actResult = array();
								foreach($xresultB->xpath("./@path") as $xresultC) {
									 $actResult["value"] = (string)$xresultC;
								}
								foreach($xresultB->xpath("./@count") as $xresultC) {
									 $actResult["count"] = ((string)$xresultC) + 0;
								}
						 
								$actResult["href"] = $this->constructQueryURI($actCategory, $actResult["value"]);
						 
								$addFacetResult = true;
								if(array_key_exists($actCategory, $requestParams) && $actDelim != false){
									 
									 $actPathX = explode($actDelim, $actResult["value"]);
									 
									 //a query parameter may be an array, so treat all like arrays
									 if(!is_array($requestParams[$actCategory])){
										  $currParamArray = array( 0=> $requestParams[$actCategory]);
									 }
									 else{
										  $currParamArray = $requestParams[$actCategory];
									 }
									 
									 //check to see if you have an exact match in the paths currently queried
									 //if an exact match is found, don't show the facet that aren't as deep in the hierarchy
									 $pathMatches = array();
									 foreach($currParamArray as $actCurParam){
										  $currentParamX = explode($actDelim, $actCurParam);
										  //$actResult["currentParamX"] = $currentParamX ;
										  //$actResult["actPathX"] = $actPathX ;
										  //$actResult["samePath"] = $this->samePath($actResult["value"], $requestParams[$actCategory]);
									
										  /*
										  This next bit checks to make sure that we don't display facets that aren't a deep in the hierarchy
										  of a path that is currently being filtered
										  */
										  $actPathXcount = count($actPathX);
										  if($actPathXcount <= count($currentParamX)){
												$allSame = true;
												$i = 0;
												while($i < $actPathXcount){
													 if($actPathX[$i] != $currentParamX[$i]){
														  $allSame = false; //paths are not an exact match, allow the currently active path to be shown.
													 }
												$i++;
												}//end of while loop
												if($allSame){
													 $pathMatches[] = $actCurParam;
												}
										  }
									 }
							  
									 if(count($pathMatches)>0){
										  $addFacetResult = false;
									 }
							  
								}
						 
								if($addFacetResult){
									 $actResults[] = $actResult; //add the result if the
								}
						  }//end loop through results
						  
						  //now process results to have display values (if needed)
						  $VocabObj->getVocabTerms($actCategory);
						  $actResults = $VocabObj->addDisplayValues($actResults);
						  if(!$this->numericSortFacets){
								$actResults = $VocabObj->sortValues($actResults); //resort facets to schema enumeration order, or alphabetic
						  }
						  
						  $actFacet["result"] = $actResults;
						  $facets[$actCategory] = $actFacet;
					 }
					 $this->facets = $facets;
				}
				else{
					 $this->facets = false;
				}
		  }
    }


    
    //make some useful information about the filters applied to the current search
    //this is done AFTER we've processed results from the NCS service, since
    //we need those data to inform us about our search (esp. about if a field has hierarchic paths for values)
    function describeExistingFilters(){
	
		  $VocabObj = new CowVocabs; //needed to add display values to facets
		  $paramConfig = $this->paramConfig; 	//array of standard parameters for querying this service
		  $requestParams = $this->requestParams;	//requested parameters made by the client's current search
		  $facets = $this->facets; //array of facets (that may be search parameters for querying this service)
		  
		  //get rid of request parameters specific to ZEND
		  unset($requestParams["controller"]);
		  unset($requestParams["action"]);
		  unset($requestParams["module"]);
		  unset($requestParams["callback"]);
		  
		  unset($requestParams["page"]);
		  unset($requestParams["sort"]);
		  unset($requestParams["sortAscendingBy"]);
		  unset($requestParams["sortDescendingBy"]);
		  unset($requestParams["facetSort"]);
		  
		  $existingFilters = array();
		  
		  foreach($requestParams as $key => $reqValues){
				
				if(!is_array($reqValues)){
					 $reqValues = array(0 => $reqValues); //make single search strings an array, since some search parameters may be arrays
				}
				
				foreach($reqValues as $value){
			  
					 $actFilter = array();
					 $actFilter["parameter"] = $key;
					 $actFilter["value"] = $value;
					 
					 $VocabObj->getVocabTerms($key);
					 $displayValue = $VocabObj->getDisplayValue($value);
					 if($displayValue != false){
						  $actFilter["displayValue"] = $displayValue; //show human friendly display values
					 }
					 else{
						   $actFilter["displayValue"] = $value;
					 }
					 
					 $this->removeValue = $value;
					 $actFilter["HREFremove"] = $this->constructQueryURI($key, null);
			  
					 if(is_array($facets)){
						  if(array_key_exists($key, $facets)){
								$actFilter["displayLabel"] = $facets[$key]["displayLabel"];
								$actFilter["hierarchy"] = $facets[$key]["pathDelimiter"];
						  }
						  elseif(array_key_exists($key, $paramConfig)){
								$actFilter["displayLabel"] = $paramConfig[$key]["displayLabel"];
								$actFilter["hierarchy"] = $paramConfig[$key]["pathDelimiter"];
						  }
						  else{
								$actFilter["hierarchy"] = false;
						  }
					 }
					 else{
						  $actFilter["hierarchy"] = false;
						  if(array_key_exists($key, $paramConfig)){
								$actFilter["displayLabel"] = $paramConfig[$key]["displayLabel"];
						  }
						  else{
								$actFilter["displayLabel"] = "Query parameter - $key - not supported";
						  }
					 }
			  
					 if($actFilter["hierarchy"] != false){
						  //the filter has an internal hierarchy, make links to allow movement through levels of the hierachy
						  if(strstr($value, $actFilter["hierarchy"])){
						  
								$hierachyArray = array();
								$hierarchyVals = explode($actFilter["hierarchy"], $value);
								$actPath = null;
								$hierachyLevels = count($hierarchyVals);
								$i = 0;
								while($i < $hierachyLevels){
									 
									 if($i == 0){
									$actPath = $hierarchyVals[0];
									$this->removeValue = $value;
									$actHREF = $this->constructQueryURI($key, null);
									 }
									 else{
									$this->removeValue = false;
									$actHREF = $this->constructQueryURI($key, $actPath, true);
									$actPath .= $actFilter["hierarchy"].$hierarchyVals[$i];
									 }
									 
									 $hierachyArray[$i] = array("value" => $actPath, "HREFlevelDown" => $actHREF);
									 
									 $i++;
								}
								
								$actFilter["hierarchy"] = $hierachyArray;
						  }
						  else{
								//only at first level of the hierarchy
								$actFilter["hierarchy"] = false;
						  }
					 }
			  		 $existingFilters[] = $actFilter;
			  
				}//end loop through request values
		  
		  } //end loop through request parameters
		  
		  $this->existingFilters = $existingFilters;
	
    }//end function



    //dates sometimes come through as just years. convert to mid year (to guestimate a year-month-day) to aid date calculations / sorting
    function shortDateFix($checkDate){
		  if(strlen($checkDate) == 4){
				$checkDate .= "-06-15";
		  }
		  return $checkDate;
    }


    
    function parseXMLresults($NCSresponse){
	
		  if($NCSresponse){
				
				$xml = simplexml_load_string($NCSresponse);
				foreach($this->NCSnamespaces as $prefix => $nsURI){
					 $xml->registerXPathNamespace($prefix, $nsURI); //register all the needed namespaces for XPATH
				}
		  
				//get the records
				$records = array();
				$schemaArray = $this->schemaArray;
				if($xml->xpath("/ddsws:DDSWebService/ddsws:Search/ddsws:results/ddsws:record/ddsws:metadata")){
					 foreach($xml->xpath("/ddsws:DDSWebService/ddsws:Search/ddsws:results/ddsws:record/ddsws:metadata") as $xmlItem){
						  
						  foreach($this->NCSnamespaces as $prefix => $nsURI){
								$xmlItem->registerXPathNamespace($prefix, $nsURI); //register all the needed namespaces for XPATH
						  }
						  
						  $record = $this->queryAgainstSchema($xmlItem, $schemaArray);
						  $NCSid = $record["general"]["recordID"]["values"][0]["value"];
						  $record["metadataURI"] = $this->NCSrecordURI($NCSid);
						  
						  //a little logic for last updated
						  if(isset($record["authorshipRightsAccessRestrictions"]["date"]["values"][0]["value"])){
								$checkDate = $record["authorshipRightsAccessRestrictions"]["date"]["values"][0]["value"];
								$checkDate = $this->shortDateFix($checkDate);
						 		if (($timestamp = strtotime($checkDate)) === false) {
									 $calendardTest = false;
								}
								else{
									 $calendardTest = true;
								}
							  
								if($calendardTest){
									 if($timestamp > strtotime($this->lastUpdated)){
										  $this->lastUpdated = date("Y-m-d\TH:i:s\-07:00", $timestamp);
									 }
								}
						  }
						  
						  //prepend base URL to images
						  if(isset($record["general"]["imageUrl"]["values"][0]["value"])){
								$record["general"]["imageUrl"]["values"][0]["value"] = self::imageBaseURL.$record["general"]["imageUrl"]["values"][0]["value"];
						  }
						  
						  
						  $records[] = $record;
					 }
				}
				
				if(count($records)>0){
					 $this->results = $records;
				}
				else{
					 $this->results = false;
				}
		  }
    }


    //recursive function that uses the NCS schem to query against the XML result returned from a search
    //outputs a PHP array which can later be expressed as JSON, Atom, etc.
    function queryAgainstSchema($xmlItem, $schemaArray, $singleValue = false){
	
		  $multiValueParentElements = $this->multiValueParentElements;
		  $VocabObj = new CowVocabs; //needed to add display values to facets
		  
		  $record = array();
		  foreach($schemaArray as $key => $subArray){
				
				if(isset($subArray["children"])){
					 if(!$subArray["children"]){
						  $public = true;
						  if(isset($subArray["public"]) && !$this->displayAllResultMetadata){ //if we're not displaying all metadata elements, check if public
								$public = $subArray["public"];
						  }
						  
						  if($this->elementValueLimit != false){
								if(strstr($subArray["xpath"], "/@")){
									 //make xpath select the right node number for the element of the attribute
									 $replaceVal = "[".$this->elementValueLimit."]/@";
									 $subArray["xpath"] = str_replace("/@", $replaceVal, $subArray["xpath"]);
								}
								else{
									 //make xpath select the right node number for the parent element
									 foreach($multiValueParentElements as $parElement){
										  $replaceVal = $parElement."[".$this->elementValueLimit."]/";
										  $subArray["xpath"] = str_replace($parElement."/", $replaceVal, $subArray["xpath"]);
									 }
								}
								//echo $subArray["xpath"];
						  }
						  
						  if($xmlItem->xpath($subArray["xpath"]) && $public){
								$foundValue = false;
								$record[$key]["displayLabel"] = $subArray["displayLabel"];
								
								foreach($xmlItem->xpath($subArray["xpath"]) as $node){
									 $foundValue = (string)$node;
									 if(!$singleValue){
										  $record[$key]["values"][]["value"] = $foundValue; //values can be an array, as when querying XML elements
									 }
									 else{
										  //$record[$key]["xpath"] = $subArray["xpath"];
										  $record[$key]["value"] = $foundValue; //value not in an array, as XML attribute
										  $VocabObj->getVocabTerms($key);
										  $displayValue = $VocabObj->getDisplayValue($foundValue);
										  if($displayValue != false){
												$record[$key]["displayValue"] = $displayValue;
										  }
									 }
								}
								
								//add display values to the list of values
								if(isset($record[$key]["values"])){
									 $actValues = $record[$key]["values"];
									 $VocabObj->getVocabTerms($key);
									 $record[$key]["values"] = $VocabObj->addDisplayValues($actValues);
								}
						 
								if(is_array($subArray["attributes"])){
									 $nodeNum = 1;
									 $newValueArray = array();
									 foreach($record[$key]["values"] as $valArray){
										  //$this->elementValueLimit = $valArray["value"];
										  $this->elementValueLimit = $nodeNum;
										  $valArray["attributes"] = $this->queryAgainstSchema($xmlItem, $subArray["attributes"], true);
										  $newValueArray[] = $valArray;
										  $nodeNum++;
									 }
									 $record[$key]["values"] = $newValueArray;
									 $this->elementValueLimit = false;
								}
						  }
					 }
					 else{
						  
						  //if(false){
						  if(in_array($key, $multiValueParentElements)){
								//$key element can be used multiple times
								$public = true;
								if($xmlItem->xpath($subArray["xpath"]) && $public){
									 $nodeNum = 1;
									 foreach($xmlItem->xpath($subArray["xpath"]) as $node){
										  $this->elementValueLimit = $nodeNum;
										  $record[$key][] = $this->queryAgainstSchema($xmlItem, $subArray["children"], true);
										  $nodeNum++;
									 }
									 $this->elementValueLimit = false;
								}
								else{
									 //$record[$key] = $this->queryAgainstSchema($xmlItem, $subArray["children"]);
								}
						  }
						  else{
								//$key element used only once
								$record[$key] = $this->queryAgainstSchema($xmlItem, $subArray["children"]);
						  }
						  
					 }
				}
		  }
		  
		  return $record;
    }
    

    //make a URI to get the NCS record for an item
    function NCSrecordURI($recordID){
		  return self::baseURL."?verb=GetRecord&id=".$recordID;
    }


    //map public domain to CC-zeo URI
    function publicDomainCCzero($actResult){
		  $publicDomain = false;
		  if(isset($actResult["authorshipRightsAccessRestrictions"]["rights"]["propertyRights"]["values"])){
				foreach($actResult["authorshipRightsAccessRestrictions"]["rights"]["propertyRights"]["values"] as $IPvalues){
					 if(stristr($IPvalues["value"], "public domain")){
						  $publicDomain = true;
					 }
				}
		  }
	
		  if($publicDomain){
				return array("rel" => "license",
					"href" => "http://creativecommons.org/publicdomain/zero/1.0/",
					"title" => "This resource is Public Domain, as indicated by a Creative Commons Zero Dedication");
		  }
		  else{
				return false;
		  }
    }


	 function makeCurrentSortOptions(){
		  
		  $sortFields = $this->sortFields;
		  $requestParams = $this->requestParams;
		  //get rid of unwanted request parameters
		  unset($requestParams["controller"]);
		  unset($requestParams["action"]);
		  unset($requestParams["module"]);
		  unset($requestParams["callback"]);
		  
		  unset($requestParams["page"]);
		  unset($requestParams["sort"]);
		  
		  $currentSortOptions = array();
		  foreach($sortFields as $valKey => $fieldArray){
				$name = $fieldArray["display"];
				if($fieldArray["NCS"] != false){
					 $currentSortOptions[$name] = array(	"HREFsortAscendingBy" =>  $this->constructQueryURI("sortAscendingBy", $valKey),
																"HREFsortDescendingBy" =>  $this->constructQueryURI("sortDescendingBy", $valKey) );
				}
				else{
					 //relevance search, only the default sort descending by relevance
					 $this->removeValue = false;
					 $currentSortOptions[$name] = array("HREFsortDescendingBy" =>  $this->constructQueryURI("sortDescendingBy", null) );
					 $this->removeValue = true;
				}
		  }
		  
		  //finish up the facet sorting array
		  $facetSorting = $this->facetSorting;
		  $facetSorting["HREFfacetTermSort"] = $this->constructQueryURI("facetSort", 1);
		  $facetSorting["HREFfacetCountSort"] = $this->constructQueryURI("facetSort", null);
		  $this->facetSorting = $facetSorting;
		  
		  $this->currentSortOptions = $currentSortOptions;
	 }

}//end class








?>
