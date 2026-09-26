<?php
/*
TurkChan - Modul yukleyici ve ortak yardimcilar
https://github.com/rainkawa/turkChan

BU DOSYA ARTIK:
  1) Alan modullerini yukleyen modul yükleyicisidir
  2) Hicbir tek alana ait olmayan, board genelinde kullanilan
     yardimci fonksiyonlari barindirir

Alan modulleri:
  inc/auth/auth.php          oturum, giris, rol kontrolu
  inc/moderation/guards.php  CAPTCHA, ban, anahtar kelime, flood, boyut
  inc/moderation/manage.php  staff moderasyon eylemleri
  inc/posts/posts.php        post yasam dongusu ve metin bicimlendirme
  inc/posts/request.php      herkese acik istek eylemleri
  inc/threads/threads.php    thread / index / katalog yeniden uretimi
  inc/threads/manage.php     thread staff eylemleri
  inc/users/users.php        staff hesap kurallari
  inc/users/manage.php       hesap paneli eylemleri
  inc/media/media.php        dosya yukleme
  inc/media/thumbnail.php    thumbnail / video karesi
  inc/media/embed.php        oEmbed
  inc/requests/manage.php    yonetim paneli yonlendiricisi

Sunum katmani inc/html.php, veri katmani inc/database/ ve giris noktasi
imgboard.php bu yukleyiciden sonra gelir.

ONEMLI: Bu dosya geriye donuk uyumluluk icin korunmustur. Bagimli
olan harici kodlar 'inc/functions.php' yolunu kullanmaya devam edebilir.

Yukleme sirasinda moduller arasinda require zinciri yoktur; butun
moduller burada tek seferde yuklenir. Fonksiyonlar birbirini yalnizca
calisma aninda cagirir. Bu sayede dongusel bagimlilik olusmaz.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

$tinyib_modules = array(
	'inc/auth/auth.php',
	'inc/moderation/guards.php',
	'inc/moderation/manage.php',
	'inc/posts/posts.php',
	'inc/posts/request.php',
	'inc/threads/threads.php',
	'inc/threads/manage.php',
	'inc/users/users.php',
	'inc/users/manage.php',
	'inc/media/media.php',
	'inc/media/thumbnail.php',
	'inc/media/embed.php',
	'inc/requests/manage.php',
);
foreach ($tinyib_modules as $tinyib_module) {
	require_once $tinyib_module;
}
unset($tinyib_modules, $tinyib_module);

$multibyte_enabled = function_exists('mb_strlen');

if (!function_exists('array_column')) {
	function array_column($array, $column_name) {
		return array_map(function ($element) use ($column_name) {
			return $element[$column_name];
		}, $array);
	}
}

// lockDatabase obtains an exclusive lock to prevent race conditions when
// accessing the database.
function lockDatabase() {
	if (TINYIB_LOCKFILE == '') {
		return true;
	}
	$fp = fopen(TINYIB_LOCKFILE, 'c+');
	if (!flock($fp, LOCK_EX)) {
		fancyDie('Failed to lock control file.');
	}
	return $fp;
}

function _strlen($string) {
	global $multibyte_enabled;
	if ($multibyte_enabled) {
		return mb_strlen($string);
	}
	return strlen($string);
}

function _strpos($haystack, $needle, $offset=0) {
	global $multibyte_enabled;
	if ($multibyte_enabled) {
		return mb_strpos($haystack, $needle, $offset);
	}
	return strpos($haystack, $needle, $offset);
}

function _substr($string, $start, $length=null) {
	global $multibyte_enabled;
	if ($multibyte_enabled) {
		return mb_substr($string, $start, $length);
	}
	return substr($string, $start, $length);
}

function _substr_count($haystack, $needle) {
	global $multibyte_enabled;
	if ($multibyte_enabled) {
		return mb_substr_count($haystack, $needle);
	}
	return substr_count($haystack, $needle);
}

// hashData hashes a value with the board bcrypt salt. Used for account
// passwords, secure tripcodes and stored IP addresses alike, because
// TINYIB_TRIPSEED is the salt source for all three.
function hashData($data, $force = false) {
	global $bcrypt_salt;
	if (substr($data, 0, 4) == '$2y$' && !$force) {
		return $data;
	}
	return crypt($data, $bcrypt_salt);
}

function cleanString($string) {
	$search = array("&", "<", ">");
	$replace = array("&amp;", "&lt;", "&gt;");

	return str_replace($search, $replace, $string);
}

function cleanQuotes($string) {
	$search = array("'", "\"");
	$replace = array("&apos;", "&quot;");

	return str_replace($search, $replace, $string);
}

function plural($count, $singular, $plural) {
	if ($plural == 's') {
		$plural = $singular . $plural;
	}
	return ($count == 1 ? $singular : $plural);
}

function convertBytes($number) {
	$len = strlen($number);
	if ($len < 4) {
		return sprintf("%dB", $number);
	} elseif ($len <= 6) {
		return sprintf("%0.2fKB", $number / 1024);
	} elseif ($len <= 9) {
		return sprintf("%0.2fMB", $number / 1024 / 1024);
	}

	return sprintf("%0.2fGB", $number / 1024 / 1024 / 1024);
}

// writePage writes a generated page atomically into the res/ staging area.
function writePage($filename, $contents) {
	$tempfile = tempnam('res/', TINYIB_BOARD . 'tmp'); /* Create the temporary file */
	$fp = fopen($tempfile, 'w');
	fwrite($fp, $contents);
	fclose($fp);
	/* If we aren't able to use the rename function, try the alternate method */
	if (!@rename($tempfile, $filename)) {
		copy($tempfile, $filename);
		unlink($tempfile);
	}

	chmod($filename, 0664); /* it was created 0600 */
}

// fixLinksInRes rewrites root-relative asset paths for pages served out of res/.
function fixLinksInRes($html) {
	$search = array(' href="css/', ' src="js/', ' href="src/', ' href="thumb/', ' href="res/', ' href="imgboard.php', ' href="catalog.html', ' href="favicon.ico', 'src="thumb/', 'src="inc/', 'src="sticky.png', 'src="lock.png', ' action="imgboard.php', ' action="catalog.html');
	$replace = array(' href="../css/', ' src="../js/', ' href="../src/', ' href="../thumb/', ' href="../res/', ' href="../imgboard.php', ' href="../catalog.html', ' href="../favicon.ico', 'src="../thumb/', 'src="../inc/', 'src="../sticky.png', 'src="../lock.png', ' action="../imgboard.php', ' action="../catalog.html');

	return str_replace($search, $replace, $html);
}

function strallpos($haystack, $needle, $offset = 0) {
	$result = array();
	for ($i = $offset; $i < _strlen($haystack); $i++) {
		$pos = _strpos($haystack, $needle, $i);
		if ($pos !== False) {
			$offset = $pos;
			if ($offset >= $i) {
				$i = $offset;
				$result[] = $offset;
			}
		}
	}
	return $result;
}

// url_get_contents fetches a remote URL, returning '' on a non-200 response.
function url_get_contents($url) {
	if (!function_exists('curl_init')) {
		return file_get_contents($url);
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$output = curl_exec($ch);
	$responsecode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	if (intval($responsecode) != 200) {
		return '';
	}
	return $output;
}

function formatDate($timestamp) {
	return @strftime(TINYIB_DATEFMT, $timestamp);
}

// remoteAddress returns the posting IP, honouring the Cloudflare header.
function remoteAddress() {
	if (TINYIB_CLOUDFLARE && isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
		return $_SERVER['HTTP_CF_CONNECTING_IP'];
	}
	return $_SERVER['REMOTE_ADDR'];
}

function installedViaGit() {
	return is_dir('.git');
}
