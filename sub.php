<?php require_once 'engine/init.php'; theme_open();
if ($config['allowSubPages']) {
	$page = (isset($_GET['page']) && !empty($_GET['page'])) ? getValue($_GET['page'] ?? null) : '';
	if (isset($subpages[$page]['file'])) { $f = theme_file('sub/'.$subpages[$page]['file']); if ($f !== null) require_once $f; }
	else {
		if (isset($subpages)) echo '<h2>Sub page not recognized.</h2><p>The sub page you requested is not recognized.</p>';
	}
}
else echo '<h2>System disabled.</h2><p>The sub page system is disabled.</p>';
theme_close(); ?>
