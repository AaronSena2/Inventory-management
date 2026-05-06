<?php
/**
 * DashboardController – main landing page.
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $computerModel = new Computer();
        $commandModel  = new Command();

        $stats          = $computerModel->getStats();
        $pendingCount   = $commandModel->count(['status' => 'pending']);
        $recentCommands = $commandModel->getAll(['limit' => 10]);

        $this->render('dashboard/index', [
            'pageTitle'      => 'Dashboard – ' . APP_NAME,
            'stats'          => $stats,
            'pendingCount'   => $pendingCount,
            'recentCommands' => $recentCommands,
        ]);
    }
}
