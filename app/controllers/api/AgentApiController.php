<?php
/**
 * AgentApiController – REST API consumed by the Python agent.
 * All responses are JSON; authentication is via X-Agent-Token header.
 */
class AgentApiController extends Controller
{
    /**
     * POST /api/agent/register
     *
     * Body (JSON):
     *   hostname, ip_address, mac_address, os_name, os_version, os_arch,
     *   cpu_info, ram_gb, storage_gb
     *
     * Returns: { "token": "<plaintext>", "computer_id": <int> }
     */
    public function register(): void
    {
        $data = $this->getJsonBody();

        $hostname = trim($data['hostname'] ?? '');
        if ($hostname === '') {
            $this->json(['error' => 'hostname is required'], 422);
        }

        // Generate a token
        $token     = bin2hex(random_bytes((int)AGENT_TOKEN_LENGTH / 2));
        $tokenHash = hash('sha256', $token);

        $computerModel = new Computer();

        // Check if a computer with this hostname+mac already exists
        $existing = null;
        if (!empty($data['mac_address'])) {
            $all = $computerModel->getAll(['search' => $data['mac_address'], 'limit' => 1]);
            foreach ($all as $row) {
                if ($row['mac_address'] === $data['mac_address']) {
                    $existing = $row;
                    break;
                }
            }
        }

        if ($existing) {
            $computerId = (int)$existing['id'];
            // Update computer info
            $computerModel->update($computerId, [
                'hostname'    => $hostname,
                'ip_address'  => $data['ip_address']  ?? $existing['ip_address'],
                'os_name'     => $data['os_name']     ?? $existing['os_name'],
                'os_version'  => $data['os_version']  ?? $existing['os_version'],
                'os_arch'     => $data['os_arch']     ?? $existing['os_arch'],
                'cpu_info'    => $data['cpu_info']    ?? $existing['cpu_info'],
                'ram_gb'      => $data['ram_gb']      ?? $existing['ram_gb'],
                'storage_gb'  => $data['storage_gb']  ?? $existing['storage_gb'],
                'last_checkin'=> date('Y-m-d H:i:s'),
                'status'      => 'online',
            ]);
            // Deactivate old agents
            $agentModel = new Agent();
            $oldAgent   = $agentModel->getByComputerId($computerId);
            if ($oldAgent) {
                $agentModel->deactivate((int)$oldAgent['id']);
            }
        } else {
            $computerId = $computerModel->create([
                'hostname'    => $hostname,
                'ip_address'  => $data['ip_address']  ?? '',
                'mac_address' => $data['mac_address']  ?? '',
                'os_name'     => $data['os_name']     ?? '',
                'os_version'  => $data['os_version']  ?? '',
                'os_arch'     => $data['os_arch']     ?? '',
                'cpu_info'    => $data['cpu_info']    ?? '',
                'ram_gb'      => $data['ram_gb']      ?? 0,
                'storage_gb'  => $data['storage_gb']  ?? 0,
                'last_checkin'=> date('Y-m-d H:i:s'),
                'status'      => 'online',
            ]);
        }

        (new Agent())->register($computerId, $tokenHash);

        $this->json([
            'token'       => $token,
            'computer_id' => $computerId,
            'message'     => 'Registered successfully',
        ], 201);
    }

    /**
     * POST /api/agent/heartbeat
     *
     * Header: X-Agent-Token
     * Body (JSON): hostname, ip_address, os_name, os_version, cpu_info, ram_gb,
     *              storage_gb, software (array)
     */
    public function heartbeat(): void
    {
        $agent = $this->requireAgentAuth();
        $data  = $this->getJsonBody();

        $computerId    = (int)$agent['computer_id'];
        $computerModel = new Computer();

        $update = [
            'last_checkin' => date('Y-m-d H:i:s'),
            'status'       => 'online',
        ];

        $fields = ['hostname', 'ip_address', 'os_name', 'os_version', 'os_arch',
                   'cpu_info', 'ram_gb', 'storage_gb'];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $update[$f] = $data[$f];
            }
        }

        $computerModel->update($computerId, $update);

        // Sync software inventory if provided
        if (isset($data['software']) && is_array($data['software'])) {
            (new SoftwareInventory())->syncForComputer($computerId, $data['software']);
        }

        $this->json(['status' => 'ok', 'timestamp' => date('c')]);
    }

    /**
     * GET /api/agent/commands
     *
     * Header: X-Agent-Token
     * Returns pending commands and marks them as sent.
     */
    public function commands(): void
    {
        $agent = $this->requireAgentAuth();

        $computerId   = (int)$agent['computer_id'];
        $commandModel = new Command();
        $pending      = $commandModel->getPending($computerId);

        $result = [];
        foreach ($pending as $cmd) {
            $commandModel->markSent((int)$cmd['id']);
            $result[] = [
                'id'           => (int)$cmd['id'],
                'command_type' => $cmd['command_type'],
                'payload'      => $cmd['payload'] ? json_decode($cmd['payload'], true) : null,
                'scheduled_at' => $cmd['scheduled_at'],
            ];
        }

        $this->json(['commands' => $result]);
    }

    /**
     * POST /api/agent/command-result
     *
     * Header: X-Agent-Token
     * Body (JSON): command_id, exit_code, output, error_output, executed_at
     */
    public function commandResult(): void
    {
        $agent = $this->requireAgentAuth();
        $data  = $this->getJsonBody();

        $commandId = (int)($data['command_id'] ?? 0);
        if ($commandId <= 0) {
            $this->json(['error' => 'command_id is required'], 422);
        }

        $commandModel = new Command();
        $command      = $commandModel->getById($commandId);

        if (!$command || (int)$command['computer_id'] !== (int)$agent['computer_id']) {
            $this->json(['error' => 'Command not found or not yours'], 404);
        }

        $exitCode = isset($data['exit_code']) ? (int)$data['exit_code'] : null;
        $status   = ($exitCode === 0 || $exitCode === null) ? 'completed' : 'failed';

        (new CommandResult())->create([
            'command_id'   => $commandId,
            'computer_id'  => (int)$agent['computer_id'],
            'exit_code'    => $exitCode,
            'output'       => $data['output']       ?? null,
            'error_output' => $data['error_output'] ?? null,
            'executed_at'  => $data['executed_at']  ?? date('Y-m-d H:i:s'),
        ]);

        $commandModel->updateStatus($commandId, $status);

        $this->json(['status' => 'recorded']);
    }
}
