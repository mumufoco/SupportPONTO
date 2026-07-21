<?php

namespace App\Services\Settings\Catalog;

use App\Models\CboOccupationModel;
use CodeIgniter\Cache\CacheInterface;
use Config\Services;

/**
 * Catálogo somente leitura da CBO (Classificação Brasileira de Ocupações).
 * Dado de referência oficial carregado via CboOccupationSeeder -- não tem
 * create/update/delete/toggle porque não é gerido pelo admin nesta tela,
 * só consultado (listagem de referência + seletor de "CBO mais indicado"
 * no cadastro de Cargos).
 */
class CboOccupationCatalogService
{
    private const CACHE_KEY_ACTIVE = 'settings_catalog_cbo_occupations_active';

    public function __construct(
        private ?CboOccupationModel $model = null,
        private ?CacheInterface $cache = null,
    ) {
        $this->model ??= model(CboOccupationModel::class);
        $this->cache ??= Services::cache();
    }

    /** Lista completa de ocupações ativas, para o seletor buscável (client-side). */
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

    public function find(int $id): ?object
    {
        $occupation = $this->model->find($id);
        return $occupation ?: null;
    }

    /** Listagem paginada/buscável para a página de referência settings/cbo-occupations. */
    public function listing(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 25)));

        $query = $this->model->orderBy('title', 'ASC');

        if ($search !== '') {
            $query = $query->groupStart()
                ->like('code', $search)
                ->orLike('title', $search)
                ->groupEnd();
        }

        // countAllResults(false) não reseta o builder -- as mesmas condições
        // (busca) continuam valendo para o findAll() logo abaixo.
        $total = $query->countAllResults(false);
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pageCount);
        $offset = ($page - 1) * $perPage;

        $items = $query->findAll($perPage, $offset);

        return [
            'items' => $items,
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
}
