<?php
/**
 * Computer model.
 */
class Computer
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Return all computers with optional search/filter.
     *
     * $filters keys: search (string), status (online|offline|unknown), limit, offset
     */
    public function getAll(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]          = '(hostname LIKE :search OR ip_address LIKE :search OR mac_address LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[]          = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        $sql = 'SELECT * FROM computers';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY hostname';

        $limit  = isset($filters['limit'])  ? (int)$filters['limit']  : 100;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
        $sql   .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count computers (for pagination).
     */
    public function count(array $filters = []): int
    {
        $where  = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]          = '(hostname LIKE :search OR ip_address LIKE :search OR mac_address LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[]          = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        $sql = 'SELECT COUNT(*) FROM computers';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM computers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO computers
               (hostname, ip_address, mac_address, os_name, os_version, os_arch,
                cpu_info, ram_gb, storage_gb, last_checkin, status)
             VALUES
               (:hostname, :ip_address, :mac_address, :os_name, :os_version, :os_arch,
                :cpu_info, :ram_gb, :storage_gb, :last_checkin, :status)'
        );
        $stmt->execute([
            ':hostname'    => $data['hostname'],
            ':ip_address'  => $data['ip_address']  ?? '',
            ':mac_address' => $data['mac_address']  ?? '',
            ':os_name'     => $data['os_name']      ?? '',
            ':os_version'  => $data['os_version']   ?? '',
            ':os_arch'     => $data['os_arch']      ?? '',
            ':cpu_info'    => $data['cpu_info']     ?? '',
            ':ram_gb'      => $data['ram_gb']       ?? 0,
            ':storage_gb'  => $data['storage_gb']   ?? 0,
            ':last_checkin'=> $data['last_checkin'] ?? null,
            ':status'      => $data['status']       ?? 'unknown',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        $allowed = [
            'hostname', 'ip_address', 'mac_address', 'os_name', 'os_version',
            'os_arch', 'cpu_info', 'ram_gb', 'storage_gb', 'last_checkin', 'status',
        ];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]        = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($fields)) {
            return;
        }

        $params[':id'] = $id;
        $sql = 'UPDATE computers SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM computers WHERE id = ?')->execute([$id]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->prepare(
            'UPDATE computers SET status = ? WHERE id = ?'
        )->execute([$status, $id]);
    }

    /**
     * Refresh stale "online" status based on ONLINE_THRESHOLD.
     */
    public function refreshStatuses(): void
    {
        $this->db->prepare(
            "UPDATE computers
             SET status = 'offline'
             WHERE status = 'online'
               AND (last_checkin IS NULL OR last_checkin < DATE_SUB(NOW(), INTERVAL :threshold SECOND))"
        )->execute([':threshold' => ONLINE_THRESHOLD]);
    }

    public function getStats(): array
    {
        // First refresh stale statuses
        $this->refreshStatuses();

        $stmt = $this->db->query(
            "SELECT
               COUNT(*) AS total,
               SUM(status = 'online')  AS online,
               SUM(status = 'offline') AS offline,
               SUM(status = 'unknown') AS unknown
             FROM computers"
        );
        $row = $stmt->fetch();
        return [
            'total'   => (int)$row['total'],
            'online'  => (int)$row['online'],
            'offline' => (int)$row['offline'],
            'unknown' => (int)$row['unknown'],
        ];
    }
}
