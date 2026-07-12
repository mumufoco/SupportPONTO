<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ClockAdjustmentModel;

/**
 * Declaração de ajustes do relógio do REP-P (SupportPONTO), exigidos pelo registro
 * tipo "4" do AFD (Portaria MTE 671/2021 — "Ajuste do relógio").
 *
 * Diferente de um REP-C/REP-A físico (cujo ajuste de relógio é um evento detectável pelo
 * próprio hardware), o "relógio" de um REP-P é o relógio do servidor onde o sistema roda —
 * mudanças nele são raras e conscientes (migração de servidor, correção manual de horário/
 * fuso). Por isso este evento é DECLARADO conscientemente por um administrador, com data/hora
 * antes e depois do ajuste e o CPF do responsável — e não inferido automaticamente.
 *
 * Cada declaração consome um NSR da mesma sequência canônica das marcações de ponto
 * (ver ClockAdjustmentModel::generateNSR), preservando a ordenação global exigida pelo AFD.
 */
class ClockAdjustmentController extends BaseController
{
    protected ClockAdjustmentModel $clockAdjustmentModel;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->clockAdjustmentModel = new ClockAdjustmentModel();
    }

    /**
     * Lista as declarações já registradas (somente leitura — registros são imutáveis).
     */
    public function index()
    {
        $adjustments = $this->clockAdjustmentModel
            ->orderBy('nsr', 'DESC')
            ->findAll(100);

        $data = [
            'title' => 'Ajustes de relógio do REP (AFD)',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => 'settings'],
                ['label' => 'Ajustes de relógio do REP', 'url' => ''],
            ],
            'adjustments' => $adjustments,
        ];

        return view('admin/settings/clock_adjustments', $data);
    }

    /**
     * Registra uma nova declaração de ajuste de relógio. Caminho único e oficial de
     * inserção — gera NSR canônico e hash de integridade via ClockAdjustmentModel.
     */
    public function store()
    {
        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido.');
        }

        $input = $this->request->getPost() ?? [];

        $previousDateTime = trim((string) ($input['previous_datetime'] ?? ''));
        $adjustedDateTime = trim((string) ($input['adjusted_datetime'] ?? ''));
        $responsibleCpf   = preg_replace('/\D/', '', (string) ($input['responsible_cpf'] ?? '')) ?? '';
        $reason           = trim((string) ($input['reason'] ?? ''));

        if ($previousDateTime !== '' && strlen($previousDateTime) === 16) {
            $previousDateTime .= ':00'; // <input type="datetime-local"> não envia segundos
        }
        if ($adjustedDateTime !== '' && strlen($adjustedDateTime) === 16) {
            $adjustedDateTime .= ':00';
        }

        $payload = [
            'previous_datetime' => $previousDateTime,
            'adjusted_datetime' => $adjustedDateTime,
            'responsible_cpf'   => $responsibleCpf,
            'declared_by'       => (int) ($this->currentUser->id ?? 0),
            'reason'            => $reason,
        ];

        if (! $this->clockAdjustmentModel->validate($payload)) {
            return redirect()->back()->withInput()->with('errors', $this->clockAdjustmentModel->errors());
        }

        try {
            $id = $this->clockAdjustmentModel->declareAdjustment($payload);
        } catch (\Throwable $e) {
            log_message('error', 'Falha ao declarar ajuste de relógio: ' . $e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Não foi possível registrar a declaração: ' . $e->getMessage());
        }

        return redirect()->back()
            ->with('success', 'Ajuste de relógio registrado com sucesso. O registro entrará automaticamente no próximo AFD que cobrir este período (NSR atribuído de forma canônica e imutável).');
    }
}
