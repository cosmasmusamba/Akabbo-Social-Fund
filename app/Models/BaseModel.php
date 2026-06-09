<?php
namespace App\Models;

use Database;

/**
 * AKABBO SOCIAL FUND
 * Base Model
 *
 * Abstract base class providing common ORM-like database operations
 * for all models. Enforces prepared statements for SQL injection prevention.
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

    /**
     * Find a single record by primary key.
     *
     * @param int $id Record ID
     * @return array|null Record data or null if not found
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        if ($this->useSoftDelete) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Find a single record by a specific column value.
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = ?";
        if ($this->useSoftDelete) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        $sql .= " LIMIT 1";
        return $this->db->fetchOne($sql, [$value]);
    }

    /**
     * Retrieve all records with optional ordering.
     *
     * @param string $orderBy Column to sort by
     * @param string $dir     Sort direction (ASC|DESC)
     */
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

    /**
     * Paginate records.
     *
     * @param int    $page    Current page number (1-indexed)
     * @param int    $limit   Records per page
     * @param string $where   Optional WHERE clause (without the WHERE keyword)
     * @param array  $params  Bind parameters for the WHERE clause
     * @param string $orderBy Column to sort by
     * @param string $dir     Sort direction
     * @return array ['data' => [], 'total' => int, 'page' => int, 'last_page' => int]
     */
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

    /**
     * Insert a new record.
     *
     * @param array $data Associative array of column => value
     * @return int Last inserted ID
     */
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

    /**
     * Update an existing record.
     *
     * @param int   $id   Primary key value
     * @param array $data Columns to update
     * @return int Affected rows
     */
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
     * Soft delete a record (marks deleted_at timestamp).
     * Falls back to hard delete if soft deletes are not enabled.
     */
    public function delete(int $id): int
    {
        if ($this->useSoftDelete) {
            return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        return $this->db->execute(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
    }

    /**
     * Restore a soft-deleted record.
     */
    public function restore(int $id): int
    {
        if (!$this->useSoftDelete) {
            return 0;
        }
        return $this->db->execute(
            "UPDATE `{$this->table}` SET `deleted_at` = NULL, `updated_at` = ? WHERE `{$this->primaryKey}` = ?",
            [date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Count records matching optional conditions.
     */
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

    /**
     * Check if a record exists by column value.
     */
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

    /**
     * Run a raw query against this model's database connection.
     * Useful for complex joins or aggregations.
     */
    public function raw(string $sql, array $params = []): array
    {
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Run a raw query and fetch a single row.
     */
    public function rawOne(string $sql, array $params = []): ?array
    {
        return $this->db->fetchOne($sql, $params);
    }
}
