
<?php

require_once "vendor/autoload.php";
require_once "includes/google_config.php";

session_start();

$client = new Google\Client();

$client->setClientId($google_client_id);
$client->setClientSecret($google_client_secret);
$client->setRedirectUri(
    "http://localhost/meetsync/google_callback.php"
);

$client->addScope("email");
$client->addScope("profile");

$client->setPrompt("select_account");

$authUrl = $client->createAuthUrl();

header("Location: " . $authUrl);
exit;