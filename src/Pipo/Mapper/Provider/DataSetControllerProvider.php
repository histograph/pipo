<?php

namespace Pipo\Mapper\Provider;

use Pipo\Mapper\Service\DatasetService;
use Silex\Application;
use Silex\ControllerProviderInterface;

use Silex\Provider\FormServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

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

        $controllers->get('/new', array(new self(), 'newSet'))->bind('dataset-new');
        $controllers->post('/new', array(new self(), 'saveNewSet'))->bind('dataset-insert');
        $controllers->get('/csvs/{id}', array(new self(), 'showCsvs'))->bind('dataset-csvs')->value('id', null)->assert('id', '\w+');
        $controllers->post('/csvs/{id}', array(new self(), 'handleCsvUpload'))->bind('dataset-csvupload')->value('id', null)->assert('id', '\w+');
        $controllers->get('/map/{id}', array(new self(), 'mapSet'))->bind('dataset-map')->assert('id', '\w+');
        $controllers->get('/describe/{id}', array(new self(), 'describeSet'))->bind('dataset-describe')->assert('id', '\w+');
        $controllers->post('/describe/{id}', array(new self(), 'saveDescription'))->bind('dataset-save-description')->assert('id', '\w+');
        $controllers->get('/validate/{id}', array(new self(), 'validateSet'))->bind('dataset-validate')->assert('id', '\w+');
        $controllers->get('/export/{id}', array(new self(), 'exportSet'))->bind('dataset-export')->value('id', null)->assert('id', '\w+');
        $controllers->get('/exportsource/{id}', array(new self(), 'exportSource'))->bind('dataset-export-source')->value('id', null)->assert('id', '\w+');
        $controllers->get('/exportrelations/{id}', array(new self(), 'exportRelations'))->bind('dataset-export-relations')->value('id', null)->assert('id', '\w+');
        $controllers->get('/exportpits/{id}', array(new self(), 'exportPits'))->bind('dataset-export-pits')->value('id', null)->assert('id', '\w+');
        
        $controllers->get('/files/{id}/{name}', array(new self(), 'serveFile'))->bind('dataset-serve-file')->value('id', null)->assert('id', '\w+');

        $controllers->get('/delete/{id}', array(new self(), 'deleteSet'))->bind('dataset-delete')->assert('id', '\w+');
        return $controllers;
    }

    /**
     * New Dataset Form
     *
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function newSet(Application $app)
    {
        $form = $this->getNewDatasetForm($app);

        return $app['twig']->render('datasets/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * Save new dataset
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function saveNewSet(Application $app, Request $request)
    {
        $form = $this->getNewDatasetForm($app);
        $form->bind($request);
        if ($form->isValid()) {
            
            $data = $form->getData();
            $date = new \DateTime('now');

            /** @var \Doctrine\DBAL\Connection $db */
            $db = $app['db'];
            $db->insert('datasets', array(
                'id'      => $data['id'],
                'name'      => $data['name']
            ));


            return $app->redirect($app['url_generator']->generate('dataset-describe', array('id' => $data['id'])));
        }

        // of toon errors:
        return $app['twig']->render('datasets/new.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * Delete a dataset and all it's data
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteSet(Application $app, $id)
    {
        $dataset = $app['dataset_service']->getDataset($id);
        if (!$dataset) {
            die($id);
            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        $app['db']->delete('datasets', array('id' => $id));

        $app['session']->getFlashBag()->set('alert', 'De dataset is verwijderd!');

        return $app->redirect($app['url_generator']->generate('datasets-all'));
    }

    /**
     * Form for csv file uploads
     *
     * @param Application $app
     * @return mixed
     */
    private function getNewDatasetForm(Application $app) {
        $form = $app['form.factory']
            ->createBuilder('form')

            ->add('id', 'text', array(
                'label'         => 'Dataset id, preferably a recognizable string (name, abbreviation or acronym)',
                'required'  => true,
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\Regex(array(
                        'pattern'     => '/^[a-z0-9]+$/i',
                        'htmlPattern' => '^[a-z0-9]+$',
                        'match'   => true,
                        'message' => 'Only lowercase characters, no spaces, please',
                    )),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('title', 'text', array(
                'label'         => 'Full name of dataset',
                'required'  => true,
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\Regex(array(
                        'pattern'     => '/^[a-z0-9-\s]+$/i',
                        'htmlPattern' => '^[a-z0-9-\'\s]+$',
                        'match'   => true,
                        'message' => 'Only characters or numbers, please',
                    )),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->getForm()
        ;
        return $form;
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
     * View csvs with dataset
     *
     * @param Application $app
     * @param $id
     */
    public function showCsvs(Application $app, $id)
    {
        $dataset = $app['dataset_service']->getDataset($id);
        $csvs = $app['dataset_service']->getCsvs($id);
        $form = $this->getUploadForm($app);

        return $app['twig']->render('datasets/csvs.html.twig', array(
            'set' => $dataset, 
            'csvs' => $csvs,
            'form' => $form->createView()
            ));
    }

    /**
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function handleCsvUpload(Application $app, Request $request, $id)
    {
        $form = $this->getUploadForm($app);
        $form->bind($request);
        if ($form->isValid()) {
            $files = $request->files->get($form->getName());

            $filename = time(). '.csv';
            $originalName = $files['csvFile']->getClientOriginalName();
            $files['csvFile']->move($app['upload_dir'], $filename);

            $data = $form->getData();
            $date = new \DateTime('now');

            /** @var \Doctrine\DBAL\Connection $db */
            $db = $app['db'];
            $db->insert('csvfiles', array(
                'filename'          => $filename,
                'dataset_id'          => $id,
                'created_on' => $date->format('Y-m-d H:i:s')
            ));
            $datasetId = $db->lastInsertId();
            if (!$datasetId) {
                $app['session']->getFlashBag()->set('error', 'Sorry, there was an error during file upload.');
            } else {
                $app['session']->getFlashBag()->set('alert', 'The file was uploaded!');
            }

            return $app->redirect($app['url_generator']->generate('dataset-csvs', array('id' => $id)));
        }

        // of toon errors:
        return $app['twig']->render('import/uploadform.html.twig', array(
            'form' => $form->createView()
        ));
    }


    /**
     * Form for csv file uploads
     *
     * @param Application $app
     * @return mixed
     */
    private function getUploadForm(Application $app) {
        $form = $app['form.factory']
            ->createBuilder('form')

            ->add('csvFile', 'file', array(
                'label'     => 'Select a csvfile for upload',
                'required'  => true,
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\File(array(
                        'maxSize'       => '4096k',
                        'mimeTypes'     => array('text/csv', 'text/plain'),
                    )),
                    new Assert\Type('file')
                )
            ))
            ->getForm()
        ;
        return $form;
    }

    /**
     * Show all the details for one dataset
     *
     * @param Application $app
     * @param $id
     */
    public function mapSet(Application $app, $id)
    {

        $dataset = $app['dataset_service']->getDataset($id);

        return $app['twig']->render('datasets/map.html.twig', array('set' => $dataset));
    }

    /**
     * describe dataset
     *
     * @param Application $app
     * @param $id
     */
    public function describeSet(Application $app, $id)
    {
        
        $dataset = $app['dataset_service']->getDataset($id);

        $form = $this->getDescriptionForm($app, $dataset);

        return $app['twig']->render('datasets/describe.html.twig', array('set' => $dataset, 'form' => $form->createView()));
    }

    /**
     * Save description
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function saveDescription(Application $app, $id)
    {
        $data = $_POST['form'];
        unset($data['_token']);

        if ($app['dataset_service']->storeDescription($data)) { 

            $app['session']->getFlashBag()->set('alert', 'Description has been saved!');
            return $app->redirect($app['url_generator']->generate('dataset-describe', array('id' => $id)));
        
        }else{

            $app['session']->getFlashBag()->set('error', 'Sorry, but the update wasn\'t succesful');
            return $app->redirect($app['url_generator']->generate('dataset-describe', array('id' => $id)));

        }
        
    }

    /**
     * Form for csv file uploads
     *
     * @param Application $app
     * @return mixed
     */
    private function getDescriptionForm(Application $app, $dataset) {
        
        $form = $app['form.factory']
            ->createBuilder('form',$dataset)

            ->add('id', 'hidden', array(
                'required'  => true,
                'data' => $dataset['id'],
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\Regex(array(
                        'pattern'     => '/^[a-z0-9]+$/i',
                        'htmlPattern' => '^[a-z0-9]+$',
                        'match'   => true,
                        'message' => 'Only lowercase characters, no spaces, please',
                    )),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('title', 'text', array(
                'label'         => 'Dataset name',
                'data' => $dataset['title'],
                'required'  => true,
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\Regex(array(
                        'pattern'     => '/^[a-z0-9-\s]+$/i',
                        'htmlPattern' => '^[a-z0-9-\'\s]+$',
                        'match'   => true,
                        'message' => 'Only characters or numbers, please',
                    )),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('description', 'textarea', array(
                'label'         => 'Dataset description',
                'data' => $dataset['description'],
                'required'  => true,
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\Regex(array(
                        'pattern'     => '/^[a-z0-9-\s]+$/i',
                        'htmlPattern' => '^[a-z0-9-\'\s]+$',
                        'match'   => true,
                        'message' => 'Only characters or numbers, please',
                    )),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('author', 'textarea', array(
                'label'         => 'Dataset author(s)',
                'data' => $dataset['author'],
                'required'  => true,
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\Regex(array(
                        'pattern'     => '/^[a-z0-9-\s]+$/i',
                        'htmlPattern' => '^[a-z0-9-\'\s]+$',
                        'match'   => true,
                        'message' => 'Only characters or numbers, please',
                    )),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('license', 'text', array(
                'label'         => 'License',
                'data' => $dataset['license']
            ))
            ->add('website', 'text', array(
                'label'         => 'Website with information on dataset',
                'data' => $dataset['website']
            ))
            ->add('sourceCreationDate', 'text', array(
                'label'         => 'Source creation date (hasBeginning and hasEnd will be calculated from pits)',
                'data' => $dataset['sourceCreationDate'],
                'attr' => array('placeholder' => '2015-04-01')
            ))
            ->add('editor', 'text', array(
                'label'         => 'Data-editor',
                'data' => $dataset['editor']
            ))
            ->add('edits', 'textarea', array(
                'label'         => 'Data-edits',
                'data' => $dataset['edits']
            ))
            ->getForm()
        ;
        return $form;
    }







    /**
     * Show all the details for one dataset
     *
     * @param Application $app
     * @param $id
     */
    public function validateSet(Application $app, $id)
    {
        
        $dataset = $app['dataset_service']->getDataset($id);

        return $app['twig']->render('datasets/validate.html.twig', array('set' => $dataset));
    }



    /**
     * Show all the details for one dataset
     *
     * @param Application $app
     * @param $id
     */
    public function exportSet(Application $app, $id)
    {
        $dataset = $app['dataset_service']->getDataset($id);

        unset($dataset['use_csv_id']);

        $files = array();
        if(file_exists($app['export_dir'] . '/' . $id . '/source.json')){
            $files['source'] = '/files/' . $id . '/source.json';
        }
        if(file_exists($app['export_dir'] . '/' . $id . '/pits.ndjson')){
            $files['pits'] = $app['export_dir'] . '/' . $id . '/pits.ndjson';
        }
        if(file_exists($app['export_dir'] . '/' . $id . '/relations.ndjson')){
            $files['relations'] = $app['export_dir'] . '/' . $id . '/relations.ndjson';
        }
        

        return $app['twig']->render('datasets/export.html.twig', array('set' => $dataset, 'files' => $files));
    }


    /**
     * Show all the details for one dataset
     *
     * @param Application $app
     * @param $id
     */
    public function exportSource(Application $app, $id)
    {
        $dataset = $app['dataset_service']->getDataset($id);
        unset($dataset['use_csv_id']);

        $sourcejson = json_encode($dataset);

        $dir = $app['export_dir'] . '/' . $id;
        if (!file_exists($dir)) {
            mkdir($dir, 0777);
        }

        file_put_contents( $dir . '/source.json', $sourcejson);
        
        $app['session']->getFlashBag()->set('alert', 'Er is een source.json aangemaakt of overschreven.');
        
        return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
    }

    /**
     * Show all the details for one dataset
     *
     * @param Application $app
     * @param $id
     */
    public function exportPits(Application $app, $id)
    {
        $dataset = $app['dataset_service']->getDataset($id);
        unset($dataset['use_csv_id']);

        $sourcejson = json_encode($dataset);

        $dir = $app['export_dir'] . '/' . $id;
        if (!file_exists($dir)) {
            mkdir($dir, 0777);
        }

        file_put_contents( $dir . '/pits.ndjson', $sourcejson);
        
        $app['session']->getFlashBag()->set('alert', 'Er is een pits.ndjson aangemaakt of overschreven.');
        
        return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
    }


    /**
     * Show all the details for one dataset
     *
     * @param Application $app
     * @param $id
     */
    public function serveFile(Application $app, $id, $name)
    {
        if(file_exists($app['export_dir'] . '/' . $id . '/' . $name)){
            header('Content-type:application/json');
            return file_get_contents($app['export_dir'] . '/' . $id . '/' . $name);
        }

    }
}