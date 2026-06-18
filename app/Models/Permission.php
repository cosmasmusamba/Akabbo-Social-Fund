<?php
namespace App\Models;

class Permission extends BaseModel
{
    protected string $table = 'permissions';

    public function getAllGroupedByModule(): array
    {
        $permissions = $this->db->fetchAll("
            SELECT id, module, action, slug, description, scope
            FROM permissions
            ORDER BY module, action
        ");

        $grouped = [];
        foreach ($permissions as $perm) {
            $grouped[$perm['module']][] = $perm;
        }

        return $grouped;
    }

    public function getAllModules(): array
    {
        return $this->db->fetchAll("
            SELECT DISTINCT module
            FROM permissions
            ORDER BY module
        ");
    }
}