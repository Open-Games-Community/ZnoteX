<?php
require_once 'engine/init.php';
// To direct users here, add: protect_page(); Here before loading header.
theme_open();

view('protected');

theme_close();
