<?php require_once 'engine/init.php';
protect_page();
theme_open();

if (empty($_POST) === false) {
	// $_POST['']
	$required_fields = array('name', 'selected_town');
	foreach($_POST as $key=>$value) {
		if (empty($value) && in_array($key, $required_fields) === true) {
			$errors[] = t('reg.fill_all');
			break 1;
		}
	}

	// check errors (= user exist, pass long enough
	if (empty($errors) === true) {
		if (!Token::isValid($_POST['token'])) {
			$errors[] = t('login.token_invalid');
		}
		$_POST['name'] = validate_name($_POST['name']);
		if ($_POST['name'] === false) {
			$errors[] = t('acc.name_max_words');
		} else {
			if (user_character_exist($_POST['name']) !== false) {
				$errors[] = t('acc.name_taken');
			}
			if (!preg_match("/^[a-zA-Z ]+$/", $_POST['name'])) {
				$errors[] = t('acc.name_letters');
			}
			if (strlen($_POST['name']) < $config['minL'] || strlen($_POST['name']) > $config['maxL']) {
				$errors[] = t('acc.name_length', ['min' => $config['minL'], 'max' => $config['maxL']]);
			}
			// name restriction
			$resname = explode(" ", $_POST['name']);
			$username = $_POST['name'];
			foreach($resname as $res) {
				if(in_array(strtolower($res), $config['invalidNameTags'])) {
						$errors[] = t('reg.restricted_word');
				}
				if(strlen($res) == 1) {
					$errors[] = t('reg.words_too_short');
				}
			}
			if(in_array(strtolower($username), $config['creatureNameTags'])) {
				$errors[] = t('createchar.creature_name');
			}
			// Validate vocation id
			if (!in_array((int)$_POST['selected_vocation'], $config['available_vocations'])) {
				$errors[] = t('createchar.bad_vocation');
			}
			// Validate town id
			if (!in_array((int)$_POST['selected_town'], $config['available_towns'])) {
				$errors[] = t('createchar.bad_town');
			}
			// Validate gender id
			if (!in_array((int)$_POST['selected_gender'], array(0, 1))) {
				$errors[] = t('createchar.bad_gender');
			}
			if (vocation_id_to_name($_POST['selected_vocation']) === false) {
				$errors[] = t('createchar.no_vocation');
			}
			if (town_id_to_name($_POST['selected_town']) === false) {
				$errors[] = t('createchar.no_town');
			}
			if (gender_exist($_POST['selected_gender']) === false) {
				$errors[] = t('createchar.no_gender');
			}
			// Char count
			$char_count = user_character_list_count($session_user_id);
			if ($char_count >= $config['max_characters'] && !is_admin($user_data)) {
				$errors[] = t('createchar.max_chars', ['max' => $config['max_characters']]);
			}
			if (validate_ip(getIP()) === false && $config['validate_IP'] === true) {
				$errors[] = t('reg.bad_ip');
			}
		}
	}
}

/**
 * What the view has to render: 'success', 'errors' or 'form'.
 * The character creation stays here - a theme must never carry it.
 */
$formState = 'form';

if (isset($_GET['success']) && empty($_GET['success'])) {
	$formState = 'success';

} elseif (empty($_POST) === false && empty($errors) === true) {

	if ($config['log_ip']) {
		znote_visitor_insert_detailed_data(2);
	}

	$character_data = array(
		'name'       => format_character_name($_POST['name']),
		'account_id' => $session_user_id,
		'vocation'   => $_POST['selected_vocation'],
		'town_id'    => $_POST['selected_town'],
		'sex'        => $_POST['selected_gender'],
		'lastip'     => getIPLong(),
		'created'    => time()
	);

	user_create_character($character_data);

	znote_hook('character.created', array(
		'name'       => $character_data['name'],
		'account_id' => $character_data['account_id'],
		'vocation'   => $character_data['vocation'],
	));

	header('Location: createcharacter.php?success');
	exit;

} elseif (empty($errors) === false) {
	$formState = 'errors';
}

view('createcharacter');

theme_close();
