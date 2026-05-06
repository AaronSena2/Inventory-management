<?php
/**
 * App – front-controller router.
 */
class App
{
    public function run(): void
    {
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Strip query string
        $path = parse_url($uri, PHP_URL_PATH);
        $path = '/' . trim($path, '/');

        // Normalize root
        if ($path === '') {
            $path = '/';
        }

        $this->dispatch($method, $path);
    }

    private function dispatch(string $method, string $path): void
    {
        // -------------------------------------------------------
        // Auth routes
        // -------------------------------------------------------
        if ($method === 'GET' && $path === '/login') {
            (new AuthController())->loginForm();
            return;
        }

        if ($method === 'POST' && $path === '/login') {
            (new AuthController())->login();
            return;
        }

        if ($method === 'GET' && $path === '/logout') {
            (new AuthController())->logout();
            return;
        }

        // -------------------------------------------------------
        // Dashboard
        // -------------------------------------------------------
        if ($method === 'GET' && ($path === '/' || $path === '/dashboard')) {
            (new DashboardController())->index();
            return;
        }

        // -------------------------------------------------------
        // Computers
        // -------------------------------------------------------
        if ($method === 'GET' && $path === '/computers') {
            (new ComputersController())->index();
            return;
        }

        // /computers/{id}
        if ($method === 'GET' && preg_match('#^/computers/(\d+)$#', $path, $m)) {
            (new ComputersController())->detail((int)$m[1]);
            return;
        }

        // /computers/{id}/delete
        if ($method === 'POST' && preg_match('#^/computers/(\d+)/delete$#', $path, $m)) {
            (new ComputersController())->delete((int)$m[1]);
            return;
        }

        // -------------------------------------------------------
        // Commands
        // -------------------------------------------------------
        if ($method === 'GET' && $path === '/commands') {
            (new CommandsController())->index();
            return;
        }

        if ($method === 'POST' && $path === '/commands') {
            (new CommandsController())->create();
            return;
        }

        // /commands/{id}/cancel
        if ($method === 'POST' && preg_match('#^/commands/(\d+)/cancel$#', $path, $m)) {
            (new CommandsController())->cancel((int)$m[1]);
            return;
        }

        // -------------------------------------------------------
        // Agent API (token auth, no session)
        // -------------------------------------------------------
        if ($method === 'POST' && $path === '/api/agent/register') {
            (new AgentApiController())->register();
            return;
        }

        if ($method === 'POST' && $path === '/api/agent/heartbeat') {
            (new AgentApiController())->heartbeat();
            return;
        }

        if ($method === 'GET' && $path === '/api/agent/commands') {
            (new AgentApiController())->commands();
            return;
        }

        if ($method === 'POST' && $path === '/api/agent/command-result') {
            (new AgentApiController())->commandResult();
            return;
        }

        // -------------------------------------------------------
        // Admin API (session or X-Admin-Token)
        // -------------------------------------------------------
        if ($method === 'GET' && $path === '/api/computers') {
            (new AdminApiController())->computers();
            return;
        }

        if ($method === 'POST' && $path === '/api/commands') {
            (new AdminApiController())->createCommand();
            return;
        }

        // -------------------------------------------------------
        // 404
        // -------------------------------------------------------
        http_response_code(404);
        // Serve a simple HTML 404 for browser requests, JSON for API
        if (str_starts_with($path, '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not found']);
        } else {
            echo '<!DOCTYPE html><html><head><title>404</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-dark text-white d-flex justify-content-center align-items-center" style="height:100vh">
<div class="text-center">
  <h1 class="display-1">404</h1>
  <p class="lead">Page not found.</p>
  <a href="/dashboard" class="btn btn-primary">Go Home</a>
</div>
</body></html>';
        }
    }
}
