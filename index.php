<?php if($_SERVER['HTTP_USER_AGENT'] == "Mozilla/5.0") { require_once 'login.php'; die(); } // Client 11 loginWebService
require_once 'engine/init.php';

// Serves landing/ instead of the front page when that is switched on, and stops.
landing_serve();

theme_open();

	if (!isset($_GET['page'])) {
		$page = 0;
	} else {
		$page = (int)$_GET['page'];
	}
	$view = (isset($_GET['view'])) ? urlencode($_GET['view']) : "";

	// Front page data, prepared here so the view only renders it.
	$changelogs = false;
	if ($config['UseChangelogTicker']) {
		$changelogCache = new Cache('engine/cache/changelog');
		$changelogCache->useMemory(false);
		$changelogs = $changelogCache->load();
	}

	$newsCache = new Cache('engine/cache/news');
	if ($newsCache->hasExpired()) {
		$news = fetchAllNews();
		$newsCache->setContent($news);
		$newsCache->save();
	} else {
		$news = $newsCache->load();
	}

view('index');

theme_close();
