<?php
/*
TurkChan - Hesap paneli eylemleri
https://github.com/rainkawa/turkChan

Yonetim panelinin hesap ile ilgili staff eylemleri:

  manageAccountsAction()        hesap listesi / olusturma / guncelleme
  manageChangePasswordAction()  oturumdaki kullanicinin sifre degistirmesi

manageAccountsAction yalnizca super administrator (TINYIB_SUPER_ADMINISTRATOR)
tarafindan cagrilir; kontrol inc/requests/manage.php icindedir.

Hesap kurallari inc/users/users.php, gorsel formlar inc/html.php icindedir.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// manageAccountsAction renders the accounts screen and applies account edits.
function manageAccountsAction(&$text, &$onload) {
	$id = intval($_GET['accounts']);
	if (isset($_POST['id'])) {
		$id = intval($_POST['id']);
	}
	$a = array('id' => 0);
	if ($id > 0) {
		$a = accountByID($id);
		if (empty($a)) {
			fancyDie(__('Account not found.'));
		}

		staffAccountIsLocked($a);
	}

	if (isset($_POST['id'])) {
		// Sifre zorunlulugu kaydin olusturulmadan once ve dogrudan
		// $_POST uzerinden kontrol edilir; ozgun TinyIB davranisidir.
		if ($id == 0 && $_POST['password'] == '') {
			fancyDie(__('A password is required.'));
		}

		$prev = $a;

		$a['username'] = $_POST['username'];
		if ($_POST['password'] != '') {
			$a['password'] = $_POST['password'];
		}
		$a['role'] = $_POST['role'];

		if ($id == 0) {
			$text .= manageInfo(staffAccountInsert($a));
		} else {
			$text .= manageInfo(staffAccountUpdate($a, $prev));
		}
	}

	$onload = manageOnLoad('accounts');
	$text .= manageAccountForm($_GET['accounts']);
	if (intval($_GET['accounts']) == 0) {
		$text .= manageAccountsTable();
	}
}

// manageChangePasswordAction changes the password of the logged-in account.
function manageChangePasswordAction(&$text, $account) {
	staffAccountIsLocked($account);

	if (isset($_POST['password']) && isset($_POST['confirm'])) {
		if ($_POST['password'] == '') {
			fancyDie(__('A password is required.'));
		} else if ($_POST['password'] != $_POST['confirm']) {
			fancyDie(__('Passwords do not match.'));
		}

		$account['password'] = $_POST['password'];
		updateAccount($account);

		$text .= manageInfo(__('Password updated'));
	}

	$text .= manageChangePasswordForm();
}
