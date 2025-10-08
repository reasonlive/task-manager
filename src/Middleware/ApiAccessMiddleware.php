<?php

namespace App\Middleware;

use App\Core\Auth\JwtService;
use App\Core\Http\BaseController;
use App\Core\Http\MiddlewareInterface;
use App\Exceptions\UnauthorizedException;
use App\Models\User;

class ApiAccessMiddleware implements MiddlewareInterface
{
    public function preInstallController(BaseController $controller): void
    {
        if ($controller->api && $controller->getRequest()->getMethod() !== 'OPTIONS') {
            try {
                $instance = new JwtService();

                $token = $instance->extractTokenFromRequest($controller->getRequest());
                $instance->validateToken($token);

                $data = $instance->getPayload($token);

                if (!$user = User::load($data['user_id'])) {
                    throw new UnauthorizedException();
                }

                $controller->setUser($user);
            } catch (\Throwable $e) {
                $exception = new UnauthorizedException();

                $controller->getResponse()
                    ->setStatusCode($exception->getCode())
                    ->json([
                    'success' => false,
                    'error' => $exception->getMessage()
                ]);
            }
        }
    }
}