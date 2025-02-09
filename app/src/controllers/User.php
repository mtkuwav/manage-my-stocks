<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\UserModel;
use App\Utils\Route;
use App\Utils\HttpException;
use App\Middlewares\AuthMiddleware;

class User extends Controller {
  protected object $user;

  public function __construct($param) {
    $this->user = new UserModel();

    parent::__construct($param);
  }

  #[Route("POST", "/users")]
  public function toto() {
    $this->user->add($this->body);

    return $this->user->getLast();
  }

  #[Route("DELETE", "/users/:id")]
  public function deleteUser() {
    return $this->user->delete(intval($this->params['id']));
  }

  #[Route("GET", "/users/:id")] 
  public function getUser() {
    return $this->user->get(intval($this->params['id']));
  }

  #[Route("GET", "/users", middlewares: [AuthMiddleware::class])]
  public function getUsers() {
      $limit = isset($this->params['limit']) ? intval($this->params['limit']) : null;
      return $this->user->getAll($limit);
  }

  #[Route("GET", "/users_injection")]
  public function getUserInjection() {
    return $this->user->getWithoutPrepare($this->body['data']);
  }

  #[Route("GET", "/users_injection_blocked")]
  public function getUserInjectionBlocked() {
    return $this->user->getWithPrepare($this->body['data']);
  }

  #[Route("PATCH", "/users/:id")]
  public function updateUser() {
    try {
      $id = intval($this->params['id']);
      $data = $this->body;

      # Check if the data is empty
      if (empty($data)) {
        throw new HttpException("Missing parameters for the update.", 400);
      }

      # Check for missing fields
      $missingFields = array_diff($this->user->authorized_fields_to_update, array_keys($data));
      if (!empty($missingFields)) {
        throw new HttpException("Missing fields: " . implode(", ", $missingFields), 400);
      }

      $this->user->update($data, intval($id));

      # Let's return the updated user
      return $this->user->get($id);
    } catch (HttpException $e) {
      throw $e;
    }
  }
}
