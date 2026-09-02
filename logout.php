<?php
require_once 'engine/init.php'; theme_open();

	if (isset($_SESSION)) {
		session_destroy();
		header('Location: index.php');
	}
?>
