<?php

error_reporting(0);

/**
 * Renders an image to the browser
 * see readme for examples.
 */
include('config.php');
include('classes/imageLibrary.php');
include('classes/unsharpen_mask.php');
include('classes/DIP.class.php');
include('classes/SimpleDIP.class.php');

//trace($_SERVER);
//
//trace('cwd: ', getcwd());

$debug = false;

// Only continue if the url contains a valid image url.
if(isset($_REQUEST['i']) || isset($_REQUEST['ic'])) {
  
  $DIP = new SimpleDIP();
  // $DIP = new DIP();
  
  // Set the base path for saving cached images
  $DIP->base = SAVE_DESTINATION; // from config
  
  $options = buildOptions(); 
  
  // Toggle debug flag if necessary
  if(isset($options->debug) && $options->debug) { $DIP->debug = true; }
  
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