<?php 
//determine environment => prod or dev --> change variable/constant and db access
define('_WVERSION_PROD', 'prod'); //prod -- dev (on server prod) -- local (pc yale)             //-----need change on prod
//environment
if( _WVERSION_PROD == 'dev' ){
  //dev
  define('_WDEBUG', true);
  define('_WDEBUG_LOG_FILE', '/data/sites/wwwdev/log/debug.log');
  define( '_WINDSPOTS_DATA_DIR', '/home/www/windspotsdev/data' );
  define( '_WINDSPOTS_URL', 'https://dev.windspots.org' );
}elseif( _WVERSION_PROD == 'local' ){
  //dev - local
  define('_WDEBUG', true);
  define('_WDEBUG_LOG_FILE', 'C:\wamp\www\windspots\logs\debug.log');
  define( '_WINDSPOTS_DATA_DIR', 'C:\wamp\www\windspots\data' );
  define( '_WINDSPOTS_URL', 'http://local.windspots' );
}else{
  //prod
  define('_WDEBUG', false);
  define('_WDEBUG_LOG_FILE', '/data/sites/www/log/debug.log');
  define( '_WINDSPOTS_DATA_DIR', '/data/sites/www/data' );
  define( '_WINDSPOTS_URL', 'https://windspots.org' );
}
//debug - Log system
define('_WDEBUG_LOG', 1);
define( '_WLOG_LVL_INFO', 'INFO' );
define( '_WLOG_LVL_WARNING', 'WARNING' );
define( '_WLOG_LVL_ERROR', 'ERROR' );
define( '_WLOG_LVL_START', 'START' );
define( '_WLOG_LVL_END', 'END' );
//activities of stations
define("ST_ACTIVITIES_KITE", 1);
define("ST_ACTIVITIES_WINDSURF", 2);
define("ST_ACTIVITIES_PADDLE", 4);
define("ST_ACTIVITIES_RELAX", 8);
define("ST_ACTIVITIES_PARA", 16);
define("ST_ACTIVITIES_NAGE", 32);
//common
$cwd = getcwd();
define( 'CWD', $cwd );
define( '_WVERSION_NUMBER', '4.0331' );
define( '_WINDSPOTS_SITENAME', 'WindSpots.org' );
define( '_WINDSPOTS_REGISTRATION_EMAIL', 'info@windspots.com' );
define( '_WINDSPOTS_REGISTRATION_MAXTIME', 86400 );
define( '_WINDSPOTS_AVATAR_DIR', _WINDSPOTS_DATA_DIR.'/avatar' );
define( '_WINDSPOTS_AVATAR_DIR_TMP', _WINDSPOTS_DATA_DIR.'/tmp' );
define( '_WINDSPOTS_SPOTS_DEFAULT_IMG_EXT', '.jpg' );
define( '_WINDSPOTS_AVATAR_MAXHEIGHT', 101 );
define( '_WINDSPOTS_AVATAR_MAXWIDTH', 101 );
define( '_WINDSPOTS_SESSION_LIFETIME', 28800 ); //8*60*60 => 8h
define( '_WINDSPOTS_DISPLAY_CONFIRMATION_MSG_TIME', 6500 );
define( '_WINDSPOTS_DISPLAY_ADVERTISING_FIRST', 300 );    //after 5min => 5*60
define( '_WINDSPOTS_DISPLAY_ADVERTISING_OTHER', 7200 );   //each 2hours => 2*60*60
define( '_WINDSPOTS_SECURITY_KEY', 'YourWindspotsSecurityKey28092016' );
//graph
define( '_WINDSPOTS_MIN_ERROR_AREA', 5 );