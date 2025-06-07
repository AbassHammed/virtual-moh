<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;

$container['db'] = static function (ContainerInterface $container): db {
    require_once __DIR__ . '/Database.php';
    $database = $container->get('settings')['db'];

    $db = new db(
        $database['user'],
        $database['pass'],
        $database['name'],
        $database['host'],
    );

    return $db;
};