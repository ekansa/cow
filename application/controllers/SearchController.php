<?php
/** Zend_Controller_Action */
require_once 'Zend/Controller/Action.php';

class SearchController extends Zend_Controller_Action
{   
    
    //load the needed classes
    function init(){
        Zend_Loader::loadClass('NCS'); //defined in User.php
    }
    
    public function indexAction()
    {
	$this->_helper->viewRenderer->setNoRender();
	echo "<h1>Moo!</h1>";
    }
    
    public function ncsAction(){
	$this->_helper->viewRenderer->setNoRender();
	$requestParams =  $this->_request->getParams();
	
	$NCSobj = new NCS;
	$NCSobj->requestParams = $requestParams;
	$NCSobj->prepNCSsearch();
	$NCSobj->NCSsearch();
	$NCSobj->parseXMLnumbers($NCSobj->NCSresponse);
	$NCSobj->parseXMLresults($NCSobj->NCSresponse);
	
	header('Content-Type: application/json; charset=utf8');
	echo Zend_json::encode($NCSobj);
    }
    
    public function ncsAtomAction(){
	//$this->_helper->viewRenderer->setNoRender();
	$requestParams =  $this->_request->getParams();
	
	$NCSobj = new NCS;
	$NCSobj->requestParams = $requestParams;
	$NCSobj->prepNCSsearch();
	$NCSobj->NCSsearch();
	$NCSobj->parseXMLnumbers($NCSobj->NCSresponse);
	$NCSobj->parseXMLresults($NCSobj->NCSresponse);
	
	$this->view->requestURI = $this->_request->getRequestUri(); //URI of current request
	$this->view->requestParams = $requestParams;
	$this->view->NCSobj = $NCSobj;
    }
    
   
   public function xsdAction(){
	$this->_helper->viewRenderer->setNoRender();
	
	Zend_Loader::loadClass('SchemaNCScow'); //defined in User.php
	$SchemaNCScow = new SchemaNCScow;
	$SchemaNCScow->getXSD();
	$SchemaNCScow->getFieldList();
	if($SchemaNCScow->xsdString != false){
	    $SchemaNCScow->getXSDelements();
	}
	if(is_array($SchemaNCScow->elements) && $SchemaNCScow->fieldListXML != false){
	    $SchemaNCScow->parseFieldList();
	}
	header('Content-Type: application/json; charset=utf8');
	echo Zend_Json::encode($SchemaNCScow->elements);
   }
   
   public function xsdSchemaAction(){
	$this->_helper->viewRenderer->setNoRender();
	
	Zend_Loader::loadClass('SchemaNCScow'); 
	$SchemaNCScow = new SchemaNCScow;
	$SchemaNCScow->getXSD();
	if($SchemaNCScow->xsdString != false){
	    $SchemaNCScow->parseXSD();
	}
	header('Content-Type: application/json; charset=utf8');
	echo Zend_Json::encode($SchemaNCScow->schema);
   }
   
   public function xmlAction(){
	$this->_helper->viewRenderer->setNoRender();
	
	$recordID = "cow-test-000-000-000-028";
	if(isset($_GET["id"])){
	    $recordID = $_GET["id"];
	}
	
	Zend_Loader::loadClass('CowRecord'); 
	Zend_Loader::loadClass('SchemaNCScow'); 
	$CowRecord = new CowRecord;
	$CowRecord->getMetadataXML($recordID);
	if($CowRecord->xmlString){
	    $CowRecord->parseXML();
	}
	
	header('Content-Type: application/json; charset=utf8');
	if(is_array($CowRecord->errors)){
	    echo Zend_Json::encode($CowRecord->errors);
	}
	else{
	    echo Zend_Json::encode($CowRecord->recordData);
	}
   }
   
   
   public function cowFacetsAction(){
	$this->_helper->viewRenderer->setNoRender();
	$requestParams =  $this->_request->getParams();
	
	Zend_Loader::loadClass('NCSfacetedSearch'); //class to get schema information
	Zend_Loader::loadClass('SchemaNCScow'); //class to get schema information
	$SchemaNCScow = new SchemaNCScow;
	$SchemaNCScow->getXSD();
	$SchemaNCScow->getFieldList();
	if($SchemaNCScow->xsdString != false){
	    $SchemaNCScow->getXSDelements();
	    $SchemaNCScow->parseXSD();
	}
	if(is_array($SchemaNCScow->elements) && $SchemaNCScow->fieldListXML != false){
	    $SchemaNCScow->parseFieldList();
	}
	
	$NCSobj = new NCSfacetedSearch;
	$NCSobj->requestParams = $requestParams;
	$NCSobj->schemaToFacetsArray = $SchemaNCScow->elements;
	$NCSobj->schemaArray = $SchemaNCScow->schema;
	$NCSobj->prepNCSsearch();
	$NCSobj->NCSsearch();
	
	//header('Content-Type: application/json; charset=utf8');
	//echo Zend_Json::encode($SchemaNCScow->elements);
	header('Content-Type: application/xml; charset=utf8');
	echo $NCSobj->NCSresponse;
	//echo $NCSobj->NCSrequestURL;
   }
   
   
   public function cowFacetsJsonAction(){
	$this->_helper->viewRenderer->setNoRender();
	$requestParams =  $this->_request->getParams();
	$requestURI = $this->_request->getRequestUri();
	
	Zend_Loader::loadClass('NCSfacetedSearch'); //class to get schema information
	Zend_Loader::loadClass('SchemaNCScow'); //class to get schema information
	$SchemaNCScow = new SchemaNCScow;
	$SchemaNCScow->getXSD();
	$SchemaNCScow->getFieldList();
	if($SchemaNCScow->xsdString != false){
	    $SchemaNCScow->getXSDelements();
	    $SchemaNCScow->parseXSD();
	}
	if(is_array($SchemaNCScow->elements) && $SchemaNCScow->fieldListXML != false){
	    $SchemaNCScow->parseFieldList();
	}
	
	$NCSobj = new NCSfacetedSearch;
	$NCSobj->requestParams = $requestParams;
	$NCSobj->requestURI = $requestURI;
	$NCSobj->schemaToFacetsArray = $SchemaNCScow->elements;
	$NCSobj->schemaArray = $SchemaNCScow->schema;
	$NCSobj->prepNCSsearch();
	$NCSobj->NCSsearch();
	$NCSobj->parseXMLnumbers($NCSobj->NCSresponse);
	$NCSobj->parseXMLfacets($NCSobj->NCSresponse);
	$NCSobj->parseXMLresults($NCSobj->NCSresponse);
	$NCSobj->describeExistingFilters();
	
	$output = array();
	$output["totalNumResults"] = $NCSobj->totalNumResults;
	$output["numReturned"] = $NCSobj->numReturned;
	$output["lastUpdated"] = $NCSobj->lastUpdated;
	$output["pagination"] = array("HREFfirstPage" => $NCSobj->firstPageURI,
				      "HREFprevPage" => $NCSobj->prevPageURI,
				      "HREFnextPage" => $NCSobj->nextPageURI,
				      "HREFlastPage" => $NCSobj->lastPageURI
				      );
	$output["HREFatom"] = $NCSobj->AtomRequestURI;
	$output["NCSrequest"] = $NCSobj->NCSrequestURL;
	$output["existingFilters"] = $NCSobj->existingFilters;
	$output["facets"] = $NCSobj->facets;
	$output["results"] = $NCSobj->results;
	
	if(!isset($requestParams["callback"])){
	    header('Content-Type: application/json; charset=utf8');
	    echo Zend_Json::encode($output);
	}
	else{
	    header('Content-Type: application/javascript; charset=utf8');
	    echo $requestParams["callback"]."(".Zend_Json::encode($output).");";
	}
	//echo $NCSobj->NCSrequestURL;
   }
   



   public function cowFacetsAtomAction(){
	
	$requestParams =  $this->_request->getParams();
	$requestURI = $this->_request->getRequestUri();
	
	Zend_Loader::loadClass('NCSfacetedSearch'); //class to get schema information
	Zend_Loader::loadClass('SchemaNCScow'); //class to get schema information
	$SchemaNCScow = new SchemaNCScow;
	$SchemaNCScow->getXSD();
	$SchemaNCScow->getFieldList();
	if($SchemaNCScow->xsdString != false){
	    $SchemaNCScow->getXSDelements();
	    $SchemaNCScow->parseXSD();
	}
	if(is_array($SchemaNCScow->elements) && $SchemaNCScow->fieldListXML != false){
	    $SchemaNCScow->parseFieldList();
	}
	
	$NCSobj = new NCSfacetedSearch;
	$NCSobj->requestParams = $requestParams;
	$NCSobj->requestURI = $requestURI;
	$NCSobj->schemaToFacetsArray = $SchemaNCScow->elements;
	$NCSobj->schemaArray = $SchemaNCScow->schema;
	$NCSobj->prepNCSsearch();
	$NCSobj->NCSsearch();
	$NCSobj->parseXMLnumbers($NCSobj->NCSresponse);
	$NCSobj->parseXMLfacets($NCSobj->NCSresponse);
	$NCSobj->parseXMLresults($NCSobj->NCSresponse);
	$NCSobj->describeExistingFilters();
	
	$this->view->NCSobj = $NCSobj;
	
   }
   









   
   
}


