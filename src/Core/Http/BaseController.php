<?php

namespace App\Core\Http;

use App\Core\Auth\SessionService;
use App\Views\View;

abstract class BaseController
{
    protected Request $request;
    protected Response $response;
    protected View $view;
    protected SessionService $session;

    public bool $admin = false; // need for checking admin access
    public bool $api = false; // need for checking api access
    protected ?array $authenticatedUser = null;

    public function __construct()
    {
        $this->view = new View(__DIR__ . '/../../../templates');
        $this->session = new SessionService();
    }

    public function setRequest(Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setResponse(Response $response): static
    {
        $this->response = $response;
        return $this;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function getSession(): SessionService
    {
        return $this->session;
    }

    public function getUser(): ?array
    {
        return $this->authenticatedUser;
    }

    public function setUser(?array $authenticatedUser): static
    {
        $this->authenticatedUser = $authenticatedUser;
        return $this;
    }

    public function setMiddleware(MiddlewareInterface $middleware): static
    {
        $middleware->preInstallController($this);
        return $this;
    }

    protected function json(array $data, int $statusCode = 200): void
    {
        $this->response->setStatusCode($statusCode);
        $this->response->json($data);
    }

    protected function redirect(string $url): void
    {
        $this->response->redirect($url);
    }

    protected function render(string $template, array $data = []): void
    {
        echo $this->view->render($template, $data);
        $this->response->setStatusCode(200);
        exit;
    }
}