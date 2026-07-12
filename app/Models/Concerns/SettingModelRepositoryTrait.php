<?php

namespace App\Models\Concerns;

trait SettingModelRepositoryTrait
{
    public function get(string $key, $default = null)
    {
        try {
            if (! $this->db->tableExists($this->table)) {
                return $default;
            }

            $row = $this->findByKey($key);

            if (! $row) {
                return $default;
            }

            $value = $this->extractValue($row);
            if ($this->isEncryptedRow($row)) {
                $value = $this->decryptValue((string) $value);
            }

            return $this->castValue($value, $this->extractType($row));
        } catch (\Throwable $e) {
            log_message('error', 'SettingModel::get error: ' . $e->getMessage());

            return $default;
        }
    }
    public function getSetting(string $key, $default = null)
    {
        return $this->get($key, $default);
    }
    public function getByGroup(string $group): array
    {
        try {
            if (! $this->db->tableExists($this->table)) {
                return [];
            }

            $groupColumn = $this->resolveColumn(['group', 'setting_group']);
            if ($groupColumn === null) {
                return [];
            }

            return $this->builder()->where($groupColumn, $group)->get()->getResult();
        } catch (\Throwable $e) {
            log_message('error', 'SettingModel::getByGroup error: ' . $e->getMessage());

            return [];
        }
    }
    public function getByGroupMap(string $group): array
    {
        $rows = $this->getByGroup($group);
        $mapped = [];

        foreach ($rows as $row) {
            $key = $this->extractKey($row);
            if ($key === null) {
                continue;
            }

            $value = $this->extractValue($row);
            if ($this->isEncryptedRow($row)) {
                try {
                    $value = $this->decryptValue((string) $value);
                } catch (\Throwable $e) {
                    log_message('error', 'SettingModel::getByGroupMap decrypt error: ' . $e->getMessage());
                }
            }

            $mapped[$key] = $this->castValue($value, $this->extractType($row));
        }

        return $mapped;
    }
    public function getAllGrouped(): array
    {
        $rows = $this->builder()->get()->getResult();
        $grouped = [];

        foreach ($rows as $row) {
            $group = $this->extractGroup($row) ?? 'general';
            $key = $this->extractKey($row);
            if ($key === null) {
                continue;
            }

            $value = $this->extractValue($row);
            if ($this->isEncryptedRow($row)) {
                $value = $this->decryptValue((string) $value);
            }

            $grouped[$group][$key] = $this->castValue($value, $this->extractType($row));
        }

        return $grouped;
    }
    public function setSetting(
        string $key,
        $value,
        string $type = 'string',
        string $group = 'general',
        bool $encrypt = false
    ): bool {
        try {
            if (! $this->db->tableExists($this->table)) {
                log_message('warning', "SettingModel::setSetting called but table '{$this->table}' does not exist.");

                return false;
            }

            $row = $this->findByKey($key);
            $payload = $this->normalizePayload($key, $value, $type, $group, $encrypt);

            if ($row) {
                $id = is_array($row) ? ($row['id'] ?? null) : ($row->id ?? null);
                if ($id === null) {
                    return false;
                }

                // Never overwrite created_at on updates.
                $updatePayload = $payload;
                unset($updatePayload['created_at']);

                return (bool) $this->update($id, $updatePayload);
            }

            return (bool) $this->insert($payload);
        } catch (\Throwable $e) {
            log_message('error', 'SettingModel::setSetting error: ' . $e->getMessage());

            return false;
        }
    }
    public function setMultiple(array $data, string $group = 'general'): bool
    {
        $this->db->transStart();

        try {
            foreach ($data as $key => $value) {
                if (! is_string($key) || $key === '') {
                    continue;
                }

                $detectedType = $this->detectType($value);
                if (! $this->setSetting($key, $value, $detectedType, $group, false)) {
                    throw new \RuntimeException('Falha ao persistir configuração: ' . $key);
                }
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Falha na transação de configurações.');
            }

            $this->clearCache();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'SettingModel::setMultiple error: ' . $e->getMessage());

            return false;
        }
    }
    public function deleteGroup(string $group): bool
    {
        $groupColumn = $this->resolveColumn(['group', 'setting_group']);
        if ($groupColumn === null) {
            return false;
        }

        return (bool) $this->builder()->where($groupColumn, $group)->delete();
    }
    public function clearCache(): void
    {
        cache()->delete('settings');
        cache()->delete('design_system');
        cache()->delete('design_system_css');
        cache()->delete('config_options');
        cache()->delete('system_settings');
    }
    public function getForExport(): array
    {
        if (! $this->db->tableExists($this->table)) {
            return [];
        }

        return $this->builder()->orderBy($this->resolveColumn(['group', 'setting_group']) ?? 'id', 'ASC')
            ->orderBy($this->resolveColumn(['key', 'setting_key']) ?? 'id', 'ASC')
            ->get()
            ->getResultArray();
    }

}
