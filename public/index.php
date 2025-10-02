<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\Admin\AdminController;
use App\Controllers\Admin\TaskController;
use App\Controllers\Admin\UserController;
use App\Controllers\Api\AuthController as ApiAuth;
use App\Controllers\Api\TaskController as ApiTasks;
use App\Controllers\AuthController;
use App\Core\Env;
use App\Core\Http\HttpRouter;
use App\Core\Http\Request;
use App\Core\Http\Response;

// Initialization
Env::load(substr(getcwd(), 0, -6) . '.env');
$router = new HttpRouter(new Request(), new Response());
$router
    ->setMiddleware(new \App\Middleware\AdminAccessMiddleware())
    ->setMiddleware(new \App\Middleware\ApiAccessMiddleware())
    ->setMiddleware(new \App\Middleware\CorsMiddleware());

// // Routes
// API
$router->post('/api/register', [ApiAuth::class, 'register']);
$router->post('/api/login', [ApiAuth::class, 'login']);
// Tasks
$router->get('/api/tasks', [ApiTasks::class, 'index']);
$router->get('/api/tasks/{id}', [ApiTasks::class, 'show']);
$router->post('/api/tasks', [ApiTasks::class, 'create']);
$router->post('/api/tasks/update', [ApiTasks::class, 'update']);
$router->delete('/api/tasks/delete/{id}', [ApiTasks::class, 'destroy']);

// Admin auth
$router->get('/', [AuthController::class, 'home']);
$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// Admin panel
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/dashboard', [AdminController::class, 'dashboard']);
// Tasks
$router->get('/admin/tasks', [TaskController::class, 'index']);
$router->get('/admin/tasks/{id}', [TaskController::class, 'show']);
$router->post('/admin/tasks', [TaskController::class, 'create']);
$router->post('/admin/tasks/update', [TaskController::class, 'update']);
$router->post('/admin/tasks/tags', [TaskController::class, 'manageTaskTags']);
$router->post('/admin/tasks/replies/add', [TaskController::class, 'addReply']);
// Users
$router->get('/admin/users', [UserController::class, 'index']);
$router->get('/admin/users/{id}', [UserController::class, 'show']);
$router->post('/admin/users', [UserController::class, 'create']);
$router->post('/admin/users/update', [UserController::class, 'update']);

// Start
echo $router->resolve();