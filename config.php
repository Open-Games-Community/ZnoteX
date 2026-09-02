<?php
	if (!defined('ZNOTE_OS')) {
		$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
		define('ZNOTE_OS', ($isWindows) ? 'WINDOWS' : 'LINUX');
	}

	// Optional item browser page.
	$config['items'] = false;

	// Server engine: TFS_02, TFS_03, OTHIRE, TFS_10, TFS_16 or CANARY.
	$config['ServerEngine'] = 'TFS_10';
	$config['CustomVersion'] = false;

	// Maintenance mode. Both are editable from Admin Panel > Settings, which is
	// the normal way to use them - visitors see the message, admins keep full
	// access so you can still work on the site while it is closed.
	$config['maintenance'] = false;
	$config['maintenance_message'] = 'The website is down for maintenance. Please come back shortly.';

	$config['site_title'] = 'ZnoteX';
	$config['site_title_context'] = 'Because open communities are good communities. :3';
	$config['site_url'] = "https://opengamescommunity.com";

	// Server data folder path, without trailing slash.
	$config['server_path'] = '';

	// -------- \\
	// DATABASE \\
	// -------- \\

	$config['sqlUser'] = 'tfs13';
	$config['sqlPassword'] = 'tfs13';
	$config['sqlDatabase'] = 'tfs13';
	$config['sqlHost'] = '127.0.0.1';

	// -------------------------- \\
	// CORE WEBSITE SETTINGS      \\
	// -------------------------- \\

	$config['client'] = 1098;
	$config['client_download'] = 'http://tibiaclient.otslist.eu/download/tibia'. $config['client'] .'.exe';
	$config['client_download_linux'] = 'http://tibiaclient.otslist.eu/download/tibia'. $config['client'] .'.tgz';
	$config['port'] = 7171;

	$config['status'] = array(
		'status_check' => false,
		'status_ip' => '127.0.0.1',
		'status_port' => '7171',
	);

	$config['login_web_service'] = true;
	$config['gameserver'] = array(
		'ip' => '127.0.0.1',
		'port' => 7172,
		'name' => 'Forgotten'
	);

	$config['page_admin_access'] = array(
		'firstaccountName',
		'secondaccountName',
	);

	// Outfit images.
	$config['show_outfits'] = array(
		'shop' => true,
		'highscores' => true,
		'characterprofile' => true,
		'onlinelist' => true,
		'imageServer' => 'https://outfit-images.ots.me/1285/animoutfit.php'
	);

	// Shop offers are managed from Admin Panel > Shop Manager.
	$config['shop'] = array(
		'enabled' => true,
		'loginToView' => false,
		'enableShopConfirmation' => true,
		'showImage' => true,
		// Item images.
		'imageServer' => 'items.znote.eu',
		'imageType' => 'gif',
	);

	// Vocation IDs, names and which vocation ID they got promoted from
	$config['vocations'] = array(
		0 => array(
			'name' => 'No vocation',
			'fromVoc' => false
		),
		1 => array(
			'name' => 'Sorcerer',
			'fromVoc' => false
		),
		2 => array(
			'name' => 'Druid',
			'fromVoc' => false
		),
		3 => array(
			'name' => 'Paladin',
			'fromVoc' => false
		),
		4 => array(
			'name' => 'Knight',
			'fromVoc' => false
		),
		5 => array(
			'name' => 'Master Sorcerer',
			'fromVoc' => 1
		),
		6 => array(
			'name' => 'Elder Druid',
			'fromVoc' => 2
		),
		7 => array(
			'name' => 'Royal Paladin',
			'fromVoc' => 3
		),
		8 => array(
			'name' => 'Elite Knight',
			'fromVoc' => 4
		),
		// -- MONK (Tibia 2025 summer update) --
		/*
		9 => array(
			'name' => 'Monk',
			'fromVoc' => false
		),
		10 => array(
			'name' => 'Exalted Monk',
			'fromVoc' => 9
		)
		*/
	);

	/* Vocation stat gains per level
		- Ordered by vocation ID
		- Currently used for admin_skills page. */
	$config['vocations_gain'] = array(
		0 => array(
			'hp' => 5,
			'mp' => 5,
			'cap' => 10
		),
		1 => array(
			'hp' => 5,
			'mp' => 30,
			'cap' => 10
		),
		2 => array(
			'hp' => 5,
			'mp' => 30,
			'cap' => 10
		),
		3 => array(
			'hp' => 10,
			'mp' => 15,
			'cap' => 20
		),
		4 => array(
			'hp' => 15,
			'mp' => 5,
			'cap' => 25
		),
		5 => array(
			'hp' => 5,
			'mp' => 30,
			'cap' => 10
		),
		6 => array(
			'hp' => 5,
			'mp' => 30,
			'cap' => 10
		),
		7 => array(
			'hp' => 10,
			'mp' => 15,
			'cap' => 20
		),
		8 => array(
			'hp' => 15,
			'mp' => 5,
			'cap' => 25
		),
		// -- MONK (Tibia 2025 summer update) --
		/*
		9 => array(
			'hp' => 10,
			'mp' => 10,
			'cap' => 25
		),
		10 => array(
			'hp' => 10,
			'mp' => 10,
			'cap' => 25
		),
		*/
	);
	// Town ids and names: (In RME map editor, open map, click CTRL + T to view towns, their names and their IDs.
	// townID => 'townName' ex: [1 => 'Rookgaard']
	$config['towns'] = array(
		1 => 'Rookgaard',
		2 => 'Rookgaard Tutorial Island',
		3 => 'Island Of Destiny',
		4 => 'Dawnport',
		5 => "Ab'Dendriel",
		6 => 'Carlin',
		7 => 'Kazordoon',
		8 => 'Thais',
		9 => 'Venore',
		10 => 'Ankrahmun',
		11 => 'Edron',
		12 => 'Farmine',
		13 => 'Darashia',
		14 => 'Liberty Bay',
		15 => 'Port Hope',
		16 => 'Svargrond',
		17 => 'Yalahar',
		18 => 'Gray Beach',
		19 => 'Krailos',
		20 => 'Rathleton',
		21 => 'Roshamuul',
		22 => 'Issavi'
	);

	// ---------------- \\
	// Create Character \\
	// ---------------- \\

	// Max characters on each account:
	$config['max_characters'] = 7;

	// Available character vocation users can choose (specify vocation ID).
	// Add 9 for Monk if your server has it: array(1, 2, 3, 4, 9);
	$config['available_vocations'] = array(1, 2, 3, 4);

	// Available towns (specify town ids, etc: (1, 2, 3); to display 3 town options (town id 1, 2 and 3).
	// Town IDs are the ones from $config['towns'] array
	$config['available_towns'] = array(6, 7, 8, 9);

	$config['player'] = array(
		'base' => array(
			'level' => 8,
			'health' => 185,
			'mana' => 90,
			'cap' => 470,
			'soul' => 100
		),
		// Health, mana cap etc are calculated with $config['vocations_gain'] and 'base' values of $config['player']
		'create' => array(
			'level' => 8,
			'novocation' => array( // Vocation id 0 (No vocation) special settings
				'level' => 1,
				'forceTown' => true,
				'townId' => 1
			),
			'skills' => array( // See $config['vocations'] for proper vocation names of these IDs
				// No vocation
				0 => array(
					'magic' => 0,
					'fist' => 10,
					'club' => 10,
					'axe' => 10,
					'sword' => 10,
					'dist' => 10,
					'shield' => 10,
					'fishing' => 10,
				),
				// Sorcerer
				1 => array(
					'magic' => 0,
					'fist' => 10,
					'club' => 10,
					'axe' => 10,
					'sword' => 10,
					'dist' => 10,
					'shield' => 10,
					'fishing' => 10,
				),
				// Druid
				2 => array(
					'magic' => 0,
					'fist' => 10,
					'club' => 10,
					'axe' => 10,
					'sword' => 10,
					'dist' => 10,
					'shield' => 10,
					'fishing' => 10,
				),
				// Paladin
				3 => array(
					'magic' => 0,
					'fist' => 10,
					'club' => 10,
					'axe' => 10,
					'sword' => 10,
					'dist' => 10,
					'shield' => 10,
					'fishing' => 10,
				),
				// Knight
				4 => array(
					'magic' => 0,
					'fist' => 10,
					'club' => 10,
					'axe' => 10,
					'sword' => 10,
					'dist' => 10,
					'shield' => 10,
					'fishing' => 10,
				),
				// -- MONK (Tibia 2025 summer update) --
				/*
				9 => array(
					'magic' => 0,
					'fist' => 10,
					'club' => 10,
					'axe' => 10,
					'sword' => 10,
					'dist' => 10,
					'shield' => 10,
					'fishing' => 10,
				),
				*/
			),
			'male_outfit' => array(
				'id' => 128,
				'head' => 78,
				'body' => 68,
				'legs' => 58,
				'feet' => 76
			),
			'female_outfit' => array(
				'id' => 136,
				'head' => 78,
				'body' => 68,
				'legs' => 58,
				'feet' => 76
			)
		)
	);

	// Minimum allowed letters in character name. Ex: 4 letters: "Kare".
	$config['minL'] = 3;
	// Maximum allowed letters in character name. Ex: 20 letters: "Bobkareolesofiesberg"
	$config['maxL'] = 20;
	// Maximum allowed words in character name. Ex: 2 words = "Bob Kare", 3 words: "Bob Arne Kare" as maximum char name words.
	$config['maxW'] = 3;

	//////////
	/// Let players sell, buy and bid on characters.
	/// Creates a deeper shop economy, encourages players to spend more money in shop for points.
	/// Pay to win/progress mechanic, but also lets people who can barely afford points to gain it
	/// by leveling characters to sell. It can also discourages illegal/risky third-party account
	/// services. Since players can buy officially & support the server, dodgy competitors have to sell for cheaper.
	/// Without admin interference this is organic to each individual community economy inflation.
	//////////
	$config['shop_auction'] = array(
		'characterAuction' => false, // Enable/disable this system
		// Account ID of the account that stores players in the auction.
		// Make sure storage account has a very secure password!
		'storage_account_id' => 500000, // Separate secure account ID, not your GM.
		'step' => 5, // Minimum amount someone can raise a bid by
		'step_duration' => 1 * 60 * 60, // When bidding over someone else, extend bid period by 1 hour.
		'lowestLevel' => 20, // Minimum level of sold character
		'lowestPrice' => 10, // Lowest donation points a char can be sold for.
		'biddingDuration' => 1 * 24 * 60 * 60, // = 1 day, 0 to disable bidding
		'deposit' => 10 // Seller has to add 10=10% deposit to auction which he gets back later.
	);

	// Two-factor authentication requires TFS 1.2+.
	$config['twoFactorAuthenticator'] = false;

	function getClock($time = false, $format = false, $adjust = true) {
		if ($time === false) $time = time();
		$date = "d F Y (H:i)";
		if ($adjust) $adjust = (1 * 3600);
		else $adjust = 0;
		if ($format) return date($date, $time+$adjust);
		else return $time+$adjust;
	}

	// --------------- \\
	// SECURITY STUFF  \\
	// --------------- \\
	$config['use_token'] = false;
	// Set up captcha keys on https://www.google.com/recaptcha/
	$config['use_captcha'] = false;
	$config['captcha_site_key'] = "Site key";
	$config['captcha_secret_key'] = "Secret key";
	$config['captcha_use_curl'] = false; // Set to false if you don't have cURL installed, otherwise set it to true

	// Session prefix, if you are hosting multiple sites, make the session name different to avoid conflict.
	$config['session_prefix'] = 'znote_';

	// TFS 1.x powergamers and top online
	// Before enabling powergamers, make sure that you have added Lua files and added the SQL columns to your server db.
	// files can be found at Lua folder.
	$config['powergamers'] = array(
		'enabled' => false, // Enable or disable page
		'limit' => 20, // Number of players that it will show.
	);

	$config['toponline'] = array(
		'enabled' => false, // Enable or disable page
		'limit' => 20, // Number of players that it will show.
	);

	// -- HOUSE AUCTION SYSTEM! (TFS 1.x, TFS 1.6 and Canary)
	// Not available on OTHIRE, TFS_02 or TFS_03: those schemas have no auction
	// columns on `houses`. Canary names them differently (internal_bid,
	// bid_end_date, highest_bid, bidder); houseCol()/houseSelect() in
	// engine/function/general.php map them, so nothing here changes per engine.
	$config['houseConfig'] = array(
		'HouseListDefaultTown' => 8, // Default town id to display when visting house list page page.
		'minimumBidSQM' => 200, // Minimum bid cost on auction (per SQM)
		'auctionPeriod' => 24 * 60 * 60, // 24 hours auction time.
		'housesPerPlayer' => 1,
		'requirePremium' => false,
		'levelToBuyHouse' => 8,
		// Instant buy with shop points
		'shopPoints' => array(
			'enabled' => true,
			// SQM count => points cost
			'cost' => array(
				1 => 10,
				25 => 15,
				60 => 25,
				100 => 30,
				200 => 40,
				300 => 50,
			),
		),
	);

	// Leave on black square in map and player should get teleported to their selected town.
	// If chars get buggy set this position to a beginner location to force players there.
	$config['default_pos'] = array(
		'x' => 5,
		'y' => 5,
		'z' => 2,
	);

	$config['war_status'] = array(
		0 => 'Pending',
		1 => 'Accepted',
		2 => 'Rejected',
		3 => 'Canceled',
		4 => 'Ended by kill limit',
		5 => 'Ended',
	);

	/* -- SUB PAGES --
		Some custom layouts/templates have custom pages, they can use
		this sub page functionality for that.
	*/
	$config['allowSubPages'] = true;

	// -------------- \\
	// WEBSITE STUFF  \\
	// -------------- \\

	// News to be displayed per page
	$config['news_per_page'] = 5;

	// Enable or disable changelog ticker in news page.
	$config['UseChangelogTicker'] = true;

	// Highscore configuration
	$config['highscore'] = array(
		'rows' => 100,
		'rowsPerPage' => 20,
		'ignoreGroupId' => 2, // Ignore this and higher group ids (staff)
	);

	// ONLY FOR TFS 0.2 (TFS 0.3/4 users don't need to care about this, as its fully loaded from db)
	$config['house'] = array(
		'house_file' => 'C:\test\Mystic Spirit_0.2.5\data\world\forgotten-house.xml',
		'price_sqm' => '50', // price per house sqm
	);

	$config['delete_character_interval'] = '3 DAY'; // Delay after user character delete request is executed, ex: 1 DAY, 2 HOUR, 3 MONTH etc.

	$config['validate_IP'] = false;
	$config['salt'] = false;

	// Use guild logo system
	$config['use_guild_logos'] = true;

	// Use country flags
	$config['country_flags'] = array(
		'enabled' => true,
		'highscores' => true,
		'onlinelist' => true,
		'characterprofile' => true,
		'server' => 'http://flag.znote.eu'
	);

	// Show advanced inventory data in character profile
	$config['EQ_shower'] = array(
		'enabled' => true,
		'equipment' => true,
		'skills' => true,
		'outfits' => true,
		// Player storage (storage_value + outfitId)
		// used to see if player has outfit.
		// see Lua scripts folder for otserv code
		'storage_value' => 10000
	);

	// Level requirement to create guild? (Just set it to 1 to allow all levels).
	$config['create_guild_level'] = 8;

	// Change Gender can be purchased in shop, or perhaps you want to allow everyone to change gender for free?
	$config['free_sex_change'] = false;

	// Do you need to have premium account to create a guild?
	$config['guild_require_premium'] = true;

	// There is a TFS 1.3 bug related to guild nicks
	// https://github.com/otland/forgottenserver/issues/2561
	// So if your using TFS 1.x, you might need to disable guild nicks until the crash has been fixed.
	$config['guild_allow_nicknames'] = true;

	$config['guildwar_enabled'] = false;

	// Use htaccess rewrite? (basically this makes website.com/username work instead of website.com/characterprofile.php?name=username
	// Linux users needs to enable mod_rewrite php extention to make it work properly, so set it to false if your lost and using Linux.
	$config['htwrite'] = true;

	// Unlock all protocol 12 client features? Free premium in config.lua? Then set this to true.
	$config['freePremium'] = true;

	// How often do you want highscores (cache) to update?
	$config['cache'] = array(
		// If you have two instances installed on same server, make each instance prefix unique
		'prefix' => 'znote_',
		// 60 * 15; // 15 minutes.
		'lifespan' => 5,
		// Store cache in memory/RAM? Requires PHP extension APCu
		'memory' => true
	);

	// Built-in FORUM
	// Enable forum, enable guildboards, level to create threads/post in them
	// How long do they have to wait to create thread or post?
	// How to design/display hidden/closed/sticky threads.
	$config['forum'] = array(
		'enabled' => true,
		'outfit_avatars' => true, // Show character outfit as forum avatar?
		'player_position' => true, // Show character position? ex: Tutor, Community Manager, God
		'guildboard' => true,
		'level' => 5,
		'cooldownPost' => 1, // 60,
		'cooldownCreate' => 1, // 180,
		'newPostsBumpThreads' => true,
		'hidden' => '<font color="orange">[H]</font>',
		'closed' => '<font color="red">[C]</font>',
		'sticky' => '<font color="green">[S]</font>',
	);

	// Guilds and guild war pages will do lots of queries on bigger databases.
	// So its recommended to require login to view them, but you can disable this
	// If you don't have any problems with load.
	$config['require_login'] = array(
		'guilds' => false,
		'guildwars' => false,
	);

	// IMPORTANT! Write a character name(that exist) that will represent website bans!
	// Or remember to create character named "God Website".
	// If you don't do this, ban from admin panel won't work properly.
	$config['website_char'] = 'God Website';

	// ---------------- \\
	//  ADVANCED STUFF  \\
	// ---------------- \\
	// API config
	$config['api'] = array(
		'debug' => false,
	);

	// website.com/gallery.php
	// website.com/admin/index.php?p=gallery
	// we use imgur as image host, and need to register app with them and add client/secret id.
	// https://github.com/Znote/ZnoteAAC/wiki/IMGUR-powered-Gallery-page
	$config['gallery'] = array(
		'Client Name' => 'ZnoteAAC-Gallery',
		'Client ID' => '4dfcdc4f2cabca6',
		'Client Secret' => '697af737777c99a8c0be07c2f4419aebb2c48ac5'
	);

	// Email Server configurations (SMTP)
	/*	Please consider using a released stable version of PHPMailer or you may run into issues.
		Download PHPMailer: https://github.com/PHPMailer/PHPMailer/releases
		Extract to ZnoteX directory (where this config.php file is located)
		Rename the folder to "PHPMailer". Then configure this with your SMTP mail settings from your email provider.
	*/
	$config['mailserver'] = array(
		'register' => false, // Send activation mail
		'accountRecovery' => false, // Recover username or password through mail
		'myaccount_verify_email' => false, // Allow user to verify their email in myaccount page
		'verify_email_points' => 0, // 0 = disabled. Give users points reward for verifying their email
		'host' => "mailserver.znote.eu", // Outgoing mail server host.
		'securityType' => 'ssl', // ssl or tls
		'port' => 465, // SMTP port number - likely to be 465(ssl) or 587(tls)
		'email' => 'noreply@znote.eu',
		'username' => 'noreply@znote.eu', // Likely the same as email
		'password' => 'emailpassword', // The password.
		'debug' => false, // Enable debugging if you have problems and are looking for errors.
		'fromName' => $config['site_title'],
	);

	// Don't touch this unless you know what you are doing. (modifying these (key value) also requires modifications in OT files data/XML/groups.xml).
	$config['ingame_positions'] = array(
		1 => 'Player',
		2 => 'Tutor',
		3 => 'Senior Tutor',
		4 => 'Gamemaster',
		5 => 'Community Manager',
		6 => 'God',
	);

	// Enable OS advanced features? false = no, true = yes
	$config['os_enabled'] = false;

	// What kind of computer are you hosting this website on?
	// Available options: LINUX or WINDOWS
	$config['os'] = ZNOTE_OS; // Use 'ZNOTE_OS' to auto-detect

	// Measure how much players are lagging in-game. (Not completed).
	$config['ping'] = false;

	// BAN STUFF - Don't touch this unless you know what you are doing.
	// You can order the lines the way you want, from top to bottom, in which order you
	// wish for them to be displayed in admin panel. Just make sure key[#] represent your description.
	$config['ban_type'] = array(
		4 => 'NOTATION_ACCOUNT',
		2 => 'NAMELOCK_PLAYER',
		3 => 'BAN_ACCOUNT',
		5 => 'DELETE_ACCOUNT',
		1 => 'BAN_IPADDRESS',
	);

	// BAN STUFF - Don't touch this unless you know what you are doing.
	// You can order the lines the way you want, from top to bot, in which order you
	// wish for them to be displayed in admin panel. Just make sure key[#] represent your description.
	$config['ban_action'] = array(
		0 => 'Notation',
		1 => 'Name Report',
		2 => 'Banishment',
		3 => 'Name Report + Banishment',
		4 => 'Banishment + Final Warning',
		5 => 'NR + Ban + FW',
		6 => 'Statement Report',
	);

	// Ban reasons, for changes beside default values to work with client,
	// you also need to edit sources (https://github.com/otland/forgottenserver/blob/master/src/enums.h#L29)
	$config['ban_reason'] = array(
		0 => 'Offensive Name',
		1 => 'Invalid Name Format',
		2 => 'Unsuitable Name',
		3 => 'Name Inciting Rule Violation',
		4 => 'Offensive Statement',
		5 => 'Spamming',
		6 => 'Illegal Advertising',
		7 => 'Off-Topic Public Statement',
		8 => 'Non-English Public Statement',
		9 => 'Inciting Rule Violation',
		10 => 'Bug Abuse',
		11 => 'Game Weakness Abuse',
		12 => 'Using Unofficial Software to Play',
		13 => 'Hacking',
		14 => 'Multi-Clienting',
		15 => 'Account Trading or Sharing',
		16 => 'Threatening Gamemaster',
		17 => 'Pretending to Have Influence on Rule Enforcement',
		18 => 'False Report to Gamemaster',
		19 => 'Destructive Behaviour',
		20 => 'Excessive Unjustified Player Killing',
		21 => 'Spoiling Auction',
	);

	// BAN STUFF
	// Ban time duration selection in admin panel
	// seconds => description
	$config['ban_time'] = array(
		3600 => '1 hour',
		21600 => '6 hours',
		43200 => '12 hours',
		86400 => '1 day',
		259200 => '3 days',
		604800 => '1 week',
		1209600 => '2 weeks',
		2592000 => '1 month',
	);

	/*	Store visitor data
		Store visitor data in the database, logging every IP visiting site,
		and how many times they have visited the site. And sometimes what
		they do on the site.

		This helps to prevent POST SPAM (like register 1000 accounts in a few seconds)
		and other things which can stress and slow down the server.

		The only downside is that database can get pretty fed up with much IP data
		if table never gets flushed once in a while. So I highly recommend you
		to configure flush_ip_logs if IPs are logged.
	*/
	$config['log_ip'] = false;

	// Flush IP logs each configured seconds, 60 * 15 = 15 minutes.
	// Set to false to entirely disable ip log flush.
	// It is important to flush for optimal performance.
	$config['flush_ip_logs'] = 59 * 27;

	/*	IP SECURTY REQUIRE: $config['log_ip'] = true;
		Configure how tight this security shall be.
		Etc: You can max click on anything/refresh page
		[max activity] 15 times, within time period 10
		seconds. During time_period, you can also only
		register 1 account and 1 character.
	*/
	$config['ip_security'] = array(
		'time_period' => 10, // In seconds
		'max_activity' => 10, // page clicks/visits
		'max_post' => 6, // register, create, highscore, character search and such actions
		'max_account' => 1, // register
		'max_character' => 1, // create char
		'max_forum_post' => 1, // create threads and post in forum
	);

	//////////////
	/// PAYPAL ///
	//////////////
	// https://www.paypal.com/

	// Write your paypal address here, and what currency you want to receive money in.
	$config['paypal'] = array(
		'enabled' => false,
		'email' => 'edit@me.com', // Example: paypal@mail.com
		'currency' => 'EUR',
		'points_per_currency' => 10, // 1 currency = ? points? [ONLY used to calculate bonuses]
		'success' => "http://".$_SERVER['HTTP_HOST']."/success.php",
		'failed' => "http://".$_SERVER['HTTP_HOST']."/failed.php",
		'ipn' => "http://".$_SERVER['HTTP_HOST']."/ipn.php",
		'showBonus' => true,
	);

	// Configure the "buy now" buttons prices, first write price, then how many points you get.
	// Giving some bonus points for higher donations will tempt users to donate more.
	$config['paypal_prices'] = array(
	//	price => points,
		1 => 45, // -10% bonus
		10 => 100, // 0% bonus
		15 => 165, // +10% bonus
		20 => 240, // +20% bonus
		25 => 325, // +30% bonus
		30 => 420, // +40% bonus
	);

	/////////////////
	/// PAGSEGURO ///
	/////////////////
	// https://pagseguro.uol.com.br/

	// Write your pagseguro address here, and what currency you want to receive money in.
	$config['pagseguro'] = array(
		'enabled' => false,
		'sandbox' => false,
		'email' => 'edit@me.com', // Example: pagseguro@mail.com
		'token' => '',
		'currency' => 'BRL',
		'product_name' => '',
		'price' => 100, // 1 real
		'ipn' => "http://".$_SERVER['HTTP_HOST']."/pagseguro_ipn.php",
		'urls' => array(
			'www' => 'pagseguro.uol.com.br',
			'ws'  => 'ws.pagseguro.uol.com.br',
			'stc' => 'stc.pagseguro.uol.com.br'
		)
	);

	if ($config['pagseguro']['sandbox']) {
		$config['pagseguro']['urls'] = array_map(function ($item) {
			return str_replace('pagseguro', 'sandbox.pagseguro', $item);
		}, $config['pagseguro']['urls']);
	}

	//////////////////
	/// PAYGOL SMS ///
	//////////////////
	// https://www.paygol.com/
	// !!! Paygol takes 60%~ of the money, and send aprox 40% to your paypal.
	// You can configure paygol to send each month, then they will send money
	// to you 1 month after receiving 50+ eur.
	$config['paygol'] = array(
		'enabled' => false,
		'serviceID' => 86648, // Service ID from paygol.com
		'secretKey' => 'xxxx-xxxx-xxxx-xxxx', // Secret key from paygol.com. Never share your secret key
		'currency' => 'SEK',
		'price' => 20,
		'points' => 20,
		'name' => '20 points',
		'returnURL' => "http://".$_SERVER['HTTP_HOST']."/success.php",
		'cancelURL' => "http://".$_SERVER['HTTP_HOST']."/failed.php"
	);

	//////////////////////////
	/// OTServers.eu voting
	//
	// Start by creating an account at OTServers.eu and add your server.
	// You can find your secret token by logging in on OTServers.eu and go to 'MY SERVER' then 'Encourage players to vote'.
	$config['otservers_eu_voting'] = [
		'enabled' => false,
		'simpleVoteUrl' => '', // This url is used if the player isn't logged in.
		'voteUrl' => 'https://api.otservers.eu/vote_link.php',
		'voteCheckUrl' => 'https://api.otservers.eu/vote_check.php',
		'secretToken' => '', // Enter your secret token. Do not share with anyone!
		'landingPage' => '/voting.php?action=reward', // The user will be redirected to this page after voting
		'points' => '1' // Amount of points to give as reward
	];

	// -------------------- \\
	// LARGE OPTIONAL LISTS \\
	// -------------------- \\

	// Achievements example. Add more entries here if achievements are enabled.
	$config['Ach'] = false;
	$config['achievements'] = array(
		35000 => array(
			'First Dragon',
			'Rumours say that you will never forget your first Dragon',
			'points' => '1',
			'img' => 'https://i.imgur.com/Nk2XDge.gif',
		),
	);

	
	// ------------------- \\
	// CUSTOM SERVER STUFF \\
	// ------------------- \\
	// Enable / disable Questlog function (true / false)
	$config['EnableQuests'] = false;

	// array for filling questlog (Questid, max value, name, end of the quest fill 1 for the last part 0 for all others)
	$config['quests'] = array(
		array(1501,100,"Killing in the Name of",0),
		array(1502,150,"Killing in the Name of",0),
		array(65001,100,"Killing in the Name of",0),
		array(65002,150,"Killing in the Name of",0),
		array(65003,300,"Killing in the Name of",0),
		array(65004,3,"Killing in the Name of",0),
		array(65005,300,"Killing in the Name of",0),
		array(65006,150,"Killing in the Name of",0),
		array(65007,200,"Killing in the Name of",0),
		array(65008,300,"Killing in the Name of",0),
		array(65009,300,"Killing in the Name of",0),
		array(65010,300,"Killing in the Name of",0),
		array(65011,300,"Killing in the Name of",0),
		array(65012,300,"Killing in the Name of",0),
		array(65013,300,"Killing in the Name of",0),
		array(65014,300,"Killing in the Name of",1),
		array(12110,2,"The Inquisition",0),
		array(12111,7,"The Inquisition",0),
		array(12112,3,"The Inquisition",0),
		array(12113,6,"The Inquisition",0),
		array(12114,3,"The Inquisition",0),
		array(12115,3,"The Inquisition",0),
		array(12116,3,"The Inquisition",0),
		array(12117,5,"The Inquisition",1),
		array(330,3,"Sam's Old Backpack",1),
		array(12121,3,"The Ape City",0),
		array(12122,5,"The Ape City",0),
		array(12123,3,"The Ape City",0),
		array(12124,3,"The Ape City",0),
		array(12125,3,"The Ape City",0),
		array(12126,3,"The Ape City",0),
		array(12127,4,"The Ape City",0),
		array(12128,3,"The Ape City",0),
		array(12129,3,"The Ape City",1),
		array(12101,1,"The Ancient Tombs",0),
		array(12102,1,"The Ancient Tombs",0),
		array(12103,1,"The Ancient Tombs",0),
		array(12104,1,"The Ancient Tombs",0),
		array(12105,1,"The Ancient Tombs",0),
		array(12106,1,"The Ancient Tombs",0),
		array(12107,1,"The Ancient Tombs",1),
		array(12022,3,"Barbarian Test Quest",0),
		array(12022,3,"Barbarian Test Quest",0),
		array(12022,3,"Barbarian Test Quest",1),
		array(12025,3,"The Ice Islands Quest",0),
		array(12026,5,"The Ice Islands Quest",0),
		array(12027,3,"The Ice Islands Quest",0),
		array(12028,2,"The Ice Islands Quest",0),
		array(12029,6,"The Ice Islands Quest",0),
		array(12030,8,"The Ice Islands Quest",0),
		array(12031,3,"The Ice Islands Quest",0),
		array(12032,4,"The Ice Islands Quest",0),
		array(12033,2,"The Ice Islands Quest",0),
		array(12034,2,"The Ice Islands Quest",0),
		array(12035,2,"The Ice Islands Quest",0),
		array(12036,6,"The Ice Islands Quest",1),
	);

	// Restricted names
	$config['invalidNameTags'] = array(
		"owner", "gamemaster", "hoster", "admin", "staff", "tibia", "account", "god", "hitler", "cm", "gm", "game master", "anal", "anus", "arse", "ass", "asses", "assfucker", "assfukka", "asshole", "arsehole", "asswhole", "assmunch", "ballsack", "wanky", "whore", "whoar", "xxx", "xx", "yaoi", "yury", "bastard", "beastial", "bestial", "bellend", "bdsm", "beastiality", "bestiality", "bitch", "bitches", "bitchin", "bitching", "bimbo", "bimbos", "blow job", "blowjob", "blowjobs", "blue waffle", "boob", "boobs", "booobs", "boooobs", "booooobs", "booooooobs", "breasts", "booty call", "brown shower", "brown showers", "boner", "bondage", "buceta", "bukake", "bukkake", "bullshit", "bull shit", "busty", "butthole", "carpet muncher", "cawk", "chink", "cipa", "clit", "clits", "clitoris", "cnut", "cock", "cocks", "cockface", "cockhead", "cockmunch", "cockmuncher", "cocksuck", "cocksucked", "cocksucking", "cocksucks", "cocksucker", "cokmuncher", "coon", "cow girl", "cow girls", "cowgirl", "cowgirls", "crap", "crotch", "cum", "cummer", "cumming", "cuming", "cums", "cumshot", "cunilingus", "cunillingus", "cunnilingus", "cunt", "cuntlicker", "cuntlicking", "cunts", "damn", "dick", "dickhead", "dildo", "dildos", "dink", "dinks", "deepthroat", "deep throat", "dog style", "doggie style", "doggiestyle", "doggy style", "doggystyle", "donkeyribber", "doosh", "douche", "duche", "dyke", "ejaculate", "ejaculated", "ejaculates", "ejaculating", "ejaculatings", "ejaculation", "ejakulate", "erotic", "erotism", "fag", "faggot", "fagging", "faggit", "faggitt", "faggs", "fagot", "fagots", "fags", "fatass", "femdom", "fingering", "footjob", "foot job", "fuck", "fucks", "fucker", "fuckers", "fucked", "fuckhead", "fuckheads", "fuckin", "fucking", "fcuk", "fcuker", "fcuking", "felching", "fellate", "fellatio", "fingerfuck", "fingerfucked", "fingerfucker", "fingerfuckers", "fingerfucking", "fingerfucks", "fistfuck", "fistfucked", "fistfucker", "fistfuckers", "fistfucking", "fistfuckings", "fistfucks", "flange", "fook", "fooker", "fucka", "fuk", "fuks", "fuker", "fukker", "fukkin", "fukking", "futanari", "futanary", "gangbang", "gangbanged", "gang bang", "gokkun", "golden shower", "goldenshower", "gaysex", "goatse", "handjob", "hand job", "hentai", "hooker", "hoer", "homo", "horny", "incest", "jackoff", "jack off", "jerkoff", "jerk off", "jizz", "knob", "kinbaku", "labia", "masturbate", "masochist", "mofo", "mothafuck", "motherfuck", "motherfucker", "mothafucka", "mothafuckas", "mothafuckaz", "mothafucked", "mothafucker", "mothafuckers", "mothafuckin", "mothafucking", "mothafuckings", "mothafucks", "mother fucker", "motherfucked", "motherfucker", "motherfuckers", "motherfuckin", "motherfucking", "motherfuckings", "motherfuckka", "motherfucks", "milf", "muff", "negro", "nigga", "nigger", "nigg", "nipple", "nipples", "nob", "nob  jokey", "nobhead", "nobjocky", "nobjokey", "numbnuts", "nutsack", "nude", "nudes", "orgy", "orgasm", "orgasms", "panty", "panties", "penis", "playboy", "pinto", "porn", "porno", "pornography", "pron", "punheta", "pussy", "pussies", "puta", "rape", "raping", "rapist", "rectum", "retard", "rimming", "sadist", "sadism", "schlong", "scrotum", "sex", "semen", "shemale", "she male", "shibari", "shibary", "shit", "shitdick", "shitfuck", "shitfull", "shithead", "shiting", "shitings", "shits", "shitted", "shitters", "shitting", "shittings", "shitty", "shota", "skank", "slut", "sluts", "smut", "smegma", "spunk", "strip club", "stripclub", "tit", "tits", "titties", "titty", "titfuck", "tittiefucker", "titties", "tittyfuck", "tittywank", "titwank", "threesome", "three some", "throating", "twat", "twathead", "twatty", "twunt", "viagra", "vagina", "vulva", "viado", "wank", "wanker",
	);
	// Comment out the below array if you want to allow players to use creature names:
	$config['creatureNameTags'] = array(
		"acolyte of the cult", "adept of the cult", "amazon", "ancient scarab", "arachnophobica", "assassin", "azure frog", "badger", "bandit", "banshee", "barbarian bloodwalker", "barbarian brutetamer", "barbarian headsplitter", "barbarian skullhunter", "bat", "bear", "behemoth", "betrayed wraith", "biting book", "black knight", "black sphinx acolyte", "blightwalker", "blood beast", "blood crab", "blood hand", "blood priest", "blue djinn", "boar", "bog frog", "bog raider", "bonebeast", "bonelord", "boogy", "brain squid", "braindeath", "breach brood", "brimstone bug", "burning book", "burning gladiator", "burster spectre", "carniphila", "carrion worm", "cave devourer", "centipede", "chakoya toolshaper", "chakoya tribewarden", "chakoya windcaller", "choking fear", "clay guardian", "clomp", "cobra", "coral frog", "corym charlatan", "corym skirmisher", "corym vanguard", "crab", "crazed beggar", "crazed summer rearguard", "crazed summer vanguard", "crazed winter rearguard", "crazed winter vanguard", "crimson frog", "crocodile", "crypt defiler", "crypt shambler", "crypt warden", "crystal spider", "crystalcrusher", "cult believer", "cult enforcer", "cult scholar", "cyclops", "cyclops drone", "cyclops smith", "dark apprentice", "dark faun", "dark magician", "dark monk", "dark torturer", "dawnfire asura", "death blob", "deathling scout", "deathling spellsinger", "deepling guard", "deepling scout", "deepling spellsinger", "deepling warrior", "deepling worker", "deepworm", "defiler", "demon outcast", "demon skeleton", "demon", "destroyer", "devourer", "diabolic imp", "diamond servant", "diremaw", "dragon hatchling", "dragon lord hatchling", "dragon lord", "dragon", "draken abomination", "draken elite", "draken spellweaver", "draken warmaster", "dread intruder", "drillworm", "dwarf geomancer", "dwarf guard", "dwarf henchman", "dwarf soldier", "dwarf", "dworc fleshhunter", "dworc venomsniper", "dworc voodoomaster", "earth elemental", "efreet", "elder bonelord", "elder wyrm", "elephant", "elf arcanist", "elf scout", "elf", "emerald damselfly", "energetic book", "energy elemental", "enfeebled silencer", "enlightened of the cult", "enraged crystal golem", "eternal guardian", "falcon knight", "falcon paladin", "faun", "fire devil", "fire elemental", "firestarter", "forest fury", "fox", "frazzlemaw", "frost dragon hatchling", "frost dragon", "frost flower asura", "fury", "gargoyle", "gazer spectre", "ghastly dragon", "ghost", "ghoul", "giant spider", "gladiator", "gloom wolf", "glooth bandit", "glooth blob", "glooth brigand", "glooth golem", "gnarlhound", "guzzlemaw", "hand of cursed fate", "haunted treeling", "hellhound", "hellflayer", "hellfire fighter", "hellspawn", "hero", "honour guard", "hunter", "hydra", "ice golem", "ice witch", "infernalist", "juggernaut", "killer caiman", "kongra", "lancer beetle", "lamassu", "lich", "lizard chosen", "lizard dragon priest", "lizard high guard", "lizard legionnaire", "lizard sentinel", "lizard snakecharmer", "lizard templar", "lizard zaogun", "lost soul", "lumbering carnivor", "mad scientist", "mammoth", "marid", "marsh stalker", "medusa", "menacing carnivor", "mercury blob", "merlkin", "metal gargoyle", "midnight asura", "minotaur amazon", "minotaur archer", "minotaur cult follower", "minotaur cult prophet", "minotaur cult zealot", "minotaur guard", "minotaur hunter", "minotaur mage", "minotaur", "monk", "mooh'tah warrior", "moohtant", "mummy", "mutated bat", "mutated human", "mutated rat", "mutated tiger", "necromancer", "nightmare scion", "nightmare", "nightstalker", "nomad", "novice of the cult ", "nymph", "omnivora", "orc berserker", "orc leader", "orc rider", "orc shaman", "orc warlord", "orc warrior", "orc", "pirate buccaneer", "pirate corsair", "pirate cutthroat", "pirate ghost", "pirate marauder", "pirate skeleton", "pixie", "plaguesmith", "priestess", "pooka", "ravenous lava lurker", "renegade knight", "retching horror", "ripper spectre", "roaring lion", "rot elemental", "rotworm", "rustheap golem", "scarab", "scorpion", "sea serpent", "serpent spawn", "sibang", "silencer", "skeleton elite warrior", "souleater", "spectre", "spiky carnivor", "stone golem", "stonerefiner", "swamp troll", "tarantula", "terramite", "thornback tortoise", "toad", "tortoise", "twisted pooka", "undead elite gladiator", "undead gladiator", "valkyrie", "vampire bride", "vampire viscount", "vampire", "vexclaw", "vicious squire", "vile grandmaster", "vulcongra", "wailing widow", "war golem", "war wolf", "warlock", "wasp", "water elemental", "weakened frazzlemaw", "werebadger", "werebear", "wereboar", "werefox", "werewolf", "worm priestess", "wolf", "wyrm", "wyvern", "yielothax", "young sea serpent", "zombie", "adult goanna", "black sphinx acolyte", "burning gladiator", "cobra assassin", "cobra scout", "cobra vizier", "crypt warden", "feral sphinx", "lamassu", "manticore", "ogre rowdy", "ogre ruffian", "ogre sage", "priestess of the wild sun", "sphinx", "sun-marked goanna", "young goanna", "cursed prospector", "evil prospector", "flimsy lost soul", "freakish lost soul", "mean lost soul", "a shielded astral glyph", "abyssador", "an astral glyph", "ascending ferumbras", "annihilon", "apocalypse", "apprentice sheng", "arachir the ancient one", "armenius", "azerus", "barbaria", "baron brute", "battlemaster zunzu", "bazir", "big boss trolliver", "bones", "boogey", "bretzecutioner", "brokul", "bruise payne", "brutus bloodbeard", "bullwark", "chizzoron the distorter", "coldheart", "countess sorrow", "deadeye devious", "deathbine", "deathstrike", "demodras", "dharalion", "diblis the fair", "dirtbeard", "diseased bill", "diseased dan", "diseased fred", "doomhowl", "dracola", "dreadwing", "ekatrix", "energized raging mage", "esmeralda", "ethershreck", "evil mastermind", "fatality", "fazzrah", "fernfang", "feroxa", "ferumbras", "flameborn", "fleshcrawler", "fleshslicer", "fluffy", "foreman kneebiter", "freegoiz", "fury of the emperor", "furyosa", "gaz'haragoth", "general murius", "ghazbaran", "glitterscale", "gnomevil", "golgordan", "grand mother foulscale", "groam", "grorlam", "gorgo", "hairman the huge", "haunter", "hellgorak", "hemming", "heoni", "hide", "hirintror", "horadron", "horestis", "incineron", "infernatil", "inky", "jaul", "kerberos", "koshei the deathless", "kraknaknork's demon", "kraknaknork", "kroazur", "latrivan", "lethal lissy", "leviathan", "lisa", "lizard abomination", "lord of the elements", "mad mage", "mad technomancer", "madareth", "man in the cave", "massacre", "mawhawk", "menace", "mephiles", "minishabaal", "monstor", "morgaroth", "morik the gladiator", "mr. punish", "munster", "mutated zalamon", "necropharus", "obujos", "orshabaal", "paiz the pauperizer", "raging mage", "ribstride", "rocko", "ron the ripper", "rottie the rotworm", "rotworm queen", "scarlett etzel", "scorn of the emperor", "shardhead", "sharptooth", "sir valorcrest", "snake god essence", "snake thing", "spider queen", "spite of the emperor", "splasher", "stonecracker", "sulphur scuttler", "tanjis", "terofar", "teleskor", "the abomination", "the axeorcist", "the blightfather", "the bloodtusk", "the bloodweb", "the book of death", "the collector", "the count", "the weakened count", "the dreadorian", "the evil eye", "the frog prince", "the handmaiden", "the horned fox", "the keeper", "the imperor", "the many", "the noxious spawn", "the old widow", "the pale count", "the plasmother", "the snapper", "the distorted astral source", "the astral source", "thul", "tiquandas revenge", "tirecz", "tyrn", "tormentor", "tremorak", "tromphonyte", "ungreez", "ushuriel", "verminor", "versperoth", "warlord ruzad", "white pale", "wrath of the emperor", "xenia", "yaga the crone", "yakchal", "zanakeph", "zavarash", "zevelon duskbringer", "zomba", "zoralurk", "zugurosh", "zushuka", "zulazza the corruptor", "glooth bomb", "bibby bloodbath", "doctor perhaps", "mooh'tah master", "the welter"
	);

	// ------------------------------------------------------------------ \
	// LAYOUT REPOSITORY                                                  \
	// ------------------------------------------------------------------ \
	// Lets Admin Panel > Layout > Browse themes list themes hosted outside
	// this install and install them with one click.
	//
	// A theme is PHP that runs on your server, so downloading one is running
	// someone else's code. Only ever point this at a repository you trust.
	// Downloads are refused unless the URL is https and its host is in
	// allowed_hosts below.
	$config['layout_repository'] = array(
		'enabled' => true,

		// JSON catalogue of downloadable themes. See layouts/README.md for its shape.
		'index' => 'https://raw.githubusercontent.com/Open-Games-Community/ZnoteX/layouts/index.json',

		// Nothing is downloaded from a host that is not on this list.
		'allowed_hosts' => array(
			'raw.githubusercontent.com',
			'codeload.github.com',
			'github.com',
			'objects.githubusercontent.com',
		),

		// How long the catalogue is kept before being fetched again, in seconds.
		'cache_time' => 3600,

		// Largest theme archive accepted, in megabytes.
		'max_size_mb' => 64,
	);

	// -------------------------------------------------------------------- \
	// LOCAL OVERRIDES                                                       \
	// -------------------------------------------------------------------- \
	// config.local.php holds the values specific to THIS install - database
	// credentials, server engine, site name, admin names. The installer writes
	// it, and it is included last so it wins over everything above.
	//
	// The point is that config.php stays replaceable: a ZnoteX update can ship
	// a new one without touching your settings. Keep config.local.php out of
	// version control and out of backups you share.
	if (is_file(__DIR__ . '/config.local.php')) {
		require __DIR__ . '/config.local.php';
	}
