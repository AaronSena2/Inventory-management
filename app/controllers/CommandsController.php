<?php
/**
 * CommandsController – list, create, cancel commands.
 */
class CommandsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $perPage = 25;
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $filters = [
            'computer_id'  => (int)($_GET['computer_id'] ?? 0) ?: null,
            'status'       => $_GET['status'] ?? '',
            'command_type' => $_GET['command_type'] ?? '',
            'limit'        => $perPage,
            'offset'       => ($page - 1) * $perPage,
        ];

        // Remove empty filters
        $filters = array_filter($filters, fn($v) => $v !== '' && $v !== null && $v !== 0);
        $filters['limit']  = $perPage;
        $filters['offset'] = ($page - 1) * $perPage;

        $commandModel = new Command();
        $commands     = $commandModel->getAll($filters);
        $total        = $commandModel->count($filters);
        $totalPages   = (int)ceil($total / $perPage);

        $computers = (new Computer())->getAll(['limit' => 500]);

        $this->render('commands/index', [
            'pageTitle'  => 'Commands – ' . APP_NAME,
            'commands'   => $commands,
            'computers'  => $computers,
            'filters'    => $filters,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            $this->redirect('/commands?error=csrf');
            return;
        }

        $computerId  = (int)($_POST['computer_id']  ?? 0);
        $commandType = trim($_POST['command_type']  ?? '');
        $payloadRaw  = trim($_POST['payload']        ?? '');
        $scheduledAt = trim($_POST['scheduled_at']   ?? '');

        $validTypes = ['patch', 'install', 'uninstall', 'shell', 'restart', 'shutdown'];

        if ($computerId <= 0 || !in_array($commandType, $validTypes, true)) {
            $this->redirect('/commands?error=invalid');
            return;
        }

        // Validate computer exists
        $computer = (new Computer())->getById($computerId);
        if (!$computer) {
            $this->redirect('/commands?error=nocomputer');
            return;
        }

        // Parse payload
        $payload = [];
        if ($payloadRaw !== '') {
            $decoded = json_decode($payloadRaw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                // Treat as simple string payload
                $payload = ['raw' => $payloadRaw];
            }
        }

        $user = Auth::getUser();
        (new Command())->create([
            'computer_id'  => $computerId,
            'created_by'   => $user['id'],
            'command_type' => $commandType,
            'payload'      => $payload,
            'status'       => 'pending',
            'scheduled_at' => $scheduledAt !== '' ? $scheduledAt : null,
        ]);

        $this->redirect('/commands?success=1');
    }

    public function cancel(int $id): void
    {
        $this->requireAdmin();

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            $this->redirect('/commands?error=csrf');
            return;
        }

        $commandModel = new Command();
        $command      = $commandModel->getById($id);

        if ($command && $command['status'] === 'pending') {
            $commandModel->updateStatus($id, 'cancelled');
        }

        $this->redirect('/commands?success=cancelled');
    }
}
