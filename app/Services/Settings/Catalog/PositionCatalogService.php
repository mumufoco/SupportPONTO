<?php

namespace App\Services\Settings\Catalog;

use App\Models\PositionModel;
use CodeIgniter\Cache\CacheInterface;
use Config\Services;

class PositionCatalogService
{
    private const CACHE_KEY = 'settings_catalog_positions_with_departments';

    public function __construct(
        private ?PositionModel $model = null,
        private ?CacheInterface $cache = null,
    ) {
        $this->model ??= model(PositionModel::class);
        $this->cache ??= Services::cache();
    }

    public function activeWithDepartments(): array
    {
        return $this->cachedAllWithDepartments();
    }

    public function listing(array $filters = []): array
    {
        $items = $this->cachedAllWithDepartments();

        $search = trim((string) ($filters['search'] ?? ''));
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 15)));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = array_values(array_filter($items, static function ($item) use ($needle): bool {
                $name = mb_strtolower((string) ($item['name'] ?? ''));
                $department = mb_strtolower((string) ($item['department_name'] ?? ''));
                $description = mb_strtolower((string) ($item['description'] ?? ''));
                return str_contains($name, $needle) || str_contains($department, $needle) || str_contains($description, $needle);
            }));
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $wantActive = $status === 'active';
            $items = array_values(array_filter($items, static fn ($item): bool => ($item['active'] === true || $item['active'] === 't') === $wantActive));
        }

        $total = count($items);
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pageCount);
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'page' => $page,
                'per_page' => $perPage,
            ],
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'page_count' => $pageCount,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    private function cachedAllWithDepartments(): array
    {
        $cache = $this->cache;
        $cached = $cache->get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $items = $this->model
            ->select('positions.*, departments.name as department_name')
            ->join('departments', 'departments.id = positions.department_id', 'left')
            ->orderBy('departments.name', 'ASC')
            ->orderBy('positions.name', 'ASC')
            ->findAll();
        $cache->save(self::CACHE_KEY, $items, 300);
        return $items;
    }

    private function bustCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    public function find(int $id): ?array
    {
        $position = $this->model->find($id);
        return $position ?: null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|min_length[2]|max_length[255]',
            'description' => 'permit_empty|max_length[1000]',
            'department_id' => 'required|integer|is_not_unique[departments.id]',
        ];
    }

    public function create(array $input): array
    {
        $saved = $this->model->insert([
            'name' => $input['name'] ?? null,
            'description' => $input['description'] ?? null,
            'department_id' => (int) ($input['department_id'] ?? 0),
            'active' => 'true',
        ]);
        if ($saved !== false) {
            $this->bustCache();
        }

        return [
            'success' => $saved !== false,
            'errors' => $this->model->errors(),
        ];
    }

    public function update(int $id, array $input): array
    {
        $updated = $this->model->update($id, [
            'name' => $input['name'] ?? null,
            'description' => $input['description'] ?? null,
            'department_id' => (int) ($input['department_id'] ?? 0),
        ]);
        if ($updated) {
            $this->bustCache();
        }

        return [
            'success' => $updated,
            'errors' => $this->model->errors(),
        ];
    }

    public function toggle(int $id): ?bool
    {
        $position = $this->find($id);
        if (!$position) {
            return null;
        }

        $active = !($position['active'] === true || $position['active'] === 't');
        $this->model->update($id, ['active' => $active ? 'true' : 'false']);
        $this->bustCache();

        return $active;
    }
}
