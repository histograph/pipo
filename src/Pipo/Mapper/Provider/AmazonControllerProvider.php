<?php

namespace Pipo\Mapper\Provider;


use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class AmazonControllerProvider implements ControllerProviderInterface
{

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/upload/{id}/{file}', array(new self(), 'uploadToAmazon'))
            ->bind('upload-amazon');
        $controllers->get('/list', array(new self(), 'listContentsAmazon'))
            ->bind('list-amazon');;

        return $controllers;
    }

    /**
     * Quick helper method to see what's in the AWS bucket
     *
     * @param Application $app
     */
    public function listContentsAmazon(Application $app)
    {
        /** @var \League\Flysystem\Filesystem $flySys */
        $flySys = $app['flysystems']['AWS'];
        //var_dump($flySys->listContents('/', true));
        var_dump($flySys->listFiles('/', true));

        die;
    }

    /**
     * Uploaded the created json /ndjson files to AWS
     * One by after the post To the API, or all together when the user hits a button
     *
     * @param Application $app
     * @param string $id Dataset identifier
     * @param string $file
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function uploadToAmazon(Application $app, $id, $file = 'all')
    {
        /** @var \League\Flysystem\Filesystem $flySys */
        $flySys = $app['flysystems']['AWS'];

        $allowed = ['dataset.json', 'pits.ndjson', 'relations.ndjson'];
        $files2Upload = null;

        if ($file == 'all') {
            $files2Upload = $allowed;
        } else {
            if (in_array($file, $allowed)) {
                $files2Upload = [$file];
            }
        }

        if (count($files2Upload) > 0) {
            foreach ($files2Upload as $filename) {
                $filepath = $app['export_dir'] . '/' . $id . '/' . $filename;

                if (file_exists($filepath)) {
                    $app['monolog']->addInfo('Uploading ' . $filename . ' to AWS');
                    try {
                        // all files in a dir with the identifier of the dataset
                        $flySys->createDir($id);
                        $flySys->put($id . '/' . $id . '.' .$filename, file_get_contents($filepath));
                    } catch (\Exception $e) {
                        $app['session']->getFlashBag()->set('alert', 'Error uploading to AWS. Please try again later.');
                        $app['monolog']->addError('Failed to upload to AWS with the following error: ' . print_r($e->getMessage(), 1));
                    }
                }
            }
            $app['session']->getFlashBag()->set('alert', 'Uploading to AWS... ' . count($files2Upload) . ' file(s) done.');
        } else {
            $app['session']->getFlashBag()->set('alert', 'Nothing to upload. Probably because the json files do not exist (yet).');
        }

        return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
    }
}