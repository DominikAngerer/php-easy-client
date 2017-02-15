<?php
require '../vendor/autoload.php';

$client = new \DominikAngerer\Client('https://raw.githubusercontent.com');

// Optionally set a cache
$client->setCache('filesytem', array('path' => 'cache'));

// execute a get Request and get the Body
$client->get('/DominikAngerer/default-datasources/master/country-iso2.json', array('time' => time()));
$data = $client->getBody();

echo '<link href="https://getuikit.com/css/theme.css?174" rel="stylesheet" type="text/css">';
echo '<div class="uk-container uk-container-center uk-margin-large-top uk-margin-large-bottom">
				<h1> PHP Client Runable Test </h1>
				<h2> get "Get A Json" </h2>
				<pre>';
var_dump($data);
echo '	</pre>';
echo '<hr>';

// access the headers
$headers = $client->getHeaders();

echo '<h2> $client->getHeaders </h2>
			<pre>';
var_dump($headers);
echo '</pre>
			<hr>';

echo '<h2> $client->flushCache(); </h2>';
$client->flushCache();

echo '</div>';