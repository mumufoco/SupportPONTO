<?php

namespace App\Services\Settings\Catalog;

use App\Models\RoleModel;
use CodeIgniter\Cache\CacheInterface;
use Config\Services;

class RoleCatalogService
{
    private const CACHE_KEY = 'settings_catalog_roles_all';

    public function __construct(
        private ?RoleModel $model = null,
        private ?CacheInterface $cache = null,
    ) {
        $this->model ??= model(RoleModel::class);
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

        $total = count($items);
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pageCount);
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'filters' => [
                'search' => $search,
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
    }

    public function find(int $id): ?object
    {
        $role = $this->model->find($id);
        return $role ?: null;
    }

    public function createRules(): array
    {
        return [
            'name' => 'required|min_length[2]|max_length[255]|is_unique[roles.name]',
            'description' => 'permit_empty|max_length[1000]',
        ];
    }

    public function updateRules(int $id): array
    {
        return [
            'name' => "required|min_length[2]|max_length[255]|is_unique[roles.name,id,{$id}]",
            'description' => 'permit_empty|max_length[1000]',
        ];
    }

    public function create(array $input, array $permissions): void
    {
        $this->model->insert([
            'name' => $input['name'] ?? null,
            'description' => $input['description'] ?? null,
            'permissions' => json_encode($permissions),
            'active' => 'true',
        ]);
        $this->bustCache();
    }

    public function update(int $id, array $input, array $permissions): void
    {
        $this->model->update($id, [
            'name' => $input['name'] ?? null,
            'description' => $input['description'] ?? null,
            'permissions' => json_encode($permissions),
        ]);
        $this->bustCache();
    }


    /**
     * Qualquer nivel de acesso pode ser excluido -- nao ha distincao de
     * 'role nativo/protegido'. So e bloqueado se houver colaboradores
     * ativos vinculados (integridade referencial), nunca pelo nome do role.
     */
    public function delete(int $id): array
    {
        $role = $this->find($id);
        if (!$role) {
            return ['success' => false, 'message' => 'Nível de acesso não encontrado.'];
        }

        // Verificar se há colaboradores usando este role
        try {
            $db = \Config\Database::connect();
            $count = $db->table('employees')
                ->where('role_id', $id)
                ->where('active', true)
                ->countAllResults();

            if ($count > 0) {
                return [
                    'success' => false,
                    'message' => "Não é possível excluir: {$count} colaborador(es) ativo(s) usa(m) este nível. Reatribua-os primeiro.",
                ];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao verificar uso do nível: ' . $e->getMessage()];
        }

        $this->model->delete($id);
        $this->bustCache();

        return ['success' => true, 'message' => 'Nível de acesso excluído com sucesso.'];
    }

    public function toggle(int $id): ?bool
    {
        $role = $this->find($id);
        if (!$role) {
            return null;
        }

        // BUG CORRIGIDO: a versao anterior fazia "return $active;" com $active
        // sendo a STRING 'true'/'false' usada pra gravar no banco -- como o retorno
        // e tipado ?bool, PHP coagia qualquer string nao-vazia (inclusive 'false')
        // pra true, entao o toggle sempre respondia active=true no JSON pro
        // front-end, mesmo quando o banco realmente virava false. Mesmo padrao
        // ja usado (correto) em DepartmentCatalogService/PositionCatalogService/
        // WorkUnitCatalogService: bool real primeiro, string derivada dele depois.
        $newActive = !($role->active === true || $role->active === 't');
        $this->model->update($id, ['active' => $newActive ? 'true' : 'false']);
        $this->bustCache();

        return $newActive;
    }
}
