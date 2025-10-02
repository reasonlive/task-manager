<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Http\BaseController;
use App\Core\Http\MiddlewareInterface;

class AdminAccessMiddleware implements MiddlewareInterface
{
    public function preInstallController(BaseController $controller): void
    {
        if ($controller->admin && !$controller->getSession()->hasRole('ADMIN')) {
            $controller->setUser($controller->getSession()->getCurrentUser());
            $controller->getResponse()->redirect('/login');
        }
    }
}