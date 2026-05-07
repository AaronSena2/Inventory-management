<?php
/**
 * Base Controller – shared helpers for all controllers.
 */
class Controller
{
    /**
     * Render a view file, extracting $data as variables.
     */
    protected function render(string $view, array $data = []): void
    {
        $data['currentUser'] = Auth::getUser();
        $data['pageTitle']   = $data['pageTitle'] ?? APP_NAME;

        extract($data, EXTR_SKIP);

        $viewPath = BASE_PATH . '/app/views/' . ltrim($view, '/') . '.php';
        if (!file_exists($viewPath)) {
            http_response_code(500);
            echo "View not found: " . htmlspecialchars($view);
            exit;
        }

        require $viewPath;
    }

    /**
     * HTTP redirect.
     */
    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    /**
     * Output JSON response and exit.
     */
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Require an authenticated session; redirect to /login otherwise.
     */
    protected function requireAuth(): void
    {
        if (!Auth::isLoggedIn()) {
            $this->redirect('/login');
        }
    }

    /**
     * Require admin role; 403 otherwise.
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if (!Auth::isAdmin()) {
            http_response_code(403);
            echo '<h1>403 – Forbidden</h1><p>Administrator access required.</p>';
            exit;
        }
    }

    /**
     * Require valid X-Agent-Token header; json-error otherwise.
     * Returns the agent row on success.
     */
    protected function requireAgentAuth(): array
    {
        $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
        if ($token === '') {
            $this->json(['error' => 'Missing X-Agent-Token header'], 401);
        }

        $hash  = hash('sha256', $token);
        $agent = (new Agent())->findByTokenHash($hash);

        if (!$agent || !$agent['is_active']) {
            $this->json(['error' => 'Invalid or inactive agent token'], 401);
        }

        // Update last_seen
        (new Agent())->updateLastSeen((int)$agent['id']);

        return $agent;
    }

    /**
     * Require session admin OR X-Admin-Token header matching APP_ADMIN_TOKEN (if defined).
     */
    protected function requireApiAuth(): void
    {
        // Allow active session admins
        if (Auth::isLoggedIn() && Auth::isAdmin()) {
            return;
        }

        // Allow X-Admin-Token header (optional env-based token)
        $headerToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
        if (defined('ADMIN_API_TOKEN') && $headerToken !== '' && hash_equals(ADMIN_API_TOKEN, $headerToken)) {
            return;
        }

        $this->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Get the raw JSON request body decoded as an array.
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Get POST body – falls back to JSON body when Content-Type is application/json.
     */
    protected function getBody(): array
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            return $this->getJsonBody();
        }
        return $_POST;
    }
}
