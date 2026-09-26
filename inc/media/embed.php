<?php
/*
TurkChan - Embed (oEmbed) destegi
https://github.com/rainkawa/turkChan

$tinyib_embeds ayarindaki servis listesine gore bir URL'nin oEmbed
bilgisi (baslik, gorsel, gomulu HTML) alinir.

MIME tanimli bir gomulu dosya degildir; bu yuzden post'un file_hex
alaninda servis adi saklanir ve isEmbed() ile ayirt edilir.
*/

if (!defined('TINYIB_BOARD')) {
	die('');
}

// isEmbed reports whether a file_hex value refers to an embed service
// rather than an actual uploaded file.
function isEmbed($file_hex) {
	global $tinyib_embeds;
	return in_array($file_hex, array_keys($tinyib_embeds));
}

// getEmbed resolves a URL against the configured embed services.
// Returns array($service, $oembed_data).
function getEmbed($url) {
	global $tinyib_embeds;
	foreach ($tinyib_embeds as $service => $service_url) {
		$service_url = str_ireplace("TINYIBEMBED", urlencode($url), $service_url);
		$data = url_get_contents($service_url);
		if ($data != '') {
			$result = json_decode($data, true);
			if (!empty($result)) {
				return array($service, $result);
			}
		}
	}

	return array('', array());
}
