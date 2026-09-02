<?php require_once 'engine/init.php';
protect_page();
theme_open();

// Import from config:
$pagseguro = $config['pagseguro'];
$paypal = $config['paypal'];
$prices = $config['paypal_prices'];

view('buypoints');

theme_close();
