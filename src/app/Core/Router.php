<?php
namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }

            $params = $this->match($routePath, $uri);
            if ($params !== null) {
                $handler($params);
                return;
            }
        }

        http_response_code(404);
        include APP_PATH . 'Shared/header.php';
        echo '<main style="padding:2rem"><section class="card"><h1>404 — Page Not Found</h1>'
           . '<p class="text-muted">The page you requested does not exist.</p>'
           . '<a href="/dashboard" class="btn btn-primary">Back to Dashboard</a></section></main>';
        include APP_PATH . 'Shared/footer.php';
    }

    private function match(string $pattern, string $uri): ?array
    {
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $uri, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
