<?php

namespace App\Core\Http;

interface MiddlewareInterface
{
    public function preInstallController(BaseController $controller): void;
}