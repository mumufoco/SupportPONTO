<?php

namespace App\Models;

use App\Models\Concerns\SettingModelRepositoryTrait;
use App\Models\Concerns\SettingModelCatalogTrait;
use App\Models\Concerns\SettingModelSupportTrait;
use CodeIgniter\Model;

class SettingModel extends Model
{
    use SettingModelRepositoryTrait;
    use SettingModelSupportTrait;
    use SettingModelCatalogTrait;
    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['setting_key', 'key', 'value', 'setting_value', 'type', 'setting_type', 'group', 'setting_group', 'description', 'editable', 'updated_at', 'created_at', 'is_encrypted'];
    protected $useTimestamps = false;

    private ?array $columnsCache = null;

    /**
     * Retorna o valor de uma configuração por chave.
     *
     * @param mixed $default
     * @return mixed
     */


    /**
     * Mantém compatibilidade com chamadas getSetting().
     *
     * @param mixed $default
     * @return mixed
     */


    /**
     * Retorna configurações por grupo.
     */


    /**
     * Retorna configurações do grupo em formato chave => valor.
     */


    /**
     * Retorna todas as configurações agrupadas.
     */


    /**
     * Define/atualiza uma configuração.
     */


    /**
     * Atualiza múltiplas configurações em transação.
     */


























    /**
     * Invalida todos os caches de configuração do sistema.
     *
     * Mantido público porque é chamado diretamente por controllers e serviços
     * após gravações de configuração (AppearanceSettingsService, SecuritySettingsService,
     * BaseSettingsController etc.). A granularidade por chave não é necessária aqui
     * porque alterações de configuração são operações raras e de baixo throughput.
     *
     * NOTA DE ARQUITETURA: Em refatoração futura, considerar evento de domínio
     * SettingsUpdated que dispare a limpeza de forma desacoplada.
     */










    // ── MELHORIA 5: Absorção do ConfigurationService ──────────────────────────
    // ConfigurationService consultava tabelas de catálogo (work_units, departments,
    // positions, roles) de forma duplicada e com cache separado. Consolidado aqui
    // para usar o mesmo ciclo de cache e eliminar a duplicação.

    /**
     * Carrega as opções de catálogo para formulários (com cache de 1 hora).
     *
     * Substitui ConfigurationService::loadOptions().
     * As tabelas consultadas são tabelas de catálogo do sistema, não `settings`.
     */


    /**
     * Valida que os IDs de catálogo fornecidos existem e estão ativos.
     *
     * Substitui ConfigurationService::validateBeforeSave().
     *
     * @return string[] Lista de erros (vazio = válido)
     */





}
