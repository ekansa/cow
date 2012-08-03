<?php

/*
Interacts with the ASN API
to get linked data about science achievement benchmarks
*/

class ASN {
 
	 public $uri; //uri of the resource requested
	 
	 public $keyFields = array("");
	 
	 
	 const cacheLife = 900000; //250 hour cache life. because why not
	 const cacheDir = "./ASNcache/"; //directory of the cache of ASN data
    
	 


}//end class

?>
