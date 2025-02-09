<?php

require 'vendor/autoload.php';

use App\Router;
use App\Controllers\{User, Dogs, Auth};

$controllers = [
    User::class,
    Dogs::class,
    Auth::class,
];

$router = new Router();
$router->registerControllers($controllers);
$router->run();