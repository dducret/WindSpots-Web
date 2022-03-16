<?php
//Constant is used in included files to prevent direct access.
define('_WEXEC', 1);
//add files
require_once './includes/config.php';
require_once './includes/helper.php';
require_once './includes/controller.php';
require_once "./includes/content.php";
//force reload script
//$r = time();
$r = 27072018;
//get current station name (session)
$currentStationName = '';
ini_set("session.gc_maxlifetime", _WINDSPOTS_SESSION_LIFETIME);
ini_set('session.cookie_lifetime', _WINDSPOTS_SESSION_LIFETIME);
//ini_set("session.use_only_cookies", '1');
//session
if(session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params( _WINDSPOTS_SESSION_LIFETIME );
  /*
  @session_start(); // @ at the beginning to suppress the PHP notice - As stated in the manual for session_start(), a second call will do no harm,it will be simply ignored.
  // load cookies
  if(isset($_COOKIE['W_UNIT']) && $_COOKIE['W_UNIT'])
    $_SESSION['W_UNIT'] = $_COOKIE['W_UNIT'];
  if(isset($_COOKIE['W_STATION']) && $_COOKIE['W_STATION'])
    $_SESSION['W_STATION'] = $_COOKIE['W_STATION'];
  if(isset($_COOKIE['W_GRAPH_LTR']) && $_COOKIE['W_GRAPH_LTR'])
    $_SESSION['W_GRAPH_LTR'] = $_COOKIE['W_GRAPH_LTR'];
  if(isset($_COOKIE['W_GRAPH_PREV_LTR']) && $_COOKIE['W_GRAPH_PREV_LTR'])
    $_SESSION['W_GRAPH_PREV_LTR'] = $_COOKIE['W_GRAPH_PREV_LTR'];
  if(isset($_COOKIE['W_LANG']) && $_COOKIE['W_LANG'])
    $_SESSION['W_LANG'] = $_COOKIE['W_LANG'];
    */
}
@session_start(); // @ at the beginning to suppress the PHP notice - As stated in the manual for session_start(), a second call will do no harm,it will be simply ignored.
logIt("index _SESSION['W_LANG']: ".$_SESSION['W_LANG']);
logIt("index _SESSION['W_UNIT']: ".$_SESSION['W_UNIT']);
//by default fix method to received request data
$dataReceived = $_POST;
logIt('index - Post: '.json_encode($_POST));
//except for case below
$getTaskAllowed = array( 'confirm', 'content', 'language', 'saveConfig' );
if( isset($_REQUEST['task']) && in_array( $_REQUEST['task'], $getTaskAllowed ) ){
    $dataReceived = $_REQUEST;
}
//auto load content (js -> in new tab)
$autoLoadContentJs = '';
//process task recieved (controller)
WindspotsController::taskManager( $dataReceived );
//load translation (not logged)
if( !isset($_SESSION['W_LANG']) || empty($_SESSION['W_LANG']) ){
    $lang = WindspotsHelper::getBrowserLanguage();
    WindspotsHelper::loadTranslationFile( $lang );
    $_SESSION['W_LANG'] = $lang;
}else{
    $lang = $_SESSION['W_LANG'];
    WindspotsHelper::loadTranslationFile( $lang );
}
//get stations data
$stations = WindspotsHelper::getStations( array('station_name', 'display_name', 'latitude', 'longitude', 'image_time', 'data_time'), true);
//store stations in session to use with access again to the db
$_SESSION['WSTATIONS'] = $stations;
//language 
$langShort = 'en';
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
//prepare data (based on stations data)
$mapGlobalMarkers = '';
$searchAutoComplete = '';
$stationStatus = array();
$mapMarkersByStation = array();
$tmpStationName = array();
$stationsNavJS = array();
$stationsListQuickNav = '';
if( is_array($stations) && count($stations) > 0 ){
  $nbStation = count($stations);
  $markerId = 1;
  foreach($stations as $key => $station){
    //quick station nav JS -> prepare stations array
    $stationsNavJS[] = $station['station_name'];
    //check station up (map marker -> if no data until 5min -> change ico disable)
    $stationLastDataUpdate = strtotime( $station['data_time'] );
    // $stationLastImgUpdate = strtotime( $station['image_time'] );
    //if( ( ($now - $stationLastDataUpdate) <= 300 ) && ( ($now - $stationLastImgUpdate) <= 300 ) ){
    if( ($now - $stationLastDataUpdate) <= 300 ){
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
      ){
        $icoMarker = 'spotIcon';
        if( $stationStatus[$station['station_name']] == false ){
            $icoMarker = 'spotDisabledIcon';
        }
        //$markerStation .= 'var marker = L.marker(['.$station['latitude'].', '.$station['longitude'].'], {icon: '.$icoMarker.', stationName: \''.$station['station_name'].'\', title: "'.utf8_encode($station['display_name']).'"}); ';  //myCustomId: 5454,
        $markerStation .= 'var marker = L.marker(['.$station['latitude'].', '.$station['longitude'].'], {icon: '.$icoMarker.', stationName: \''.$station['station_name'].'\', title: "'.$station['display_name'].'"}); ';  //myCustomId: 5454,
        $markerStation .= 'marker.on(\'click\', onMarkerClick); ';
        $markerStation .= 'marker.addTo(mapGlobal); '; //map
        $markerStation .= 'var marker = null; ';
        $mapGlobalMarkers .= $markerStation;
        $mapMarkersByStation[$station['station_name']] = $markerStation;
    }
    //search auto complete
    //$searchAutoComplete .= '{label:"'.WindspotsHelper::convertStr($station['display_name']).'",value:"'.$station['station_name'].'"}';
    $searchAutoComplete .= '{label:"'.$station['display_name'].'",value:"'.$station['station_name'].'"}';
    if($key < ($nbStation - 1) ){
        $searchAutoComplete .= ',';
    }
    //select list station (quick nav)
    //$stationsListQuickNav .= '<div class="menu-spot-quick-nav-item" onclick="actionQuickNav( \''.$station['station_name'].'\' );">'.WindspotsHelper::convertStr($station['display_name']).'</div>';
    $stationsListQuickNav .= '<div class="menu-spot-quick-nav-item" onclick="actionQuickNav( \''.$station['station_name'].'\' );">'.$station['display_name'].'</div>';
  }
  if( is_array($stationsNavJS) && count($stationsNavJS) > 0 ){
      $stationsNavJS = implode("','", $stationsNavJS);
      $stationsNavJS = "var stationsNav = ['".$stationsNavJS."'];";
  }else{
      //reset if no data to avoid js error
      $stationsNavJS = [''];
    }
}
if( !empty($stationsListQuickNav) ){
    $stationsListQuickNav = '<div class="menu-spot-quick-nav" style="display: none;">'.$stationsListQuickNav.'</div>';
}
$_SESSION['WSTATIONS_MAP_MARKERS_BY_STATION'] = $mapMarkersByStation;
$_SESSION['WSTATIONS_STATUS'] = $stationStatus;
$_SESSION['WSTATIONS_STATUS_LAST_UPDATE'] = time();
//var_dump(date('Y-m-d H:i:s', $_SESSION['WSTATIONS_STATUS_LAST_UPDATE']));
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
  <div class="info_cookie">
    <?php 
    if( $langShort == 'fr' ){
        echo "<p>En poursuivant votre navigation sur ce site, vous acceptez l'utilisation de cookies créés par nous-mêmes afin de gérer vos préférences, comme décrit dans nos <span class=\"cookies_link\" onclick=\"loadContent( '', 'terms', 'Conditions d\'utilisation' );\">conditions d'utilisation</span> et <span class=\"cookies_link\" onclick=\"loadContent( '', 'data', 'Protection des données' );\">Protection des données</span>.<br />Si vous <a href=\"#\" onclick=\"acceptCookie();\">acceptez</a> notre utilisation des cookies, veuillez continuer à utiliser notre site.</p>";
    }else{
        echo "<p>By continuing your visit to this site, you accept the use of cookies created by us or by third parties to Forecastde statistics on the use of the site, as described in our <span class=\"cookies_link\" onclick=\"loadContent( '', 'terms', 'Terms of use' );\">Terms of uses</span> and <span class=\"cookies_link\" onclick=\"loadContent( '', 'data', 'Data protection' );\">Data protection</span>.<br />If you <a href=\"#\" onclick=\"acceptCookie();\">agree</a> to our use of cookies, please continue to use our site.</p>";
    }
    ?>
  </div>
  <div id="userConfigWrapper" class="modal" onclick="hideModal( 'userConfigWrapper', 'user_config', true );">
    <div id="user_config">
      <span class="icoCloseLogin pointer" onclick="hideModal( 'userConfigWrapper', 'user_config', false );"></span>
      <div id="user_config_form">
        <?php echo WindspotsHelper::generateModalConfigContent(); ?>
      </div>
    </div>
  </div>
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
  <?php echo $stationsListQuickNav; ?>
  <?php //-- website structure --?>
  <div id="myHeader" class="header-fixed">
    <div class="top-menu">
      <div class="logo" onclick="openUrl( '<?php echo _WINDSPOTS_URL; ?>' );">
        <img id="logo" src="svg/windspots.svg" style="height: 10vw; width:100%"/>
      </div>
      <div class="spot-nav-container">
        <div class="spot-nav">
          <div class="menu-spot-container menu-windspots">
            <div class="menu-spot" >
              <div style="display: inline-block; text-align: center; width:100%;">
                <div class="cellIco" onclick="quickStationNav( -1 );" style="display: inline-block; width: 2vw;">
                  <img src="svg/prev.svg" style="height: 1.2vw; width:100%"/>
                </div>
                <div class="cellIco" style="display: inline-block; width: 12vw; text-align: center; vertical-align: bottom;">
                  <div id="st_menu_name" style="height: 1.2vw; width: 100%; cursor: pointer;" onclick="displayQuickNav( false );">
                    <h4 style="padding-top: 0.6vw;">&nbsp;</h4>
                  </div>  
                </div>
                <div class="cellIco" onclick="quickStationNav( 1 );" style="display: inline-block; width: 2vw;">
                  <img src="svg/next.svg" style="height: 1.2vw; width: 100%"/>
                </div>
              </div>
            </div>
            <div class="menu-spot" >
              <input id="st_search" style="font-family: 'droid'; color: rgba(42,73,153,1); font-size: 1vw; margin: 0px;" value="<?php echo WindspotsHelper::lang('SEARCH_A_SPOT'); ?>">&nbsp;
            </div>
            <div class="menu-spot" >
              <img src="svg/search.svg" style="height: 1.4vw; width: 1.4vw;" onclick="$('#st_search').focus();"/>
            </div>
            <?php //spacer ?>
            <div class="menu-spot" style="width: 1.6vw;">
              &nbsp;
            </div>
            <div class="menu-spot">
              <img src="svg/world.svg" style="height:6vw; " onclick="loadMainContent('map_global');"/>
            </div>
          </div>
        </div>
      </div>
      <div class="user-menu-container">
        <div class="user-menu">
          <div style="display: table; width: 100%;">
            <div class="logo" style="padding: 0 2%; vertical-align: middle; width: 2vw;" >
            </div>
            <div class="menu-tab" style="position: relative; top: 2%;">
              <div class="cssmenu cssmenu_right">
                <ul>
                  <li class="<?php echo $clsMenuLanguageDE; ?>"><span class="cssmenu-label" onclick="switchLanguage('de_DE');">DE</span></li>
                  <li class="<?php echo $clsMenuLanguageEN; ?>"><span class="cssmenu-label" onclick="switchLanguage('en_GB');">EN</span></li>
                  <li class="<?php echo $clsMenuLanguageFR; ?>"><span class="cssmenu-label" onclick="switchLanguage('fr_FR');">FR</span></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div style="display: table; width: 100%;">
          <div class="menu-tab" style="position: relative; top: 2%;">
            <img src="svg/parameter.svg" class="user-menu-ico" id="user-menu-ico-login" style="width: 2.5vw; margin-left: 3vw;" onclick="showModal( 'userConfigWrapper', 'user_config', 'config');"/>
          </div>
        </div>
      </div>
    
    </div>
  </div>

  <div id="confirmation-msg"></div>
  <!-- <div id="error-msg"></div> -->
  <div id="myBody" class="body-scrollable">
    <div class="main-content main-content-vip-container"></div>
    <div class="main-content main-content-mapglobal-container">
      <div id="mapGlobal" style="display: block; width: 100%; height: 90vh;"></div>
    </div>
    <?php
    //preload content to limit ajax request --> just get content and show/hide elements
    //WindSpots
    //about
    echo WindspotsContent::generateWindspotsAbout();
    //contact
    echo WindspotsContent::generateWindspotsContact();
    //faq
    echo WindspotsContent::generateWindspotsFaq();
    //terms and conditions
    echo WindspotsContent::generateWindspotsTerms();
    //data protection
    echo WindspotsContent::generateWindspotsDataProtection();    
    //credits
    echo WindspotsContent::generateWindspotsCredits();
    ?>
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
            <svg version="1.1" id="patate_svg_file" xmlns="https://www.w3.org/2000/svg" xmlns:xlink="https://www.w3.org/1999/xlink" x="0px" y="0px"
               viewBox="0 0 250 250" style="enable-background:new 0 0 615.1 280.6; height: 17.8vw; width: 17.8vw;" xml:space="preserve">
              <g>
                <path id="fleche" fill="#00DBB1" opacity="0.9" style="transform-origin: 125px 125px 0px; display: block; transform: rotate(160deg);" d="M125,47.744L70.373,179.631 L125,154.566l54.628,25.064L125,47.744z"></path>
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
            <div class="wind-direction-patate" style="width: 100%; z-index: 9;position:absolute; top: 7vw; left:0px; text-align:center;">
              <h2><span id="st_wind_direction_letter_patate"></span>&nbsp;<span id="st_wind_direction_deg_patate"></span>&deg;</h2>
            </div>
            <div class="wind-speed-patate" style="width: 100%; z-index: 9; position:absolute; top: 9vw; left:0px; text-align:center;">
              <h2><span id="st_wind_speed_patate"></span>&nbsp;<span class="st_global_wind_unit">kts</span></h2>
            </div>
            <div class="wind-update-patate" style="width: 100%; position:absolute; top: 18.5vw; left:0;  text-align:center;">
              <h4><span class="st_last_update"></span></h4>
            </div>
            <div class="wind-gust-patate" style="display: inline-block; position:absolute; top: 20vw; left: 6.5vw; text-align:center;">
              <div class="table" style="width: 5.5vw">
                <div class="cellIco" style="">
                  <img style="opacity:0.6; width: 2.5vw;" src="svg/rafale.svg" />
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
                    <img style="opacity:0.6; width: 4vw;" src="svg/temp_air.svg" />
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
                    <img style="opacity:0.6; width: 4vw;" src="svg/temp_eau.svg" />
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
      <div class="bottom-menu-windspots" style="text-align: center;">
        <div onclick="loadContent( '', 'about', '<?php echo WindspotsHelper::lang('FOOTER_ABOUT'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_ABOUT'); ?></h3></div>
        <div onclick="loadContent( '', 'contact', '<?php echo WindspotsHelper::lang('FOOTER_CONTACT'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_CONTACT'); ?></h3></div>
        <div onclick="loadContent( '', 'faq', '<?php echo WindspotsHelper::lang('FOOTER_FAQ'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_FAQ'); ?></h3></div>
        <div onclick="loadContent( '', 'terms', '<?php echo str_replace("'", "\'", WindspotsHelper::lang('FOOTER_TERMS_OF_USE') ); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_TERMS_OF_USE'); ?></h3></div>
        <div onclick="loadContent( '', 'data', '<?php echo WindspotsHelper::lang('FOOTER_DATA_PROTECTION'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_DATA_PROTECTION'); ?></h3></div>
        <!-- <div onclick="loadContent( '', 'pub', '<?php echo WindspotsHelper::lang('FOOTER_PUBLICITY'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_PUBLICITY'); ?></h3></div> -->
        <div onclick="loadContent( '', 'credits', '<?php echo WindspotsHelper::lang('FOOTER_CREDITS'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_CREDITS'); ?></h3></div>
      </div>
      <div class="bottom-menu-vip" style="text-align: center;">
        <div onclick="loadContent( 'vip', 'about', '<?php echo WindspotsHelper::lang('FOOTER_ABOUT'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_ABOUT'); ?></h3></div>
        <div onclick="loadContent( 'vip', 'contact', '<?php echo WindspotsHelper::lang('FOOTER_CONTACT'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_CONTACT'); ?></h3></div>
        <!-- <div onclick="loadContent( 'vip', 'faq', '<?php echo WindspotsHelper::lang('Faq - vip'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_FAQ'); ?></h3></div> -->
        <div onclick="loadContent( 'vip', 'terms', '<?php echo str_replace("'", "\'", WindspotsHelper::lang('FOOTER_TERMS_OF_USE') ); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_TERMS_OF_USE'); ?></h3></div>
        <div onclick="loadContent( 'vip', 'data', '<?php echo WindspotsHelper::lang('FOOTER_DATA_PROTECTION'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_DATA_PROTECTION'); ?></h3></div>
        <!-- <div onclick="loadContent( 'vip', 'terms_sponsorship', '<?php echo WindspotsHelper::lang('FOOTER_TERMS_AND_CONDITION_SPONSORSHIP'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_TERMS_AND_CONDITION_SPONSORSHIP'); ?></h3></div> -->
        <div onclick="loadContent( 'vip', 'terms_shop', '<?php echo WindspotsHelper::lang('FOOTER_TERMS_AND_CONDITION_SHOP'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_TERMS_AND_CONDITION_SHOP'); ?></h3></div>
        <div onclick="loadContent( 'vip', 'credits', '<?php echo WindspotsHelper::lang('FOOTER_ABOUT'); ?>' );"><h3><?php echo WindspotsHelper::lang('FOOTER_CREDITS'); ?></h3></div>
      </div>
      <div class="text-transparent">
        <?php echo WindspotsHelper::lang('VERSION').' '._WVERSION_NUMBER.' &copy '.WindspotsHelper::lang('WINDSPOTS_SARL').' 2004 - '.date('Y'); ?>
      </div>
    </div>
  </div>
</body>
  <script>
  <?php
  // echo '// Cookies: '.$_COOKIE['W_UNIT']." - ".$_COOKIE['W_GRAPH_LTR']." - ".$_COOKIE['W_GRAPH_PREV_LTR']." - ".$_COOKIE['W_LANG']."\r\n";
  // echo '  // Session: '.$_SESSION['W_STATION'].' - '.$_SESSION['W_UNIT']." - ".$_SESSION['W_GRAPH_LTR']." - ".$_SESSION['W_GRAPH_PREV_LTR']." - ".$_SESSION['W_LANG']."\r\n";
  // echo '  // Current: '.$_SESSION['WCURRENT_STATION'].' - '.$currentStationName."\r\n";
  //init javascript var
  if( !empty($currentStationName) ){
    echo "  var currentStationName = '".$currentStationName."';\r\n";
    echo "  var refresh = true;\r\n";
  } else {
    if( isset($_SESSION['W_STATION']) && $_SESSION['W_STATION'] ) {
      $currentStationName = $_SESSION['W_STATION'];
      echo "  var currentStationName = '".$_SESSION['W_STATION']."';\r\n";
      echo "  var refresh = true;\r\n";
    } else {
      echo "  var currentStationName = null;\r\n";
      echo "  var refresh = false;\r\n";
    }
  }
  if( isset($_SESSION['W_UNIT']) && $_SESSION['W_UNIT'] ){
    echo "  var windUnitSess = '".$_SESSION['W_UNIT']."';\r\n";
  } else {
    echo "  var windUnitSess = '_kts';\r\n";
    $_SESSION['W_UNIT'] = '_kts';
  }
  echo "  ".$stationsNavJS."\r\n";
  if( isset($_SESSION['W_GRAPH_LTR']) && $_SESSION['W_GRAPH_LTR'] ){
    echo "  var graphDirection = '".$_SESSION['W_GRAPH_LTR']."';\r\n";
  } else {
    echo "  var graphDirection = 'ltr';"."\r\n";
  }
  if( isset($_SESSION['W_GRAPH_PREV_LTR']) && $_SESSION['W_GRAPH_PREV_LTR'] ){
    echo "  var graphForecastDirection = '".$_SESSION['W_GRAPH_PREV_LTR']."';\r\n";
  } else {
    echo "  var graphForecastDirection = 'ltr';\r\n";
  }
  echo "  var hourLabelNow = '".WindspotsHelper::lang('Maintenant')."';\r\n";
  ?>
  // Use for graph (real/forcasted)
  var minErrorArea = <?php echo _WINDSPOTS_MIN_ERROR_AREA; ?>;
  function showInfo( left, top, strenght, direction, rafMax, hour, nData, timeLapse, widthContext ){
      var boxInfo = document.getElementById('graph_box_info');
      var boxInfoStrenght = document.getElementById('gbi_strenght');
      var boxInfoDirectionName = document.getElementById('gbi_dir_name');
      var boxInfoDirectionDeg = document.getElementById('gbi_dir_deg');
      var boxInfoRaf = document.getElementById('gbi_raf');
      var boxInfoHour = document.getElementById('gbi_hour');
      var ltr = true;
      if( graphDirection == 'rtl' ){
        ltr = false;
      }
      timeLapse = timeLapse * nData;
      hour = hour.split('h');
      var date = new Date();
    date.setHours( hour[0] );
    date.setMinutes( hour[1] );
    if( ltr ){
      date.setMinutes(date.getMinutes() + timeLapse);
    }else{
      date.setMinutes(date.getMinutes() - timeLapse);
    }
    var minutes = date.getMinutes();
    if( minutes < 10 ){
      minutes = '0'+minutes;
    }
    var timeData = date.getHours()+'h'+minutes;
    var directionName = laPatate( direction, 0, true );
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
      var boxInfo = document.getElementById('graph_box_info');
      boxInfo.style.display = "none";
      var boxInfo = null;
  }
  function showInfoForecast( left, top, strenghtOverlay, directionOverlay, hour, nData, timeLapse, widthContext, strenghtForecast, directionForecast ){
      var boxInfo = document.getElementById('graph_previ_box_info');
      var boxInfoStrenght = document.getElementById('gbi_Forecast_strenght');
      var boxInfoDirectionName = document.getElementById('gbi_Forecast_dir_name');
      var boxInfoDirectionDeg = document.getElementById('gbi_Forecast_dir_deg');
      var boxInfoOverlayStrenght = document.getElementById('gbi_Forecast_overlay_strenght');
      var boxInfoOverlayDirectionName = document.getElementById('gbi_Forecast_overlay_dir_name');
      var boxInfoOverlayDirectionDeg = document.getElementById('gbi_Forecast_overlay_dir_deg');
      var boxInfoHour = document.getElementById('gbi_Forecast_hour');
      var directionName = '';
      var timeData = hour;
      var ltr = true;
      if( graphForecastDirection == 'rtl' ){
        ltr = false;
      }
      hour = hour.split('h');
      var date = new Date();
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
      var minutes = date.getMinutes();
    if( minutes < 10 ){
      minutes = '0'+minutes;
    }
    var timeData = date.getHours()+'h'+minutes;
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
      var boxInfoForecast = document.getElementById('graph_previ_box_info');
      boxInfoForecast.style.display = "none";
      var boxInfoForecast = null;
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
  function initMapGlobal(){
    //Leafletjs 
    mapGlobal = L.map('mapGlobal').setView([45.906741, 6.1528432], 6);
    checkInitMapGlobal = true;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(mapGlobal);
    <?php
    echo $mapGlobalMarkers;
    ?>
  }
  function redirectHome(){
    openUrl( "<?php echo _WINDSPOTS_URL; ?>" );
  }
  function switchLanguageCallBack( response ){
    // alert(response);
    redirectHome();
  }
  function switchLanguage( lang ){
    if( lang == 'fr_FR' || lang == 'en_GB' || lang == 'de_DE' ){
      $.post( "index.php", { "task":"language", "lang": lang }, switchLanguageCallBack );
    }
  }
  //without anonymous function and no reload
  var resizeBodyTimer = null
  function calculateHeaderBodyHeight(){
    var clientHeight = document.getElementById('myHeader').clientHeight;
    var d = document.getElementById('myBody');
    d.style.top = clientHeight+'px';
    var clientHeight = null;
    var d = null;
  } 
  function resizeBodyTimeout(){  
    calculateHeaderBodyHeight();      
    resizeBodyTimer = null;
    loadGraph( currentGraphType );
    userMenuAutoClose();
    autoCloseQuickNav();
  }
  function resizeBody() { 
    resizeBodyTimer = setTimeout(resizeBodyTimeout,200); 
  }
  window.addEventListener('resize', resizeBody);
  function loadContentInNewTab( type, content, title ){
    var win = window.open( '<?php echo _WINDSPOTS_URL; ?>?task=content&ty='+type+'&co='+content+'&ti='+title, '_blank');
    win.focus();
  }
  function checkAcceptCookie(){
    var check = false;
    var name = "acceptCookie=";
      var ca = document.cookie.split(';');
      for(var i = 0; i < ca.length; i++) {
          var c = ca[i];
          while (c.charAt(0) == ' ') {
              c = c.substring(1);
          }
          if (c.indexOf(name) == 0) {
            check = c.substring(name.length, c.length);
          }
      }
    toggleAcceptCookie( check );
  }
  function toggleAcceptCookie( check ){
    if( check != false ){
      jQuery('.info_cookie').hide();
    }else{
      jQuery('.info_cookie').show();
    }
  }
  function setAcceptCookie(c_name,value,expiredays){
    var exdate=new Date();
    exdate.setDate(exdate.getDate()+expiredays);
    document.cookie=c_name+ "=" +escape(value)+((expiredays==null) ? "" : ";expires="+exdate.toGMTString())+"; path=/";
    checkAcceptCookie();
  }
  function acceptCookie(){
    setAcceptCookie('acceptCookie','yes',365);
  }
  function resetModalContentCallBack( response ){
    if( response.wrapper_id == '' ){
      cleanModalsContent();
    }else{        
      $('#'+response.wrapper_id).html( response.modal_content );
    }
  }
  function resetModalContent( type ){
    $.post( "index.php", { "task":"generateModalContent", type:type }, resetModalContentCallBack, "json" );
  }
  function saveConfigCallBack( response ){
    // alert(response);
  }
  function userMenu( task, subTask ){
    switch(task){
      case 'config':        
        checkTask = true;
        if( subTask == 'save' ){
          cleanMsgAndError();
          prefWindUnit = $('#pref_wind_unit').val();
          prefFavoriteStation = $('#pref_fav_station').val();
          prefGraphDirection = $('#pref_graph_direction').val();
          prefGraphForecastDirection = $('#pref_graph_previ_direction').val();
          prefLanguage = $('#pref_user_language').val();
          $.post( "index.php", { "task":"saveConfig", pref_wind_unit:prefWindUnit, pref_favorite_station:prefFavoriteStation, pref_graph_direction:prefGraphDirection, pref_graph_previ_direction:prefGraphForecastDirection, pref_language:prefLanguage }, saveConfigCallBack, "json" );
          hideModal( 'userConfigWrapper', 'user_config', false );
          redirectHome();
        }
        if( subTask == 'cancel' ){
          hideModal( 'userConfigWrapper', 'user_config', false );
          resetModalContent( 'config' );
        }
      break;
    default:
      break;    
    }
  }
  function layoutReady(){
    resizeBodyTimeout();
    <?php     
    if( !empty($currentStationName) &&  $currentStationName != '1111' ){
      //load station
      //echo "loadStation( '".$currentStationName."', 1 );";
      echo "loadMainContent( 'spot' );";
    }else{
      //no station found (published and public)
      echo "loadMainContent( 'map_global' );";
    }
    ?>
    keywordInput = $('#st_search');
    keywordDefaultValue = keywordInput.val();
    keywordInput.focus( focusSearch ).blur( blurSearch ); 
    var availableTags = [
      <?php 
          echo $searchAutoComplete;
      ?>
    ];
    $( "#st_search" ).autocomplete({
      source: availableTags,
      minLength: 0,
      select: actionSelectSearch,
      focus: actionFocusSearch
    });
    setInterval(refreshStation,10000);
    $("#context").mousemove( getGraphMousePos );
    $("#contextForecast").mousemove( getGraphForecastMousePos );
    $(document).click( userMenuAutoClose );
    $('.user-menu-modal').mouseleave( userMenuAutoClose );
    $('.menu-spot-quick-nav').mouseleave( autoCloseQuickNav );
    $('body').on('click', '#profile_submit', function() {
      $('#profile_data_form').submit(function(e) {
        var formObj = $(this);
        var formURL = formObj.attr('action');
        var formData = new FormData(this);
        $.ajax({
          url: formURL,
          type: 'POST',
          data:  formData,
          mimeType: "multipart/form-data",
          contentType: false,
          cache: false,
          processData: false,
          beforeSend: ajaxAvatarbeforeSend,
            xhr: ajaxAvatarXhr,
          success: ajaxAvatarSuccess,
          complete: ajaxAvatarComplete,
          error: ajaxAvatarError          
        });
        e.preventDefault();
        $('#profile_data_form').unbind();
        var formObj = null;
        var formURL = null;
        var formData = null;
      });
    });
    calculateHeaderBodyHeight();
    headerHeight = document.getElementById('myHeader').offsetHeight;
    //console.log(headerHeight);
    <?php 
      //auto load content (from a link to open content in a new tab)
      echo $autoLoadContentJs;
    ?>
    /////////////////////debug - test
    jQuery('body').on('click', '#sp_form_submit', function() {
      jQuery('#sp_form').submit(function(e) {
        var formObj = jQuery(this);
        var formURL = formObj.attr('action');
        var formData = new FormData(this);
    //console.log(formObj);
    //console.log(formURL);
    //console.log(formData);
        jQuery.ajax({
          url: formURL,
          type: 'POST',
          data:  formData,
          mimeType: "multipart/form-data",
          contentType: false,
          cache: false,
          processData: false,
          beforeSend: ajaxSpNewBeforeSend,
            xhr: ajaxSpNewXhr,
          success: ajaxSpNewSuccess,
          complete: ajaxSpNewComplete,
          error: ajaxSpNewError          
        });
        e.preventDefault();
        jQuery('#sp_form').unbind();
        var formObj = null;
        var formURL = null;
        var formData = null;
      });
    });
    checkAcceptCookie();
  }
  $( document ).ready( layoutReady );
  </script>
</html>
