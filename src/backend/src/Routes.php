<?php

declare(strict_types=1);

use Slim\App;

return static function(App $app) {

    $app->group('/api/v1', function() use ($app): void {
        $app->get('/film', function ($request, $response, $args) {
            // get random movie from omdb api
        });
        $app->post('/note', function ($request, $response, $args) {
            // add a rating for a movie
        });
        $app->get('/note/{id}', function ($request, $response, $args) {
            // get average ratings for movies
        });
    });
};