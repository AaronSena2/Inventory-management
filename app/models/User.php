<?php
/**
 * User model.
 */
class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash, role)
             VALUES (:username, :email, :password_hash, :role)'
        );
        $stmt->execute([
            ':username'      => $data['username'],
            ':email'         => $data['email'] ?? '',
            ':password_hash' => $data['password_hash'],
            ':role'          => $data['role'] ?? 'viewer',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updatePassword(int $id, string $hash): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET password_hash = ? WHERE id = ?'
        );
        $stmt->execute([$hash, $id]);
    }

    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, username, email, role, created_at FROM users ORDER BY username'
        );
        return $stmt->fetchAll();
    }
}
