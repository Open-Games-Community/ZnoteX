<?php require_once 'engine/init.php'; theme_open();

/* SETUP INSTALLATION
 - See comments above $config['gallery'] in config.php */
 
$logged_in = user_logged_in();

// Public gallery images, prepared here so the view only renders them.
$galleryCache = new Cache('engine/cache/gallery');
$images = $galleryCache->load();

view('gallery');

theme_close();
