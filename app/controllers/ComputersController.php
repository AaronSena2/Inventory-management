<?php
/**
 * ComputersController – list, detail, delete computers.
 */
class ComputersController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $perPage = 20;
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'status' => $_GET['status'] ?? '',
            'limit'  => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        $computerModel = new Computer();
        $computers     = $computerModel->getAll($filters);
        $total         = $computerModel->count($filters);
        $totalPages    = (int)ceil($total / $perPage);

        $this->render('computers/index', [
            'pageTitle'   => 'Computers – ' . APP_NAME,
            'computers'   => $computers,
            'filters'     => $filters,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
        ]);
    }

    public function detail(int $id): void
    {
        $this->requireAuth();

        $computerModel = new Computer();
        $computer      = $computerModel->getById($id);

        if (!$computer) {
            http_response_code(404);
            echo '<h1>Computer not found</h1>';
            return;
        }

        $software = (new SoftwareInventory())->getByComputerId($id);
        $commands = (new Command())->getByComputerId($id);

        $this->render('computers/detail', [
            'pageTitle' => htmlspecialchars($computer['hostname']) . ' – ' . APP_NAME,
            'computer'  => $computer,
            'software'  => $software,
            'commands'  => $commands,
        ]);
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $computerModel = new Computer();
        $computer      = $computerModel->getById($id);

        if ($computer) {
            $computerModel->delete($id);
        }

        $this->redirect('/computers');
    }
}
