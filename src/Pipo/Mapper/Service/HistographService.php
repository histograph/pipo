<?php

namespace Pipo\Mapper\Service;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ParseException;
use GuzzleHttp\Post\PostFile;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;

class HistographService {

    const API_TIMEOUT           = 50;
    const API_CONNECT_TIMEOUT   = 50;

    const SOURCES_ENTRY_POINT   = '/datasets'; //'/sources';

    private $apiUser = null;
    private $apiPass = null;

    /**
     * @var string $baseUri Uri of the service to call
     */
    private $baseUri = 'http://api.histograph.io';
    //private $baseUri = 'http://histograph-lb-2072119452.eu-central-1.elb.amazonaws.com';

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
        $this->client = new \GuzzleHttp\Client();
        $this->apiUser = $app['api_user'];
        $this->apiPass = $app['api_pass'];
    }

    /**
     * Add pits to an existing Histograph source
     *
     * @param string $sourceId
     * @param string $json
     * @return bool
     */
    public function addPitsToHistographSource($sourceId, $json)
    {
        $uri = $this->baseUri . self::SOURCES_ENTRY_POINT . '/' . $sourceId . '/pits';
        $auth = base64_encode($this->getApiUser() . ":" . $this->getApiPass());

        try {
            $response = $this->client->put(
                $uri,
                array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . $auth,
                        'Accept' => 'application/json',
                    ),
                    'body' => [
                        'file' => new PostFile('file', $json),
                    ]
                ));

            if ($response->getStatusCode() === 200) {
                return true;
            } else {
                return $response->json()['message'];
            }
        } catch (ClientException $e) { // 400 errors
            return $this->handleException($e);
        }
    }

    /**
     *
     * @param ClientException $e
     * @return mixed|string
     */
    private function handleException(ClientException $e)
    {
        return (string) $e->getResponse();
        if ($e->hasResponse()) {
            try {
                $json = $e->getResponse()->json();
                // todo fix this when Bert has updated the API
                if (isset($json['details'])) {
                    return $json['message'] . ' Details: ' . print_r($json['details'], 1);
                }
                return $json['message'];
            } catch (ParseException $e) {
                return print_r($e->getResponse(), 1);
            }
        }
        return 'The Histograph API returned an unknown error';
    }

    /**
     * Add relations to an existing Histograph source
     *
     * @param string $sourceId
     * @param string $json
     * @return bool
     */
    public function addRelationsToHistographSource($sourceId, $json)
    {
        $uri = $this->baseUri . self::SOURCES_ENTRY_POINT . '/' . $sourceId . '/relations';
        $auth = base64_encode($this->getApiUser() . ":" . $this->getApiPass());
        try {

            $response = $this->client->put(
                $uri,
                array(
                    'headers' => array(
                 //       'Content-type' => 'application/x-ndjson',
                 //       'Mime-type' => 'application/x-ndjson',
                        'Authorization' => 'Basic ' . $auth,
                        'Accept' => 'application/json',
                    ),
                    'body' => [
                        'file' => new PostFile('file', $json),
                    ]
                ));

            if ($response->getStatusCode() === 200) {
                return true;
            }
        } catch (ClientException $e) { // 400 errors
            return $this->handleException($e);
        };
    }

    /**
     * Creates a new or updates an existing source, depending on the situation
     *
     * @param $sourceId
     * @param $json
     * @return bool|mixed|void
     */
    public function saveHistographSource($sourceId, $json)
    {
        $uri = $this->baseUri . self::SOURCES_ENTRY_POINT . '/' . $sourceId . '';

        try {
            $response = $this->client->get(
                $uri,
                array(
                    'headers'       => array(
                        'Content-type' => 'application/json',
                        'Accept' =>'application/json',
                    ),
                ));

            if ($response->getStatusCode() === 200) { // source already exists, call update
                return $this->updateHistographSource($sourceId, $json);
            } else {
                return $response->json()['message'];
            }
        } catch (ClientException $e) { // 400 errors
            if ($e->hasResponse()) {
                if ($e->getResponse()->getStatusCode() === 404) { // source does not exist, go create
                    return $this->createNewHistographSource($json);
                } else {
                    return $this->handleException($e);
                }
            }
            return 'An unknown error occurred';
        };
    }


    /**
     * Deletes the entire source from Histograph
     *
     * @param $sourceId
     * @return mixed
     */
    public function deleteHistographSource($sourceId)
    {
        $uri = $this->baseUri . self::SOURCES_ENTRY_POINT . '/' . $sourceId . '';
        $auth = base64_encode($this->getApiUser() . ":" . $this->getApiPass());

        Try {
            $response = $this->client->delete(
                $uri,
                array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . $auth,
                        'Accept' => 'application/json',
                    ),
                ));

            if ($response->getStatusCode() === 200) {
                return true;
            } else {
                return $response->json()['message'];
            }
        } catch (ClientException $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Updates the description etc of an existing source
     *
     * @param $sourceId
     * @param $json
     * @return bool
     */
    public function updateHistographSource($sourceId, $json)
    {
        $uri = $this->baseUri . self::SOURCES_ENTRY_POINT . '/' . $sourceId . '';
        $auth = base64_encode($this->getApiUser() . ":" . $this->getApiPass());

        try {
            $response = $this->client->patch(
                $uri,
                array(
                    'headers'       => array(
                        'Content-type' => 'application/json',
                        'Authorization' => 'Basic ' . $auth,
                        'Accept' =>'application/json',
                    ),
                    'body' => $json
                ));

            if ($response->getStatusCode() === 200) {
                return true;
            }
        } catch (ClientException $e) { // 400 errors
            return $this->handleException($e);
        };
    }

    /**
     * POST sources file to the API
     *
     * @param $json
     * @return bool|mixed
     */
    public function createNewHistographSource($json)
    {
        $uri = $this->baseUri . self::SOURCES_ENTRY_POINT;
        $auth = base64_encode($this->getApiUser() . ":" . $this->getApiPass());
        
        try {
            $response = $this->client->post(
                $uri,
                array(
                    'headers'       => array(
                        'Content-type' => 'application/json',
                        'Authorization' => 'Basic ' . $auth,
                        'Accept' =>'application/json',
                    ),
                    'body' => $json
                ));

            if ($response->getStatusCode() === 201) {
                return true;
            }
        } catch (ClientException $e) { // 400 errors
            return $this->handleException($e);
        };

    }

    /**
     * @return null
     */
    public function getApiUser()
    {
        return $this->apiUser;
    }

    /**
     * @param null $apiUser
     */
    public function setApiUser($apiUser)
    {
        $this->apiUser = $apiUser;
    }

    /**
     * @return null
     */
    public function getApiPass()
    {
        return $this->apiPass;
    }

    /**
     * @param null $apiPass
     */
    public function setApiPass($apiPass)
    {
        $this->apiPass = $apiPass;
    }

}