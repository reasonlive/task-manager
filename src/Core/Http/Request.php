<?php

namespace App\Core\Http;

class Request
{
    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function getHeader(string $header): ?string
    {
        return $_SERVER[$header] ?? null;
    }

    public function getAuthorizationHeader(): ?string
    {
        return $this->getHeader('HTTP_AUTHORIZATION');
    }

    public function getPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($path, '?');

        if ($position === false) {
            return $path;
        }

        return substr($path, 0, $position);
    }

    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    public function isDelete(): bool
    {
        return $this->getMethod() === 'DELETE';
    }

    public function isJson(): bool
    {
        return str_contains(strtolower($_SERVER['HTTP_CONTENT_TYPE']), 'json');
    }

    public function getBody(): array
    {
        $body = [];

        if ($this->isGet() || $this->isDelete()) {
            foreach ($_GET as $key => $value) {
                $body[$key] = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }

        if ($this->isPost()) {
            if ($this->isJson()) {
                $json_data = file_get_contents('php://input');
                $body = json_decode($json_data, true);
            } else {
                foreach ($_POST as $key => $value) {
                    $body[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
                }
            }
        }

        return $body;
    }

    public function get(string $key, $default = null)
    {
        return $this->getBody()[$key] ?? $default;
    }
}