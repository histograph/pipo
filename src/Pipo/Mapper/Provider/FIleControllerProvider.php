<?php

namespace Pipo\Mapper\Provider;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class FileControllerProvider implements ControllerProviderInterface {

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/view-csv/{id}', array(new self(), 'viewCsv'))
            ->bind('file-view-csv')
            ->assert('id', '\d+')
            ;
        $controllers->get('/download-csv/{id}', array(new self(), 'downloadCsv'))
            ->bind('file-download-csv')
            ->assert('id', '\d+')
            ;
        return $controllers;
    }

    /**
     * View the csv
     *
     * @param Application $app
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function viewCsv(Application $app, $id)
    {
        $csvRecord = $app['dataset_service']->getCsv($id);

        if (!$csvRecord) {
            $app['session']->getFlashBag()->set('error', 'Die csv kan niet gevonden worden!');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        $file = $app['upload_dir'] . '/' . $csvRecord['filename'];

        $f = fopen($file, "r");

        $html = '<table class="table table-striped table-hover">';
        $firstLine = fgetcsv($f);
        $html .='<thead><tr>';
        foreach ($firstLine as $headercolumn) {
            $html .='<th>' . $headercolumn . '</th>';
        }
        $html .='</tr></thead>';
        while (($line = fgetcsv($f)) !== false) {
            $html.='<tbody><tr>';
            foreach ($line as $cell) {
                $html.='<td>' . $cell . '</td>';
            }
            $html.='</tr></tbody>';
        }
        fclose($f);
        $html.='</table>';

        return $app['twig']->render('datasets/csv.view.html.twig', array(
            'csv' => $csvRecord,
            'table' => $html
        ));
    }

    public function downloadCsv(Application $app, $id)
    {
        $csvRecord = $app['dataset_service']->getCsv($id);

        if (!$csvRecord) {
            $app['session']->getFlashBag()->set('error', 'Die csv kan niet gevonden worden!');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        $file = $app['upload_dir'] . '/' . $csvRecord['filename'];
        $stream = function () use ($file) {
            readfile($file);
        };

        return $app->stream($stream, 200, array(
            'Content-Type' => 'text/csv',
            'Content-length' => filesize($file),
            'Content-Disposition' => 'attachment; filename="' . $csvRecord['filename'] . '"'
        ));

    }

}