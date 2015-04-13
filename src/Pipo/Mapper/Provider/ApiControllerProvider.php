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

        $controllers->get('/record/unmap/{id}', array(new self(), 'clearStandardization'))->bind('api-clear-mapping')->assert('id', '\d+');
        $controllers->get('/record/map/{id}', array(new self(), 'setStandardization'))->bind('api-set-mapping')->assert('id', '\d+');
        $controllers->get('/record/ummappable/{id}', array(new self(), 'setUnmappable'))->bind('api-unmappable')->assert('id', '\d+');

        $controllers->post('/record/choose-pit/{id}', array(new self(), 'choosePit'))->bind('api-choose-pit')->assert('id', '\d+');
        return $controllers;
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
        if ($app['dataset_service']->setRecordAsUnmappable($id)){
            return $app->json(array('id' => $id));
        }

        return $app->json(array('error' => 'Record could not be updated'), 503);
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