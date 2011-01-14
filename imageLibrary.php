<?php

	set_error_handler("my_warning_handler", E_WARNING);

	function my_warning_handler($errno, $errstr) {
		if(strpos($errstr, 'function.chmod') !== FALSE) {
			
			// We do not need to respond to an error where we do not have permission to 
			// set permissions. 
			
		}
		else {
			echo '<pre>' . $errno . ' | ' . $errstr . '</pre>';
			die();
		}
	}

	define('RIGHT', 'right');
	define('LEFT', 'left');
	define('CENTER', 'center');
	define('TOP', 'top');
	define('BOTTOM', 'bottom');
	
	/**
	 * A hack class. It behaves same as Image, but it only
	 * opens and renders an image. Designed for those images that
	 * you want to load directry to the browser without processing.
	 */
	class Image_ {
		
		var $path = null;
		
		function open($path) {
			$this->path = $path;
		}
		
		function getImage() {
			if(file_exists($this->path)) {
					
				$info = GetImageSize($this->path);
				
				$type = $info[2];
				
				switch ($type) {
			
					//case 'jpeg':
					case IMAGETYPE_JPEG:
						$image_create_func = 'ImageCreateFromJPEG';
						break;

					//case 'png':
					case IMAGETYPE_PNG:
						$image_create_func = 'ImageCreateFromPNG';
						break;

					//case 'bmp':
					case IMAGETYPE_BMP:
						$image_create_func = 'ImageCreateFromBMP';
						break;

					//case 'gif':
					case IMAGETYPE_GIF:
						$image_create_func = 'ImageCreateFromGIF';
						break;

					default:
						$image_create_func = 'ImageCreateFromJPEG';
				}
				
				if(isset($image_create_func)) {
				    $image = $image_create_func($this->path);
				}

				$mime = image_type_to_mime_type($info[2]);
				header("Content-type: $mime");

				// Writing image according to type to the output destination
				switch ( $info[2] ) {
					case IMAGETYPE_GIF:   imagegif($image);    break;
					case IMAGETYPE_JPEG:  imagejpeg($image);   break;
					case IMAGETYPE_PNG:   imagepng($image);    break;
					default: return false;
				}
			}
		}
		
		/**
		 * Get the path to the destination image
		 * 
		 * @return string The url to the destination image
		 */
		function getImagePath() {
			return $this->path;
		}
	}

	/**
	 * Wraps an image with processing features such as crop, sharpen, etc.
	 */
	class Image {
		
		var $src_image = '';       // The image path relative to root url (from users perspective)
		var $src_image_path = '';  // The image path from root of server (from servers perspective)
		var $dest_image = null;    // The destination path from root of server (from servers perspective)
		
		var $image = null;         // The image data in memory from ImageCreateFunction
		var $info = null;          // Info on the image from GetImageSize
		
		var $original_info = null;    // Info from the image before any operations performed
		var $original_width = null;   // Width of original image before any operations
		var $original_height = null;  // Height of original image before any operations
		
		var $image_create_func = null;  // The function to create read image into memory
		var $image_save_func = null;    // The function to save image from memory onto disk
		var $new_image_ext = null;      // The extension of the image
		
		var $debug = FALSE;       // Render debug messages or no?
		var $cropLarger = FALSE;  // Is image allowed to crop to larger canvas than original dimensions?
		
		function Image() {
			
		}
		
		/**
		 * Open an image.
		 */
		function open($path) {
			if($this->debug) echo '<p>Opening "' . $path . '"</p>';
			$this->setImagePath($path);
			
			// Gather the image info
			
			$this->original_info = GetImageSize($this->src_image_path);
			$this->info = GetImageSize($this->src_image_path);
		
			$this->original_width = $this->info[0];
			$this->original_height = $this->info[1];
		}
		
		/**
		 * Load the image from disk into memory.
		 * 
		 * Each image operation should call this in an if-statement.
		 * If true, do work, else exit
		 * 
		 * @return boolean TRUE if read succeeds, FALSE otherwise
		 */
		function loadSourceImage() {
			if($this->image === null) {
				$image_create_func = $this->getImageCreateFunction();
				$this->image = $image_create_func($this->src_image_path);
			}
			
			if($this->image !== NULL) {
				return TRUE;
			}
			return FALSE;
		}
		
		/**
		 * Write the completed image to disk.
		 * 
		 * Will update all info associated with the image.
		 * 
		 * Each image operation should call this after it's done with
		 * its operations to write image to disk and prepare the Image
		 * object for further operations on this image.
		 * 
		 * @return boolean TRUE if write succeeds, FALSE otherwise
		 */
		function writeImageToDestination($image) {
			
			$this->image = $image;
			
			$image_save_func = $this->getImageSaveFunction();

			if($this->debug) echo '<p>Saving to "' . $this->dest_image . '"</p>';
			$process = $image_save_func($image, $this->dest_image) or die("There was a problem in saving the new file.");
			
			// Refresh the info on this image
			$this->info = GetImageSize($this->dest_image);
			
			// if($this->debug) echo '<p>src: "' . $this->src_image . '"</p>';
			// if($this->debug) echo '<p>src path: "' . $this->src_image_path . '"</p>';
			
			$this->setImagePath($this->dest_image);
			
			// if($this->debug) echo '<p>after the scale...</p>';
			// if($this->debug) echo '<p>src: "' . $this->src_image . '"</p>';
			// if($this->debug) echo '<p>src path: "' . $this->src_image_path . '"</p>';
			
			return TRUE;
		}
		
		/**
		 * Set image paths
		 * 
		 * Will set all image paths needed, such as browser url 
		 * to the image (from users perspective) and server url
		 * from the servers perspective
		 */
		function setImagePath($path) {
			
			// Try to find image on this server relative to the script's path
			
			$uri = $_SERVER['SCRIPT_NAME'];
			// $uri = $_SERVER['REQUEST_URI'];
			
			if($this->debug) echo '<p>uri = "' . $uri . '"</p>';
			
			$strrchr = strrchr($uri, '/');
			
			if($this->debug) echo '<p>strrchr = "' . $strrchr . '"</p>';
			
			if( $strrchr !== FALSE && $strrchr !== '/' ) {
				$uri = str_replace($strrchr, '/', $uri);
			}
			
			if($this->debug) echo '<p>uri = "' . $uri . '"</p>';
			
			// Try to find the image on this server at root path
			if($this->debug) echo '<p>path = ' . $_SERVER['DOCUMENT_ROOT'] . '/' . $path . '</p>';
			if(file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $path)) {
				$this->src_image_path = $path;
			}
			
			else if(file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $uri . $path)) {
				$this->src_image_path = $uri . $path;
			}
			
			$this->src_image = $this->src_image_path;
			
			// Default behavior is to save over the original
			// Notice that we DO NOT append the DOCUMENT ROOT
			
			if($this->dest_image === null) {
				$this->dest_image = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'] . $this->src_image_path);
			}
			
			// Remove any double-slashes "//" that might be in the path
			// Create the full path to the image
			
			if(strpos($this->src_image_path, $_SERVER['DOCUMENT_ROOT']) === FALSE) {
				$this->src_image_path = $_SERVER['DOCUMENT_ROOT'] . $this->src_image_path;
			}
			
			$this->src_image_path = str_replace('//', '/', $this->src_image_path);
			
			if($this->debug) echo '<p>src: "' . $this->src_image . '"</p>';
			if($this->debug) echo '<p>src path: "' . $this->src_image_path . '"</p>';
			if($this->debug) echo '<p>dest: "' . $this->dest_image . '"</p>';
		}
		
		/**
		 * Set the destination to save the image out to
		 * 
		 * @param path string The path to save image to. Must me writable by server.
		 */
		function setSaveDestination($path) {
			$this->dest_image = $path;
		}
		
		/**
		 * Append something to the destination image name
		 * 
		 * @param string The string to append to the destination filename.
		 */
		function appendtoSaveDestination($append) {
			$this->dest_image .= $append;
		}
		
		/**
		 * Sharpen an image with an unsharpen mask
		 * Designed to emulate Photoshop's Unsharpen Mask
		 * 
		 * Uses Unsharp mask algorithm by Torstein Hønsi 2003-07.
		 * 
		 * The Amount parameter simply says how much of the effect you want. 100 is 'normal'. 
		 * Radius is the radius of the blurring circle of the mask. 
		 * 'Threshold' is the least difference in colour values that is allowed between the original 
		 * and the mask. In practice this means that low-contrast areas of the picture are left 
		 * unrendered whereas edges are treated normally. This is good for pictures of e.g. skin or blue skies. 
		 * 
		 * @param int $amount The sharpn amount - 100 is 'normal'. Max: 500
		 * @param float $radius The radious of the blur. Max: 50
		 * @param int $threshold Least difference in color allowed. Max: 255
		 */
		function sharpen($amount = 80, $radius = 0.5, $threshold = 3) {
			
			if($this->debug) echo '<p>BEGIN sharpen</p>';
			
			if($this->debug) echo '<p>src: "' . $this->src_image . '"</p>';
			if($this->debug) echo '<p>src path: "' . $this->src_image_path . '"</p>';
			
			if( $this->loadSourceImage() ) {
				
				if($this->debug) echo '<p>will sharpen</p>';
				
				// do the sharpening...
				
				$image_sharpened = UnsharpMask($this->image, $amount, $radius, $threshold);
				
				if($image_sharpened) {

					if($this->debug) echo '<p>sharpened...will save</p>';
			
					if( $this->writeImageToDestination($image_sharpened) ) {
						if($this->debug) echo '<p>END sharpen: SUCCESS</p>';
						return TRUE;
					}
				}
			}
			
			if($this->debug) echo '<p>END sharpen: FAIL</p>';
			return FALSE;
		}
		
		/**
		 * Perform a crop operation.
		 * 
		 * @param number $x_pos The left corner of the crop
		 * @param number $y_pos The top corner of the crop
		 * @param number $width The width of the crop area
		 * @param number $height The height of the crop area
		 */
		function crop($x_pos = CENTER, $y_pos = CENTER, $width, $height) {
			
			if($this->debug) echo '<p>BEGIN crop</p>';
			
			$original_width = $this->info[0];
			$original_height = $this->info[1];
			
			if($this->debug) echo '<p>src: "' . $this->src_image . '"</p>';
			if($this->debug) echo '<p>src path: "' . $this->src_image_path . '"</p>';
			
			// Default top/left position of crop
			
			$dest_x = 0;
			$dest_y = 0;
			$x = 0;
			$y = 0;
			
			$x = $this->parseNumber($x_pos, $width);
			$y = $this->parseNumber($y_pos, $height);
			
			if($this->debug) echo '<p>x = ' . $x . '</p>';
			if($this->debug) echo '<p>y = ' . $y . '</p>';
			
			$width = $this->parseNumber($width, $original_width);
			$height = $this->parseNumber($height, $original_height);
			
			if(!$this->cropLarger) {
				if($width > ($original_width)) $width = $original_width - $x;
				if($height > ($original_height)) $height = $original_height - $y;
			}
			
			if($this->debug) echo '<p>width = ' . $width . '</p>';
			if($this->debug) echo '<p>height = ' . $height . '</p>';
			
			if($this->debug) echo '<p>original_width = ' . $original_width . '</p>';
			if($this->debug) echo '<p>original_height = ' . $original_height . '</p>';
			
			// Calculate the top/left position of custom crops
			if($original_width > $width) {
				if($x_pos === RIGHT) {
					$x = $original_width - $width;
				}
				else if($x_pos === CENTER) {
					$x = ($original_width - $width)/2;
				}
				// Otherwise we use the default of LEFT
			}
			else if($original_width < $width) {
				if($x_pos === RIGHT) {
					$dest_x = $width - $original_width;
				}
				else if($x_pos === CENTER) {
					$dest_x = ($width - $original_width)/2;
				}
				// Otherwise we use the default of LEFT
			}
            else { // widths both match, so x is origin
                $x = 0;
            }
			
			if ($original_height > $height) {
				if($y_pos === BOTTOM) {
					$y = $original_height - $height;
				}
				else if($y_pos === CENTER) {
					$y = ($original_height - $height)/2;
				}
				// Otherwise use default of TOP
			}
			else if ($original_height < $height) {
				if($y_pos === BOTTOM) {
					$dest_y = $height - $original_height;
				}
				else if($y_pos === CENTER) {
					$dest_y = ($height - $original_height)/2;
				}
				// Otherwise use default of TOP
			}
			
			else { // heights both match, so y is origin
				$y = 0;
			}
			
			if($this->debug) echo '<p>x = ' . $x . '</p>';
			if($this->debug) echo '<p>y = ' . $y . '</p>';
			if($this->debug) echo '<p>width = ' . $width . '</p>';
			if($this->debug) echo '<p>height = ' . $height . '</p>';
			
			if( $this->loadSourceImage() ) {
				
				$new_image = ImageCreateTrueColor($width, $height);
				
				if($new_image) {
					//if($this->debug) echo 'Created image!';
				}
				else {
					if($this->debug) echo 'Failed to create image';
				}
						
				// This is the resizing/resampling/transparency-preserving magic

				if ( ($this->info[2] == IMAGETYPE_GIF) || ($this->info[2] == IMAGETYPE_PNG) ) {
					$transparency = imagecolortransparent($this->image);

					if ($transparency >= 0) {
						$transparent_color  = imagecolorsforindex($this->image, $trnprt_indx);
						$transparency	 = imagecolorallocate($new_image, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
						imagefill($image_resized, 0, 0, $transparency);
						imagecolortransparent($new_image, $transparency);
					}
					elseif ($this->info[2] == IMAGETYPE_PNG) {
						imagealphablending($new_image, false);
						imagesavealpha($new_image, true);
						$color = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
						imagefill($new_image, 0, 0, $color);
					}
				}

				// Crop to the given dimensions
				// We use to use the $width & $height, but that left images cropped to LARGER that the original size
				// with big black areas. Using the original size seems to fix this
				//if(ImageCopy($new_image, $image, 0, 0, $x, $y, $width, $height)) {
				//if(ImageCopy($new_image, $image, 0, 0, $x, $y, $original_width, $original_height)) {
					
				// This version allows us to 'crop' to a larger size, adding padding around an image
				if(ImageCopy($new_image, $this->image, $dest_x, $dest_y, $x, $y, $original_width, $original_height)) {
					//if($this->debug) echo 'Success copying image';
				}
				else {
					if($this->debug) echo 'Failed to copy image';
				}

				if( $this->writeImageToDestination($new_image) ) {
					if($this->debug) echo '<p>END crop: SUCCESS</p>';
					return TRUE;
				}
				
				if($this->debug) echo '<p>END crop: FAIL</p>';
				
				return TRUE;
			}
			
			return FALSE;
		}
		
		/**
		 * Perform a scale operation.
		 * 
		 * @param number $width The destination width
		 * @param number $height The destination height
		 * @param bool $proportional Should image be scaled proportionally, or no?
		 * @param bool $inside Should image be scaled to best fit within width/height, or no?
		 */
		function scale($width                     = 0, 
		               $height                    = 0, 
		               $proportional              = false, 
		               $inside                    = true) 
		{
			
			if($this->debug) echo '<p>BEGIN scale</p>';
			
			if ( $height <= 0 && $width <= 0 ) return false;
			
			$final_width = 0;
			$final_height = 0;
			list($width_old, $height_old) = $this->info;
			
			$width = $this->parseNumber($width, $width_old);
			$height = $this->parseNumber($height, $height_old);
			
			if($inside) {
				// Calculating proportionality
				if ($proportional) {
					if	($width  == 0)  $factor = $height/$height_old;
					elseif  ($height == 0)  $factor = $width/$width_old;
					else                    $factor = min( $width / $width_old, $height / $height_old );

					$final_width  = round( $width_old * $factor );
					$final_height = round( $height_old * $factor );
				}
				else {
					$final_width = ( $width <= 0 ) ? $width_old : $width;
					$final_height = ( $height <= 0 ) ? $height_old : $height;
				}
			}
			else {
				// Calculating proportionality
				if ($proportional) {
					if	($width  == 0)  $factor = $height/$height_old;
					elseif  ($height == 0)  $factor = $width/$width_old;
					else                    $factor = max( $width / $width_old, $height / $height_old );

					$final_width  = round( $width_old * $factor );
					$final_height = round( $height_old * $factor );
					
					if($this->debug) echo '<p>Width: ' . $final_width . ', Height: ' . $final_height . '</p>';
					if($this->debug) echo '<p>Old Width: ' . $width_old . ', Old Height: ' . $height_old . '</p>';
				}
				else {
					$final_width = ( $width <= 0 ) ? $width_old : $width;
					$final_height = ( $height <= 0 ) ? $height_old : $height;
				}
			}
			
			if( $this->loadSourceImage() ) {
				
				// This is the resizing/resampling/transparency-preserving magic
				$image_resized = imagecreatetruecolor( $final_width, $final_height );
				if ( ($this->info[2] == IMAGETYPE_GIF) || ($this->info[2] == IMAGETYPE_PNG) ) {
					$transparency = imagecolortransparent($this->image);

					if ($transparency >= 0) {
						$transparent_color  = imagecolorsforindex($this->image, $trnprt_indx);
						$transparency	 = imagecolorallocate($image_resized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
						imagefill($image_resized, 0, 0, $transparency);
						imagecolortransparent($image_resized, $transparency);
					}
					elseif ($this->info[2] == IMAGETYPE_PNG) {
						imagealphablending($image_resized, false);
						$color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);
						imagefill($image_resized, 0, 0, $color);
						imagesavealpha($image_resized, true);
					}
				}
				imagecopyresampled($image_resized, $this->image, 0, 0, 0, 0, $final_width, $final_height, $width_old, $height_old);
				
				if( $this->writeImageToDestination($image_resized) ) {
					if($this->debug) echo '<p>END scale: SUCCESS</p>';
					return TRUE;
				}
			}
			
			if($this->debug) echo '<p>END scale: FAIL</p>';
			return FALSE;
		}
		
		/**
		 * Convenience function to perform a scale, then a crop
		 * 
		 * Assumes a proportial scale that does not fit to inside of area
		 * 
		 * @param number $x_pos The left corner of the crop
		 * @param number $y_pos The top corner of the crop
		 * @param number $width The width of the crop area
		 * @param number $height The height of the crop area
		 */
		function scaleAndCrop($x_pos = CENTER, $y_pos = CENTER, $width, $height) {
			
			if( $this->scale($width, $height, TRUE, FALSE)) {
				
				clearstatcache();
				
				if(!file_exists($this->src_image_path)) {
					if($this->debug) echo '<p>delaying while we wait for image...</p>';
					delay(1);
				}
				
				if( $this->crop($x_pos, $y_pos, $width, $height) ){
					return TRUE;
				}
			}
			return FALSE;
		}
		
		/**
		 * Get the path to the destination image
		 * 
		 * @return string The url to the destination image
		 */
		function getImagePath() {
			return $this->dest_image;
		}
		
		/**
		 * Get the raw image from memory
		 * 
		 * @return image The final image after all operations
		 */
		function getImage() {
			$mime = image_type_to_mime_type($this->info[2]);
			header("Content-type: $mime");
			
			// Writing image according to type to the output destination
			switch ( $this->info[2] ) {
				case IMAGETYPE_GIF:   imagegif($this->image);    break;
				case IMAGETYPE_JPEG:  imagejpeg($this->image);   break;
				case IMAGETYPE_PNG:   imagepng($this->image);    break;
				default: return false;
			}
		}
		
		function getImageCreateFunction() {
			if($this->image_create_func === null) {
				$this->processImageType();
			}
			return $this->image_create_func;
		}
		
		function getImageSaveFunction() {
			if($this->image_save_func === null) {
				$this->processImageType();
			}
			return $this->image_save_func;
		}
		
		function getNewImageExtension() {
			if($this->new_image_ext === null) {
				$this->processImageType();
			}
			return $this->new_image_ext;
		}
		
		function processImageType() {

			//$type = substr(strrchr($this->info[2], '/'), 1);
			$type = $this->info[2];
			
			switch ($type) {
		
				//case 'jpeg':
				case IMAGETYPE_JPEG:
					$this->image_create_func = 'ImageCreateFromJPEG';
					$this->image_save_func = 'ImageJPEG';
					$this->new_image_ext = 'jpg';
					break;

				//case 'png':
				case IMAGETYPE_PNG:
					$this->image_create_func = 'ImageCreateFromPNG';
					$this->image_save_func = 'ImagePNG';
					$this->new_image_ext = 'png';
					break;

				//case 'bmp':
				case IMAGETYPE_BMP:
					$this->image_create_func = 'ImageCreateFromBMP';
					$this->image_save_func = 'ImageBMP';
					$this->new_image_ext = 'bmp';
					break;

				//case 'gif':
				case IMAGETYPE_GIF:
					$this->image_create_func = 'ImageCreateFromGIF';
					$this->image_save_func = 'ImageGIF';
					$this->new_image_ext = 'gif';
					break;

				default:
					$this->image_create_func = 'ImageCreateFromJPEG';
					$this->image_save_func = 'ImageJPEG';
					$this->new_image_ext = 'jpg';
			}
		}
		
		function parseNumber($num, $comparison) {
			if(is_string($num)) {
				if( strpos($num, '%') !== FALSE) {
					$percent = str_replace('%', '', $num);
					//if($this->debug) echo '<p>percent is "' . $percent . '"</p>';
					$num = $comparison / (100/$percent);
					//if($this->debug) echo '<p>Num scaled to "' . $num . '"</p>';
				}
				else if( strpos($num, 'px') !== FALSE) {
					$num = str_replace('px', '', $num);
					//if($this->debug) echo '<p>Num scaled to "' . $num . '"</p>';
				}
			}
			return $num;
		}
	}

