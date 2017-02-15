<?php

namespace DominikAngerer;

use GuzzleHttp\Client as Guzzle;
use Apix\Cache as ApixCache;

/**
* Request Client and Cache
*/
class Client
{
    const API_USER = "api";
    const SDK_VERSION = "1.0";
    const CACHE_VERSION_KEY = "easyclient:cache_version";
    const SDK_USER_AGENT = "easyclient-sdk-php";
    const EXCEPTION_GENERIC_HTTP_ERROR = "An HTTP Error has occurred! Check your network connection and try again.";

    /**
     * @var stdClass
     */
    private $responseBody;

    /**
     * @var stdClass
     */
    private $responseHeaders;

    /**
     * @var string
     */
    public $cacheVersion;

    /**
     * @var string
     */
    public $type;

    /**
     * @var Guzzle
     */
    protected $client;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @param string $apiKey
     * @param string $apiEndpoint
     * @param string $apiVersion
     * @param bool   $ssl
     */
    function __construct($base_uri, $type = 'request', $defaults = array( 'headers' => array('User-Agent' => self::SDK_USER_AGENT.'/'.self::SDK_VERSION) ))
    {
        $this->type = $type;
        $this->client = new Guzzle([
            'base_uri'=> $base_uri,
            'defaults'=> $defaults,
        ]);
    }

    /**
     * @param string $apiEndpoint
     * @param string $apiVersion
     * @param bool   $ssl
     *
     * @return string
     */
    private function generateEndpoint($apiEndpoint, $apiVersion, $ssl)
    {
        if (!$ssl) {
            return "http://".$apiEndpoint."/".$apiVersion."/cdn/";
        } else {
            return "https://".$apiEndpoint."/".$apiVersion."/cdn/";
        }
    }

    /**
     * @param string $endpointUrl
     * @param array  $queryString
     *
     * @return \stdClass
     *
     * @throws Exception
     */
    public function get($endpointUrl, $queryString = array())
    {
        try {
            $responseObj = $this->client->get($endpointUrl, ['query' => $queryString]);
            return $this->responseHandler($responseObj);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new \Exception(self::EXCEPTION_GENERIC_HTTP_ERROR);
        }
    }

    /**
     * @param string $endpointUrl
     * @param array  $queryString
     *
     * @return \stdClass
     *
     * @throws Exception
     */
    public function post($endpointUrl, $requestData = array())
    {
        try {
            $responseObj = $this->client->post($endpointUrl, ['json' => $requestData]);
            return $this->responseHandler($responseObj);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new \Exception(self::EXCEPTION_GENERIC_HTTP_ERROR);
        }
    }

    /**
     * @param ResponseInterface $responseObj
     *
     * @return \stdClass
     *
     * @throws Exception
     */
    public function responseHandler($responseObj)
    {
        $httpResponseCode = $responseObj->getStatusCode();
        if ($httpResponseCode === 200) {
            $data = (string) $responseObj->getBody();
            // var_dump($data);
            $interpretedResponseData = (array) json_decode($data, true);
            if(count($interpretedResponseData) == 0) {
                $interpretedResponseData = (array) json_decode(json_encode(@simplexml_load_string($data)));
            }
            $result = new \stdClass();
            // return response data as json if possible, raw if not
            $result->httpResponseBody = $data && empty($interpretedResponseData) ? $data : $interpretedResponseData;
        } else {
            throw new \Exception(self::EXCEPTION_GENERIC_HTTP_ERROR . $this->getResponseExceptionMessage($responseObj), $httpResponseCode, $responseObj->getBody());
        }
        $result->httpResponseCode = $httpResponseCode;
        $result->httpResponseHeaders = $responseObj->getHeaders();

        $this->_assignState($result);

        return $result;
    }

    /**
     * @param \Guzzle\Http\Message\Response $responseObj
     *
     * @return string
     */
    protected function getResponseExceptionMessage(\GuzzleHttp\Message\Response $responseObj)
    {
        $body = (string) $responseObj->getBody();
        $response = json_decode($body);

        if (json_last_error() == JSON_ERROR_NONE && isset($response->message)) {
            return $response->message;
        }
    }

    /**
     * Set cache driver and optional the cache path
     *
     * @param string $driver Driver
     * @param string $options Path for file cache
     * @return \DominikAngerer\Client
     */
    public function setCache($driver, $options = array())
    {
        $options['serializer'] = 'php';
        $options['prefix_key'] = 'rq_cache:';
        $options['prefix_tag'] = 'rq_cache:';

        switch ($driver) {
            case 'mysql':
                $dbh = $options['pdo'];
                $this->cache = new ApixCache\Pdo\Mysql($dbh, $options);

                break;

            case 'sqlite':
                $dbh = $options['pdo'];
                $this->cache = new ApixCache\Pdo\Sqlite($dbh, $options);

                break;

            case 'postgres':
                $dbh = $options['pdo'];
                $this->cache = new ApixCache\Pdo\Pgsql($dbh, $options);

                break;

            default:
                $options['directory'] = $options['path'];

                $this->cache = new ApixCache\Files($options);

                break;
        }

        $this->cacheVersion = $this->cache->load(self::CACHE_VERSION_KEY);

        if (!$this->cacheVersion) {
            $this->setCacheVersion();
        }

        return $this;
    }

    /**
     * Flush all cache
     *
     * @return \DominikAngerer\Client
     */
    public function flushCache()
    {
        if ($this->cache) {
            $this->cache->flush();
            $this->setCacheVersion();
        }
        return $this;
    }

    /**
     * Sets cache version to get a fresh version from cdn after clearing the cache
     *
     * @return \DominikAngerer\Client
     */
    public function setCacheVersion()
    {
        if ($this->cache) {
            $timestamp = time();
            $this->cache->save($timestamp, self::CACHE_VERSION_KEY);
            $this->cacheVersion = $timestamp;
        }

        return $this;
    }

    /**
     * Gets the json response body
     *
     * @return array
     */
    public function getBody()
    {
        if (isset($this->responseBody)) {
            return $this->responseBody;
        }

        return array();
    }

    /**
     * Gets the response headers
     *
     * @return array
     */
    public function getHeaders()
    {
        if (isset($this->responseHeaders)) {
            return $this->responseHeaders;
        }

        return array();
    }

    /**
     * Save's the current response in the cache if version is published
     *
     * @param  array $response
     * @param  string $key
     * @param  string $version
     */
    private function _save($response, $key, $version)
    {
        $this->_assignState($response);

        if ($this->cache && $version == 'published') {
            $this->cache->save($response, $key);
        }
    }

    /**
     * Assigns the httpResponseBody and httpResponseHeader to '$this';
     *
     * @param  array $response
     * @param  string $key
     * @param  string $version
     */
    private function _assignState($response) {
        $this->responseBody = $response->httpResponseBody;
        $this->responseHeaders = $response->httpResponseHeaders;
    }
}