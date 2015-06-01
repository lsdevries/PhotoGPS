<?php

//
// A PHP script to copy location info from one photo to another taken at a times close to each other. 
// Nice if you're traveling with a high quality camera without GPS and a camera phone and want location data
// added to all your photos.
//

define( 'GPS_TAG', '-GPS*' );

echo 'Copy photo GPS tags to photos missing GPS tags, taken at a time close to each other.' . PHP_EOL;

//
// get command line options and output available options
// 
$options = [
	'help'      => 'h',
	'recursive' => 'r',
	'delete_originals' => 'd'
];
$args = getopt( implode( '', $options ), array_keys( $options ) );
$showHelp = isset( $args[ 'h' ] );
$recursive = isset( $args[ 'r' ] );
$deleteOriginals = isset( $args[ 'd' ] );
$pathIsMissing = count( $argv ) != 2;
if ( $showHelp || $pathIsMissing ) {
	echo implode( PHP_EOL, [
			'Usage: php photogps [OPTIONS] <PATH>',
			'Option		GNU long option		Meaning',
			'-h		--help			Show this message',
			'-r		--recursive		Process all sub folders',
			'-d		--delete_originals	Delete originals',
		] ) . PHP_EOL . PHP_EOL;
	exit;
}

//
// Get photos with creation timestamps
// 

$photoPath = get_photo_path();

echo "Getting photo timestamps in '$photoPath' ..." . PHP_EOL;
$photos = get_photos_in_path( $photoPath, $recursive );
if ( !count( $photos ) ) {
	die ( "Error: No photos found in $photoPath" . PHP_EOL . PHP_EOL );
}

//
// Filter photos with location info
//

echo 'Getting photos with GPS tags...' . PHP_EOL;
$photosWithGpsTag = get_photos_with_gps_tag( $photos );

//
// Add location info to photo's missing that
//

echo 'Adding GPS tags to the other photos...' . PHP_EOL;
$updated = 0;
foreach ( $photos as $photoPath => $ts ) {

	if ( !array_key_exists( $photoPath, $photosWithGpsTag ) ) {

		echo "$photoPath (" . date( 'Y-n-j H:i:s', $ts ) . ") -> ";

		$closestPhotoWithGpsTag = get_closest_photo_with_gps_tags( $photosWithGpsTag, $ts );

		if ( !is_null( $closestPhotoWithGpsTag ) ) {

			echo $closestPhotoWithGpsTag;

			copy_gps_tags( $closestPhotoWithGpsTag, $photoPath );

			$updated++;
		}
		echo PHP_EOL;
	}
}

echo "$updated photo(s) processed." . PHP_EOL;
delete_originals_or_print_command($photoPath, $deleteOriginals);
exit;




/**
 * Get photo path from current dir or command line arg
 *
 * @return string path
 * @throws Exception
 */
function get_photo_path()
{
	global $argv;
	$_argv = array_reverse( $argv );
	$path = (string) reset( $_argv );

	if ( substr( $path, 0, 1 ) != '/' ) {

		$currentDir = null;
		if ( !isset( $_SERVER[ 'PWD' ] ) ) {
			throw new Exception( "Error: Can't determine current dir." );
		}
		$currentDir = $_SERVER[ 'PWD' ];
		$path = $currentDir . '/' . $path;
		if ( !is_dir( $path ) ) {
			throw new Exception( "Error: $path is not a directory." );
		}

	}

	return $path;
}

/**
 * Get array containing all photo's in path with it's creation timestamp
 *
 * @param $path
 * @param bool $recursive
 * @return array
 */
function get_photos_in_path($path, $recursive = false)
{
	$photos = [ ];

	if ( $handle = opendir( $path ) ) {

		while ( false !== ( $entry = readdir( $handle ) ) ) {

			if ( !in_array( $entry, [ '.', '..' ] ) ) {
				$photoPath = $path . '/' . $entry;

				if ( is_dir( $photoPath ) ) {

					if ( $recursive ) {
						foreach ( get_photos_in_path( $photoPath ) as $_photoPath => $ts ) {
							$photos [ $_photoPath ] = $ts;
						}
					}
				} else {

					$ts = exif_get_create_timestamp( $photoPath );
					if ( !is_null( $ts ) ) {
						$photos[ $photoPath ] = $ts;
					}
				}
			}
		}

		closedir( $handle );
	}
	asort( $photos );

	return $photos;
}

/**
 * get photos in array containing location info
 *
 * @param array $photos
 * @return array
 */
function get_photos_with_gps_tag(array $photos)
{
	$photosWithGpsTag = [ ];
	foreach ( $photos as $photoPath => $ts ) {
		if ( exif_has_gps_tags( $photoPath ) ) {
			$photosWithGpsTag[ $photoPath ] = $ts;
		}
	}

	return $photosWithGpsTag;
}

/**
 * Find photo filename with location info taken near the given timestamp 
 *
 * @param array $photosWithGpsTag
 * @param string $ts
 * @return null|string
 */
function get_closest_photo_with_gps_tags(array $photosWithGpsTag, $ts)
{
	$diff = null;
	$closestPhotoWithGpsTag = null;
	foreach ( $photosWithGpsTag as $photoWithGpsTag => $gpsTagTs ) {
		$newTsDiff = abs( $ts - $gpsTagTs );
		if ( is_null( $diff ) || $newTsDiff < $diff ) {
			$diff = $newTsDiff;
			$closestPhotoWithGpsTag = $photoWithGpsTag; // . "(" . date('Y-n-j H:i:s', $gpsTagTs) . ")";
		}
	}

	return $closestPhotoWithGpsTag;
}

/**
 * Check if photo has location info
 *
 * @param string $filename
 * @return bool
 */
function exif_has_gps_tags($filename)
{
	$command = "exiftool " . GPS_TAG . " '$filename'";
	exec( $command, $output );
	if ( !count( $output ) ) {
		return false;
	}

	return true;
}

/**
 * Copy location info from one photo to another
 *
 * @param string $sourcePhotoPath
 * @param string $destinationPhotoPath
 * @throws Exception
 */
function copy_gps_tags($sourcePhotoPath, $destinationPhotoPath)
{
	if ( !exif_has_gps_tags( $sourcePhotoPath ) ) {
		throw new Exception( "Error: $sourcePhotoPath file has no GPS tags." );
	}

	$command = "exiftool -tagsFromFile '$sourcePhotoPath' " . GPS_TAG . " '$destinationPhotoPath'";
	exec( $command, $output );

	print_exec_output($output);
}

/**
 * Get creation timestamp from photo
 *
 * @param $path
 * @return int|null
 */
function exif_get_create_timestamp($path)
{
	$command = "exiftool -CreateDate '$path'";
	exec( $command, $output );

	if ( !count( $output ) ) {
		return null;
	}

	if ( !preg_match( '/([0-9]{4}.*)/', $output[ 0 ], $matches ) ) {
		return null;
	}

	return strtotime( $matches[ 1 ] );
}

/**
 * Display or execute command to delete photo backup 
 *
 * @param string $photoPath
 * @param bool $deleteOriginals
 */
function delete_originals_or_print_command($photoPath, $deleteOriginals = false)
{
	$deleteOriginalsCommand = "exiftool -r -delete_original '$photoPath/*'";
	if ( $deleteOriginals ) {
		exec( $deleteOriginals, $output );
		print_exec_output( $output );
		return;
	}

	echo "To delete originals, use: $deleteOriginalsCommand" . PHP_EOL;
}


/**
 * Print exec command output
 *
 * @param array $output
 */
function print_exec_output(array $output)
{
	foreach ( $output as $line ) {
		echo $line . PHP_EOL;
	}
}




