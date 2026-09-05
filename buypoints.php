<?php require_once 'engine/init.php';
protect_page();
theme_open();

if (empty($config['buypoints_enabled'])) {
	echo '<p>'. t('buypoints.off') .'</p>';
	theme_close();
	exit();
}

// Import from config:
$pagseguro = $config['pagseguro'];
$paypal = $config['paypal'];
$stripe = $config['stripe'] ?? array('enabled' => false);
$mercadopago = $config['mercadopago'] ?? array('enabled' => false);
$prices = $config['paypal_prices'];

view('buypoints');

theme_close();
