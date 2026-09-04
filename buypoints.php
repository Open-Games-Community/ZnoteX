<?php require_once 'engine/init.php';
protect_page();
theme_open();

if (empty($config['buypoints_enabled'])) {
	echo '<p>Buying points is currently disabled.</p>';
	theme_close();
	exit();
}

// Import from config:
$pagseguro = $config['pagseguro'];
$paypal = $config['paypal'];
$prices = $config['paypal_prices'];

view('buypoints');

theme_close();
