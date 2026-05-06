<?php
/**
 * Agent model.
 */
class Agent
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function register(int $computerId, string $tokenHash): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO agents (computer_id, token_hash, registered_at, last_seen, is_active)
             VALUES (:computer_id, :token_hash, NOW(), NOW(), 1)'
        );
        $stmt->execute([
            ':computer_id' => $computerId,
            ':token_hash'  => $tokenHash,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findByTokenHash(string $hash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM agents WHERE token_hash = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateLastSeen(int $id): void
    {
        $this->db->prepare(
            'UPDATE agents SET last_seen = NOW() WHERE id = ?'
        )->execute([$id]);
    }

    public function deactivate(int $id): void
    {
        $this->db->prepare(
            'UPDATE agents SET is_active = 0 WHERE id = ?'
        )->execute([$id]);
    }

    public function getByComputerId(int $computerId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM agents WHERE computer_id = ? ORDER BY registered_at DESC LIMIT 1'
        );
        $stmt->execute([$computerId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
