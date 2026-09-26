<?php
/*
TurkChan
https://github.com/rainkawa/turkChan

MIT License

Copyright (c) 2020 Trevor Slocum <trevor@rocket9labs.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

/*
BU DOSYA GIRIS NOKTASIDIR. Yalnizca su sorumluluklari tasir:

  1. Kurulum / oturum baslatma
  2. Yapilandirma yukleme ve dogrulama
  3. Oturum ve ban kontrolu
  4. Istek turunu belirleyip dogru handler'a yonlendirme (action dispatch)
  5. Yonlendirme (response)

Is mantigi alan modullerindedir:
  gonderi  -> inc/posts/request.php
  yonetim  -> inc/requests/manage.php
  veri     -> inc/database/
  HTML     -> inc/html.php

1200 oncesi bu dosya butun gonderi ve yonetim mantigini iceriyordu.
TINYIB_* sabitleri, $tinyib_* degiskenleri ve URL adresleri bilerek
degistirilmedi; harici kodlar bu dosya yolunu kullanmaya devam eder.
*/

error_reporting(E_ALL);
ini_set("display_errors", 1);
session_start();
setcookie(session_name(), session_id(), time() + 2592000);
ob_implicit_flush();
while (ob_get_level() > 0) {
	ob_end_flush();
}

function fancyDie($message, $go_back = 1) {
	$go_back_text = 'Click here to go back';
	if (function_exists('__')) {
		$go_back_text = __('Click here to go back');
	}
	die('<body text="#800000" bgcolor="#FFFFEE" align="center"><br><div style="display: inline-block; background-color: #F0E0D6;font-size: 1.25em;font-family: Tahoma, Geneva, sans-serif;padding: 7px;border: 1px solid #D9BFB7;border-left: none;border-top: none;">' . $message . '</div><br><br>- <a href="javascript:history.go(-' . $go_back . ')">' . $go_back_text . '</a> -</body>');
}

if (!file_exists('settings.php')) {
	fancyDie('Please copy the file settings.default.php to settings.php');
}
require 'settings.php';
require 'inc/config.php';
require 'inc/defines.php';
global $tinyib_capcodes, $tinyib_embeds, $tinyib_hidefields, $tinyib_hidefieldsop;

if (!defined('TINYIB_LOCALE') || TINYIB_LOCALE == '') {
	function __($string) {
		return $string;
	}
} else {
	require 'inc/gettext.php';
}

if ((TINYIB_CAPTCHA === 'hcaptcha' || TINYIB_REPLYCAPTCHA === 'hcaptcha' || TINYIB_MANAGECAPTCHA === 'hcaptcha') && (TINYIB_HCAPTCHA_SITE == '' || TINYIB_HCAPTCHA_SECRET == '')) {
	fancyDie(__('TINYIB_HCAPTCHA_SITE and TINYIB_HCAPTCHA_SECRET  must be configured.'));
}

if ((TINYIB_CAPTCHA === 'recaptcha' || TINYIB_REPLYCAPTCHA === 'recaptcha' || TINYIB_MANAGECAPTCHA === 'recaptcha') && (TINYIB_RECAPTCHA_SITE == '' || TINYIB_RECAPTCHA_SECRET == '')) {
	fancyDie(__('TINYIB_RECAPTCHA_SITE and TINYIB_RECAPTCHA_SECRET  must be configured.'));
}

if (TINYIB_TIMEZONE != '') {
	date_default_timezone_set(TINYIB_TIMEZONE);
}

if (TINYIB_TRIPSEED == '') {
	fancyDie(__('TINYIB_TRIPSEED must be configured.'));
}

$bcrypt_salt = '$2y$12$' . str_pad(str_replace('=', '/', str_replace('+', '.', substr(base64_encode(TINYIB_TRIPSEED), 0, 22))), 22, '/');

$database_modes = array('flatfile', 'mysql', 'mysqli', 'sqlite', 'sqlite3', 'pdo');
if (!in_array(TINYIB_DBMODE, $database_modes)) {
	fancyDie(__('Unknown database mode specified.'));
}

/* -------------------------------------------------------------------
 * Sema tanimlari
 *
 * Bu blok yalnizca CREATE TABLE ifadelerini hazirlar; surucu
 * dosyalari (inc/database/<mod>.php) bunlari kurulurken kullanir.
 * Tablo yapisi TinyIB ile birebir aynidir, degistirilmemistir.
 * ------------------------------------------------------------------- */

$schema_mode = (TINYIB_DBMIGRATE) ? TINYIB_DBMIGRATE : TINYIB_DBMODE;
if ($schema_mode == 'pdo' && TINYIB_DBDRIVER == 'pgsql') {
	$accounts_sql = 'CREATE TABLE "' . TINYIB_DBACCOUNTS . '" (
		"id" bigserial NOT NULL,
		"username" varchar(255) NOT NULL,
		"password" text NOT NULL,
		"role" integer NOT NULL,
		"lastactive" integer NOT NULL,
		PRIMARY KEY	("id")
	);';

	$bans_sql = 'CREATE TABLE "' . TINYIB_DBBANS . '" (
		"id" bigserial NOT NULL,
		"ip" varchar(255) NOT NULL,
		"timestamp" integer NOT NULL,
		"expire" integer NOT NULL,
		"reason" text NOT NULL,
		PRIMARY KEY	("id")
	);
	CREATE INDEX ON "' . TINYIB_DBBANS . '"("ip");';

	$keywords_sql = 'CREATE TABLE "' . TINYIB_DBKEYWORDS . '" (
		"id" bigserial NOT NULL,
		"text" varchar(255) NOT NULL,
		"action" varchar(255) NOT NULL,
		PRIMARY KEY	("id")
	);';

	$logs_sql = 'CREATE TABLE "' . TINYIB_DBLOGS . '" (
		"id" bigserial NOT NULL,
		"timestamp" integer NOT NULL,
		"account" integer NOT NULL,
		"message" text NOT NULL,
		PRIMARY KEY	("id")
	);
	CREATE INDEX ON "' . TINYIB_DBLOGS . '"("account");';

	$posts_sql = 'CREATE TABLE "' . TINYIB_DBPOSTS . '" (
		"id" bigserial NOT NULL,
		"parent" integer NOT NULL,
		"timestamp" integer NOT NULL,
		"bumped" integer NOT NULL,
		"ip" varchar(255) NOT NULL,
		"name" varchar(75) NOT NULL,
		"tripcode" varchar(24) NOT NULL,
		"email" varchar(75) NOT NULL,
		"nameblock" varchar(255) NOT NULL,
		"subject" varchar(75) NOT NULL,
		"message" text NOT NULL,
		"password" varchar(255) NOT NULL,
		"file" text NOT NULL,
		"file_hex" varchar(75) NOT NULL,
		"file_original" varchar(255) NOT NULL,
		"file_size" integer NOT NULL default \'0\',
		"file_size_formatted" varchar(75) NOT NULL,
		"image_width" smallint NOT NULL default \'0\',
		"image_height" smallint NOT NULL default \'0\',
		"thumb" varchar(255) NOT NULL,
		"thumb_width" smallint NOT NULL default \'0\',
		"thumb_height" smallint NOT NULL default \'0\',
		"moderated" smallint NOT NULL default \'1\',
		"stickied" smallint NOT NULL default \'0\',
		"locked" smallint NOT NULL default \'0\',
		PRIMARY KEY	("id")
	);
	CREATE INDEX ON "' . TINYIB_DBPOSTS . '"("parent");
	CREATE INDEX ON "' . TINYIB_DBPOSTS . '"("bumped");
	CREATE INDEX ON "' . TINYIB_DBPOSTS . '"("stickied");
	CREATE INDEX ON "' . TINYIB_DBPOSTS . '"("moderated");';

	$reports_sql = 'CREATE TABLE "' . TINYIB_DBREPORTS . '" (
		"id" bigserial NOT NULL,
		"ip" varchar(255) NOT NULL,
		"post" integer NOT NULL,
		PRIMARY KEY	("id")
	);';
} else {
	$accounts_sql = "CREATE TABLE `" . TINYIB_DBACCOUNTS . "` (
		`id` mediumint(7) unsigned NOT NULL auto_increment,
		`username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`role` mediumint(7) unsigned NOT NULL,
		`lastactive` int(20) unsigned NOT NULL,
		PRIMARY KEY	(`id`)
	)";

	$bans_sql = "CREATE TABLE `" . TINYIB_DBBANS . "` (
		`id` mediumint(7) unsigned NOT NULL auto_increment,
		`ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`timestamp` int(20) NOT NULL,
		`expire` int(20) NOT NULL,
		`reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		PRIMARY KEY	(`id`),
		KEY `ip` (`ip`)
	)";

	$keywords_sql = "CREATE TABLE `" . TINYIB_DBKEYWORDS . "` (
		`id` mediumint(7) unsigned NOT NULL auto_increment,
		`text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		PRIMARY KEY	(`id`)
	)";

	$logs_sql = "CREATE TABLE `" . TINYIB_DBLOGS . "` (
		`id` mediumint(7) unsigned NOT NULL auto_increment,
		`timestamp` int(20),
		`account` mediumint(7) unsigned NOT NULL,
		`message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		PRIMARY KEY	(`id`),
		KEY `account` (`account`)
	)";

	$posts_sql = "CREATE TABLE `" . TINYIB_DBPOSTS . "` (
		`id` mediumint(7) unsigned NOT NULL auto_increment,
		`parent` mediumint(7) unsigned NOT NULL,
		`timestamp` int(20) NOT NULL,
		`bumped` int(20) NOT NULL,
		`ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`name` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`tripcode` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`email` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`nameblock` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`subject` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`file` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`file_hex` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`file_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`file_size` int(20) unsigned NOT NULL default '0',
		`file_size_formatted` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`image_width` smallint(5) unsigned NOT NULL default '0',
		`image_height` smallint(5) unsigned NOT NULL default '0',
		`thumb` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`thumb_width` smallint(5) unsigned NOT NULL default '0',
		`thumb_height` smallint(5) unsigned NOT NULL default '0',
		`stickied` tinyint(1) NOT NULL default '0',
		`moderated` tinyint(1) NOT NULL default '1',
		PRIMARY KEY	(`id`),
		KEY `parent` (`parent`),
		KEY `bumped` (`bumped`),
		KEY `stickied` (`stickied`),
		KEY `moderated` (`moderated`)
	)";

	$reports_sql = "CREATE TABLE `" . TINYIB_DBREPORTS . "` (
		`id` mediumint(7) unsigned NOT NULL auto_increment,
		`ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		`post` int(20) NOT NULL,
		PRIMARY KEY	(`id`)
	)";
}

// Check directories are writable by the script
$writedirs = array('res', 'src', 'thumb');
if (TINYIB_DBMODE == 'flatfile') {
	$writedirs[] = 'inc/database/flatfile';
}
foreach ($writedirs as $dir) {
	if (!is_writable($dir)) {
		fancyDie(sprintf(__("Directory '%s' can not be written to.  Please modify its permissions."), $dir));
	}
}

/* -------------------------------------------------------------------
 * Katman yukleme
 *
 * inc/functions.php  -> alan modulleri + ortak yardimcilar
 * inc/html.php       -> sunum
 * inc/database/*     -> veri katmani (secilen surucu)
 *
 * inc/database/database.php, calisma aninda accountByUsername()
 * cagirdigi icin en son yuklenir.
 * ------------------------------------------------------------------- */

$includes = array('inc/functions.php', 'inc/html.php', 'inc/database/' . TINYIB_DBMODE . '_link.php', 'inc/database/' . TINYIB_DBMODE . '.php', 'inc/database/database.php');
foreach ($includes as $include) {
	require $include;
}

list($account, $loggedin, $isadmin) = manageCheckLogIn(false);

if (!$loggedin) {
	checkBanned();
}

$redirect = true;

// Check if the request is to make a post
if (!isset($_GET['delete']) && !isset($_GET['manage']) && (isset($_POST['name']) || isset($_POST['email']) || isset($_POST['subject']) || isset($_POST['message']) || isset($_POST['file']) || isset($_POST['embed']) || isset($_POST['password']))) {
	handlePostSubmission();
// Check if the request is to preview a post
} elseif (isset($_GET['preview']) && !isset($_GET['manage'])) {
	handlePostPreview();
// Check if the request is to auto-refresh a thread
} elseif (isset($_GET['posts']) && !isset($_GET['manage'])) {
	handleThreadRefresh();
// Check if the request is to report a post
} elseif (isset($_GET['report']) && !isset($_GET['manage'])) {
	handlePostReport();
// Check if the request is to delete a post and/or its associated image
} elseif (isset($_GET['delete']) && !isset($_GET['manage'])) {
	handlePostDelete();
// Check if the request is to access the management area
} elseif (isset($_GET['manage'])) {
	$lock = lockDatabase();

	handleManageRequest();
} elseif (!file_exists(TINYIB_INDEX) || countThreads() == 0) {
	rebuildIndexes();
}

if ($redirect) {
	echo '--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="' . (isset($slow_redirect) ? '3' : '0') . ';url=' . (is_string($redirect) ? $redirect : TINYIB_INDEX) . '">';
}
