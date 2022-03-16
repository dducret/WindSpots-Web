<?php
$rootPath=__FILE__;
$scriptPath=baseName($rootPath);
$rootPath=str_replace($scriptPath,'',$rootPath);
$rootPath=realPath($rootPath.'../');
$rootPath=str_replace('\\','/',$rootPath);
date_default_timezone_set('Europe/Zurich');
$dataDir = '/data/sites/www/data/';
  // There is some subdirectory ?
  // /!\ Must be an array ! so if you don't have any subdirectory,
  // please define as follow: $validImageDir = array( 'LAST_FOLDER' ); ( i.e. /var/www/mysite/image/LAST_FOLDER where $dataDir = "/var/www/mysite/image/" )
  $validImageDir = array(
      'AVATAR',
      'CAPTURE',
      'GMAP',
      'GRAPH',
      'IMAGEBANK',
      'MENU',
  );
  // Main page of our website
  $homepage = 'https://' . $_SERVER['HTTP_HOST'] . '/';
  //*** Main process **********************************************************
  // Checks if we receive the expected parameters
  if ( !isset( $_GET['imagedir'] ) || !isset( $_GET['image'] ) || empty( $_GET['imagedir'] ) || empty( $_GET['image'] ) ) {
    header( 'Location: ' . $homepage );
    exit;
  }
  // Retrieve parameters ( subdirectory & image name )
  $imageDir = strtoupper( $_GET['imagedir'] );
  $imageName = $_GET['image'];
  // Check and set subdirectory
  if ( !is_array( $validImageDir ) || !in_array( $imageDir, $validImageDir ) ) {
    header( 'HTTP/1.1 403 Forbidden' );
    // die( 'Invalid image directory' );
    exit;
  }
  $subDir = strtolower( $validImageDir[0] );
  foreach ( $validImageDir as $imgDir ) {
    if ( $imageDir == $imgDir ) {
      $subDir = strtolower( $imgDir );
      break;
    }
  }
  // Get the image requested
  $imagePath = $dataDir . $subDir . '/' . $imageName;
  if ( !file_exists( $imagePath ) ) {
    die($imagePath);
    header( "HTTP/1.0 404 Not Found" );
    exit;
  }
  list( $imageWidth, $imageHeight, $imageType, $imageAttr ) = getimagesize( $imagePath );
  if ( $imageType == 1 ) {
    $imageType = 'gif';
  } elseif ( $imageType == 2 ) {
    $imageType = 'jpeg';
  } elseif ( $imageType == 3 ) {
    $imageType = 'png';
  } else {
    header( 'HTTP/1.0 404 Not Found' );
    // die( 'Invalid image type' );
    exit;
  }
  // Load image
  header( 'Content-type: image/' . $imageType );
  header( 'Access-Control-Allow-Origin: *' );
  header( 'Access-Control-Allow-Method: GET, POST' );
  header( 'Cache-Control: no-cache, must-revalidate' );
  header( 'Expires: Sat, 01 Jan 2000 06:00:00 GMT' );
  //@readfile( $imagePath );
  // Better way
  echo file_get_contents( $imagePath );
?>