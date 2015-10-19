<?php
/**
 *
 * Routes for this Silex app
 *
 */

// a complete set of routes
$app->mount('/sets', new \Pipo\Mapper\Provider\DataSetControllerProvider());
$app->mount('/file', new \Pipo\Mapper\Provider\FileControllerProvider());
$app->mount('/api', new \Pipo\Mapper\Provider\ApiControllerProvider());
$app->mount('/aws', new \Pipo\Mapper\Provider\AmazonControllerProvider());

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