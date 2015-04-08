<?php
/**
 *
 * Routes for this Silex app
 *
 */

// homepage
$app->get('/', function () use ($app) {
    return $app['twig']->render('home.html.twig');
});

//upload
$app->get('/upload', function () use ($app) {
    return $app['twig']->render('upload.html.twig');
})->bind('upload');


// a complete set of routes
$app->mount('/datasets', new \Pipo\Mapper\Provider\DataSetControllerProvider());

// Error route
$app->error(function (\Exception $e, $code) use ($app) {
    switch ($code) {
        case 404:
            $message = $app['twig']->render('404.html.twig');
            break;
        default:
            $message = 'We are sorry, but something went terribly wrong.';
    }

    if ($app['debug']) {
        $message .= ' Error Message: ' . $e->getMessage();
    }

    return new Symfony\Component\HttpFoundation\Response($message, $code);
});

return $app;