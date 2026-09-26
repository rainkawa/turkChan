<?php
/*
TurkChan - Post (gonderi) alan katmani
https://github.com/rainkawa/turkChan

Bu modul post kaydinin yasam dongusunu ve metin bicimlendirmesini icerir:

  - Post kaydi olusturma (newPost)
  - Post silme (deletePost) ve silinince dosyalarin temizlenmesi
  - Parent (thread) iliskisi (setParent / getParent)
  - Isim + tripcode ayristirma (nameAndTripcode)
  - Nameblock olusturma (nameBlock)
  - Post referansi / tirnak / kelime bolme bicimlendirmesi

Kural: bu katman HTML uretmez. nameBlock() ve postLink() ciktisi
zaten HTML parcalari oldugu icin bunlar sunum katmanina ait kalir;
ancak TinyIB'in geriye donuk uyumlulugu icin fonksiyon adlari ve
urettikleri HTML birebir ayni birakilmistir. Duz HTML sayfasi olusturan
buildPost/buildPage gibi fonksiyonlar ic/html.php'de kalir.

Bu modul thread modülünden bagimsizdir; thread ile ilgili yeniden
yazma/bump islemleri inc/threads/ altindadir.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// newPost returns an empty post record with TinyIB's default field values.
function newPost($parent = TINYIB_NEWTHREAD) {
	return array(
		'parent' => $parent,
		'timestamp' => '0',
		'bumped' => '0',
		'ip' => '',
		'name' => '',
		'tripcode' => '',
		'email' => '',
		'nameblock' => '',
		'subject' => '',
		'message' => '',
		'password' => '',
		'file' => '',
		'file_hex' => '',
		'file_original' => '',
		'file_size' => '0',
		'file_size_formatted' => '',
		'image_width' => '0',
		'image_height' => '0',
		'thumb' => '',
		'thumb_width' => '0',
		'thumb_height' => '0',
		'stickied' => '0',
		'locked' => '0',
		'moderated' => '1'
	);
}

// setParent resolves the thread a new submission belongs to.
function setParent() {
	if (isset($_POST["parent"])) {
		if ($_POST["parent"] != TINYIB_NEWTHREAD) {
			if (!threadExistsByID($_POST['parent'])) {
				fancyDie(__('Invalid parent thread ID supplied, unable to create post.'));
			}

			return $_POST["parent"];
		}
	}

	return TINYIB_NEWTHREAD;
}

// getParent returns the thread ID that owns the given post.
function getParent($post) {
	if ($post['parent'] == TINYIB_NEWTHREAD) {
		return $post['id'];
	}
	return $post['parent'];
}

// deletePostImages removes the uploaded file and thumbnail of a post.
function deletePostImages($post) {
	if (!isEmbed($post['file_hex']) && $post['file'] != '') {
		@unlink('src/' . $post['file']);
	}
	if ($post['thumb'] != '') {
		@unlink('thumb/' . $post['thumb']);
	}
}

// deletePost removes a post. Deleting an OP removes the whole thread.
function deletePost($id) {
	$id = intval($id);

	$is_op = false;
	$parent = 0;
	$op = array();
	$posts = postsInThreadByID($id, false);
	foreach ($posts as $post) {
		if ($post['parent'] == TINYIB_NEWTHREAD) {
			if ($post['id'] == $id) {
				$is_op = true;
			}
			$op = $post;
			continue;
		} else if ($post['id'] == $id) {
			$parent = $post['parent'];
		}

		deletePostImages($post);
		deleteReportsByPost($post['id']);
		deletePostByID($post['id']);
	}
	if (!empty($op)) {
		deletePostImages($op);
		deleteReportsByPost($op['id']);
		deletePostByID($op['id']);
	}

	if ($is_op) {
		@unlink('res/' . $id . '.html');
		return;
	}

	$current_bumped = 0;
	$new_bumped = 0;
	$posts = postsInThreadByID($parent, false);
	foreach ($posts as $post) {
		if ($post['parent'] == TINYIB_NEWTHREAD) {
			$current_bumped = $post['bumped'];
		} else if ($post['id'] == $id || strtolower($post['email']) == 'sage') {
			continue;
		}
		$new_bumped = $post['timestamp'];
	}
	if ($new_bumped >= $current_bumped) {
		return;
	}
	updatePostBumped($parent, $new_bumped);
	rebuildIndexes();
}

function _postLink($matches) {
	$post = postByID($matches[1]);
	if ($post) {
		$is_op = $post['parent'] == TINYIB_NEWTHREAD;
		return '<a href="res/' . ($is_op ? $post['id'] : $post['parent']) . '.html#' . $matches[1] . '" class="' . ($is_op ? 'refop' : 'refreply') . '">' . $matches[0] . '</a>';
	}
	return $matches[0];
}

// postLink turns >>123 references into links to the referenced post.
function postLink($message) {
	return preg_replace_callback('/&gt;&gt;([0-9]+)/', '_postLink', $message);
}

function _finishWordBreak($matches) {
	return '<a' . $matches[1] . 'href="' . str_replace(TINYIB_WORDBREAK_IDENTIFIER, '', $matches[2]) . '"' . $matches[3] . '>' . str_replace(TINYIB_WORDBREAK_IDENTIFIER, '<br>', $matches[4]) . '</a>';
}

// finishWordBreak converts wordbreak markers inside links into line breaks.
function finishWordBreak($message) {
	return str_replace(TINYIB_WORDBREAK_IDENTIFIER, '<br>', preg_replace_callback('/<a(.*?)href="([^"]*?)"(.*?)>(.*?)<\/a>/', '_finishWordBreak', $message));
}

// colorQuote wraps quoted lines in a styled span.
function colorQuote($message) {
	if (substr($message, -1, 1) != "\n") {
		$message .= "\n";
	}
	return preg_replace('/^(&gt;[^\>](.*))\n/m', '<span class="unkfunc">\\1</span>' . "\n", $message);
}

// nameAndTripcode splits a raw name field into name and tripcode.
function nameAndTripcode($name) {
	if (preg_match("/(#|!)(.*)/", $name, $regs)) {
		$cap = $regs[2];
		$cap_full = '#' . $regs[2];

		if (function_exists('mb_convert_encoding')) {
			$recoded_cap = mb_convert_encoding($cap, 'SJIS', 'UTF-8');
			if ($recoded_cap != '') {
				$cap = $recoded_cap;
			}
		}

		if (strpos($name, '#') === false) {
			$cap_delimiter = '!';
		} elseif (strpos($name, '!') === false) {
			$cap_delimiter = '#';
		} else {
			$cap_delimiter = (strpos($name, '#') < strpos($name, '!')) ? '#' : '!';
		}

		if (preg_match("/(.*)(" . $cap_delimiter . ")(.*)/", $cap, $regs_secure)) {
			$cap = $regs_secure[1];
			$cap_secure = $regs_secure[3];
			$is_secure_trip = true;
		} else {
			$is_secure_trip = false;
		}

		$tripcode = "";
		if ($cap != "") { // Copied from Futabally
			$cap = strtr($cap, "&amp;", "&");
			$cap = strtr($cap, "&#44;", ", ");
			$salt = substr($cap . "H.", 1, 2);
			$salt = preg_replace("/[^\.-z]/", ".", $salt);
			$salt = strtr($salt, ":;<=>?@[\\]^_`", "ABCDEFGabcdef");
			$tripcode = substr(crypt($cap, $salt), -10);
		}

		if ($is_secure_trip) {
			if ($cap != "") {
				$tripcode .= "!";
			}

			$tripcode .= "!" . substr(md5($cap_secure . TINYIB_TRIPSEED), 2, 10);
		}

		return array(preg_replace("/(" . $cap_delimiter . ")(.*)/", "", $name), $tripcode);
	}

	return array($name, "");
}

// nameBlock renders the poster name, tripcode, email link, capcode and date.
function nameBlock($name, $tripcode, $email, $timestamp, $capcode) {
	global $tinyib_anonymous;
	$anonymous = $tinyib_anonymous[array_rand($tinyib_anonymous)];

	$output = '<span class="postername">';
	$output .= ($name == '' && $tripcode == '') ? $anonymous : $name;

	if ($tripcode != '') {
		$output .= '</span><span class="postertrip">!' . $tripcode;
	}

	$output .= '</span>';

	if ($email != '' && strtolower($email) != 'noko') {
		$output = '<a href="mailto:' . $email . '">' . $output . '</a>';
	}

	return $output . $capcode . ' ' . formatDate($timestamp);
}
