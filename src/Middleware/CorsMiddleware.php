<?php

namespace App\Middleware;

use App\Core\Http\BaseController;
use App\Core\Http\MiddlewareInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function preInstallController(BaseController $controller): void
    {
        $controller->getResponse()
            ->setHeader('Access-Control-Allow-Origin: *')
            ->setHeader('Access-Control-Allow-Headers: *')
            ->setHeader('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE')
            //->setHeader('Access-Control-Allow-Credentials: true')
            //->setHeader('Access-Control-Max-Age: 86400')
        ;

        if ($controller->getRequest()->getMethod() === 'OPTIONS') {
            $controller->getResponse()->setStatusCode(204);
            exit;
        }
    }
}