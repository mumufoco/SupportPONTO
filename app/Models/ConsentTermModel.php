<?php

namespace App\Models;

use CodeIgniter\Model;

class ConsentTermModel extends Model
{
    protected $table            = 'consent_terms';
    protected $primaryKey       = 'id';
    protected $returnType       = 'object';
    protected $allowedFields    = ['type', 'version', 'title', 'body', 'legal_basis', 'active', 'created_by'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    public function getActiveTerm(string $type): ?object
    {
        return $this->where('type', $type)
                    ->where('active', true)
                    ->orderBy('version', 'DESC')
                    ->first();
    }

    public function getTermById(int $id): ?object
    {
        return $this->find($id);
    }

    public function getAllVersions(string $type): array
    {
        return $this->where('type', $type)
                    ->orderBy('version', 'DESC')
                    ->findAll();
    }

    public function deactivateAll(string $type): void
    {
        $this->where('type', $type)->set(['active' => false])->update();
    }

    public function nextVersion(string $type): string
    {
        $latest = $this->where('type', $type)->orderBy('version', 'DESC')->first();
        if (!$latest) return '1.0';
        [$major, $minor] = explode('.', $latest->version . '.0');
        return $major . '.' . ((int)$minor + 1);
    }
}
