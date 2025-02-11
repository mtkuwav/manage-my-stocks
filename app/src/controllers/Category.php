<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\CategoryModel;
use App\Utils\Route;
use App\Utils\HttpException;

class Category extends Controller {
    protected object $category;

    public function __construct($params) {
        $this->category = new CategoryModel();

        parent::__construct($params);
    }

    #[Route("GET", "/categories/:id")]
    public function getCategoryById() {
        return $this->category->getById(intval($this->params['id']));
    }
}