<?php

/**
 * Renders an image to the browser
 * see readme for examples.
 */
include('config.php');
include('imageLibrary.php');
include('unsharpen_mask.php');

$debug = false;

// Establish the location of saved/cached images.
$tmp = '/tmp/';

// Create an images directory within the cache dir.
if(!is_dir($tmp . 'images')) {
	mkdir($tmp . 'images');
	$tmp = $tmp . 'images/';
}

// If we successfully created an images folder, add it to path.
if(is_dir($tmp . 'images')) { $tmp = $tmp . 'images/'; }

// Only continue if the url contains a valid image url.
if(isset($_REQUEST['i'])) {

	$image = new Image();

	// enable debug?
	if(isset($_REQUEST['debug'])) { $image->debug = $debug = TRUE; }

	// Find the parts of the image
	$path_parts = pathinfo($_REQUEST['i']);
	$path_parts = fixPathParts($path_parts);

	if($debug) echo '<p>path_parts: <pre>' . var_export($path_parts, true) . '</pre>';

	$sourceImagePath = '/' . $path_parts['dirname'] . '/' . $path_parts['basename'];

	if($debug) echo '<p>sourceImagePath = ' . $sourceImagePath . '</p>';

	$image->open($sourceImagePath);

	// Set the target width and height. First grab the original image
	// values. If the url specifies overrides, then use them.
	$width = $image->original_width;
	if( isset($_REQUEST['w']) ) {
		$width = $_REQUEST['w'];
	}

	$height = $image->original_height;
	if( isset($_REQUEST['h']) ) {
		$height = $_REQUEST['h'];
	}

	// If the target width/height is smaller than original, prepare to process image
	if( $width < $image->original_width || $height < $image->original_height ) {

		// Create the new image's filename. This will be the full path to the image,
		// separated by dots, so something like :
		//     images.JEWELRY.Primary000007_AK-E03-14W_w258_h258.sharpen.jpg
		$tmp_filename = str_replace('/', '.', $path_parts['dirname']) . $path_parts['filename'] . '_w' . $width . '_h' . $height;
		if(isset($_REQUEST['sharpen'])) { $tmp_filename .= '.sharpen'; }
		$tmp_filename .= '.' . $path_parts['extension'];
			
		// If the file modification time of the original is NEWER than the thumbnail
		// OR the numbnail does not exist then process image.
		if(
		!file_exists($tmp . $tmp_filename)
		or ( filemtime($tmp . $tmp_filename) - filemtime( $image->src_image_path ) < 0 )
		or ( isset($_REQUEST['nocache']))
		){
			if($debug) echo '<p>will process thumbnail</p>';

			// Set the save destination for the processed image
			$image->setSaveDestination( $tmp . $tmp_filename );

			// Crop and scale the image
			$image->scale($width, $height, TRUE, FALSE);
			$image->crop(CENTER, CENTER, $width, $height);

			// Sharpen the image? Default = false.
			if(isset($_REQUEST['sharpen'])) { $image->sharpen(); }

		}
		// The processed image already exists in cache, so load it.
		else {
			$image = new Image_();
			$image->open($tmp . $tmp_filename);
		}
	}

	// For debugging we echo the path to the processed image.
	if($debug) echo $image->getImagePath();
	// For production version we echo the real image data.
	else echo $image->getImage();

	return;
}

function fixPathParts($path_parts) {
	$path = $path_parts['dirname'];
	if(strpos($path, DOMAIN) !== false) {
		$path = str_replace(DOMAIN, '', $path);
		$path_parts['dirname'] = $path;
	}
	return $path_parts;
}


