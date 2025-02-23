<?php

require 'vendor/autoload.php';

use App\Router;
use App\Controllers\{
    User,
    Auth,
    Category,
    Product,
    InventoryLog,
    Order
};

$controllers = [
    User::class,
    Auth::class,
    Category::class,
    Product::class,
    InventoryLog::class,
    Order::class
];

$router = new Router();
$router->registerControllers($controllers);
$router->run();