#!/usr/local/bin/php
<?
$SERVER_PATH_MATRIX = array(
	"/" =>	"hello_world"		,
);

error_reporting(E_ALL);


require dirname(__FILE__) . "/micro_httpd.conf.php";

$SERVER_CONFIG["bind"]		= "0.0.0.0:82";
$SERVER_CONFIG["max_workers"]	= 8;
//$SERVER_CONFIG["debug"]		= true;
$SERVER_CONFIG["log_file"]	= "z_hello_world.log";
$SERVER_CONFIG["log_file_level"] = 100;

require dirname(__FILE__) . "/micro_httpd.modules.php";
require dirname(__FILE__) . "/micro_httpd.php";



function hello_world($path, $query_string_parsed, $query_string){
	return "<p>hello world</p>\n";
}
