<?php
require_once 'engine/init.php';

znote_session_destroy();
header('Location: index.php');
exit;
