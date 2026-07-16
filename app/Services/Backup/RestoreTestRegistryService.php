<?php

namespace App\Services\Backup;

use App\Models\RestoreTestRecordModel;

/**
 * Registro de testes de restauracao (governanca), mesmo conceito do
 * RestoreTestRegistryService do SupportCHECK: um backup salvo no disco nao
 * prova que restaura de verdade -- so um teste de restauracao efetivamente
 * feito e registrado prova isso. Este servico so REGISTRA a confirmacao
 * manual de um admin (nao dispara `DatabaseBackupService::restoreBackup()`
 * automaticamente -- restaurar sobrescreve o banco de producao inteiro,
 * mesma cautela que o proprio SupportCHECK adota ao nao expor um botao de
 * "restaurar agora" na tela).
 */
class RestoreTestRegistryService
{
    public function __construct(private readonly ?RestoreTestRecordModel $model = null)
    {
    }

    private function model(): RestoreTestRecordModel
    {
        return $this->model ?? new RestoreTestRecordModel();
    }

    public function latest(): ?object
    {
        return $this->model()->latest();
    }

    public function record(?int $userId, string $status, ?string $notes = null): object
    {
        $id = $this->model()->insert([
            'tested_by' => $userId,
            'tested_at' => date('Y-m-d H:i:s'),
            'status' => $status,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->model()->find($id);
    }
}
