<?php 

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\AuthModel;
use App\Utils\{Route, HttpException};

class Auth extends Controller {
  protected object $auth;

  public function __construct($params) {
    $this->auth = new AuthModel();
    parent::__construct($params);
  }


  #[Route("POST", "/auth/register")]
  public function register() {
      try {
          $data = $this->body;
          if (empty($data['email']) || empty($data['password']) || empty($data['username'])) {
              throw new HttpException("Missing email, username or password.", 400);
          }
          $user = $this->auth->register($data);
          return $user;
      } catch (\Exception $e) {
          throw new HttpException($e->getMessage(), 400);
      }
  }

  #[Route("POST", "/auth/login")]
  public function login() {
      try {
          $data = $this->body;
          if (empty($data['email']) || empty($data['password'])) {
              throw new HttpException("Missing email or password.", 400);
          }
          $tokens = $this->auth->login($data['email'], $data['password']);
          return $tokens;
      } catch (\Exception $e) {
          throw new HttpException($e->getMessage(), 401);
      }
  }

}