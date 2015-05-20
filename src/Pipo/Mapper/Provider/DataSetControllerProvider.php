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
use SplTempFileObject;

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
        $controllers->get('/csvs/{id}', array(new self(), 'showCsvs'))->bind('dataset-csvs')->value('id', null)->assert('id', '[a-z0-9-]+');
        $controllers->post('/csvs/{id}', array(new self(), 'handleCsvUpload'))->bind('dataset-csvupload')->value('id', null)->assert('id', '[a-z0-9-]+');
        $controllers->get('/map/{id}', array(new self(), 'mapSet'))->bind('dataset-map')->assert('id', '[a-z0-9-]+');
        $controllers->post('/map/{id}', array(new self(), 'mapSave'))->bind('dataset-map-save')->assert('id', '[a-z0-9-]+');
        $controllers->get('/describe/{id}', array(new self(), 'describeSet'))->bind('dataset-describe')->assert('id', '[a-z0-9-]+');
        $controllers->post('/describe/{id}', array(new self(), 'saveDescription'))->bind('dataset-save-description')->assert('id', '[a-z0-9-]+');
        $controllers->get('/validate/{id}', array(new self(), 'validateSet'))->bind('dataset-validate')->assert('id', '[a-z0-9-]+');
        $controllers->get('/export/{id}', array(new self(), 'exportSet'))->bind('dataset-export')->value('id', null)->assert('id', '[a-z0-9-]+');
        $controllers->get('/exportsource/{id}', array(new self(), 'exportSource'))->bind('dataset-export-source')->value('id', null)->assert('id', '[a-z0-9-]+');
        $controllers->get('/exportrelations/{id}', array(new self(), 'exportRelations'))->bind('dataset-export-relations')->value('id', null)->assert('id', '[a-z0-9-]+');
        $controllers->get('/exportpits/{id}', array(new self(), 'exportPits'))->bind('dataset-export-pits')->value('id', null)->assert('id', '[a-z0-9-]+');

        $controllers->get('/files/{id}/{name}', array(new self(), 'serveFile'))->bind('dataset-serve-file')->value('id', null)->assert('id', '[a-z0-9-]+');

        $controllers->get('/delete/{id}', array(new self(), 'deleteSet'))->bind('dataset-delete')->assert('id', '[a-z0-9-]+');

        // Calls to the API
        $controllers->get('/postsource/{id}', array(new self(), 'postSource'))->bind('dataset-post-source')->assert('id', '[a-z0-9-]+');
        $controllers->get('/postpits/{id}', array(new self(), 'postPits'))->bind('dataset-post-pits')->assert('id', '[a-z0-9-]+');
        $controllers->get('/postrelations/{id}', array(new self(), 'postRelations'))->bind('dataset-post-relations')->assert('id', '[a-z0-9-]+');
        $controllers->get('/postdelete/{id}', array(new self(), 'postDelete'))->bind('dataset-post-delete')->assert('id', '[a-z0-9-]+');
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
                'title'      => $data['title']
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
                        'pattern'     => '/^[a-z0-9-]+$/i',
                        'htmlPattern' => '^[a-z0-9-]+$',
                        'match'   => true,
                        'message' => 'Only lowercase characters and hyphens, no spaces, please',
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

            $originalName = $files['csvFile']->getClientOriginalName();

            if(preg_match('/.geojson/', $originalName)){
                $geojsonfilename = time(). '.geojson';
                $filename = time(). 'from-geojson.csv';
                $files['csvFile']->move($app['upload_dir'], $geojsonfilename);

                // now, read geojson file, convert to csv and save csv file
                ini_set('memory_limit', '-1');
                set_time_limit(0);
                $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $geojsonfilename;
                $geojson = file_get_contents($file);
                $filedata = json_decode($geojson,true);

                // get column names
                $columnNames = array();
                foreach ($filedata['features'][0]['properties'] as $key => $value) {
                    $columnNames[] = $key;
                }
                $columnNames[] = "geometry";

                // write new csv with geojson content
                $writer = Writer::createFromFileObject(new SplTempFileObject()); //the CSV file will be created into a temporary File
                $writer->setDelimiter(","); //the delimiter will be the tab character
                $writer->setNewline("\r\n"); //use windows line endings for compatibility with some csv libraries
                $writer->setEncodingFrom("utf-8");

                $writer->insertOne($columnNames);

                $recs = array();
                foreach ($filedata['features'] as $rec) {
                    $fields = array();
                    foreach ($rec['properties'] as $k => $v) {
                        if($v!=null){
                            $fields[] = $v;
                        }else{
                            $fields[] = "";
                        }
                    }
                    $fields[] = json_encode($rec['geometry']);
                    $recs[] = $fields;
                }

                $writer->insertAll($recs);

                file_put_contents($app['upload_dir'] . '/' . $filename, $writer);

                // all done, now delete initial geojson file
                unlink($app['upload_dir'] . DIRECTORY_SEPARATOR . $geojsonfilename);
            }else{
                $filename = time(). '.csv';
                $originalName = $files['csvFile']->getClientOriginalName();
                $files['csvFile']->move($app['upload_dir'], $filename);
            }

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
                'label'     => 'Select a csv or geojson file for upload',
                'required'  => true,
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\File(array(
                        'maxSize'       => '409600k',
                        'mimeTypes'     => array('text/csv', 'text/plain', 'application/json'),
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

        // get fieldnames from csv
        $usecsv =  $app['dataset_service']->getCsv($dataset['use_csv_id']);
        $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $usecsv['filename'];

        if(!is_file($file)){
            $app['session']->getFlashBag()->set('error', 'No csv-file yet, redirected to csv-file upload');
            return $app->redirect($app['url_generator']->generate('dataset-csvs', array('id' => $id)));
        }

        $csv = \League\Csv\Reader::createFromPath($file);
        $columnNames = $csv->fetchOne();


        // get current mapping if any
        $mappings = $app['dataset_service']->getMappings($id);
        $maptypes = array("property" => array(),"relation" => array(),"data" => array());
        foreach ($mappings as $k => $v) {
            if($v['mapping_type']=="property"){
                $maptypes[$v['mapping_type']][$v['the_key'] . 'Column'] = $v['value_in_field'];
                $maptypes[$v['mapping_type']][$v['the_key'] . 'Text'] = $v['value'];
            }else{
                $maptypes[$v['mapping_type']][$v['id']]['column'] = $v['value_in_field'];
                $maptypes[$v['mapping_type']][$v['id']]['text'] = $v['value'];
                $maptypes[$v['mapping_type']][$v['id']]['key'] = $v['the_key'];
            }

        }

        $possibleProperties = array("name","geometry","type","hasBeginning","hasEnd","lat","long");
        foreach($possibleProperties as $k => $v){
            if(!isset($maptypes['property'][$v])){
                $maptypes['property'][$v] = "";
            }
        }

        // get all relations and pittypes, but where from??
        $relationTypes = array(     "hg:absorbed",
            "hg:absorbedBy",
            "hg:contains",
            "hg:hasGeoFeature",
            "hg:hasName",
            "hg:hasPitType",
            "hg:hasProvEntity",
            "hg:hasTimeTemporalEntity",
            "hg:isUsedFor",
            "hg:sameHgConcept",
            "hg:within");
        $pitTypes = array( "hg:Street",
            "hg:Country",
            "hg:Province",
            "hg:Municipality",
            "hg:Place",
            "hg:Water",
            "hg:Area",
            "hg:Building",
            "hg:Monument",
            "hg:Neighbourhood");

        if($json = file_get_contents("https://raw.githubusercontent.com/histograph/schemas/master/json/pits.schema.json")){
            $pitschema = json_decode($json,true);
            $pitTypes = $pitschema['properties']['type']['enum'];
        }

        if($json = file_get_contents("https://raw.githubusercontent.com/histograph/schemas/master/json/relations.schema.json")){
            $relationschema = json_decode($json,true);
            $relationTypes = $relationschema['properties']['label']['enum'];
        }

        //print_r($pitTypes);


        return $app['twig']->render('datasets/map.html.twig', array(
            'set' => $dataset,
            'properties' => $maptypes['property'],
            'relations' => $maptypes['relation'],
            'data' => $maptypes['data'],
            'columns' => $columnNames,
            'relationtypes' => $relationTypes,
            'pittypes' => $pitTypes
        ));
    }

    /**
     * Save Mapping
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function mapSave(Application $app, $id)
    {
        $data = $_POST;
        $db = $app['db'];

        // first, get rid of all previous mappings
        $app['db']->delete('fieldmappings', array('dataset_id' => $id));

        // props
        foreach($data as $k => $v){

            if(preg_match("/^prop-([^-]+)$/",$k,$found)){
                if(isset($_POST['prop-' . $found[1] . '-text'])){
                    $insdata = array('dataset_id' => $id, 'mapping_type' => 'property', 'the_key' => $found[1], 'value_in_field' => $v, 'value' => $_POST['prop-' . $found[1] . '-text']);
                }else{
                    $insdata = array('dataset_id' => $id, 'mapping_type' => 'property', 'mapping_type' => 'property', 'the_key' => $found[1], 'value_in_field' => $v);
                }
                $db->insert('fieldmappings', $insdata);
            }

        }

        // relations
        for ($i=0; $i<count($data['relation-type']); $i++) {
            if($data['relation-type'][$i]!=""){
                $insdata = array('dataset_id' => $id, 'mapping_type' => 'relation', 'the_key' => $data['relation-type'][$i], 'value_in_field' => $data['relation-column'][$i], 'value' => $data['relation-value'][$i]);
                $db->insert('fieldmappings', $insdata);
            }
        }


        // data
        for ($i=0; $i<count($data['data-name']); $i++) {
            if($data['data-name'][$i]!=""){
                $insdata = array('dataset_id' => $id, 'mapping_type' => 'data', 'the_key' => $data['data-name'][$i], 'value_in_field' => $data['data-value'][$i]);
                $db->insert('fieldmappings', $insdata);
            }
        }

        $app['session']->getFlashBag()->set('alert', 'Mapping has been saved!');
        return $app->redirect($app['url_generator']->generate('dataset-map', array('id' => $id)));



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
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @internal param Request $request
     */
    public function saveDescription(Application $app, Request $request, $id)
    {
        $data = $request->request->get('form');
        unset($data['_token']);

        if ($app['dataset_service']->storeDescription($data)) {

            $app['session']->getFlashBag()->set('alert', 'Description has been saved!');
            return $app->redirect($app['url_generator']->generate('dataset-describe', array('id' => $id)));

        } else{
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
                'required'  => false,
                'label'         => 'Website with information on dataset',
                'data' => $dataset['website']
            ))
            ->add('sourceCreationDate', 'text', array(
                'required'  => false,
                'label'         => 'Source creation date (hasBeginning and hasEnd will be calculated from pits)',
                'data' => $dataset['sourceCreationDate'],
                'attr' => array('placeholder' => '2015-04-01')
            ))
            ->add('editor', 'text', array(
                'required'  => false,
                'label'         => 'Data-editor',
                'data' => $dataset['editor']
            ))
            ->add('edits', 'textarea', array(
                'required'  => false,
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
     * Post the sources file to the histograph API
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function postSource(Application $app, $id)
    {
        if (!file_exists($app['export_dir'] . '/' . $id . '/source.json')) {
            $app['session']->getFlashBag()->set('alert', 'The source.json file does not exist yet. You have to create it before we can send it to the API.');
            return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
        }

        $json = file_get_contents($app['export_dir'] . '/' . $id . '/source.json');
        $response = $app['histograph_service']->saveHistographSource($id, $json);

        if (true === $response) {
            $app['session']->getFlashBag()->set('alert', 'The source.json file has been sent to the API. It should show up any minute now.');
        } else {
            $app['session']->getFlashBag()->set('error', 'Oops, something went wrong. The API returned the following error: ' . $response);
        }

        return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
    }

    /**
     * Post the pits file to the histograph API
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function postPits(Application $app, $id)
    {
        if (!file_exists($app['export_dir'] . '/' . $id . '/pits.ndjson')) {
            $app['session']->getFlashBag()->set('alert', 'The pits.ndjson file does not exist yet. You have to create it before we can send it to the API.');
            return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
        }
        $json = file_get_contents($app['export_dir'] . '/' . $id . '/pits.ndjson');
        $response = $app['histograph_service']->addPitsToHistographSource($id, $json);

        if (true === $response) {
            $app['session']->getFlashBag()->set('alert', 'The PiTs have been added to the API. It might take a while to process them all (depending on the size of your set). Please check the API or viewer later.');
        } else {
            $app['session']->getFlashBag()->set('error', 'Oops, something went wrong. The API returned the following error: ' . $response);
        }

        return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
    }

    /**
     * POST the relations to the API
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function postRelations(Application $app, $id)
    {
        if (!file_exists($app['export_dir'] . '/' . $id . '/relations.ndjson')) {
            $app['session']->getFlashBag()->set('alert', 'The relations.ndjson file does not exist yet. You have to create it before we can send it to the API.');
            return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
        }
        $json = file_get_contents($app['export_dir'] . '/' . $id . '/relations.ndjson');
        $response = $app['histograph_service']->addRelationsToHistographSource($id, $json);

        if (true === $response) {
            $app['session']->getFlashBag()->set('alert', 'The relations have been added to the API. It might take a while to process them all (depending on the size of your set). Please check the API or viewer later.');
        } else {
            $app['session']->getFlashBag()->set('error', 'Oops, something went wrong. The API returned the following error: ' . $response);
        }

        return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
    }

    /**
     * POST a DELETE request for this source to the API
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function postDelete(Application $app, $id)
    {
        $response = $app['histograph_service']->deleteHistographSource($id);

        if (true === $response) {
            $app['session']->getFlashBag()->set('alert', 'The source has been removed completely.');
        } else {
            $app['session']->getFlashBag()->set('error', 'Oops, something went wrong. The API returned the following error: ' . $response);
        }

        return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
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

        $files = array();
        if(file_exists($app['export_dir'] . '/' . $id . '/source.json')){
            $files['source'] = '/sets/files/' . $id . '/source.json';
        }
        if(file_exists($app['export_dir'] . '/' . $id . '/pits.ndjson')){
            $files['pits'] = '/sets/files/' . $id . '/pits.ndjson';
        }
        if(file_exists($app['export_dir'] . '/' . $id . '/relations.ndjson')){
            $files['relations'] = '/sets/files/' . $id . '/relations.ndjson';
        }


        return $app['twig']->render('datasets/export.html.twig', array('set' => $dataset, 'files' => $files));
    }


    /**
     * Show all the details for one dataset
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
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
     * Export pits to ndjson file
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function exportPits(Application $app, $id)
    {
        $dataset = $app['dataset_service']->getDataset($id);

        // get csv
        $usecsv =  $app['dataset_service']->getCsv($dataset['use_csv_id']);
        $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $usecsv['filename'];

        if(!is_file($file)){
            $app['session']->getFlashBag()->set('error', 'No csv-file yet, redirected to csv-file upload');
            return $app->redirect($app['url_generator']->generate('dataset-csvs', array('id' => $id)));
        }

        $csv = \League\Csv\Reader::createFromPath($file);
        $recs = $csv->fetchAll();
        $columnNames = array_shift($recs); // first row holds column names, right?
        $columnKeys = array_flip($columnNames);


        // get mappings (what property, relation of data is held in what field?)
        $mappings = $app['dataset_service']->getMappings($id);
        foreach ($mappings as $k => $v) {

            $maptypes[$v['mapping_type']][$v['id']]['column'] = $v['value_in_field'];
            $maptypes[$v['mapping_type']][$v['id']]['text'] = $v['value'];
            $maptypes[$v['mapping_type']][$v['id']]['key'] = $v['the_key'];


        }

        // attach the right values to the keys expected by Histograph and create ndjson
        $pits = array();
        $lastkey = count($columnNames)-1;
        foreach ($recs as $recKey => $rec) {

            $pit = array();

            if(isset($rec[$lastkey])){ // first csv i tried ended with an empty line, make sure rec has all expected columns
                foreach ($maptypes['property'] as $prop) {

                    if($prop['text']!=""){
                        $pit[$prop['key']] = $prop['text'];
                    }
                    if($prop['column']!=""){
                        $pit[$prop['key']] = $rec[$columnKeys[$prop['column']]];
                    }


                }
                
                // if lat & long and no geometry, make geojson from lat & long values
                if(!isset($pit['geometry']) && isset($pit['lat']) && isset($pit['long']) && $pit['lat']>0 && $pit['long']>0){
                    $pit['geometry'] = '{ "type": "Point", "coordinates": [' . $pit['lat'] . ', ' .  $pit['long']. '] }';
                    unset($pit['lat']);
                    unset($pit['long']);
                }


                // valid dates?
                if(isset($pit['hasBeginning'])){
                    if(preg_match("/^[0-9]{1,4}$/",$pit['hasBeginning'])){ // if year only, create valid date
                        $pit['hasBeginning'] = $pit['hasBeginning'] . "-01-01";
                    }
                }
                if(isset($pit['hasEnd'])){
                    if(preg_match("/^[0-9]{1,4}$/",$pit['hasEnd'])){ // if year only, create valid date
                        $pit['hasEnd'] = $pit['hasEnd'] . "-01-01";
                    }
                }
                

                if (isset($maptypes['data'])) {
                    foreach ($maptypes['data'] as $item) {
                        $pit['data'][$item['key']] = $rec[$columnKeys[$item['column']]];
                    }
                }
                $pit['geometry'] = json_decode(stripslashes($pit['geometry']));
                //die(print_r($pit));
                
                $pits[] = json_encode($pit);
            }
        }
        $ndjson = implode("\n",$pits);

        $dir = $app['export_dir'] . '/' . $id;
        if (!file_exists($dir)) {
            mkdir($dir, 0777);
        }

        file_put_contents( $dir . '/pits.ndjson', $ndjson);

        $app['session']->getFlashBag()->set('alert', 'Er is een pits.ndjson aangemaakt of overschreven.');

        return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
    }

    /**
     * Export pits to ndjson file
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function exportRelations(Application $app, $id)
    {
        $dataset = $app['dataset_service']->getDataset($id);

        // get csv
        $usecsv =  $app['dataset_service']->getCsv($dataset['use_csv_id']);
        $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $usecsv['filename'];

        if(!is_file($file)){
            $app['session']->getFlashBag()->set('error', 'No csv-file yet, redirected to csv-file upload');
            return $app->redirect($app['url_generator']->generate('dataset-csvs', array('id' => $id)));
        }

        $csv = \League\Csv\Reader::createFromPath($file);
        $recs = $csv->fetchAll();
        $columnNames = array_shift($recs); // first row holds column names, right?
        $columnKeys = array_flip($columnNames);


        // get mappings (what property, relation of data is held in what field?)
        $mappings = $app['dataset_service']->getMappings($id);

        foreach ($mappings as $k => $v) {
            $maptypes[$v['mapping_type']][$v['id']]['column'] = $v['value_in_field'];
            $maptypes[$v['mapping_type']][$v['id']]['text'] = $v['value'];
            $maptypes[$v['mapping_type']][$v['id']]['key'] = $v['the_key'];
        }

        // attach the right values to the keys expected by Histograph and create ndjson
        $relations = array();
        $lastkey = count($columnNames)-1;
        foreach ($recs as $recKey => $rec) {
            $pitid = false;
            if(isset($rec[$lastkey])){ // first csv i tried ended with an empty line, make sure rec has all expected columns

                foreach ($maptypes['property'] as $prop) {

                    if($prop['key']=="id"){
                        $pitid = $rec[$columnKeys[$prop['column']]];
                    }

                }
                if(!$pitid){
                    $app['session']->getFlashBag()->set('error', 'No pit id has been defined');
                    return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
                }
                
                if(isset($maptypes['relation'])){
                    foreach ($maptypes['relation'] as $item) {
                        if($rec[$columnKeys[$item['column']]] != ""){
                            $relation = 	array(	'from' => $pitid,
                                'to' => $rec[$columnKeys[$item['column']]],
                                'label' => $item['key']
                            );
                            $relations[] = json_encode($relation);
                        }
                    }
                }

            }
        }
        $ndjson = implode("\n",$relations);

        $dir = $app['export_dir'] . '/' . $id;
        if (!file_exists($dir)) {
            mkdir($dir, 0777);
        }

        file_put_contents( $dir . '/relations.ndjson', $ndjson);

        $app['session']->getFlashBag()->set('alert', 'Er is een relations.ndjson aangemaakt of overschreven.');

        return $app->redirect($app['url_generator']->generate('dataset-export', array('id' => $id)));
    }



    /**
     * Show all the details for one dataset
     *
     * @param Application $app
     * @param $id
     * @return string
     */
    public function serveFile(Application $app, $id, $name)
    {
        if(file_exists($app['export_dir'] . '/' . $id . '/' . $name)){
            header('Content-type:application/json');
            return file_get_contents($app['export_dir'] . '/' . $id . '/' . $name);
        }

    }
}