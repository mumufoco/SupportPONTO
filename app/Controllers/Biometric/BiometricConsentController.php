<?php

namespace App\Controllers\Biometric;

use App\Controllers\BaseController;
use App\Models\ConsentTermModel;
use App\Models\UserConsentModel;
use App\Models\EmployeeModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class BiometricConsentController extends BaseController
{
    protected ConsentTermModel  $termModel;
    protected UserConsentModel  $consentModel;
    protected EmployeeModel     $employeeModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->termModel     = new ConsentTermModel();
        $this->consentModel  = new UserConsentModel();
        $this->employeeModel = new EmployeeModel();
    }

    /** Exibe o termo antes do cadastro biometrico (admin registrando por colaborador) */
    public function showForEmployee(int $employeeId): ResponseInterface|string
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'rh', 'gestor']);

        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            $this->setError('Colaborador nao encontrado.');
            return redirect()->to(site_url('biometric/manage'));
        }

        $term = $this->termModel->getActiveTerm('biometric_face');
        if (!$term) {
            return redirect()->to(site_url('biometric/enroll-for/' . $employeeId));
        }

        $alreadyConsented = \Config\Database::connect()->table('user_consents')
            ->where('employee_id', $employeeId)
            ->where('consent_type', 'biometric_face')
            ->where('granted', true)
            ->where('version', $term->version)
            ->where('revoked_at IS NULL')
            ->countAllResults();

        if ($alreadyConsented > 0) {
            return redirect()->to(site_url('biometric/enroll-for/' . $employeeId));
        }

        return view('biometric/consent_term', [
            'employee' => $employee,
            'term'     => $term,
            'title'    => 'Termo de Consentimento Biometrico',
        ]);
    }

    /** Registra o aceite e redireciona ao cadastro */
    public function acceptForEmployee(int $employeeId): ResponseInterface
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'rh', 'gestor']);

        if (!$this->request->is('post')) {
            return redirect()->to(site_url('biometric/consent-term/' . $employeeId));
        }

        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            $this->setError('Colaborador nao encontrado.');
            return redirect()->to(site_url('biometric/manage'));
        }

        $term = $this->termModel->getActiveTerm('biometric_face');
        if (!$term) {
            return redirect()->to(site_url('biometric/enroll-for/' . $employeeId));
        }

        if ($this->request->getPost('agreed') !== '1') {
            $this->setError('O colaborador deve aceitar o termo para prosseguir com o cadastro biometrico.');
            return redirect()->to(site_url('biometric/consent-term/' . $employeeId));
        }

        $actorId      = (int)  ($this->currentUser->id   ?? 0);
        $actorName    = (string)($this->currentUser->name ?? '');
        $now          = date('Y-m-d H:i:s');
        $resolvedBody = sp_apply_consent_variables($term->body);
        $evidenceData = $employeeId . '|biometric_face|' . $term->version . '|' . $resolvedBody . '|' . $now;

        \Config\Database::connect()->table('user_consents')->insert([
            'employee_id'        => $employeeId,
            'consent_type'       => 'biometric_face',
            'purpose'            => 'Identificacao biometrica facial para controle de ponto (REP-P) conforme Portaria MTE 671/2021.',
            'legal_basis'        => $term->legal_basis,
            'granted'            => true,
            'granted_at'         => $now,
            'ip_address'         => $this->request->getIPAddress(),
            'user_agent'         => (string) $this->request->getUserAgent(),
            'consent_text'       => $resolvedBody,
            'version'            => $term->version,
            'evidence_hash'      => hash('sha256', $evidenceData),
            'processing_context' => json_encode([
                'term_id'                => $term->id,
                'accepted_by_actor_id'   => $actorId,
                'accepted_by_actor_name' => $actorName,
                'acceptance_method'      => 'admin_enrollment_gate',
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        log_message('info', "[LGPD] Aceite biometrico facial: employee_id={$employeeId}, versao={$term->version}, actor_id={$actorId}");

        $this->setSuccess('Termo aceito. Prossiga com o cadastro biometrico.');
        return redirect()->to(site_url('biometric/enroll-for/' . $employeeId));
    }


    /** Lista todos os aceites (aba de auditoria LGPD) */
    public function listConsents(): ResponseInterface|string
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'dpo', 'auditor', 'rh']);

        $page       = (int) ($this->request->getGet('page') ?? 1);
        $perPage    = 20;
        $offset     = ($page - 1) * $perPage;
        $employeeId = (int) ($this->request->getGet('employee_id') ?? 0);

        $db = \Config\Database::connect();

        // Build WHERE clauses manually to avoid query builder clone issues
        $where  = '';
        $params = [];

        if ($employeeId > 0) {
            $where   .= " AND uc.employee_id = ?";
            $params[] = $employeeId;
        }

        $countSql = "SELECT COUNT(*) AS cnt
                     FROM user_consents uc
                     LEFT JOIN employees e ON e.id = uc.employee_id
                     WHERE 1=1{$where}";
        $totalRow = $db->query($countSql, $params)->getRowObject();
        $total    = (int) ($totalRow->cnt ?? 0);

        $recordSql = "SELECT uc.*,
                             e.name   AS employee_name,
                             e.cpf    AS employee_cpf,
                             e.email  AS employee_email,
                             e.active AS employee_active
                      FROM user_consents uc
                      LEFT JOIN employees e ON e.id = uc.employee_id
                      WHERE 1=1{$where}
                      ORDER BY uc.granted_at DESC
                      LIMIT ? OFFSET ?";

        $recordParams   = $params;
        $recordParams[] = $perPage;
        $recordParams[] = $offset;

        $records = $db->query($recordSql, $recordParams)->getResultObject();

        // MED-11 (auditoria): employees.cpf agora fica criptografado — este JOIN cru
        // não passa por EmployeeModel::afterFind(), então precisa decriptar aqui.
        foreach ($records as $record) {
            $record->employee_cpf = \App\Models\EmployeeModel::decryptCpfValue($record->employee_cpf ?? null);
        }

        // withDeleted(): registros de consentimento sao prova juridica permanente mesmo
        // apos o colaborador ser excluido do sistema - a lista de busca precisa alcancar
        // esses colaboradores tambem, nao so os ativos/soft-deleted-out.
        $employees = $this->employeeModel
            ->withDeleted()
            ->select('id, name, active, deleted_at')
            ->orderBy('active', 'DESC')
            ->orderBy('name', 'ASC')
            ->findAll();

        return view('biometric/consent_list', [
            'records'      => $records,
            'page'         => $page,
            'perPage'      => $perPage,
            'total'        => $total,
            'employeeId'   => $employeeId,
            'employees'    => $employees,
            'consentTypes' => self::MANAGED_CONSENT_TYPES,
            'title'        => 'Termos e Aceites LGPD',
        ]);
    }

    /** Download PDF juridicamente valido do aceite */
    public function downloadConsentPdf(int $consentId): ResponseInterface
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'dpo', 'auditor', 'rh']);

        $consent = \Config\Database::connect()->table('user_consents uc')
            ->select('uc.*, e.name AS employee_name, e.cpf AS employee_cpf, e.email AS employee_email, e.department')
            ->join('employees e', 'e.id = uc.employee_id', 'left')
            ->where('uc.id', $consentId)
            ->get()->getRowObject();

        if (!$consent) {
            $this->setError('Registro nao encontrado.');
            return redirect()->to(site_url('biometric/consent-terms/list'));
        }

        // MED-11 (auditoria): idem — JOIN cru precisa decriptar o CPF explicitamente
        // antes de compor o PDF juridicamente válido do termo.
        $consent->employee_cpf = \App\Models\EmployeeModel::decryptCpfValue($consent->employee_cpf ?? null);

        $pdf      = $this->buildConsentPdf($consent);
        $filename = 'termo-biometrico-' . $consentId . '-' . date('Ymd') . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($pdf);
    }

    private function buildConsentPdf(object $consent): string
    {
        $pdf = new \App\Services\Pdf\GotenbergPdfDocument('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('SupportPONTO');
        $pdf->SetAuthor('Support Solo Sondagens');
        $pdf->SetTitle('Termo de Consentimento Biometrico');
        $pdf->SetSubject('LGPD - Dados Biometricos Faciais');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $grantedAt  = $consent->granted_at
            ? date('d/m/Y \a\s H:i:s', strtotime($consent->granted_at))
            : '-';
        $cpfDisplay = $consent->employee_cpf
            ? '***.' . substr((string)$consent->employee_cpf, 3, 3) . '.***-**'
            : 'Nao informado';

        $ctx        = json_decode($consent->processing_context ?? '{}', true) ?? [];
        $acceptedBy = $ctx['accepted_by_actor_name'] ?? '(proprio colaborador)';
        $termText   = nl2br(esc($consent->consent_text ?? ''));

        $html  = '<h2 style="text-align:center;font-size:13pt;">TERMO DE CONSENTIMENTO — DADOS BIOMETRICOS FACIAIS</h2>';
        $html .= '<p style="text-align:center;font-size:9pt;color:#555;">Documento com validade juridica — LGPD (Lei 13.709/2018)</p><hr/>';
        $html .= '<h3>IDENTIFICACAO DO TITULAR</h3>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
        $html .= '<tr><td width="40%"><b>Colaborador</b></td><td>' . esc($consent->employee_name) . '</td></tr>';
        $html .= '<tr><td><b>CPF</b></td><td>' . $cpfDisplay . '</td></tr>';
        $html .= '<tr><td><b>E-mail</b></td><td>' . esc($consent->employee_email) . '</td></tr>';
        $html .= '<tr><td><b>Departamento</b></td><td>' . esc($consent->department ?? '-') . '</td></tr>';
        $html .= '</table><br/>';
        $html .= '<h3>REGISTRO DO ACEITE</h3>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
        $html .= '<tr><td width="40%"><b>Data e hora do aceite</b></td><td>' . $grantedAt . '</td></tr>';
        $html .= '<tr><td><b>Versao do termo aceito</b></td><td>' . esc($consent->version) . '</td></tr>';
        $html .= '<tr><td><b>IP do aceite</b></td><td>' . esc($consent->ip_address ?? '-') . '</td></tr>';
        $html .= '<tr><td><b>Aceite registrado por</b></td><td>' . esc($acceptedBy) . '</td></tr>';
        $html .= '<tr><td><b>Hash SHA-256 (integridade)</b></td><td style="font-size:7pt;word-break:break-all;">' . esc($consent->evidence_hash ?? '-') . '</td></tr>';
        $html .= '</table><br/>';
        $html .= '<h3>TEXTO INTEGRO DO TERMO ACEITO (versao ' . esc($consent->version) . ')</h3><hr/>';
        $html .= '<p style="font-size:9pt;">' . $termText . '</p>';
        $html .= '<br/><p style="font-size:7pt;color:#777;">Documento gerado automaticamente pelo SupportPONTO. O hash SHA-256 garante integridade e imutabilidade do aceite registrado no banco de dados. Em caso de auditoria, o hash deve ser verificado contra o registro em user_consents.evidence_hash.</p>';

        $pdf->writeHTML($html, true, false, true, false, '');
        return $pdf->Output('', 'S');
    }

    /** Tipos de termo geridos em settings/consent-terms (rotulo de exibicao). */
    private const MANAGED_CONSENT_TYPES = [
        'biometric_face'        => 'Biometria Facial',
        'biometric_fingerprint' => 'Biometria Digital',
        'geolocation'           => 'Geolocalização',
        'data_processing'       => 'Dados Pessoais',
        'marketing'             => 'Marketing',
        'data_sharing'          => 'Compartilhamento',
    ];

    /** Lista de templates de termos (settings/consent-terms) */
    public function manageTerms(): ResponseInterface|string
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'dpo', 'auditor', 'rh']);

        $rows = [];
        foreach (self::MANAGED_CONSENT_TYPES as $type => $label) {
            $active = $this->termModel->getActiveTerm($type);
            $rows[] = [
                'type'   => $type,
                'label'  => $label,
                'active' => $active,
            ];
        }

        return view('biometric/consent_terms_manage', [
            'rows'  => $rows,
            'title' => 'Templates de Termos de Consentimento',
        ]);
    }

    /** Editor de um template de termo (settings/consent-terms/{type}/edit) */
    public function editTerm(string $type): ResponseInterface|string
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'dpo', 'auditor', 'rh']);

        if (! array_key_exists($type, self::MANAGED_CONSENT_TYPES)) {
            return redirect()->to(site_url('settings/consent-terms'))->with('error', 'Tipo de termo inválido.');
        }

        $active = $this->termModel->getActiveTerm($type);

        return view('biometric/consent_term_edit', [
            'title'       => 'Termo: ' . self::MANAGED_CONSENT_TYPES[$type],
            'type'        => $type,
            'typeLabel'   => self::MANAGED_CONSENT_TYPES[$type],
            'activeTerm'  => $active,
            'allVersions' => $this->termModel->getAllVersions($type),
            'variables'   => array_keys(sp_consent_term_variables()),
        ]);
    }

    /** Salva nova versao do termo */
    public function saveTerm(): ResponseInterface
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'dpo', 'auditor', 'rh']);

        $type = (string) ($this->request->getPost('term_type') ?? 'biometric_face');
        if (! array_key_exists($type, self::MANAGED_CONSENT_TYPES)) {
            $type = 'biometric_face';
        }

        $title      = security_sanitize_text((string) $this->request->getPost('title'), 255);
        $bodyRaw    = (string) $this->request->getPost('body');
        $legalBasis = security_sanitize_text((string) $this->request->getPost('legal_basis'), 500);

        // $bodyRaw e sempre codigo-fonte HTML digitado direto no textarea (sem
        // editor rico) -- pode conter tags "vazias" sem texto real, por isso o
        // check usa strip_tags(), nao so a string bruta.
        if ($title === '' || trim(strip_tags($bodyRaw)) === '') {
            $this->setError('Título e texto do termo são obrigatórios.');
            return redirect()->to(site_url('settings/consent-terms/' . $type . '/edit'))->withInput();
        }

        // Sanitiza o HTML digitado antes de gravar -- nunca confia em HTML vindo
        // direto do navegador, mesmo de um admin autenticado.
        $body = (new \App\Services\Security\ConsentTermSanitizerService())->sanitize($bodyRaw);

        $nextVersion = $this->termModel->nextVersion($type);

        $this->termModel->deactivateAll($type);
        $this->termModel->insert([
            'type'        => $type,
            'version'     => $nextVersion,
            'title'       => $title,
            'body'        => $body,
            'legal_basis' => $legalBasis,
            'active'      => true,
            'created_by'  => (int) ($this->currentUser->id ?? 0),
        ]);

        log_message('info', "[LGPD] Novo termo biometrico publicado: versao={$nextVersion}");
        $this->setSuccess("Novo termo v{$nextVersion} publicado. Colaboradores sem aceite nesta versão serão solicitados novamente.");
        return redirect()->to(site_url('settings/consent-terms/' . $type . '/edit'));
    }

    /** Gate generico por tipo (fingerprint, geolocation, etc.) */
    public function showForEmployeeByType(int $employeeId, string $type): ResponseInterface|string
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'rh', 'gestor']);

        $allowed = array_keys(self::MANAGED_CONSENT_TYPES);
        if (!in_array($type, $allowed, true)) {
            $this->setError('Tipo de consentimento invalido.');
            return redirect()->to(site_url('biometric/manage'));
        }

        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            $this->setError('Colaborador nao encontrado.');
            return redirect()->to(site_url('biometric/manage'));
        }

        $term = $this->termModel->getActiveTerm($type);
        if (!$term) {
            return $this->redirectAfterConsent($type, $employeeId);
        }

        $alreadyConsented = \Config\Database::connect()->table('user_consents')
            ->where('employee_id', $employeeId)
            ->where('consent_type', $type)
            ->where('granted', true)
            ->where('version', $term->version)
            ->where('revoked_at IS NULL')
            ->countAllResults();

        if ($alreadyConsented > 0) {
            return $this->redirectAfterConsent($type, $employeeId);
        }

        return view('biometric/consent_term', [
            'employee'    => $employee,
            'term'        => $term,
            'consentType' => $type,
            'title'       => 'Termo de Consentimento',
        ]);
    }

    public function acceptForEmployeeByType(int $employeeId, string $type): ResponseInterface
    {
        $this->requireAuth();
        $this->requireAnyRole(['admin', 'rh', 'gestor']);

        $allowed = array_keys(self::MANAGED_CONSENT_TYPES);
        if (!in_array($type, $allowed, true)) {
            return redirect()->to(site_url('biometric/manage'));
        }

        if (!$this->request->is('post')) {
            return redirect()->to(site_url('biometric/consent-term/' . $type . '/' . $employeeId));
        }

        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            $this->setError('Colaborador nao encontrado.');
            return redirect()->to(site_url('biometric/manage'));
        }

        if ($this->request->getPost('agreed') !== '1') {
            $this->setError('O colaborador deve aceitar o termo para prosseguir.');
            return redirect()->to(site_url('biometric/consent-term/' . $type . '/' . $employeeId));
        }

        $term = $this->termModel->getActiveTerm($type);
        if (!$term) {
            return $this->redirectAfterConsent($type, $employeeId);
        }

        $actorId      = (int)  ($this->currentUser->id   ?? 0);
        $actorName    = (string)($this->currentUser->name ?? '');
        $now          = date('Y-m-d H:i:s');
        $resolvedBody = sp_apply_consent_variables($term->body);
        $evidence     = $employeeId . '|' . $type . '|' . $term->version . '|' . $resolvedBody . '|' . $now;

        \Config\Database::connect()->table('user_consents')->insert([
            'employee_id'             => $employeeId,
            'employee_name_snapshot'  => $employee->name ?? null,
            'employee_cpf_snapshot'   => $employee->cpf  ?? null,
            'consent_type'            => $type,
            'purpose'                 => $term->title,
            'legal_basis'             => $term->legal_basis,
            'granted'                 => true,
            'granted_at'              => $now,
            'ip_address'              => $this->request->getIPAddress(),
            'user_agent'              => (string) $this->request->getUserAgent(),
            'consent_text'            => $resolvedBody,
            'version'                 => $term->version,
            'evidence_hash'           => hash('sha256', $evidence),
            'processing_context'      => json_encode([
                'term_id'                => $term->id,
                'accepted_by_actor_id'   => $actorId,
                'accepted_by_actor_name' => $actorName,
                'acceptance_method'      => 'admin_enrollment_gate',
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        log_message('info', "[LGPD] Aceite {$type}: employee_id={$employeeId}, versao={$term->version}, actor_id={$actorId}");
        $this->setSuccess('Termo aceito com sucesso.');
        return $this->redirectAfterConsent($type, $employeeId);
    }

    private function redirectAfterConsent(string $type, int $employeeId): ResponseInterface
    {
        $map = [
            'biometric_face'        => 'biometric/enroll-for/' . $employeeId,
            'biometric_fingerprint' => 'biometric/fingerprint/enroll/' . $employeeId,
            'geolocation'           => 'employees/' . $employeeId . '/edit',
            'data_processing'       => 'employees/' . $employeeId . '/edit',
            'marketing'             => 'lgpd/consents',
            'data_sharing'          => 'lgpd/consents',
        ];
        return redirect()->to(site_url($map[$type] ?? 'biometric/manage'));
    }

}
