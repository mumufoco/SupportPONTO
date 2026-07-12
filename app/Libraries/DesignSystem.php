<?php

/**
 * Design System Configuration
 *
 * Centraliza todas as configurações de tema, cores, tipografia e estilos
 * Permite customização completa via painel de configurações
 */

namespace App\Libraries;

use App\Models\SettingModel;

class DesignSystem
{
    private $config = [];

    public function __construct()
    {
        // Carregar configurações salvas ou usar padrões
        $this->loadConfig();
    }

    /**
     * Carrega configurações do banco ou arquivo
     */
    private function loadConfig()
    {
        try {
            /** @var SettingModel $settings */
            $settings = model(SettingModel::class);
            $config = $settings->getSetting('design_system');

            if (is_string($config)) {
                $decoded = json_decode($config, true);
                if (is_array($decoded)) {
                    $this->config = $decoded;
                    return;
                }
            }

            if (is_array($config) && $config !== []) {
                $this->config = $config;
                return;
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Could not load design config from settings: ' . $e->getMessage());
        }

        // Usar configurações padrão
        $this->config = $this->getDefaultConfig();
    }

    /**
     * Configurações padrão do sistema
     */
    private function getDefaultConfig(): array
    {
        return [
            // Cores principais
            'colors' => [
                'primary' => '#3B82F6',      // Azul moderno
                'secondary' => '#8B5CF6',    // Roxo
                'success' => '#10B981',      // Verde
                'danger' => '#EF4444',       // Vermelho
                'warning' => '#F59E0B',      // Laranja
                'info' => '#06B6D4',         // Ciano
                'dark' => '#1F2937',         // Cinza escuro
                'light' => '#F3F4F6',        // Cinza claro
            ],

            // Cores do tema claro
            'light_theme' => [
                'background' => '#FFFFFF',
                'surface' => '#F9FAFB',
                'text_primary' => '#111827',
                'text_secondary' => '#6B7280',
                'border' => '#E5E7EB',
            ],

            // Cores do tema escuro
            'dark_theme' => [
                'background' => '#111827',
                'surface' => '#1F2937',
                'text_primary' => '#F9FAFB',
                'text_secondary' => '#9CA3AF',
                'border' => '#374151',
            ],

            // Tipografia
            'typography' => [
                'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
                'font_size_base' => '16px',
                'font_size_sm' => '14px',
                'font_size_lg' => '18px',
                'font_size_xl' => '20px',
                'line_height' => '1.5',
            ],

            // Espaçamento
            'spacing' => [
                'xs' => '4px',
                'sm' => '8px',
                'md' => '16px',
                'lg' => '24px',
                'xl' => '32px',
                'xxl' => '48px',
            ],

            // Bordas
            'borders' => [
                'radius_sm' => '4px',
                'radius_md' => '8px',
                'radius_lg' => '12px',
                'radius_xl' => '16px',
                'radius_full' => '9999px',
            ],

            // Sombras
            'shadows' => [
                'sm' => '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                'md' => '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                'lg' => '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
                'xl' => '0 20px 25px -5px rgba(0, 0, 0, 0.1)',
            ],

            // Layout
            'layout' => [
                'sidebar_width' => '280px',
                'sidebar_collapsed_width' => '80px',
                'header_height' => '64px',
                'content_max_width' => '1400px',
            ],

            // Customizações
            'custom' => [
                'logo' => null,
                'favicon' => null,
                'login_background' => null,
                'company_name' => 'Sistema de Ponto Eletrônico',
                'theme_mode' => 'light', // 'light', 'dark', 'auto'
            ],
        ];
    }

    /**
     * Retorna todas as configurações
     */
    public function getAll(): array
    {
        return $this->config;
    }

    /**
     * Retorna uma configuração específica
     */
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Atualiza configurações
     */
    public function update(array $newConfig): bool
    {
        $this->config = array_merge($this->config, $newConfig);
        return $this->save();
    }

    /**
     * Atualiza cores do sistema
     */
    public function updateColors(array $colors): bool
    {
        $this->ensureConfigStructure();
        $defaults = $this->getDefaultConfig();
        
        foreach ($colors as $key => $value) {
            if ($value !== null && $value !== '' && isset($defaults['colors'][$key])) {
                $this->config['colors'][$key] = $value;
            }
        }
        return $this->save();
    }

    /**
     * Atualiza tipografia do sistema
     */
    public function updateTypography(array $typography): bool
    {
        $this->ensureConfigStructure();
        $defaults = $this->getDefaultConfig();
        
        foreach ($typography as $key => $value) {
            if ($value !== null && $value !== '' && isset($defaults['typography'][$key])) {
                $this->config['typography'][$key] = $value;
            }
        }
        return $this->save();
    }

    /**
     * Atualiza configurações customizadas
     */
    public function updateCustom(array $custom): bool
    {
        $this->ensureConfigStructure();
        $defaults = $this->getDefaultConfig();
        
        foreach ($custom as $key => $value) {
            if ($value !== null && array_key_exists($key, $defaults['custom'])) {
                $this->config['custom'][$key] = $value;
            }
        }
        return $this->save();
    }

    /**
     * Reseta configurações para o padrão
     */
    public function resetToDefaults(): bool
    {
        $this->config = $this->getDefaultConfig();
        return $this->save();
    }

    /**
     * Garante que a estrutura de configuração existe
     */
    private function ensureConfigStructure(): void
    {
        $defaults = $this->getDefaultConfig();
        
        foreach ($defaults as $section => $values) {
            if (!isset($this->config[$section]) || !is_array($this->config[$section])) {
                $this->config[$section] = $values;
            } else {
                foreach ($values as $key => $value) {
                    if (!array_key_exists($key, $this->config[$section])) {
                        $this->config[$section][$key] = $value;
                    }
                }
            }
        }
    }

    /**
     * Salva configurações no banco
     */
    private function save(): bool
    {
        try {
            /** @var SettingModel $settings */
            $settings = model(SettingModel::class);

            if (! $settings->db->tableExists('settings')) {
                $this->createSettingsTable();
            }

            if (! $settings->setSetting('design_system', $this->config, 'json', 'appearance')) {
                throw new \RuntimeException('Failed to persist design_system setting.');
            }

            $this->invalidateCache();

            log_message('info', 'Design system configuration saved successfully');
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Could not save design config: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Invalidate design system cache
     */
    public function invalidateCache(): void
    {
        try {
            cache()->delete('design_system_css');
            cache()->delete('settings');
            cache()->delete('design_system');
            
            // Clear OPCache if available
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            log_message('info', 'Design system cache invalidated');
        } catch (\Exception $e) {
            log_message('error', 'Failed to invalidate design system cache: ' . $e->getMessage());
        }
    }

    /**
     * Cria tabela de configurações do sistema
     */
    private function createSettingsTable()
    {
        $forge = \Config\Database::forge();

        $fields = [
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'key' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
            ],
            'value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'string',
            ],
            'group' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'general',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'editable' => [
                'type' => 'BOOLEAN',
                'default' => true,
            ],
            'is_encrypted' => [
                'type' => 'BOOLEAN',
                'default' => false,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ];

        $forge->addField($fields);
        $forge->addKey('id', true);
        $forge->addUniqueKey('key');
        $forge->addKey('group');
        $forge->createTable('settings', true);

        log_message('info', 'Created settings table');
    }

    /**
     * Sanitize a CSS custom property name (variable name suffix).
     * Allows only letters, digits, hyphens and underscores.
     */
    private function safeCssKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
    }

    /**
     * Sanitize a CSS custom property value.
     * Strips < and > to prevent </style> tag-breakout injection while
     * leaving all legitimate CSS value characters intact.
     */
    private function safeCssValue(string $value): string
    {
        return str_replace(['<', '>'], '', $value);
    }

    /**
     * Gera CSS customizado baseado nas configurações
     */
    public function generateCSS(): string
    {
        $colors = $this->get('colors', []);
        $typography = $this->get('typography', []);
        $spacing = $this->get('spacing', []);
        $borders = $this->get('borders', []);
        $shadows = $this->get('shadows', []);
        $layout = $this->get('layout', []);
        $lightTheme = $this->get('light_theme', []);
        $darkTheme = $this->get('dark_theme', []);

        $css = ":root {\n";

        // Cores
        foreach ($colors as $key => $value) {
            $css .= '  --color-' . $this->safeCssKey((string) $key) . ': ' . $this->safeCssValue((string) $value) . ";\n";
        }

        // Tema claro
        foreach ($lightTheme as $key => $value) {
            $css .= '  --light-' . $this->safeCssKey((string) $key) . ': ' . $this->safeCssValue((string) $value) . ";\n";
        }

        // Tema escuro
        foreach ($darkTheme as $key => $value) {
            $css .= '  --dark-' . $this->safeCssKey((string) $key) . ': ' . $this->safeCssValue((string) $value) . ";\n";
        }

        // Tipografia
        foreach ($typography as $key => $value) {
            $css .= '  --' . $this->safeCssKey((string) $key) . ': ' . $this->safeCssValue((string) $value) . ";\n";
        }

        // Espaçamento
        foreach ($spacing as $key => $value) {
            $css .= '  --spacing-' . $this->safeCssKey((string) $key) . ': ' . $this->safeCssValue((string) $value) . ";\n";
        }

        // Bordas
        foreach ($borders as $key => $value) {
            $css .= '  --' . $this->safeCssKey((string) $key) . ': ' . $this->safeCssValue((string) $value) . ";\n";
        }

        // Sombras
        foreach ($shadows as $key => $value) {
            $css .= '  --shadow-' . $this->safeCssKey((string) $key) . ': ' . $this->safeCssValue((string) $value) . ";\n";
        }

        // Layout
        foreach ($layout as $key => $value) {
            $css .= '  --' . $this->safeCssKey((string) $key) . ': ' . $this->safeCssValue((string) $value) . ";\n";
        }

        $css .= "}\n\n";

        // Tema claro (padrão)
        $css .= "[data-theme='light'] {\n";
        $css .= "  --background: var(--light-background);\n";
        $css .= "  --surface: var(--light-surface);\n";
        $css .= "  --text-primary: var(--light-text_primary);\n";
        $css .= "  --text-secondary: var(--light-text_secondary);\n";
        $css .= "  --border: var(--light-border);\n";
        $css .= "}\n\n";

        // Tema escuro
        $css .= "[data-theme='dark'] {\n";
        $css .= "  --background: var(--dark-background);\n";
        $css .= "  --surface: var(--dark-surface);\n";
        $css .= "  --text-primary: var(--dark-text_primary);\n";
        $css .= "  --text-secondary: var(--dark-text_secondary);\n";
        $css .= "  --border: var(--dark-border);\n";
        $css .= "}\n";

        return $css;
    }
}
