<?php
/*
TurkChan - Thumbnail / video kare uretimi
https://github.com/rainkawa/turkChan

TINYIB_THUMBNAIL ayarina gore uc arka uc uygulama desteklenir:
  - 'gd'          : PHP GD uzantisi
  - 'ffmpeg'      : ffmpeg/ffprobe komutlari
  - 'imagemagick' : convert komutu

Dosya yollari src/ ve thumb/ dizinleri TinyIB tarafindan gomulu olarak
kullanildigi icin burada degistirilmemistir.

fastimagecopyresampled fonksiyonu FreeRingers.net'e ait FreeRingers
uygulamasindan alinmis, serbest kullanilabilir kod olarak korunmaktadir.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// videoDimensions returns the pixel dimensions of a video file via ffprobe.
function videoDimensions($file_location) {
	$discard = '';
	$exit_status = 1;
	exec("ffprobe -version", $discard, $exit_status);
	if ($exit_status != 0) {
		fancyDie('FFMPEG is not installed, or the commands ffmpeg and ffprobe are not in the server\'s $PATH.<br>Install FFMPEG, or set TINYIB_THUMBNAIL to \'gd\' or \'imagemagick\'.');
	}

	$dimensions = '';
	$exit_status = 1;
	exec("ffprobe -hide_banner -loglevel error -of csv=p=0 -select_streams v -show_entries stream=width,height $file_location", $dimensions, $exit_status);
	if ($exit_status != 0) {
		return array(0, 0);
	}
	if (is_array($dimensions)) {
		$dimensions = $dimensions[0];
	}
	$split = explode(',', $dimensions);
	if (count($split) != 2) {
		return array(0, 0);
	}
	return array(intval($split[0]), intval($split[1]));
}

// videoDuration returns the duration of a video file in seconds.
function videoDuration($file_location) {
	$discard = '';
	$exit_status = 1;
	exec("ffprobe -version", $discard, $exit_status);
	if ($exit_status != 0) {
		fancyDie('FFMPEG is not installed, or the commands ffmpeg and ffprobe are not in the server\'s $PATH.<br>Install FFMPEG, or set TINYIB_THUMBNAIL to \'gd\' or \'imagemagick\'.');
	}

	$duration = '';
	$exit_status = 1;
	exec("ffprobe -hide_banner -loglevel error -of csv=p=0 -show_entries format=duration $file_location", $duration, $exit_status);
	if ($exit_status != 0) {
		return 0;
	}
	if (is_array($duration)) {
		$duration = $duration[0];
	}
	return floatval($duration);
}

// ffmpegThumbnail extracts a single scaled frame from a video.
function ffmpegThumbnail($file_location, $thumb_location, $new_w, $new_h) {
	$discard = '';
	$exit_status = 1;
	exec("ffmpeg -version", $discard, $exit_status);
	if ($exit_status != 0) {
		fancyDie('FFMPEG is not installed, or the commands ffmpeg and ffprobe are not in the server\'s $PATH.<br>Install FFMPEG, or set TINYIB_THUMBNAIL to \'gd\' or \'imagemagick\'.');
	}

	$quarter = videoDuration($file_location) / 4;

	$exit_status = 1;
	exec("ffmpeg -hide_banner -loglevel error -ss $quarter -i $file_location -frames:v 1 -vf scale=w=$new_w:h=$new_h:force_original_aspect_ratio=decrease $thumb_location", $discard, $exit_status);
	if ($exit_status != 0) {
		return false;
	}
}

// createThumbnail generates a thumbnail using the configured backend.
function createThumbnail($file_location, $thumb_location, $new_w, $new_h, $spoiler) {
	$system = explode(".", $thumb_location);
	$system = array_reverse($system);
	if (TINYIB_THUMBNAIL == 'gd' || (TINYIB_THUMBNAIL == 'ffmpeg' && preg_match("/jpg|jpeg/", $system[0]))) {
		if (preg_match("/jpg|jpeg/", $system[0])) {
			$src_img = imagecreatefromjpeg($file_location);
		} else if (preg_match("/png/", $system[0])) {
			$src_img = imagecreatefrompng($file_location);
		} else if (preg_match("/gif/", $system[0])) {
			$src_img = imagecreatefromgif($file_location);
		} else {
			return false;
		}

		if (!$src_img) {
			fancyDie(__('Unable to read the uploaded file while creating its thumbnail. A common cause for this is an incorrect extension when the file is actually of a different type.'));
		}

		$old_x = imageSX($src_img);
		$old_y = imageSY($src_img);
		$percent = ($old_x > $old_y) ? ($new_w / $old_x) : ($new_h / $old_y);
		$thumb_w = round($old_x * $percent);
		$thumb_h = round($old_y * $percent);

		$dst_img = imagecreatetruecolor($thumb_w, $thumb_h);
		if (preg_match("/png/", $system[0]) && imagepng($src_img, $thumb_location)) {
			imagealphablending($dst_img, false);
			imagesavealpha($dst_img, true);

			$color = imagecolorallocatealpha($dst_img, 0, 0, 0, 0);
			imagefilledrectangle($dst_img, 0, 0, $thumb_w, $thumb_h, $color);
			imagecolortransparent($dst_img, $color);

			imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y);
		} else {
			fastimagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y);
		}

		if (preg_match("/png/", $system[0])) {
			if (!imagepng($dst_img, $thumb_location)) {
				return false;
			}
		} else if (preg_match("/jpg|jpeg/", $system[0])) {
			if (!imagejpeg($dst_img, $thumb_location, 70)) {
				return false;
			}
		} else if (preg_match("/gif/", $system[0])) {
			if (!imagegif($dst_img, $thumb_location)) {
				return false;
			}
		}

		imagedestroy($dst_img);
		imagedestroy($src_img);
	} else if (TINYIB_THUMBNAIL == 'ffmpeg') {
		ffmpegThumbnail($file_location, $thumb_location, $new_w, $new_h);
	} else { // ImageMagick
		$discard = '';

		$exit_status = 1;
		exec("convert -version", $discard, $exit_status);
		if ($exit_status != 0) {
			fancyDie('ImageMagick is not installed, or the convert command is not in the server\'s $PATH.<br>Install ImageMagick, or set TINYIB_THUMBNAIL to \'gd\' or \'ffmpeg\'.');
		}

		$exit_status = 1;
		exec("convert $file_location -auto-orient -thumbnail '" . $new_w . "x" . $new_h . "' -coalesce -layers OptimizeFrame -depth 4 -type palettealpha $thumb_location", $discard, $exit_status);

		if ($exit_status != 0) {
			return false;
		}
	}

	if (!$spoiler) {
		return true;
	}

	if (preg_match("/jpg|jpeg/", $system[0])) {
		$src_img = imagecreatefromjpeg($thumb_location);
	} else if (preg_match("/png/", $system[0])) {
		$src_img = imagecreatefrompng($thumb_location);
	} else if (preg_match("/gif/", $system[0])) {
		$src_img = imagecreatefromgif($thumb_location);
	} else {
		return true;
	}

	if (!$src_img) {
		fancyDie(__('Unable to read the uploaded file while creating its thumbnail. A common cause for this is an incorrect extension when the file is actually of a different type.'));
	}

	$gaussian = array(array(1.0, 2.0, 1.0), array(2.0, 4.0, 2.0), array(1.0, 2.0, 1.0));
	for ($x = 1; $x <= 149; $x++) {
		imageconvolution($src_img, $gaussian, 16, 0);
	}

	if (preg_match("/png/", $system[0])) {
		if (!imagepng($src_img, $thumb_location)) {
			return false;
		}
	} else if (preg_match("/jpg|jpeg/", $system[0])) {
		if (!imagejpeg($src_img, $thumb_location, 70)) {
			return false;
		}
	} else if (preg_match("/gif/", $system[0])) {
		if (!imagegif($src_img, $thumb_location)) {
			return false;
		}
	}
	imagedestroy($src_img);
	return true;
}

function fastimagecopyresampled(&$dst_image, &$src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $quality = 3) {
	// Author: Tim Eckel - Date: 12/17/04 - Project: FreeRingers.net - Freely distributable.
	if (empty($src_image) || empty($dst_image)) {
		return false;
	}

	if ($quality <= 1) {
		$temp = imagecreatetruecolor($dst_w + 1, $dst_h + 1);

		imagecopyresized($temp, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w + 1, $dst_h + 1, $src_w, $src_h);
		imagecopyresized($dst_image, $temp, 0, 0, 0, 0, $dst_w, $dst_h, $dst_w, $dst_h);
		imagedestroy($temp);
	} elseif ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h)) {
		$tmp_w = $dst_w * $quality;
		$tmp_h = $dst_h * $quality;
		$temp = imagecreatetruecolor($tmp_w + 1, $tmp_h + 1);

		imagecopyresized($temp, $src_image, $dst_x * $quality, $dst_y * $quality, $src_x, $src_y, $tmp_w + 1, $tmp_h + 1, $src_w, $src_h);
		imagecopyresampled($dst_image, $temp, 0, 0, 0, 0, $dst_w, $dst_h, $tmp_w, $tmp_h);
		imagedestroy($temp);
	} else {
		imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
	}

	return true;
}

// addVideoOverlay composites the play button onto a video thumbnail.
function addVideoOverlay($thumb_location) {
	if (!file_exists('video_overlay.png')) {
		return;
	}

	if (TINYIB_THUMBNAIL == 'gd' || TINYIB_THUMBNAIL == 'ffmpeg') {
		if (substr($thumb_location, -4) == ".jpg") {
			$thumbnail = imagecreatefromjpeg($thumb_location);
		} else {
			$thumbnail = imagecreatefrompng($thumb_location);
		}
		list($width, $height, $type, $attr) = getimagesize($thumb_location);

		$overlay_play = imagecreatefrompng('video_overlay.png');
		imagealphablending($overlay_play, false);
		imagesavealpha($overlay_play, true);
		list($overlay_width, $overlay_height, $overlay_type, $overlay_attr) = getimagesize('video_overlay.png');

		if (substr($thumb_location, -4) == ".png") {
			imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
			imagealphablending($thumbnail, true);
			imagesavealpha($thumbnail, true);
		}

		imagecopy($thumbnail, $overlay_play, ($width / 2) - ($overlay_width / 2), ($height / 2) - ($overlay_height / 2), 0, 0, $overlay_width, $overlay_height);

		if (substr($thumb_location, -4) == ".jpg") {
			imagejpeg($thumbnail, $thumb_location);
		} else {
			imagepng($thumbnail, $thumb_location);
		}
	} else { // imagemagick
		$discard = '';
		$exit_status = 1;
		exec("convert $thumb_location video_overlay.png -gravity center -composite -quality 75 $thumb_location", $discard, $exit_status);
	}
}
