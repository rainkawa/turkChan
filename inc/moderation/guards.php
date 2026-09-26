<?php
/*
TurkChan - Gonderi koruma katmani (request guards)
https://github.com/rainkawa/turkChan

Bu modul, bir istegin veritabanina yazilmadan once gecmesi gereken
anti-abuse ve dogrulama kurallarini icerir:

  - CAPTCHA dogrulamasi (simple / hcaptcha / recaptcha)
  - IP ban kontrolu
  - Anahtar kelime filtreleme
  - Flood (hizli gonderi) korumasi
  - Mesaj boyutu siniri
  - Rapor sayisiyla otomatik gizleme (autohide)

Kural: bu katman veritabanina yazar, HTML uretmez.
Gorsel cikti ureten yardimcilar fancyDie() uzerinden hata sayfasi dondurur;
bu TinyIB'nin mevcut davranisidir ve bilerek korunmustur.

Bu modul bir sey yazmaz/guncellemez; yalnizca okur ve karar verir
(insertBan gibi yazma islemleri iceren anahtar kelime ban akisi
imgboard.php icindeki gonderi akisinda calisir).
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// checkCAPTCHA validates the configured CAPTCHA for the given mode.
function checkCAPTCHA($mode) {
	if ($mode === 'hcaptcha') {
		$captcha = isset($_POST['h-captcha-response']) ? $_POST['h-captcha-response'] : '';
		if ($captcha == '') {
			fancyDie('Failed CAPTCHA. Reason:<br>Please click the checkbox labeled "I am human".');
		}

		$data = array(
			'secret' => TINYIB_HCAPTCHA_SECRET,
			'response' => $captcha
		);
		$verify = curl_init();
		curl_setopt($verify, CURLOPT_URL, "https://hcaptcha.com/siteverify");
		curl_setopt($verify, CURLOPT_POST, true);
		curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
		$verifyResponse = curl_exec($verify);
		$responseData = json_decode($verifyResponse);
		if (!isset($responseData->success) || !$responseData->success) {
			fancyDie('Failed CAPTCHA.');
		}
	} else if ($mode === 'recaptcha') {
		require_once 'inc/recaptcha/autoload.php';

		$captcha = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
		$failed_captcha = true;

		$recaptcha = new \ReCaptcha\ReCaptcha(TINYIB_RECAPTCHA_SECRET);
		$resp = $recaptcha->verify($captcha, remoteAddress());
		if ($resp->isSuccess()) {
			$failed_captcha = false;
		}

		if ($failed_captcha) {
			$captcha_error = 'Failed CAPTCHA.';
			$error_reason = '';

			if (count($resp->getErrorCodes()) == 1) {
				$error_codes = $resp->getErrorCodes();
				$error_reason = $error_codes[0];
			}

			if ($error_reason == 'missing-input-response') {
				$captcha_error .= ' Please click the checkbox labeled "I\'m not a robot".';
			} else {
				$captcha_error .= ' Reason:';
				foreach ($resp->getErrorCodes() as $error) {
					$captcha_error .= '<br>' . $error;
				}
			}
			fancyDie($captcha_error);
		}
	} else if ($mode) { // Simple CAPTCHA
		$captcha = isset($_POST['captcha']) ? strtolower(trim($_POST['captcha'])) : '';
		$captcha_solution = isset($_SESSION['turkchancaptcha']) ? strtolower(trim($_SESSION['turkchancaptcha'])) : '';

		if ($captcha == '') {
			fancyDie(__('Please enter the CAPTCHA text.'));
		} else if ($captcha != $captcha_solution) {
			fancyDie(__('Incorrect CAPTCHA text entered.  Please try again.<br>Click the image to retrieve a new CAPTCHA.'));
		}
	}
}

// checkBanned blocks anonymous posting from a banned IP address.
function checkBanned() {
	$ban = banByIP(remoteAddress());
	if ($ban) {
		if ($ban['expire'] == 0 || $ban['expire'] > time()) {
			$expire = ($ban['expire'] > 0) ? ('<br>This ban will expire ' . formatDate($ban['expire'])) : '<br>This ban is permanent and will not expire.';
			$reason = ($ban['reason'] == '') ? '' : ('<br>Reason: ' . $ban['reason']);
			fancyDie('Your IP address ' . remoteAddress() . ' has been banned from posting on this image board.  ' . $expire . $reason);
		} else {
			clearExpiredBans();
		}
	}
}

// checkKeywords returns the first keyword matching $text, or an empty array.
function checkKeywords($text) {
	$keywords = allKeywords();
	foreach ($keywords as $keyword) {
		if (substr($keyword['text'], 0, 7) == 'regexp:') {
			if (preg_match(substr($keyword['text'], 7), $text)) {
				$keyword['text'] = substr($keyword['text'], 7);
				return $keyword;
			}
			continue;
		}

		if (stripos($text, $keyword['text']) !== false) {
			return $keyword;
		}
	}
	return array();
}

// checkFlood enforces TINYIB_DELAY between posts from the same IP address.
function checkFlood() {
	if (TINYIB_DELAY > 0) {
		$lastpost = lastPostByIP();
		if ($lastpost) {
			if ((time() - $lastpost['timestamp']) < TINYIB_DELAY) {
				fancyDie("Please wait a moment before posting again.  You will be able to make another post in " . (TINYIB_DELAY - (time() - $lastpost['timestamp'])) . " " . plural(TINYIB_DELAY - (time() - $lastpost['timestamp']), "second", "seconds") . ".");
			}
		}
	}
}

// checkMessageSize enforces TINYIB_MAXMESSAGE.
function checkMessageSize() {
	if (TINYIB_MAXMESSAGE > 0 && _strlen($_POST['message']) > TINYIB_MAXMESSAGE) {
		fancyDie(sprintf(__('Please shorten your message, or post it in multiple parts. Your message is %1$d characters long, and the maximum allowed is %2$d.'), _strlen($_POST['message']), TINYIB_MAXMESSAGE));
	}
}

// checkAutoHide hides a post once it collects TINYIB_AUTOHIDE reports.
function checkAutoHide($post) {
	if (TINYIB_AUTOHIDE <= 0) {
		return;
	}

	$reports = reportsByPost($post['id']);
	if (count($reports) >= TINYIB_AUTOHIDE) {
		approvePostByID($post['id'], 0);

		$parent_id = $post['parent'] == TINYIB_NEWTHREAD ? $post['id'] : $post['parent'];
		threadUpdated($parent_id);
	}
}
