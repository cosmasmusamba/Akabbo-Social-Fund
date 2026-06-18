<?php
namespace App\Models;

use Database;

/**
 * AKABBO SOCIAL FUND
 * Base Model
 *
 * Abstract base class providing common ORM-like database operations
 * for all models. Enforces prepared statements for SQL injection prevention
 * and standardized Trash & Recovery handling.
 to be inherited by all domain models.
 */
abstract class BaseModel
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool $useSoftDelete = false;
    protected bool $useTimestamps = true;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        if ($this->useSoftDelete) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        return $this->db->fetchOne($sql, [$id]);
    }

    public function findBy(string $column, mixed $value): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = ?";
        if ($this->useSoftDelete) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        $sql .= " LIMIT 1";
        return $this->db->fetchOne($sql, [$value]);
    }

    public function all(string $orderBy = 'id', string $dir = 'ASC'): array
    {
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $sql = "SELECT * FROM `{$this->table}`";
        if ($this->useSoftDelete) {
            $sql .= " WHERE `deleted_at` IS NULL";
        }
        $sql .= " ORDER BY `{$orderBy}` {$dir}";
        return $this->db->fetchAll($sql);
    }

    public function paginate(
        int $page = 1,
        int $limit = DEFAULT_PAGE_SIZE,
        string $where = '',
        array $params = [],
        string $orderBy = 'id',
        string $dir = 'DESC'
    ): array {
        $dir    = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $limit  = min($limit, MAX_PAGE_SIZE);
        $offset = ($page - 1) * $limit;

        $whereSql = '';
        if ($this->useSoftDelete && $where) {
            $whereSql = " WHERE deleted_at IS NULL AND ({$where})";
        } elseif ($this->useSoftDelete) {
            $whereSql = " WHERE deleted_at IS NULL";
        } elseif ($where) {
            $whereSql = " WHERE {$where}";
        }

        $countSql = "SELECT COUNT(*) FROM `{$this->table}`{$whereSql}";
        $total    = (int) $this->db->fetchColumn($countSql, $params);

        $dataSql = "SELECT * FROM `{$this->table}`{$whereSql} ORDER BY `{$orderBy}` {$dir} LIMIT {$limit} OFFSET {$offset}";
        $data    = $this->db->fetchAll($dataSql, $params);

        return [
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $limit,
            'last_page' => (int) ceil($total / $limit),
            'from'      => $total > 0 ? $offset + 1 : 0,
            'to'        => min($offset + $limit, $total),
        ];
    }

    public function create(array $data): int
    {
        if ($this->useTimestamps) {
            $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $columns     = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `{$this->table}` (`{$columns}`) VALUES ({$placeholders})";
        return (int) $this->db->insert($sql, array_values($data));
    }

    public function update(int $id, array $data): int
    {
        if ($this->useTimestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $setClauses = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        $sql        = "UPDATE `{$this->table}` SET {$setClauses} WHERE `{$this->primaryKey}` = ?";

        return $this->db->execute($sql, [...array_values($data), $id]);
    }

    /**
     * Get the current record data (useful for audit snapshots before update/delete).
     */
    public function getOldValues(int $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Soft delete a record. Automatically inserts a snapshot into the `trash` table
     * for compliance and recovery evidence before marking as deleted.
     */
    public function delete(int $id, ?int $deletedBy = null): int
    {
        if ($this->useSoftDelete) {
            $oldData = $this->find($id);
            if ($oldData) {
                $this->db->execute(
                    "INSERT INTO trash (record_type, record_id, record_data, deleted_by, deleted_at) 
                     VALUES (?, ?, ?, ?, NOW())",
                    [$this->table, $id, json_encode($oldData), $deletedBy ?? ($_SESSION['user_id'] ?? null)]
                );
            }
            return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        
        return $this->db->execute(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
    }

    /**
     * Restore a soft-deleted record and update the trash record.
     */
    public function restore(int $id, ?int $restoredBy = null): int
    {
        if (!$this->useSoftDelete) {
            return 0;
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE `{$this->table}` SET `deleted_at` = NULL, `updated_at` = ? WHERE `{$this->primaryKey}` = ?",
                [date('Y-m-d H:i:s'), $id]
            );

            $this->db->execute(
                "UPDATE trash SET restored_at = NOW(), restored_by = ? WHERE record_type = ? AND record_id = ?",
                [$restoredBy ?? ($_SESSION['user_id'] ?? null), $this->table, $id]
            );

            $this->db->commit();
            return 1;
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("[BaseModel::restore] Failed for {$this->table} ID {$id}: " . $e->getMessage());
            return 0;
        }
    }

    public function count(string $where = '', array $params = []): int
    {
        $whereSql = '';
        if ($this->useSoftDelete && $where) {
            $whereSql = " WHERE deleted_at IS NULL AND ({$where})";
        } elseif ($this->useSoftDelete) {
            $whereSql = " WHERE deleted_at IS NULL";
        } elseif ($where) {
            $whereSql = " WHERE {$where}";
        }

        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}`{$whereSql}",
            $params
        );
    }

    public function exists(string $column, mixed $value, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM `{$this->table}` WHERE `{$column}` = ?";
        $params = [$value];

        if ($excludeId !== null) {
            $sql      .= " AND `{$this->primaryKey}` != ?";
            $params[] = $excludeId;
        }

        return (int) $this->db->fetchColumn($sql, $params) > 0;
    }

    public function raw(string $sql, array $params = []): array
    {
        return $this->db->fetchAll($sql, $params);
    }

    public function rawOne(string $sql, array $params = []): ?array
    {
        return $this->db->fetchOne($sql, $params);
    }
}