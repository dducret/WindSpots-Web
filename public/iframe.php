<?php
// To display the iframe of a station
// https://windspots.org/iframe.php?station=CHGE04&unit=kmh&language=en_GB&graph=ltr&forecast=ltr&maps=true
//Constant is used in included files to prevent direct access.
const _WEXEC = 1;
//add files
require_once './includes/config.php';
require_once './includes/helper.php';
require_once './includes/controller.php';
require_once "./includes/content.php";
// default
$currentStationName = 'CHGE04';
$lang  = WindspotsHelper::getBrowserLanguage(); // en_GB fr_FR de_DE
$unit  = 'kts';   // kts kmh bft
$graph = 'ltr';   //  ltr rtl
$forecast = 'ltr';//  ltr rtl
$map = 'false'; // true false
// station
if (isset($_REQUEST['station'])) {
  $currentStationName = $_REQUEST['station'];
}
// unit
if (isset($_REQUEST['unit'])) {
  $unit = "_".$_REQUEST['unit'];
}
// language
if (isset($_REQUEST['language'])) {
  $lang = $_REQUEST['language'];
}
WindspotsHelper::loadTranslationFile( $lang );
// graph
if (isset($_REQUEST['graph'])) {
  $graph = $_REQUEST['graph'];
}
// forecast
if (isset($_REQUEST['forecast'])) {
  $forecast = $_REQUEST['forecast'];
}
// forecast
if (isset($_REQUEST['map'])) {
  $map = $_REQUEST['map'];
}
// for helper
@session_start();
$_SESSION['W_GRAPH_LTR'] = $graph;
$_SESSION['W_GRAPH_PREV_LTR'] = $forecast;
$_SESSION['W_UNIT'] = $unit;
$_SESSION['W_LANG'] = $lang;
//$r = time();
$r = 27072018;
//language
$langShort = '';
$clsMenuLanguageFR = '';
$clsMenuLanguageEN = '';
$clsMenuLanguageDE = '';
switch( $lang ){
    case 'fr_FR':
        $langShort = 'fr';
        $clsMenuLanguageFR = 'active';
        break;
    default:
    case 'en_GB':
        $langShort = 'en';
        $clsMenuLanguageEN = 'active';
        break;
    case 'de_DE':
        $langShort = 'de';
        $clsMenuLanguageDE = 'active';
        break;
}
//server GMT 0 -> set Geneva time
date_default_timezone_set('Europe/Zurich');
$now = strtotime( date("Y-m-d H:i:s") );
?>
<!DOCTYPE html>
<html lang="<?php echo $langShort; ?>">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!--  --> <meta http-equiv="X-UA-Compatible" content="IE=9">
  <!-- <meta http-equiv="x-ua-compatible" content="ie=edge"> -->  
  <title><?php WindspotsHelper::lang('WINDSPOTS'); ?></title>
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/windspots.css<?php echo '?r='.$r; ?>">
  <link rel="stylesheet" href="css/leaflet.css<?php echo '?r='.$r; ?>">
  <script src="js/jquery.min.js<?php echo '?r='.$r; ?>"></script>
  <script src="js/jquery-ui.min.js<?php echo '?r='.$r; ?>"></script>
  <script src="js/graph.js<?php echo '?r='.$r; ?>"></script>
  <script src="js/windspots.js<?php echo '?r='.$r; ?>"></script>
  <script src="js/leaflet.js<?php echo '?r='.$r; ?>"></script>
</head>
<body>
  <?php //-- modal graph strucure -- ?>
  <?php //grap box info ?>
  <div id="graph_box_info">
    <div class="gbi_hour_wrapper"><span id="gbi_hour"></span></div>
    <div class="gbi_data_wrapper">
      <div class="gbi_label gbi_label_<?php echo $langShort; ?>"><?php echo WindspotsHelper::lang('DIRECTION').' : '; ?></div><span id="gbi_dir_name" class="gbi_data"></span> <span id="gbi_dir_deg" class="gbi_data"></span><span class="gbi_data">&deg;</span><br/>
      <div class="gbi_label gbi_label_<?php echo $langShort; ?>"><?php echo WindspotsHelper::lang('SPEED').' : '; ?></div><span id="gbi_strenght" class="gbi_data"></span> <span class="gbi_data st_global_wind_unit"></span><br />
      <div class="gbi_label gbi_label_<?php echo $langShort; ?>"><?php echo WindspotsHelper::lang('GUST').' : '; ?></div><span id="gbi_raf" class="gbi_data"></span> <span class="gbi_data st_global_wind_unit"></span>
    </div>
  </div>
  <?php //grap Forecast box info ?>
  <div id="graph_previ_box_info">
    <div class="gbi_Forecast_hour_wrapper"><span id="gbi_Forecast_hour"></span></div>
    <div class="gbi_Forecast_data_wrapper gbi_Forecast_data_overlay_wrapper" style="border-bottom: white 1px solid;">
      <div class="gbi_Forecast_label gbi_Forecast_label_<?php echo $langShort; ?>"><?php echo WindspotsHelper::lang('DIRECTION').' : '; ?></div><span id="gbi_Forecast_overlay_dir_name" class="gbi_data"></span> <span id="gbi_Forecast_overlay_dir_deg" class="gbi_data"></span><span class="gbi_data">&deg;</span><br/>
      <div class="gbi_Forecast_label gbi_Forecast_label_<?php echo $langShort; ?>"><?php echo WindspotsHelper::lang('SPEED').' : '; ?></div><span id="gbi_Forecast_overlay_strenght" class="gbi_data"></span> <span class="gbi_data st_global_wind_unit"></span><br />
    </div>
    <div class="gbi_Forecast_data_wrapper">
      <p class="gbi_Forecast_data_sub_title"></p>
      <div class="gbi_Forecast_label gbi_Forecast_label_<?php echo $langShort; ?>"><?php echo WindspotsHelper::lang('DIRECTION').' : '; ?></div><span id="gbi_Forecast_dir_name" class="gbi_data"></span> <span id="gbi_Forecast_dir_deg" class="gbi_data"></span><span class="gbi_data">&deg;</span><br/>
      <div class="gbi_Forecast_label gbi_Forecast_label_<?php echo $langShort; ?>"><?php echo WindspotsHelper::lang('SPEED').' : '; ?></div><span id="gbi_Forecast_strenght" class="gbi_data"></span> <span class="gbi_data st_global_wind_unit"></span><br />
    </div>
  </div>
  <?php //Quick Nav structure (stations list) ?>
  <?php //-- website structure --?>
  <!-- <div id="error-msg"></div> -->
  <div id="myBody" class="body-scrollable">

    <div class="main-content main-content-article-container"></div>
    <div class="main-content main-content-spot-container">
      <div class="spot-image">
        <img id="st_image" src="images/no-station-image.jpg" alt="Vue du spot"/>
      </div>
      <div class="spot-weather">
        <div class="table">
          <div class="spot-value patate tooltip">
            <div class="tooltiptext" style="width:100%; height:100%; position:absolute; top:0; left:0;"> <?php //style="width:100%; height:100%; position:absolute; top:0; left:0;" ?>
              <h2><span class="st_last_update"></span></h2>
              <br />
              <h2><?php echo WindspotsHelper::lang('DIRECTION'); ?>: <span id="st_wind_direction_letter"></span>&nbsp;<span id="st_wind_direction_deg"></span>&deg;</h2>
              <h2><?php echo WindspotsHelper::lang('SPEED'); ?>: <span id="st_wind_speed"></span>&nbsp;<span class="st_global_wind_unit">kts</span></h2>
              <hr />
              <h2><?php echo WindspotsHelper::lang('GUST'); ?>: <span id="st_wind_gust"></span>&nbsp;<span class="st_global_wind_unit">kts</span></h2>
              <h2><?php echo WindspotsHelper::lang('WIND_SPEED_MIN'); ?> 30': <span id="st_wind_min"></span>&nbsp;<span class="st_global_wind_unit">kts</span></h2>
              <h2><?php echo WindspotsHelper::lang('WIND_SPEED_MED'); ?> 30': <span id="st_wind_med"></span>&nbsp;<span class="st_global_wind_unit">kts</span></h2>
              <h2><?php echo WindspotsHelper::lang('WIND_SPEED_MAX'); ?> 30': <span id="st_wind_gust_max"></span>&nbsp;<span class="st_global_wind_unit">kts</span></h2>
              <hr />
              <h2><?php echo WindspotsHelper::lang('TEMPERATURE_WIND'); ?>: <span id="st_temperature"></span>&deg;</h2>
              <h2><?php echo WindspotsHelper::lang('TEMPERATURE_WATER'); ?>: <span id="st_temperature_water"></span>&deg;</h2>
              <hr />
              <h2><?php echo WindspotsHelper::lang('PRESSURE'); ?>: <span id="st_barometer"></span>&nbsp;hPa</h2>
              <h2><?php echo WindspotsHelper::lang('HUMIDITY'); ?>: <span id="st_humidity"></span>%</h2>
              <br />
            </div>
            <?php //Patate ?>
            <svg id="patate_svg_file" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
               viewBox="0 0 250 250" style="enable-background:new 0 0 615.1 280.6; height: 17.8vw; width: 17.8vw;" xml:space="preserve">
              <g>
                <path id="fleche" fill="#00DBB1" opacity="0.9" style="transform-origin: 125px 125px 0; display: block; transform: rotate(160deg);" d="M125,47.744L70.373,179.631 L125,154.566l54.628,25.064L125,47.744z"></path>
              </g> 
              <g> 
                <path fill="#0034D2" opacity="0.6" d="M149.387,2.402 c-16.1-3.203-32.673-3.203-48.772,0l9.314,46.827c9.95-1.979,20.193-1.979,30.144,0L149.387,2.402z" style="opacity: 0.6;"></path> 
                <path fill="#0034D2" opacity="0.2" d="M146.373,17.557l-6.301,31.673 c9.95,1.98,19.414,5.899,27.849,11.536l17.941-26.85C173.901,25.922,160.482,20.364,146.373,17.557L146.373,17.557z" style="opacity: 0.2;"></path> 
                <path fill="#0034D2" opacity="0.4" d="M190.155,27.49l-22.234,33.275 c8.438,5.636,15.679,12.879,21.315,21.314l33.274-22.232C213.956,47.042,202.96,36.047,190.155,27.49z" style="opacity: 0.4;"></path> 
                <path fill="#0034D2" opacity="0.2" d="M216.087,64.139L189.236,82.08 c5.635,8.437,9.557,17.9,11.537,27.85l31.671-6.3C229.637,89.52,224.081,76.101,216.087,64.139z" style="opacity: 0.2;"></path> 
                <path fill="#0034D2" opacity="0.6" d="M247.599,100.614 l-46.825,9.315c0.985,4.963,1.483,10.01,1.483,15.072s-0.498,10.109-1.483,15.072l46.825,9.314 C250.801,133.288,250.801,116.715,247.599,100.614L247.599,100.614z" style="opacity: 0.6;"></path> 
                <path fill="#0034D2" opacity="0.2" d="M232.444,146.374 l-31.671-6.301c-1.98,9.95-5.902,19.414-11.537,27.85l26.851,17.941C224.081,173.902,229.637,160.482,232.444,146.374 L232.444,146.374z" style="opacity: 0.2;"></path> 
                <path fill="#0034D2" opacity="0.4" d="M222.511,190.156 l-33.274-22.233c-5.637,8.435-12.878,15.678-21.315,21.314l22.234,33.275C202.96,213.955,213.956,202.961,222.511,190.156z" style="opacity: 0.4;"></path> 
                <path fill="#0034D2" id="path4537" opacity="0.2" d="M167.921,189.237 c-8.435,5.637-17.898,9.557-27.849,11.536l6.301,31.671c14.109-2.806,27.528-8.365,39.489-16.355L167.921,189.237L167.921,189.237z" style="opacity: 0.2;"></path> 
                <path fill="#0034D2" opacity="0.6" d="M140.072,200.773 c-9.95,1.979-20.193,1.979-30.144,0l-9.314,46.826c16.1,3.202,32.673,3.202,48.772,0L140.072,200.773z" style="opacity: 0.6;"></path> 
                <path fill="#0034D2" id="path4541" opacity="0.2" d="M109.929,200.773 c-9.95-1.979-19.412-5.899-27.849-11.536l-17.941,26.852c11.961,7.99,25.38,13.55,39.488,16.355L109.929,200.773L109.929,200.773z" style="opacity: 0.2;"></path> 
                <path fill="#0034D2" opacity="0.4" d="M82.08,189.237 c-8.437-5.637-15.679-12.88-21.315-21.314l-33.273,22.233c8.556,12.805,19.548,23.799,32.355,32.356L82.08,189.237L82.08,189.237z" style="opacity: 0.4;"></path> 
                <path fill="#0034D2" id="path4545" opacity="0.2" d="M49.229,140.073l-31.675,6.301 c2.808,14.108,8.365,27.528,16.359,39.49l26.851-17.941C55.127,159.487,51.208,150.023,49.229,140.073z" style="opacity: 0.2;"></path> 
                <path fill="#0034D2" opacity="0.6" d="M47.744,125.001 c0-5.062,0.498-10.109,1.485-15.072l-46.826-9.315c-3.203,16.101-3.203,32.674,0,48.773l46.826-9.314 C48.242,135.11,47.744,130.063,47.744,125.001L47.744,125.001z" style="opacity: 0.6;"></path> 
                <path fill="#0034D2" opacity="0.2" d="M60.765,82.08L33.914,64.139 C25.92,76.101,20.362,89.52,17.555,103.629l31.675,6.3C51.208,99.979,55.127,90.516,60.765,82.08L60.765,82.08z" style="opacity: 0.2;"></path> 
                <path fill="#0034D2" id="path4551" opacity="0.4" d="M59.847,27.49 c-12.808,8.557-23.8,19.551-32.355,32.357L60.765,82.08c5.637-8.435,12.879-15.679,21.315-21.314L59.847,27.49z" style="opacity: 0.4;"></path> 
                <path fill="#0034D2" opacity="0.2" d="M103.627,17.557 C89.519,20.364,76.1,25.922,64.139,33.915l17.941,26.85c8.437-5.637,17.898-9.556,27.849-11.536L103.627,17.557L103.627,17.557z" style="opacity: 0.2;"></path> 
              </g> 
              <g><text transform="matrix(1 0 0 1 117.665 32.8179)" fill="#0034D2" font-family="'Arial'" font-size="20.3125">N</text></g> 
              <g><text transform="matrix(1 0 0 1 43.4873 62.3706)" fill="#0034D2" font-family="'Arial'" font-size="16.4566">NO</text></g> 
              <g><text transform="matrix(1 0 0 1 184.2627 62.3706)" fill="#0034D2" font-family="'Arial'" font-size="16.4566">NE</text></g> 
              <g><text transform="matrix(1 0 0 1 184.2627 199.5898)" fill="#0034D2" font-family="'Arial'" font-size="16.4566">SE</text></g> 
              <g><text transform="matrix(1 0 0 1 118.2256 232.4443)" fill="#0034D2" font-family="'Arial'" font-size="20.3125">S</text></g> 
              <g><text transform="matrix(1 0 0 1 16.6084 132.6563)" fill="#0034D2" font-family="'Arial'" font-size="20.3125">O</text></g> 
              <g><text transform="matrix(1 0 0 1 42.8125 199.5908)" fill="#0034D2" font-family="'Arial'" font-size="16.4566">SO</text></g> 
              <g><text transform="matrix(1 0 0 1 218.5107 132.6563)" fill="#0034D2" font-family="'Arial'" font-size="20.3125">E</text></g>
              <g><text transform="matrix(1 0 0 1 117.665 32.8179)" fill="#2a4999" font-family="'Arial'" font-size="20.3125">N</text></g> 
              <g><text transform="matrix(1 0 0 1 43.4873 62.3706)" fill="#2a4999" font-family="'Arial'" font-size="16.4566">NO</text></g> 
              <g><text transform="matrix(1 0 0 1 184.2627 62.3706)" fill="#2a4999" font-family="'Arial'" font-size="16.4566">NE</text></g> 
              <g><text transform="matrix(1 0 0 1 184.2627 199.5898)" fill="#2a4999" font-family="'Arial'" font-size="16.4566">SE</text></g>
            </svg>
            <div class="wind-direction-patate" style="width: 100%; z-index: 9;position:absolute; top: 7vw; left:0; text-align:center;">
              <h2><span id="st_wind_direction_letter_patate"></span>&nbsp;<span id="st_wind_direction_deg_patate"></span>&deg;</h2>
            </div>
            <div class="wind-speed-patate" style="width: 100%; z-index: 9; position:absolute; top: 9vw; left:0; text-align:center;">
              <h2><span id="st_wind_speed_patate"></span>&nbsp;<span class="st_global_wind_unit">kts</span></h2>
            </div>
            <div class="wind-update-patate" style="width: 100%; position:absolute; top: 18.5vw; left:0;  text-align:center;">
              <h4><span class="st_last_update"></span></h4>
            </div>
            <div class="wind-gust-patate" style="display: inline-block; position:absolute; top: 20vw; left: 6.5vw; text-align:center;">
              <div class="table" style="width: 5.5vw">
                <div class="cellIco" style="">
                  <img style="opacity:0.6; width: 2.5vw;" src="svg/rafale.svg" alt="rafale"/>
                </div>
                <div class="cellText" style="">
                  <div class="table">
                    <div class="cell" style="text-align: left;">
                      <h4><span id="st_wind_gust_summary"></span>&nbsp;<span class="st_global_wind_unit">kts</span></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="wind-temp-patate" style="position:absolute; top: 21vw; left: 1vw; height:100%;">
              <div class="table" style="width: 6vw">
                <div class="cellIco" style="">
                    <img style="opacity:0.6; width: 4vw;" src="svg/temp_air.svg" alt="temp" />
                </div>
                <div class="cellText" style="">
                  <div class="table">
                    <div class="cell" style="text-align:left;">
                      <h4><span id="st_temperature_summary"></span>&deg;</h4>
                    </div>
                  </div>
                </div>
              </div>  
            </div>
            <div class="wind-temp-water-patate" style="position:absolute; top: 21vw; display: inline-block; height:100%; right: 1vw;">
              <div class="table" style="width: 6vw">
                <div class="cellText" style="">
                  <div class="table">
                    <div class="cell" style="text-align:right;">
                      <h4><span id="st_temperature_water_summary"></span>&deg;</h4>
                    </div>
                  </div>
                </div>
                <div class="cellIco" style="">
                    <img style="opacity:0.6; width: 4vw;" src="svg/temp_eau.svg" alt="eau" />
                </div>
              </div>  
            </div>
          </div>
          <div class="spot-graph">
            <div class="table">
              <div class="graph-actual">
                <div class="table">
                  <div class="row">
                    <div style="display: table-cell; text-align: left;" class="text-transparent"><?php echo WindspotsHelper::lang('REAL_TIME_WIND_GRAPH'); ?></div>
                    <div style="display: table-cell" >
                      <div class="cssmenu" style="float: right; padding-top:1%;" >
                        <ul>
                          <li class="cssmenu-graph cssmenu-graph-1 active" onclick="loadGraph( 1 );"><span class="cssmenu-label">1 H </span></li>
                          <li class="cssmenu-graph cssmenu-graph-12" onclick="loadGraph( 12 );"><span class="cssmenu-label">12 H</span></li>
                          <li class="cssmenu-graph cssmenu-graph-24" onclick="loadGraph( 24 );"><span class="cssmenu-label">24 H</span></li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
                <svg id="context" class="context-graph">
                  <g>
                    <defs>
                      <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="0%"></linearGradient>
                    </defs>
                    <rect id="scaleColor" x="0" y="85%" height="10%" width="100%" style="fill:url(#grad); transform:translate(0px,-20px)" />
                  </g>
                  <g id="grid"></g>
                  <g id="layer2">
                    <g id="rafMax"></g>
                    <g><polyline id="graph" points="" style="fill:rgba(42,73,153,0.5);" /></g>
                    <g id="error"></g>
                    <g id="arrow"></g>
                  </g>
                  <g id="text"></g>
                </svg>
              </div>
              <div class="graph-forecast">
                <div class="table">
                  <div class="row">
                    <div style="display: table-cell; text-align: left;" class="text-transparent"><?php echo WindspotsHelper::lang('FORECAST_CHART'); ?></div>
                    <div style="float: right;" >
                      <ul class="legend legend_kts">
                        <li><span class="infoColor infoColor1"></span> 0 <span>Kts</span></li>
                        <li><span class="infoColor infoColor2"></span> 1-4 <span>Kts</span></li>
                        <li><span class="infoColor infoColor3"></span> 5-9 <span>Kts</span></li>
                        <li><span class="infoColor infoColor4"></span> 10-14 <span>Kts</span></li>
                        <li><span class="infoColor infoColor5"></span> 15-19 <span>Kts</span></li>
                        <li><span class="infoColor infoColor6"></span> 20-34 <span>Kts</span></li>
                        <li><span class="infoColor infoColor7"></span> 35+ <span>Kts</span></li>
                      </ul>
                      <ul class="legend legend_kmh">
                        <li><span class="infoColor infoColor1"></span> 0 <span>Kmh</span></li>
                        <li><span class="infoColor infoColor2"></span> 1-8 <span>Kmh</span></li>
                        <li><span class="infoColor infoColor3"></span> 9-17 <span>Kmh</span></li>
                        <li><span class="infoColor infoColor4"></span> 18-26 <span>Kmh</span></li>
                        <li><span class="infoColor infoColor5"></span> 27-36 <span>Kmh</span></li>
                        <li><span class="infoColor infoColor6"></span> 37-63 <span>Kmh</span></li>
                        <li><span class="infoColor infoColor7"></span> 64+ <span>Kmh</span></li>
                      </ul>
                      <ul class="legend legend_ms">
                        <li><span class="infoColor infoColor1"></span> 0 <span>Ms</span></li>
                        <li><span class="infoColor infoColor2"></span> 1-2 <span>Ms</span></li>
                        <li><span class="infoColor infoColor3"></span> 3-4 <span>Ms</span></li>
                        <li><span class="infoColor infoColor4"></span> 5-7 <span>Ms</span></li>
                        <li><span class="infoColor infoColor5"></span> 8-9 <span>Ms</span></li>
                        <li><span class="infoColor infoColor6"></span> 10-17 <span>Ms</span></li>
                        <li><span class="infoColor infoColor7"></span> 18+ <span>Ms</span></li>
                      </ul>
                      <ul class="legend legend_bft">
                        <li><span class="infoColor infoColor1"></span> 0 <span>Bft</span></li>
                        <li><span class="infoColor infoColor2"></span> 1-2 <span>Bft</span></li>
                        <li><span class="infoColor infoColor3"></span> 3 <span>Bft</span></li>
                        <li><span class="infoColor infoColor4"></span> 4 <span>Bft</span></li>
                        <li><span class="infoColor infoColor5"></span> 5 <span>Bft</span></li>
                        <li><span class="infoColor infoColor6"></span> 6-8 <span>Bft</span></li>
                        <li><span class="infoColor infoColor7"></span> 8+ <span>Bft</span></li>
                      </ul>
                    </div>
                  </div>
                </div>
                <svg id="contextForecast">
                  <g>
                    <defs>
                      <linearGradient id="gradForecast" x1="0%" y1="0%" x2="100%" y2="0%"></linearGradient>
                    </defs>
                    <rect id="scaleColorForecast" x="0" y="85%" height="10%" width="100%" style="fill:url(#gradForecast); transform:translate(0px,-20px)" />
                  </g>
                  <g id="gridForecast"></g>
                  <g id="layer2Forecast">
                    <?php //no rafale max on Forecastsional ?>
                    <g><polyline id="graphForecast" points="" style="fill:none; stroke: #0034d2; stroke-width: 1px;" /></g>
                    <g><polyline id="graphForecastOverlay" points="" style="fill:rgba(42,73,153,0.5);" /></g>
                    <g id="errorForecast"></g>
                    <g id="errorForecastOverlay"></g>
                    <g id="arrowForecast"></g>
                    <g id="arrowForecastOverlay"></g>
                  </g>
                  <g id="textForecast"></g>
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="spot-infos">
        <div class="table">
          <div class="spot-info">
            <div class="table">
              <div class="cellInfo">
                <h2><span id="st_name"></span></h2>
                <hr>
                <div class="spot-activities">
                </div>
                <p id="st_news"></p>
              </div>
            </div>
          </div>
          <div class="spot-map">
            <div id="map" class="google-maps"></div>
          </div>
        </div>    
      </div>
    </div>
    <div class="bottom-menu">
      <div class="text-transparent">
        <?php echo WindspotsHelper::lang('VERSION').' '._WVERSION_NUMBER.' &copy '.WindspotsHelper::lang('WINDSPOTS_SARL').' 2004 - '.date('Y'); ?>
      </div>
    </div>
  </div>
</body>
  <script>
  <?php
  //init javascript var
  echo "  var currentStationName = '".$currentStationName."';\r\n";
  echo "  var refresh = true;\r\n";
  echo "  var windUnitSess = '".$unit."';\r\n";
  echo "  var graphDirection = '".$graph."';\r\n";
  echo "  var graphForecastDirection = '".$forecast."';\r\n";
  echo "  var hourLabelNow = '".WindspotsHelper::lang('Maintenant')."';\r\n";
  ?>
  // Use for graph (real/forcasted)
  let minErrorArea = <?php echo _WINDSPOTS_MIN_ERROR_AREA; ?>;
  function showInfo( left, top, strenght, direction, rafMax, hour, nData, timeLapse, widthContext ){
    boxInfo = document.getElementById('graph_box_info');
    boxInfoStrenght = document.getElementById('gbi_strenght');
    boxInfoDirectionName = document.getElementById('gbi_dir_name');
    boxInfoDirectionDeg = document.getElementById('gbi_dir_deg');
    boxInfoRaf = document.getElementById('gbi_raf');
    boxInfoHour = document.getElementById('gbi_hour');

    ltr = graphDirection !== 'rtl';
    timeLapse = timeLapse * nData;
    hour = hour.split('h');
    date = new Date();
    date.setHours( hour[0] );
    date.setMinutes( hour[1] );
    if( ltr ){
      date.setMinutes(date.getMinutes() + timeLapse);
    }else{
      date.setMinutes(date.getMinutes() - timeLapse);
    }
    minutes = date.getMinutes();
    if( minutes < 10 ){
      minutes = '0'+minutes;
    }
    timeData = date.getHours()+'h'+minutes;
    directionName = laPatate( direction, 0, true );
      boxInfo.style.display = "block";
      boxInfo.style.top = (graphMousePosY+10)+"px";
    if( widthContext < 850 ){
        boxInfo.style.top = (graphMousePosY+50)+"px";
      boxInfo.style.left = (left+90)+"px";
      if( (left + 90 + 100) >= widthContext ){
        boxInfo.style.left = (left-90)+"px";
      }
    }else{
      boxInfo.style.left = (left+150)+"px";
    }
    boxInfoHour.innerHTML = timeData;
    boxInfoStrenght.innerHTML = strenght;
    boxInfoDirectionName.innerHTML = directionName;
    boxInfoDirectionDeg.innerHTML = direction;
    //directionName
    if( rafMax == null ){
      boxInfoRaf.innerHTML = "0";
    }else{
      boxInfoRaf.innerHTML = rafMax;
    }
    var boxInfo = null;
    var boxInfoStrenght = null;
    var boxInfoDirectionName = null;
    var boxInfoDirectionDeg = null;
    var boxInfoRaf = null;
    var boxInfoHour = null;
    var date = null;
    var minutes = null;
    var timeData =  null;
    var directionName = null;
    var ltr = null;
  }
  function hideInfo(){
      boxInfo = document.getElementById('graph_box_info');
      boxInfo.style.display = "none";
      boxInfo = null;
  }
  function showInfoForecast( left, top, strenghtOverlay, directionOverlay, hour, nData, timeLapse, widthContext, strenghtForecast, directionForecast ){
      boxInfo = document.getElementById('graph_previ_box_info');
      boxInfoStrenght = document.getElementById('gbi_Forecast_strenght');
      boxInfoDirectionName = document.getElementById('gbi_Forecast_dir_name');
      boxInfoDirectionDeg = document.getElementById('gbi_Forecast_dir_deg');
      boxInfoOverlayStrenght = document.getElementById('gbi_Forecast_overlay_strenght');
      boxInfoOverlayDirectionName = document.getElementById('gbi_Forecast_overlay_dir_name');
      boxInfoOverlayDirectionDeg = document.getElementById('gbi_Forecast_overlay_dir_deg');
      boxInfoHour = document.getElementById('gbi_Forecast_hour');
      directionName = '';

      ltr = graphForecastDirection !== 'rtl';
      hour = hour.split('h');
      date = new Date();
      date.setHours( hour[0] );
      date.setMinutes( 0 );
      boxInfo.style.display = "block";
      graphForecastMousePosY = graphForecastMousePosY - 100;
      boxInfo.style.top = graphForecastMousePosY+"px";
      //console.log(widthContext);
      if( strenghtOverlay != null && directionOverlay != null){
      $('.gbi_Forecast_data_sub_title').text('<?php echo WindspotsHelper::lang('FORECASTED'); ?>');
        timeLapse = timeLapse * (nData%3);
        if( ltr ){
          date.setMinutes(date.getMinutes() + timeLapse);
        }else{
          date.setMinutes(date.getMinutes() - timeLapse);
        }         
        directionName = laPatate( directionOverlay, 0, true );
        boxInfoOverlayStrenght.innerHTML = strenghtOverlay;
        boxInfoOverlayDirectionName.innerHTML = directionName;
        boxInfoOverlayDirectionDeg.innerHTML = directionOverlay;
        $('.gbi_Forecast_data_overlay_wrapper').show();
        boxInfo.style.left = (left+100)+"px"; //+50
        if( widthContext < 850 ){
          boxInfo.style.top = (graphForecastMousePosY+150)+"px";
        boxInfo.style.left = (left+90)+"px"; //+90
        if( (left + 90 + 100) >= widthContext ){
          boxInfo.style.left = (left-90)+"px";
        }
        }else{
          //boxInfo.style.left = (left-50)+"px"; //+150
        }
      }else{
        $('.gbi_Forecast_data_overlay_wrapper').hide();
        $('.gbi_Forecast_data_sub_title').text('<?php echo WindspotsHelper::lang('FORECAST'); ?>');
        boxInfo.style.left = (left+100)+"px"; //+50
        if( widthContext < 850 ){
          boxInfo.style.top = (graphForecastMousePosY+150)+"px";
        boxInfo.style.left = (left+90)+"px"; //+90
        if( (left + 90 + 100) >= widthContext ){
          boxInfo.style.left = (left-90)+"px";
        }
        }else{
          //boxInfo.style.left = (left+50)+"px"; //+150
        }
        if( ltr ){
          date.setMinutes(0);
        }else{
          date.setMinutes(date.getMinutes() - 60);
        }    
      }
      minutes = date.getMinutes();
    if( minutes < 10 ){
      minutes = '0'+minutes;
    }
    timeData = date.getHours()+'h'+minutes;
    directionName = laPatate( directionForecast, 0, true );
    boxInfoHour.innerHTML = timeData;
    boxInfoStrenght.innerHTML = strenghtForecast;
    boxInfoDirectionName.innerHTML = directionName;
    boxInfoDirectionDeg.innerHTML = directionForecast;
    var boxInfo = null;
    var boxInfoStrenght = null;
    var boxInfoDirectionName = null;
    var boxInfoDirectionDeg = null;
    var boxInfoOverlayStrenght = null;
    var boxInfoOverlayDirectionName = null;
    var boxInfoOverlayDirectionDeg = null;
    var boxInfoHour = null;
    var date = null;
    var minutes = null;
    var timeData =  null;
    var directionName = null;
    var ltr = null;
  }
  function hideInfoForecast(){
      boxInfoForecast = document.getElementById('graph_previ_box_info');
      boxInfoForecast.style.display = "none";
      boxInfoForecast = null;
  }
  function hideMap(){
    boxMap = document.getElementById('map');
    boxMap.style.display = "none";
    boxMap = null;
  }
  var onMarkerClick = function(e){
      //console.log(this);
      //alert("You clicked on marker with customId: " +this.options.myCustomId);
      loadStationFromMap(this.options.stationName);   
  }
  var spotIcon = L.icon({
      iconUrl: 'images/ico-spot-normal.png',
      iconSize:     [28, 40]
  });
  var spotDisabledIcon = L.icon({
      iconUrl: 'images/ico-spot-disable.png',
      iconSize:     [28, 41]
  });
  var spotActiveIcon = L.icon({
      iconUrl: 'images/ico-spot-active.png',
      iconSize:     [28, 40]
  });
  function resizeBodyTimeout(){
    resizeBodyTimer = null;
    loadGraph( currentGraphType );
  }
  function layoutReady(){
    <?php
    echo "loadMainContent( 'spot' );";
    if($map === "false")
      echo "hideMap();";
    ?>
    keywordInput = $('#st_search');
    keywordDefaultValue = keywordInput.val();
    keywordInput.focus( focusSearch ).blur( blurSearch );
    var availableTags = [];
    $( "#st_search" ).autocomplete({
      source: availableTags,
      minLength: 0,
      select: actionSelectSearch,
      focus: actionFocusSearch
    });
    setInterval(refreshStation,10000);
    $("#context").mousemove( getGraphMousePos );
    $("#contextForecast").mousemove( getGraphForecastMousePos );
  }
  $( document ).ready( layoutReady );
  </script>
</html>