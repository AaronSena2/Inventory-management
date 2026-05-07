<?php
/**
 * Command model.
 */
class Command
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO commands
               (computer_id, created_by, command_type, payload, status, scheduled_at)
             VALUES
               (:computer_id, :created_by, :command_type, :payload, :status, :scheduled_at)'
        );
        $stmt->execute([
            ':computer_id'  => $data['computer_id'],
            ':created_by'   => $data['created_by'],
            ':command_type' => $data['command_type'],
            ':payload'      => isset($data['payload']) ? json_encode($data['payload']) : null,
            ':status'       => $data['status'] ?? 'pending',
            ':scheduled_at' => $data['scheduled_at'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Get pending commands for a computer (ready to execute, not yet sent).
     */
    public function getPending(int $computerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM commands
             WHERE computer_id = :cid
               AND status = 'pending'
               AND (scheduled_at IS NULL OR scheduled_at <= NOW())
             ORDER BY created_at ASC"
        );
        $stmt->execute([':cid' => $computerId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM commands WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->prepare(
            'UPDATE commands SET status = ? WHERE id = ?'
        )->execute([$status, $id]);
    }

    public function markSent(int $id): void
    {
        $this->updateStatus($id, 'sent');
    }

    /**
     * Get commands with optional filters.
     *
     * $filters keys: computer_id, status, command_type, limit, offset
     */
    public function getAll(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['computer_id'])) {
            $where[]               = 'c.computer_id = :computer_id';
            $params[':computer_id'] = (int)$filters['computer_id'];
        }

        if (!empty($filters['status'])) {
            $where[]          = 'c.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['command_type'])) {
            $where[]               = 'c.command_type = :command_type';
            $params[':command_type'] = $filters['command_type'];
        }

        $sql = 'SELECT c.*, comp.hostname, u.username AS created_by_name
                FROM commands c
                LEFT JOIN computers comp ON comp.id = c.computer_id
                LEFT JOIN users u ON u.id = c.created_by';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql   .= ' ORDER BY c.created_at DESC';

        $limit  = isset($filters['limit'])  ? (int)$filters['limit']  : 100;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
        $sql   .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        $where  = [];
        $params = [];

        if (!empty($filters['computer_id'])) {
            $where[]               = 'computer_id = :computer_id';
            $params[':computer_id'] = (int)$filters['computer_id'];
        }
        if (!empty($filters['status'])) {
            $where[]          = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['command_type'])) {
            $where[]               = 'command_type = :command_type';
            $params[':command_type'] = $filters['command_type'];
        }

        $sql = 'SELECT COUNT(*) FROM commands';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getByComputerId(int $computerId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, u.username AS created_by_name
             FROM commands c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.computer_id = ?
             ORDER BY c.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$computerId, $limit]);
        return $stmt->fetchAll();
    }
}
