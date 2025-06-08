<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;


require_once __DIR__ . '/../vendor/autoload.php';
$baseDir = __DIR__ . '/../../../';
$dotenv = Dotenv\Dotenv::createImmutable($baseDir);
$envFile = "$baseDir.env";
if(file_exists($envFile)) {
    $dotenv->load();
}

$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT']); 

$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

require_once __DIR__ . '/Dependencies.php';
require_db();

$app->group('/api/v1', function (Group $group) : void {
    $group->get('/film', function (Request $request, Response $response) {
        $apiKey = getenv('OMDB_API_KEY');

        if (!$apiKey) {
            return jsonResponse($response, ['erreur' => "Clé API OMDb manquante."], 500);
        }

        $randomNumber = random_int(1, 9916880);
        $randomId = 'tt' . str_pad((string)$randomNumber, 7, '0', STR_PAD_LEFT);

        $client = new \GuzzleHttp\Client();

        try {
            $res = $client->get("http://www.omdbapi.com/?i={$randomId}&apikey={$apiKey}&");
            $data = json_decode($res->getBody()->getContents(), true);

            if ($data['Response'] === 'False') {
                return jsonResponse($response, ['erreur' => "Film non trouvé. Veuillez réessayer."], 404);
            }

            return jsonResponse($response, $data);
        } catch (\Exception $e) {
            return jsonResponse($response, ['erreur' => "Erreur lors de la récupération du film."], 500);
        }
    });

    $group->post('/note', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        
        if (empty($data['tconst']) || !isset($data['rating'])) {
            return jsonResponse($response, ['erreur' => 'Les champs tconst et rating sont obligatoires.'], 400);
        }

        $tconst = $data['tconst'];
        $rating = (float)$data['rating'];

        // Validate rating range
        if ($rating < 0.5 || $rating > 10.0) {
            return jsonResponse($response, ['erreur' => 'La note doit être entre 0.5 et 10.0.'], 400);
        }

        global $db;

        try {
            $db->insert('ratings', [
                'tconst' => $tconst,
                'rating' => $rating
            ], [
                '%s',   
                '%f'    
            ]);

            return jsonResponse($response, ['message' => 'Note enregistrée avec succès.']);
        } catch (\Exception $e) {
            return jsonResponse($response, ['erreur' => "Erreur lors de l'enregistrement de la note."], 500);
        }
    });

    $group->get('/note/{id}', function (Request $request, Response $response, $args) {
        $tconst = $args['id'];

        global $db;

        try {
            $results = $db->get_results(
                $db->prepare(
                    "SELECT COUNT(*) AS votes, AVG(rating) AS moyenne 
                        FROM ratings 
                        WHERE tconst = %s",
                    $tconst
                )
            );

            if (empty($results) || $results[0]->votes == 0) {
                return jsonResponse($response, ['message' => 'Aucune note trouvée pour ce film.'], 404);
            }

            return jsonResponse($response, [
                'tconst' => $tconst,
                'votes' => (int)$results[0]->votes,
                'moyenne' => round((float)$results[0]->moyenne, 2)
            ]);
        } catch (\Exception $e) {
            return jsonResponse($response, ['erreur' => 'Erreur de base de données.'], 500);
        }
    });
});