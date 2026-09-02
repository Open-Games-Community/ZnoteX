<?php

function setSession($key, $data) {
	global $sessionPrefix;
	$_SESSION[$sessionPrefix.$key] = $data;
}
function getSession($key) {
	global $sessionPrefix;
	return (isset($_SESSION[$sessionPrefix.$key])) ? $_SESSION[$sessionPrefix.$key] : false;
}
// Fetch and sanitize POST and GET values
function getValue($value) {
	return (!empty($value)) ? sanitize($value) : false;
}

function SendGet($getArray, $location = 'error.php') {
	$string = "";
	$count = 0;
	foreach ($getArray as $getKey => $getValue) {
		if ($count > 0) $string .= '&';
		header('Location: ' . $location . '?' . http_build_query($getArray));
		exit;
	}
	header("Location: {$location}?{$string}");
	exit();
}

// Sweet error reporting
function data_dump($print = false, $var = false, $title = false) {
	if ($title !== false) echo "<pre><font color='red' size='5'>$title</font><br>";
	else echo '<pre>';
	if ($print !== false) {
		echo 'Print: - ';
		print_r($print);
		echo "<br>";
	}
	if ($var !== false) {
		echo 'Var_dump: - ';
		var_dump($var);
	}
	echo '</pre><br>';
}

function accountAccess($accountId, $TFS) {
	$accountId = (int)$accountId;
	$access = 0;

	// TFS 0.3/4
	$yourChars = mysql_select_multi("SELECT `name`, `group_id`, `account_id` FROM `players` WHERE `account_id`='$accountId';");
	if ($yourChars !== false) {
		foreach ($yourChars as $char) {
			if ($TFS === 'TFS_03' || $TFS === 'OTHIRE') {
				if ($char['group_id'] > $access) $access = $char['group_id'];
			} else {
				if ($char['group_id'] > 1) {
					if ($access == 0) {
						$acc = mysql_select_single("SELECT `type` FROM `accounts` WHERE `id`='". $char['account_id'] ."' LIMIT 1;");
						$access = $acc['type'];
					}
				}
			}
		}
		if ($access == 0) $access++;
		return $access;
	} else return false;
	//
}
// Generate recovery key
function generate_recovery_key($length) {
    return substr(bin2hex(random_bytes(32)), 0, (int)$length);
}

// Calculate discount
function calculate_discount($orig, $new) {
	$orig = (int)$orig;
	$new = (int)$new;

	$tmp = '';
	if ($new >= $orig) {
		if ($new != $orig) {
			$calc = ($new/$orig) - 1;
			$calc *= 100;
			$tmp = '+'. floor($calc) .'%';
		} else $tmp = '0%';
	} else {
		$calc = 1 - ($new/$orig);
		$calc *= 100;
		$tmp = '-'. floor($calc) .'%';
	}
	return $tmp;
}

// Proper URLs
function url($path = false) {
	$folder   = dirname($_SERVER['SCRIPT_NAME']);
	return config('site_url') . '/' . $path;
}

function getCache() {
	$results = mysql_select_single("SELECT `cached` FROM `znote`;");
	return ($results !== false) ? $results['cached'] : false;
}

function setCache($time) {
	$time = (int)$time;
	mysql_update("UPDATE `znote` set `cached`='$time'");
}

// Get visitor basic data
function znote_visitors_get_data() {
	return mysql_select_multi("SELECT `ip`, `value` FROM `znote_visitors` ORDER BY `id` DESC LIMIT 1000;");
}

// Set visitor basic data
function znote_visitor_set_data($visitor_data) {
	$exist = false;
	$ip = getIPLong();

	foreach ((array)$visitor_data as $row) {
		if ($ip == $row['ip']) {
			$exist = true;
			$value = $row['value'];
		}
	}

	if ($exist && isset($value)) {
		// Update the value
		$value++;
		mysql_update("UPDATE `znote_visitors` SET `value` = '$value' WHERE `ip` = '$ip'");
	} else {
		// Insert new row
		mysql_insert("INSERT INTO `znote_visitors` (`ip`, `value`) VALUES ('$ip', '1')");
	}
}

// Get visitor basic data
function znote_visitors_get_detailed_data($cache_time) {
	$period = (int)time() - (int)$cache_time;
	return mysql_select_multi("SELECT `ip`, `time`, `type`, `account_id` FROM `znote_visitors_details` WHERE `time` >= '$period' LIMIT 0, 50");
}

function znote_visitor_insert_detailed_data($type) {
	$type = (int)$type;
	/*
	type 0 = normal visits
	type 1 = register form
	type 2 = character creation
	type 3 = fetch highscores
	type 4 = search character
	*/
	$time = time();
	$ip = getIPLong();
	if (user_logged_in()) {
		$acc = (int)getSession('user_id');
		mysql_insert("INSERT INTO `znote_visitors_details` (`ip`, `time`, `type`, `account_id`) VALUES ('$ip', '$time', '$type', '$acc')");
	} else mysql_insert("INSERT INTO `znote_visitors_details` (`ip`, `time`, `type`, `account_id`) VALUES ('$ip', '$time', '$type', '0')");
}

function something () {
	// Make acc data compatible:
	$ip = getIPLong();
}

// Secret token
function create_token() {
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
    echo '<input type="hidden" name="token" value="'.$_SESSION['token'].'">';
}

function reset_token() {
	echo 'Reseting token<br />';
	unset($_SESSION['token']);
}

// Time based functions
// 60 seconds to 1 minute
function second_to_minute($seconds) {
	return ($seconds / 60);
}

// 1 minute to 60 seconds
function minute_to_seconds($minutes) {
	return ($minutes * 60);
}

// 60 minutes to 1 hour
function minute_to_hour($minutes) {
	return ($minutes / 60);
}

// 1 hour to 60 minutes
function hour_to_minute($hours) {
    return $hours * 60;
}

// seconds / 60 / 60 = hours.
function seconds_to_hours($seconds) {
	$minutes = second_to_minute($seconds);
	$hours = minute_to_hour($minutes);
	return $hours;
}

function remaining_seconds_to_clock($seconds) {
	return date("(H:i)",time() + $seconds);
}

/**
 * Check if name contains more than configured max words
 *
 * @param string $string
 * @return string|boolean
 */
function validate_name($string) {
	$max = config('maxW') ?? 3;
	return (str_word_count(trim($string)) > $max) ? false : trim($string);
}

// Checks if an IPv4(or localhost IPv6) address is valid
function validate_ip($ip) {
	$ipL = safeIp2Long($ip);
	$ipR = long2ip((int)$ipL);

	if ($ip === $ipR) {
		return true;
	} elseif ($ip=='::1')  {
		return true;
	} else {
		return false;
	}
}

// Fetch a config value. Etc config('vocations') will return vocation array from config.php.
function config($value) {
	global $config;
	return $config[$value] ?? null;
}

function serverEngineReal() {
	global $config;
	return $config['ServerEngineReal'] ?? ($config['ServerEngine'] ?? 'TFS_10');
}

function engineIsCanary() {
	return serverEngineReal() === 'CANARY';
}

function engineIsTFS16() {
	return serverEngineReal() === 'TFS_16';
}

function accountField($field) {
	if (engineIsCanary()) {
		if ($field === 'premium_ends_at') return '(`lastday` + (`premdays` * 86400)) AS `premium_ends_at`';
		if ($field === 'secret') return 'NULL AS `secret`';
	}
	return '`' . $field . '`';
}

function accountFieldList(array $fields) {
	return implode(', ', array_map('accountField', $fields));
}

function houseCol($name) {
	if (!engineIsCanary()) return $name;
	$map = array(
		'bid'            => 'internal_bid',
		'bid_end'        => 'bid_end_date',
		'last_bid'       => 'highest_bid',
		'highest_bidder' => 'bidder',
	);
	return $map[$name] ?? $name;
}

function houseSelect(array $fields, $prefix = '') {
	$p = ($prefix !== '') ? '`' . $prefix . '`.' : '';
	$out = array();
	foreach ($fields as $f) {
		$phys = houseCol($f);
		$out[] = ($phys === $f) ? $p . '`' . $f . '`' : $p . '`' . $phys . '` AS `' . $f . '`';
	}
	return implode(', ', $out);
}

function sqlIpWrite($ipLong) {
	if (engineIsTFS16()) {
		return "INET6_ATON('" . mysql_znote_escape_string(long2ip((int)$ipLong)) . "')";
	}
	return "'" . (int)$ipLong . "'";
}

function sqlIpSelect($column, $alias, $prefix = '') {
	$p = ($prefix !== '') ? '`' . $prefix . '`.' : '';
	if (engineIsTFS16()) {
		return 'INET_ATON(INET6_NTOA(' . $p . '`' . $column . '`)) AS `' . $alias . '`';
	}
	return $p . '`' . $column . '` AS `' . $alias . '`';
}

// Some functions uses several configurations from config.php, so it sounds
// smarter to give them the whole array instead of calling the function all the time.
function fullConfig() {
	global $config;
	return $config;
}

// Capitalize Every Word In String.
function format_character_name($name) {
	return ucwords(strtolower($name));
}

// Gets you the actual IP address even from users behind ISP proxies and so on.
function getIP() {
	/*
  $IP = '';
  if (getenv('HTTP_CLIENT_IP')) {
    $IP =getenv('HTTP_CLIENT_IP');
  } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
    $IP =getenv('HTTP_X_FORWARDED_FOR');
  } elseif (getenv('HTTP_X_FORWARDED')) {
    $IP =getenv('HTTP_X_FORWARDED');
  } elseif (getenv('HTTP_FORWARDED_FOR')) {
    $IP =getenv('HTTP_FORWARDED_FOR');
  } elseif (getenv('HTTP_FORWARDED')) {
    $IP = getenv('HTTP_FORWARDED');
  } else {
    $IP = $_SERVER['REMOTE_ADDR'];
  } */
return $_SERVER['REMOTE_ADDR'];
}

function safeIp2Long($ip) {
	return sprintf('%u', ip2long($ip));
}

// Gets you the actual IP address even from users in long type
function getIPLong() {
	return safeIp2Long(getIP());
}

// Deprecated, just use count($array) instead.
function array_length($ar) {
    return count($ar);
}
// Parameter: level, returns experience for that level from an experience table.
function level_to_experience($level) {
	return 50/3*(pow($level, 3) - 6*pow($level, 2) + 17*$level - 12);
}

// Parameter: players.hide_char returns: Status word inside a font with class identifier so it can be designed later on by CSS.
function hide_char_to_name($id) {
	$id = (int)$id;
	if ($id == 1) {
		return 'hidden';
	} else {
		return 'visible';
	}
}

// Parameter: players.online returns: Status word inside a font with class identifier so it can be designed later on by CSS.
function online_id_to_name($id) {
	$id = (int)$id;
	if ($id == 1) {
		return '<font class="status_online">ONLINE</font>';
	} else {
		return '<font class="status_offline">offline</font>';
	}
}

// Parameter: players.vocation_id. Returns: Configured vocation name.
function vocation_id_to_name($id) {
	$vocations = config('vocations');
	return (isset($vocations[$id]['name'])) ? $vocations[$id]['name'] : "{$id} - Unknown";
}

// Parameter: players.name. Returns: Configured vocation id.
function vocation_name_to_id($name) {
	$vocations = config('vocations');
	foreach ($vocations as $id => $vocation)
		if ($vocation['name'] == $name)
			return $id;
	return false;
}

// Parameter: players.group_id. Returns: Configured group name.
function group_id_to_name($id) {
    $positions = config('ingame_positions');
    return $positions[$id] ?? false;
}

function gender_exist($gender) {
	// Range of allowed gender ids, fromid toid
	if ($gender >= 0 && $gender <= 1) {
		return true;
	} else {
		return false;
	}
}

function skillid_to_name($skillid) {
    $skillname = [
        0 => 'fist fighting',
        1 => 'club fighting',
        2 => 'sword fighting',
        3 => 'axe fighting',
        4 => 'distance fighting',
        5 => 'shielding',
        6 => 'fishing',
        7 => 'experience',
        8 => 'magic level'
    ];

    return $skillname[$skillid] ?? false;
}

// Parameter: players.town_id. Returns: Configured town name.
function town_id_to_name($id) {
	$towns = config('towns');
	return (array_key_exists($id, $towns)) ? $towns[$id] : 'Missing Town';
}

// On this version we included From, because without that nowdays the email is classed directly as spam, using a From prevents that.
function email($to, $subject, $body) {
	$headers = "From: noreply@znoteaac.com\r\nContent-Type: text/plain; charset=UTF-8";
	mail($to, $subject, $body, $headers);
}

function logged_in_redirect() {
	if (user_logged_in() === true) {
		header('Location: myaccount.php');
		exit;
	}
}

function protect_page() {
	if (user_logged_in() === false) {
		header('Location: protected.php');
		exit;
	}
}

// When function is called, you will be redirected to protect_page and deny access to rest of page, as long as you are not admin.
function admin_only($user_data) {
	// Chris way
	$gotAccess = is_admin($user_data);

	if ($gotAccess == false) {
		logged_in_redirect();
		exit();
	}
}

function is_admin($user_data) {
	if (!is_array($user_data)) return false;
	if (config('ServerEngine') === 'OTHIRE')
		return in_array($user_data['id'] ?? null, config('page_admin_access')) ? true : false;
	else
		return in_array($user_data['name'] ?? null, config('page_admin_access')) ? true : false;
}

function array_sanitize(&$item) {
	$item = htmlentities(strip_tags(mysql_znote_escape_string($item)));
}

function sanitize($data) {
    return htmlspecialchars(strip_tags((string)($data ?? '')), ENT_QUOTES, 'UTF-8');
}

function output_errors($errors) {
	return '<ul><li>'. implode('</li><li>', $errors) .'</li></ul>';
}

// Resize images and create image
function resize_imagex($file, $width, $height) {
	list($w, $h) = getimagesize($file['tmp']);
	if (!function_exists('imagecreatefromstring')) {
		return false;
	}
	$ratio = max($width/$w, $height/$h);
	$h = ceil($height / $ratio);
	$x = ($w - $width / $ratio) / 2;
	$w = ceil($width / $ratio);

	$path = 'engine/guildimg/'.$file['new_name'];

	$imgString = file_get_contents($file['tmp']);

	$image = imagecreatefromstring($imgString);
	$tmp = imagecreatetruecolor($width, $height);
	imagecopyresampled($tmp, $image,
	    0, 0,
	    $x, 0,
	    $width, $height,
	    $w, $h
	);

	imagegif($tmp, $path);
	imagedestroy($image);
	imagedestroy($tmp);

	return true;
}

// Validate guild logo
function check_image($image) {
	$image_data = array(
		'new_name' => $_GET['name'].'.gif', 
		'name' => $image['name'], 
		'tmp' => $image['tmp_name'], 
		'error' => $image['error'], 
		'size' => $image['size'], 
		'type' => $image['type']
	);

	if ($image_data['type'] !== 'image/gif') {
		header('Location: guilds.php?error=Only gif images are accepted, you uploaded:['.$image_data['type'].'].&name='. $_GET['name']);
		exit;
	}

	$check = getimagesize($image_data['tmp']);
	if (!$check) {
		header('Location: guilds.php?error=Uploaded image is invalid.&name='. $_GET['name']);
		exit;
	}

	if ($check['mime'] !== 'image/gif') {
		header('Location: guilds.php?error=Only gif images accepted, you uploaded:['.$check['mime'].'].&name='. $_GET['name']);
		exit;
	}
	
	$path_info = pathinfo($image_data['name']);
	if ($path_info['extension'] !== 'gif') {
		header('Location: guilds.php?error=Only gif images accepted, you uploaded:['.$path_info['extension'].'].&name='. $_GET['name']);
		exit;
	}
	
	// Resize image
	if (resize_imagex($image_data, 100, 100)) {
		header('Location: guilds.php?name='. $_GET['name']);
		exit;
	}
}

function generateRandomString($length = 16) {
    return substr(strtoupper(bin2hex(random_bytes($length))), 0, $length);
}

function verifyGoogleReCaptcha($postResponse = null) {
	if(!isset($postResponse) || empty($postResponse)) {
		return false;
	}

	$recaptcha_api_url = 'https://www.google.com/recaptcha/api/siteverify';
	$secretKey = config('captcha_secret_key');
	$ip = $_SERVER['REMOTE_ADDR'];
	$params = 'secret='.$secretKey.'&response='.$postResponse.'&remoteip='.$ip;

	$useCurl = config('captcha_use_curl');
	if($useCurl) {
		$curl_connection = curl_init($recaptcha_api_url);

		curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $params);

		$response = curl_exec($curl_connection);
		curl_close($curl_connection);
	} else {
		$response = file_get_contents($recaptcha_api_url . '?' . $params);
	}

	$json = json_decode($response);
	return isset($json->success) && $json->success;
}

// html encoding function (encode any string to valid UTF-8 HTML)
function hhb_tohtml(/*string*/ $str)/*:string*/ {
	return htmlentities($str, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE | ENT_DISALLOWED, 'UTF-8', true);
}

// php5-compatibile version of php7's random_bytes()
// $crypto_strong:  a boolean value that determines if the algorithm used was "cryptographically strong"
function random_bytes_compat($length, &$crypto_strong = null) {
    $crypto_strong = false;
    if (!is_int($length)) {
        throw new \InvalidArgumentException("argument 1 must be an int, is " . gettype($length));
    }
    if ($length < 0) {
        throw new \InvalidArgumentException("length must be >= 0");
    }
    if (is_callable("random_bytes")) {
        $crypto_strong = true;
        return random_bytes($length);
    }
    if (is_callable("openssl_random_pseudo_bytes")) {
        return openssl_random_pseudo_bytes($length, $crypto_strong);
    }
    $ret = @file_get_contents("/dev/urandom", false, null, 0, $length);
    if (is_string($ret) && strlen($ret) === $length) {
        $crypto_strong = true;
        return $ret;
    }
    // fallback to non-cryptographically-secure mt_rand() implementation...
    $crypto_strong = false;
    $ret = "";
    for ($i = 0; $i < $length; ++$i) {
        $ret .= chr(mt_rand(0, 255));
    }
    return $ret;
}

// hash_equals legacy support < 5.6
if(!function_exists('hash_equals')) {
    function hash_equals($str1, $str2) {
        if(strlen($str1) != strlen($str2)) {
            return false;
        }
		$res = $str1 ^ $str2;
		$ret = 0;
		for($i = strlen($res) - 1; $i >= 0; $i--) {
			$ret |= ord($res[$i]);
		}
		return !$ret;
    }
}
/**
 * Path to the CA bundle shipped with ZnoteX, or '' when the PHP install
 * already has one configured.
 *
 * PHP on Windows ships without a certificate store: unless curl.cainfo is set
 * in php.ini, every HTTPS request fails with curl error 60. The repo carries a
 * bundle for exactly this reason - ipn.php has always pointed CURLOPT_CAINFO
 * at it - so anything making outbound HTTPS calls should use this rather than
 * asking the admin to edit php.ini.
 *
 * Absolute on purpose: callers may run with any working directory.
 */
function znote_cainfo() {
	if (ini_get('curl.cainfo') !== '' || ini_get('openssl.cafile') !== '') {
		return '';
	}

	$bundle = dirname(__DIR__) . '/cert/cacert.pem';

	return is_file($bundle) ? $bundle : '';
}
