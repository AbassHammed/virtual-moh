<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;

function require_db(): void {

    global $db;
    require_once __DIR__ . '/Database.php';

    if(isset($db)) {
        return;
    }

    $db = new db(
        getenv('DB_USER'),
        getenv('DB_PASS'),
        getenv('DB_NAME'),
        getenv('DB_HOST'),
    );
};

function jsonResponse(Response $response, array $data, int $status = 200): Response
{
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $response->getBody()->write($payload);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}