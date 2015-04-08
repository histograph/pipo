<?php

namespace Pipo\Mapper\Provider;

use Pipo\Mapper\Service\DatasetService;
use Silex\Application;
use Silex\ControllerProviderInterface;

use Silex\Provider\FormServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use League\Csv\Writer;

/**
 * Class DataSetControllerProvider
 * List datasets (for a certain user)
 *
 */
class DataSetControllerProvider implements ControllerProviderInterface
{

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/', array(new self(), 'listSets'))->bind('datasets-all');

        $controllers->get('/view/{id}', array(new self(), 'viewSet'))->bind('dataset-view')->value('id', null)->assert('id', '\d+');
        $controllers->get('/upload/{id}', array(new self(), 'uploadSet'))->bind('dataset-upload')->value('id', null)->assert('id', '\d+');
        $controllers->get('/map/{id}', array(new self(), 'mapSet'))->bind('dataset-map')->assert('id', '\d+');
        $controllers->get('/describe/{id}', array(new self(), 'mapSet'))->bind('dataset-describe')->assert('id', '\d+');
        $controllers->get('/validate/{id}', array(new self(), 'mapSet'))->bind('dataset-validate')->assert('id', '\d+');
        $controllers->get('/export/{id}', array(new self(), 'exportSet'))->bind('dataset-downloadcsv')->value('id', null)->assert('id', '\d+');

        $controllers->get('/delete/{id}', array(new self(), 'mapSet'))->bind('dataset-delete')->assert('id', '\d+');
        return $controllers;
    }

    /**
     * List all datasets
     *
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function listSets(Application $app)
    {
        $datasets = $app['dataset_service']->getAllSets();

        return $app['twig']->render('datasets/list.html.twig', array('datasets' => $datasets));
    }

    /**
     * Show all the details for one dataset
     *
     * @param Application $app
     * @param $id
     */
    public function mapSet(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDatasetDetails($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }

        return $app['twig']->render('datasets/map.html.twig', array('dataset' => $dataset));
    }
}