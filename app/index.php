<?php

require 'vendor/autoload.php';

use App\Router;
use App\Controllers\{User, Auth, Category, Product};

$controllers = [
    User::class,
    Auth::class,
    Category::class,
    Product::class
];

$router = new Router();
$router->registerControllers($controllers);
$router->run();