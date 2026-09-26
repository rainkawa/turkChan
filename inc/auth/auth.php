<?php
/*
TurkChan - Kimlik dogrulama (authentication) katmani
https://github.com/rainkawa/turkChan

Bu modul yalnizca su soruyu yanitlar:
    "Bu istemci giris yapmis mi, ve yetkisi ne?"

Kapsam:
  - Yonetim paneli oturumu (TINYIB_MANAGEKEY / session)
  - Kullanici adi + sifre dogrulamasi
  - Rol kontrolu (super administrator / administrator / moderator / disabled)
  - Staff post (capcode) yetkisi

Kapsam disi (bilerek burada degil):
  - Hesabin kim oldugu ve hangi alanlara sahip oldugu  -> inc/users/
  - Sifre/oturum dogrulama yapmayan butun post kurallari -> inc/moderation/guards.php
  - Veri tabani erisimi (accountByUsername vb.) -> inc/database/

Gerceklestirilen kod, TinyIB'nin manageCheckLogIn/isStaffPost
davranisini birebir korur.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// manageCheckLogIn validates the management panel session.
// Returns array($account, $loggedin, $isadmin).
function manageCheckLogIn($requireKey) {
	$account = array();
	$loggedin = false;
	$isadmin = false;

	$key = (isset($_GET['manage']) && $_GET['manage'] != '') ? hashData($_GET['manage']) : '';
	if ($key == '' && isset($_SESSION['turkchan_key'])) {
		$key = $_SESSION['turkchan_key'];
	}
	if (TINYIB_MANAGEKEY != '' && $key !== hashData(TINYIB_MANAGEKEY)) {
		$_SESSION['turkchan_key'] = '';
		$_SESSION['turkchan_account'] = '';
		session_destroy();

		if ($requireKey) {
			fancyDie(__('Invalid key.'));
		}

		return array($account, $loggedin, $isadmin);
	}

	if (isset($_POST['username']) && isset($_POST['managepassword']) && $_POST['username'] != '' && $_POST['managepassword'] != '') {
		checkCAPTCHA(TINYIB_MANAGECAPTCHA);

		$a = accountByUsername($_POST['username']);
		if (empty($a) || hashData($_POST['managepassword'], true) !== $a['password']) {
			fancyDie(__('Invalid username or password.'));
		}
		$_SESSION['turkchan_key'] = hashData(TINYIB_MANAGEKEY);
		$_SESSION['turkchan_username'] = $a['username'];
		$_SESSION['turkchan_password'] = $a['password'];

		// Prevent reauthentication
		$_POST['username'] = '';
		$_POST['managepassword'] = '';
	}

	if (isset($_SESSION['turkchan_username']) && isset($_SESSION['turkchan_password'])) {
		$a = accountByUsername($_SESSION['turkchan_username']);
		if (!empty($a) && $a['password'] == $_SESSION['turkchan_password'] && $a['role'] != TINYIB_DISABLED) {
			$account = $a;
			$loggedin = true;
			if ($account['role'] == TINYIB_SUPER_ADMINISTRATOR || $account['role'] == TINYIB_ADMINISTRATOR) {
				$isadmin = true;
			}

			$account['lastactive'] = time();
			updateAccount($account);
		}
	}

	return array($account, $loggedin, $isadmin);
}

// isStaffPost reports whether the current submission may carry a staff capcode.
function isStaffPost() {
	if (isset($_POST['staffpost'])) {
		list($loggedin, $isadmin) = manageCheckLogIn(false);
		return $loggedin;
	}

	return false;
}
