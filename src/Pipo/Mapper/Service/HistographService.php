<?php

namespace Pipo\Mapper\Service;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;

class HistographService {

    const API_TIMEOUT           = 50;
    const API_CONNECT_TIMEOUT   = 50;

    const SOURCES_ENTRY_POINT   = '/sources';

    private $apiUser = null;
    private $apiPass = null;

    /**
     * @var string $baseUri Uri of the service to call
     */
    private $baseUri = 'https://api.histograph.io';

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
     */
    public function addPitsToHistographSource($sourceId, $json)
    {
        $uri = $this->baseUri . SELF::SOURCES_ENTRY_POINT . '/' . $sourceId . '/pits';

        // todo ndjson not as json body, but www-form encoded
        $auth = base64_encode($this->getApiUser() . ":" . $this->getApiPass());
        $response = $this->client->post(
            $uri,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Accept' => 'application/json',
                ),
                'body' => json_encode($json)
            ));

        //var_dump($response);
        var_dump($response->json());

        die;
    }

    /**
     * Add relations to an existing Histograph source
     *
     * @param string $sourceId
     * @param string $json
     */
    public function addRelationsToHistographSource($sourceId, $json)
    {
        $uri = $this->baseUri . SELF::SOURCES_ENTRY_POINT . '/' . $sourceId . '/relations';

        $auth = base64_encode($this->getApiUser() . ":" . $this->getApiPass());
        $response = $this->client->post(
            $uri,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Accept' => 'application/json',
                ),
                'body' => json_encode($json)
            ));

        //var_dump($response);
        var_dump($response->json());

        die;
    }

    /**
     * POST sources file to the API
     * If a set with the same id already exists it will be overwritten
     *
     * @param $json
     */
    public function createNewHistographSource($json)
    {
        $uri = $this->baseUri . SELF::SOURCES_ENTRY_POINT;

        $auth = base64_encode($this->getApiUser() . ":" . $this->getApiPass());
        $response = $this->client->post(
            $uri,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Accept' =>'application/json',
                ),
                'body' => json_encode($json)
            ));

        //var_dump($response);
        var_dump($response->json());

        die;
        if ($response->getStatusCode() === 200) {
            if (!property_exists($response, 'features')) {

            } else {

            }
        }
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