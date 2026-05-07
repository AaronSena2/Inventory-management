<?php
/**
 * AdminApiController – REST API for admin consumers (dashboard AJAX, external tools).
 * Requires active session (admin) or X-Admin-Token header.
 */
class AdminApiController extends Controller
{
    /**
     * GET /api/computers
     * Returns JSON list of computers with current status.
     */
    public function computers(): void
    {
        $this->requireApiAuth();

        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'status' => $_GET['status'] ?? '',
            'limit'  => min((int)($_GET['limit'] ?? 100), 500),
            'offset' => (int)($_GET['offset'] ?? 0),
        ];

        $computerModel = new Computer();
        $computers     = $computerModel->getAll($filters);
        $total         = $computerModel->count($filters);

        $this->json([
            'computers' => $computers,
            'total'     => $total,
        ]);
    }

    /**
     * POST /api/commands
     * Body (JSON): computer_id, command_type, payload (object), scheduled_at
     */
    public function createCommand(): void
    {
        $this->requireApiAuth();

        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Admin role required'], 403);
        }

        $data = $this->getJsonBody();

        $computerId  = (int)($data['computer_id']  ?? 0);
        $commandType = trim($data['command_type']   ?? '');
        $validTypes  = ['patch', 'install', 'uninstall', 'shell', 'restart', 'shutdown'];

        if ($computerId <= 0 || !in_array($commandType, $validTypes, true)) {
            $this->json(['error' => 'Invalid computer_id or command_type'], 422);
        }

        $computer = (new Computer())->getById($computerId);
        if (!$computer) {
            $this->json(['error' => 'Computer not found'], 404);
        }

        $user      = Auth::getUser();
        $commandId = (new Command())->create([
            'computer_id'  => $computerId,
            'created_by'   => $user['id'],
            'command_type' => $commandType,
            'payload'      => $data['payload']      ?? [],
            'status'       => 'pending',
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);

        $this->json(['command_id' => $commandId, 'status' => 'pending'], 201);
    }
}
