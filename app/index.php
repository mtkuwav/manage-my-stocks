<?php

require 'vendor/autoload.php';

use App\Router;
use App\Controllers\{User, Dogs, Auth, Category};

$controllers = [
    User::class,
    Dogs::class,
    Auth::class,
    Category::class
];

$router = new Router();
$router->registerControllers($controllers);
$router->run();