<?php

namespace App\Services\Employees\Management;

use App\Models\EmployeeModel;

class EmployeePayloadBuilder
{
    public function __construct(private readonly EmployeeModel $employeeModel)
    {
    }

    public function build(array $postData, bool $isCreate): array
    {
        $phone = $postData['telefone'] ?? $postData['phone'] ?? null;
        $pisPasep = $postData['pis_pasep'] ?? $postData['pis'] ?? null;
        $uniqueCode = $postData['unique_code'] ?? $postData['employee_code'] ?? null;

        $payload = [
            'name' => $this->cleanText($postData['name'] ?? null),
            'birth_date' => $this->emptyToNull($postData['birth_date'] ?? null),
            'cpf' => $this->digits($postData['cpf'] ?? null),
            'rg' => $this->cleanText($postData['rg'] ?? null),
            'rg_orgao_emissor' => $this->cleanText($postData['rg_orgao_emissor'] ?? null),
            'rg_data_expedicao' => $this->emptyToNull($postData['rg_data_expedicao'] ?? null),
            'estado_civil' => $this->emptyToNull($postData['estado_civil'] ?? null),
            'nacionalidade' => $this->cleanText($postData['nacionalidade'] ?? null),
            'sexo' => $this->emptyToNull($postData['sexo'] ?? null),
            'cor_raca' => $this->emptyToNull($postData['cor_raca'] ?? null),
            'grau_instrucao' => $this->emptyToNull($postData['grau_instrucao'] ?? null),
            'logradouro' => $this->cleanText($postData['logradouro'] ?? null),
            'numero' => $this->cleanText($postData['numero'] ?? null),
            'complemento' => $this->cleanText($postData['complemento'] ?? null),
            'bairro' => $this->cleanText($postData['bairro'] ?? null),
            'municipio' => $this->cleanText($postData['municipio'] ?? null),
            'uf' => $this->upper($postData['uf'] ?? null),
            'cep' => $this->digits($postData['cep'] ?? null),
            'telefone' => $phone,
            'phone' => $phone,
            'email' => mb_strtolower(trim((string) ($postData['email'] ?? ''))),
            'ctps_numero' => $this->cleanText($postData['ctps_numero'] ?? null),
            'ctps_serie' => $this->cleanText($postData['ctps_serie'] ?? null),
            'ctps_uf' => $this->upper($postData['ctps_uf'] ?? null),
            'ctps_data_emissao' => $this->emptyToNull($postData['ctps_data_emissao'] ?? null),
            'pis' => $this->digits($pisPasep),
            'pis_pasep' => $this->digits($pisPasep),
            'admission_date' => $this->emptyToNull($postData['admission_date'] ?? null),
            'cargo' => $this->cleanText($postData['cargo'] ?? $postData['position'] ?? null),
            'salario_base' => $this->normalizeDecimal($postData['salario_base'] ?? null),
            'jornada_trabalho' => $this->cleanText($postData['jornada_trabalho'] ?? null),
            'tipo_contrato' => $this->emptyToNull($postData['tipo_contrato'] ?? null),
            'setor' => $this->cleanText($postData['setor'] ?? $postData['department'] ?? null),
            'horario_entrada' => $this->normalizeTime($postData['horario_entrada'] ?? null),
            'horario_saida' => $this->normalizeTime($postData['horario_saida'] ?? null),
            'dependentes' => json_encode($this->buildDependentes($postData), JSON_UNESCAPED_UNICODE),
            'banco' => $this->cleanText($postData['banco'] ?? null),
            'agencia' => $this->cleanText($postData['agencia'] ?? null),
            'conta'        => $this->cleanText($postData['conta'] ?? null),
            'pix_key_type' => $this->emptyToNull($postData['pix_key_type'] ?? null),
            'pix_key'      => $this->cleanText($postData['pix_key'] ?? null),
            'deficiencia' => $this->emptyToNull($postData['deficiencia'] ?? null),
            'demission_date' => $this->emptyToNull($postData['demission_date'] ?? null),
            'role' => $postData['role'] ?? 'funcionario',
            'role_id' => $this->nullableInt($postData['role_id'] ?? null),
            'work_unit_id' => $this->nullableInt($postData['work_unit_id'] ?? null),
            'department_id' => $this->nullableInt($postData['department_id'] ?? null),
            'position_id' => $this->nullableInt($postData['position_id'] ?? null),
            'work_unit' => $this->cleanText($postData['work_unit'] ?? null),
            'department' => $this->cleanText($postData['department'] ?? null),
            'position' => $this->cleanText($postData['position'] ?? null),
            'work_shift_id' => $this->nullableInt($postData['work_shift_id'] ?? null),
            'expected_hours_daily' => $this->normalizeDecimal($postData['expected_hours_daily'] ?? $postData['daily_hours'] ?? '8.00'),
            'daily_hours' => $this->normalizeDecimal($postData['daily_hours'] ?? $postData['expected_hours_daily'] ?? '8.00'),
            'weekly_hours' => $this->normalizeDecimal($postData['weekly_hours'] ?? '44.00'),
            'work_schedule_start' => $this->normalizeTime($postData['work_schedule_start'] ?? $postData['horario_entrada'] ?? null),
            'work_start_time' => $this->normalizeTime($postData['work_start_time'] ?? $postData['horario_entrada'] ?? null),
            'work_schedule_end' => $this->normalizeTime($postData['work_schedule_end'] ?? $postData['horario_saida'] ?? null),
            'work_end_time' => $this->normalizeTime($postData['work_end_time'] ?? $postData['horario_saida'] ?? null),
            'lunch_start_time' => $this->normalizeTime($postData['lunch_start_time'] ?? null),
            'lunch_end_time' => $this->normalizeTime($postData['lunch_end_time'] ?? null),
            'allow_remote_punch' => $this->truthy($postData['allow_remote_punch'] ?? false),
            'require_geolocation' => $this->truthy($postData['require_geolocation'] ?? false),
            'active' => $isCreate ? $this->truthy($postData['active'] ?? true) : $this->truthy($postData['active'] ?? false),
        ];

        if ($isCreate) {
            $payload['password'] = password_hash((string) ($postData['password'] ?? ''), PASSWORD_ARGON2ID);
            $payload['unique_code'] = $uniqueCode !== null && trim((string) $uniqueCode) !== ''
                ? trim((string) $uniqueCode)
                : $this->generateUniqueCode();
        } elseif ($uniqueCode !== null && trim((string) $uniqueCode) !== '') {
            $payload['unique_code'] = trim((string) $uniqueCode);
        }

        return array_filter($payload, static fn($value) => $value !== '');
    }

    private function buildDependentes(array $postData): array
    {
        $dependentes = [];
        $nomes = $postData['dependente_nome'] ?? null;
        if (!is_array($nomes)) {
            return $dependentes;
        }

        foreach ($nomes as $key => $nome) {
            $nome = trim((string) $nome);
            if ($nome === '') {
                continue;
            }

            $dependentes[] = [
                'nome' => $nome,
                'cpf' => $this->digits($postData['dependente_cpf'][$key] ?? null),
                'data_nascimento' => $this->emptyToNull($postData['dependente_data'][$key] ?? null),
                'parentesco' => $this->emptyToNull($postData['dependente_parentesco'][$key] ?? null),
            ];
        }

        return $dependentes;
    }

    private function generateUniqueCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        do {
            $code = '';
            for ($i = 0; $i < 9; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $exists = $this->employeeModel->where('unique_code', $code)->first();
        } while ($exists);

        return $code;
    }

    private function cleanText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function emptyToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function digits(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($value ?? '')) ?: '';
        return $digits === '' ? null : $digits;
    }

    private function upper(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_strtoupper($value);
    }

    private function normalizeDecimal(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return str_replace(',', '.', $value);
    }

    private function normalizeTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value . ':00' : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes', 'sim'], true);
    }
}
