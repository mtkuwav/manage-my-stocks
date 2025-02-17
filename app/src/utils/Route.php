<?php

namespace App\Utils;

#[\Attribute]
class Route {
    public string $method;
    public string $path;
    public array $middlewares;
    public array $allowedRoles;

    public function __construct(string $method, string $path, array $middlewares = [], array $allowedRoles = ['admin', 'manager']) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->middlewares = $middlewares;
        $this->allowedRoles = $allowedRoles;
    }
}