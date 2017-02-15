# php-easy-client
Allow GET and POST Request from Guzzle combined with the caching from APIX

## Install

```
composer install dominikangerer/php-easy-client
```
## How to use

```
// initialize with domain
$client = new \DominikAngerer\Client('https://raw.githubusercontent.com');

// Optionally set a cache
$client->setCache('filesytem', array('path' => 'cache'));

// execute a get Request and get the Body
$client->get('/DominikAngerer/default-datasources/master/country-iso2.json', array('time' => time()));
$data = $client->getBody();

// access the headers
$headers = $client->getHeaders();
```

