<?php

use Symfony\Component\HttpFoundation\Request;

$loader = require __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();

// CONFIG
require_once __DIR__ . '/../app/config/parameters.php';

// BOOTSTRAP this here app
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/routes.php';

// added basic auth middlewware
$app->get('/', function (Request $request) use ($app) {
    return $app['twig']->render('home.html.twig');
})->bind('home');

$app->get('/login', function (Request $request) use ($app) {
    return $app->redirect($app['url_generator']->generate('home'));
})->bind('login');

$app = (new Stack\Builder())
    ->push('Dflydev\Stack\BasicAuthentication', [
        'firewall' => [
            ['path' => '/', 'anonymous' => true],
            ['path' => '/login'],
            ['path' => '/sets'],
        ],
        'authenticator' => function ($username, $password) use ($app) {
            if (isset($app['users'][$username]) && $app['users'][$username] === $password) {
                return 'pipo-user-token';
            }
        },
        'realm' => 'here there be dragons',
    ])
    ->resolve($app);

$request = Request::createFromGlobals();
$response = $app->handle($request)->send();
$app->terminate($request, $response);


// DEV
//$app->run();

// PROD
//$app['http_cache']->run();


