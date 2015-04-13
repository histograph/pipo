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
    //private $baseUri = 'http://api.histograph.io';
    private $baseUri = 'http://bertwaag.local:3000';

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
        $this->client = new \GuzzleHttp\Client();
        $this->apiUser = $app['api_user'];
        $this->apiPass = $app['api_pass'];
    }

    public function createNewHistographSource($json)
    {
        $uri = $this->baseUri . SELF::SOURCES_ENTRY_POINT;

        $auth = base64_encode($this->getApiUser() . ":" . $this->getApiPass());
        $response = $this->client->post(
            $uri,
            array(
                'headers' => array(
                    'Authorization' => 'Basic '.$auth,
                    'Accept' =>'application/json',
                ),
                'body' => json_encode('{"id":"poorterding","title":"Carnaval onzin","description":"Bla die bla bla","license":"GPL","author":"@meme","website":"www.ergensheen.nl","edits":"ik heb er iets mee gedaan","editor":"Petra","sourceCreationDate":null}')
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