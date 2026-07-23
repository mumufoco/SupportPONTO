<?php

namespace App\Services\Settings\Catalog;

use App\Models\ContractTypeModel;
use CodeIgniter\Cache\CacheInterface;
use Config\Services;

class ContractTypeCatalogService
{
    private const CACHE_KEY_ALL = 'settings_catalog_contract_types_all';
    private const CACHE_KEY_ACTIVE = 'settings_catalog_contract_types_active';

    public function __construct(
        private ?ContractTypeModel $model = null,
        private ?CacheInterface $cache = null,
    ) {
        $this->model ??= model(ContractTypeModel::class);
        $this->cache ??= Services::cache();
    }

    public function all(): array
    {
        return $this->cachedAll();
    }

    public function active(): array
    {
        $cache = $this->cache;
        $cached = $cache->get(self::CACHE_KEY_ACTIVE);
        if (is_array($cached)) {
            return $cached;
        }

        $items = $this->model->getActive();
        $cache->save(self::CACHE_KEY_ACTIVE, $items, 300);
        return $items;
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
        $cached = $cache->get(self::CACHE_KEY_ALL);
        if (is_array($cached)) {
            return $cached;
        }

        $items = $this->model->orderBy('name', 'ASC')->findAll();
        $cache->save(self::CACHE_KEY_ALL, $items, 300);
        return $items;
    }

    private function bustCache(): void
    {
        $cache = $this->cache;
        $cache->delete(self::CACHE_KEY_ALL);
        $cache->delete(self::CACHE_KEY_ACTIVE);
    }

    public function find(int $id): ?object
    {
        $contractType = $this->model->find($id);
        return $contractType ?: null;
    }

    public function createRules(): array
    {
        return [
            'name' => 'required|min_length[2]|max_length[255]|is_unique[contract_types.name]',
            'description' => 'permit_empty|max_length[1000]',
        ];
    }

    public function updateRules(int $id): array
    {
        return [
            'name' => "required|min_length[2]|max_length[255]|is_unique[contract_types.name,id,{$id}]",
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
        $contractType = $this->find($id);
        if (!$contractType) {
            return null;
        }

        $newActive = !($contractType->active === true || $contractType->active === 't');
        $this->model->update($id, ['active' => $newActive ? 'true' : 'false']);
        $this->bustCache();

        return $newActive;
    }

    public function delete(int $id): array
    {
        $contractType = $this->find($id);
        if (!$contractType) {
            return ['success' => false, 'message' => 'Tipo de contrato não encontrado.'];
        }

        try {
            $db = \Config\Database::connect();

            // employees.tipo_contrato guarda o NOME diretamente (sem FK, mesmo
            // padrão legado de department/position antes dos catálogos com id) --
            // a checagem de uso compara pelo nome do tipo, não por id.
            $employeeCount = $db->table('employees')
                ->where('tipo_contrato', $contractType->name)
                ->where('active', true)
                ->where('role !=', 'admin')
                ->countAllResults();

            if ($employeeCount > 0) {
                return [
                    'success' => false,
                    'message' => "Não é possível excluir: {$employeeCount} colaborador(es) ativo(s) usa(m) este tipo de contrato. Reatribua-os primeiro.",
                ];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao verificar uso do tipo de contrato: ' . $e->getMessage()];
        }

        $this->model->delete($id);
        $this->bustCache();

        return ['success' => true, 'message' => 'Tipo de contrato excluído com sucesso.'];
    }
}
