<?php

return array(

		'Site' => array(
			'site_title' => array(
				'label' => 'Site title',
				'type'  => 'text',
				'help'  => 'Shown in the browser tab, the footer and the social banners.',
			),
			'site_title_context' => array(
				'label' => 'Tagline',
				'type'  => 'text',
			),
			'site_url' => array(
				'label' => 'Site URL',
				'type'  => 'text',
				'help'  => 'Used in e-mails and absolute links. No trailing slash.',
			),
		),

		'Maintenance' => array(
			'maintenance' => array(
				'label' => 'Maintenance mode',
				'type'  => 'bool',
				'help'  => 'Visitors see the message below. Admins keep full access.',
			),
			'maintenance_message' => array(
				'label' => 'Maintenance message',
				'type'  => 'textarea',
			),
		),

		'Game server' => array(
			'client' => array(
				'label' => 'Client version',
				'type'  => 'int',
				'help'  => 'For example 1098 for client 10.98.',
			),
			'port' => array(
				'label' => 'Game server port',
				'type'  => 'int',
			),
			'freePremium' => array(
				'label' => 'Free premium',
				'type'  => 'bool',
			),
		),

		'Characters' => array(
			'max_characters' => array(
				'label' => 'Characters per account',
				'type'  => 'int',
			),
			'minL' => array(
				'label' => 'Minimum name length',
				'type'  => 'int',
			),
			'maxL' => array(
				'label' => 'Maximum name length',
				'type'  => 'int',
			),
			'maxW' => array(
				'label' => 'Maximum words in a name',
				'type'  => 'int',
			),
			'create_guild_level' => array(
				'label' => 'Level required to create a guild',
				'type'  => 'int',
			),
		),

		'Content' => array(
			'news_per_page' => array(
				'label' => 'News articles per page',
				'type'  => 'int',
			),
			'UseChangelogTicker' => array(
				'label' => 'Changelog ticker on the front page',
				'type'  => 'bool',
			),
			'allowSubPages' => array(
				'label' => 'Allow theme sub pages',
				'type'  => 'bool',
			),
		),

		'Privacy' => array(
			'log_ip' => array(
				'label' => 'Log visitor IPs',
				'type'  => 'bool',
				'help'  => 'Feeds the Visitors page. Turning it off stops the collection entirely.',
			),
		),

		'Shop' => array(
			'shop.enabled' => array(
				'label' => 'Shop enabled',
				'type'  => 'bool',
				'help'  => 'Turns off the shop page and its menu entry.',
			),
			'shop.loginToView' => array(
				'label' => 'Require login to view the shop',
				'type'  => 'bool',
			),
			'shop.enableShopConfirmation' => array(
				'label' => 'Ask for confirmation before buying',
				'type'  => 'bool',
			),
			'shop.showImage' => array(
				'label' => 'Show item images',
				'type'  => 'bool',
			),
			'shop.imageServer' => array(
				'label' => 'Item image server',
				'type'  => 'text',
			),
			'shop.imageType' => array(
				'label' => 'Item image extension',
				'type'  => 'text',
				'help'  => 'gif or png, depending on the image server.',
			),
			'buypoints_enabled' => array(
				'label' => 'Buy points page',
				'type'  => 'bool',
				'help'  => 'Off hides buypoints.php whichever gateways are configured. Gateways themselves are on the Payments page.',
			),
		),

		'Outfit images' => array(
			'show_outfits.imageServer' => array(
				'label' => 'Outfit image server',
				'type'  => 'text',
				'help'  => 'The script that renders outfits, e.g. https://outfit-images.ots.me/1285/animoutfit.php',
			),
			'show_outfits.shop' => array(
				'label' => 'Outfits in the shop',
				'type'  => 'bool',
			),
			'show_outfits.highscores' => array(
				'label' => 'Outfits in the highscores',
				'type'  => 'bool',
			),
			'show_outfits.characterprofile' => array(
				'label' => 'Outfits on character profiles',
				'type'  => 'bool',
			),
			'show_outfits.onlinelist' => array(
				'label' => 'Outfits in the online list',
				'type'  => 'bool',
			),
		),

		'Character auction' => array(
			'shop_auction.characterAuction' => array(
				'label' => 'Character auction enabled',
				'type'  => 'bool',
				'help'  => 'Lets players sell and bid on characters for shop points.',
			),
			'shop_auction.storage_account_id' => array(
				'label' => 'Storage account ID',
				'type'  => 'int',
				'help'  => 'Holds characters while they are listed. Use a separate, secure account - not your god account.',
			),
			'shop_auction.lowestLevel' => array(
				'label' => 'Minimum level to sell',
				'type'  => 'int',
			),
			'shop_auction.lowestPrice' => array(
				'label' => 'Minimum price in points',
				'type'  => 'int',
			),
			'shop_auction.step' => array(
				'label' => 'Minimum bid increase',
				'type'  => 'int',
			),
			'shop_auction.deposit' => array(
				'label' => 'Seller deposit (%)',
				'type'  => 'int',
			),
		),

		'Forum' => array(
			'forum.enabled' => array(
				'label' => 'Forum enabled',
				'type'  => 'bool',
			),
			'forum.level' => array(
				'label' => 'Level required to post',
				'type'  => 'int',
			),
			'forum.maxImagesPerPost' => array(
				'label' => 'Images allowed per post',
				'type'  => 'int',
				'help'  => '0 blocks images entirely.',
			),
			'forum.guildboard' => array(
				'label' => 'Guild boards',
				'type'  => 'bool',
			),
			'forum.outfit_avatars' => array(
				'label' => 'Show outfits as avatars',
				'type'  => 'bool',
			),
			'forum.newPostsBumpThreads' => array(
				'label' => 'New posts bump threads',
				'type'  => 'bool',
			),
		),

		'Guilds' => array(
			'use_guild_logos' => array(
				'label' => 'Guild logo uploads',
				'type'  => 'bool',
			),
			'guild_require_premium' => array(
				'label' => 'Premium required to create a guild',
				'type'  => 'bool',
			),
			'guild_allow_nicknames' => array(
				'label' => 'Allow guild nicknames',
				'type'  => 'bool',
			),
			'guildwar_enabled' => array(
				'label' => 'Guild wars',
				'type'  => 'bool',
			),
		),

		'Highscores & lists' => array(
			'highscore.rows' => array(
				'label' => 'Highscore entries',
				'type'  => 'int',
			),
			'highscore.rowsPerPage' => array(
				'label' => 'Highscore rows per page',
				'type'  => 'int',
			),
			'highscore.ignoreGroupId' => array(
				'label' => 'Hide group ID and above',
				'type'  => 'int',
				'help'  => 'Keeps staff out of the highscores. 2 hides gamemasters upward.',
			),
			'powergamers.enabled' => array(
				'label' => 'Powergamers page',
				'type'  => 'bool',
				'help'  => 'Needs the Lua script and the extra SQL columns.',
			),
			'powergamers.limit' => array(
				'label' => 'Powergamers shown',
				'type'  => 'int',
			),
			'toponline.enabled' => array(
				'label' => 'Top online page',
				'type'  => 'bool',
			),
			'toponline.limit' => array(
				'label' => 'Top online shown',
				'type'  => 'int',
			),
		),

		'Houses' => array(
			'houseConfig.HouseListDefaultTown' => array(
				'label' => 'Default town on the house list',
				'type'  => 'int',
			),
			'houseConfig.minimumBidSQM' => array(
				'label' => 'Minimum bid per SQM',
				'type'  => 'int',
			),
			'houseConfig.housesPerPlayer' => array(
				'label' => 'Houses per player',
				'type'  => 'int',
			),
			'houseConfig.levelToBuyHouse' => array(
				'label' => 'Level required to buy a house',
				'type'  => 'int',
			),
			'houseConfig.requirePremium' => array(
				'label' => 'Premium required',
				'type'  => 'bool',
			),
			'houseConfig.shopPoints.enabled' => array(
				'label' => 'Instant buy with shop points',
				'type'  => 'bool',
			),
		),

		'Server status' => array(
			'status.status_check' => array(
				'label' => 'Query the game server for status',
				'type'  => 'bool',
			),
			'status.status_ip' => array(
				'label' => 'Status IP',
				'type'  => 'text',
			),
			'status.status_port' => array(
				'label' => 'Status port',
				'type'  => 'text',
			),
			'login_web_service' => array(
				'label' => 'Client login web service',
				'type'  => 'bool',
				'help'  => 'Required by client 11 and newer.',
			),
			'gameserver.ip' => array(
				'label' => 'Game server IP',
				'type'  => 'text',
			),
			'gameserver.port' => array(
				'label' => 'Login web service port',
				'type'  => 'int',
			),
			'gameserver.name' => array(
				'label' => 'World name',
				'type'  => 'text',
			),
		),

		'Downloads' => array(
			'client_download' => array(
				'label' => 'Windows client URL',
				'type'  => 'text',
			),
			'client_download_linux' => array(
				'label' => 'Linux client URL',
				'type'  => 'text',
			),
		),

		'Security' => array(
			'use_token' => array(
				'label' => 'CSRF tokens on forms',
				'type'  => 'bool',
			),
			'use_captcha' => array(
				'label' => 'reCaptcha',
				'type'  => 'bool',
				'help'  => 'Needs the site and secret keys below, and the cURL extension.',
			),
			'captcha_site_key' => array(
				'label' => 'reCaptcha site key',
				'type'  => 'text',
			),
			'captcha_secret_key' => array(
				'label' => 'reCaptcha secret key',
				'type'  => 'text',
			),
			'twoFactorAuthenticator' => array(
				'label' => 'Two-factor authentication',
				'type'  => 'bool',
				'help'  => 'TFS 1.2+ only. Unavailable on Canary.',
			),
			'validate_IP' => array(
				'label' => 'Tie sessions to the visitor IP',
				'type'  => 'bool',
			),
		),

		'Cache' => array(
			'cache.lifespan' => array(
				'label' => 'Cache lifetime (seconds)',
				'type'  => 'int',
			),
			'cache.memory' => array(
				'label' => 'Keep cache in memory (APCu)',
				'type'  => 'bool',
				'help'  => 'Needs the APCu extension. With it off, the cache uses files in engine/cache/.',
			),
		),

		'E-mail' => array(
			'mailserver.register' => array(
				'label' => 'Send activation mail on register',
				'type'  => 'bool',
			),
			'mailserver.accountRecovery' => array(
				'label' => 'Allow account recovery by mail',
				'type'  => 'bool',
			),
			'mailserver.myaccount_verify_email' => array(
				'label' => 'Let players verify their e-mail',
				'type'  => 'bool',
			),
			'mailserver.verify_email_points' => array(
				'label' => 'Points for verifying e-mail',
				'type'  => 'int',
				'help'  => '0 disables the reward.',
			),
			'mailserver.host' => array(
				'label' => 'SMTP host',
				'type'  => 'text',
			),
			'mailserver.port' => array(
				'label' => 'SMTP port',
				'type'  => 'int',
				'help'  => '465 for SSL, 587 for TLS.',
			),
			'mailserver.securityType' => array(
				'label' => 'SMTP security',
				'type'  => 'text',
				'help'  => 'ssl or tls.',
			),
			'mailserver.email' => array(
				'label' => 'From address',
				'type'  => 'text',
			),
			'mailserver.username' => array(
				'label' => 'SMTP username',
				'type'  => 'text',
			),
			'mailserver.password' => array(
				'label' => 'SMTP password',
				'type'  => 'text',
			),
		),
	);
