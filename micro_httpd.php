<?
/*
Simple pre-forking web server

Based on:
	https://github.com/dhotson/httpparser-php/blob/master/simple_server.php
	http://stackoverflow.com/questions/2942473/php-miniwebsever-file-download
*/



// Version

$server_version      = 1.1;
$server_version_full = "Micro HTTPD 1.1 / 2011-11-18";

ini_set("max_execution_time", "0");
ini_set("max_input_time",     "0");
set_time_limit(0);
ob_implicit_flush();

error_reporting(E_ALL);

// Checking command line arguments

if (@$argv[1])
	$SERVER_CONFIG["bind"] = $argv[1];
	
if (@$argv[2])
	$SERVER_CONFIG["max_workers"] = (int)$argv[2];

server_start();







// =======================================
// Server process
// =======================================

function server_start(){
	global $SERVER_CONFIG;
		
	// Check configuration
	
	if (@$SERVER_CONFIG["server_limit"] < 8)
		$SERVER_CONFIG["server_limit"] = 8;

	if (@$SERVER_CONFIG["max_workers"] < 8)
		$SERVER_CONFIG["max_workers"] = 8;

	if ($SERVER_CONFIG["max_workers"] > $SERVER_CONFIG["server_limit"])
		$SERVER_CONFIG["max_workers"] = $SERVER_CONFIG["server_limit"];

	if (@$SERVER_CONFIG["max_request_per_child"] < 10 || @$SERVER_CONFIG["max_request_per_child"] > 1000000)
		$SERVER_CONFIG["max_request_per_child"] = 1000000;

	if (@$SERVER_CONFIG["max_request_size"] < 1024)
		$SERVER_CONFIG["max_request_size"] = 1024;

	if (!@$SERVER_CONFIG["default_mime_type"])
		$SERVER_CONFIG["default_mime_type"] = "text/html";

	if (!@$SERVER_CONFIG["log_file"])
		$SERVER_CONFIG["log_file"] = "/dev/null";

	if (!@$SERVER_CONFIG["chdir"])
		$SERVER_CONFIG["chdir"] = "/";



	// Create the log file
	global $server_log_file;

	if ( @$SERVER_CONFIG["demon"] )
		$SERVER_CONFIG["log_file"] = "php://stdout";

	$fmode = "a";
	if (!@$SERVER_CONFIG["log_file_reset"])
		$fmode = "w";

	$server_log_file = @fopen($SERVER_CONFIG["log_file"], $fmode);



	// Parse host:port

	@list($host, $port) = explode(":", $SERVER_CONFIG["bind"]);

	if (!$host)
		$host = "0.0.0.0";
	
	if (!$port)
		$port = 9000;



	// Create a socket

	if (!($server_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)))
		server_log_halt( socket_strerror(socket_last_error()) );


	if (!socket_set_option($server_socket, SOL_SOCKET, SO_REUSEADDR, 1))
		server_log_halt( socket_strerror(socket_last_error()) );

	if (!socket_set_option($server_socket, SOL_SOCKET, SO_RCVTIMEO, 
						array("sec" => 5, "usec" => 0)) )
		server_log_halt( socket_strerror(socket_last_error()) );

	if (!socket_bind($server_socket, $host, $port))
		server_log_halt( socket_strerror(socket_last_error()), 1 );

	if (!socket_listen($server_socket))
		server_log_halt( socket_strerror(socket_last_error()), 1 );



	// UID 0 check, chroot, setuid...
	if ( @$SERVER_CONFIG["demon"] ){
		server_log("Server will start in NOT demon mode for development");
	}else{
		server_security_preparation();
	}



	global $server_version_full;
	
	server_log("$server_version_full started @ $host:$port with {$SERVER_CONFIG["max_workers"]} workers");
	server_debug("Begin fork() children");



	// Main loop

	$procs = array();

	while(true){
		if ( -1 == ($pid = pcntl_fork()) ){
			// error
			server_log_halt("Can not fork()");
		}else if ($pid == 0){
			// child process
			server_child_process($server_socket);
			exit(0);
		}else{
			// parent process
			$procs[$pid] = $pid;

			server_debug("fork()-ed process $pid");
		}

		if (count($procs) >= $SERVER_CONFIG["max_workers"]){
			if (-1 == ($pid = pcntl_wait($status)))
				server_log_halt("Can not pcntl_wait()");

			//Returns the return code of a terminated child
			//$exitStatus = pcntl_wexitstatus($status);
			unset($procs[$pid]);
		}

	//	print_r($procs);

		server_log(count($procs) . " running processes...", 1);

		// Give the CPU some time
		usleep(1000 * 100); // 1000 * 1000 is 1 sec
	}
	
}



// =======================================
// Child process
// =======================================

function server_child_process($server_socket){
	global $SERVER_CONFIG;

	server_debug("Child started...");

	$req_count     = 0;
	$req_count_max = (int) @$SERVER_CONFIG["max_request_per_child"];

//	while ($client = @socket_accept($server_socket)){
	while (true){
		if ( ( $client = @socket_accept($server_socket) ) === false ){
		//	server_debug("Can not socket_accept()...");
			usleep(1000 * 1); // 1000 * 1000 is 1 sec
			continue;
		}

		server_debug("Child socket_accept(), request $req_count...");

		$input = trim(socket_read($client, $SERVER_CONFIG["max_request_size"]));

		// parse HTTP
		$input = explode(" ", $input);	// GET /index.php HTTP/1.0

		if ( $file_part = trim(@$input[1]) ){
			$buffer = request_process($file_part);
			@socket_write($client, $buffer);
		}

		@socket_close($client);

		server_debug("Child socket_close()...");

		if ($req_count_max > 0){
			$req_count++;

			if ($req_count > $req_count_max){
				server_debug("Child performed {$req_count_max} requests and will exit");
				exit;
			}
		}
	}
}



// =======================================
// Request
// =======================================

function request_process($request){
	global $SERVER_CONFIG;

	@list($file, $query_string) = explode("?", $request);
	
	parse_str($query_string, $query_string_parsed);
	
	
	
	$output = get_file_process($file, $query_string_parsed, $query_string);
	
	
	
	// fixing output
	if (! is_array($output) ){
		$output = array(
			"code"		=> 200			,
			"codetxt"	=> "OK"			,
			"mime"		=> $SERVER_CONFIG["default_mime_type"]	,
			"body"		=> $output		,
			"headers"	=> array()
		);
	}
	
	// fix HTTP code
	if (@$output["code"] == 0 || @$output["codetxt"] == ""){
		$output["code"   ] = 200;
		$output["codetxt"] = "OK";
	}
	
	// fix mime type
	if (@$output["mime"] == "")
		$output["mime"] = $SERVER_CONFIG["default_mime_type"];
	
	// prepare HTTP response "Content-Length"
	$buffer_size = strlen(@$output["body"]);
	
	// prepare HTTP response
	$buffer  = "HTTP/1.0 {$output["code"]} {$output["codetxt"]}"	. "\r\n";
	
	$buffer .= "Content-type: {$output["mime"]}"			. "\r\n";
	$buffer .= "Content-Length: $buffer_size"			. "\r\n";

	if (@$SERVER_CONFIG["server_name"])
	$buffer .= "Server: {$SERVER_CONFIG["server_name"]}"		. "\r\n";

	$buffer .= "Connection: close"					. "\r\n";

	if (@$output["headers"])	
	foreach($output["headers"] as $h)
		$buffer .= $h . "\r\n";
	
	$buffer .= ""							. "\r\n";
	
	$buffer .= $output["body"];    

	server_log("Req: $file, $query_string, {$output["mime"]}, {$output["code"]} {$output["codetxt"]}",2);
	
	return $buffer;
}



// =======================================
// Get actual file
// =======================================

function get_file_process($path, $query_string_parsed, $query_string){
	global $SERVER_PATH_MATRIX;
	global $SERVER_CONFIG;

//	print_r($SERVER_PATH_MATRIX);
	
	// Call user function...
	if ( function_exists($func = @$SERVER_PATH_MATRIX[$path]) )
		return $func($path, $query_string_parsed, $query_string);
		
		
	
	// Try custom 404, if exists...
	if (@$SERVER_CONFIG["error_document_404"]){
		$path404 = $SERVER_CONFIG["error_document_404"];
		if ($func = @$SERVER_PATH_MATRIX[$path404])
			return $func($path, array(), "");
	}
	
	
	// Produce simple 404...
	return array(
			"code"		=> 404			,
			"codetxt"	=> "FILE_NOT_FOUND"	,
			"mime"		=> "text/html"		,
			"body"		=> "<p>404 - File not found</p>"
	);
}



// =======================================
// setuid() / setgid()
// =======================================

function server_security_preparation(){
	global $SERVER_CONFIG;

	$pid = pcntl_fork();
	
	if ($pid == -1){
		// error
		server_log_halt("Can not fork()...");
		exit;
	}else if ($pid){
		// parent
		exit;
	}else{
		// child becomes our daemon
	}

	// Became session leader
	posix_setsid();

	fclose(STDIN); 
	fclose(STDOUT);
	fclose(STDERR);

	// moved at the end after chroot
	//chdir('/');

	umask(0);

	if ( posix_getuid() == 0 ){

		// GID first...
		if ( $group = $SERVER_CONFIG["group"] ){
			$gid_info = posix_getgrnam($group);
			posix_setgid($gid_info["gid"]);

			if ( posix_getgid() == 0 )
				server_log_halt("Running as GID 0 ? Really?");

			server_log("setgid() to $group", 1);
		}

		// then UID...
		if ( $user = $SERVER_CONFIG["user"] ){
			$uid_info = posix_getpwnam($user);
			posix_setuid($uid_info["uid"]);

			if ( posix_getuid() == 0 )
				server_log_halt("Running as UID 0 ? Really?");

			server_log("setuid() to $user", 1);
		}
	}

	// chroot
	if ($chroot = $SERVER_CONFIG["chroot"]){
		if ( function_exists("chroot") ){
			chroot($chroot);
		//	chdir('/');

			server_log("chroot() to $chroot", 1);
		}else{
			server_log("Can not chroot(). Will try live without it.");
		}
	}

	if ( $SERVER_CONFIG["chdir"] )
		chdir( $SERVER_CONFIG["chdir"] );
}



// =======================================
// Log functions
// =======================================

function server_log($message, $level = 0){
	global $SERVER_CONFIG;

	if (@$SERVER_CONFIG["log_level"] <= $level)
		return;

	global $server_log_file;

	$prefix = date('Y-m-d H:i:s') . " " . "PID:" . posix_getpid() . " ";

	@fwrite($server_log_file, $prefix . $message . "\n");
}

function server_debug($message){
	global $SERVER_CONFIG;

	if (@$SERVER_CONFIG["debug"])
		server_log($message);
	
}

function server_log_halt($message, $code = 1){
	server_log($message);
	server_log("Server halt");
	exit($code);
}

