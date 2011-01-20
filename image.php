<?php

error_reporting(-1);

/**
 * Renders an image to the browser
 * see readme for examples.
 */
include('config.php');
include('imageLibrary.php');
include('unsharpen_mask.php');
include('DIP.class.php');
include('SimpleDIP.class.php');

$debug = false;

// Only continue if the url contains a valid image url.
if(isset($_REQUEST['i']) || isset($_REQUEST['ic'])) {
  
  // $DIP = new SimpleDIP();
  $DIP = new DIP();
  
  // Set the base path for saving cached images
  $DIP->base = SAVE_DESTINATION; // from config
  
  $options = buildOptions(); 
  
  // Toggle debug flag if necessary
  if($options->debug) { $DIP->debug = true; }
  
  // Process!
  if(isset($_REQUEST['ic'])) {
    echo $DIP->ProcessComplex($_REQUEST['ic'], $options);
  }
  else if(isset($_REQUEST['i'])) {
    echo $DIP->ProcessImage($_REQUEST['i'], $options);
  }
  return;
  
}



function buildOptions() {

  $options = array();
  
  if( isset($_REQUEST['o']) ) { 
    $options = json_decode($_REQUEST['o']); 
  }
  
  return $options;
}