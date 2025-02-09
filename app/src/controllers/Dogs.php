<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\DogModel;
use App\Utils\{Route, HttpException};

class Dogs extends Controller {
  private object $dog;

  public function __construct($param) {
    $this->dog = new DogModel();

    parent::__construct($param);
  }

  #[Route("GET", "/dogs/:id")]
  public function getDogById() {
    return $this->dog->getById($this->params["id"]);
  }

  #[Route("POST", "/dogs")]
  public function createDog() {
    try {
      $data = $this->body;
      if (empty($data['name'])) {
          throw new HttpException("Le nom est manquant.", 400);
      }
      return $this->dog->create($data);
    } catch (\Exception $e) {
      throw new HttpException("Impossible de créer un chien", 400);
    }
  }

}