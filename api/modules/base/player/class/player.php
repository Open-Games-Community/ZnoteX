<?php

class Player {

	protected $_playerdata = array(
		'id' => null,
		'name' => null,
		'world_id' => null,
		'group_id' => null,
		'account_id' => null,
		'level' => null,
		'vocation' => null,
		'health' => null,
		'healthmax' => null,
		'experience' => null,
		'lookbody' => null,
		'lookfeet' => null,
		'lookhead' => null,
		'looklegs' => null,
		'looktype' => null,
		'lookaddons' => null,
		'maglevel' => null,
		'mana' => null,
		'manamax' => null,
		'manaspent' => null,
		'soul' => null,
		'town_id' => null,
		'posx' => null,
		'posy' => null,
		'posz' => null,
		'conditions' => null,
		'cap' => null,
		'sex' => null,
		'lastlogin' => null,
		'lastip' => null,
		'save' => null,
		'skull' => null,
		'skulltime' => null,
		'rank_id' => null,
		'guildnick' => null,
		'lastlogout' => null,
		'blessings' => null,
		'balance' => null,
		'stamina' => null,
		'direction' => null,
		'loss_experience' => null,
		'loss_mana' => null,
		'loss_skills' => null,
		'loss_containers' => null,
		'loss_items' => null,
		'premend' => null,
		'online' => null,
		'marriage' => null,
		'promotion' => null,
		'deleted' => null,
		'description' => null,
		'onlinetime' => null,
		'deletion' => null,
		'offlinetraining_time' => null,
		'offlinetraining_skill' => null,
		'skill_fist' => null,
		'skill_fist_tries' => null,
		'skill_club' => null,
		'skill_club_tries' => null,
		'skill_sword' => null,
		'skill_sword_tries' => null,
		'skill_axe' => null,
		'skill_axe_tries' => null,
		'skill_dist' => null,
		'skill_dist_tries' => null,
		'skill_shielding' => null,
		'skill_shielding_tries' => null,
		'skill_fishing' => null,
		'skill_fishing_tries' => null,
	);
	protected $_znotedata = array(
		'comment' => null,
		'created' => null,
		'hide_char' => null,
	);
	protected $_name_id = false;
	protected $_querylog = array();
	protected $_errors = array();

	public function __construct(string|int|array $name_id_array, array|string|false $fields = false, bool $query = true) {

		if (!is_array($name_id_array)) {
			$this->_name_id = $name_id_array;
		}

		if ($name_id_array !== false) {

			if (is_string($name_id_array) || is_int($name_id_array)) {
				if ($query) {
					$this->update($this->mysql_select($name_id_array, $fields));
				}
				return;
			}

			if (is_array($name_id_array)) {
				if (isset($name_id_array['id'])) {
					$this->_name_id = $name_id_array['id'];
				} elseif (isset($name_id_array['name'])) {
					$this->_name_id = $name_id_array['name'];
				}

				$this->update($name_id_array);
				return;
			}
		}

		throw new InvalidArgumentException(
			'Player constructor expects string|int|array'
		);
	}

	/**
	 * Return all player data, or the fields specified in param $fields.
	 *
	 * @param  array $fields
	 * @access public
	 * @return mixed (array 'field' => 'value', or false (bool))
	**/
	public function fetch($fields = false) {
		if (is_string($fields)) {
			$fields = [$fields];
		}
		if ($fields !== false && !is_array($fields)) {
			return false;
		}

		// Return all data that is not null.
		if (!$fields) {
			$returndata = array();
			foreach ($this->_playerdata as $field => $value) {
				if (!is_null($value)) $returndata[$field] = $value;
			}
			foreach ($this->_znotedata as $field => $value) {
				if (!is_null($value)) $returndata[$field] = $value;
			}
			return $returndata;

		} else {
			// The return array
			$returndata = array();

			// Array containing null fields, we need to fetch these from db later on.
			$missingValues = array();

			// Populate the two above arrays
			foreach ($fields as $field) {

				if (array_key_exists($field, $this->_playerdata)) {
					if (is_null($this->_playerdata[$field])) $missingValues[] = $field;
					else $returndata[$field] = $this->_playerdata[$field];

				} elseif (array_key_exists($field, $this->_znotedata)) {
					if (is_null($this->_znotedata[$field])) $missingValues[] = $field;
					else $returndata[$field] = $this->_znotedata[$field];
				}
			}

			// See if we are missing any values
			if (!empty($missingValues)) {
				// Query for this data
				$data = $this->mysql_select($this->_name_id, $missingValues);
				// Update this object
				$this->update($data);
				foreach ($data as $field => $value) {
					$returndata[$field] = $value;
				}
			}
			return $returndata;
		}
		return false;
	}

	/**
	 * Update player data.
	 *
	 * @param  array $fields
	 * @access public
	 * @return mixed (array, boolean)
	**/
	public function update(array $data): bool {
		if (is_array($data) && !empty($data)) {
			foreach ($data as $field => $value) {

				if (array_key_exists($field, $this->_playerdata)) {
					$this->_playerdata[$field] = $value;

				} elseif (array_key_exists($field, $this->_znotedata)) {
					$this->_znotedata[$field] = $value;
				}
			}
			return true;
		}
		return false;
	}

	public function getErrors() {
		return (!empty($this->_errors)) ? $this->_errors : false;
	}
	public function dumpErrors() {
		if ($this->getErrors() !== false)
			data_dump($this->getErrors(), false, "Errors detected in player class:");
	}

	/**
	 * Select player data from mysql.
	 *
	 * @param  mixed (int, string) $name_id, array $fields
	 * @access private
	 * @return mixed (array, boolean)
	**/
	private function mysql_select($name_id, $fields = false) {
		$table = 'players';
		$znote_table = 'znote_players';
		$znote_fields = array();

		// Dynamic fields logic
		switch (gettype($fields)) {
			case 'boolean':
				$field_elements = '*';
				$znote_fields = array('comment', 'created', 'hide_char');
				break;

			case 'string':
				$fields = array($fields);

			case 'array':
				// Get rid of fields related to znote_
				foreach ($fields as $key => $field) {
					if (!array_key_exists($field, $this->_playerdata)) {
						$znote_fields[] = $field;
						unset($fields[$key]);
					}
				}

				//Since we use for loop later, we need to reindex the array if we unset something.
				if (!empty($znote_fields)) $fields = array_values($fields);

				// Add 'id' field if its not already there.
				if (!in_array('id', $fields)) $fields[] = 'id';

				// Loop through every field and generate the sql string
				$allowedFields = array_keys($this->_playerdata + $this->_znotedata);

				$safeFields = [];

				foreach ($fields as $field) {
					if (!in_array($field, $allowedFields, true)) {
						continue;
					}
					$safeFields[] = "`$field`";
				}
				if (empty($safeFields)) {
					return false;
				}
				$field_elements = implode(', ', $safeFields);
			break;
		}

		// Value logic
		if (is_int($name_id)) {
			$name_id = (int)$name_id;
			$where = "`id` = '{$name_id}'";
		} else {
			$name_id = getValue($name_id);
			if ($name_id === false) {
				return false;
			}
			$where = "`name` = '{$name_id}'";
		}

		$query = "SELECT {$field_elements} FROM `{$table}` WHERE {$where} LIMIT 1;";

		// Log query to player object
		$this->_querylog[] = $query;
		// Fetch from players table
		$data = mysql_select_single($query);
		if ($data === false) {
			return false;
		}

		unset($data['conditions']);

		// Fetch from znote_players table if neccesary
		if (!empty($znote_fields)) {
			// Loop through every field and generate the sql string
			for ($i = 0; $i < count($znote_fields); $i++) {
				if ($i === 0) $field_elements = "`". getValue($znote_fields[$i]) ."`";
				else $field_elements .= ", `". getValue($znote_fields[$i]) ."`";
			}

			$query = "SELECT {$field_elements} FROM `{$znote_table}` WHERE `player_id`='".$data['id']."' LIMIT 1;";
			$this->_querylog[] = $query;
			$zdata = mysql_select_single($query);
			foreach ($zdata as $field => $value) $data[$field] = $value;
		}
		return $data;
	}

	/**
	 * Create player.
	 *
	 * @param  none
	 * @access public
	 * @return bool $status
	**/
	public function create(int $accountId): bool {
		// Player already exists
		if (!is_null($this->_playerdata['id'])) {
			$this->_errors[] = 'Player already exists.';
			return false;
		}

		// 🔐 SECURE POST ACCESS
		$name       = $_POST['name'] ?? null;
		$vocation   = $_POST['selected_vocation'] ?? null;
		$town       = $_POST['selected_town'] ?? null;
		$gender     = $_POST['selected_gender'] ?? null;

		if (!$name || !$vocation || !$town || $gender === null) {
			$this->_errors[] = 'Missing character creation data.';
			return false;
		}

		// Format & validate name
		$name = format_character_name($name);
		$name = validate_name($name);
		$name = sanitize($name);

		if ($name === false) {
			$this->_errors[] = 'Invalid character name.';
			return false;
		}

		// Check name exists
		$exist = mysql_select_single(
			"SELECT `id` FROM `players` WHERE `name`='{$name}' LIMIT 1;"
		);
		if ($exist !== false) {
			$this->_errors[] = "Character name already exists.";
			return false;
		}

		$config = fullConfig();

		// Validate vocation
		if (!in_array((int)$vocation, $config['available_vocations'], true)) {
			$this->_errors[] = 'Invalid vocation.';
		}

		// Validate town
		if (!in_array((int)$town, $config['available_towns'], true)) {
			$this->_errors[] = 'Invalid town.';
		}

		// Validate gender
		if (!in_array((int)$gender, [0, 1], true)) {
			$this->_errors[] = 'Invalid gender.';
		}

		// Stop if errors
		if (!empty($this->_errors)) {
			return false;
		}

		// Character count
		$char_count = user_character_list_count($accountId);
		if ($char_count >= $config['max_characters']) {
			$this->_errors[] = 'Maximum characters reached.';
			return false;
		}

		// Prepare insert data
		$character_data = [
			'name'       => $name,
			'account_id' => $accountId,
			'vocation'   => (int)$vocation,
			'town_id'    => (int)$town,
			'sex'        => (int)$gender,
			'lastip'     => getIPLong(),
			'created'    => time()
		];

		array_walk($character_data, 'array_sanitize');

		// Outfit
		$character_data['looktype'] = (
			$gender == 1
				? $config['maleOutfitId']
				: $config['femaleOutfitId']
		);

		// INSERT PLAYER
		mysql_insert(
			"INSERT INTO `players`
			(`name`,`account_id`,`vocation`,`town_id`,`sex`,`lastip`,`created`,`looktype`)
			VALUES
			('{$character_data['name']}',
			'{$character_data['account_id']}',
			'{$character_data['vocation']}',
			'{$character_data['town_id']}',
			'{$character_data['sex']}',
			" . sqlIpWrite($character_data['lastip']) . ",
			'{$character_data['created']}',
			'{$character_data['looktype']}')"
		);
		return true;
	}
}

/*
$this->_file = $file . self::EXT;
$this->setExpiration(config('cache_lifespan'));
$this->_lifespan = $span;
*/
