<?php require_once 'engine/init.php'; theme_open();
if ($config['allowSubPages']) {
	$page = (isset($_GET['page']) && !empty($_GET['page'])) ? getValue($_GET['page'] ?? null) : '';
	if (isset($subpages[$page]['file'])) { $f = theme_file('sub/'.$subpages[$page]['file']); if ($f !== null) require_once $f; }
	else {
		if (isset($subpages)) echo '<h2>'. t('sub.unknown_title') .'</h2><p>'. t('sub.unknown_text') .'</p>';
	}
}
else echo '<h2>'. t('sub.disabled_title') .'</h2><p>'. t('sub.disabled_text') .'</p>';
theme_close(); ?>
