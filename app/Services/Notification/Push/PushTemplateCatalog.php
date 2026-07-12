<?php

namespace App\Services\Notification\Push;

class PushTemplateCatalog
{
    private array $templates = [
        'punch_in' => [
            'title' => 'Ponto Registrado',
            'body' => 'Entrada registrada às {time}',
            'icon' => 'ic_punch_in',
            'sound' => 'default',
            'badge' => 1,
        ],
        'punch_out' => [
            'title' => 'Ponto Registrado',
            'body' => 'Saída registrada às {time}',
            'icon' => 'ic_punch_out',
            'sound' => 'default',
            'badge' => 1,
        ],
        'timesheet_approved' => [
            'title' => 'Ponto Aprovado',
            'body' => 'Seu ponto de {date} foi aprovado',
            'icon' => 'ic_approved',
            'sound' => 'success',
            'badge' => 1,
        ],
        'timesheet_rejected' => [
            'title' => 'Ponto Rejeitado',
            'body' => 'Seu ponto de {date} foi rejeitado. Motivo: {reason}',
            'icon' => 'ic_rejected',
            'sound' => 'alert',
            'badge' => 1,
        ],
        'warning_issued' => [
            'title' => 'Advertência',
            'body' => 'Você recebeu uma advertência: {reason}',
            'icon' => 'ic_warning',
            'sound' => 'alert',
            'badge' => 1,
        ],
        'schedule_updated' => [
            'title' => 'Escala Atualizada',
            'body' => 'Sua escala de trabalho foi atualizada',
            'icon' => 'ic_schedule',
            'sound' => 'default',
            'badge' => 1,
        ],
        'announcement' => [
            'title' => 'Comunicado',
            'body' => '{message}',
            'icon' => 'ic_announcement',
            'sound' => 'default',
            'badge' => 1,
        ],
    ];

    public function all(): array
    {
        return $this->templates;
    }

    public function add(string $name, array $template): void
    {
        $this->templates[$name] = $template;
    }

    public function resolve(string $templateName, array $variables = []): ?array
    {
        if (! isset($this->templates[$templateName])) {
            return null;
        }

        $template = $this->templates[$templateName];

        return [
            'title' => $this->replaceVariables((string) $template['title'], $variables),
            'body' => $this->replaceVariables((string) $template['body'], $variables),
            'options' => [
                'icon' => $template['icon'] ?? 'ic_notification',
                'sound' => $template['sound'] ?? 'default',
                'badge' => $template['badge'] ?? 1,
            ],
        ];
    }

    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }

        return $text;
    }
}
