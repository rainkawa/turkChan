<?php
/*
TurkChan - Merkezi marka / kimlik yapilandirmasi
https://github.com/rainkawa/turkChan

Bu dosya TurkChan'in butun marka kimligini tek bir yerde toplar:
  - Versiyon sistemi
  - Site adi ve logo sistemi
  - Favicon
  - Board isimlendirme sistemi
  - Header / footer yapisi
  - Asset (JS, lock) dosya adlari
  - Proje baglantilari

Burada tanimlanan degerler su dosyalarda kullanilir:
  imgboard.php, inc/defines.php, inc/html.php, inc/functions.php, inc/captcha.php

Board'a ozel ayarlar (TINYIB_* ve $tinyib_* ) settings.php icinde kalir.
*/

if (!defined('TURKCHAN_CONFIG')) {
	define('TURKCHAN_CONFIG', true);

	/* ---------------------------------------------------------------
	 * 1. Versiyon sistemi
	 * --------------------------------------------------------------- */

	// Uretim surumu (semantic versioning: MAJOR.MINOR.PATCH)
	define('TURKCHAN_VERSION', '1.0.0');
	define('TURKCHAN_VERSION_MAJOR', 1);
	define('TURKCHAN_VERSION_MINOR', 0);
	define('TURKCHAN_VERSION_PATCH', 0);

	// Uzerine kurulan kaynak surumu (attribution / lisans uyumu icin)
	define('TURKCHAN_BASED_ON', 'TinyIB');

	/* ---------------------------------------------------------------
	 * 2. Kimlik ve site adi
	 * --------------------------------------------------------------- */

	// Ana marka adi. Sayfa basligi, logo, footer ve yonetim panelinde kullanilir.
	define('TURKCHAN_NAME', 'TurkChan');

	// URL/dosya adlarinda kullanilan kucuk harfli kod ad.
	define('TURKCHAN_SLUG', 'turkchan');

	/* ---------------------------------------------------------------
	 * 3. Logo sistemi
	 *    Oncelik sirasi: settings.php icindeki TINYIB_LOGO
	 *                   > TURKCHAN_LOGO_HTML
	 *                   > TURKCHAN_LOGO_IMAGE
	 *                   > TINYIB_BOARDDESC
	 *                   > TURKCHAN_LOGO_TEXT
	 * --------------------------------------------------------------- */

	// Metin logosu (goruntu ve ozel HTML yoksa kullanilir)
	define('TURKCHAN_LOGO_TEXT', 'TurkChan');

	// Hazir HTML logosu  [' to disable]
	define('TURKCHAN_LOGO_HTML', '');

	// Logo goruntusu yolu  [' to disable]
	define('TURKCHAN_LOGO_IMAGE', '');

	// Logo altinda gosterilen aciklama satiri  ['' to disable]
	define('TURKCHAN_LOGO_SUBTITLE', '');

	/* ---------------------------------------------------------------
	 * 4. Favicon
	 * --------------------------------------------------------------- */

	define('TURKCHAN_FAVICON', 'favicon.ico');

	/* ---------------------------------------------------------------
	 * 5. Board isimlendirme sistemi
	 *
	 *    Kimlik oneki : <slug>            ->  turkchan
	 *    Tablo oneki  : <slug>_<tur>      ->  turkchan_posts, turkchan_reports
	 *                 (settings.php icindeki TINYIB_BOARD uzerinden uretilir)
	 *    Sayfa/dosya  : asagidaki sabitler
	 *
	 *    Not: src/, thumb/ ve res/ dizin adlari kod icinde gomulu olarak
	 *    kullanildigi icin burada tanimlanmaz; degistirilmemelidir.
	 * --------------------------------------------------------------- */

	define('TURKCHAN_BOARD_PREFIX', TURKCHAN_SLUG);
	define('TURKCHAN_BOARD_INDEX', 'index.html');
	define('TURKCHAN_BOARD_CATALOG', 'catalog.html');
	define('TURKCHAN_BOARD_CATALOG_JSON', 'catalog.json');
	define('TURKCHAN_BOARD_THREADS_JSON', 'threads.json');

	/* ---------------------------------------------------------------
	 * 6. Asset dosya adlari ve form kimligi
	 *
	 *    Not: Tarayiciya dogrudan sunulan varliklar (js/turkchan.js,
	 *    inc/captcha.php) PHP sabitlerine erisemedigi icin cookie ve
	 *    session adlari ilgili dosyalarda gomulu olarak tutulur.
	 *    Adlar: turkchan_style, turkchan_password, turkchancaptcha
	 * --------------------------------------------------------------- */

	define('TURKCHAN_JS', 'js/turkchan.js');
	define('TURKCHAN_LOCKFILE', TURKCHAN_SLUG . '.lock');
	define('TURKCHAN_FORM_NAME', TURKCHAN_SLUG);

	/* ---------------------------------------------------------------
	 * 7. Proje baglantilari
	 * --------------------------------------------------------------- */

	define('TURKCHAN_PROJECT_URL', 'https://github.com/rainkawa/turkChan');
	define('TURKCHAN_REPO_URL', 'https://github.com/rainkawa/turkChan');
	define('TURKCHAN_ISSUES_URL', 'https://github.com/rainkawa/turkChan/issues');
	define('TURKCHAN_COMMITS_URL', 'https://github.com/rainkawa/turkChan/commits');
	define('TURKCHAN_README_URL', 'https://github.com/rainkawa/turkChan/blob/main/README.md');
	define('TURKCHAN_UPSTREAM_URL', 'https://codeberg.org/tslocum/tinyib');

	/* ---------------------------------------------------------------
	 * 8. Footer / header yapisi
	 * --------------------------------------------------------------- */

	// Alt bilgi satiri. Bos birakilirsa TURKCHAN_FOOTER_DEFAULT kullanilir.
	define('TURKCHAN_FOOTER_HTML', '');

	define('TURKCHAN_FOOTER_DEFAULT',
		'- <a href="http://www.2chan.net" target="_blank">futaba</a>' .
		' + <a href="http://www.1chan.net" target="_blank">futallaby</a>' .
		' + <a href="' . TURKCHAN_PROJECT_URL . '" target="_blank">' . TURKCHAN_SLUG . '</a> -'
	);
}
