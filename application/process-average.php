<?php
$rootPath=__FILE__;
$scriptPath=baseName($rootPath);
$rootPath=str_replace($scriptPath,'',$rootPath);
$rootPath=realPath($rootPath.'../');
$rootPath=str_replace('\\','/',$rootPath);
date_default_timezone_set('Europe/Zurich');
$windspotsLog  =  $rootPath."/log";
$windspotsData =  $rootPath."/data";
// API
$windspotsAPI = $rootPath."/../api/library/windspots";
require_once $windspotsAPI.'/db.php';
function logIt($message) {
  global $windspotsLog;
  $logfile = "/process-average.log";
  $wlHandle = fopen($windspotsLog.$logfile, "a");
  $t = microtime(true);
  $micro = sprintf("%06d",($t - floor($t)) * 1000000);
  $micro = substr($micro,0,3);
  fwrite($wlHandle, Date("H:i:s").".$micro".": ".$message."\n");
  fclose($wlHandle);
}
// 
function processAverage10min($sensorId) {
  $nbComputed     = 0;
  $nbPerPeriod    = array();
  $computed       = array();
  $timeFrom       = 0;
  $diffMinutes    = 0;
  $battery_sum    = 0;
  $temperature_sum= 0;
  $humidity_sum   = 0;
  $barometer_sum  = 0;
  $wind_dir_Ux    = 0;
  $wind_dir_Uy    = 0;
  $speed_sum      = 0.0;
  $average_sum    = 0.0;
  $gust           = 0.0;  // gust sum is not a computed value
  $uv_sum         = 0;
  $rain_rate_sum  = 0;
  $rain_total_sum = 0;
  $nbPerPeriod['battery']     = 0;
  $nbPerPeriod['temperature'] = 0;
  $nbPerPeriod['humidity']    = 0;
  $nbPerPeriod['barometer']   = 0;
  $nbPerPeriod['direction']   = 0;
  $nbPerPeriod['speed']       = 0;
  $nbPerPeriod['average']     = 0;
  $nbPerPeriod['uv']          = 0;
  $nbPerPeriod['rain_rate']   = 0;
  $nbPerPeriod['rain_total']  = 0;
  //
  if (empty($sensorId)){
    logIt("END - No SensorId : ".$sensorId);
    return;
  }
  // Retrieve sensor's data last 10 minutes
  $sensorData=WindspotsDB::getSensorData($sensorId, 10, false, true);
  $nbResult = count($sensorData);
  if(empty($nbResult)) {
    logIt('END - There is no data for this sensor '.$sensorId);
    return false;
  }
  logIt("Sensor: ".$sensorId." nb : ".$nbResult);
  for ($i = 0; $i < $nbResult; $i++) {
    // check if sensor Time in last ten minutes
    $sensorTime = strtotime($sensorData[$i]['sensor_time']);
    if($sensorTime < (time() - 630)) { // not 600 due to previous
      logIt("        time more than 10 minutes: ".date('Y-m-d H:i:s', $sensorTime));
      continue;
    }
    $nbComputed++;
    if (!empty($sensorData[$i]['battery'])) {
      $battery_sum += (float)(min(100.0, $sensorData[$i]['battery']));
      $nbPerPeriod['battery']++;
    }
    if (!empty($sensorData[$i]['temperature'])) {
      $temperature_sum += (float)$sensorData[$i]['temperature'];
      $nbPerPeriod['temperature']++;
    }
    if (!empty($sensorData[$i]['humidity'])) {
      $humidity_sum += (float)$sensorData[$i]['humidity'];
      $nbPerPeriod['humidity']++;
    }
    if (!empty($sensorData[$i]['barometer'])) {
      $barometer_sum += (float)$sensorData[$i]['barometer'];
      $nbPerPeriod['barometer']++;
    }
    /**
     * The wind direction is a measure representing an angle in degrees (°).
     * We can't do a simple average as the winds' speed.
     * To compute this average, we use a more complex mathematical formula based on the sine and cosine of each direction.
     *
     * T = arctan(Ux / Uy) + K            =>  T average wind direction
     * OA   Ux = (Sum of sin(T[i])) / N   =>  T[i] = a sample direction, N = number of samples
     *      Uy = (Sum of cos(T[i])) / N   =>  T[i] = a sample direction, N = number of samples
     *
     * K value according to Ux and Uy :
     *
     *        | Ux = 0  | Ux > 0  | Ux < 0  |
     * Uy = 0 |    -    |  note1  |  note2  |
     * Uy > 0 |   360   |    0    |   360   |
     * Uy < 0 |   180   |   180   |   180   |
     *
     * note1: T will always return 90°
     * note2: T will always return 270°
     */
    if (!empty($sensorData[$i]['direction'])) {
      $wind_direction = (float)$sensorData[$i]['direction'];
      $wind_dir_Ux += sin(deg2rad($wind_direction));
      $wind_dir_Uy += cos(deg2rad($wind_direction));
      $nbPerPeriod['direction']++;
    }
    if (!empty($sensorData[$i]['speed'])) {
      $speed_sum += (float)$sensorData[$i]['speed'];
      $nbPerPeriod['speed']++;
    }
    if (!empty($sensorData[$i]['average'])) {
      $average_sum += (float)$sensorData[$i]['average'];
      $nbPerPeriod['average']++;
    }
    // gust is not computed
    if (!empty($sensorData[$i]['gust'])) {  
      if($sensorData[$i]['gust'] > $gust)
        $gust = (float)$sensorData[$i]['gust'];
    }
    if (!empty($sensorData[$i]['uv'])) {
      $uv_sum += (float)$sensorData[$i]['uv'];
      $nbPerPeriod['uv']++;
    }
    if (!empty($sensorData[$i]['rain_rate'])) {
      $rain_rate_sum += (float)$sensorData[$i]['rain_rate'];
      $nbPerPeriod['rain_rate']++;
    }
    if (!empty($sensorData[$i]['rain_total'])) {
      $rain_total_sum += (float)$sensorData[$i]['rain_total'];
      $nbPerPeriod['rain_total']++;
    }
  } // for ($i = 0; $i < $nbResult; $i++) 
  logIt("        Computing... ".$nbComputed);
  if($nbComputed < 1) {
    logIt('        0 record ');
    return;
  }
  $timeData = round(time() / 600) * 600;
  $computed['date']        = date('Y-m-d H:i:00',  $timeData);
  $computed['nbdata']      = $nbPerPeriod;
  $computed['battery']     = 0;
  $computed['temperature'] = 0;
  $computed['humidity']    = 0;
  $computed['barometer']   = 0;
  $computed['direction']   = 0;
  $computed['speed']       = 0.0;
  $computed['average']     = 0.0;
  $computed['gust']        = 0.0;
  $computed['uv']          = 0;
  $computed['rain_rate']   = 0;
  $computed['rain_total']  = 0;
  //    
  if (!empty($battery_sum))     $computed['battery'] = round(($battery_sum / $nbPerPeriod['battery']), 2);
  if (!empty($temperature_sum)) $computed['temperature'] = round(($temperature_sum / $nbPerPeriod['temperature']), 1);
  if (!empty($humidity_sum))    $computed['humidity'] = round(($humidity_sum / $nbPerPeriod['humidity']), 2);
  if (!empty($barometer_sum))   $computed['barometer'] = round(($barometer_sum / $nbPerPeriod['barometer']), 2);
  if (!empty($nbPerPeriod['direction'])) {
    $wind_dir_Ux = $wind_dir_Ux / $nbPerPeriod['direction'];
    $wind_dir_Uy = $wind_dir_Uy / $nbPerPeriod['direction'];
    $K = 0;
    $wind_direction_average = 0;
    if (($wind_dir_Ux == 0) && ($wind_dir_Uy == 0)) {
      $K = 0;
    } elseif (($wind_dir_Ux == 0) && ($wind_dir_Uy > 0)) {
      $K = 360;
    } elseif (($wind_dir_Ux == 0) && ($wind_dir_Uy < 0)) {
      $K = 180;
    } elseif (($wind_dir_Ux > 0) && ($wind_dir_Uy == 0)) {
      $wind_direction_average = 90;
    } elseif (($wind_dir_Ux > 0) && ($wind_dir_Uy > 0)) {
      $K = 0;
    } elseif (($wind_dir_Ux > 0) && ($wind_dir_Uy < 0)) {
      $K = 180;
    } elseif (($wind_dir_Ux < 0) && ($wind_dir_Uy == 0)) {
      $wind_direction_average = 270;
    } elseif (($wind_dir_Ux < 0) && ($wind_dir_Uy > 0)) {
      $K = 360;
    } elseif (($wind_dir_Ux < 0) && ($wind_dir_Uy < 0)) {
      $K = 180;
    }
    if (empty ($wind_direction_average)) $wind_direction_average = rad2deg (atan (($wind_dir_Ux / $wind_dir_Uy))) + $K;
    $computed['direction'] = $wind_direction_average;
  }
  if(!empty($speed_sum))      $computed['speed'] = round(($speed_sum / $nbPerPeriod['speed']), 2);
  if(!empty($average_sum))    $computed['average'] = round(($average_sum / $nbPerPeriod['average']), 2);
  $computed['gust'] = $gust;
  if(!empty($uv_sum))         $computed['uv'] = round(($uv_sum / $nbPerPeriod['uv']), 2);
  if(!empty($rain_rate_sum))  $computed['rain_rate'] = round(($rain_rate_sum / $nbPerPeriod['rain_rate']), 2);
  if(!empty($rain_total_sum)) $computed['rain_total'] = round(($rain_total_sum / $nbPerPeriod['rain_total']), 2);
  //
  if(!WindspotsDB::setSensorData($sensorId, $computed['date'],      $computed['battery'],
        $computed['temperature'],   $computed['humidity'],  $computed['barometer'],
        $computed['direction'],     $computed['speed'],     $computed['average'],
        $computed['gust'],          $computed['uv'],        $computed['rain_rate'],
        $computed['rain_total'],    true)) {
    logIt('       END - Insert error.');
    return false; 
  }
  logIt('        '.$nbComputed.' record(s) - '.$computed['date'] );
  return;
}
/**
 * Process Average 
 **/
// multiple of 10
$minute = date('i', time());
if($minute % 10 != 0){
  logIt($minute." is not a multiple of 10");
  return;
}
$mt = microtime(true);
$stations = WindspotsDB::getStations();
if(is_array($stations) && count($stations) > 0){
	logIt('------------------------------------------------------ ');
  foreach($stations as $key => $station){
    logIt('Avearge for ' . $station['station_name']);
    // check id
    $wind_id =  $station['wind_id'];
    $barometer_id = $station['barometer_id'];
    $temperature_id = $station['temperature_id'];
    $water_id = $station['water_id'];
    if($barometer_id == $wind_id)
      $barometer_id =0;
    if($temperature_id == $wind_id)
      $temperature_id =0;
    if($water_id == $wind_id)
      $water_id =0;
    if($temperature_id == $barometer_id)
      $temperature_id =0;
    if($water_id == $barometer_id)
      $water_id =0;
    if($water_id == $temperature_id)
      $water_id =0;
    //  processAverage10min
    if ($wind_id != 0)
        processAverage10min($station['wind_id']);
    if ($barometer_id != 0)
        processAverage10min($station['barometer_id']);
    if ($temperature_id != 0)
      processAverage10min($station['temperature_id']);
    if ($water_id != 0)
      processAverage10min($station['water_id']);
    logIt('------------------------------------------------------ ');
  } // foreach($stations as $key => $station){
}
$et = microtime(true) - $mt;
logIt("Elapsed time: " . number_format($et,5));