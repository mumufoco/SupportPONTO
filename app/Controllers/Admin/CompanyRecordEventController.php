<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CompanyRecordEventModel;
use App\Services\TXT\TXTSettingsProvider;

/**
 * Declaração de alterações cadastrais da empresa no REP-P (SupportPONTO), exigidas pelo
 * registro tipo "2" do AFD (Portaria MTE 671/2021 — "Inclusão ou alteração da identificação
 * da empresa no REP").
 *
 * Assim como o ajuste de relógio (ver ClockAdjustmentController), este é um evento RARO e
 * CONSCIENTE: só ocorre quando a razão social, o CNPJ/CPF, o CNO/CAEPF ou o local de
 * prestação de serviços da empresa mudam no REP. Por isso é DECLARADO conscientemente por
 * um administrador — que registra o "retrato" dos novos dados cadastrais — e não inferido
 * automaticamente a partir de mudanças em `TXTSettingsProvider`.
 *
 * Cada declaração consome um NSR da mesma sequência canônica das marcações de ponto e dos
 * ajustes de relógio (ver CompanyRecordEventModel::generateNSR), preservando a ordenação
 * global exigida pelo AFD.
 */
class CompanyRecordEventController extends BaseController
{
    protected CompanyRecordEventModel $companyRecordEventModel;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->companyRecordEventModel = new CompanyRecordEventModel();
    }

    /**
     * Lista as declarações já registradas (somente leitura — registros são imutáveis) e
     * exibe o cadastro atual da empresa (origem dos valores sugeridos no formulário).
     */
    public function index()
    {
        $events = $this->companyRecordEventModel
            ->orderBy('nsr', 'DESC')
            ->findAll(100);

        $companyProfile = (new TXTSettingsProvider())->getCompanyProfile();

        $data = [
            'title' => 'Alterações cadastrais da empresa no REP (AFD)',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => 'settings'],
                ['label' => 'Alterações cadastrais no REP', 'url' => ''],
            ],
            'events' => $events,
            'companyProfile' => $companyProfile,
        ];

        return view('admin/settings/company_record_events', $data);
    }

    /**
     * Registra uma nova declaração de alteração cadastral. Caminho único e oficial de
     * inserção — gera NSR canônico e hash de integridade via CompanyRecordEventModel.
     */
    public function store()
    {
        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido.');
        }

        $input = $this->request->getPost() ?? [];

        $recordedAt       = trim((string) ($input['recorded_at'] ?? ''));
        $responsibleCpf   = preg_replace('/\D/', '', (string) ($input['responsible_cpf'] ?? '')) ?? '';
        $employerDocType  = trim((string) ($input['employer_doc_type'] ?? '1')) ?: '1';
        $employerDoc      = preg_replace('/\D/', '', (string) ($input['employer_doc'] ?? '')) ?? '';
        $cnoCaepf         = preg_replace('/\D/', '', (string) ($input['cno_caepf'] ?? '')) ?? '';
        $companyName      = trim((string) ($input['company_name'] ?? ''));
        $serviceLocation  = trim((string) ($input['service_location'] ?? ''));
        $reason           = trim((string) ($input['reason'] ?? ''));

        if ($recordedAt !== '' && strlen($recordedAt) === 16) {
            $recordedAt .= ':00'; // <input type="datetime-local"> não envia segundos
        }

        $payload = [
            'recorded_at'       => $recordedAt,
            'responsible_cpf'   => $responsibleCpf,
            'employer_doc_type' => $employerDocType,
            'employer_doc'      => $employerDoc,
            'cno_caepf'         => $cnoCaepf !== '' ? $cnoCaepf : null,
            'company_name'      => $companyName,
            'service_location'  => $serviceLocation !== '' ? $serviceLocation : null,
            'reason'            => $reason,
            'declared_by'       => (int) ($this->currentUser->id ?? 0),
        ];

        if (! $this->companyRecordEventModel->validate($payload)) {
            return redirect()->back()->withInput()->with('errors', $this->companyRecordEventModel->errors());
        }

        try {
            $this->companyRecordEventModel->declareCompanyRecordEvent($payload);
        } catch (\Throwable $e) {
            log_message('error', 'Falha ao declarar alteração cadastral da empresa: ' . $e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Não foi possível registrar a declaração: ' . $e->getMessage());
        }

        return redirect()->back()
            ->with('success', 'Alteração cadastral registrada com sucesso. O registro entrará automaticamente no próximo AFD que cobrir este período (NSR atribuído de forma canônica e imutável).');
    }
}
