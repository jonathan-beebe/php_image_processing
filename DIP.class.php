<?php
/**
 * @author somethingkindawierd@gmail.com (Jon Beebe)
 * @copyright Copyright (c) 2011, Jonathan Beebe
 */
/**
 * DIP -- Dynamic Image Processor
 * 
 * Dynamically crop, resize, and/or sharpen an image.
 */
class DIP {

  public $debug = false;
  public $base = null;
  
  
  
  /**
   * Basic image processing, designed for thumbnails.
   * 
   * This will 1st scale the image to a best fit within the requested size.
   * Then it will crop the excess width or height.
   * Lastly a sharpen filter is applied, removing softness introduced by scaling.
   * 
   * @param string $path The path to the image.
   * @param object $options The json_decoded options.
   * 
   * @return raw image data.
   */
  public function ProcessImage($path, $options) {
  
    $defaults = array(
      'x'=>CENTER,
      'y'=>CENTER,
      'nocache'=>false,
      'debug'=>false
    );
      
    $options = (object)array_merge((array)$defaults, (array)$options);
    
    // Create the basic processing filters
    $scale = new stdClass();
    $scale->w = $options->w;
    $scale->h = $options->h;
    $options->scale = $scale;
    
    $crop = new stdClass();
    $crop->x = $options->x;
    $crop->y = $options->y;
    $crop->w = $options->w;
    $crop->h = $options->h;
    $options->crop = $crop;
    
    // If sharpenening has been requested then move it to the bottom of stack
    // because sharpening should always be performed last.
    if(isset($options->sharpen)) {
      $o = $options->sharpen;
      unset($options->sharpen);
      $options->sharpen = $o;
    }
    
    if($this->debug) echo '<p>options = <pre>' . var_export($options, true) . '</pre>';
    
    return $this->ProcessComplex($path, $options);
  }
  
  
  
  /**
   * Complex image processing.
   * 
   * Mix and match any of the filters available through the image library, in
   * any order. Options are supplied in an object and applied in order.
   * 
   * @param string $path The path to the image.
   * @param object $options The json_decoded options.
   * 
   * @return raw image data.
   */
  public function ProcessComplex($path, $options) {
    
    $options = $this->FillinOptions($options);
    
    if($this->debug) echo '<p>options = <pre>' . var_export($options, true) . '</pre>';
    
    $path_parts = $this->LocateImage($path);
    
    /*   $path_parts = array (
     *     'dirname' => 'test_images/sub_dir',
     *     'basename' => 'boats.jpg',
     *     'extension' => 'jpg',
     *     'filename' => 'boats',
     *     'sourceImagePath' => '/test_images/sub_dir/boats.jpg',
     *   )
     */    
    
    if($this->debug) echo '<p>$pathData = <pre>' . var_export($path_parts, true) . '</pre>';
    
    $destinationPath = $this->CalculateDestinationPath($path_parts, $options);
    
    if($this->debug) echo '<p>$destinationPath = <pre>' . var_export($destinationPath, true) . '</pre>';
    
    // If the file modification time of the original is NEWER than the thumbnail
    // OR the numbnail does not exist then process image.
    if(!file_exists($destinationPath) or ($options->nocache)) {
    
      $image = new Image();
      
      // enable debug?
      if($this->debug) { $image->debug = TRUE; }
      
      $image->open($path_parts['sourceImagePath']);
    
      // If target width and/or height is smaller than original, then process image
      if( $options->w < $image->original_width || $options->h < $image->original_height ) {
        
        if($this->debug) echo '<p>will process thumbnail</p>';
        
        // Set the save destination for the processed image
        $image->setSaveDestination($destinationPath);
        
        $this->PerformImageFilters($image, $options);
        
      }
        
    }
    // The processed image already exists in cache, so load it.
    else {
      $image = new Image_();
      $image->open($destinationPath);
    }
    
    // For debugging we echo the path to the processed image.
    if($this->debug) return $image->getImagePath();
    // For production version we echo the real image data.
    return $image->getImage();
    
  }
  
  
  
  public function PerformImageFilters($image, $options) {
    foreach($options as $key=>$opt) {
      
      switch($key) {
        
      case 'scale':
        $image->scale($opt->w, $opt->h, TRUE, FALSE);
        break;
        
      case 'crop':
        $image->crop($opt->x, $opt->y, $opt->w, $opt->h);
        break;
        
      case 'sharpen':
        $image->sharpen();
        break;
        
      }
    }
  }
  
  
  
  public function FillinOptions($o) {
    
    if(!isset($o->w) || !isset($o->h)) {
      
      if(isset($o->crop) && isset($o->crop->w) && isset($o->crop->h)) {
        $o->w = $o->crop->w;
        $o->h = $o->crop->h;
      }
      else if(isset($o->scale) && isset($o->scale->w) && isset($o->scale->h)) {
        $o->w = $o->scale->w;
        $o->h = $o->scale->h;
      }
      
    }
    
    if(isset($o->crop) && isset($o->crop->x) && isset($o->crop->y)) {
      $o->crop->x = strtolower($o->crop->x);
      $o->crop->y = strtolower($o->crop->y);
    }
    
    return $o;
    
  }
  
  
  
  public function CalculateDestinationPath($path_parts, $options) {
    // Should we duplicate the directory structure to the original image inside
    // of our cache folder?
    if(DUPLICATE_DIR_STRUCTURE) {
      $this->base = $this->getTargetDirectory($this->base, $path_parts['dirname']);
      $tmp_filename = $path_parts['filename'];
    }
    else {
      // Create new image's filename. This will be the full path to the image,
      // separated by dots, so something like :
      //     images.JEWELRY.Primary000007_AK-E03-14W_w258_h258.sharpen.jpg
      $tmp_filename = str_replace('/', '.', $path_parts['dirname']) . $path_parts['filename'];
    }
    
    if($this->debug) echo '<p>$tmp_filename = <pre>' . var_export($tmp_filename, true) . '</pre>';
    $tmp_filename .= $this->BuildNameFromOptions($options);
    $tmp_filename .= '.' . $path_parts['extension'];
    
    return $this->base . $tmp_filename;
  }
  
  
  
  public function BuildNameFromOptions($options) {
    
    $n = array();
    
    if(isset($options->w)) { $n[] = '_w' . $options->w; }
    if(isset($options->h)) { $n[] = '_h' . $options->h; }
    
    foreach($options as $key=>$opt) {
      
      switch($key) {
        
      case 'scale':
        $n [] = 'scale{w-' . $opt->w . ',h-' . $opt->h . '}';
        break;
        
      case 'crop':
        $n [] = 'crop{x-' . $opt->x . ',y-' . $opt->y . ',w-' . $opt->w . ',h-' . $opt->h . '}';
        break;
        
      case 'sharpen':
        $n[] = 'sharpen';
        break;
        
      }
    }
    
    return implode('_', $n);
    
  }
  
  
  
  public function LocateImage($path) {
    
    // Find the parts of the image
    $path_parts = pathinfo($path);
    $path_parts = $this->fixPathParts($path_parts);
    
    if($this->debug) echo '<p>path_parts = <pre>' . var_export($path_parts, true) . '</pre>';
      
    // Build a valid path to the image so we can open it.
    $sourceImagePath = '/' . $path_parts['dirname'] . '/' . $path_parts['basename'];
    
    if($this->debug) echo '<p>sourceImagePath = ' . $sourceImagePath . '</p>';
     
    $path_parts['sourceImagePath'] = $sourceImagePath;
    
    return $path_parts;
      
  }
  
  
  
  /**
   * Get the final path to a folder.
   * If the folders below the $base do not exist, then try to create them.
   * 
   * @param string $base The absolute path to the base directory.
   * @param string $dir_path Relative folder path 
   */
  public function getTargetDirectory($base, $dir_path) {
    global $debug;
    
    $array = explode('/', $dir_path);
    
    $assembled_path = $base;
    
    foreach($array as $folder) {
      
      if(!is_dir($assembled_path . $folder . '/')) {
        
        if($this->debug) { echo '<p>Will make folder "' . $assembled_path . $folder . '/' . '"</p>'; }
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
  public function fixPathParts($path_parts) {
  	$path = $path_parts['dirname'];
  	if(strpos($path, DOMAIN) !== false) {
  		$path = str_replace(DOMAIN, '', $path);
  		$path_parts['dirname'] = $path;
  	}
  	return $path_parts;
  }
  
}


