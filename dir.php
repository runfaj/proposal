<?php

/**** NOTE: This script only accepts JPG images ****/

ini_set('memory_limit', '-1');
set_time_limit(60*5);

$path = './images/';
$recs = array();

function image_fix_orientation($filename) {
    /* some cameras add meta data to change orientation. this corrects and removes that metadata */
	if (is_file($filename)) {
		$exif = exif_read_data($filename);

		if (!empty($exif['Orientation'])) {
			switch ($exif['Orientation']) {
				case 3:
					$image = imagerotate($filename, 180, 0);
					break;

				case 6:
					$image = imagerotate($filename, -90, 0);
					break;

				case 8:
					$image = imagerotate($filename, 90, 0);
					break;
			}
			// replicate and overwrite image to remove exif data
			$img = imagecreatefromjpeg ($filename);
			imagejpeg ($img, $filename, 100);
			imagedestroy ($img);
		}
	}
}

function check_resize($filename, $nWidth) {
	/* resizes big pictures to a max width */
	if (is_file($filename)) {
		list($width, $height, $type, $attr) = getimagesize($filename);
		if ($width > 800) {
			$img = imagecreatefromjpeg($filename);
			$w = imagesx( $img );
			$h = imagesy( $img );

			// calculate thumbnail size
			$new_width = $nWidth;
			$new_height = floor( $h * ( $nWidth / $w ) );

			// create a new temporary image
			$tmp_img = imagecreatetruecolor( $new_width, $new_height );

			// copy and resize old image into new image
			imagecopyresized( $tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $w, $h );

			// save thumbnail into a file with highest quality
			imagejpeg( $tmp_img, $filename, 100 );
		}
	}
}

function createThumbs( $pathToImages, $pathToThumbs, $thumbWidth ) {
	/* something to create thumbs of bigger pictures */
	$dir = opendir( $pathToImages );
	while (false !== ($fname = readdir( $dir ))) {
		if ($fname == '.' || $fname == '..') continue;
	
		// modify the original pictures if needed with orientation and resizing
		// the 800 below is the width of the picture
		image_fix_orientation($pathToImages.$fname);
		check_resize($pathToImages.$fname, 800);
	
		if (is_file($pathToImages."thumbs/".$fname)) continue; // skip ones already created
		$ext = pathinfo($fname, PATHINFO_EXTENSION);
		// continue only if this is a JPEG image
		if ( strtolower($ext) == 'jpg' ) {
			$img = imagecreatefromjpeg( "{$pathToImages}{$fname}" );
			$width = imagesx( $img );
			$height = imagesy( $img );

			// calculate thumbnail size
			$new_width = $thumbWidth;
			$new_height = floor( $height * ( $thumbWidth / $width ) );

			// create a new temporary image
			$tmp_img = imagecreatetruecolor( $new_width, $new_height );

			// copy and resize old image into new image
			imagecopyresized( $tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height );

			// save thumbnail into a file with highest quality
			imagejpeg( $tmp_img, "{$pathToThumbs}{$fname}", 100 );
		}
	}
	// close the directory
	closedir( $dir );
}

// create any thumbnails for newly added pictures
createThumbs($path,$path."thumbs/",100);

// start getting image data to return to the page
if (is_dir($path)) {
	// use scandir vs readdir because it reads alphabetically
	$cdir = scandir($path); 
	foreach ($cdir as $entry) {
		if ($entry == '.' || $entry == '..') continue;
		$entry = $path . $entry;
		if (is_file($entry)) {
			$filetype = pathinfo($entry, PATHINFO_EXTENSION);
			$rec = array(
				"path" 		=> $entry,
				"name" 		=> basename($entry),
				"filetype" 	=> $filetype,
				"thumb"		=> $path."thumbs/".basename($entry)
			);
			array_push($recs, $rec);
		}
	}
}

// build the json result and send it
$result = array();
$success = true;
if (!empty($errorText)) {
	$success = false;
	$result['error'] 	= $errorText;
} else {
	$result['total'] 	= count($recs);
	$result['payload']	= $recs;
}
$result['success'] = $success;
$result = json_encode($result);

echo $result;