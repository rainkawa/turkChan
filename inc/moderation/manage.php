<?php
/*
TurkChan - Staff moderasyon eylemleri
https://github.com/rainkawa/turkChan

Yonetim panelinin yetkili kullanicilara acik eylemleri:

  manageLogAction()          staff eylem logu
  manageRebuildAllAction()   board'u tamamen yeniden uretme
  manageModLogAction()       moderasyon logu sayfasi
  manageReportsAction()      rapor sayfasi
  manageBansAction()         ban ekleme / kaldirma / ban mesaji
  manageKeywordsAction()     anahtar kelime ekleme / silme
  manageUpdateAction()       git ile guncelleme
  manageDbMigrateAction()    veritabani gecisi
  manageModerateAction()     post moderasyon formu
  manageApproveAction()      post onaylama
  manageClearReportsAction() raporlari temizleyip onaylama

Bu eylemler yalnizca inc/requests/manage.php tarafindan, oturum
dogrulamasindan sonra cagrilir. Yetki kontrolu (super administrator)
burada degil, yonlendiricide yapilir; boylece tek bir yetki kapisi kalir.

Gorsel cikti ureten form fonksiyonlari inc/html.php'de kalir; burada
yalnizca is mantigi ve $text/$onload biriktirilir.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// manageLogAction appends an entry to the staff moderation log.
function manageLogAction($action) {
	global $account;
	$account_id = 0;
	if (isset($account['id'])) {
		$account_id = $account['id'];
	}
	$log = array(
		'timestamp' => time(),
		'account' => $account_id,
		'message' => $action,
	);
	insertLog($log);
}

// manageRebuildAllAction regenerates every thread page and the indexes.
function manageRebuildAllAction(&$text) {
	$allthreads = allThreads();
	foreach ($allthreads as $thread) {
		rebuildThread($thread['id']);
	}
	rebuildIndexes();
	$text .= manageInfo(__('Rebuilt board.'));
}

// manageModLogAction renders the staff moderation log.
function manageModLogAction(&$text) {
	$text .= manageModerationLog($_GET['modlog']);
}

// manageReportsAction renders the reported posts overview.
function manageReportsAction(&$text) {
	if (!TINYIB_REPORT) {
		fancyDie(__('Reporting is disabled.'));
	}
	$text .= manageReportsPage($_GET['reports']);
}

// manageBansAction adds, lifts bans and appends ban messages to posts.
function manageBansAction(&$text, &$onload) {
	clearExpiredBans();

	if (isset($_POST['ip']) && $_POST['ip'] != '') {
		$ips = explode(',', $_POST['ip']);
		foreach ($ips as $ip) {
			$banexists = banByIP($ip);
			if ($banexists) {
				continue;
			}

			if (TINYIB_REPORT) {
				deleteReportsByIP($ip);
			}

			$ban = array();
			$ban['ip'] = $ip;
			$ban['expire'] = ($_POST['expire'] > 0) ? (time() + $_POST['expire']) : 0;
			$ban['reason'] = $_POST['reason'];

			$until = __('permanently');
			if ($ban['expire'] > 0) {
				$until = sprintf(__('until %s'), formatDate($ban['expire']));
			}
			$action = sprintf(__('Banned %s %s'), htmlentities($ban['ip']), $until);
			if ($ban['reason'] != '') {
				$action = sprintf(__('Banned %s %s: %s'), htmlentities($ban['ip']), $until, htmlentities($ban['reason']));
			}

			insertBan($ban);
			manageLogAction($action);
		}
		if (TINYIB_BANMESSAGE && isset($_POST['message']) && $_POST['message'] != '' && isset($_GET['posts']) && $_GET['posts'] != '') {
			$post_ids = explode(',', $_GET['posts']);
			foreach ($post_ids as $post_id) {
				$post = postByID($post_id);
				if (!$post) {
					continue; // The post has been deleted
				}
				updatePostMessage($post['id'], $post['message'] . '<br>' . "\n" . '<span class="banmessage">(' . htmlentities($_POST['message']) . ')</span><br>');
				manageLogAction(sprintf(__('Added ban message to %s'), postLink('&gt;&gt;' . $post['id'])));
			}
			clearPostCache();
			foreach ($post_ids as $post_id) {
				$post = postByID($post_id);
				if (!$post) {
					continue; // The post has been deleted
				}
				threadUpdated(getParent($post));
			}
		}
		if (count($ips) == 1) {
			$text .= manageInfo(__('Banned 1 IP address'));
		} else {
			$text .= manageInfo(sprintf(__('Banned %d IP addresses'), count($ips)));
		}
	} elseif (isset($_GET['lift'])) {
		$ban = banByID($_GET['lift']);
		if ($ban) {
			deleteBanByID($_GET['lift']);
			$info = sprintf(__('Lifted ban on %s'), htmlentities($ban['ip']));
			manageLogAction($info);
			$text .= manageInfo($info);
		}
	}

	$onload = manageOnLoad('bans');
	$text .= manageBanForm();
	$text .= manageBansTable();
}

// manageKeywordsAction adds, edits and deletes filter keywords.
function manageKeywordsAction(&$text, &$onload) {
	if (isset($_POST['text']) && $_POST['text'] != '') {
		if ($_GET['keywords'] > 0) {
			deleteKeyword($_GET['keywords']);
		}

		$keyword_exists = keywordByText($_POST['text']);
		if ($keyword_exists) {
			fancyDie(__('Sorry, that keyword has already been added.'));
		}

		$keyword = array();
		$keyword['text'] = $_POST['text'];
		$keyword['action'] = $_POST['action'];

		$kw = $keyword['text'];

		if (isset($_POST['regexp']) && $_POST['regexp'] == '1') {
			$keyword['text'] = 'regexp:' . $keyword['text'];
		}

		insertKeyword($keyword);
		if ($_GET['keywords'] > 0) {
			manageLogAction(sprintf(__('Updated keyword %s'), htmlentities($kw)));
			$text .= manageInfo(__('Keyword updated.'));
			$_GET['keywords'] = 0;
		} else {
			manageLogAction(sprintf(__('Updated keyword %s'), htmlentities($kw)));
			$text .= manageInfo(__('Keyword added.'));
		}
	} elseif (isset($_GET['deletekeyword'])) {
		$keyword = keywordByID($_GET['deletekeyword']);
		if (empty($keyword)) {
			fancyDie(__('That keyword does not exist.'));
		}

		$kw = $keyword['text'];
		if (substr($keyword['text'], 0, 7) == 'regexp:') {
			$kw = substr($keyword['text'], 7);
		}

		deleteKeyword($_GET['deletekeyword']);
		manageLogAction(sprintf(__('Deleted keyword %s'), htmlentities($kw)));
		$text .= manageInfo(__('Keyword deleted.'));
	}

	$onload = manageOnLoad('keywords');
	if ($_GET['keywords'] > 0) {
		$text .= manageEditKeyword($_GET['keywords']);
	} else {
		$text .= manageEditKeyword(0);
		$text .= manageKeywordsTable();
	}
}

// manageUpdateAction runs a git pull and reports its output.
function manageUpdateAction(&$text) {
	if (is_dir('.git')) {
		$git_output = shell_exec('git pull 2>&1');
		$text .= '<blockquote class="reply" style="padding: 7px;font-size: 1.25em;">
	<pre style="margin: 0;padding: 0;">Attempting update...' . "\n\n" . $git_output . '</pre>
	</blockquote>
	<p><b>Note:</b> If ' . TURKCHAN_NAME . ' updates and you have made custom modifications, <a href="' . TURKCHAN_COMMITS_URL . '" target="_blank">review the changes</a> which have been merged into your installation.
	Ensure that your modifications do not interfere with any new/modified files.
	See the <a href="' . TURKCHAN_README_URL . '">README</a> <small>(<a href="README.md" target="_blank">alternate link</a>)</small> for instructions.</p>';
	} else {
		// Orijinal TinyIB ifadesinde noktali virgul eksikligi nedeniyle
		// iki paragraf arasina satir sonu + sekme dizisi giriyordu.
		// Uretilen HTML'in birebir ayni kalmasi icin ayrim acikca yazildi.
		$text .= '<p><b>' . TURKCHAN_NAME . ' was not installed via Git.</b></p>' . "\n\t\t\t\t\t" . '<p>If you installed ' . TURKCHAN_NAME . ' without Git, you must <a href="' . TURKCHAN_REPO_URL . '">update manually</a>.  If you did install with Git, ensure the script has read and write access to the <b>.git</b> folder.</p>';
	}
}

// manageDbMigrateAction drives the database migration tool.
function manageDbMigrateAction(&$text) {
	global $database_modes;

	if (TINYIB_DBMIGRATE !== '' && TINYIB_DBMIGRATE !== false && TINYIB_DBMODE != TINYIB_DBMIGRATE) {
		$mysql_modes = array('mysql', 'mysqli');
		if (in_array(TINYIB_DBMODE, $mysql_modes) && in_array(TINYIB_DBMIGRATE, $mysql_modes)) {
			fancyDie('TINYIB_DBMODE and TINYIB_DBMIGRATE are both set to MySQL database modes. No migration is necessary.');
		}

		$sqlite_modes = array('sqlite', 'sqlite3');
		if (in_array(TINYIB_DBMODE, $sqlite_modes) && in_array(TINYIB_DBMIGRATE, $sqlite_modes)) {
			fancyDie('TINYIB_DBMODE and TINYIB_DBMIGRATE are both set to SQLite database modes. No migration is necessary.');
		}

		if (!in_array(TINYIB_DBMIGRATE, $database_modes)) {
			fancyDie(__('Unknown database mode specified.'));
		}

		if (isset($_GET['go'])) {
			require 'inc/database/' . TINYIB_DBMIGRATE . '_link.php';

			echo '<p>Migrating accounts...</p>';
			$accounts = allAccounts();
			foreach ($accounts as $account) {
				migrateAccount($account);
			}

			echo '<p>Migrating bans...</p>';
			$bans = allBans();
			foreach ($bans as $ban) {
				migrateBan($ban);
			}

			echo '<p>Migrating keywords...</p>';
			$keywords = allKeywords();
			foreach ($keywords as $keyword) {
				migrateKeyword($keyword);
			}

			echo '<p>Migrating logs...</p>';
			$logs = allLogs();
			foreach ($logs as $log) {
				migrateLog($log);
			}

			echo '<p>Migrating posts...</p>';
			$threads = allThreads();
			foreach ($threads as $thread) {
				$posts = postsInThreadByID($thread['id']);
				foreach ($posts as $post) {
					migratePost($post);
				}
			}

			echo '<p>Migrating reports...</p>';
			$reports = allReports();
			foreach ($reports as $report) {
				migrateReport($report);
			}

			echo '<p><b>Database migration complete</b>.  Set TINYIB_DBMODE to the new database mode and TINYIB_DBMIGRATE to false, then click <b>Rebuild All</b> above and ensure everything looks and works as it should.</p>';
		} else {
			$text .= '<p>Your original database will not be deleted.  If the migration fails, disable the tool and your board will be unaffected.  See the <a href="' . TURKCHAN_README_URL . '" target="_blank">README</a> <small>(<a href="README.md" target="_blank">alternate link</a>)</small> for instructions.</a><br><br><a href="?manage&dbmigrate&go"><b>Start the migration</b></a></p>';
		}
	} else {
		fancyDie('Set TINYIB_DBMIGRATE to the desired TINYIB_DBMODE and enter in any database related settings in settings.php before migrating.');
	}
}

// manageModerateAction renders the post moderation form for one or many posts.
function manageModerateAction(&$text, &$onload) {
	if ($_GET['moderate'] != '' && $_GET['moderate'] != '0') {
		$post_ids = explode(',', $_GET['moderate']);
		$compact = count($post_ids) > 1;
		$posts = array();
		$threads = 0;
		$replies = 0;
		$ips = array();

		foreach ($post_ids as $post_id) {
			$post = postByID($post_id);
			if (!$post) {
				fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
			}
			if ($post['parent'] == TINYIB_NEWTHREAD) {
				$threads++;
			} else {
				$replies++;
			}
			$ips[] = $post['ip'];

			$posts[$post_id] = $post;
		}

		$ips = array_unique($ips);

		if (count($post_ids) > 1) {
			$text .= manageModerateAll($post_ids, $threads, $replies, $ips);
		}
		foreach ($post_ids as $post_id) {
			$text .= manageModeratePost($posts[$post_id], $compact);
		}
	} else {
		$onload = manageOnLoad('moderate');
		$text .= manageModeratePostForm();
	}
}

// manageApproveAction approves a post and bumps its thread.
function manageApproveAction(&$text) {
	if ($_GET['approve'] > 0) {
		$post = postByID($_GET['approve']);
		if ($post) {
			approvePostByID($post['id'], 2);
			$thread_id = $post['parent'] == TINYIB_NEWTHREAD ? $post['id'] : $post['parent'];

			if (strtolower($post['email']) != 'sage' && (TINYIB_MAXREPLIES == 0 || numRepliesToThreadByID($thread_id) <= TINYIB_MAXREPLIES)) {
				bumpThreadByID($thread_id);
			}
			threadUpdated($thread_id);

			manageLogAction(__('Approved') . ' ' . postLink('&gt;&gt;' . $post['id']));
			$text .= manageInfo(sprintf(__('Post No.%d approved.'), $post['id']));
		} else {
			fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
		}
	}
}

// manageClearReportsAction approves a post and drops its reports.
function manageClearReportsAction(&$text) {
	if ($_GET['clearreports'] > 0) {
		$post = postByID($_GET['clearreports']);
		if ($post) {
			approvePostByID($post['id'], 2);
			deleteReportsByPost($post['id']);

			manageLogAction(__('Approved') . ' ' . postLink('&gt;&gt;' . $post['id']));
			$text .= manageInfo(sprintf(__('Post No.%d approved.'), $post['id']));
		} else {
			fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
		}
	}
}
