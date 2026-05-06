<?php
/**
 * SoftwareInventory model.
 */
class SoftwareInventory
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Replace all software records for a computer with the provided list.
     *
     * @param int   $computerId
     * @param array $softwareList  Array of ['name'=>..., 'version'=>..., 'publisher'=>..., 'install_date'=>...]
     */
    public function syncForComputer(int $computerId, array $softwareList): void
    {
        // Delete existing
        $this->db->prepare(
            'DELETE FROM software_inventory WHERE computer_id = ?'
        )->execute([$computerId]);

        if (empty($softwareList)) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO software_inventory (computer_id, name, version, publisher, install_date)
             VALUES (:computer_id, :name, :version, :publisher, :install_date)'
        );

        foreach ($softwareList as $sw) {
            $stmt->execute([
                ':computer_id' => $computerId,
                ':name'        => substr((string)($sw['name']         ?? ''), 0, 255),
                ':version'     => substr((string)($sw['version']      ?? ''), 0, 128),
                ':publisher'   => substr((string)($sw['publisher']    ?? ''), 0, 255),
                ':install_date'=> !empty($sw['install_date']) ? $sw['install_date'] : null,
            ]);
        }
    }

    public function getByComputerId(int $computerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM software_inventory
             WHERE computer_id = ?
             ORDER BY name ASC'
        );
        $stmt->execute([$computerId]);
        return $stmt->fetchAll();
    }

    public function search(string $query, int $computerId = 0): array
    {
        $params = [':q' => '%' . $query . '%'];
        $extra  = '';

        if ($computerId > 0) {
            $extra          = ' AND computer_id = :cid';
            $params[':cid'] = $computerId;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM software_inventory
             WHERE (name LIKE :q OR publisher LIKE :q){$extra}
             ORDER BY name ASC
             LIMIT 200"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
