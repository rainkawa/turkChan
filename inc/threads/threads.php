<?php
/*
TurkChan - Thread alan katmani
https://github.com/rainkawa/turkChan

Bu modul board'un statik ciktilarini (res/*.html, index*.html,
catalog.html, *.json) yeniden uretir:

  - threadUpdated  : bir thread degistiginde thread + indexleri tazeler
  - rebuildThread  : tek bir thread'in res/<id>.html sayfasini yazar
  - rebuildIndexes : index sayfalarini, katalogu ve JSON dosyalarini yazar
  - rebuildCatalog : yalnizca katalog sayfasini yazar

Baglilik yonu: bu katman sunum katmanini (buildPost/buildPage, inc/html.php)
ve veri katmanini (allThreads/postsInThreadByID) cagirir; hicbir modul
burayi geri cagirmaz. Boylece dongusel bagimlilik olusmaz.

Yinelenen/bump edilen thread siralamasi, kilitleme ve kisis lastirme
veri katmaninda (stickyThreadByID, lockThreadByID, bumpThreadByID)
korunur; buradaki fonksiyonlar yalnizca dosya uretir.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// threadUpdated regenerates a thread page and the board indexes.
function threadUpdated($id) {
	rebuildThread($id);
	rebuildIndexes();
}

// rebuildCatalog regenerates catalog.html.
function rebuildCatalog() {
	$threads = allThreads();
	$htmlposts = '';
	foreach ($threads as $post) {
		$htmlposts .= buildCatalogPost($post);
	}

	writePage(TURKCHAN_BOARD_CATALOG, buildPage($htmlposts, -1));
}

// rebuildIndexes regenerates every index page, the catalog and the JSON files.
function rebuildIndexes() {
	$page = 0;
	$i = 0;
	$htmlposts = '';
	$threads = allThreads();
	$pages = ceil(count($threads) / TINYIB_THREADSPERPAGE) - 1;

	foreach ($threads as $thread) {
		$replies = postsInThreadByID($thread['id']);
		$thread['omitted'] = max(0, count($replies) - TINYIB_PREVIEWREPLIES - 1);

		// Build replies for preview
		$htmlreplies = array();
		for ($j = count($replies) - 1; $j > $thread['omitted']; $j--) {
			$htmlreplies[] = buildPost($replies[$j], TINYIB_INDEXPAGE);
		}

		if ($i > 0) {
			$htmlposts .= "\n<hr>";
		}
		$htmlposts .= buildPost($thread, TINYIB_INDEXPAGE) . implode('', array_reverse($htmlreplies));

		if (++$i >= TINYIB_THREADSPERPAGE) {
			$file = ($page == 0) ? TINYIB_INDEX : ($page . '.html');
			writePage($file, buildPage($htmlposts, 0, $pages, $page));

			$page++;
			$i = 0;
			$htmlposts = '';
		}
	}

	if ($page == 0 || $htmlposts != '') {
		$file = ($page == 0) ? TINYIB_INDEX : ($page . '.html');
		writePage($file, buildPage($htmlposts, 0, $pages, $page));
	}

	if (TINYIB_CATALOG) {
		rebuildCatalog();
	}

	if (TINYIB_JSON) {
		writePage(TURKCHAN_BOARD_THREADS_JSON, buildIndexJSON());
		writePage(TURKCHAN_BOARD_CATALOG_JSON, buildCatalogJSON());
	}
}

// rebuildThread regenerates a single thread page (and its JSON sidecar).
function rebuildThread($id) {
	$id = intval($id);

	$post = postByID($id);
	if (empty($post) || $post['moderated'] == 0) {
		@unlink('res/' . $id . '.html');
		return;
	}

	$posts = postsInThreadByID($id);
	if (count($posts) == 0) {
		@unlink('res/' . $id . '.html');
		return;
	}

	$htmlposts = "";
	$lastpostid = 0;
	foreach ($posts as $post) {
		$htmlposts .= buildPost($post, TINYIB_RESPAGE);
		$lastpostid = $post['id'];
	}

	writePage('res/' . $id . '.html', fixLinksInRes(buildPage($htmlposts, $id, 0, 0, $lastpostid)));

	if (TINYIB_JSON) {
		writePage('res/' . $id . '.json', buildSingleThreadJSON($id));
	}
}
