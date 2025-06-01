<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Paris');

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('VIEWS')) {
    define('VIEWS', 'views');
}
if (!defined('ASSETS')) {
    define('ASSETS', 'views/assets');
}
if (!defined('PAGES')) {
    define('PAGES', 'views/pages');
}
