<?php

ini_set('display_errors', 0);

require_once __DIR__.'/../vendor/autoload.php';
\Symfony\Component\Debug\Debug::enable();
require __DIR__.'/../config/db.php';
$app = require __DIR__.'/../src/app.php';
require __DIR__.'/../config/prod.php';
require __DIR__.'/../src/controllers.php';
$app->run();
