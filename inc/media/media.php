<?php
/*
TurkChan - Dosya yukleme / medya katmani
https://github.com/rainkawa/turkChan

Bu modul yuklenen dosyanin board'a eklenmesini yonetir:

  - Yukleme hatasi analizi (validateFileUpload)
  - MIME tipi ve boyut dogrulamasi (attachFile)
  - Yinelenen dosya kontrolu (checkDuplicateFile)
  - Thumbnail boyutlandirma (thumbnailDimensions)
  - Metadata temizleme (stripMetadata)
  - Dosya + thumbnail + video karesinin post kaydina baglanmasi (attachFile)

Dizin yaslari (src/, thumb/, res/) TinyIB tarafindan gomulu olarak
kullanildigi icin degistirilmemistir. path/rewrite mantigi korunur.
Thumbnail uretimi inc/media/thumbnail.php, embed islemesi
inc/media/embed.php icindedir.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// validateFileUpload converts a PHP upload error code into a readable message.
function validateFileUpload() {
	switch ($_FILES['file']['error']) {
		case UPLOAD_ERR_OK:
			break;
		case UPLOAD_ERR_FORM_SIZE:
			fancyDie(sprintf(__('That file is larger than %s.'), TINYIB_MAXKBDESC));
			break;
		case UPLOAD_ERR_INI_SIZE:
			fancyDie(sprintf(__('The uploaded file exceeds the upload_max_filesize directive (%s) in php.ini.'), ini_get('upload_max_filesize')));
			break;
		case UPLOAD_ERR_PARTIAL:
			fancyDie(__('The uploaded file was only partially uploaded.'));
			break;
		case UPLOAD_ERR_NO_FILE:
			fancyDie(__('No file was uploaded.'));
			break;
		case UPLOAD_ERR_NO_TMP_DIR:
			fancyDie(__('Missing a temporary folder.'));
			break;
		case UPLOAD_ERR_CANT_WRITE:
			fancyDie(__('Failed to write file to disk'));
			break;
		default:
			fancyDie(__('Unable to save the uploaded file.'));
	}
}

// checkDuplicateFile rejects a file that has already been posted.
function checkDuplicateFile($hex) {
	$hexmatches = postsByHex($hex);
	if (count($hexmatches) > 0) {
		foreach ($hexmatches as $hexmatch) {
			fancyDie(sprintf(__('Duplicate file uploaded. That file has already been posted <a href="%s">here</a>.'), 'res/' . (($hexmatch['parent'] == TINYIB_NEWTHREAD) ? $hexmatch['id'] : $hexmatch['parent']) . '.html#' . $hexmatch['id']));
		}
	}
}

// thumbnailDimensions returns the maximum thumbnail size for a post.
function thumbnailDimensions($post) {
	if ($post['parent'] == TINYIB_NEWTHREAD) {
		$max_width = TINYIB_MAXWOP;
		$max_height = TINYIB_MAXHOP;
	} else {
		$max_width = TINYIB_MAXW;
		$max_height = TINYIB_MAXH;
	}
	return ($post['image_width'] > $max_width || $post['image_height'] > $max_height) ? array($max_width, $max_height) : array($post['image_width'], $post['image_height']);
}

// attachFile moves a validated file into src/, creates its thumbnail and
// returns the post record with the file fields filled in.
function attachFile($post, $filepath, $filename, $uploaded, $spoiler) {
	global $tinyib_uploads;

	if (!is_file($filepath) || !is_readable($filepath)) {
		@unlink($filepath);
		fancyDie(__('File transfer failure. Please retry the submission.'));
	}

	$file_mime_split = explode(' ', trim(mime_content_type($filepath)));
	if (count($file_mime_split) > 0) {
		$file_mime = strtolower(array_pop($file_mime_split));
	} else {
		if (!@getimagesize($filepath)) {
			@unlink($filepath);
			fancyDie(__('Failed to read the MIME type and size of the uploaded file. Please retry the submission.'));
		}
		$file_mime = mime_content_type($filepath);
	}
	if (empty($file_mime) || !isset($tinyib_uploads[$file_mime])) {
		fancyDie(supportedFileTypes());
	}

	$file_name_pre = time() . substr(microtime(), 2, 3);
	$file_name = $file_name_pre . '.' . $tinyib_uploads[$file_mime][0];
	$file_src = 'src/' . $file_name;

	if ($uploaded) {
		if (!move_uploaded_file($filepath, $file_src)) {
			fancyDie(__('Could not copy uploaded file.'));
		}
	} else {
		if (!rename($filepath, $file_src)) {
			@unlink($filepath);
			fancyDie(__('Could not copy uploaded file.'));
		}
	}
	$filepath = $file_src;

	$filesize = filesize($filepath);
	if (filesize($filepath) != $filesize) {
		@unlink($filepath);
		fancyDie(__('File transfer failure. Please go back and try again.'));
	} else if (TINYIB_MAXKB > 0 && $filesize > (TINYIB_MAXKB * 1024)) {
		@unlink($filepath);
		fancyDie(sprintf(__('That file is larger than %s.'), TINYIB_MAXKBDESC));
	}

	if (TINYIB_STRIPMETADATA) {
		stripMetadata($filepath);
	}

	$post['file'] = $file_name;
	$post['file_original'] = trim(htmlentities(_substr($filename, 0, 50), ENT_QUOTES));
	$post['file_hex'] = md5_file($filepath);
	$post['file_size'] = $filesize;
	$post['file_size_formatted'] = convertBytes($post['file_size']);
	checkDuplicateFile($post['file_hex']);

	if (in_array($file_mime, array('image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'application/x-shockwave-flash'))) {
		$file_info = getimagesize($file_src);
		$post['image_width'] = $file_info[0] != '' ? $file_info[0] : 0;
		$post['image_height'] = $file_info[1] != '' ? $file_info[1] : 0;
	}

	if (isset($tinyib_uploads[$file_mime][1])) {
		$thumbfile_split = explode('.', $tinyib_uploads[$file_mime][1]);
		$post['thumb'] = $file_name_pre . 's.' . array_pop($thumbfile_split);
		if (!copy($tinyib_uploads[$file_mime][1], 'thumb/' . $post['thumb'])) {
			@unlink($file_src);
			fancyDie(__('Could not create thumbnail.'));
		}
		if ($file_mime == 'application/x-shockwave-flash') {
			addVideoOverlay('thumb/' . $post['thumb']);
		}
	} else if (in_array($file_mime, array('image/jpeg', 'image/pjpeg', 'image/png', 'image/gif'))) {
		$post['thumb'] = $file_name_pre . 's.' . $tinyib_uploads[$file_mime][0];
		list($thumb_maxwidth, $thumb_maxheight) = thumbnailDimensions($post);

		if (!createThumbnail($file_src, 'thumb/' . $post['thumb'], $thumb_maxwidth, $thumb_maxheight, $spoiler)) {
			@unlink($file_src);
			fancyDie(__('Could not create thumbnail.'));
		}
	} else if ($file_mime == 'audio/webm' || $file_mime == 'video/webm' || $file_mime == 'audio/mp4' || $file_mime == 'video/mp4') {
		list($post['image_width'], $post['image_height']) = videoDimensions($file_src);

		if ($post['image_width'] > 0 && $post['image_height'] > 0) {
			list($thumb_maxwidth, $thumb_maxheight) = thumbnailDimensions($post);
			$post['thumb'] = $file_name_pre . 's.jpg';
			ffmpegThumbnail($file_src, 'thumb/' . $post['thumb'], $thumb_maxwidth, $thumb_maxheight);

			$thumb_info = getimagesize('thumb/' . $post['thumb']);
			$post['thumb_width'] = $thumb_info[0];
			$post['thumb_height'] = $thumb_info[1];

			if ($post['thumb_width'] <= 0 || $post['thumb_height'] <= 0) {
				@unlink($file_src);
				@unlink('thumb/' . $post['thumb']);
				fancyDie(__('Sorry, your video appears to be corrupt.'));
			}

			addVideoOverlay('thumb/' . $post['thumb']);
		}

		$duration = videoDuration($file_src);
		if ($duration > 0) {
			$mins = floor(round($duration / 1000) / 60);
			$secs = str_pad(floor(round($duration / 1000) % 60), 2, '0', STR_PAD_LEFT);

			$post['file_original'] = "$mins:$secs" . ($post['file_original'] != '' ? (', ' . $post['file_original']) : '');
		}
	}

	if ($post['thumb'] != '') {
		$thumb_info = getimagesize('thumb/' . $post['thumb']);
		$post['thumb_width'] = $thumb_info[0];
		$post['thumb_height'] = $thumb_info[1];

		if ($post['thumb_width'] <= 0 || $post['thumb_height'] <= 0) {
			@unlink($file_src);
			@unlink('thumb/' . $post['thumb']);
			fancyDie(__('Sorry, your video appears to be corrupt.'));
		}
	}

	return $post;
}

// stripMetadata removes EXIF/metadata from an uploaded file using ExifTool.
function stripMetadata($filename) {
	$discard = '';
	$exit_status = 1;
	exec("exiftool -ver", $discard, $exit_status);
	if ($exit_status != 0) {
		fancyDie('ExifTool is not installed, or the <i>exiftool</i> executable is not in the server\'s $PATH.<br>Install ExifTool, or set TINYIB_STRIPMETADATA to false.');
	}

	$discard = '';
	$exit_status = 1;
	exec("exiftool -All= -overwrite_original_in_place " . escapeshellarg($filename), $discard, $exit_status);
}
