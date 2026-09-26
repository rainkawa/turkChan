<?php
/*
TurkChan - Yonetim paneli yonlendiricisi
https://github.com/rainkawa/turkChan

imgboard.php yalnizca "?manage" istegini buraya yonlendirir. Burada:

  1. Oturum acma / kapama
  2. Yetkiye gore bolum secimi (super administrator vs moderator)
  3. Ilgili alan modulundeki eylemi cagirma
  4. Paneli olusturup ekrana yazma

Yetki kapisi buradadir; tek yerde tutulur:
  - Super administrator/admin bolumleri : $isadmin
  - Moderator bolumleri                 : yalnizca $loggedin

Bolum tasinabilirligi:
  accounts / changepassword -> inc/users/manage.php
  bans / keywords / reports / modlog / rebuildall / update / dbmigrate
  moderate / approve / clearreports    -> inc/moderation/manage.php
  delete / sticky / lock               -> inc/threads/manage.php

$account, $loggedin, $isadmin, $returnlink ve $redirect global kapsamda
guncellenir; inc/html.php icindeki adminBar() bunlari okur.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// handleManageRequest processes the management panel request and prints the page.
function handleManageRequest() {
	global $account, $loggedin, $isadmin, $returnlink, $redirect;

	$text = '';
	$onload = '';
	$redirect = false;
	$loggedin = false;
	$isadmin = false;
	$returnlink = basename($_SERVER['PHP_SELF']);

	if (isset($_GET["logout"])) {
		$_SESSION['turkchan'] = '';
		$_SESSION['turkchan_key'] = '';
		session_destroy();
		die('--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="0;url=imgboard.php">');
	}

	list($account, $loggedin, $isadmin) = manageCheckLogIn(true);

	if ($loggedin) {
		if ($isadmin) {
			if (isset($_GET['rebuildall'])) {
				manageRebuildAllAction($text);
			} else if (isset($_GET['modlog'])) {
				manageModLogAction($text);
			} else if (isset($_GET['reports'])) {
				manageReportsAction($text);
			} elseif (isset($_GET['accounts'])) {
				if ($account['role'] != TINYIB_SUPER_ADMINISTRATOR) {
					fancyDie(__('Access denied'));
				}

				manageAccountsAction($text, $onload);
			} elseif (isset($_GET['bans'])) {
				manageBansAction($text, $onload);
			} elseif (isset($_GET['keywords'])) {
				manageKeywordsAction($text, $onload);
			} else if (isset($_GET['update'])) {
				manageUpdateAction($text);
			} elseif (isset($_GET['dbmigrate'])) {
				manageDbMigrateAction($text);
			}
		}

		if (isset($_GET['delete'])) {
			manageDeletePostsAction($text);
		} elseif (isset($_GET['approve'])) {
			manageApproveAction($text);
		} elseif (isset($_GET['moderate'])) {
			manageModerateAction($text, $onload);
		} elseif (isset($_GET['sticky']) && isset($_GET['setsticky'])) {
			manageStickyAction($text);
		} elseif (isset($_GET['lock']) && isset($_GET['setlock'])) {
			manageLockAction($text);
		} elseif (isset($_GET['clearreports'])) {
			manageClearReportsAction($text);
		} elseif (isset($_GET["staffpost"])) {
			$onload = manageOnLoad("staffpost");
			$text .= buildPostForm(0, true);
		} elseif (isset($_GET['changepassword'])) {
			manageChangePasswordAction($text, $account);
		}

		if ($text == '') {
			$text = manageStatus();
		}
	} else {
		$onload = manageOnLoad('login');
		$text .= manageLogInForm();
	}

	echo managePage($text, $onload);
}
