<?php
error_reporting(E_ALL|E_STRICT);
mb_internal_encoding( 'UTF-8' );

/*
@$path = "/public_html/cow";
set_include_path(get_include_path() .PATH_SEPARATOR. $path);@
*/

date_default_timezone_set('America/Los_Angeles');
/*
$webroot = $_SERVER['DOCUMENT_ROOT'];
//echo $webroot;

if($webroot == "/home/alexan25/public_html/cow/public"){
    set_include_path(get_include_path().PATH_SEPARATOR.'/home/alexan25/public_html/cow/library'
                    . PATH_SEPARATOR . '../application/models/'
                    . PATH_SEPARATOR . '../application/controllers/'
                     );
    
}
else{
    set_include_path('.' . PATH_SEPARATOR . '../library/'
. PATH_SEPARATOR . '../application/models/'
. PATH_SEPARATOR . '../application/controllers/'

. PATH_SEPARATOR . get_include_path());
}
*/

set_include_path('.' . PATH_SEPARATOR . '../library/'
. PATH_SEPARATOR . '../application/models/'
. PATH_SEPARATOR . '../application/controllers/'

. PATH_SEPARATOR . get_include_path());


require_once "Zend/Loader/Autoloader.php";

$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('Zend_');
//$autoloader->registerNamespace('OpenContext_');



// load configuration
$config = new Zend_Config_Ini('../application/config.ini', 'general');
$registry = Zend_Registry::getInstance();
$registry->set('config', $config);

// setup database
$db = Zend_Db::factory($config->db->adapter,
$config->db->config->toArray());
Zend_Db_Table::setDefaultAdapter($db); 
Zend_Registry::set('db', $db);


// setup controller
$frontController = Zend_Controller_Front::getInstance();


// Custom routes
$router = $frontController->getRouter();
$frontController->throwExceptions(true);
$frontController->setControllerDirectory('../application/controllers');
try {
    $frontController->dispatch();

}catch (Exception $e){
    // handle exceptions yourself
    echo $e;
}

?>