<?php
/*
TurkChan - Thread staff eylemleri
https://github.com/rainkawa/turkChan

Yonetim panelinin thread ile ilgili staff eylemleri:

  manageDeletePostsAction()  post silme (OP silinirse thread silinir)
  manageStickyAction()       thread'i sabitleme / sabitlemeyi kaldirma
  manageLockAction()         thread'i kilitleme / kilidi acma

Bu eylemler yalnizca inc/requests/manage.php tarafindan, oturum
dogrulamasindan sonra cagrilir.

Statik sayfa uretimi inc/threads/threads.php (threadUpdated) icinde
yapilir; burada yalnizca veri degisikligi ve log kaydi yurutulur.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// manageDeletePostsAction deletes the given comma-separated post IDs.
function manageDeletePostsAction(&$text) {
	$post_ids = explode(',', $_GET['delete']);
	$posts = array();
	foreach ($post_ids as $post_id) {
		$post = postByID($post_id);
		if (!$post) {
			continue; // The post has already been deleted
		}
		$posts[$post_id] = $post;
	}
	foreach ($post_ids as $post_id) {
		$post = $posts[$post_id];

		deletePost($post['id']);
		if ($post['parent'] == TINYIB_NEWTHREAD) {
			rebuildThread($post['id']);
		} else {
			rebuildThread($post['parent']);
		}

		$action = sprintf(__('Deleted %s'),'&gt;&gt;' . $post['id']) . ' - ' . hashData($post['ip']);
		$stripped = strip_tags($post['message']);
		if ($stripped != '') {
			$action .= ' - ' . htmlentities(_substr($stripped, 0, 32));
			if (_strlen($stripped) > 32) {
				$action .= '...';
			}
		}
		manageLogAction($action);
	}
	rebuildIndexes();
	if (count($post_ids) == 1) {
		$text .= manageInfo(__('Deleted 1 post'));
	} else {
		$text .= manageInfo(sprintf(__('Deleted %d posts'), count($post_ids)));
	}
}

// manageStickyAction sticks or unsticks a thread.
function manageStickyAction(&$text) {
	if ($_GET['sticky'] > 0) {
		$post = postByID($_GET['sticky']);
		if ($post && $post['parent'] == TINYIB_NEWTHREAD) {
			stickyThreadByID($post['id'], intval($_GET['setsticky']));
			threadUpdated($post['id']);

			$actionMessage = intval($_GET['setsticky']) == 1 ? __('Stickied') : __('Unstickied') . ' ' . postLink('&gt;&gt;' . $post['id']);
			manageLogAction($actionMessage);
			$text .= manageInfo($actionMessage);
		} else {
			fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
		}
	} else {
		fancyDie(__('Form data was lost. Please go back and try again.'));
	}
}

// manageLockAction locks or unlocks a thread.
function manageLockAction(&$text) {
	if ($_GET['lock'] > 0) {
		$post = postByID($_GET['lock']);
		if ($post && $post['parent'] == TINYIB_NEWTHREAD) {
			lockThreadByID($post['id'], intval($_GET['setlock']));
			threadUpdated($post['id']);

			$actionMessage = intval($_GET['setlock']) == 1 ? __('Locked') : __('Unlocked') . ' ' . postLink('&gt;&gt;' . $post['id']);
			manageLogAction($actionMessage);
			$text .= manageInfo($actionMessage);
		} else {
			fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
		}
	} else {
		fancyDie(__('Form data was lost. Please go back and try again.'));
	}
}
