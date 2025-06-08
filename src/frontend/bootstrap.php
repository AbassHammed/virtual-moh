<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Paris');

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('VIEWS')) {
    define('VIEWS', ABSPATH . 'views');
}
if (!defined('ASSETS')) {
    define('ASSETS', VIEWS . '/assets');
}
if (!defined('PAGES')) {
    define('PAGES', VIEWS . '/pages');
}

require_once 'views/inc/functions.php';