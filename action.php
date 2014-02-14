<?php
/**
 * iReflect Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_reflect extends DokuWiki_Action_Plugin {

	var $functions = null;

	function register(&$controller) {

		if ( empty($_REQUEST['reflect']) ) { return; }
		$controller->register_hook('MEDIA_SENDFILE', 'BEFORE', $this, 'reflect__reflect');
	}

	function reflect__reflect(&$event, $args) {

		if (extension_loaded('gd') == false && !@dl('gd.so')) { return; }

		/* Filename for reflect Image */
		$data = $event->data;
		$ext = empty( $_REQUEST['return_type'] ) ? $this->getConf('return_type') : (in_array( hsc($_REQUEST['return_type']), array('png', 'jpg') ) ? hsc($_REQUEST['return_type']) : $this->getConf('return_type'));

		$data['height'] = $this->getConf('reflect_height');
		if ( empty($_REQUEST['bgc']) ) $_REQUEST['bgc'] = $this->getConf('bgc');
		if ( !empty($_REQUEST['reflect_height']) ) $data['height'] = $_REQUEST['reflect_height'];
		$cacheFile = getCacheName($data['file'],".media.reflect.{$_REQUEST['bgc']}.$ext");

		$mtime = @filemtime($cacheFile); // 0 if not exists
		$cache = $data['cache'];
		
		if( ($mtime == 0) ||           // cache does not exist
			($mtime < time()-$cache)   // 'recache' and cache has expired
		){
			if ( $this->create_reflect_image( $data, $cacheFile, $_REQUEST['bgc'] ) ) {
				$data['orig'] = $data['file'];
				$data['file'] = $cacheFile;
				list($data['ext'],$data['mime'],$data['download']) = mimetype($cacheFile);
				$event->data = $data;
			}
		}
	}
	
	function create_reflect_image( $data, $cache_path, $imagebgcolor=null ) {
		global $conf;

		$input = $data['file'];
		$imagebgcolor = $this->calc_bgcolor($imagebgcolor); 

		//	How big is the image?
		if ( !($image_details = getimagesize($input)) ) { return false; }

		$width = $image_details[0];
		$height = $image_details[1];
		$type = $image_details[2];
		$mime = $image_details['mime'];

		//	height (how tall should the reflection be?)
		if (isset($data['height']) ) {
			$output_height = $data['height'];
			if ( $output_height == 0 ) $output_height = $this->getConf('reflect_height');
		} else {
			//	No height was given, so default to 50% of the source images height
			$output_height = $this->getConf('reflect_height');
		}

		//	Calculate the height of the output image
		if ($output_height < 1) {
			//	The output height is a percentage
			$new_height = $height * $output_height;
		} else {
			//	The output height is a fixed pixel value
			$new_height = $output_height;
		}
		
		
		if (isset($_REQUEST['fade_start']))
		{
			if (strpos($_REQUEST['fade_start'], '%') !== false) {
				$alpha_start = str_replace('%', '', $_REQUEST['fade_start']);
				$alpha_start = (int) (127 * $alpha_start / 100);
			} else {
				$alpha_start = (int) $_REQUEST['fade_start'];
			

				if ($alpha_start < 1 || $alpha_start > 127) {
					$alpha_start = $this->getConf('fade_start');
				}
			}
		} else {
			$alpha_start = $this->getConf('fade_start');
		}

		if (isset($_REQUEST['fade_end']))
		{
			if (strpos($_REQUEST['fade_end'], '%') !== false) {
				$alpha_end = str_replace('%', '', $_REQUEST['fade_end']);
				$alpha_end = (int) (127 * $alpha_end / 100);
			} else {
				$alpha_end = (int) $_REQUEST['fade_end'];

				if ($alpha_end < 1 || $alpha_end > 127) {
					$alpha_end = $this->getConf('fade_end');
				}
			}
		} else {
			$alpha_end = $this->getConf('fade_end');
		}

		$alpha_start = 127 - $alpha_start;
		$alpha_end = 127 - $alpha_end;

		//	Detect the source image format - only GIF, JPEG and PNG are supported. If you need more, extend this yourself.
		switch ($type)
		{
			case 1://	GIF
						$source = imagecreatefromgif($input); break;
			case 2://	JPG
						$source = imagecreatefromjpeg($input); break;
			case 3://	PNG
						$source = imagecreatefrompng($input); break;
			default:	return false;
		}

		/* ----------------------------------------------------------------
			Build the reflection image
		---------------------------------------------------------------- */
		$output = $this->imagereflection($source, $width, $height, $new_height, $alpha_start, $alpha_end);

		/* ----------------------------------------------------------------
			Output our final Image
		---------------------------------------------------------------- */
		if ( headers_sent() ) { return false; }

		//	If you'd rather output a JPEG instead of a PNG then pass the parameter 'jpeg' (no value needed) on the querystring
		if ( substr($cache_path, -3) == 'png' ) {
			imagepng($output, $cache_path, intval($conf['jpg_quality'] / 11));
		} else if ( substr($cache_path, -3) == 'jpg' ) {
			/* -----------------------------------------------------------------------
				HACK - Build the reflection image by combining the png output 
				image AND the color background in one new image!
			------------------------------------------------------------------------ */
		
			// Create transparent BG
			$finaloutput = imagecreatetruecolor($width, $height+$new_height);
			$white = imagecolorallocatealpha($finaloutput, $imagebgcolor['red'], $imagebgcolor['green'], $imagebgcolor['blue'], $imagebgcolor['alpha']);
			imagecolortransparent($finaloutput, $white);
			imagefill($finaloutput, 0, 0, $white);
			
			imagecopy($finaloutput, $output, 0, 0, 0, 0, $width, $height+$new_height);
			imagejpeg($finaloutput, $cache_path, intval($conf['jpg_quality']));
		}
		imagedestroy($output);
		return true;
		
		}
	
	function calc_bgcolor( $bgcolor ) {
	
		if ( empty($bgcolor) ) { $bgcolor = $this->getConf('bgc'); }

		//	Does it start with a hash? If so then strip it
		$bgcolor = str_replace('#', '', $bgcolor);

		switch (strlen($bgcolor))
		{
			case 8:
				$red = hexdec(substr($bgcolor, 0, 2));
				$green = hexdec(substr($bgcolor, 2, 2));
				$blue = hexdec(substr($bgcolor, 4, 2));
				$alpha = hexdec(substr($bgcolor, 6, 2));
				break;

			case 6:
				$red = hexdec(substr($bgcolor, 0, 2));
				$green = hexdec(substr($bgcolor, 2, 2));
				$blue = hexdec(substr($bgcolor, 4, 2));
				$alpha = hexdec('00');
				break;
				
			case 4:
				$red = substr($bgcolor, 0, 1);
				$green = substr($bgcolor, 1, 1);
				$blue = substr($bgcolor, 2, 1);
				$alpha = substr($bgcolor, 3, 1);
				$red = hexdec($red . $red);
				$green = hexdec($green . $green);
				$blue = hexdec($blue . $blue);
				$alpha = hexdec($alpha . $alpha);
				break;
				
				
			case 3:
				$red = substr($bgcolor, 0, 1);
				$green = substr($bgcolor, 1, 1);
				$blue = substr($bgcolor, 2, 1);
				$red = hexdec($red . $red);
				$green = hexdec($green . $green);
				$blue = hexdec($blue . $blue);
				$alpha = hexdec('00');
				break;

			default:
				//	Wrong values passed, default to black
				$red = 0;
				$green = 0;
				$blue = 0;
				$alpha = 0;
		}

		$alpha = floor($alpha / 2);
		return array('red' => $red, 'green' => $green, 'blue' => $blue, 'alpha' => $alpha );
	}

	function imagereflection($src_img, $src_width, $src_height, $reflection_height, $alpha_start, $alpha_end) {
		$dest_height = $src_height + $reflection_height;
		$dest_width = $src_width;

		// Create Reflected Object
		$reflected = imagecreatetruecolor($dest_width, $dest_height);
		imagealphablending($reflected, false);
		imagesavealpha($reflected, true);

		// Copy Source
		imagecopy($reflected, $src_img, 0, 0, 0, 0, $src_width, $src_height);
		if ( empty($reflection_height) ) $reflection_height = $src_height / 2;
		
		// Calc alpha width and step
		$alpha_length = abs($alpha_start - $alpha_end);

		// For each Pixel in the reflection area
		for ($y = 1; $y <= $reflection_height; $y++) {

			$pct = $y / $reflection_height;
			for ($x = 0; $x < $dest_width; $x++) {
				// copy pixel from x / $src_height - y to x / $src_height + y
				$rgba = imagecolorat($src_img, $x, $src_height - $y);
				$alpha = ($rgba & 0x7F000000) >> 24;

				//  Get % of alpha
		        if ($alpha_start > $alpha_end) { $alpha_calc = (int) ($alpha_start - ($pct * $alpha_length)); }
		        else { $alpha_calc = (int) ($alpha_start + ($pct * $alpha_length)); }

				$alpha =  max($alpha, $alpha_calc);
				$rgba = imagecolorsforindex($src_img, $rgba);
				$rgba = imagecolorallocatealpha($reflected, $rgba['red'], $rgba['green'], $rgba['blue'], $alpha);
				imagesetpixel($reflected, $x, $src_height + $y - 1, $rgba);
			}
		}

		return $reflected;
	}
}