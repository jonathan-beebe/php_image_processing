<?php

error_reporting(-1);

/**
 * Renders an image to the browser
 * see readme for examples.
 */
include('config.php');
include('imageLibrary.php');
include('unsharpen_mask.php');

$debug = false;

// Establish the location of saved/cached images.
$tmp = SAVE_DESTINATION;

// TODO: Should we create the path to the cache folder if it does not exist?

// Only continue if the url contains a valid image url.
if(isset($_REQUEST['i'])) {
  
  // Set the base path for saving cached images
  DIP::$base = $tmp;
  
  $options = buildOptions(); 
  
  // Toggle debug flag if necessary
  if($options->debug) { DIP::$debug = true; }
  
  // Process!
  DIP::ProcessImage($_REQUEST['i'], $options);
  
}


function buildOptions() {

  $options = array();
  
  if( isset($_REQUEST['o']) ) { 
    $options = json_decode($_REQUEST['o']); 
  }
  
  return $options;
}



/**
 * DIP -- Dynamic Image Processor
 */
class DIP {

  static public $debug = false;
  static public $base = null;
  
  static public function ProcessImage($path, $options) {
  
    $defaults = array(
      'x'=>CENTER,
      'y'=>CENTER,
      'filter'=>array(),
      'nocache'=>false,
      'debug'=>false
    );
      
    $options = (object)array_merge((array)$defaults, (array)$options);
    
    if(self::$debug) echo '<p>options = <pre>' . var_export($options, true) . '</pre>';
    
  	$image = new Image();
    
  	// enable debug?
  	if(self::$debug) { $image->debug = TRUE; }
    
  	// Find the parts of the image
  	$path_parts = pathinfo($path);
  	$path_parts = self::fixPathParts($path_parts);
    
  	if(self::$debug) echo '<p>path_parts = <pre>' . var_export($path_parts, true) . '</pre>';
    
    /*
     *  path_parts = array (
     *    'dirname' => 'test_images/sub_dir',
     *    'basename' => 'boats.jpg',
     *    'extension' => 'jpg',
     *    'filename' => 'boats',
     *  )
     */
      
      // Build a valid path to the image so we can open it.
  	$sourceImagePath = '/' . $path_parts['dirname'] . '/' . $path_parts['basename'];
    
  	if(self::$debug) echo '<p>sourceImagePath = ' . $sourceImagePath . '</p>';
    
  	$image->open($sourceImagePath);
    
  	// Set the target width and height. First grab the original image
  	// values. If the url specifies overrides, then use them.
  	$width = $image->original_width;
    if( isset($options->w) ) { $width = $options->w; }
    
  	$height = $image->original_height;
    if( isset($options->h) ) { $height = $options->h; }
    
  	// If target width and/or height is smaller than original, then process image
    if( $width < $image->original_width || $height < $image->original_height ) {
      
      // Should we duplicate the directory structure to the original image inside
      // of our cache folder?
      if(DUPLICATE_DIR_STRUCTURE) {
        self::$base = self::getTargetDirectory(self::$base, $path_parts['dirname']);
        $tmp_filename = $path_parts['filename'] . '_w' . $width . '_h' . $height;
    		if($options->filter->sharpen) { $tmp_filename .= '.sharpen'; }
    		$tmp_filename .= '.' . $path_parts['extension'];
      }
      else {
    		// Create new image's filename. This will be the full path to the image,
    		// separated by dots, so something like :
        //     images.JEWELRY.Primary000007_AK-E03-14W_w258_h258.sharpen.jpg
        $tmp_filename = str_replace('/', '.', $path_parts['dirname']) . 
        $path_parts['filename'] . 
                                              '_w' . $width . '_h' . $height;
    		if($options->filter->sharpen) { $tmp_filename .= '.sharpen'; }
    		$tmp_filename .= '.' . $path_parts['extension'];
      }
      
  		// If the file modification time of the original is NEWER than the thumbnail
  		// OR the numbnail does not exist then process image.
      if(
        !file_exists(self::$base . $tmp_filename)
        or ( filemtime(self::$base . $tmp_filename) - filemtime( $image->src_image_path ) < 0 )
        or ($options->nocache)
      ) {
          
  			if(self::$debug) echo '<p>will process thumbnail</p>';
        
  			// Set the save destination for the processed image
  			$image->setSaveDestination( self::$base . $tmp_filename );
        
  			// Crop and scale the image
  			$image->scale($width, $height, TRUE, FALSE);
  			$image->crop($options->x, $options->y, $width, $height);
        
  			// Sharpen the image? Default = false.
  			if($options->filter->sharpen) { $image->sharpen(); }
        
      }
  		// The processed image already exists in cache, so load it.
      else {
  			$image = new Image_();
  			$image->open(self::$base . $tmp_filename);
      }
    }
    
  	// For debugging we echo the path to the processed image.
  	if(self::$debug) echo $image->getImagePath();
  	// For production version we echo the real image data.
  	else echo $image->getImage();
    
  	return;
  }
  
  /**
  * Get the final path to a folder.
   * If the folders below the $base do not exist, then try to create them.
   * 
   * @param string $base The absolute path to the base directory.
   * @param string $dir_path Relative folder path 
   */
  static public function getTargetDirectory($base, $dir_path) {
    global $debug;
    
    $array = explode('/', $dir_path);
    
    $assembled_path = $base;
    
    foreach($array as $folder) {
      
      if(!is_dir($assembled_path . $folder . '/')) {
        
        if(self::$debug) { echo '<p>Will make folder "' . $assembled_path . $folder . '/' . '"</p>'; }
        mkdir($assembled_path . $folder . '/');
      }
      
      clearstatcache();
      
      if(is_dir($assembled_path . $folder . '/')) {
        $assembled_path .= $folder . '/';
      }
    }
    
    return $assembled_path;
  }
  
  /**
   * Fix the image dirname if it is a url path.
   * Given a domain set in the config file, remove the domain
   * from the root of the path so we now have the image path from root
   * of the web directory where images are hosted.
   *
   * @param array $path_parts Array of pathname parts.
   * 
   * @return array The same path_parts array with dirname fixed.
   */
  static public function fixPathParts($path_parts) {
  	$path = $path_parts['dirname'];
  	if(strpos($path, DOMAIN) !== false) {
  		$path = str_replace(DOMAIN, '', $path);
  		$path_parts['dirname'] = $path;
  	}
  	return $path_parts;
  }
  
}


