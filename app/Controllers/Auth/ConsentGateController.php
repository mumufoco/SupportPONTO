<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\ConsentTermModel;
use App\Models\UserConsentModel;

/**
 * ConsentGateController
 *
 * Gerencia o fluxo de aceitação dos termos principais de consentimento LGPD
 * no primeiro acesso ao sistema.
 *
 * Exibe a lista de termos pendentes, apresenta cada termo individualmente
 * e registra a aceitação. Só libera o acesso ao dashboard após todos
 * os termos obrigatórios serem aceitos.
 */
class ConsentGateController extends BaseController
{
    private const REQUIRED_TYPES = [
        'data_processing' => [
            'label'       => 'Processamento de Dados Pessoais',
            'icon'        => 'bi bi-person-lines-fill',
            'description' => 'Autoriza o tratamento dos seus dados pessoais para fins de gestão de ponto, folha de pagamento e obrigações trabalhistas.',
            'legal_basis' => 'LGPD Art. 7º, V – Execução de contrato',
            'required'    => true,
        ],
        'data_sharing' => [
            'label'       => 'Compartilhamento de Dados',
            'icon'        => 'bi bi-share-fill',
            'description' => 'Autoriza o compartilhamento de dados com prestadores de benefícios, parceiros de segurança do trabalho e órgãos regulatórios conforme exigido por lei.',
            'legal_basis' => 'LGPD Art. 7º, V – Execução de contrato',
            'required'    => true,
        ],
        'geolocation' => [
            'label'       => 'Geolocalização',
            'icon'        => 'bi bi-geo-alt-fill',
            'description' => 'Autoriza o uso da sua localização geográfica para registro de ponto em campo, controle de limites virtuais e validação de presença.',
            'legal_basis' => 'LGPD Art. 7º, I – Consentimento',
            'required'    => true,
        ],
        'marketing' => [
            'label'       => 'Comunicações de Marketing',
            'icon'        => 'bi bi-megaphone-fill',
            'description' => 'Autoriza o envio de comunicados sobre novidades, atualizações e informações institucionais da empresa por e-mail ou mensagem.',
            'legal_basis' => 'LGPD Art. 7º, I – Consentimento',
            'required'    => false,
        ],
    ];

    /**
     * Lista todos os termos pendentes de aceitação.
     */
    public function index(): string
    {
        $userId    = (int) session('user_id');
        $consentModel = model(UserConsentModel::class);

        $pending  = [];
        $accepted = [];

        foreach (self::REQUIRED_TYPES as $type => $meta) {
            $entry = ['type' => $type] + $meta;
            if ($consentModel->hasConsent($userId, $type)) {
                $accepted[] = $entry;
            } else {
                $pending[] = $entry;
            }
        }

        // Se não há mais pendentes, redireciona ao dashboard.
        if (empty($pending)) {
            return redirect()->to(site_url('dashboard'));
        }

        return view('auth/consent_gate_list', [
            'pending'  => $pending,
            'accepted' => $accepted,
            'total'    => count(self::REQUIRED_TYPES),
        ]);
    }

    /**
     * Exibe o texto completo de um termo específico para aceitação.
     */
    public function show(string $type): string
    {
        if (!array_key_exists($type, self::REQUIRED_TYPES)) {
            return redirect()->to(site_url('consent-gate'));
        }

        $userId       = (int) session('user_id');
        $consentModel = model(UserConsentModel::class);

        // Já aceito — avança para o próximo pendente.
        if ($consentModel->hasConsent($userId, $type)) {
            return redirect()->to(site_url('consent-gate'));
        }

        $termModel = model(ConsentTermModel::class);
        $term      = $termModel->getActiveTerm($type);
        $meta      = self::REQUIRED_TYPES[$type];

        // Próximo termo pendente (para mostrar progresso)
        $pending = $this->getPendingTypes($userId, $consentModel);
        $position = array_search($type, $pending, true);
        $total    = count(self::REQUIRED_TYPES);
        $done     = $total - count($pending);

        return view('auth/consent_gate_term', [
            'type'       => $type,
            'meta'       => $meta,
            'term'       => $term,
            'done'       => $done,
            'total'      => $total,
            'position'   => $position !== false ? (int) $position : 0,
            'pendingCount' => count($pending),
        ]);
    }

    /**
     * Registra a aceitação de um termo e redireciona ao próximo pendente.
     */
    public function accept(string $type): \CodeIgniter\HTTP\RedirectResponse
    {
        if (!array_key_exists($type, self::REQUIRED_TYPES)) {
            return redirect()->to(site_url('consent-gate'));
        }

        $userId       = (int) session('user_id');
        $consentModel = model(UserConsentModel::class);
        $termModel    = model(ConsentTermModel::class);
        $meta         = self::REQUIRED_TYPES[$type];

        // Busca o texto do termo ativo; usa descrição padrão como fallback.
        $term        = $termModel->getActiveTerm($type);
        $consentText = $term?->body ?? $meta['description'];
        $version     = $term?->version ?? '1.0';
        $legalBasis  = $term?->legal_basis ?? $meta['legal_basis'];

        if (!$consentModel->hasConsent($userId, $type)) {
            $consentModel->grant(
                $userId,
                $type,
                $meta['description'],
                $consentText,
                $legalBasis,
                $version,
            );
        }

        // Redireciona ao próximo termo pendente ou ao dashboard.
        $pending = $this->getPendingTypes($userId, $consentModel);

        if (empty($pending)) {
            return redirect()->to(site_url('dashboard'))
                             ->with('success', 'Termos aceitos. Bem-vindo ao SupportPONTO!');
        }

        return redirect()->to(site_url('consent-gate/' . $pending[0]));
    }

    /**
     * Retorna a lista ordenada de tipos ainda pendentes de aceitação.
     *
     * @return list<string>
     */
    private function getPendingTypes(int $userId, UserConsentModel $model): array
    {
        $pending = [];
        foreach (array_keys(self::REQUIRED_TYPES) as $type) {
            if (!$model->hasConsent($userId, $type)) {
                $pending[] = $type;
            }
        }
        return $pending;
    }
}
