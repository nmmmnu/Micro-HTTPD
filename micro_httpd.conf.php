<?

$SERVER_CONFIG = array(

// $server_limit: internal maximal socket connections
// default: 8
// recommended: 64-1024
// minimum: 8
// note: must be less than "sysctl -a | grep net.core.somaxconn"

"server_limit"		=>	1024		,



// $bind: where to bind()
// default: 0.0.0.0:9000
// argv: can be changed with argv[1]

"bind"			=>	"0.0.0.0:9000"	,



// $demon: (not) demonize (option value is reversed)
// default: false
// note: because of PHP "issue", you need always to use the server as demon mode.
// false - demonize, close stdin/stdout.stderr, setuid/setgid, chroot, chdir
// true  - do not demonize - for development

//"demon"		=>	true		,

// $user, $group: user and group for the server
// default: none
// note: works only if started as UID 0, e.g. root

"user"			=>	"nobody"	,
"group"			=>	"nobody"	,



// $chroot: chroot() directory
// default: none / false

"chroot"		=>	"/var/empty"	,



// $chdir: working directory
// default: /

"chdir"			=>	"/"		,



// $max_workers: maximum pre-forked connections
// default: $server_limit
// minimum: 8
// recommended: 32-512
// recommended minimum: as many as CPU cores
// argv: can be changed with argv[2]

"max_workers"		=>	128		,



// $max_request_per_child: maximum requests per worker
// default: 0 ("unlimited", e.g. equal to 1M)
// recommended: 1000-5000

"max_request_per_child"	=>	100		,



// $max_request_size: maximum HTTP request size
// default: 1024 (1KB)
// recommended: 1024 * 4 (4KB)
// minimum: 1024

"max_request_size"	=>	1024 * 4	,



// $server_name: server HTTP header
// default: none

"server_name"		=>	"micro_httpd"	,



// $default_mime_type: Content-type HTTP header
// default: text/html

"default_mime_type"	=>	"text/html"	,



// $error_document_404: custom 404 index
// default: none
// note: this must be found in the $server_directory_matrix array

"error_document_404"	=>	"/404/"		,



// $debug: show misc debug logs
// default: false

"debug"			=>	false		,



// $log_file: log file
// default: /dev/null

"log_file"		=>	"log.txt"	,



// $log_file_reset - overwrite log file at start
// default: false
// true  = overwrite
// false = append

"log_file_reset"	=> false		,



// $log_level: log level
// default: 0
// maximum: 10
//  0 = start messages
//  1 = some notifications
//  2 = access log
// 10 = debug messages, if debug is on.

"log_level"		=>	5		,

); // $SERVER_CONFIG



// $SERVER_PATH_MATRIX: description of server "pages"
// default: none
// note: usually this is defined in different file where the application code is.

/*
$SERVER_PATH_MATRIX = array(
	"/"		=>	"home"		,
	"/list/"	=>	"list_users"	,
	"/404/"		=>	"page404"
*/

