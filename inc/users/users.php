<?php
/*
TurkChan - Hesap (account) alan katmani
https://github.com/rainkawa/turkChan

Auth ile Users arasindaki sinir:

  AUTH (inc/auth/auth.php)  : "Bu istemci giris yapti mi?"
  USERS (bu dosya)          : "Bu hesap kim, rolu ne, nasil degisir?"

Burada yalnizca TinyIB'in zaten var olan staff hesap kurallari
toplanmistir; hicbir yeni hesap olusturma/kayit akisi eklenmemistir.

Veri tabani CRUD fonksiyonlari (accountByUsername, insertAccount,
updateAccount, deleteAccountByID) TinyIB'in surucu katmaninda
(inc/database/) kalmaya devam eder; burada tekrarlanmaz.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// staffAccountRoleName returns the localized label for an account role.
function staffAccountRoleName($role) {
	switch ($role) {
		case TINYIB_SUPER_ADMINISTRATOR:
			return __('Super-administrator');
		case TINYIB_ADMINISTRATOR:
			return __('Administrator');
		case TINYIB_MODERATOR:
			return __('Moderator');
		case TINYIB_DISABLED:
			return __('Disabled');
	}
	return '';
}

// staffAccountIsLocked stops an update when settings.php pins the account
// to a constant password (TINYIB_ADMINPASS / TINYIB_MODPASS).
function staffAccountIsLocked($account) {
	if ($account['username'] == 'admin' && TINYIB_ADMINPASS != '') {
		fancyDie(__('This account may not be updated while TINYIB_ADMINPASS is set.'));
	} else if ($account['username'] == 'mod' && TINYIB_MODPASS != '') {
		fancyDie(__('This account may not be updated while TINYIB_MODPASS is set.'));
	}
}

// staffAccountRoleIsValid validates a submitted role value.
function staffAccountRoleIsValid($role) {
	return ($role === TINYIB_SUPER_ADMINISTRATOR || $role == TINYIB_ADMINISTRATOR || $role == TINYIB_MODERATOR || $role == TINYIB_DISABLED);
}

// staffAccountInsert validates and stores a new staff account.
// $a is the record built by the caller; returns an informational message.
// Sifre zorunlulugu ve rol dogrulamasi orijinal TinyIB sirasini korur.
function staffAccountInsert($a) {
	$a['role'] = intval($a['role']);
	if (!staffAccountRoleIsValid($a['role'])) {
		fancyDie(__('Invalid role.'));
	}

	insertAccount($a);
	manageLogAction(sprintf(__('Added account %s'), $a['username']));

	return __('Added account');
}

// staffAccountUpdate validates and stores changes to an existing staff
// account and writes the corresponding moderation log entries.
// $prev is the record as loaded from the database.
function staffAccountUpdate($a, $prev) {
	$a['role'] = intval($a['role']);
	if (!staffAccountRoleIsValid($a['role'])) {
		fancyDie(__('Invalid role.'));
	}

	updateAccount($a);
	if ($a['username'] != $prev['username']) {
		manageLogAction(sprintf(__('Renamed account %1$s as %2$s'), $prev['username'], $a['username']));
	}
	if ($a['password'] != $prev['password']) {
		manageLogAction(sprintf(__('Changed password of account %s'), $a['username']));
	}
	if ($a['role'] != $prev['role']) {
		manageLogAction(sprintf(__('Changed role of account %s to %s'), $a['username'], staffAccountRoleName($a['role'])));
	}

	return __('Updated account');
}
