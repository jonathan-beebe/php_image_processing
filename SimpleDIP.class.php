<?php

class SimpleDIP extends DIP {
 
  /**
   * Override the name-building method to create simpler names, such as
   * 
   * 		boats_100_100.jpg
   * 
   * @param $options
   */
  public function BuildNameFromOptions($options) {
    
    $n = array();
    
    if(isset($options->w)) { $n[] = $options->w; }
    if(isset($options->h)) { $n[] = $options->h; }
    if(isset($options->sharpen)) { $n[] = 'sharpen'; }
    
    return '_' . implode('_', $n);
    
  }
  
}