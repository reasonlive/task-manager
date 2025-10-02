<?php
declare(strict_types=1);
namespace App\Core\Http;

class HttpRouter
{
    private array $routes = [];
    private array $middlewares = [];
    private Request $request;
    private Response $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function get(string $path, $callback): self
    {
        $this->routes['GET'][$path] = $callback;
        $this->routes['OPTIONS'][$path] = $callback;

        return $this;
    }

    public function post(string $path, $callback): self
    {
        $this->routes['POST'][$path] = $callback;
        $this->routes['OPTIONS'][$path] = $callback;

        return $this;
    }

    public function delete(string $path, $callback): self
    {
        $this->routes['DELETE'][$path] = $callback;
        $this->routes['OPTIONS'][$path] = $callback;
        return $this;
    }

    public function setMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function resolve()
    {
        $path = $this->request->getPath();
        $method = $this->request->getMethod();

        foreach ($this->routes[$method] ?? [] as $route => $callback) {
            $params = $this->matchRoute($route, $path);

            if ($params !== false) {
                return $this->executeCallback($callback, $params);
            }
        }

        $this->response->setStatusCode(404);
        return "Not Found";
    }

    private function matchRoute(string $route, string $path): array|false
    {
        // Заменяем {param} на regex группы
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '([^/]+)', $route);
        $pattern = "#^{$pattern}$#";

        if (preg_match($pattern, $path, $matches)) {
            // Убираем полное совпадение (индекс 0), оставляем только группы
            array_shift($matches);
            return $matches;
        }

        return false;
    }

    private function executeCallback($callback, array $params = [])
    {
        if (is_array($callback)) {
            $controller = new $callback[0]();
            $method = $callback[1];

            if ($controller instanceof BaseController) {
                $controller
                    ->setRequest($this->request)
                    ->setResponse($this->response);

                if (count($this->middlewares)) {
                    foreach ($this->middlewares as $middleware) {
                        $controller->setMiddleware($middleware);
                    }
                }
            }

            return call_user_func_array([$controller, $method], $params);
        }

        return call_user_func_array($callback, array_merge([$this->request], $params));
    }
}