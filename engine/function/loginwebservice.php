<?php

function lws_is_request(): bool {
	if (!config('login_web_service') || config('ServerEngine') !== 'TFS_10') {
		return false;
	}

	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
		return false;
	}

	$body = file_get_contents('php://input');
	if ($body === false || $body === '') {
		return false;
	}

	$decoded = json_decode($body);
	if (!is_object($decoded) || !isset($decoded->type)) {
		return false;
	}

	$GLOBALS['lws_request'] = $decoded;
	return true;
}

function lws_field($value, bool $raw = false) {
	if (!is_scalar($value)) {
		return false;
	}

	return $raw ? (string)$value : sanitize((string)$value);
}

function lws_send($message): void {
	die(json_encode($message));
}

function lws_error(string $message, int $code = 3): void {
	die(json_encode(array('errorCode' => $code, 'errorMessage' => $message)));
}

function lws_tier(): int {
	$forced = strtolower(trim((string)(config('login_protocol') ?? 'auto')));

	if (in_array($forced, array('11', '12', '13', '15'), true)) {
		return (int)$forced;
	}

	$client = (int)(config('client') ?? 0);

	if ($client >= 1500) return 15;
	if ($client >= 1300) return 13;
	if ($client >= 1200) return 12;

	return engineIsCanary() ? 12 : 11;
}

function lws_lua(string $key, $fallback = null) {
	static $lua = null;

	if ($lua === null) {
		$loaded = function_exists('serverdata_load') ? serverdata_load('config') : false;
		$lua = is_array($loaded) ? $loaded : array();
	}

	return $lua[$key] ?? $fallback;
}

function lws_gameserver(): array {
	$gameserver = config('gameserver');
	if (!is_array($gameserver)) {
		$gameserver = array();
	}

	$gameserver += array('ip' => '127.0.0.1', 'port' => 7172, 'name' => 'ZnoteX');

	$rows = mysql_select_multi("
		SELECT `key`, `value`
		FROM `znote_global_storage`
		WHERE `key` IN('SERVER_NAME', 'IP', 'GAME_PORT')
	");

	if ($rows !== false) {
		foreach ($rows as $row) {
			switch ($row['key']) {
				case 'SERVER_NAME': $gameserver['name'] = $row['value']; break;
				case 'IP':          $gameserver['ip']   = $row['value']; break;
				case 'GAME_PORT':   $gameserver['port'] = (int)$row['value']; break;
			}
		}
	}

	return $gameserver;
}

function lws_pvptype(): int {
	$worldType = strtolower(trim((string)lws_lua('worldType', 'pvp')));

	switch ($worldType) {
		case 'no-pvp':       return 1;
		case 'pvp-enforced': return 2;
		case 'retro-pvp':    return 3;
		case 'expert-pvp':   return 0;
		case 'pvp':          return engineIsCanary() ? 3 : 0;
	}

	return 0;
}

function lws_boosted(int $tier): array {
	$creature = znote_table_exists('boosted_creature')
		? mysql_select_single("SELECT `raceid` FROM `boosted_creature` LIMIT 1;")
		: false;

	$creatureRace = ($creature !== false) ? (int)$creature['raceid'] : 0;

	if ($tier < 12) {
		return array('raceid' => $creatureRace);
	}

	$boss = znote_table_exists('boosted_boss')
		? mysql_select_single("SELECT `raceid` FROM `boosted_boss` LIMIT 1;")
		: false;

	return array(
		'boostedcreature' => ($creatureRace > 0),
		'creatureraceid'  => $creatureRace,
		'bossraceid'      => ($boss !== false) ? (int)$boss['raceid'] : 0,
		'raceid'          => $creatureRace
	);
}

function lws_player_columns(int $tier): array {
	$columns = array(
		'name', 'sex', 'level', 'vocation', 'lookbody', 'looktype',
		'lookhead', 'looklegs', 'lookfeet', 'lookaddons', 'deletion'
	);

	if ($tier >= 12) {
		foreach (array('isreward', 'istutorial') as $optional) {
			if (znote_column_exists('players', $optional)) {
				$columns[] = $optional;
			}
		}
	}

	return $columns;
}

function lws_character(array $player, int $tier): array {
	$character = array(
		'worldid'                          => 0,
		'name'                             => $player['name'],
		'ismale'                           => ((int)$player['sex'] === 1),
		'tutorial'                         => false,
		'level'                            => (int)$player['level'],
		'vocation'                         => vocation_id_to_name($player['vocation']),
		'outfitid'                         => (int)$player['looktype'],
		'headcolor'                        => (int)$player['lookhead'],
		'torsocolor'                       => (int)$player['lookbody'],
		'legscolor'                        => (int)$player['looklegs'],
		'detailcolor'                      => (int)$player['lookfeet'],
		'addonsflags'                      => (int)$player['lookaddons'],
		'ishidden'                         => ((int)$player['deletion'] === 1),
		'istournamentparticipant'          => false,
		'remainingdailytournamentplaytime' => 0
	);

	if ($tier >= 12) {
		$character['tutorial']         = isset($player['istutorial']) && (int)$player['istutorial'] === 1;
		$character['dailyrewardstate'] = isset($player['isreward']) ? (int)$player['isreward'] : 0;
		$character['ismaincharacter']  = false;
	}

	return $character;
}

function lws_session_key(string $descriptor, string $password, $token, bool $hasSecret): string {
	$key = $descriptor . "\n" . $password;

	if (engineIsCanary()) {
		return $key;
	}

	$key .= ($hasSecret && $token !== false) ? "\n" . $token : "\n";
	$key .= "\n" . floor(time() / 30);

	return $key;
}

function lws_register_session(int $accountId, string $sessionKey): void {
	if (strtolower(trim((string)(config('login_auth_type') ?? 'password'))) !== 'session') {
		return;
	}

	if (!znote_table_exists('account_sessions')) {
		return;
	}

	$ttl = (int)(config('login_session_ttl') ?? 86400);
	if ($ttl < 60) {
		$ttl = 86400;
	}

	$id      = mysql_znote_escape_string(hash('sha256', $sessionKey));
	$now     = time();
	$expires = $now + $ttl;

	mysql_insert("
		INSERT INTO `account_sessions` (`id`, `account_id`, `expires`)
		VALUES ('{$id}', '{$accountId}', '{$expires}')
		ON DUPLICATE KEY UPDATE `account_id` = '{$accountId}', `expires` = '{$expires}';
	");

	mysql_delete("DELETE FROM `account_sessions` WHERE `expires` < '{$now}';");
}

function lws_premium(array $account): array {
	$free = (bool)(config('freePremium') ?? lws_lua('freePremium', false));
	$ends = (int)($account['premium_ends_at'] ?? 0);

	$cap = time() + (365 * 86400);
	if ($ends > $cap) {
		$ends = $cap;
	}

	return array(
		'ispremium'    => ($free || $ends > time()),
		'premiumuntil' => max(0, $ends)
	);
}

function lws_handle_login($client, int $tier): void {
	$email    = isset($client->email)       ? lws_field($client->email)            : false;
	$username = isset($client->accountname) ? lws_field($client->accountname)      : false;
	$token    = isset($client->token)       ? lws_field($client->token)            : false;
	$plain    = isset($client->password)    ? lws_field($client->password, true)   : '';

	if ($plain === false) {
		lws_error('Wrong username and/or password.');
	}

	$password = SHA1($plain);

	$fieldList = array('id', 'premium_ends_at');
	if (config('twoFactorAuthenticator')) {
		$fieldList[] = 'secret';
	}
	$fields = accountFieldList($fieldList);

	$account = false;

	if ($email !== false) {
		$fields .= ', `name`';
		$account = mysql_select_single("SELECT {$fields} FROM `accounts` WHERE `email`='{$email}' AND `password`='{$password}' LIMIT 1;");
		if ($account !== false) {
			$username = $account['name'];
		}
	} elseif ($username !== false) {
		$account = mysql_select_single("SELECT {$fields} FROM `accounts` WHERE `name`='{$username}' AND `password`='{$password}' LIMIT 1;");
	}

	if ($account === false) {
		lws_error('Wrong username and/or password.');
	}

	$hasSecret = isset($account['secret']) && $account['secret'] !== null && strlen((string)$account['secret']) > 5;

	if (config('twoFactorAuthenticator') === true && $hasSecret) {
		if ($token === false) {
			lws_error('Submit a valid two-factor authentication token.', 6);
		}

		require_once(__DIR__ . '/rfc6238.php');
		if (TokenAuth6238::verify($account['secret'], $token) !== true) {
			lws_error('Two-factor authentication failed, token is wrong.', 6);
		}
	}

	$columns = '`' . implode('`, `', lws_player_columns($tier)) . '`';
	$players = mysql_select_multi("SELECT {$columns} FROM `players` WHERE `account_id`='" . (int)$account['id'] . "' AND `deletion` = 0;");

	if ($players === false) {
		lws_error('Character list is empty.');
	}

	$gameserver = lws_gameserver();
	$descriptor = ($email !== false) ? $email : $username;
	$sessionKey = lws_session_key((string)$descriptor, $plain, $token, $hasSecret);

	lws_register_session((int)$account['id'], $sessionKey);

	$premium = lws_premium($account);
	$port    = (int)$gameserver['port'];

	$response = array(
		'session' => array(
			'fpstracking'                   => false,
			'optiontracking'                => false,
			'isreturner'                    => true,
			'returnernotification'          => false,
			'showrewardnews'                => true,
			'tournamentticketpurchasestate' => 0,
			'emailcoderequest'              => false,
			'sessionkey'                    => $sessionKey,
			'lastlogintime'                 => 0,
			'ispremium'                     => $premium['ispremium'],
			'premiumuntil'                  => $premium['premiumuntil'],
			'status'                        => 'active'
		),
		'playdata' => array(
			'worlds' => array(
				array(
					'id'                         => 0,
					'name'                       => $gameserver['name'],
					'externaladdress'            => $gameserver['ip'],
					'externalport'               => $port,
					'previewstate'               => 0,
					'location'                   => 'ALL',
					'pvptype'                    => lws_pvptype(),
					'externaladdressunprotected' => $gameserver['ip'],
					'externaladdressprotected'   => $gameserver['ip'],
					'externalportunprotected'    => $port,
					'externalportprotected'      => $port,
					'istournamentworld'          => false,
					'restrictedstore'            => false,
					'currenttournamentphase'     => 2,
					'anticheatprotection'        => false
				)
			),
			'characters' => array()
		)
	);

	foreach ($players as $player) {
		$response['playdata']['characters'][] = lws_character($player, $tier);
	}

	if ($tier >= 12 && !empty($response['playdata']['characters'])) {
		$response['playdata']['characters'][0]['ismaincharacter'] = true;
	}

	lws_send($response);
}

function lws_handle_eventschedule(): void {
	$path = rtrim((string)config('server_path'), '/\\') . '/data/XML/events.xml';

	if (!is_file($path)) {
		lws_send(array('eventlist' => array(), 'lastupdatetimestamp' => time()));
	}

	$xml = new DOMDocument;
	if (@$xml->load($path) === false) {
		lws_send(array('eventlist' => array(), 'lastupdatetimestamp' => time()));
	}

	$attr = function ($nodes, string $name) {
		foreach ($nodes as $node) {
			return $node->getAttribute($name);
		}
		return '';
	};

	$stamp = function (string $date): int {
		$parsed = date_create($date);
		return ($parsed === false) ? 0 : (int)$parsed->format('U');
	};

	$eventlist = array();

	foreach ($xml->getElementsByTagName('event') as $event) {
		$eventlist[] = array(
			'colorlight'      => $attr($event->getElementsByTagName('colors'), 'colorlight'),
			'colordark'       => $attr($event->getElementsByTagName('colors'), 'colordark'),
			'description'     => $attr($event->getElementsByTagName('description'), 'description'),
			'displaypriority' => (int)$attr($event->getElementsByTagName('details'), 'displaypriority'),
			'enddate'         => $stamp($event->getAttribute('enddate')),
			'isseasonal'      => ((int)$attr($event->getElementsByTagName('details'), 'isseasonal') === 1),
			'name'            => $event->getAttribute('name'),
			'startdate'       => $stamp($event->getAttribute('startdate')),
			'specialevent'    => (int)$attr($event->getElementsByTagName('details'), 'specialevent')
		);
	}

	lws_send(array('eventlist' => $eventlist, 'lastupdatetimestamp' => time()));
}

function lws_handle(): void {
	header('Content-Type: application/json');

	$client = $GLOBALS['lws_request'] ?? null;
	if (!is_object($client)) {
		lws_error('Type missing.');
	}

	$tier = lws_tier();
	$type = lws_field($client->type);

	switch ($type) {
		case 'cacheinfo':
			lws_send(array(
				'playersonline'        => (int)user_count_online(),
				'twitchstreams'        => 0,
				'twitchviewer'         => 0,
				'gamingyoutubestreams' => 0,
				'gamingyoutubeviewer'  => 0
			));
			break;

		case 'eventschedule':
			lws_handle_eventschedule();
			break;

		case 'boostedcreature':
			lws_send(lws_boosted($tier));
			break;

		case 'news':
			lws_send(array(
				'gamenews'       => array(),
				'categorycounts' => array(
					'support'         => 1,
					'game contents'   => 2,
					'useful info'     => 3,
					'major updates'   => 4,
					'client features' => 5
				),
				'maxeditdate' => time()
			));
			break;

		case 'login':
			lws_handle_login($client, $tier);
			break;

		default:
			lws_error('Unsupported type: ' . (($type === false) ? '?' : $type));
	}
}
