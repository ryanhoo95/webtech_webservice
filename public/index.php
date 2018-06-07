<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Http\UploadedFile;

require '../vendor/autoload.php';
require '../src/config/db.php';

header("Access-Control-Allow-Origin:*");
header('Access-Control-Allow-Headers: content-type');

$app = new \Slim\App(['settings' => ['displayErrorDetails' => true]]);

//general functions
require '../src/general/general.php';

// users routes
//example: require '../src/routes/users.php';
require '../src/routes/pang.php';
require '../src/routes/bk.php';

$app->run();