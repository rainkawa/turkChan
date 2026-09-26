<?php
/*
TurkChan - Herkese acik istek eylemleri
https://github.com/rainkawa/turkChan

imgboard.php yalnizca yonlendirme yapar; is burada yurutulur:

  handlePostSubmission()  yeni post/thread olusturma
  handlePostPreview()    tek bir postu onizleme
  handleThreadRefresh()   otomatik yenileme (JSON)
  handlePostReport()     post raporlama
  handlePostDelete()     sifre ile post silme

Akis: istek -> dogrulama -> is mantigi -> veritabani -> render.
Bu katman HTML uretmez; yalnizca yonetim panosunun/panelin ciktisini
ekrana yazar ve yonlendirme meta etiketini ayarlar.

Onemli: $redirect, $slow_redirect ve $lock imgboard.php tarafindan
okundugu icin global kapsamda tutulur. $slow_redirect yalnizca
TINYIB_REQMOD aktifken atanir; onde tanimlanmamasi "yavas yonlendirme"
davranisini korur.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// handlePostSubmission creates a new thread or reply from a POST request.
// Echoes progress output and sets the post-submission redirect target.
function handlePostSubmission() {
	global $redirect, $slow_redirect, $lock, $loggedin, $isadmin;
	global $tinyib_capcodes, $tinyib_embeds, $tinyib_hidefields, $tinyib_hidefieldsop, $tinyib_uploads, $tinyib_anonymous;

	$lock = lockDatabase();

	if (TINYIB_DBMIGRATE) {
		fancyDie(__('Posting is currently disabled.<br>Please try again in a few moments.'));
	}

	$staffpost = isStaffPost();
	$capcode = '';
	if (!$staffpost) {
		checkMessageSize();
	}

	$post = newPost(setParent());

	if (!$loggedin) {
		checkCAPTCHA($post['parent'] == TINYIB_NEWTHREAD ? TINYIB_CAPTCHA : TINYIB_REPLYCAPTCHA);
		checkFlood();
	}

	if (!$loggedin) {
		if ($post['parent'] == TINYIB_NEWTHREAD && TINYIB_DISALLOWTHREADS != '') {
			fancyDie(TINYIB_DISALLOWTHREADS);
		} else if ($post['parent'] != TINYIB_NEWTHREAD && TINYIB_DISALLOWREPLIES != '') {
			fancyDie(TINYIB_DISALLOWREPLIES);
		}
	}

	$hide_fields = $post['parent'] == TINYIB_NEWTHREAD ? $tinyib_hidefieldsop : $tinyib_hidefields;

	if ($post['parent'] != TINYIB_NEWTHREAD && !$loggedin) {
		$parent = postByID($post['parent']);
		if (!isset($parent['locked'])) {
			fancyDie(__('Invalid parent thread ID supplied, unable to create post.'));
		} else if ($parent['locked'] == 1) {
			fancyDie(__('Replies are not allowed to locked threads.'));
		}
	}

	if ($post['name'] == '' && $post['tripcode'] == '') {
		$post['name'] = $tinyib_anonymous[array_rand($tinyib_anonymous)];
	}

	$post['ip'] = remoteAddress();

	$spoiler = TINYIB_SPOILERIMAGE && isset($_POST['spoiler']);

	if ($staffpost || !in_array('name', $hide_fields)) {
		list($post['name'], $post['tripcode']) = nameAndTripcode($_POST['name']);
		if (TINYIB_MAXNAME > 0) {
			$post['name'] = _substr($post['name'], 0, TINYIB_MAXNAME);
		}
		$post['name'] = cleanString($post['name']);
	}
	if ($staffpost || !in_array('email', $hide_fields)) {
		$post['email'] = $_POST['email'];
		if (TINYIB_MAXEMAIL > 0) {
			$post['email'] = _substr($post['email'], 0, TINYIB_MAXEMAIL);
		}
		$post['email'] = cleanString(str_replace('"', '&quot;', $post['email']));
	}
	if ($staffpost) {
		$capcode = ($isadmin) ? ' <span style="color: ' . $tinyib_capcodes[0][1] . ' ;">## ' . $tinyib_capcodes[0][0] . '</span>' : ' <span style="color: ' . $tinyib_capcodes[1][1] . ';">## ' . $tinyib_capcodes[1][0] . '</span>';
	}
	if ($staffpost || !in_array('subject', $hide_fields)) {
		$post['subject'] = $_POST['subject'];
		if (TINYIB_MAXSUBJECT > 0) {
			$post['subject'] = _substr($post['subject'], 0, TINYIB_MAXSUBJECT);
		}
		$post['subject'] = cleanString($post['subject']);
	}
	if ($staffpost || !in_array('message', $hide_fields)) {
		$post['message'] = $_POST['message'];
		if ($staffpost && isset($_POST['raw'])) {
			// Treat message as raw HTML
		} else {
			if (TINYIB_WORDBREAK > 0) {
				$post['message'] = preg_replace('/([^\s]{' . TINYIB_WORDBREAK . '})(?=[^\s])/u', '$1' . TINYIB_WORDBREAK_IDENTIFIER, $post['message']);
			}
			$post['message'] = str_replace("\n", '<br>', makeLinksClickable(colorQuote(postLink(cleanString(rtrim($post['message']))))));

			if (TINYIB_SPOILERTEXT) {
				$post['message'] = preg_replace('/&lt;s&gt;(.*?)&lt;\/s&gt;/i', '<span class="spoiler">$1</span>', $post['message']);
				$post['message'] = preg_replace('/&lt;spoiler&gt;(.*?)&lt;\/spoiler&gt;/i', '<span class="spoiler">$1</span>', $post['message']);
				$post['message'] = preg_replace('/&lt;spoilers&gt;(.*?)&lt;\/spoilers&gt;/i', '<span class="spoiler">$1</span>', $post['message']);
			}

			if (TINYIB_WORDBREAK > 0) {
				$post['message'] = finishWordBreak($post['message']);
			}
		}
	}
	if ($staffpost || !in_array('password', $hide_fields)) {
		$post['password'] = ($_POST['password'] != '') ? hashData($_POST['password']) : '';
	}

	$hide_post = false;
	$report_post = false;
	foreach (array($post['name'], $post['email'], $post['subject'], $post['message']) as $field) {
		$keyword = checkKeywords($field);
		if (empty($keyword)) {
			continue;
		}

		$expire = -1;
		switch ($keyword['action']) {
			case 'report':
				$report_post = true;
				break;
			case 'hide':
				$hide_post = true;
				break;
			case 'delete':
				fancyDie(__('Your post contains a blocked keyword.'));
			case 'ban0':
				$expire = 0;
				break;
			case 'ban1h':
				$expire = 3600;
				break;
			case 'ban1d':
				$expire = 86400;
				break;
			case 'ban2d':
				$expire = 172800;
				break;
			case 'ban1w':
				$expire = 604800;
				break;
			case 'ban2w':
				$expire = 1209600;
				break;
			case 'ban1m':
				$expire = 2592000;
				break;
		}
		if ($expire >= 0) {
			$ban = array();
			$ban['ip'] = $post['ip'];
			$ban['expire'] = $expire > 0 ? (time() + $expire) : 0;
			$ban['reason'] = __('Keyword') . ': ' . $keyword['text'];
			insertBan($ban);

			if ($ban['expire'] > 0) {
				$bannedText = sprintf(__('Your IP address (%1$s) is banned until %2$s.'), remoteAddress(), formatDate($ban['expire']));
			} else {
				$bannedText = sprintf(__('Your IP address (%s) is permanently banned.'), remoteAddress());
			}
			if ($ban['reason'] != '') {
				$bannedText .= '<br>' . __('Reason') . ': ' . $ban['reason'];
			}
			fancyDie($bannedText);
		}
		break;
	}

	$post['nameblock'] = nameBlock($post['name'], $post['tripcode'], $post['email'], time(), $capcode);

	if (isset($_POST['embed']) && trim($_POST['embed']) != '' && ($staffpost || !in_array('embed', $hide_fields))) {
		if (isset($_FILES['file']) && $_FILES['file']['name'] != "") {
			fancyDie(__('Embedding a URL and uploading a file at the same time is not supported.'));
		}

		list($service, $embed) = getEmbed(trim($_POST['embed']));
		if (empty($embed) || !isset($embed['html']) || !isset($embed['title']) || !isset($embed['thumbnail_url'])) {
			if (!TINYIB_UPLOADVIAURL) {
				fancyDie(sprintf(__('Invalid embed URL. Only %s URLs are supported.'), implode('/', array_keys($tinyib_embeds))));
			}

			$headers = get_headers(trim($_POST['embed']), true);
			if (TINYIB_MAXKB > 0 && isset($headers['Content-Length']) && intval($headers['Content-Length']) > (TINYIB_MAXKB * 1024)) {
				fancyDie(sprintf(__('That file is larger than %s.'), TINYIB_MAXKBDESC));
			}

			$data = url_get_contents(trim($_POST['embed']));
			if (strlen($data) == 0) {
				fancyDie(__('Failed to download file at specified URL.'));
			}

			if (TINYIB_MAXKB > 0 && strlen($data) > (TINYIB_MAXKB * 1024)) {
				fancyDie(sprintf(__('That file is larger than %s.'), TINYIB_MAXKBDESC));
			}

			$filepath = 'src/' . time() . substr(microtime(), 2, 3) . rand(1000, 9999) . '.txt';
			if (!file_put_contents($filepath, $data)) {
				@unlink($filepath);
				fancyDie(__('Failed to download file at specified URL.'));
			}

			$post = attachFile($post, $filepath, basename(parse_url(trim($_POST['embed']), PHP_URL_PATH)), false, $spoiler);
		} else {
			$post['file_hex'] = $service;
			$temp_file = time() . substr(microtime(), 2, 3);
			$file_location = "thumb/" . $temp_file;
			file_put_contents($file_location, url_get_contents($embed['thumbnail_url']));

			$file_info = getimagesize($file_location);
			$file_mime = mime_content_type($file_location);
			$post['image_width'] = $file_info[0];
			$post['image_height'] = $file_info[1];

			if ($file_mime == "image/jpeg") {
				$post['thumb'] = $temp_file . '.jpg';
			} else if ($file_mime == "image/gif") {
				$post['thumb'] = $temp_file . '.gif';
			} else if ($file_mime == "image/png") {
				$post['thumb'] = $temp_file . '.png';
			} else {
				fancyDie(__('Error while processing audio/video.'));
			}
			$thumb_location = "thumb/" . $post['thumb'];

			list($thumb_maxwidth, $thumb_maxheight) = thumbnailDimensions($post);

			if (!createThumbnail($file_location, $thumb_location, $thumb_maxwidth, $thumb_maxheight, $spoiler)) {
				fancyDie(__('Could not create thumbnail.'));
			}

			addVideoOverlay($thumb_location);

			$thumb_info = getimagesize($thumb_location);
			$post['thumb_width'] = $thumb_info[0];
			$post['thumb_height'] = $thumb_info[1];

			$post['file_original'] = cleanString($embed['title']);
			$post['file'] = str_ireplace(array('src="https://', 'src="http://'), 'src="//', $embed['html']);
		}
	} else if (isset($_FILES['file']) && $_FILES['file']['name'] != "" && ($staffpost || !in_array('file', $hide_fields))) {
		validateFileUpload();

		$post = attachFile($post, $_FILES['file']['tmp_name'], $_FILES['file']['name'], true, $spoiler);
	}

	if ($post['file'] == '') { // No file uploaded
		$file_ok = !empty($tinyib_uploads) && ($staffpost || !in_array('file', $hide_fields));
		$embed_ok = (!empty($tinyib_embeds) || TINYIB_UPLOADVIAURL) && ($staffpost || !in_array('embed', $hide_fields));
		$allowed = '';
		if ($file_ok && $embed_ok) {
			$allowed = __('upload a file or embed a URL');
		} else if ($file_ok) {
			$allowed = __('upload a file');
		} else if ($embed_ok) {
			$allowed = __('embed a URL');
		}
		if ($post['parent'] == TINYIB_NEWTHREAD && $allowed != "" && !TINYIB_NOFILEOK) {
			fancyDie(sprintf(__('Please %s to start a new thread.'), $allowed));
		}
		if (!$staffpost && str_replace('<br>', '', $post['message']) == "") {
			$message_ok = !in_array('message', $hide_fields);
			if ($message_ok) {
				if ($allowed != '') {
					fancyDie(sprintf(__('Please enter a message and/or %s.'), $allowed));
				}
				fancyDie(__('Please enter a message.'));
			}
			fancyDie(sprintf(__('Please %s.'), $allowed));
		}
	}

	if (!$loggedin && (($post['file'] != '' && TINYIB_REQMOD == 'files') || TINYIB_REQMOD == 'all')) {
		$post['moderated'] = '0';
		echo sprintf(__('Your %s will be shown <b>once it has been approved</b>.'), $post['parent'] == TINYIB_NEWTHREAD ? 'thread' : 'post') . '<br>';
		$slow_redirect = true;
	}

	$post['id'] = insertPost($post);

	if ($report_post) {
		$report = array('ip' => $post['ip'], 'post' => $post['id']);
		insertReport($report);
		checkAutoHide($post);
	}

	if ($hide_post) {
		approvePostByID($post['id'], 0);
	}

	if ($post['moderated'] == '1') {
		if (TINYIB_ALWAYSNOKO || strtolower($post['email']) == 'noko') {
			$redirect = 'res/' . ($post['parent'] == TINYIB_NEWTHREAD ? $post['id'] : $post['parent']) . '.html#' . $post['id'];
		}

		trimThreads();

		echo __('Updating thread...') . '<br>';
		if ($post['parent'] != TINYIB_NEWTHREAD) {
			rebuildThread($post['parent']);

			if (strtolower($post['email']) != 'sage') {
				if (TINYIB_MAXREPLIES == 0 || numRepliesToThreadByID($post['parent']) <= TINYIB_MAXREPLIES) {
					bumpThreadByID($post['parent']);
				}
			}
		} else {
			rebuildThread($post['id']);
		}

		echo __('Updating index...') . '<br>';
		rebuildIndexes();
	}

	if ($staffpost) {
		manageLogAction(__('Created staff post') . ' ' . postLink('&gt;&gt;' . $post['id']));
	}
}

// handlePostPreview renders a single post for the quote/expand UI.
function handlePostPreview() {
	global $isadmin;

	$post = postByID(intval($_GET['preview']));
	if (empty($post)) {
		die(__('This post has been deleted'));
	} else if ($post['moderated'] == 0 && !$isadmin) {
		die(__('This post requires moderation before it can be displayed'));
	}

	$html = buildPost($post, isset($_GET['res']), true);
	if (isset($_GET['res'])) {
		$html = fixLinksInRes($html);
	}

	echo $html;
	die();
}

// handleThreadRefresh returns newly posted replies as JSON for auto-refresh.
function handleThreadRefresh() {
	if (TINYIB_AUTOREFRESH <= 0) {
		fancyDie(__('Automatic refreshing is disabled.'));
	}

	$thread_id = intval($_GET['posts']);
	$new_since = intval($_GET['since']);
	if ($thread_id <= 0 || $new_since < 0) {
		fancyDie('');
	}

	$json_posts = array();
	$posts = postsInThreadByID($thread_id);
	if ($new_since > 0) {
		foreach ($posts as $i => $post) {
			if ($post['id'] <= $new_since) {
				continue;
			}
			$json_posts[$post['id']] = fixLinksInRes(buildPost($post, true));
		}
	}

	echo json_encode($json_posts);
	die();
}

// handlePostReport records a report against a post.
function handlePostReport() {
	$lock = lockDatabase();

	if (!TINYIB_REPORT) {
		fancyDie(__('Reporting is disabled.'));
	}

	$post = postByID($_GET['report']);
	if (!$post) {
		fancyDie(__('Sorry, an invalid post identifier was sent. Please go back, refresh the page, and try again.'));
	}

	if ($post['moderated'] == 2) {
		fancyDie(__('Moderators have determined that post does not break any rules.'));
	}

	$report = reportByIP($post['id'], remoteAddress());
	if (!empty($report)) {
		fancyDie(__('You have already submitted a report for that post.'));
	}

	$go_back = 1;
	if (TINYIB_REPORTCAPTCHA != '') {
		if (isset($_GET['verify'])) {
			checkCAPTCHA(TINYIB_REPORTCAPTCHA);
			$go_back = 2;
		} else {
			if (TINYIB_REPORTCAPTCHA === 'hcaptcha') {
				$captcha = '
<br>
<div style="min-height: 82px;">
	<div class="h-captcha" data-sitekey="' . TINYIB_HCAPTCHA_SITE . '"></div>
</div><br><br>';
			} else if (TINYIB_REPORTCAPTCHA === 'recaptcha') {
				$captcha = '
<br>
<div style="min-height: 80px;">
	<div class="g-recaptcha" data-sitekey="' . TINYIB_RECAPTCHA_SITE . '"></div>
	<noscript>
		<div>
			<div style="width: 302px; height: 422px; position: relative;">
				<div style="width: 302px; height: 422px; position: absolute;">
					<iframe src="https://www.google.com/recaptcha/api/fallback?k=' . TINYIB_RECAPTCHA_SITE . '" frameborder="0" scrolling="no" style="width: 302px; height:422px; border-style: none;"></iframe>
				</div>
			</div>
			<div style="width: 300px; height: 60px; border-style: none;bottom: 12px; left: 25px; margin: 0px; padding: 0px; right: 25px;background: #f9f9f9; border: 1px solid #c1c1c1; border-radius: 3px;">
				<textarea id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response" style="width: 250px; height: 40px; border: 1px solid #c1c1c1; margin: 10px 25px; padding: 0px; resize: none;"></textarea>
			</div>
		</div>
	</noscript>
</div><br><br>';
			} else { // Simple CAPTCHA
				$captcha = '
<br>
<input type="text" name="captcha" id="captcha" size="6" accesskey="c" autocomplete="off">&nbsp;&nbsp;' . __('(enter the text below)') . '<br>
<img id="captchaimage" src="inc/captcha.php" width="175" height="55" alt="CAPTCHA" onclick="javascript:reloadCAPTCHA()" style="margin-top: 5px;cursor: pointer;"><br><br>';
			}

			$txt_report = __('Please complete a CAPTCHA to submit your report');
			$txt_submit = __('Submit');
			$form_name = TURKCHAN_FORM_NAME;
			$body = <<<EOF
<form id="{$form_name}" name="{$form_name}" method="post" action="?report={$post['id']}&verify">
<fieldset>
<legend align="center">$txt_report</legend>
<div class="login">
$captcha
<input type="submit" value="$txt_submit" class="managebutton">
</div>
</fieldset>
</form>
EOF;

			echo pageHeader() . $body . pageFooter();
			die();
		}
	}

	$report = array('ip' => remoteAddress(), 'post' => $post['id']);
	insertReport($report);
	checkAutoHide($post);

	fancyDie(__('Post reported.'), $go_back);
}

// handlePostDelete deletes a post after checking the submitter's password.
function handlePostDelete() {
	global $account, $loggedin, $isadmin, $redirect;

	$lock = lockDatabase();

	if (!isset($_POST['delete'])) {
		fancyDie(__('Tick the box next to a post and click "Delete" to delete it.'));
	}

	if (TINYIB_DBMIGRATE) {
		fancyDie(__('Post deletion is currently disabled.<br>Please try again in a few moments.'));
	}

	$post_ids = array();
	if (is_array($_POST['delete'])) {
		$post_ids = $_POST['delete'];
	} else {
		$post_ids = array($_POST['delete']);
	}

	list($account, $loggedin, $isadmin) = manageCheckLogIn(false);
	if (!empty($account)) {
		// Redirect to post moderation page
		echo '--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="0;url=' . basename($_SERVER['PHP_SELF']) . '?manage&moderate=' . implode(',', $post_ids) . '">';
		die();
	}

	$post = postByID($post_ids[0]);
	if (!$post) {
		fancyDie(__('Sorry, an invalid post identifier was sent. Please go back, refresh the page, and try again.'));
	} else if ($post['password'] != '' && (hashData($_POST['password']) == $post['password'] || md5(md5($_POST['password'])) == $post['password'])) {
		deletePost($post['id']);
		if ($post['parent'] == TINYIB_NEWTHREAD) {
			threadUpdated($post['id']);
		} else {
			threadUpdated($post['parent']);
		}
		fancyDie(__('Post deleted.'));
	} else {
		fancyDie(__('Invalid password.'));
	}

	$redirect = false;
}
