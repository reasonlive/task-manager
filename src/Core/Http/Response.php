<?php

namespace App\Core\Http;

class Response
{
    public function setStatusCode(int $code): self
    {
        http_response_code($code);
        return $this;
    }

    public function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    public function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public function setHeader(string $header): self
    {
        header($header);
        return $this;
    }
}