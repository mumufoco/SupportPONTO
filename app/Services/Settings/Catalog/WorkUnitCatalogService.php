<?php

namespace App\Services\Settings\Catalog;

use App\Models\WorkUnitModel;
use CodeIgniter\Cache\CacheInterface;
use Config\Services;

class WorkUnitCatalogService
{
    private const CACHE_KEY = 'settings_catalog_work_units_all';

    public function __construct(
        private ?WorkUnitModel $model = null,
        private ?CacheInterface $cache = null,
    ) {
        $this->model ??= model(WorkUnitModel::class);
        $this->cache ??= Services::cache();
    }

    public function all(): array
    {
        return $this->cachedAll();
    }

    public function listing(array $filters = []): array
    {
        $items = $this->cachedAll();

        $search = trim((string) ($filters['search'] ?? ''));
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 15)));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = array_values(array_filter($items, static function ($item) use ($needle): bool {
                $name = mb_strtolower((string) ($item->name ?? ''));
                $description = mb_strtolower((string) ($item->description ?? ''));
                return str_contains($name, $needle) || str_contains($description, $needle);
            }));
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $wantActive = $status === 'active';
            $items = array_values(array_filter($items, static fn ($item): bool => ($item->active === true || $item->active === 't') === $wantActive));
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

    private function cachedAll(): array
    {
        $cache = $this->cache;
        $cached = $cache->get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $items = $this->model->orderBy('name', 'ASC')->findAll();
        $cache->save(self::CACHE_KEY, $items, 300);
        return $items;
    }

    private function bustCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);

        // config_options e' o cache usado pelo cadastro/convite de colaborador
        // (SettingModel::loadSelectOptions(), TTL de 1h) -- sem isso, criar/editar/
        // desativar/excluir uma unidade aqui não refletia no formulário de
        // colaboradores por até 1 hora (unidades excluídas continuavam aparecendo).
        $this->cache->delete('config_options');
    }

    public function find(int $id): ?object
    {
        $workUnit = $this->model->find($id);
        return $workUnit ?: null;
    }

    public function createRules(): array
    {
        return [
            'name' => 'required|min_length[2]|max_length[255]|is_unique[work_units.name]',
            'description' => 'permit_empty|max_length[1000]',
        ];
    }

    public function updateRules(int $id): array
    {
        return [
            'name' => "required|min_length[2]|max_length[255]|is_unique[work_units.name,id,{$id}]",
            'description' => 'permit_empty|max_length[1000]',
        ];
    }

    public function create(array $input): void
    {
        $this->model->insert([
            'name' => $input['name'] ?? null,
            'description' => $input['description'] ?? null,
            'active' => 'true',
        ]);
        $this->bustCache();
    }

    public function update(int $id, array $input): void
    {
        $this->model->update($id, [
            'name' => $input['name'] ?? null,
            'description' => $input['description'] ?? null,
        ]);
        $this->bustCache();
    }

    public function toggle(int $id): ?bool
    {
        $workUnit = $this->find($id);
        if (!$workUnit) {
            return null;
        }

        $newActive = !($workUnit->active === true || $workUnit->active === 't');
        $this->model->update($id, ['active' => $newActive ? 'true' : 'false']);
        $this->bustCache();

        return $newActive;
    }

    public function delete(int $id): array
    {
        $workUnit = $this->find($id);
        if (!$workUnit) {
            return ['success' => false, 'message' => 'Unidade de trabalho não encontrada.'];
        }

        try {
            $db = \Config\Database::connect();
            $count = $db->table('employees')
                ->where('work_unit_id', $id)
                ->where('active', true)
                ->countAllResults();

            if ($count > 0) {
                return [
                    'success' => false,
                    'message' => "Não é possível excluir: {$count} colaborador(es) ativo(s) usa(m) esta unidade. Reatribua-os primeiro.",
                ];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao verificar uso da unidade: ' . $e->getMessage()];
        }

        $this->model->delete($id);
        $this->bustCache();

        return ['success' => true, 'message' => 'Unidade de trabalho excluída com sucesso.'];
    }
}
