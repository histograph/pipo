<?php

namespace Pipo\Mapper\Provider;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class ApiControllerProvider implements ControllerProviderInterface {

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/delete-csv/{id}', array(new self(), 'deleteCSV'))->bind('api-delete-csv')->assert('id', '\d+');
        $controllers->get('/choose-csv/{id}/{datasetId}', array(new self(), 'chooseCsv'))
            ->bind('api-choose-csv')
            ->assert('id', '\d+')
            ->assert('datasetId', '\w+');

        //$controllers->post('/record/choose-pit/{id}', array(new self(), 'choosePit'))->bind('api-choose-pit')->assert('id', '\d+');
        return $controllers;
    }


    /**
     * Select the csv to use for a dataset
     *
     * @param Application $app
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function chooseCSV(Application $app, $id, $datasetId)
    {
        if ($app['dataset_service']->chooseCsv($id, $datasetId)){
            return $app->json(array('id' => $id));
        }

        return $app->json(array('error' => 'Csv could not be used'), 503);
    }

    /**
     * Delete the csv
     *
     * @param Application $app
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function deleteCSV(Application $app, $id)
    {
        if ($app['dataset_service']->deleteCsv($id, $app['upload_dir'])){
            return $app->json(array('id' => $id));
        }

        return $app->json(array('error' => 'Csv could not be removed'), 503);
    }

    /**
     * Example POST
     * @param Application $app
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function chooseThing(Application $app, Request $request, $id)
    {
        $jsonData = json_decode($data = $request->getContent());
        $data = [];

        return $app->json(array('id' => $id), 503);
    }

}