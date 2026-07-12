<?php

namespace App\Validation;

use App\Enums\PunchType;
use App\Enums\Role;
use ValueError;

/**
 * Custom Validation Rules
 *
 * Regras de validação customizadas para o Sistema de Ponto Eletrônico
 */
class CustomRules
{
    /**
     * Valida tipo de ponto usando o contrato canônico do sistema.
     *
     * Mantém compatibilidade transitória com aliases legados ainda aceitos
     * pelo resolver de endpoints, mas a interface pública do sistema deve
     * sempre usar os valores canônicos do enum PunchType.
     */
    public function valid_punch_type(string $value, ?string $params = null, array $data = []): bool
    {
        $normalized = strtolower(trim($value));

        $aliases = [
            'in' => PunchType::Entrada->value,
            'out' => PunchType::Saida->value,
            'inicio_intervalo' => PunchType::IntervaloInicio->value,
            'saida_intervalo' => PunchType::IntervaloInicio->value,
            'almoco_saida' => PunchType::IntervaloInicio->value,
            'intervalo-inicio' => PunchType::IntervaloInicio->value,
            'break_start' => PunchType::IntervaloInicio->value,
            'fim_intervalo' => PunchType::IntervaloFim->value,
            'volta_intervalo' => PunchType::IntervaloFim->value,
            'almoco_retorno' => PunchType::IntervaloFim->value,
            'intervalo-fim' => PunchType::IntervaloFim->value,
            'break_end' => PunchType::IntervaloFim->value,
        ];

        $canonical = $aliases[$normalized] ?? $normalized;

        try {
            PunchType::from($canonical);
            return true;
        } catch (ValueError) {
            return false;
        }
    }

    /**
     * Valida o role do colaborador usando o enum canônico do sistema.
     *
     * Mantém compatibilidade transitória com aliases históricos enquanto
     * o projeto conclui a limpeza dos pontos legados.
     */
    public function valid_employee_role(string $value, ?string $params = null, array $data = []): bool
    {
        try {
            Role::normalize($value);
            return true;
        } catch (\ValueError) {
            // Not a built-in role — check custom DB roles
        }
        try {
            $db = \Config\Database::connect();
            return (bool) $db->table('roles')->where('name', $value)->where('active', true)->countAllResults();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Valida latitude
     */
    public function valid_latitude(string $value, ?string $params = null, array $data = []): bool
    {
        if (empty($value)) {
            return true; // permit_empty handle this
        }

        $lat = floatval($value);
        return $lat >= -90 && $lat <= 90;
    }

    /**
     * Valida longitude
     */
    public function valid_longitude(string $value, ?string $params = null, array $data = []): bool
    {
        if (empty($value)) {
            return true; // permit_empty handle this
        }

        $lng = floatval($value);
        return $lng >= -180 && $lng <= 180;
    }

    /**
     * Valida imagem base64
     */
    public function valid_base64_image(string $value, ?string $params = null, array $data = []): bool
    {
        if (empty($value)) {
            return true; // permit_empty handle this
        }

        // Remove data URI scheme if present
        if (strpos($value, 'data:image') === 0) {
            $value = substr($value, strpos($value, ',') + 1);
        }

        // Decode base64
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        // Check if it's a valid image
        $imageInfo = @getimagesizefromstring($decoded);

        return $imageInfo !== false;
    }

    /**
     * Valida tamanho máximo de arquivo (em bytes)
     */
    public function max_file_size(string $value, ?string $params = null, array $data = []): bool
    {
        if (empty($value) || empty($params)) {
            return true;
        }

        // Remove data URI scheme if present
        if (strpos($value, 'data:image') === 0) {
            $value = substr($value, strpos($value, ',') + 1);
        }

        // Calculate base64 decoded size
        $decodedSize = (strlen($value) * 3) / 4;

        // Account for padding
        $paddingCount = substr_count(substr($value, -2), '=');
        $decodedSize -= $paddingCount;

        return $decodedSize <= (int)$params;
    }

    /**
     * Valida senha forte
     */
    public function strong_password(string $value, ?string $params = null, array $data = []): bool
    {
        $minimumLength = (int) ($params ?: \App\Support\InitialAdminPolicy::MIN_PASSWORD_LENGTH);
        if (strlen($value) < $minimumLength) {
            return false;
        }

        if (!preg_match('/[A-Z]/', $value)) {
            return false;
        }

        if (!preg_match('/[a-z]/', $value)) {
            return false;
        }

        if (!preg_match('/[0-9]/', $value)) {
            return false;
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Valida CPF brasileiro
     */
    public function valid_cpf(string $value, ?string $params = null, array $data = []): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $value);

        if (strlen($cpf) != 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$cpf[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;

        if ((int)$cpf[9] != $digit1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int)$cpf[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;

        return (int)$cpf[10] == $digit2;
    }

    /**
     * Verifica unicidade de CPF (MED-11 na auditoria).
     *
     * employees.cpf agora guarda o valor criptografado (nonce aleatório, nunca repete
     * o mesmo texto cifrado mesmo para o mesmo CPF) — a regra nativa
     * is_unique[employees.cpf] pararia de detectar duplicatas silenciosamente, pois
     * compara o CPF em claro enviado no formulário contra um valor cifrado que nunca
     * vai bater. Delega para EmployeeModel::isCpfUnique(), que compara por
     * employees.cpf_hash (determinístico).
     *
     * Uso: cpf_is_unique (criação) ou cpf_is_unique[{$id}] (edição, excluindo o
     * próprio registro), mesma convenção de is_unique[employees.cpf,id,{$id}].
     */
    public function cpf_is_unique(string $value, ?string $params = null, array $data = []): bool
    {
        $excludeId = ($params !== null && ctype_digit($params)) ? (int) $params : null;

        return (new \App\Models\EmployeeModel())->isCpfUnique($value, $excludeId);
    }

    /**
     * Valida CNPJ brasileiro
     */
    public function valid_cnpj(string $value, ?string $params = null, array $data = []): bool
    {
        $cnpj = preg_replace('/[^0-9]/', '', $value);

        if (strlen($cnpj) != 14) {
            return false;
        }

        // Check for known invalid CNPJs (all same digits)
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        // Validate first check digit
        $sum = 0;
        $multiplier = 5;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$cnpj[$i] * $multiplier;
            $multiplier = ($multiplier == 2) ? 9 : $multiplier - 1;
        }
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;

        if ((int)$cnpj[12] != $digit1) {
            return false;
        }

        // Validate second check digit
        $sum = 0;
        $multiplier = 6;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int)$cnpj[$i] * $multiplier;
            $multiplier = ($multiplier == 2) ? 9 : $multiplier - 1;
        }
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;

        return (int)$cnpj[13] == $digit2;
    }

    /**
     * Valida telefone brasileiro
     */
    public function valid_phone_br(string $value, ?string $params = null, array $data = []): bool
    {
        if (empty($value)) {
            return true; // permit_empty handle this
        }

        $phone = preg_replace('/[^0-9]/', '', $value);

        // Valid lengths: 10 (landline) or 11 (mobile)
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            return false;
        }

        // Check if it starts with valid DDD (area code 11-99)
        $ddd = (int)substr($phone, 0, 2);
        if ($ddd < 11 || $ddd > 99) {
            return false;
        }

        // For 11 digits, 3rd digit must be 9 (mobile)
        if (strlen($phone) == 11) {
            if ($phone[2] != '9') {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida horário no formato HH:MM ou HH:MM:SS
     */
    public function valid_time(string $value, ?string $params = null, array $data = []): bool
    {
        if (empty($value)) {
            return true; // permit_empty handle this
        }

        // Match HH:MM or HH:MM:SS
        if (!preg_match('/^([0-1][0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Valida data no formato brasileiro (dd/mm/YYYY)
     */
    public function valid_date_br(string $value, ?string $params = null, array $data = []): bool
    {
        if (empty($value)) {
            return true; // permit_empty handle this
        }

        // Match dd/mm/YYYY
        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return false;
        }

        $day = (int)$matches[1];
        $month = (int)$matches[2];
        $year = (int)$matches[3];

        // Check if date is valid
        if (!checkdate($month, $day, $year)) {
            return false;
        }

        return true;
    }
}
