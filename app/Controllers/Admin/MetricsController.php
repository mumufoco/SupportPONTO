<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use Config\Database;

class MetricsController extends BaseController
{
    private const WINDOWS = [1, 6, 24, 72, 168];

    public function index(): string
    {
        $window = $this->validWindow();
        $db     = Database::connect();

        $rows = $db->query(
            "SELECT metric, value, recorded_at
             FROM metrics_timeseries
             WHERE recorded_at >= NOW() - INTERVAL '$window hours'
             ORDER BY recorded_at ASC"
        )->getResultArray();

        $series = [];
        foreach ($rows as $r) {
            $series[$r['metric']][] = [
                'x' => (int)(strtotime($r['recorded_at']) * 1000),
                'y' => (float)$r['value'],
            ];
        }

        $latest = $db->query(
            "SELECT DISTINCT ON (metric) metric, value
             FROM metrics_timeseries ORDER BY metric, recorded_at DESC"
        )->getResultArray();
        $snap = [];
        foreach ($latest as $r) {
            $snap[$r['metric']] = (float)$r['value'];
        }

        return view('admin/metrics', ['series' => $series, 'snap' => $snap, 'window' => $window]);
    }

    public function json(): string
    {
        $window = $this->validWindow();
        $db     = Database::connect();
        $rows   = $db->query(
            "SELECT metric, value, recorded_at
             FROM metrics_timeseries
             WHERE recorded_at >= NOW() - INTERVAL '$window hours'
             ORDER BY recorded_at ASC"
        )->getResultArray();

        return $this->response->setJSON($rows);
    }

    private function validWindow(): int
    {
        $w = (int)($this->request->getGet('hours') ?? 24);
        return in_array($w, self::WINDOWS, true) ? $w : 24;
    }
}
