<?php

namespace App\Models\Concerns;

trait SettingModelSupportTrait
{
    protected function findByKey(string $key)
    {
        // Preferir 'key' (coluna canônica NOT NULL) sobre 'setting_key'
        $keyColumn = $this->resolveColumn(['key', 'setting_key']);
        if ($keyColumn === null) {
            return null;
        }

        return $this->builder()->where($keyColumn, $key)->get()->getFirstRow();
    }
    protected function normalizePayload(string $key, $value, string $type, string $group, bool $encrypt): array
    {
        $now = date('Y-m-d H:i:s');
        $valueColumn = $this->resolveColumn(['value', 'setting_value']) ?? 'value';
        // Preferir 'key' sobre 'setting_key' para garantir que a coluna NOT NULL seja sempre preenchida
        $keyColumn = $this->resolveColumn(['key', 'setting_key']) ?? 'key';
        $typeColumn = $this->resolveColumn(['type', 'setting_type']);
        $groupColumn = $this->resolveColumn(['group', 'setting_group']);
        $payload = [
            $keyColumn => $key,
            $valueColumn => $this->prepareStoredValue($value, $encrypt),
            'updated_at' => $now,
        ];

        // Se o modelo tem AMBAS as colunas key e setting_key, preencher as duas
        // para satisfazer constraints NOT NULL em qualquer configuração de schema.
        if ($keyColumn === 'key' && $this->columnExists('setting_key')) {
            $payload['setting_key'] = $key;
        }
        if ($keyColumn === 'setting_key' && $this->columnExists('key')) {
            $payload['key'] = $key;
        }

        if ($typeColumn !== null) {
            $payload[$typeColumn] = $type;
        }

        if ($groupColumn !== null) {
            $payload[$groupColumn] = $group;
        }

        if ($this->columnExists('is_encrypted')) {
            $payload['is_encrypted'] = $encrypt;
        }

        // Always set created_at; on UPDATE it is stripped in setSetting().
        $payload['created_at'] = $now;

        // A coluna `class` existe no schema do PostgreSQL com NOT NULL e sem default.
        // Usamos o `group` como valor, garantindo que o INSERT nunca viole a constraint.
        if ($this->columnExists('class')) {
            $payload['class'] = $group;
        }

        return $payload;
    }
    protected function prepareStoredValue($value, bool $encrypt)
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($value !== null) {
            $value = (string) $value;
        }

        if ($encrypt && $value !== null) {
            $encrypter = \Config\Services::encrypter();

            return base64_encode($encrypter->encrypt((string) $value));
        }

        return $value;
    }
    protected function decryptValue(string $value): string
    {
        try {
            $encrypter = \Config\Services::encrypter();

            return (string) $encrypter->decrypt(base64_decode($value, true) ?: $value);
        } catch (\Throwable $e) {
            log_message('error', 'SettingModel::decryptValue error: ' . $e->getMessage());

            return $value;
        }
    }
    protected function isEncryptedRow($row): bool
    {
        $v = is_array($row) ? ($row['is_encrypted'] ?? false) : ($row->is_encrypted ?? false);
        // PostgreSQL returns 't'/'f' strings for boolean columns — never cast 'f' to true.
        if ($v === 'f' || $v === '0' || $v === false || $v === null || $v === 0) {
            return false;
        }
        return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
    }
    protected function extractKey($row): ?string
    {
        return is_array($row)
            ? ($row['setting_key'] ?? $row['key'] ?? null)
            : ($row->setting_key ?? $row->key ?? null);
    }
    protected function extractValue($row)
    {
        return is_array($row)
            ? ($row['value'] ?? $row['setting_value'] ?? null)
            : ($row->value ?? $row->setting_value ?? null);
    }
    protected function extractType($row): string
    {
        return (string) (is_array($row)
            ? ($row['type'] ?? $row['setting_type'] ?? 'string')
            : ($row->type ?? $row->setting_type ?? 'string'));
    }
    protected function extractGroup($row): ?string
    {
        return is_array($row)
            ? ($row['group'] ?? $row['setting_group'] ?? null)
            : ($row->group ?? $row->setting_group ?? null);
    }
    protected function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }
    protected function detectType($value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value), is_object($value) => 'json',
            default => 'string',
        };
    }
    protected function resolveColumn(array $candidates): ?string
    {
        $columns = $this->getColumns();

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }
    protected function columnExists(string $column): bool
    {
        return in_array($column, $this->getColumns(), true);
    }
    protected function getColumns(): array
    {
        if ($this->columnsCache === null) {
            $this->columnsCache = $this->db->getFieldNames($this->table);
        }

        return $this->columnsCache;
    }

}
