<?php

require 'vendor/autoload.php';

use App\Router;
use App\Controllers\{User, Dogs, Auth, Category, Product};

$controllers = [
    User::class,
    Dogs::class,
    Auth::class,
    Category::class,
    Product::class
];

$router = new Router();
$router->registerControllers($controllers);
$router->run();