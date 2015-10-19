<?php
/**
 * Bootstrap file for this Silex app
 *
 */

// CUSTOM services
use Aws\S3\S3Client;

$app['dataset_service'] = $app->share(function ($app) {
    return new \Pipo\Mapper\Service\DatasetService($app['db']);
});
$app['histograph_service'] = $app->share(function ($app) {
    return new \Pipo\Mapper\Service\HistographService($app);
});

// FLY SYSTEM
$AwsClient = S3Client::factory([
    'key'    => $app['aws_key'],
    'secret' => $app['aws_secret'],
    'region' => $app['aws_region'],
]);

$app->register(new WyriHaximus\SliFly\FlysystemServiceProvider(), [
    'flysystem.filesystems' => [
        /*'local__DIR__' => [
            'adapter' => 'League\Flysystem\Adapter\Local',
            'args' => [
                __DIR__,
            ],
        ],*/
        'AWS' => [
            'adapter' => 'League\Flysystem\AwsS3v2\AwsS3Adapter',
            'args' => [
                $AwsClient,
                $app['aws_bucket']
            ],
        ],
    ],
]);

// TWIG
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.options' => array(
        'cache'            => isset($app['twig.options.cache']) ? $app['twig.options.cache'] : false,
        'strict_variables' => true
    ),
    'twig.path'    => array(__DIR__ . '/../app/views')
));

// TWIG extensions
/*$app["twig"] = $app->share($app->extend("twig", function (\Twig_Environment $twig, Silex\Application $app) {
    //$twig->addExtension(new \Pipo\Mapper\Twig\StatusFilter($app));
    return $twig;
}));*/


// DOCTRINE DBAL
$app->register(new Silex\Provider\DoctrineServiceProvider());


$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// SYMFONY FORM THING
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());

// CACHE
$app->register(new Silex\Provider\HttpCacheServiceProvider());


return $app;
