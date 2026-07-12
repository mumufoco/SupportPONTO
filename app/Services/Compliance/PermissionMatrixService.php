<?php

namespace App\Services\Compliance;

class PermissionMatrixService
{
    public function getRows(): array
    {
        return [
            ['module' => 'Colaboradores', 'admin' => 'Total', 'gestor' => 'Gerencial', 'funcionario' => 'Leitura limitada'],
            ['module' => 'Ponto', 'admin' => 'Total', 'gestor' => 'Equipe', 'funcionario' => 'Próprio'],
            ['module' => 'Advertências', 'admin' => 'Total', 'gestor' => 'Equipe', 'funcionario' => 'Somente ciência'],
            ['module' => 'Configurações', 'admin' => 'Total', 'gestor' => 'Restrito', 'funcionario' => 'Sem acesso'],
            ['module' => 'Analytics', 'admin' => 'Total', 'gestor' => 'Gerencial', 'funcionario' => 'Sem acesso'],
            ['module' => 'Compliance', 'admin' => 'Total', 'gestor' => 'Sem acesso', 'funcionario' => 'Sem acesso'],
        ];
    }
}
