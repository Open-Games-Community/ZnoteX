<?php

return array(

		t_default('acp.sec.Site', 'Site') => array(
			'site_title' => array(
				'label' => t_default('acp.set.site_title.label', 'Site title'),
				'type'  => 'text',
				'help'  => t_default('acp.set.site_title.help', 'Shown in the browser tab, the footer and the social banners.'),
			),
			'site_title_context' => array(
				'label' => t_default('acp.set.site_title_context.label', 'Tagline'),
				'type'  => 'text',
			),
			'site_url' => array(
				'label' => t_default('acp.set.site_url.label', 'Site URL'),
				'type'  => 'text',
				'help'  => t_default('acp.set.site_url.help', 'Used in e-mails and absolute links. No trailing slash.'),
			),
		),

		t_default('acp.sec.Maintenance', 'Maintenance') => array(
			'maintenance' => array(
				'label' => t_default('acp.set.maintenance.label', 'Maintenance mode'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.maintenance.help', 'Visitors see the message below. Admins keep full access.'),
			),
			'maintenance_message' => array(
				'label' => t_default('acp.set.maintenance_message.label', 'Maintenance message'),
				'type'  => 'textarea',
			),
		),

		t_default('acp.sec.Language', 'Language') => array(
			'language' => array(
				'label'   => t_default('acp.set.language.label', 'Default language'),
				'type'    => 'select',
				'options' => array(
					'en'    => 'English',
					'pt_br' => 'Português (Brasil)',
					'es'    => 'Español',
					'pl'    => 'Polski',
					'de'    => 'Deutsch',
				),
				'help'  => t_default('acp.set.language.help', 'Used when the visitor has no saved choice and their browser asks for nothing you offer.'),
			),
			'languages_enabled' => array(
				'label'   => t_default('acp.set.languages_enabled.label', 'Offered languages'),
				'type'    => 'checklist',
				'options' => array(
					'en'    => 'English',
					'pt_br' => 'Português (Brasil)',
					'es'    => 'Español',
					'pl'    => 'Polski',
					'de'    => 'Deutsch',
				),
				'help'  => t_default('acp.set.languages_enabled.help', 'Which flags the switcher shows. The default language is always kept.'),
			),
			'language_selector' => array(
				'label' => t_default('acp.set.language_selector.label', 'Show the language switcher'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.language_selector.help', 'Adds it to every theme, top right. Turn off if your theme places it itself.'),
			),
		),

		t_default('acp.sec.Game server', 'Game server') => array(
			'client' => array(
				'label' => t_default('acp.set.client.label', 'Client version'),
				'type'  => 'int',
				'help'  => t_default('acp.set.client.help', 'For example 1098 for client 10.98.'),
			),
			'port' => array(
				'label' => t_default('acp.set.port.label', 'Game server port'),
				'type'  => 'int',
			),
			'freePremium' => array(
				'label' => t_default('acp.set.freePremium.label', 'Free premium'),
				'type'  => 'bool',
			),
			'account_create_premdays' => array(
				'label' => t_default('acp.set.account_create_premdays.label', 'Premium days on account creation'),
				'type'  => 'int',
				'min'   => 0,
				'max'   => 999,
				'help'  => t_default('acp.set.account_create_premdays.help', '0 disables it. New accounts only; existing accounts are not changed.'),
			),
		),

		t_default('acp.sec.Characters', 'Characters') => array(
			'max_characters' => array(
				'label' => t_default('acp.set.max_characters.label', 'Characters per account'),
				'type'  => 'int',
			),
			'minL' => array(
				'label' => t_default('acp.set.minL.label', 'Minimum name length'),
				'type'  => 'int',
			),
			'maxL' => array(
				'label' => t_default('acp.set.maxL.label', 'Maximum name length'),
				'type'  => 'int',
			),
			'maxW' => array(
				'label' => t_default('acp.set.maxW.label', 'Maximum words in a name'),
				'type'  => 'int',
			),
			'create_guild_level' => array(
				'label' => t_default('acp.set.create_guild_level.label', 'Level required to create a guild'),
				'type'  => 'int',
			),
		),

		t_default('acp.sec.Content', 'Content') => array(
			'news_per_page' => array(
				'label' => t_default('acp.set.news_per_page.label', 'News articles per page'),
				'type'  => 'int',
			),
			'UseChangelogTicker' => array(
				'label' => t_default('acp.set.UseChangelogTicker.label', 'Changelog ticker on the front page'),
				'type'  => 'bool',
			),
			'allowSubPages' => array(
				'label' => t_default('acp.set.allowSubPages.label', 'Allow theme sub pages'),
				'type'  => 'bool',
			),
		),

		t_default('acp.sec.Privacy', 'Privacy') => array(
			'log_ip' => array(
				'label' => t_default('acp.set.log_ip.label', 'Log visitor IPs'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.log_ip.help', 'Feeds the Visitors page. Turning it off stops the collection entirely.'),
			),
		),

		t_default('acp.sec.Shop', 'Shop') => array(
			'shop.enabled' => array(
				'label' => t_default('acp.set.shop.enabled.label', 'Shop enabled'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.shop.enabled.help', 'Turns off the shop page and its menu entry.'),
			),
			'shop.loginToView' => array(
				'label' => t_default('acp.set.shop.loginToView.label', 'Require login to view the shop'),
				'type'  => 'bool',
			),
			'shop.enableShopConfirmation' => array(
				'label' => t_default('acp.set.shop.enableShopConfirmation.label', 'Ask for confirmation before buying'),
				'type'  => 'bool',
			),
			'shop.showImage' => array(
				'label' => t_default('acp.set.shop.showImage.label', 'Show item images'),
				'type'  => 'bool',
			),
			'shop.imageServer' => array(
				'label' => t_default('acp.set.shop.imageServer.label', 'Item image server'),
				'type'  => 'text',
			),
			'shop.imageType' => array(
				'label' => t_default('acp.set.shop.imageType.label', 'Item image extension'),
				'type'  => 'text',
				'help'  => t_default('acp.set.shop.imageType.help', 'gif or png, depending on the image server.'),
			),
			'buypoints_enabled' => array(
				'label' => t_default('acp.set.buypoints_enabled.label', 'Buy points page'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.buypoints_enabled.help', 'Off hides buypoints.php whichever gateways are configured. Gateways themselves are on the Payments page.'),
			),
		),

		t_default('acp.sec.Outfit images', 'Outfit images') => array(
			'show_outfits.imageServer' => array(
				'label' => t_default('acp.set.show_outfits.imageServer.label', 'Outfit image server'),
				'type'  => 'text',
				'help'  => t_default('acp.set.show_outfits.imageServer.help', 'The script that renders outfits, e.g. https://outfit-images.ots.me/1285/animoutfit.php'),
			),
			'show_outfits.shop' => array(
				'label' => t_default('acp.set.show_outfits.shop.label', 'Outfits in the shop'),
				'type'  => 'bool',
			),
			'show_outfits.highscores' => array(
				'label' => t_default('acp.set.show_outfits.highscores.label', 'Outfits in the highscores'),
				'type'  => 'bool',
			),
			'show_outfits.characterprofile' => array(
				'label' => t_default('acp.set.show_outfits.characterprofile.label', 'Outfits on character profiles'),
				'type'  => 'bool',
			),
			'show_outfits.onlinelist' => array(
				'label' => t_default('acp.set.show_outfits.onlinelist.label', 'Outfits in the online list'),
				'type'  => 'bool',
			),
		),

		t_default('acp.sec.Character auction', 'Character auction') => array(
			'shop_auction.characterAuction' => array(
				'label' => t_default('acp.set.shop_auction.characterAuction.label', 'Character auction enabled'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.shop_auction.characterAuction.help', 'Lets players sell and bid on characters for shop points.'),
			),
			'shop_auction.storage_account_id' => array(
				'label' => t_default('acp.set.shop_auction.storage_account_id.label', 'Storage account ID'),
				'type'  => 'int',
				'help'  => t_default('acp.set.shop_auction.storage_account_id.help', 'Holds characters while they are listed. Use a separate, secure account - not your god account.'),
			),
			'shop_auction.lowestLevel' => array(
				'label' => t_default('acp.set.shop_auction.lowestLevel.label', 'Minimum level to sell'),
				'type'  => 'int',
			),
			'shop_auction.lowestPrice' => array(
				'label' => t_default('acp.set.shop_auction.lowestPrice.label', 'Minimum price in points'),
				'type'  => 'int',
			),
			'shop_auction.step' => array(
				'label' => t_default('acp.set.shop_auction.step.label', 'Minimum bid increase'),
				'type'  => 'int',
			),
			'shop_auction.deposit' => array(
				'label' => t_default('acp.set.shop_auction.deposit.label', 'Seller deposit (%)'),
				'type'  => 'int',
			),
		),

		t_default('acp.sec.Forum', 'Forum') => array(
			'forum.enabled' => array(
				'label' => t_default('acp.set.forum.enabled.label', 'Forum enabled'),
				'type'  => 'bool',
			),
			'forum.level' => array(
				'label' => t_default('acp.set.forum.level.label', 'Level required to post'),
				'type'  => 'int',
			),
			'forum.maxImagesPerPost' => array(
				'label' => t_default('acp.set.forum.maxImagesPerPost.label', 'Images allowed per post'),
				'type'  => 'int',
				'help'  => t_default('acp.set.forum.maxImagesPerPost.help', '0 blocks images entirely.'),
			),
			'forum.guildboard' => array(
				'label' => t_default('acp.set.forum.guildboard.label', 'Guild boards'),
				'type'  => 'bool',
			),
			'forum.outfit_avatars' => array(
				'label' => t_default('acp.set.forum.outfit_avatars.label', 'Show outfits as avatars'),
				'type'  => 'bool',
			),
			'forum.newPostsBumpThreads' => array(
				'label' => t_default('acp.set.forum.newPostsBumpThreads.label', 'New posts bump threads'),
				'type'  => 'bool',
			),
		),

		t_default('acp.sec.Guilds', 'Guilds') => array(
			'use_guild_logos' => array(
				'label' => t_default('acp.set.use_guild_logos.label', 'Guild logo uploads'),
				'type'  => 'bool',
			),
			'guild_require_premium' => array(
				'label' => t_default('acp.set.guild_require_premium.label', 'Premium required to create a guild'),
				'type'  => 'bool',
			),
			'guild_allow_nicknames' => array(
				'label' => t_default('acp.set.guild_allow_nicknames.label', 'Allow guild nicknames'),
				'type'  => 'bool',
			),
			'guildwar_enabled' => array(
				'label' => t_default('acp.set.guildwar_enabled.label', 'Guild wars'),
				'type'  => 'bool',
			),
		),

		t_default('acp.sec.Highscores & lists', 'Highscores & lists') => array(
			'highscore.rows' => array(
				'label' => t_default('acp.set.highscore.rows.label', 'Highscore entries'),
				'type'  => 'int',
			),
			'highscore.rowsPerPage' => array(
				'label' => t_default('acp.set.highscore.rowsPerPage.label', 'Highscore rows per page'),
				'type'  => 'int',
			),
			'highscore.ignoreGroupId' => array(
				'label' => t_default('acp.set.highscore.ignoreGroupId.label', 'Hide group ID and above'),
				'type'  => 'int',
				'help'  => t_default('acp.set.highscore.ignoreGroupId.help', 'Keeps staff out of the highscores. 2 hides gamemasters upward.'),
			),
			'powergamers.enabled' => array(
				'label' => t_default('acp.set.powergamers.enabled.label', 'Powergamers page'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.powergamers.enabled.help', 'Needs the Lua script and the extra SQL columns.'),
			),
			'powergamers.limit' => array(
				'label' => t_default('acp.set.powergamers.limit.label', 'Powergamers shown'),
				'type'  => 'int',
			),
			'toponline.enabled' => array(
				'label' => t_default('acp.set.toponline.enabled.label', 'Top online page'),
				'type'  => 'bool',
			),
			'toponline.limit' => array(
				'label' => t_default('acp.set.toponline.limit.label', 'Top online shown'),
				'type'  => 'int',
			),
		),

		t_default('acp.sec.Houses', 'Houses') => array(
			'houseConfig.HouseListDefaultTown' => array(
				'label' => t_default('acp.set.houseConfig.HouseListDefaultTown.label', 'Default town on the house list'),
				'type'  => 'int',
			),
			'houseConfig.minimumBidSQM' => array(
				'label' => t_default('acp.set.houseConfig.minimumBidSQM.label', 'Minimum bid per SQM'),
				'type'  => 'int',
			),
			'houseConfig.housesPerPlayer' => array(
				'label' => t_default('acp.set.houseConfig.housesPerPlayer.label', 'Houses per player'),
				'type'  => 'int',
			),
			'houseConfig.levelToBuyHouse' => array(
				'label' => t_default('acp.set.houseConfig.levelToBuyHouse.label', 'Level required to buy a house'),
				'type'  => 'int',
			),
			'houseConfig.requirePremium' => array(
				'label' => t_default('acp.set.houseConfig.requirePremium.label', 'Premium required'),
				'type'  => 'bool',
			),
			'houseConfig.shopPoints.enabled' => array(
				'label' => t_default('acp.set.houseConfig.shopPoints.enabled.label', 'Instant buy with shop points'),
				'type'  => 'bool',
			),
		),

		t_default('acp.sec.Server status', 'Server status') => array(
			'status.status_check' => array(
				'label' => t_default('acp.set.status.status_check.label', 'Query the game server for status'),
				'type'  => 'bool',
			),
			'status.status_ip' => array(
				'label' => t_default('acp.set.status.status_ip.label', 'Status IP'),
				'type'  => 'text',
			),
			'status.status_port' => array(
				'label' => t_default('acp.set.status.status_port.label', 'Status port'),
				'type'  => 'text',
			),
			'login_web_service' => array(
				'label' => t_default('acp.set.login_web_service.label', 'Client login web service'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.login_web_service.help', 'Required by client 11 and newer.'),
			),
			'login_protocol' => array(
				'label'   => t_default('acp.set.login_protocol.label', 'Login protocol'),
				'type'    => 'select',
				'options' => array(
					'auto' => 'Auto (follow client version)',
					'11'   => 'Client 11',
					'12'   => 'Client 12 / Canary 12.x',
					'13'   => 'Client 13 / Canary 13.x',
					'15'   => 'Client 15 / Canary 15.x',
				),
				'help'  => t_default('acp.set.login_protocol.help', 'Shape of the character list the client is sent. Auto follows the client version above.'),
			),
			'login_auth_type' => array(
				'label'   => t_default('acp.set.login_auth_type.label', 'Canary auth type'),
				'type'    => 'select',
				'options' => array(
					'password' => 'Password (Canary default)',
					'session'  => 'Session (writes account_sessions)',
				),
				'help'  => t_default('acp.set.login_auth_type.help', 'Must match authType in your Canary config.lua, or nobody can enter the world.'),
			),
			'login_session_ttl' => array(
				'label' => t_default('acp.set.login_session_ttl.label', 'Session lifetime'),
				'type'  => 'int',
				'help'  => t_default('acp.set.login_session_ttl.help', 'Seconds a session auth row stays valid. Ignored on password auth.'),
			),
			'gameserver.ip' => array(
				'label' => t_default('acp.set.gameserver.ip.label', 'Game server IP'),
				'type'  => 'text',
			),
			'gameserver.port' => array(
				'label' => t_default('acp.set.gameserver.port.label', 'Login web service port'),
				'type'  => 'int',
			),
			'gameserver.name' => array(
				'label' => t_default('acp.set.gameserver.name.label', 'World name'),
				'type'  => 'text',
			),
		),

		t_default('acp.sec.Downloads', 'Downloads') => array(
			'client_download' => array(
				'label' => t_default('acp.set.client_download.label', 'Windows client URL'),
				'type'  => 'text',
			),
			'client_download_linux' => array(
				'label' => t_default('acp.set.client_download_linux.label', 'Linux client URL'),
				'type'  => 'text',
			),
		),

		t_default('acp.sec.Security', 'Security') => array(
			'use_token' => array(
				'label' => t_default('acp.set.use_token.label', 'CSRF tokens on forms'),
				'type'  => 'bool',
			),
			'use_captcha' => array(
				'label' => t_default('acp.set.use_captcha.label', 'reCaptcha'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.use_captcha.help', 'Needs the site and secret keys below, and the cURL extension.'),
			),
			'captcha_site_key' => array(
				'label' => t_default('acp.set.captcha_site_key.label', 'reCaptcha site key'),
				'type'  => 'text',
			),
			'captcha_secret_key' => array(
				'label' => t_default('acp.set.captcha_secret_key.label', 'reCaptcha secret key'),
				'type'  => 'text',
			),
			'twoFactorAuthenticator' => array(
				'label' => t_default('acp.set.twoFactorAuthenticator.label', 'Two-factor authentication'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.twoFactorAuthenticator.help', 'TFS 1.2+ only. Unavailable on Canary.'),
			),
			'validate_IP' => array(
				'label' => t_default('acp.set.validate_IP.label', 'Tie sessions to the visitor IP'),
				'type'  => 'bool',
			),
		),

		t_default('acp.sec.Cache', 'Cache') => array(
			'cache.lifespan' => array(
				'label' => t_default('acp.set.cache.lifespan.label', 'Cache lifetime (seconds)'),
				'type'  => 'int',
			),
			'cache.memory' => array(
				'label' => t_default('acp.set.cache.memory.label', 'Keep cache in memory (APCu)'),
				'type'  => 'bool',
				'help'  => t_default('acp.set.cache.memory.help', 'Needs the APCu extension. With it off, the cache uses files in engine/cache/.'),
			),
		),

		t_default('acp.sec.E-mail', 'E-mail') => array(
			'mailserver.register' => array(
				'label' => t_default('acp.set.mailserver.register.label', 'Send activation mail on register'),
				'type'  => 'bool',
			),
			'mailserver.accountRecovery' => array(
				'label' => t_default('acp.set.mailserver.accountRecovery.label', 'Allow account recovery by mail'),
				'type'  => 'bool',
			),
			'mailserver.myaccount_verify_email' => array(
				'label' => t_default('acp.set.mailserver.myaccount_verify_email.label', 'Let players verify their e-mail'),
				'type'  => 'bool',
			),
			'mailserver.verify_email_points' => array(
				'label' => t_default('acp.set.mailserver.verify_email_points.label', 'Points for verifying e-mail'),
				'type'  => 'int',
				'help'  => t_default('acp.set.mailserver.verify_email_points.help', '0 disables the reward.'),
			),
			'mailserver.host' => array(
				'label' => t_default('acp.set.mailserver.host.label', 'SMTP host'),
				'type'  => 'text',
			),
			'mailserver.port' => array(
				'label' => t_default('acp.set.mailserver.port.label', 'SMTP port'),
				'type'  => 'int',
				'help'  => t_default('acp.set.mailserver.port.help', '465 for SSL, 587 for TLS.'),
			),
			'mailserver.securityType' => array(
				'label' => t_default('acp.set.mailserver.securityType.label', 'SMTP security'),
				'type'  => 'text',
				'help'  => t_default('acp.set.mailserver.securityType.help', 'ssl or tls.'),
			),
			'mailserver.email' => array(
				'label' => t_default('acp.set.mailserver.email.label', 'From address'),
				'type'  => 'text',
			),
			'mailserver.username' => array(
				'label' => t_default('acp.set.mailserver.username.label', 'SMTP username'),
				'type'  => 'text',
			),
			'mailserver.password' => array(
				'label' => t_default('acp.set.mailserver.password.label', 'SMTP password'),
				'type'  => 'text',
			),
		),
	);
