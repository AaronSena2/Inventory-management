<?php
/**
 * CommandResult model.
 */
class CommandResult
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO command_results
               (command_id, computer_id, exit_code, output, error_output, executed_at)
             VALUES
               (:command_id, :computer_id, :exit_code, :output, :error_output, :executed_at)'
        );
        $stmt->execute([
            ':command_id'   => $data['command_id'],
            ':computer_id'  => $data['computer_id'],
            ':exit_code'    => $data['exit_code']   ?? null,
            ':output'       => $data['output']      ?? null,
            ':error_output' => $data['error_output'] ?? null,
            ':executed_at'  => $data['executed_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getByCommandId(int $commandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM command_results WHERE command_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$commandId]);
        return $stmt->fetchAll();
    }

    public function getByComputerId(int $computerId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT cr.*, c.command_type, c.status AS command_status
             FROM command_results cr
             JOIN commands c ON c.id = cr.command_id
             WHERE cr.computer_id = ?
             ORDER BY cr.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$computerId, $limit]);
        return $stmt->fetchAll();
    }
}
