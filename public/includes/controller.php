<?php 
// No direct access to this file
defined('_WEXEC') or die('Restricted access');
class WindspotsController{  
  public static function taskManager( $dataReceived = '' ){
    //mapping task received
    logIt("Controller taskManger ".json_encode($dataReceived));
    if( is_array($dataReceived) && count($dataReceived) > 0 ){
      if( isset($dataReceived['task']) && !empty($dataReceived['task']) ){
        switch( $dataReceived['task'] ){
          case 'content':
            //action done in index.php -> call js to load content
            //insert case here to respect process and allow this case (to avoid default case 'Restricted access')
            //no action required here for the moment
            break;
          case 'loadStation':
            if( isset($dataReceived['sid']) && !empty($dataReceived['sid']) && isset($dataReceived['load']) ){
              WindspotsHelper::loadStationData( $dataReceived['sid'], $dataReceived['load'] );
            }
            break;
          case 'generateModalContent':
            if( isset($dataReceived['type']) && !empty($dataReceived['type']) ){
              WindspotsHelper::generateModalContent( $dataReceived['type'] );
            }
            break;
          case 'resetConfirmationMessage':
            WindspotsHelper::resetConfirmationMessage();
            break;
          case 'resetStationName':
            //call ajax -> so stop php
            WindspotsHelper::setCurrentStation( '', true );
            break;
          case 'saveConfig':
            $prefWindUnit = '';
            $prefFavoriteStation = '';
            $prefGraphDirection = '';
            $prefGraphPreviDirection = '';
            $prefLanguage = '';
            if( isset($dataReceived['pref_wind_unit']) && $dataReceived['pref_wind_unit'] != '' ){
              $prefWindUnit = $dataReceived['pref_wind_unit'];
            }
            if( isset($dataReceived['pref_favorite_station']) && $dataReceived['pref_favorite_station'] != '' ){
              $prefFavoriteStation = $dataReceived['pref_favorite_station'];
            }
            if( isset($dataReceived['pref_graph_direction']) && $dataReceived['pref_graph_direction'] != '' ){
              $prefGraphDirection = $dataReceived['pref_graph_direction'];
            }
            if( isset($dataReceived['pref_graph_previ_direction']) && $dataReceived['pref_graph_previ_direction'] != '' ){
              $prefGraphPreviDirection = $dataReceived['pref_graph_previ_direction'];
            }
            if( isset($dataReceived['pref_language']) && $dataReceived['pref_language'] != '' ){
              $prefLanguage = $dataReceived['pref_language'];
            }
            WindspotsHelper::saveConfigPreferences( $prefWindUnit, $prefFavoriteStation, $prefGraphDirection, $prefGraphPreviDirection, $prefLanguage );
            break;
          case 'language':
            //reload translation
            if( isset($dataReceived['lang']) && $dataReceived['lang'] != '' ){
              $lang = $dataReceived['lang'];
              if( $lang == 'fr_FR' || $lang == 'en_GB' || $lang == 'de_DE' ){
                $_SESSION['W_LANG'] = $lang;
                $_COOKIE['W_LANG']= $lang;
                logIt("Controller taskManger _SESSION['W_LANG']: ".$_SESSION['W_LANG']);
                //load new translation
                WindspotsHelper::loadTranslationFile( $lang );
              }
            }
            break;
          default:
            logIt("Controller taskManger UNDEFINED TASK: ".$dataReceived['task']);
            break;
        }
      }else{
        die('Not a task');
      }
    }
    return false;   
  }
}