<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Calculation;

use App\Enums\PunchType;

class WorkPairingPolicy
{
    /** @param list<object|array<string,mixed>> $punches */
    public function pair(array $punches): array
    {
        usort($punches, static fn ($a, $b): int => strtotime((string) self::value($a, 'punch_time')) <=> strtotime((string) self::value($b, 'punch_time')));

        $pairs = [];
        $errors = [];
        $warnings = [];
        $openWork = null;
        $openBreak = null;
        $lastType = null;

        foreach ($punches as $punch) {
            $type = $this->normalizeType((string) self::value($punch, 'punch_type'));
            $time = (string) self::value($punch, 'punch_time');
            $nsr = self::value($punch, 'nsr') ?: 'sem NSR';

            if ($type === PunchType::Entrada->value) {
                if ($openWork !== null) {
                    $warnings[] = "Registro #{$nsr}: nova entrada com jornada anterior aberta.";
                }
                $openWork = $punch;
                $lastType = $type;
                continue;
            }

            if ($type === PunchType::IntervaloInicio->value) {
                if ($openWork === null) {
                    $errors[] = "Registro #{$nsr}: início de intervalo sem entrada aberta.";
                }
                if ($openBreak !== null) {
                    $warnings[] = "Registro #{$nsr}: início de intervalo duplicado sem fim anterior.";
                }
                $openBreak = $punch;
                $lastType = $type;
                continue;
            }

            if ($type === PunchType::IntervaloFim->value) {
                if ($openBreak === null) {
                    $errors[] = "Registro #{$nsr}: fim de intervalo sem início correspondente.";
                } else {
                    $pairs[] = $this->makePair('break', $openBreak, $punch);
                    $openBreak = null;
                }
                $lastType = $type;
                continue;
            }

            if ($type === PunchType::Saida->value) {
                if ($openBreak !== null) {
                    $errors[] = "Registro #{$nsr}: saída com intervalo ainda aberto.";
                    $openBreak = null;
                }
                if ($openWork === null) {
                    $errors[] = "Registro #{$nsr}: saída sem entrada correspondente.";
                } else {
                    $pairs[] = $this->makePair('work', $openWork, $punch);
                    $openWork = null;
                }
                $lastType = $type;
                continue;
            }

            $errors[] = "Registro #{$nsr}: tipo de marcação desconhecido ({$type}).";
        }

        if ($openBreak !== null) {
            $warnings[] = 'Intervalo iniciado e não finalizado.';
        }
        if ($openWork !== null) {
            $warnings[] = 'Jornada iniciada e não finalizada.';
        }
        if ($punches === []) {
            $warnings[] = 'Nenhuma marcação encontrada.';
        }

        return [
            'pairs' => $pairs,
            'errors' => $errors,
            'warnings' => $warnings,
            'complete' => $punches !== [] && $openWork === null && $openBreak === null && $lastType === PunchType::Saida->value,
            'ordered_punches' => $punches,
        ];
    }

    private function makePair(string $type, object|array $start, object|array $end): array
    {
        $startAt = (string) self::value($start, 'punch_time');
        $endAt = (string) self::value($end, 'punch_time');
        $seconds = max(0, strtotime($endAt) - strtotime($startAt));

        return [
            'type' => $type,
            'start' => $start,
            'end' => $end,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'hours' => round($seconds / 3600, 4),
        ];
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'almoco_saida', 'saida_intervalo', 'inicio_intervalo', 'intervalo-inicio' => PunchType::IntervaloInicio->value,
            'almoco_retorno', 'volta_intervalo', 'fim_intervalo', 'intervalo-fim' => PunchType::IntervaloFim->value,
            default => $type,
        };
    }

    private static function value(object|array $source, string $key): mixed
    {
        return is_array($source) ? ($source[$key] ?? null) : ($source->{$key} ?? null);
    }
}
