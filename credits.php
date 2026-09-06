<?php require_once 'engine/init.php';

if (!($config['credits_enabled'] ?? true)) {
	header('Location: index.php');
	exit();
}

theme_open();

$creditsMaintainer = array(
	'login'  => 'Alexv45',
	'url'    => 'https://github.com/Alexv45',
	'avatar' => 'https://avatars.githubusercontent.com/u/89811188?s=400&u=5299472333b12cff2d5ae9cff220541abb3cfb7b&v=4',
	'role'   => 'ZnoteX remaster and maintenance',
);

view('credits');

theme_close();
