<?php

namespace App\Services\Reports\Excel;

class ReportExcelValueFormatter
{
    public function cpf(string $cpf): string
    {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }

    public function hours(float $hours): string
    {
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        return sprintf('%02d:%02d', $h, $m);
    }

    public function balance(float $balance): string
    {
        $sign = $balance >= 0 ? '+' : '';
        return $sign . $this->hours(abs($balance));
    }

    public function dayOfWeekPt(string $dayOfWeek): string
    {
        $days = [
            'Monday' => 'Segunda',
            'Tuesday' => 'Terça',
            'Wednesday' => 'Quarta',
            'Thursday' => 'Quinta',
            'Friday' => 'Sexta',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo',
        ];

        return $days[$dayOfWeek] ?? $dayOfWeek;
    }
}
