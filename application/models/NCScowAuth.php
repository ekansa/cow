<?php

/*
Authenticates into the CoW

*/

class NCScowAuth {
 
    const CoWUser = "ekansa"; //CoW User name
    const CoWPassword = "asdf123"; // CoW password
    const loginURL = "http://cow.lhs.berkeley.edu/ncs/auth/logon.do";
    
    //login to the CoW
    function login(){
	
	$client = new Zend_Http_Client(self::loginURL);
	/*
	$client->setHeaders('Host', $this->requestHost);
	$client->setHeaders('Accept', 'application/json');
	$client->setHeaders('AUTHORIZATION', $this->password);
	*/
	$client->setParameterPost('username', self::CoWUser);
	$client->setParameterPost('password', self::CoWPassword);
	
	$response = $client->request("POST"); //send the request, using the POST method
	return $response;
    }
    
    

}//end class








?>
