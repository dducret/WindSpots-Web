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
// API
$windspotsAPI = $rootPath."/../api/library/windspots";
require_once $windspotsAPI.'/db.php';
//
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
  public static function getStations($fields = array(), $online = true){
    return WindspotsDB::getStations($online);
  }
  public static function lang($txt = '', $convert = true){
    $lang = array();
    if(isset($_SESSION['WTRANSLATION']) && is_array($_SESSION['WTRANSLATION'])){
      $lang = $_SESSION['WTRANSLATION'];
    }
    if(!array_key_exists($txt,$lang)){
      return $txt;
    }else{
      //translation not found -> use string recieved in parameter
      return $lang[$txt];
    }
  }
  public static function loadTranslationFile($lang = 'fr_FR'){
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
        if(isset($line[0]) && !empty($line[0]) && isset($line[1])){
          $_SESSION['WTRANSLATION'][$line[0]] = $line[1];
        } 
      }
      fclose($handle);
    } else {
      // error opening the file.
    }
  }
  public static function loadStationData($stationName = 'default', $firstLoad = 0){
    //default values
    $graphDirection = 'ltr';
    $graphForecastDirection = 'ltr';
    //load pref graph direction
    if (isset($_SESSION['W_GRAPH_LTR']) && $_SESSION['W_GRAPH_LTR']) {
      $graphDirection = $_SESSION['W_GRAPH_LTR'];
    }
    //load pref graph Forecast direction
    if (isset($_SESSION['W_GRAPH_PREV_LTR']) && $_SESSION['W_GRAPH_PREV_LTR']) {
      $graphForecastDirection = $_SESSION['W_GRAPH_PREV_LTR'];
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
    $stationData = WindspotsDB::getStationByName($stationName);
    //server GMT 0 -> set Geneva time
    date_default_timezone_set('Europe/Zurich');
    //define data time slot
    $now = date("Y-m-d H:i:00");
    $to = date("Y-m-d H:i:00", strtotime($now) - 60);   //round time to previous minute
    $from = '';
    $period = '';
    //check sensor id (if same => get only one request)
    $sensorIdTemp = array();
    if(!empty($stationData['wind_id'])){
        $sensorIdTemp['wind'] = $stationData['wind_id'];
    }
    if(!empty($stationData['barometer_id'])){
        $sensorIdTemp['barometer_pressure'] = $stationData['barometer_id'];
    }
    if(!empty($stationData['barometer_id'])){
        $sensorIdTemp['barometer_temperature'] = $stationData['barometer_id'];
    }
    if(!empty($stationData['temperature_id'])){
        $sensorIdTemp['temperature_temperature'] = $stationData['temperature_id'];
    }
    if(!empty($stationData['temperature_id']) ){
        $sensorIdTemp['temperature_humidity'] = $stationData['temperature_id'];
    }
    $lastValueTemperature      = 0;
    $lastValueTemperatureWater = 0;
    $lastValueHumidity         = 0;
    $lastValueBarometer        = 0;
    $lastValueWind             = 0;
    $lastValueDirection        = 0;
    $lastValueWindGust         = 0;
    //exception about water temperatue no changing so fast
    if(!empty($stationData['water_id'])){
        $waterTemperatueResult = WindspotsDB::getStationSensorData($stationData['water_id'], true, null, null, null);
        // logIt("Water: ".json_encode($waterTemperatueResult));
        if(is_array($waterTemperatueResult) && isset($waterTemperatueResult['temperature']) && !empty($waterTemperatueResult['temperature'])){
            $lastValueTemperatureWater = $waterTemperatueResult['temperature'];
        }
    }
    // ---- Graph Data (1h) ----
    $period = 1;
    $diffTime = $period * 60 * 60;
    $from = date("Y-m-d H:i:00", strtotime($to) - $diffTime);  //round time to minute
    $sensorDataTemperature      = '';   //station data -> last value
    $sensorDataTemperatureWater = '';   //station data -> last value
    $sensorDataBarometer        = '';   //station data -> last value
    $sensorDataHumidity         = '';   //station data -> last value
    $sensorDataSpeedGust        = '';   //station data -> last value
    $sensorDataSpeedGustMax     = '';   //station data -> rafal/gust max during last 30min
    $sensorDataSpeedMed         = '';   //station data -> medium (average) wind speed during last 30min
    $sensorDataSpeedMin         = '';   //station data -> minimum wind speed during last 30min
    $sensorDataSpeedData        = array();  //use for wind min speed (during last 30min)
    $sensorDataSpeedAverageData = array();  //use for wind med average (during last 30min)
    $sensorDataDirection        = '';   //station data -> last value
    $sensorDataSpeed            = '';   //station data -> last value
    $sensorDataWorking          = array();  // to get station data (working array)
    $sensorDataSpeed1           = array();
    $sensorDataDirection1       = array();
    $sensorDataGust1            = array();
    $sensorDataJSSpeed1         = array();
    $sensorDataJSDirection1     = array();
    $sensorDataJSGust1          = array();
    $scale1                 = array();
    for($i = strtotime($from); $i <= strtotime($to); $i += 60){
        $sensorDataSpeed1[$i]     = null; 
        $sensorDataDirection1[$i] = null; 
        $sensorDataGust1[$i]      = null; 
        $sensorDataWorking[$i]    = array( 'sensor_id' => null,
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
    if(is_array($sensorIdTemp) && count($sensorIdTemp) > 0){
      foreach($sensorIdTemp as $sensorKey => $sensorId){
        $sensorData = WindspotsDB::getStationSensorData($sensorId, false, $from, $to, false);
        if(is_array($sensorData) && count($sensorData) > 0){
          foreach($sensorData as $key => $data){
            $time = strtotime($data['sensor_time']);
            if($sensorKey == 'wind' && array_key_exists($time, $sensorDataSpeed1) && $data['speed'] != '' && $data['speed'] != null){
              $sensorDataSpeed1[$time] = $data['speed'];
            }
           if($sensorKey == 'wind' && array_key_exists($time, $sensorDataDirection1) && $data['direction'] != '' && $data['direction'] != null){
              $sensorDataDirection1[$time] = $data['direction'];
            }
            if($sensorKey == 'wind' && array_key_exists($time, $sensorDataGust1) && $data['gust'] != '' && $data['gust'] != null){
              $sensorDataGust1[$time] = $data['gust'];
            }
            //keep data to calculate station data/info
            if(array_key_exists($time, $sensorDataWorking)){
              if($sensorKey == 'wind' && $data['speed'] != '' && $data['speed'] != null){ $sensorDataWorking[$time]['speed'] = $data['speed']; }
              if($sensorKey == 'wind' && $data['average'] != '' && $data['average'] != null){ $sensorDataWorking[$time]['average'] = $data['average']; }
              if($sensorKey == 'wind' && $data['gust'] != '' && $data['gust'] != null){ $sensorDataWorking[$time]['gust'] = $data['gust']; }
            }
            if($sensorKey == 'temperature_temperature' &&  $data['temperature'] != '' && $data['temperature'] != null){
              $lastValueTemperature = $data['temperature'];
            }else{
              if($sensorKey == 'barometer_temperature' &&  $data['temperature'] != '' && $data['temperature'] != null){
                $lastValueTemperature = $data['temperature'];
              }
            }
            if($sensorKey == 'temperature_humidity' && $data['humidity'] != '' && $data['humidity'] != null){ $lastValueHumidity = $data['humidity']; }
            if($sensorKey == 'barometer_pressure' &&  $data['barometer'] != '' && $data['barometer'] != null){ $lastValueBarometer = $data['barometer']; }
            if($time >= (strtotime($to) - 60)){
              if($sensorKey == 'wind' && $data['speed'] != '' && $data['speed'] != null){ $lastValueWind = $data['speed']; }
              if($sensorKey == 'wind' && $data['direction'] != '' && $data['direction'] != null){ $lastValueDirection = $data['direction']; }
              if($sensorKey == 'wind' && $data['gust'] != '' && $data['gust'] != null){ $lastValueWindGust = $data['gust']; }
            }
          } // foreach($sensorData as $key => $data)
        }
      } // foreach($sensorIdTemp as $sensorKey => $sensorId)
    }
    //generate graph 1h scale hour (each 10 min)
    for($i = strtotime($from); $i <= strtotime($to); $i += 600){
      $scale1[] = str_replace( ":", "h", date('G:i', $i));
    }
    // ---- Graph Data (12h/24h) ----
    $now = self::time_minutes_round(date("Y-m-d H:i:00"), 10);
    $to = date("Y-m-d H:i:00", strtotime($now));
    $period = 24;
    $diffTime = $period * 60 * 60;
    $from24h = date("Y-m-d H:i:00", strtotime($to) - $diffTime); //round time to minute
    $period = 12;
    $diffTime = $period * 60 * 60;
    $from12h = date("Y-m-d H:i:00", strtotime($to) - $diffTime); //round time to minute
    $period = 6;
    $diffTime = $period * 60 * 60;
    $fromForecast6h = date("Y-m-d H:00:00", strtotime($to) - $diffTime);  //round time to hour
    //(period * sec * min)  -> 73x data - each 20 min for the graph 24h (to have the first and last with an scale hour of 1h)
    //            -> 73x data - each 10 min for the graph 12h (to have the first and last with an scale hour of 1h)
    //=> total results of query => 144x data => each 10 min last 24h (with data computed period 10min)
    //init graph result (12h/24h)
    $sensorDataSpeed12            = array();
    $sensorDataDirection12       = array();
    $sensorDataGust12        = array();
    $sensorDataJSSpeed12          = array();
    $sensorDataJSDirection12     = array();
    $sensorDataJSGust12      = array();
    $scale12                 = array();
    $sensorDataSpeed24            = array();
    $sensorDataDirection24       = array();
    $sensorDataSpeedGust24        = array();
    $sensorDataJSSpeed24          = array();
    $sensorDataJSDirection24     = array();
    $sensorDataJSGust24      = array();
    $scale24                 = array();
    $sensorDataForecastSpeed6        = array();
    $sensorDataForecastDirection6   = array();
    $sensorDataForecastGust6        = array();
    $sensorDataJSForecastSpeed6      = array();
    $sensorDataJSForecastDirection6 = array();
    $sensorDataJSForecastGust6       = array();
    $scale6                  = array();
    //prepare exact entries in result (key => time)
    //because we don't have automatically a record for each 10 minute in bdd -> so complete by null to have the number of values expected in JS
    for($i = strtotime($from12h); $i <= strtotime($to); $i += (60 * 10)){
      $sensorDataSpeed12[$i]      = null; //graph -> wind strength
      $sensorDataDirection12[$i] = null; //graph -> wind direction
      $sensorDataGust12[$i]  = null; //graph -> rafale/gust
    }
    //because we don't have automatically a record for each 20 minute in bdd -> so complete by null to have the number of values expected in JS
    for($i = strtotime($from24h); $i <= strtotime($to); $i += (60 * 20)){
      $sensorDataSpeed24[$i]      = null; //graph -> wind strength
      $sensorDataDirection24[$i] = null; //graph -> wind direction
      $sensorDataSpeedGust24[$i]  = null; //graph -> rafale/gust
    }
    //because we don't have automatically a record for each 10 minute in bdd -> so complete by null to have the number of values expected in JS
    for($i = strtotime($fromForecast6h); $i <= strtotime($to); $i += (60 * 20)){
      $sensorDataForecastSpeed6[$i]      = null; //graph -> wind strength
      $sensorDataForecastDirection6[$i] = null; //graph -> wind direction
    }
    //get data by sensor id (station can be have the same for all value or not...) -> make like this to limit redundant queries
    if(is_array($sensorIdTemp) && count($sensorIdTemp) > 0){
      foreach($sensorIdTemp as $sensorKey => $sensorId){
        $sensorData = WindspotsDB::getStationSensorData($sensorId, false, $from24h, $to, true);
        $time24h = strtotime($from24h);
        $time12h = strtotime($from12h);
        $timeForecast6h = strtotime($fromForecast6h);
        if(is_array($sensorData) && count($sensorData) > 0){
          foreach($sensorData as $key => $data){
            $time = strtotime($data['sensor_time']);
            if($sensorKey == 'wind' && array_key_exists($time, $sensorDataSpeed24) && $data['speed'] != '' && $data['speed'] != null){
              $sensorDataSpeed24[$time] = $data['speed'];
            }
            if($sensorKey == 'wind' && array_key_exists($time, $sensorDataDirection24) && $data['direction'] != '' && $data['direction'] != null){
              $sensorDataDirection24[$time] = $data['direction'];
            }
            if($sensorKey == 'wind' && array_key_exists($time, $sensorDataSpeedGust24) && $data['gust'] != '' && $data['gust'] != null){
              $sensorDataSpeedGust24[$time] = $data['gust'];
            }
            if($time >= $time12h){
              if($sensorKey == 'wind' && array_key_exists($time, $sensorDataSpeed12) && $data['speed'] != '' && $data['speed'] != null){
                $sensorDataSpeed12[$time] = $data['speed'];
              }
              if($sensorKey == 'wind' && array_key_exists($time, $sensorDataDirection12) && $data['direction'] != '' && $data['direction'] != null){
                $sensorDataDirection12[$time] = $data['direction'];
              }
              if($sensorKey == 'wind' && array_key_exists($time, $sensorDataGust12) && $data['gust'] != '' && $data['gust'] != null){
                $sensorDataGust12[$time] = $data['gust'];
              }
            }
            if($time >= $timeForecast6h){
              if($sensorKey == 'wind' && array_key_exists($time, $sensorDataForecastSpeed6) && $data['speed'] != '' && $data['speed'] != null){
                $sensorDataForecastSpeed6[$time] = $data['speed'];
              }
              if($sensorKey == 'wind' && array_key_exists($time, $sensorDataForecastDirection6) && $data['direction'] != '' && $data['direction'] != null){
                $sensorDataForecastDirection6[$time] = $data['direction'];
              }
              if($sensorKey == 'wind' && array_key_exists($time, $sensorDataForecastGust6) && $data['gust'] != '' && $data['gust'] != null){
                $sensorDataForecastSpeedGust6[$time] = $data['gust'];
              }
            }
          } // foreach($sensorData as $key => $data)
        }
      } // foreach($sensorIdTemp as $sensorKey => $sensorId)
    }
    //generate graph 24h scale hour (each hour)
    for($i = strtotime($from24h); $i <= strtotime($to); $i += 3600){
      $scale24[] = str_replace( ":", "h", date('G:i', $i));
    }
    //generate graph 12h scale hour (each hour)
    for($i = strtotime($from12h); $i <= strtotime($to); $i += 3600){
      $scale12[] = str_replace( ":", "h", date('G:i', $i));
    }
    //prepare data for JS (tranform key time to key int (egal to i in JS loop) and convert str to float)
    $sensorDataJSSpeed24      = self::prepareGraphDataJs($sensorDataSpeed24, $sensorDataJSSpeed24, $windUnit);
    $sensorDataJSDirection24 = self::prepareGraphDataJs($sensorDataDirection24, $sensorDataJSDirection24, '');
    $sensorDataJSGust24  = self::prepareGraphDataJs($sensorDataSpeedGust24, $sensorDataJSGust24, $windUnit);
    $sensorDataJSSpeed12      = self::prepareGraphDataJs($sensorDataSpeed12, $sensorDataJSSpeed12, $windUnit);
    $sensorDataJSDirection12 = self::prepareGraphDataJs($sensorDataDirection12, $sensorDataJSDirection12, '');
    $sensorDataJSGust12  = self::prepareGraphDataJs($sensorDataGust12, $sensorDataJSGust12, $windUnit);
    $sensorDataJSForecastSpeed6  = self::prepareGraphDataJs($sensorDataForecastSpeed6, $sensorDataJSForecastSpeed6, $windUnit);
    $sensorDataJSForecastDirection6 = self::prepareGraphDataJs($sensorDataForecastDirection6, $sensorDataJSForecastDirection6, '');
    // ---- Graph Forecast Data (24h) ----
    $period = 18; // +6h already use by Forecast 6h => tot: 24h
    $diffTime = $period * 60 * 60;
    $toForecast24h = date("Y-m-d H:00:00", strtotime($now) + $diffTime);  //round time to hour ! //+ (60*60)  and use tot 25 hours to displayed 24h
    $forecastWind24        = array();
    $forecastDirection24   = array();
    $forecastGust24        = array();
    $forecastJSSpeed24     = array();
    $forecastJSDirection24 = array();
    $forecastJSGust24      = array();
    $scaleForecast24   = array();
    //generate graph Forecast 24h scale hour (each hour for Forecast 24h)
    for($i = strtotime($fromForecast6h); $i <= strtotime($toForecast24h); $i += (60 * 60)){
      $scaleForecast24[] = str_replace( ":", "h", date('G:', $i));
    }
    for($i = strtotime($fromForecast6h); $i <= strtotime($toForecast24h); $i += (60 * 60)){
      $forecastWind24[$i]      = null; 
      $forecastDirection24[$i] = null; 
      $forecastGust24[$i]      = null; 
    }
    $forecast = windspotsDB::getStationForecast($stationName, $fromForecast6h, $toForecast24h);
    if(is_array($forecast) && count($forecast) > 0){
      foreach($forecast as $key => $data){
        $time = strtotime($data['reference_time']);
        if(array_key_exists($time, $forecastWind24) && !empty($data['speed'])){
          $forecastWind24[$time] = number_format($data['speed'], 2, '.', '');
        }
        if(array_key_exists($time, $forecastDirection24) && !empty($data['direction'])){
          $forecastDirection24[$time] = $data['direction'];
        }
      }
    }
    $forecastJSSpeed24            = self::prepareGraphDataJs($forecastWind24, $forecastJSSpeed24, $windUnit);
    $forecastJSDirection24        = self::prepareGraphDataJs($forecastDirection24, $forecastJSDirection24, '');
    if($lastValueTemperature      != '' && $lastValueTemperature != null){ $sensorDataTemperature = $lastValueTemperature; }
    if($lastValueTemperatureWater != '' && $lastValueTemperatureWater != null){ $sensorDataTemperatureWater = $lastValueTemperatureWater; }
    if($lastValueHumidity         != '' && $lastValueHumidity != null){ $sensorDataHumidity = $lastValueHumidity; }
    if($lastValueBarometer        != '' && $lastValueBarometer != null){  $sensorDataBarometer = $lastValueBarometer; }
    if($lastValueWind             != '' && $lastValueWind != null){ $sensorDataSpeed = self::convertWindUnit($windUnit, $lastValueWind); }
    if($lastValueDirection        != '' && $lastValueDirection != null){ $sensorDataDirection = $lastValueDirection; }
    if($lastValueWindGust         != '' && $lastValueWindGust != null){ $sensorDataSpeedGust = self::convertWindUnit($windUnit, $lastValueWindGust); }
    if(is_array($sensorDataWorking) && count($sensorDataWorking) > 0){
      $i = 0;
      foreach($sensorDataWorking as $time => $dataWorking){
        if($i >= 29){
          if(!empty($dataWorking['gust']) && $sensorDataSpeedGustMax < $dataWorking['gust']){
            $sensorDataSpeedGustMax = $dataWorking['gust'];
          }
          if(!empty($dataWorking['speed'])){
            $sensorDataSpeedData[] = $dataWorking['speed'];
          }
          if(!empty($dataWorking['average'])){
            $sensorDataSpeedAverageData[] = $dataWorking['average'];
          }
        }
        $i++;
      }
    }
    $sensorDataJSSpeed1 = self::prepareGraphDataJs($sensorDataSpeed1, $sensorDataJSSpeed1, $windUnit);
    $sensorDataJSDirection1 = self::prepareGraphDataJs($sensorDataDirection1, $sensorDataJSDirection1, '');
    $sensorDataJSGust1 = self::prepareGraphDataJs($sensorDataGust1, $sensorDataJSGust1, $windUnit);
    $sensorDataDirectionLetter = self::getDirectionLetter($sensorDataDirection);
    if(!empty($sensorDataSpeedGustMax)){
      $sensorDataSpeedGustMax  = self::convertWindUnit($windUnit, $sensorDataSpeedGustMax);
    }else{
      $sensorDataSpeedGustMax = 0;
    }
    $sensorDataSpeedMed = 0;
    if(is_array($sensorDataSpeedData) && count($sensorDataSpeedData) > 0){
      foreach($sensorDataSpeedData as $val){
      if(!empty($val)){
        $sensorDataSpeedMed += (float)$val;
      }
     }
     if(!empty($sensorDataSpeedMed)){
       $sensorDataSpeedMed = self::convertWindUnit($windUnit, round($sensorDataSpeedMed / count($sensorDataSpeedData), 2));
     }else{
       $sensorDataSpeedMed = 0;
     }
    }
    if(is_array($sensorDataSpeedData) && count($sensorDataSpeedData) > 0){
      $sensorDataSpeedMin = self::convertWindUnit($windUnit, min($sensorDataSpeedData));
    }
    if(empty($sensorDataSpeedMin)){ $sensorDataSpeedMin = 0; }
    //check station activities
    $activitiesHtml = '';
    if(isset($stationData['spot_type'])  && !empty($stationData['spot_type'])){
      if(($stationData['spot_type'] & ST_ACTIVITIES_KITE) == true){
        $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/kitesurf.svg" /></div>';
      }
      if(($stationData['spot_type'] & ST_ACTIVITIES_WINDSURF) == true){
        $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/windsurf.svg" /></div>';
      }
      if(($stationData['spot_type'] & ST_ACTIVITIES_PADDLE) == true){
        $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/paddle.svg" /></div>';
      }
      if(($stationData['spot_type'] & ST_ACTIVITIES_RELAX) == true){
        $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/laze.svg" /></div>';
      }
      if(($stationData['spot_type'] & ST_ACTIVITIES_PARA) == true){
        $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/paraglide.svg" /></div>';
      }
      if(($stationData['spot_type'] & ST_ACTIVITIES_NAGE) == true){
        $activitiesHtml .= '<div class="cellIcoActivity"><img src="svg/swim.svg" /></div>';
      }
    }
    //set current station (session)
    self::setCurrentStation($stationName);
    //prepare mini map marker (to change ico of current station marker / other not change)
    date_default_timezone_set('Europe/Paris');
    $stationUpdateNow = time();
    $updateStationData = false;
    $mapMarkersByStation = array();
    $stationStatus = array();
    $updateMarkersStation = '0';
    if($firstLoad || !isset($_SESSION['WSTATIONS_STATUS_LAST_UPDATE']) || ($stationUpdateNow - $_SESSION['WSTATIONS_STATUS_LAST_UPDATE'] >= (5*60))){
      $updateStationData = true;
      $updateMarkersStation = '1';
    }
    if($updateStationData){
      $stations = WindspotsDB::getStations();
      $_SESSION['WSTATIONS'] = $stations;
      if(is_array($stations) && count($stations) > 0){
        $markerId = 1;
        foreach($stations as $key => $station){
          $stationLastDataUpdate = strtotime($station['data_time']);
          if(($stationUpdateNow - $stationLastDataUpdate) <= 300){
            $stationStatus[$station['station_name']] = true;
          }else{
            $stationStatus[$station['station_name']] = false;
          }
          //map markers
          $markerStation = '';
          if(isset($station['station_name']) && !empty($station['station_name'])
            && isset($station['display_name']) && !empty($station['display_name'])
            && isset($station['latitude']) && !empty($station['latitude'])
            && isset($station['longitude']) && !empty($station['longitude'])
          ){  //Leaflet
            $icoMarker = 'spotIcon';
            if($stationStatus[$station['station_name']] == false){
                $icoMarker = 'spotDisabledIcon';
            }
            //$markerStation .= 'var marker = L.marker(['.$station['latitude'].', '.$station['longitude'].'], {icon: '.$icoMarker.', stationName: \''.$station['station_name'].'\', title: "'.utf8_encode($station['display_name']).'"}); ';  //myCustomId: 5454,
            $markerStation .= 'var marker = L.marker(['.$station['latitude'].', '.$station['longitude'].'], {icon: '.$icoMarker.', stationName: \''.$station['station_name'].'\', title: "'.$station['display_name'].'"}); ';  //myCustomId: 5454,
            $markerStation .= 'marker.on(\'click\', onMarkerClick); ';
            $markerStation .= 'marker.addTo(map); ';
            $markerStation .= 'var marker = null; ';
            $mapMarkersByStation[$station['station_name']] = $markerStation;
          }
        } // foreach
        $_SESSION['WSTATIONS_STATUS'] = $stationStatus;
        $_SESSION['WSTATIONS_STATUS_LAST_UPDATE'] = $stationUpdateNow;
        $_SESSION['WSTATIONS_MAP_MARKERS_BY_STATION'] = $mapMarkersByStation;
        $markersStation = $mapMarkersByStation;
      }else{
        $stations = $_SESSION['WSTATIONS'];
        $markersStation = $_SESSION['WSTATIONS_MAP_MARKERS_BY_STATION'];
      }
    }else{
      $stations = $_SESSION['WSTATIONS'];
      $markersStation = $_SESSION['WSTATIONS_MAP_MARKERS_BY_STATION'];
    }
    $resultMarkers = '';
    if(is_array($markersStation) && count($markersStation) > 0){
      $markerId = 1;
      foreach($markersStation as $key => $marker){
        $tmpMarker = '';
        //Leaflet
        if($key != $stationName){
          $resultMarkers .= $marker;
        }else{
          $icoMarker = 'spotActiveIcon';
          if(isset($_SESSION['WSTATIONS_STATUS'][$stationName]) && $_SESSION['WSTATIONS_STATUS'][$stationName] == false){
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
    if($graphDirection == 'rtl'){
      //1h
      $sensorDataJSSpeed1 = array_reverse($sensorDataJSSpeed1);
      $sensorDataJSDirection1 = array_reverse($sensorDataJSDirection1);
      $sensorDataJSGust1 = array_reverse($sensorDataJSGust1);
      $scale1 = array_reverse($scale1);
      //12h
      $sensorDataJSSpeed12 = array_reverse($sensorDataJSSpeed12);
      $sensorDataJSDirection12 = array_reverse($sensorDataJSDirection12);
      $sensorDataJSGust12 = array_reverse($sensorDataJSGust12);
      $scale12 = array_reverse($scale12);
      //24h
      $sensorDataJSSpeed24 = array_reverse($sensorDataJSSpeed24);
      $sensorDataJSDirection24 = array_reverse($sensorDataJSDirection24);
      $sensorDataJSGust24 = array_reverse($sensorDataJSGust24);
      $scale24 = array_reverse($scale24);
    }
    //check if need reverse chart data (Forecast)
    if($graphForecastDirection == 'rtl'){
        //previ 6h
        $sensorDataJSForecastSpeed6 = array_reverse($sensorDataJSForecastSpeed6);
        $sensorDataJSForecastDirection6 = array_reverse($sensorDataJSForecastDirection6);
        $sensorDataJSForecastGust6 = array_reverse($sensorDataJSForecastGust6);
        //previ 24h
        $forecastJSSpeed24 = array_reverse($forecastJSSpeed24);
        $forecastJSDirection24 = array_reverse($forecastJSDirection24);
        $forecastJSGust24 = array_reverse($forecastJSGust24);
        $scaleForecast24 = array_reverse($scaleForecast24);
    }
    $result = array(
      'image' => $imgUrl,
      'load' => $firstLoad,
      'station_name' => $stationName,
      'display_name' => $stationData['display_name'],
      'activities' => $activitiesHtml,
      'last_data_received' => date('j m Y - G:i', strtotime($stationData['data_time'])),
      'lat' => $stationData['latitude'],
      'lng' => $stationData['longitude'],
      'infos_windspots' => self::lang('NO_NEWS_FOR_NOW'),
      'temperature' => $sensorDataTemperature,
      'temperature_water' => $sensorDataTemperatureWater,
      'barometer' => $sensorDataBarometer,
      'humidity' => $sensorDataHumidity,
      'wind_gust' => $sensorDataSpeedGust,
      'wind_gust_max' => $sensorDataSpeedGustMax,
      'wind_speed_med' => $sensorDataSpeedMed,
      'wind_speed_min' => $sensorDataSpeedMin,
      'wind_direction' => $sensorDataDirection,
      'wind_direction_letter' => $sensorDataDirectionLetter,
      'wind_speed' => $sensorDataSpeed,
      'wind_unit' => $windUnit,
      'auto_logout' => '0',
      'graph_direction' => $graphDirection,
      'graph_previ_direction' => $graphForecastDirection,
      'wind_data_period_1' => array(
          $sensorDataJSSpeed1,
          $sensorDataJSDirection1,
          $sensorDataJSGust1,
          $scale1
      ),
      'wind_data_forecast_6' => array(
          $sensorDataJSForecastSpeed6,
          $sensorDataJSForecastDirection6,
          // $sensorDataJSForecastGust6,
          // $scale6
      ),
      'wind_data_period_12' => array(
          $sensorDataJSSpeed12,
          $sensorDataJSDirection12,
          $sensorDataJSGust12,
          $scale12
      ),
      'wind_data_period_24' => array(
          $sensorDataJSSpeed24,
          $sensorDataJSDirection24,
          $sensorDataJSGust24,
          $scale24
      ),
      'wind_data_forecast_24' => array(
          $forecastJSSpeed24,
          $forecastJSDirection24,
          // $forecastJSGust24,
          $scaleForecast24
      ),
      'markers_station' => $resultMarkers,
      'update_markers_station' => $updateMarkersStation,
      'status' => 1
    );
    $result = json_encode($result);
    echo $result;
    exit();
  }
  public static function check_url($url) {
    $headers = @get_headers($url);
    $headers = (is_array($headers)) ? implode( "\n ", $headers) : $headers;
    return (bool)preg_match('#^HTTP/.*\s+[(200|301|302)]+\s#i', $headers);
  }
  public static function prepareGraphDataJs($arr = array(), $resultArr = array(), $windUnit = ''){
    //prepare data for JS (tranform key time to key int (egal to i in JS loop) and convert str to float)
    if(is_array($arr) && count($arr) > 0){
      $i = 0;
      foreach($arr as $time => $val){
        if($val != null){
          //number value
          if(!empty($windUnit)){
            //use this only for wind data
            $val = self::convertWindUnit($windUnit, $val);
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
  public static function convertWindUnit($windUnit = 'kts', $data = 0){
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
    switch ($windUnit) {
      case 'kmh':
        $data = round( ($data * 3.6), 0);
        break;
      case 'ms':
        $data = round( ($data + 0), 2);
        break;
      case 'bft':
        $data = round( ( ( ( ($data * 3.6) / 1.852) / 5) + 1), 0);
        break;
      case 'kts':
      default:
        $data = round( ( ($data * 3.6) / 1.852), 0);
        break;
    }
    return $data;
  }
  public static function getDirectionLetter($Direction = 0){
    $direction = 'N';
    $Direction = (float)$Direction;
    //each parts 22.5 deg
    //north => 360 - 11.25 && 0 + 11.25
    if($Direction <= 11.25){
      $direction = 'N';
    }elseif($Direction > 11.25 && $Direction <= 33.75){
      $direction = 'NNE';
    }elseif($Direction > 33.75 && $Direction <= 56.25){
      $direction = 'NE';
    }elseif($Direction > 56.25 && $Direction <= 78.75){
      $direction = 'ENE';
    }elseif($Direction > 78.75 && $Direction <= 101.25){
      $direction = 'E';
    }elseif($Direction > 101.25 && $Direction <= 123.75){
      $direction = 'ESE';
    }elseif($Direction > 123.75 && $Direction <= 146.25){
      $direction = 'SE';
    }elseif($Direction > 146.25 && $Direction <= 168.75){
      $direction = 'SSE';
    }elseif($Direction > 168.75 && $Direction <= 191.25){
      $direction = 'S';
    }elseif($Direction > 191.25 && $Direction <= 213.75){
      $direction = 'SSO';
    }elseif($Direction > 213.75 && $Direction <= 236.25){
      $direction = 'SO';
    }elseif($Direction > 236.25 && $Direction <= 258.75){
      $direction = 'OSO';
    }elseif($Direction > 258.75 && $Direction <= 281.25){
      $direction = 'O';
    }elseif($Direction > 281.25 && $Direction <= 303.75){
      $direction = 'ONO';
    }elseif($Direction > 303.75 && $Direction <= 326.25){
      $direction = 'NO';
    }elseif($Direction > 326.25 && $Direction <= 348.75){
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
    if(strtotime($hour) < strtotime($result) && (strtotime($result) - strtotime($hour) < 600)){
      //use previous 10minute period
      $result = date($format, (strtotime($result) - 600));
    }
    return $result;
  }
  public static function generateModalContent($type = ''){
    $result = '';
    switch($type){
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
    $result .= self::generateConfigGraphForecastDirectionList();
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
  public static function getBrowserLanguage($short = false){
    $lang = 'fr_FR';
    if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
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
    if($short){
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
    switch($userPrefWindUnit){
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
    $stations = "";
    $userPrefFavStation = "";
    if(isset($_SESSION['WSTATIONS']))
    	$stations = $_SESSION['WSTATIONS'];
    if(isset($_SESSION['W_STATION']))
    	$userPrefFavStation = $_SESSION['W_STATION'];
    //user config - station list (preference - favorite stations) -> auto load station after user login
    $result =  '<select id="pref_fav_station">';
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
  	$userPrefGraphDirection = "";
  	if(isset($_SESSION['W_GRAPH_LTR']))
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
  private static function generateConfigGraphForecastDirectionList(){
  	$userPrefGraphDirection = "";
  	if(isset($_SESSION['W_GRAPH_PREV_LTR']))
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
  public static function saveConfigPreferences($prefWindUnit = '', $prefFavoriteStation = '', $prefGraph = '', $prefGraphForecast = '', $prefLanguage = ''){
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
   private function log($logPath, $msg, $level = _WINDSPOTS_LOG_LVL_INFO, $cleanLogFile = false) {
    //use for log (debug)
    if ( empty($logPath) || empty ($msg)) return null;
    if ($cleanLogFile) {
      if ( !is_dir($logPath) && file_exists($logPath)) unlink($logPath);
    }
    $logFile = fopen($logPath, 'a+');
    //server GMT 0 -> set Geneva time
    date_default_timezone_set('Europe/Paris');
    $line = '';
    if ($level === _WLOG_LVL_START) $line = '[' . date( 'd-m-Y H:i:s') . '] ' . ' -**************************************************- ' . "\r\n";
    $line .= '[' . date( 'd-m-Y H:i:s') . '] ' . $level . ' - ' . $msg . "\r\n";
    if ($level === _WLOG_LVL_END) $line .= '[' . date( 'd-m-Y H:i:s') . '] ' . ' -**************************************************- ' . "\r\n";
    fwrite($logFile, $line);
    fclose($logFile);
    return $line;
  }
  public static function redirect($url, $statusCode = 303)  {
    header('Location: ' . $url, true, $statusCode);
    exit();
  }
  public static function getCurrentStation(){
    if(isset($_SESSION['WCURRENT_STATION']) && !empty($_SESSION['WCURRENT_STATION'])){
      return $_SESSION['WCURRENT_STATION'];
    }else{
      return false;
    }
  }
  public static function setCurrentStation($stationName = '', $reset = false){
    if($reset){
      $_SESSION['WCURRENT_STATION'] = '';
      return true;
    }
    if(!empty($stationName)){
      $_SESSION['WCURRENT_STATION'] = $stationName;
      return true;
    }
    return false;
  }
  private static function getPreferences($idAsKey = true){
    $preferences = array(
      '1' => 'pref_wind_unit',
      '2' => 'pref_fav_station',
      '3' => 'pref_graph_direction',
      '4' => 'pref_graph_previ_direction'
   );
    if($idAsKey){ return $preferences; }
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
}