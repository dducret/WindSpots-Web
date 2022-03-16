<?php 
// No direct access to this file
defined('_WEXEC') or die('Restricted access');
$rootPath=__FILE__;
$scriptPath=baseName($rootPath);
$rootPath=str_replace($scriptPath,'',$rootPath);
$rootPath=realPath($rootPath.'../../');
$rootPath=str_replace('\\','/',$rootPath);
date_default_timezone_set('Europe/Zurich');
$windspotsLog =  $rootPath."/log";
function logIt($message) {
  global $windspotsLog;
  $dirname = dirname(__FILE__);
  if(empty($station)) {
    $logfile = "/error.log";
  } 
  $wlHandle = fopen($windspotsLog.$logfile, "a");
  $t = microtime(true);
  $micro = sprintf("%06d",($t - floor($t)) * 1000000);
  $micro = substr($micro,0,3);
  fwrite($wlHandle, Date("H:i:s").".$micro"." ".$message."\n");
  fclose($wlHandle);
}
//
class WindspotsHelper{  
  private static function connectDb(){
    if( _WVERSION_PROD == 'dev' ){
      //dev
        $dbHost = '127.0.0.1'; //localhost //www.windspots.org
        $dbUser = 'windspots';
        $dbPassword = 'WS2022org!';
        $dbName = 'windspots';
    }elseif( _WVERSION_PROD == 'local' ){
      //dev - local
      $dbHost = 'localhost'; //localhost //www.windspots.org
      $dbUser = 'root';
      $dbPassword = '';
      $dbName = 'windspots';
    }else{
      //prod
        $dbHost = '127.0.0.1'; //localhost //www.windspots.org
        $dbUser = 'windspots';
        $dbPassword = 'WS2022org!';
        $dbName = 'windspots';
    }
    $dbLink = mysqli_connect( $dbHost, $dbUser, $dbPassword, $dbName );
    if( mysqli_select_db( $dbLink, $dbName ) ){
      return $dbLink;
    }else{
      return false;
    }
  }
  private static function disconnectDb( $dbLink ){
      mysqli_close( $dbLink );
  }
  private static function escapeStr( $dbLink, $str ){
      return mysqli_real_escape_string( $dbLink, $str );
  }
  public static function getStations( $fields = array(), $online = true ){
    $dbLink = self::connectDb();
    $result = array();
    $query = '';
    $query .= "SELECT ";
    if( is_array($fields) && count($fields) > 0 ){
      //specific fields
      $checkSepField = count($fields) - 1;
      foreach($fields as $key => $field){
        $query .= " `".self::escapeStr( $dbLink, $field )."`";
        if( $key < $checkSepField ){
          $query .= ",";
        }
      }
    }else{
      //all
      $query .= "*";
    }
    $query .= " FROM `station` WHERE ";
    //published
    if( $online ){
      $query .= "`online` = 1 ";
    }else{
      $query .= "`online` = 0 ";
    }
    //order alpha
    $query .= "ORDER BY `display_name` ASC ;";
    // echo("Query:".$query."</br>\r\n");
    $data = mysqli_query( $dbLink, $query );
    if( empty($data) ){
      // echo("Data empty</br>\r\n");
      self::disconnectDb( $dbLink );
      return $result; 
    }
    while ( $station = mysqli_fetch_assoc( $data ) ) {
      // echo("station: ".var_dump($station)."</br>\r\n");
      $result[$station['station_name']] = $station;
    }
    self::disconnectDb( $dbLink );
    return $result;
  }
  public static function getStationsByName( $stationName = 'default', $fields = array(), $published = true, $private = false ){
    $dbLink = self::connectDb();
    $result = array();
    $query = '';
    $query .= "SELECT ";
    if( is_array($fields) && count($fields) > 0 ){
      //specific fields
      $checkSepField = count($fields) - 1;
      foreach($fields as $key => $field){
        $query .= "`".self::escapeStr( $dbLink, $field )."`";
        if( $key < $checkSepField ){
          $query .= ",";
        }
      }
    }else{
      //all
      $query .= "*";
    }
    $query .= " FROM `station` WHERE `station_name` = '".self::escapeStr( $dbLink, $stationName )."' AND ";
    //published
    if( $published ){
      $query .= "`online` = 1";
    }else{
      $query .= "`online` = 0 ";
    }
    $data = mysqli_query( $dbLink, $query );
    if( empty($data) ){ 
      self::disconnectDb( $dbLink );
      return $result; 
    }
    $result = mysqli_fetch_assoc( $data );
    self::disconnectDb( $dbLink );
    return $result;
  }
  public static function convertStr( $str = '' ){
    if( !empty($str) ){
      return $str;
    }
  }
  public static function lang( $txt = '', $convert = true ){
    $lang = array();
    if( isset($_SESSION['WTRANSLATION']) && is_array($_SESSION['WTRANSLATION']) ){
      $lang = $_SESSION['WTRANSLATION'];
    }
    if( !array_key_exists($txt,$lang) ){
      //translation found
      if( $convert ){
        return self::convertStr($txt);
      }else{
        return $txt;
      }
    }else{
      //translation not found -> use string recieved in parameter
      if( $convert ){
        return self::convertStr($lang[$txt]);
      }else{
        return $lang[$txt];
      }
    }
  }
  public static function loadTranslationFile( $lang = 'fr_FR' ){
    $lang = str_replace('-','_',$lang); //convert fr-FR to fr_FR
    $handle = fopen("./languages/".$lang.".txt", "r");      
    $_SESSION['WTRANSLATION'] = array();
    if ($handle) {
      while (($line = fgets($handle)) !== false) {
        // process the line read.
        $line = trim($line);
        // Ignore comment lines.
        if (!strlen($line) || $line['0'] == ';'){
          continue;
        }
        $line = explode('=', $line, 2);
        if( isset($line[0]) && !empty($line[0]) && isset($line[1]) ){
          $_SESSION['WTRANSLATION'][$line[0]] = $line[1];
        } 
      }
      fclose($handle);
    } else {
      // error opening the file.
    }
  }
  public static function loadStationData( $stationName = 'default', $firstLoad = 0 ){
    //default values
    $graphDirection = 'ltr';
    $graphPreviDirection = 'ltr';
    //load pref graph direction
    if (isset($_SESSION['W_GRAPH_LTR']) && $_SESSION['W_GRAPH_LTR']) {
      $graphDirection = $_SESSION['W_GRAPH_LTR'];
    }
    //load pref graph Previ direction
    if (isset($_SESSION['W_GRAPH_PREV_LTR']) && $_SESSION['W_GRAPH_PREV_LTR']) {
      $graphPreviDirection = $_SESSION['W_GRAPH_PREV_LTR'];
    }
    //set user pref wind unit ////units available => kts / bft / kmh / ms
    $windUnit = 'kts';
    if (isset($_SESSION['W_UNIT']) && !empty($_SESSION['W_UNIT'])) {
      switch ($_SESSION['W_UNIT']) {
        case '_bft':
          $windUnit = 'bft';
          break;
        case '_kmh':
          $windUnit = 'kmh';
          break;
        case '_ms':
          $windUnit = 'ms';
          break;
        case '_kts':
        default:
          $windUnit = 'kts';
          break;
      }
    }
    $langShort = 'en';
    if (isset($_SESSION['W_LANG']) && !empty($_SESSION['W_LANG'])) {
      $lang = $_SESSION['W_LANG'];
      switch ($lang) {
        case 'fr_FR':
          $langShort = 'fr';
          break;
        default:
        case 'en_GB':
          $langShort = 'en';
          break;
        case 'de_DE':
          $langShort = 'de';
          break;
      }
    }
    //get station data
    $stationData = self::getStationsByName($stationName);
    //server GMT 0 -> set Geneva time
    date_default_timezone_set('Europe/Zurich');
    //define data time slot
    $now = date("Y-m-d H:i:00");
    $to = date("Y-m-d H:i:00", strtotime($now) - 60 );   //round time to previous minute
    $from = '';
    $period = '';
    //check sensor id (if same => get only one request)
    $sensorIdTemp = array();
    if( !empty($stationData['wind_id']) ){
        $sensorIdTemp['wind'] = $stationData['wind_id'];
    }
    if( !empty($stationData['barometer_id']) ){
        $sensorIdTemp['barometer_pressure'] = $stationData['barometer_id'];
    }
    if( !empty($stationData['barometer_id']) ){
        $sensorIdTemp['barometer_temperature'] = $stationData['barometer_id'];
    }
    if( !empty($stationData['temperature_id']) ){
        $sensorIdTemp['temperature_temperature'] = $stationData['temperature_id'];
    }
    if( !empty($stationData['temperature_id'])  ){
        $sensorIdTemp['temperature_humidity'] = $stationData['temperature_id'];
    }
    //var_dump($sensorIdTemp);
    //init station info (last values)
    $lastValueTemperature = 0;
    $lastValueTemperatureWater = 0;
    $lastValueHumidity = 0;
    $lastValueBarometer = 0;
    $lastValueWind = 0;
    $lastValueWindDirection = 0;
    $lastValueWindGust = 0;
    //get last temperature value
    //exception about water temperatue -> because stored with period_1 (so each min) but time is all 10 min (and can be change -> so not necessarily 10min, 20min...)
    //use this value like last value (like 1 min)
    //if( $sensorKey == 'temperature_water' &&  $data['temperature'] != '' && $data['temperature'] != null ){ $lastValueTemperatureWater = $data['temperature']; }
    if( !empty($stationData['water_id']) ){
        $waterTemperatueResult = self::getStationSensorData( $stationData['water_id'], true, '', '', '' );
        if( is_array($waterTemperatueResult) && isset($waterTemperatueResult['temperature']) && !empty($waterTemperatueResult['temperature']) ){
            $lastValueTemperatureWater = $waterTemperatueResult['temperature'];
        }
    }
    // ---- Graph Data (1h) ----
    //get sensors data (period 1h)
    $period = 1;
    //(period * sec * min) -> 61x values for the graph 1h (to have the first and last with an scale hour of 1h)
    $diffTime = $period * 60 * 60;
    $from = date("Y-m-d H:i:00", strtotime($to) - $diffTime );  //round time to minute
    //init station data result (based on last update -> each 1min)
    $sensorDataTemperature    = '';   //station data -> last value
    $sensorDataTemperatureWater = '';   //station data -> last value
    $sensorDataBarometer    = '';   //station data -> last value
    $sensorDataHumidity     = '';   //station data -> last value
    $sensorDataWindGust     = '';   //station data -> last value
    $sensorDataWindGustMax    = '';   //station data -> rafal/gust max during last 30min
    $sensorDataWindMed      = '';   //station data -> medium (average) wind speed during last 30min
    $sensorDataWindMin      = '';   //station data -> minimum wind speed during last 30min
    $sensorDataWindData     = array();  //use for wind min speed (during last 30min)
    $sensorDataWindAverageData  = array();  //use for wind med average (during last 30min)
    $sensorDataWindDirection  = '';   //station data -> last value
    $sensorDataWind       = '';   //station data -> last value
    $sensorDataWorking      = array();  // to get station data (working array)
    //init graph result
    $sensorDataWind1      = array();
    $sensorDataWindDirection1   = array();
    $sensorDataWindGust1    = array();
    $sensorDataJSWind1      = array();
    $sensorDataJSWindDirection1 = array();
    $sensorDataJSWindGust1    = array();
    $scaleHour1         = array();
    //prepare exact entries in result (key => time)
    //because we don't have automatically a record for each minute in bdd -> so complete by null to have the number of values expected in JS
    for($i = strtotime($from); $i <= strtotime($to); $i += 60 ){
        $sensorDataWind1[$i]      = null; //graph -> wind strength
        $sensorDataWindDirection1[$i] = null; //graph -> wind direction
        $sensorDataWindGust1[$i]    = null; //graph -> rafale/gust
        $sensorDataWorking[$i]      = array( 'sensor_id' => null,
            'sensor_time' => null,
            'temperature' => null,
            'humidity' => null,
            'barometer' => null,
            'direction' => null,
            'speed' => null,
            'average' => null,
            'gust' => null,
            'ten' => null
        );
    }
    //get data by sensor id (station can be have the same for all value or not...) -> make like this to limit redundant queries
    if(is_array($sensorIdTemp) && count($sensorIdTemp) > 0 ){
        foreach($sensorIdTemp as $sensorKey => $sensorId){
            //order data -> the older before
            $sensorData = self::getStationSensorData( $sensorId, false, $from, $to, $period );
            // logIt("Sensor Data: ".count($sensorData));
            //parse results of period
            if( is_array($sensorData) && count($sensorData) > 0){
                foreach($sensorData as $key => $data){
                    //add valable value based on time (graph and working array)
                    $time = strtotime($data['sensor_time']);
                    //if( array_key_exists( $time, $sensorDataWind1 ) && !empty($data['speed']) ){
                    if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataWind1 ) && $data['speed'] != '' && $data['speed'] != null ){
                        $sensorDataWind1[$time] = $data['speed'];
                    }
                    if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataWindDirection1 ) && $data['direction'] != '' && $data['direction'] != null ){
                        $sensorDataWindDirection1[$time] = $data['direction'];
                    }
                    if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataWindGust1 ) && $data['gust'] != '' && $data['gust'] != null ){
                        $sensorDataWindGust1[$time] = $data['gust'];
                    }
                    //keep data to calculate station data/info
                    if( array_key_exists( $time, $sensorDataWorking ) ){
                        if( $sensorKey == 'wind' && $data['speed'] != '' && $data['speed'] != null ){ $sensorDataWorking[$time]['speed'] = $data['speed']; }
                        if( $sensorKey == 'wind' && $data['average'] != '' && $data['average'] != null ){ $sensorDataWorking[$time]['average'] = $data['average']; }
                        if( $sensorKey == 'wind' && $data['gust'] != '' && $data['gust'] != null ){ $sensorDataWorking[$time]['gust'] = $data['gust']; }
                    }
                    //check if a value (not empty) in last minut has been already set -> to do not overwrite a right value by empty value
                    if( $sensorKey == 'temperature_temperature' &&  $data['temperature'] != '' && $data['temperature'] != null ){
                        //sensor temperature master value compared to barometer sensor -> so if value in temperature -> use this instead of barometer --> if not use barometer data
                        $lastValueTemperature = $data['temperature'];
                    }else{
                        if( $sensorKey == 'barometer_temperature' &&  $data['temperature'] != '' && $data['temperature'] != null ){
                            $lastValueTemperature = $data['temperature'];
                        }
                    }
                    if( $sensorKey == 'temperature_humidity' && $data['humidity'] != '' && $data['humidity'] != null ){ $lastValueHumidity = $data['humidity']; }
                    if( $sensorKey == 'barometer_pressure' &&  $data['barometer'] != '' && $data['barometer'] != null ){ $lastValueBarometer = $data['barometer']; }
                    if( $time >= (strtotime($to) - 60) ){
                        if( $sensorKey == 'wind' && $data['speed'] != '' && $data['speed'] != null ){ $lastValueWind = $data['speed']; }
                        if( $sensorKey == 'wind' && $data['direction'] != '' && $data['direction'] != null ){ $lastValueWindDirection = $data['direction']; }
                        if( $sensorKey == 'wind' && $data['gust'] != '' && $data['gust'] != null ){ $lastValueWindGust = $data['gust']; }
                        //get water temperature value (last value) by another way (see after define sensorKey) -> so not here
                    }
                }
            }
        }
    }
    //generate graph 1h scale hour (each 10 min)
    for( $i = strtotime($from); $i <= strtotime($to); $i += 600 ){
        $scaleHour1[] = str_replace( ":", "h", date('G:i', $i) );
    }
    // ---- Graph Data (12h/24h) ----
    //override minutes of now with last period 10min --> important to prepare entries in result array on compare time to update data by default (null)
    $now = date("Y-m-d H:i:00");
    $now = self::time_minutes_round( $now, 10 );
    $to = date("Y-m-d H:i:00", strtotime($now) );
    //get sensors data (period 24h) -> use also for 12h to limit bdd queries
    $period = 24;
    $diffTime = $period * 60 * 60;
    $from24h = date("Y-m-d H:i:00", strtotime($to) - $diffTime ); //round time to minute
    $period = 12;
    $diffTime = $period * 60 * 60;
    $from12h = date("Y-m-d H:i:00", strtotime($to) - $diffTime ); //round time to minute
    $period = 6;
    $diffTime = $period * 60 * 60;
    $fromPrevi6h = date("Y-m-d H:00:00", strtotime($to) - $diffTime );  //round time to hour
    //(period * sec * min)  -> 73x data - each 20 min for the graph 24h (to have the first and last with an scale hour of 1h)
    //            -> 73x data - each 10 min for the graph 12h (to have the first and last with an scale hour of 1h)
    //=> total results of query => 144x data => each 10 min last 24h (with data computed period 10min)
    //init graph result (12h/24h)
    $sensorDataWind12       = array();
    $sensorDataWindDirection12  = array();
    $sensorDataWindGust12     = array();
    $sensorDataJSWind12     = array();
    $sensorDataJSWindDirection12 = array();
    $sensorDataJSWindGust12   = array();
    $scaleHour12        = array();
    $sensorDataWind24       = array();
    $sensorDataWindDirection24  = array();
    $sensorDataWindGust24     = array();
    $sensorDataJSWind24     = array();
    $sensorDataJSWindDirection24 = array();
    $sensorDataJSWindGust24   = array();
    $scaleHour24        = array();
    //init graph Previ(6h)
    $sensorDataPreviWind6       = array();
    $sensorDataPreviWindDirection6  = array();
    $sensorDataJSPreviWind6     = array();
    $sensorDataJSPreviWindDirection6 = array();
    //prepare exact entries in result (key => time)
    //because we don't have automatically a record for each 10 minute in bdd -> so complete by null to have the number of values expected in JS
    for($i = strtotime($from12h); $i <= strtotime($to); $i += (60 * 10) ){
        $sensorDataWind12[$i]     = null; //graph -> wind strength
        $sensorDataWindDirection12[$i]  = null; //graph -> wind direction
        $sensorDataWindGust12[$i]   = null; //graph -> rafale/gust
    }
    //because we don't have automatically a record for each 20 minute in bdd -> so complete by null to have the number of values expected in JS
    for($i = strtotime($from24h); $i <= strtotime($to); $i += (60 * 20) ){
        $sensorDataWind24[$i]     = null; //graph -> wind strength
        $sensorDataWindDirection24[$i]  = null; //graph -> wind direction
        $sensorDataWindGust24[$i]   = null; //graph -> rafale/gust
    }
    //because we don't have automatically a record for each 10 minute in bdd -> so complete by null to have the number of values expected in JS
    for($i = strtotime($fromPrevi6h); $i <= strtotime($to); $i += (60 * 20) ){
        $sensorDataPreviWind6[$i]     = null; //graph -> wind strength
        $sensorDataPreviWindDirection6[$i]  = null; //graph -> wind direction
    }
    //get data by sensor id (station can be have the same for all value or not...) -> make like this to limit redundant queries
    if( is_array($sensorIdTemp) && count($sensorIdTemp) > 0 ){
        foreach($sensorIdTemp as $sensorKey => $sensorId){
            //order data -> the older before
            $sensorData = self::getStationSensorData( $sensorId, false, $from24h, $to, $period );
            $time24h = strtotime($from24h);
            $time12h = strtotime($from12h);
            $timePrevi6h = strtotime($fromPrevi6h);
            //parse results of period
            //so we have a value for each 10 min -> use each result for a period of 12h AND one on two for a period 24h (each 20min)
            if( is_array($sensorData) && count($sensorData) > 0){
                foreach($sensorData as $key => $data){
                    //add valable value based on time (graph and working array)
                    $time = strtotime($data['sensor_time']);
                    //for 24h use each 20min of last 24h
                    //for 12h use each 10min of last 12h
                    if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataWind24 ) && $data['speed'] != '' && $data['speed'] != null ){
                        $sensorDataWind24[$time] = $data['speed'];
                    }
                    if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataWindDirection24 ) && $data['direction'] != '' && $data['direction'] != null ){
                        $sensorDataWindDirection24[$time] = $data['direction'];
                    }
                    if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataWindGust24 ) && $data['gust'] != '' && $data['gust'] != null ){
                        $sensorDataWindGust24[$time] = $data['gust'];
                    }
                    if( $time >= $time12h ){
                        if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataWind12 ) && $data['speed'] != '' && $data['speed'] != null ){
                            $sensorDataWind12[$time] = $data['speed'];
                        }
                        if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataWindDirection12 ) && $data['direction'] != '' && $data['direction'] != null ){
                            $sensorDataWindDirection12[$time] = $data['direction'];
                        }
                        if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataWindGust12 ) && $data['gust'] != '' && $data['gust'] != null ){
                            $sensorDataWindGust12[$time] = $data['gust'];
                        }
                    }
                    if( $time >= $timePrevi6h ){
                      if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataPreviWind6 ) && $data['speed'] != '' && $data['speed'] != null ){
                         $sensorDataPreviWind6[$time] = $data['speed'];
                      }else{
                        //var_dump( date("Y-m-d H:i:00",$time) );                                
                      }
                      if( $sensorKey == 'wind' && array_key_exists( $time, $sensorDataPreviWindDirection6 ) && $data['direction'] != '' && $data['direction'] != null ){
                          $sensorDataPreviWindDirection6[$time] = $data['direction'];
                      }
                    }
                }
            }
        }
    }
    //generate graph 24h scale hour (each hour)
    for( $i = strtotime($from24h); $i <= strtotime($to); $i += 3600 ){
        $scaleHour24[] = str_replace( ":", "h", date('G:i', $i) );
    }
    //generate graph 12h scale hour (each hour)
    for( $i = strtotime($from12h); $i <= strtotime($to); $i += 3600 ){
        $scaleHour12[] = str_replace( ":", "h", date('G:i', $i) );
    }
    //prepare data for JS (tranform key time to key int (egal to i in JS loop) and convert str to float)
    $sensorDataJSWind24 = self::prepareGraphDataJs( $sensorDataWind24, $sensorDataJSWind24, $windUnit );
    $sensorDataJSWindDirection24 = self::prepareGraphDataJs( $sensorDataWindDirection24, $sensorDataJSWindDirection24, '' );
    $sensorDataJSWindGust24 = self::prepareGraphDataJs( $sensorDataWindGust24, $sensorDataJSWindGust24, $windUnit );
    $sensorDataJSWind12 = self::prepareGraphDataJs( $sensorDataWind12, $sensorDataJSWind12, $windUnit );
    $sensorDataJSWindDirection12 = self::prepareGraphDataJs( $sensorDataWindDirection12, $sensorDataJSWindDirection12, '' );
    $sensorDataJSWindGust12 = self::prepareGraphDataJs( $sensorDataWindGust12, $sensorDataJSWindGust12, $windUnit );
    $sensorDataJSPreviWind6 = self::prepareGraphDataJs( $sensorDataPreviWind6, $sensorDataJSPreviWind6, $windUnit );
    $sensorDataJSPreviWindDirection6 = self::prepareGraphDataJs( $sensorDataPreviWindDirection6, $sensorDataJSPreviWindDirection6, '' );
    // ---- Graph Previ Data (24h) ----
    //define data time slot
    //$now = date("Y-m-d H:00:00"); //round time to hour
    $period = 18; // +6h already use by Previ 6h => tot: 24h
    $diffTime = $period * 60 * 60;
    $toPrevi24h = date("Y-m-d H:00:00", strtotime($now) + $diffTime );  //round time to hour ! //+ (60*60)  and use tot 25 hours to displayed 24h
    //init (only wind speed and direction for Previ data)
    $PreviDataWind24            = array();
    $PreviDataWindDirection24   = array();
    $PreviDataJSWind24          = array();
    $PreviDataJSWindDirection24 = array();
    $scaleHourPrevi24           = array();
    //generate graph Previ 24h scale hour (each hour for Previ 24h)
    for( $i = strtotime($fromPrevi6h); $i <= strtotime($toPrevi24h); $i += (60 * 60) ){
      //$scaleHourPrevi24[] = str_replace( ":", "h", date('H:i', $i) );
      $scaleHourPrevi24[] = str_replace( ":", "h", date('G:', $i) );
    }
    //prepare exact entries in result (key => time)
    //because we don't have automatically a record for each 1 hour in bdd -> so complete by null to have the number of values expected in JS
    for($i = strtotime($fromPrevi6h); $i <= strtotime($toPrevi24h); $i += (60 * 60) ){
      $PreviDataWind24[$i]      = null; //graph -> wind strength
      $PreviDataWindDirection24[$i] = null; //graph -> wind direction
    }
    //order data -> the older before
    $PreviData = self::getStationPreviData( $stationName, $fromPrevi6h, $toPrevi24h );
    //parse results of period
    //so we have a value for each 10 min -> use each result for a period of 12h AND one on two for a period 24h (each 20min)
    if( is_array($PreviData) && count($PreviData) > 0){
        foreach($PreviData as $key => $data){
            //add valable value based on time (graph and working array)
            $time = strtotime($data['reference_time']);
            if( array_key_exists( $time, $PreviDataWind24 ) && !empty($data['speed']) ){
                //convert data string data < 0 => example .9 to 0.90
                $PreviDataWind24[$time] = number_format($data['speed'], 2, '.', '');
            }
            if( array_key_exists( $time, $PreviDataWindDirection24 ) && !empty($data['direction']) ){
                $PreviDataWindDirection24[$time] = $data['direction'];
            }
        }
    }
    //prepare data for JS (tranform key time to key int (egal to i in JS loop) and convert str to float)
    $PreviDataJSWind24 = self::prepareGraphDataJs( $PreviDataWind24, $PreviDataJSWind24, $windUnit );
    $PreviDataJSWindDirection24 = self::prepareGraphDataJs( $PreviDataWindDirection24, $PreviDataJSWindDirection24, '' );
    // ---- Station Data ----
    //get last value
    if( $lastValueTemperature != '' && $lastValueTemperature != null ){ $sensorDataTemperature = $lastValueTemperature; }
    if( $lastValueTemperatureWater != '' && $lastValueTemperatureWater != null ){ $sensorDataTemperatureWater = $lastValueTemperatureWater; }
    if( $lastValueHumidity != '' && $lastValueHumidity != null ){ $sensorDataHumidity = $lastValueHumidity; }
    if( $lastValueBarometer != '' && $lastValueBarometer != null ){  $sensorDataBarometer = $lastValueBarometer; }
    if( $lastValueWind != '' && $lastValueWind != null ){ $sensorDataWind = self::convertWindUnit( $windUnit, $lastValueWind); }
    if( $lastValueWindDirection != '' && $lastValueWindDirection != null ){ $sensorDataWindDirection = $lastValueWindDirection; }
    if( $lastValueWindGust != '' && $lastValueWindGust != null ){ $sensorDataWindGust = self::convertWindUnit( $windUnit, $lastValueWindGust); }
    if( is_array($sensorDataWorking) && count($sensorDataWorking) > 0 ){
        $i = 0;
        foreach($sensorDataWorking as $time => $dataWorking){
            //get value from complete sensor data of period 1 -> to limit query (data already getted)
            if( $i >= 29 ){
                //use only data of the last 30min
                //station data -> rafal/gust max during last 30min
                if( !empty($dataWorking['gust']) && $sensorDataWindGustMax < $dataWorking['gust'] ){
                    $sensorDataWindGustMax = $dataWorking['gust'];
                }
                //wind speed min last 30min
                if( !empty($dataWorking['speed']) ){
                    $sensorDataWindData[] = $dataWorking['speed'];
                }
                //wind speed med (average)
                if( !empty($dataWorking['average']) ){
                    $sensorDataWindAverageData[] = $dataWorking['average'];
                }
            }
            $i++;
        }
    }
    //prepare data for JS (tranform key time to key int (egal to i in JS loop) and convert str to float)
    $sensorDataJSWind1 = self::prepareGraphDataJs( $sensorDataWind1, $sensorDataJSWind1, $windUnit );
    $sensorDataJSWindDirection1 = self::prepareGraphDataJs( $sensorDataWindDirection1, $sensorDataJSWindDirection1, '' );
    $sensorDataJSWindGust1 = self::prepareGraphDataJs( $sensorDataWindGust1, $sensorDataJSWindGust1, $windUnit );
    //direction in letter
    $sensorDataWindDirectionLetter = self::getDirectionLetter( $sensorDataWindDirection );
    //rafale/gust max during last 30min
    if( !empty($sensorDataWindGustMax)){
        $sensorDataWindGustMax  = self::convertWindUnit( $windUnit, $sensorDataWindGustMax );
    }else{
        $sensorDataWindGustMax = 0;
    }
    //medium (average) wind speed during last 30min
    $sensorDataWindMed = 0;
    //average with wind_speed
    if( is_array($sensorDataWindData) && count($sensorDataWindData) > 0 ){
      foreach($sensorDataWindData as $val){
      if( !empty($val) ){
        $sensorDataWindMed += (float)$val;
      }
     }
     if( !empty($sensorDataWindMed) ){
        $sensorDataWindMed = self::convertWindUnit( $windUnit, round( $sensorDataWindMed / count($sensorDataWindData), 2 ) );
     }else{
       $sensorDataWindMed = 0;
     }
    }
    //average with wind_speed_average
/* ddu ???
          $nbAvgVal = 0;
    if( is_array($sensorDataWindAverageData) && count($sensorDataWindAverageData) > 0 ){
        foreach($sensorDataWindAverageData as $val){
            if( !empty($val) &&  $val >= min($sensorDataWindData) ){
                      $nbAvgVal++;
                $sensorDataWindMed += (float)$val;
            }
        }
        if( !empty($sensorDataWindMed) ){
            $sensorDataWindMed = self::convertWindUnit( $windUnit, round( ($sensorDataWindMed / $nbAvgVal), 2 ) );
        }else{
            $sensorDataWindMed = 0;
        }
    }
*/      
    //minimum wind speed during last 30min
    if( is_array($sensorDataWindData) && count($sensorDataWindData) > 0 ){
        $sensorDataWindMin = self::convertWindUnit( $windUnit, min($sensorDataWindData) );
    }
    if( empty($sensorDataWindMin) ){ $sensorDataWindMin = 0; }
    //check station activities
    $activitiesHtml = '';
    if( isset($stationData['spot_type'])  && !empty($stationData['spot_type']) ){
        if( ($stationData['spot_type'] & ST_ACTIVITIES_KITE) == true ){
            //$activitiesHtml .= '<li><span class="kite icoSports"></span></li>';
            $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/kitesurf.svg" /></div>';
        }
        if( ($stationData['spot_type'] & ST_ACTIVITIES_WINDSURF) == true ){
            //$activitiesHtml .= '<li><span class="windsurf icoSports"></span></li>';
            $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/windsurf.svg" /></div>';
        }
        if( ($stationData['spot_type'] & ST_ACTIVITIES_PADDLE) == true ){
            //$activitiesHtml .= '<li><span class="paddle icoSports"></span></li>';
        }
        if( ($stationData['spot_type'] & ST_ACTIVITIES_RELAX) == true ){
            //$activitiesHtml .= '<li><span class="relax icoSports"></span></li>';
            $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/laze.svg" /></div>';
        }
        if( ($stationData['spot_type'] & ST_ACTIVITIES_PARA) == true ){
            //$activitiesHtml .= '<li><span class="para icoSports"></span></li>';
            $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/para.svg" /></div>';
        }
        if( ($stationData['spot_type'] & ST_ACTIVITIES_NAGE) == true ){
            //$activitiesHtml .= '<li><span class="nage icoSports"></span></li>';
            $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/nage.svg" /></div>';
        }
    }
    //set current station (session)
    self::setCurrentStation( $stationName );
    //prepare mini map marker (to change ico of current station marker / other not change)
    date_default_timezone_set('Europe/Paris');
    $stationUpdateNow = time();
    $updateStationData = false;
    $mapMarkersByStation = array();
    $stationStatus = array();
    $updateMarkersStation = '0';
    if( $firstLoad || !isset($_SESSION['WSTATIONS_STATUS_LAST_UPDATE']) || ( $stationUpdateNow - $_SESSION['WSTATIONS_STATUS_LAST_UPDATE'] >= (5*60) ) ){
        //refresh station data each 5min (for refresh station status -> disable/normal)
        $updateStationData = true;
        $updateMarkersStation = '1';
    }
    if( $updateStationData ){
        //get stations data
        $stations = WindspotsHelper::getStations();
        //as we have get stations data for update their status -> in same time (as db access already done) -> use it to update stations data in session
        //->store stations in session to use with access again to the db
        $_SESSION['WSTATIONS'] = $stations;
        //update station status and all markers (session)
        if( is_array($stations) && count($stations) > 0 ){
            $markerId = 1;
            foreach($stations as $key => $station){
                //check station up (map marker -> if no data until 5min -> change ico disable)
                $stationLastDataUpdate = strtotime( $station['data_time'] );
                // $stationLastImgUpdate = strtotime( $station['image_time'] );
                //if( ( ($stationUpdateNow - $stationLastDataUpdate) <= 300 ) && ( ($stationUpdateNow - $stationLastImgUpdate) <= 300 ) ){
                if( ($stationUpdateNow - $stationLastDataUpdate) <= 300 ){
                    $stationStatus[$station['station_name']] = true;
                }else{
                    $stationStatus[$station['station_name']] = false;
                }
                //map markers
                $markerStation = '';
                if( isset($station['station_name']) && !empty($station['station_name'])
                    && isset($station['display_name']) && !empty($station['display_name'])
                    && isset($station['latitude']) && !empty($station['latitude'])
                    && isset($station['longitude']) && !empty($station['longitude'])
                    ){  //Leaflet
                        $icoMarker = 'spotIcon';
                        if( $stationStatus[$station['station_name']] == false ){
                            $icoMarker = 'spotDisabledIcon';
                        }
                        //$markerStation .= 'var marker = L.marker(['.$station['latitude'].', '.$station['longitude'].'], {icon: '.$icoMarker.', stationName: \''.$station['station_name'].'\', title: "'.utf8_encode($station['display_name']).'"}); ';  //myCustomId: 5454,
                        $markerStation .= 'var marker = L.marker(['.$station['latitude'].', '.$station['longitude'].'], {icon: '.$icoMarker.', stationName: \''.$station['station_name'].'\', title: "'.$station['display_name'].'"}); ';  //myCustomId: 5454,
                        $markerStation .= 'marker.on(\'click\', onMarkerClick); ';
                        $markerStation .= 'marker.addTo(map); ';
                        $markerStation .= 'var marker = null; ';
                        $mapMarkersByStation[$station['station_name']] = $markerStation;
                }
            }
            $_SESSION['WSTATIONS_STATUS'] = $stationStatus;
            $_SESSION['WSTATIONS_STATUS_LAST_UPDATE'] = $stationUpdateNow;
            $_SESSION['WSTATIONS_MAP_MARKERS_BY_STATION'] = $mapMarkersByStation;
            $markersStation = $mapMarkersByStation;
        }else{
            //error when get last data -> so use last found
            //-> use station data from last 5min
            $stations = $_SESSION['WSTATIONS'];
            $markersStation = $_SESSION['WSTATIONS_MAP_MARKERS_BY_STATION'];
        }
    }else{
        //use station data from last 5min
        $stations = $_SESSION['WSTATIONS'];
        $markersStation = $_SESSION['WSTATIONS_MAP_MARKERS_BY_STATION'];
    }
    $resultMarkers = '';
    if( is_array($markersStation) && count($markersStation) > 0 ){
      $markerId = 1;
      foreach($markersStation as $key => $marker){
        $tmpMarker = '';
        //Leaflet
        if( $key != $stationName ){
          //use maker with normal ico
          $resultMarkers .= $marker;
        }else{
          //replace marker by another with active ico
          $icoMarker = 'spotActiveIcon';
          if( isset($_SESSION['WSTATIONS_STATUS'][$stationName]) && $_SESSION['WSTATIONS_STATUS'][$stationName] == false ){
              $icoMarker = 'spotDisabledIcon';
          }
          $tmpMarker .= 'var marker = L.marker(['.$stations[$stationName]['latitude'].', '.$stations[$stationName]['longitude'].'], {icon: '.$icoMarker.', stationName: \''.$stationName.'\', title: "'.$stations[$stationName]['display_name'].'"}); ';  //myCustomId: 5454,
          $tmpMarker .= 'marker.on(\'click\', onMarkerClick); ';
          $tmpMarker .= 'marker.addTo(map); ';
          $tmpMarker .= 'var marker = null; ';
          $resultMarkers .= $tmpMarker;
        }
      }
    }
    $imgKeyTime = strtotime(date("Y-m-d H:i:s"));
    $imgUrl = 'https://windspots.org/images.php?imagedir=capture&image='.$stationName.'1'._WINDSPOTS_SPOTS_DEFAULT_IMG_EXT.'&r='.$imgKeyTime;
    //check if need reverse chart data
    if( $graphDirection == 'rtl' ){
        //1h
        $sensorDataJSWind1 = array_reverse($sensorDataJSWind1);
        $sensorDataJSWindDirection1 = array_reverse($sensorDataJSWindDirection1);
        $sensorDataJSWindGust1 = array_reverse($sensorDataJSWindGust1);
        $scaleHour1 = array_reverse($scaleHour1);
        //12h
        $sensorDataJSWind12 = array_reverse($sensorDataJSWind12);
        $sensorDataJSWindDirection12 = array_reverse($sensorDataJSWindDirection12);
        $sensorDataJSWindGust12 = array_reverse($sensorDataJSWindGust12);
        $scaleHour12 = array_reverse($scaleHour12);
        //24h
        $sensorDataJSWind24 = array_reverse($sensorDataJSWind24);
        $sensorDataJSWindDirection24 = array_reverse($sensorDataJSWindDirection24);
        $sensorDataJSWindGust24 = array_reverse($sensorDataJSWindGust24);
        $scaleHour24 = array_reverse($scaleHour24);
    }
    //check if need reverse chart data (Previ)
    if( $graphPreviDirection == 'rtl' ){
        //previ 6h
        $sensorDataJSPreviWind6 = array_reverse($sensorDataJSPreviWind6);
        $sensorDataJSPreviWindDirection6 = array_reverse($sensorDataJSPreviWindDirection6);
        //previ 24h
        $PreviDataJSWind24 = array_reverse($PreviDataJSWind24);
        $PreviDataJSWindDirection24 = array_reverse($PreviDataJSWindDirection24);
        $scaleHourPrevi24 = array_reverse($scaleHourPrevi24);
    }
    // logIt("result: ".count($sensorDataJSWind24));
    // logIt("result: ".json_encode($sensorDataJSWind24));
    $result = array(
      'image' => $imgUrl,
      'load' => $firstLoad,
      'station_name' => $stationName,
      //'display_name' => self::convertStr($stationData['display_name']),
      'display_name' => $stationData['display_name'],
      'activities' => $activitiesHtml,
      'last_data_received' => date('j m Y - G:i', strtotime($stationData['data_time']) ),
      //'last_data_received' => date('j/m/Y - G:i', strtotime($stationData['data_time']) + ( 3600*(1+date('I')) ) ),
      'lat' => $stationData['latitude'],
      'lng' => $stationData['longitude'],
      'infos_windspots' => self::lang('NO_NEWS_FOR_NOW'),
      'temperature' => $sensorDataTemperature,
      'temperature_water' => $sensorDataTemperatureWater,
      'barometer' => $sensorDataBarometer,
      'humidity' => $sensorDataHumidity,
      'wind_gust' => $sensorDataWindGust,
      'wind_gust_max' => $sensorDataWindGustMax,
      'wind_speed_med' => $sensorDataWindMed,
      'wind_speed_min' => $sensorDataWindMin,
      'wind_direction' => $sensorDataWindDirection,
      'wind_direction_letter' => $sensorDataWindDirectionLetter,
      'wind_speed' => $sensorDataWind,
      'wind_unit' => $windUnit,
      'auto_logout' => '0',
      'graph_direction' => $graphDirection,
      'graph_previ_direction' => $graphPreviDirection,
      'wind_data_period_1' => array(
          $sensorDataJSWind1,
          $sensorDataJSWindDirection1,
          $sensorDataJSWindGust1,
          $scaleHour1
      ),
      'wind_data_Previ_6' => array(
          $sensorDataJSPreviWind6,
          $sensorDataJSPreviWindDirection6
      ),
      'wind_data_period_12' => array(
          $sensorDataJSWind12,
          $sensorDataJSWindDirection12,
          $sensorDataJSWindGust12,
          $scaleHour12
      ),
      'wind_data_period_24' => array(
          $sensorDataJSWind24,
          $sensorDataJSWindDirection24,
          $sensorDataJSWindGust24,
          $scaleHour24
      ),
      'wind_data_Previ_24' => array(
          $PreviDataJSWind24,
          $PreviDataJSWindDirection24,
          $scaleHourPrevi24
      ),
      'markers_station' => $resultMarkers,
      'update_markers_station' => $updateMarkersStation,
      'status' => 1
    );
    $result = json_encode($result);
    echo $result;
    // logIt("Station Data: ".$result);
    exit();
  }
  public static function check_url($url) {
    $headers = @get_headers( $url);
    $headers = (is_array($headers)) ? implode( "\n ", $headers) : $headers;
    return (bool)preg_match('#^HTTP/.*\s+[(200|301|302)]+\s#i', $headers);
  }
  public static function prepareGraphDataJs( $arr = array(), $resultArr = array(), $windUnit = '' ){
    //prepare data for JS (tranform key time to key int (egal to i in JS loop) and convert str to float)
    if( is_array($arr) && count($arr) > 0 ){
      $i = 0;
      foreach($arr as $time => $val){
        if( $val != null ){
          //number value
          if( !empty( $windUnit) ){
            //use this only for wind data
            $val = self::convertWindUnit( $windUnit, $val );
          }
          $resultArr[$i] = (float)$val;
        }else{
          //no value
          $resultArr[$i] = null;
        }
        $i++; 
      }
    }
    return $resultArr;
  }
  public static function convertWindUnit( $windUnit = 'kts', $data = 0 ){
    //Information about scale wind strength: (calculate and rounded)
    //KTS : 0 - 1-4   - 5-9   - 10-14 - 15-19 - 20-34 - 35+
    //KMH : 0 - 1-7   - 9-16  - 18-25 - 27-35 - 37-62 - 65+   //1x kts = 1.852 kmh
    //MS  : 0 - 1-2   - 3-4   - 5-7   - 8-9   - 10-17 - 18+   //1x kts = 0.514444 ms
    //BFT : 0 - 1-2   - 3   - 4   - 5   - 6-8   - 8+    // < 8 bft => 5 x (bf - 1) = kts OR (kts/5) + = bf // > 8 bft => 5 x bf = kts OR kts/5 = bf
    //Information about scale wind strength: (adapted and use on website)
    //KTS : 0 - 1-4   - 5-9   - 10-14 - 15-19 - 20-34 - 35+
    //KMH : 0 - 1-8   - 9-17  - 18-26 - 27-36 - 37-63 - 64+   
    //MS  : 0 - 1-2   - 3-4   - 5-7   - 8-9   - 10-17 - 18+   
    //BFT : 0 - 1-2   - 3   - 4   - 5   - 6-8   - 8+    
    //units avaialble => kts / bft / kmh / ms
    switch ( $windUnit ) {
      case 'kmh':
        $data = round( ( $data * 3.6 ), 0 );
        break;
      case 'ms':
        $data = round( ( $data + 0 ), 2 );
        break;
      case 'bft':
        $data = round( ( ( ( ( $data * 3.6 ) / 1.852 ) / 5 ) + 1 ), 0 );
        break;
      case 'kts':
      default:
        $data = round( ( ( $data * 3.6 ) / 1.852 ), 0 );
        break;
    }
    return $data;
  }
  public static function getDirectionLetter( $windDirection = 0 ){
    $direction = 'N';
    $windDirection = (float)$windDirection;
    //each parts 22.5 deg
    //north => 360 - 11.25 && 0 + 11.25
    if( $windDirection <= 11.25 ){
      $direction = 'N';
    }elseif( $windDirection > 11.25 && $windDirection <= 33.75 ){
      $direction = 'NNE';
    }elseif( $windDirection > 33.75 && $windDirection <= 56.25 ){
      $direction = 'NE';
    }elseif( $windDirection > 56.25 && $windDirection <= 78.75 ){
      $direction = 'ENE';
    }elseif( $windDirection > 78.75 && $windDirection <= 101.25 ){
      $direction = 'E';
    }elseif( $windDirection > 101.25 && $windDirection <= 123.75 ){
      $direction = 'ESE';
    }elseif( $windDirection > 123.75 && $windDirection <= 146.25 ){
      $direction = 'SE';
    }elseif( $windDirection > 146.25 && $windDirection <= 168.75 ){
      $direction = 'SSE';
    }elseif( $windDirection > 168.75 && $windDirection <= 191.25 ){
      $direction = 'S';
    }elseif( $windDirection > 191.25 && $windDirection <= 213.75 ){
      $direction = 'SSO';
    }elseif( $windDirection > 213.75 && $windDirection <= 236.25 ){
      $direction = 'SO';
    }elseif( $windDirection > 236.25 && $windDirection <= 258.75 ){
      $direction = 'OSO';
    }elseif( $windDirection > 258.75 && $windDirection <= 281.25 ){
      $direction = 'O';
    }elseif( $windDirection > 281.25 && $windDirection <= 303.75 ){
      $direction = 'ONO';
    }elseif( $windDirection > 303.75 && $windDirection <= 326.25 ){
      $direction = 'NO';
    }elseif( $windDirection > 326.25 && $windDirection <= 348.75 ){
      $direction = 'NNO';
    }else{
      $direction = 'N';
    }
    return $direction;
  }
  public static function time_minutes_round($hour = '', $minutes = 10, $format = "Y-m-d H:i:00"){
    $seconds = strtotime($hour);
    $rounded = round($seconds / ($minutes * 60)) * ($minutes * 60);
    $result =  date($format, $rounded);
    if( strtotime($hour) < strtotime($result) && (strtotime($result) - strtotime($hour) < 600) ){
      //use previous 10minute period
      $result = date($format, (strtotime($result) - 600) );
    }
    return $result;
  }
  public static function generateModalContent( $type = '' ){
    $result = '';
    switch( $type ){
      case 'config':
        $result = array(
          'wrapper_id' => 'user_config_form',
          'modal_content' => self::generateModalConfigContent()
        );
        break;
      default:
        break;
    }
    $result = json_encode($result);
    echo $result;
    exit();
  }
  public static function generateModalConfigContent(){
    //call during call back of login process and refresh page (F5)
    $result = '';
    $result .= '<label>'.self::lang('WIND_UNIT').'</label><br />';
    $result .= self::generateConfigWindUnitsList();
    $result .= '<br /><br />';
    $result .= '<label>'.self::lang('DEFAULT_STATION').'</label><br />';
    $result .= self::generateConfigStationList();
    $result .= '<br /><br />';
    $result .= '<label>'.self::lang('GRAPH_DIRECTION').'</label><br />';
    $result .= self::generateConfigGraphDirectionList();
    $result .= '<br /><br />';
    $result .= '<label>'.self::lang('GRAPH_PREVI_DIRECTION').'</label><br />';
    $result .= self::generateConfigGraphPreviDirectionList();
    $result .= '<br /><br />';
    $result .= '<label>'.self::lang('LANGUAGE').'</label><br />';
    $result .= self::generateConfigLanguageList();
    $result .= '<br /><br />';
    $result .= '<input type="button" value="'.self::lang('SAVE').'" class="modal_btn" onclick="userMenu(\'config\', \'save\');" />'; 
    $result .= '<input type="button" value="'.self::lang('CANCEL').'" class="modal_btn" onclick="userMenu(\'config\', \'cancel\');" />';
    $result .= '<br /><br />';
    $result .= '<div class="modal_msg" style="margin-bottom: 5px;"></div>';
    $result .= '<div class="modal_error" style="margin-bottom: 0px;"></div>';
    return $result;
  }
  public static function getBrowserLanguage( $short = false ){
    $lang = 'fr_FR';
    if( isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ){
      $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
      switch ($lang){
        case "fr":
          $lang = 'fr_FR';
          $langShort = 'fr';
          break;
        default:
        case "en":
          $lang = 'en_GB';
          $langShort = 'en';
          break;
        case "de":
          $lang = 'de_DE';
          $langShort = 'de';
          break;
      }
    }
    if( $short ){
      return $langShort;
    }else{
      return $lang;
    }
  }
  private static function generateConfigWindUnitsList(){
    $result = '';
    $userPrefWindUnit = $_SESSION['W_UNIT'];
    $selected_KMH = '';
    $selected_BFT = '';
    $selected_MS = '';
    $selected_KTS = '';
    switch( $userPrefWindUnit ){
      case '_kmh':
        $selected_KMH = 'selected';
        break;
      case '_bft':
        $selected_BFT = 'selected';
        break;
      case '_ms':
        $selected_MS = 'selected';
        break;
      default:
      case '_kts':
        $selected_KTS = 'selected';
        break;
    }
    //generate list
    $result .= '<select id="pref_wind_unit">';
      $result .= '<option value="_kmh" '.$selected_KMH.'>'.self::lang('UNIT_KMH').'</option>';
      $result .= '<option value="_bft" '.$selected_BFT.'>'.self::lang('UNIT_BFT').'</option>';
      $result .= '<option value="_ms" '.$selected_MS.'>'.self::lang('UNIT_MS').'</option>';
      $result .= '<option value="_kts" '.$selected_KTS.'>'.self::lang('UNIT_KTS').'</option>';
    $result .= '</select>';
    return $result;
  }
  private static function generateConfigStationList(){
    $result = '';
    $stations = $_SESSION['WSTATIONS'];
    $userPrefFavStation = $_SESSION['W_STATION'];
    //user config - station list (preference - favorite stations) -> auto load station after user login
    $result .= '<select id="pref_fav_station">';
    $result .= '<option value="1111">' . self::lang('PREFERENCES_FAVORITE_STATION_DEFAULT_VALUE') . '</option>';
    if (is_array($stations) && count($stations) > 0) {
      foreach ($stations as $key => $station) {
        //favorite station (prefrence id => 2 / pref_fav_station
        $selectedFavStation = '';
        if ($key == $userPrefFavStation) {
          $selectedFavStation = 'selected';
        }
        $result .= '<option value="' . $station['station_name'] . '" ' . $selectedFavStation . '>' . $station['display_name'] . '</option>';
      }
    }
    $result .= '</select>';
    return $result;
  }
  private static function generateConfigGraphDirectionList(){
    $userPrefGraphDirection = $_SESSION['W_GRAPH_LTR'];
    $result = '';
    $result .= '<select id="pref_graph_direction">';
    $ltrSelected = '';
    if ($userPrefGraphDirection == 'ltr') {
      $ltrSelected = 'selected';
    }
    $result .= '<option value="ltr" ' . $ltrSelected . '>' . self::lang('PREFERENCES_GRAPH_DIRECTION_LTR') . '</option>';
    $rtlSelected = '';
    if ($userPrefGraphDirection == 'rtl') {
      $rtlSelected = 'selected';
    }
    $result .= '<option value="rtl" ' . $rtlSelected . '>' . self::lang('PREFERENCES_GRAPH_DIRECTION_RTL') . '</option>';
    $result .= '</select>';
    return $result;
  }
  private static function generateConfigGraphPreviDirectionList(){
    $userPrefGraphDirection = $_SESSION['W_GRAPH_PREV_LTR'];
    $result = '';
    $result .= '<select id="pref_graph_previ_direction">';
    $ltrSelected = '';
    if ($userPrefGraphDirection == 'ltr') {
      $ltrSelected = 'selected';
    }
    $result .= '<option value="ltr" ' . $ltrSelected . '>' . self::lang('PREFERENCES_GRAPH_DIRECTION_LTR') . '</option>';
    $rtlSelected = '';
    if ($userPrefGraphDirection == 'rtl') {
      $rtlSelected = 'selected';
    }
    $result .= '<option value="rtl" ' . $rtlSelected . '>' . self::lang('PREFERENCES_GRAPH_DIRECTION_RTL') . '</option>';
    $result .= '</select>';
    return $result;
  }
  private static function generateConfigLanguageList(){
     //get pref in session
    $lang =  $_SESSION['W_LANG'];
    $selected_FR = '';
    $selected_EN = '';
    $selected_DE = '';
    switch ($lang) {
      default:
      case 'fr_FR':
        $selected_FR = 'selected';
        break;
      case 'en_GB':
        $selected_EN = 'selected';
        break;
      case 'de_DE':
        $selected_DE = 'selected';
        break;
    }
    $result = '';
    $result .= '<select id="pref_user_language">';
    $result .= '<option value="fr_FR" ' . $selected_FR . '>' . self::lang('PREFERENCES_LANGUAGE_FRENCH') . '</option>';
    $result .= '<option value="en_GB" ' . $selected_EN . '>' . self::lang('PREFERENCES_LANGUAGE_ENGLISH') . '</option>';
    $result .= '<option value="de_DE" ' . $selected_DE . '>' . self::lang('PREFERENCES_LANGUAGE_GERMAN') . '</option>';
    $result .= '</select>';
    return $result;
  }
  public static function saveConfigPreferences($prefWindUnit = '', $prefFavoriteStation = '', $prefGraph = '', $prefGraphForecast = '', $prefLanguage = '' ){
    // logIt("Helper saveConfigPreferences prefWindUnit: ".$prefWindUnit);
    //update user preferences in session
    $_SESSION['W_LANG'] = $prefLanguage;
    $_SESSION['W_UNIT'] = $prefWindUnit;
    $_SESSION['W_STATION'] = $prefFavoriteStation;
    $_SESSION['W_GRAPH_LTR'] = $prefGraph;
    $_SESSION['W_GRAPH_PREV_LTR'] = $prefGraphForecast;
    // update cookies
    setcookie('W_LANG', $prefLanguage);
    setcookie('W_UNIT', $prefWindUnit);
    setcookie('W_STATION', $prefFavoriteStation);
    setcookie('W_GRAPH_LTR', $prefGraph);
    setcookie('W_GRAPH_PREV_LTR', $prefGraphForecast);
    //get current station
    //$_SESSION['W_STATION'] = WindspotsHelper::getCurrentStation();
    $page = $_SERVER['PHP_SELF'];
    $sec = "0";
    header("Refresh: $sec; url=$page");
    return;
    }
  private static function getStationPreviData( $stationName = '', $from = '', $to = '' ){
    $result = null;
    if( empty($stationName) || empty($from) || empty($to) ){
      return $result;
    }
    $dbLink = self::connectDb();
    $query = "SELECT `reference_time`, `speed`, `direction`";
    $query .= " FROM `forecast` WHERE `station_name` = '".self::escapeStr( $dbLink, $stationName )."' ";
    //time slot
    $query .= " AND `reference_time` >= '".self::escapeStr( $dbLink, $from)."' AND `reference_time` <= '".self::escapeStr( $dbLink, $to)."'";
    //order
    $query .= " ORDER BY `reference_time` ASC ;";
    $data = mysqli_query( $dbLink, $query );
    if( empty($data) ){
      self::disconnectDb( $dbLink );
      return $result;
    }
    while ( $PreviData = mysqli_fetch_assoc( $data ) ) {
      $result[] = $PreviData;
    }
    mysqli_free_result($data);
    self::disconnectDb( $dbLink );
    return $result;
  }
  private static function getStationSensorData( $sensorId = 0, $last = false, $from = '', $to = '', $period = 0 ){
    $result = null;
    if( empty($sensorId) || ( (empty($from) || empty($to) || empty($period)) && empty($last) ) ){
      return $result;
    }
    $dbLink = self::connectDb();
    $query = "SELECT * FROM `sensor_data`";
    $query .= " WHERE `sensor_id` = '".self::escapeStr( $dbLink, $sensorId )."' ";
    if( empty($last) ){
      //data for period
      if( $period == 1 ){
        $query .= " AND `ten` = '0'";
      }else{
        $query .= " AND `ten` = '1'";
      }
      //time slot
      $query .= " AND `sensor_time` >= '".self::escapeStr( $dbLink, $from)."' AND `sensor_time` <= '".self::escapeStr( $dbLink, $to)."'";
      //order
      $query .= " ORDER BY `sensor_time` ASC ;";
      $data = mysqli_query( $dbLink, $query );
      // logIt("Query(".mysqli_num_rows($data)."): ".$query);
      if( empty($data) ){
        self::disconnectDb( $dbLink );
        return $result; 
      }
      while ( $sensorData = mysqli_fetch_assoc( $data ) ) {
        $result[] = $sensorData;
      }
      mysqli_free_result($data);
    }else{
      //order/limit
      $query .= " ORDER BY `sensor_time` DESC LIMIT 1 ;";
      $data = mysqli_query( $dbLink, $query );
      // logIt("Query: ".$query);
      if( empty($data) ){
        self::disconnectDb( $dbLink );
        return $result;
      }
      $result = mysqli_fetch_assoc( $data );
      mysqli_free_result($data);
    }
    self::disconnectDb( $dbLink );
    return $result;
  }
  private function log( $logPath, $msg, $level = _WINDSPOTS_LOG_LVL_INFO, $cleanLogFile = false ) {
    //use for log (debug)
    if ( empty( $logPath ) || empty ( $msg ) ) return null;
    if ( $cleanLogFile ) {
      if ( !is_dir( $logPath ) && file_exists( $logPath ) ) unlink( $logPath );
    }
    $logFile = fopen( $logPath, 'a+' );
    //server GMT 0 -> set Geneva time
    date_default_timezone_set('Europe/Paris');
    $line = '';
    if ( $level === _WLOG_LVL_START ) $line = '[' . date( 'd-m-Y H:i:s' ) . '] ' . ' -**************************************************- ' . "\r\n";
    $line .= '[' . date( 'd-m-Y H:i:s' ) . '] ' . $level . ' - ' . $msg . "\r\n";
    if ( $level === _WLOG_LVL_END ) $line .= '[' . date( 'd-m-Y H:i:s' ) . '] ' . ' -**************************************************- ' . "\r\n";
    fwrite( $logFile, $line );
    fclose( $logFile );
    return $line;
  }
  public static function redirect($url, $statusCode = 303)  {
    header('Location: ' . $url, true, $statusCode);
    exit();
  }
  private static function encrypt ($key, $str) {
    $iv = mcrypt_create_iv(_WINDSPOTS_IV_SIZE, MCRYPT_DEV_URANDOM);
    $crypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $str, MCRYPT_MODE_CBC, $iv);
    $combo = $iv . $crypt;
    $garble = base64_encode($iv . $crypt);
    return $garble;
  }
  public static function decrypt ($key, $garble) {
    $combo = base64_decode($garble);
    $iv = substr($combo, 0, _WINDSPOTS_IV_SIZE);
    $crypt = substr($combo, _WINDSPOTS_IV_SIZE, strlen($combo));
    $str = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $crypt, MCRYPT_MODE_CBC, $iv);
    $str = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $str);
    return $str;
  }
  public static function getCurrentStation(){
    if( isset($_SESSION['WCURRENT_STATION']) && !empty($_SESSION['WCURRENT_STATION']) ){
      return $_SESSION['WCURRENT_STATION'];
    }else{
      return false;
    }
  }
  public static function setCurrentStation( $stationName = '', $reset = false ){
    if( $reset ){
      $_SESSION['WCURRENT_STATION'] = '';
      return true;
    }
    if( !empty($stationName) ){
      $_SESSION['WCURRENT_STATION'] = $stationName;
      return true;
    }
    return false;
  }
  private static function getPreferences( $idAsKey = true ){
    //define preference for V3 here for the moment (not in db)
    $preferences = array(
      '1' => 'pref_wind_unit',
      '2' => 'pref_fav_station',
      '3' => 'pref_graph_direction',
      '4' => 'pref_graph_previ_direction'
    );
    if( $idAsKey ){ return $preferences; }
    $preferences = array(
        'pref_wind_unit' => '1',
        'pref_fav_station' => '2',
        'pref_graph_direction' => '3',
        'pref_graph_previ_direction' => '4'
    );
    return $preferences;
  }
  private static function cleanUserSession(){
    unset($_SESSION['WUSER']);
    return true;
  }
  private static function getRandomKey( $length = 10, $useNumberChars = false, $useSpecialChars = false, $forceStartAlpha = true ) {
    if ( !is_numeric( $length ) ) return false;
    $length = (int)$length;
    $alpha = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $number = '0123456789';
    $alphaSpecial = '_-.*!?+@#|';
    $workingStr = $alpha;
    if ( $useNumberChars ) $workingStr .= $number;
    if ( $useSpecialChars ) $workingStr .= $alphaSpecial;
    $randomKey = '';
    for ( $i = 0; $i < $length; $i++ ) {
      $index = mt_rand( 0, ( strlen( $workingStr ) - 1 ) );
      $randomKey .= $workingStr[ $index ];
    }
    if ( $forceStartAlpha ) {
      $alphaCheck = str_split( $alpha );
      if ( !in_array( $randomKey[0], $alphaCheck ) ) {
        $index = mt_rand( 0, ( strlen( $alpha ) - 1 ) );
        $randomKey[0] = $alpha[ $index ];
      }
    }
    return $randomKey;
  }
}