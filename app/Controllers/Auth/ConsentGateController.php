<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\ConsentTermModel;
use App\Models\EmployeeModel;
use App\Models\UserConsentModel;
use App\Services\LGPD\ConsentGateCatalog;

/**
 * ConsentGateController
 *
 * Gerencia o fluxo de aceitação dos termos principais de consentimento LGPD
 * (todo colaborador, qualquer papel). Expõe o fluxo completo (termo por
 * termo, com leitura obrigatória) em /consent-gate, e o aceite em lote usado
 * pelo lembrete flutuante do dashboard em accept-all().
 */
class ConsentGateController extends BaseController
{
    private const REQUIRED_TYPES = ConsentGateCatalog::TYPES;

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
        $employee  = model(EmployeeModel::class)->find($userId);

        // Próximo termo pendente (para mostrar progresso)
        $pending = $this->getPendingTypes($userId, $consentModel);
        $position = array_search($type, $pending, true);
        $total    = count(self::REQUIRED_TYPES);
        $done     = $total - count($pending);

        return view('auth/consent_gate_term', [
            'type'       => $type,
            'meta'       => $meta,
            'term'       => $term,
            'employee'   => $employee,
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
        $employee     = model(EmployeeModel::class)->find($userId);

        // Busca o texto do termo ativo; usa descrição padrão como fallback.
        $term        = $termModel->getActiveTerm($type);
        $consentText = sp_apply_consent_variables($term?->body ?? $meta['description'], false, $employee);
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
     * Aceita de uma vez todos os termos obrigatórios ainda pendentes -- usado
     * pelo lembrete flutuante exibido no dashboard (botão único "Aceitar").
     */
    public function acceptAll(): \CodeIgniter\HTTP\RedirectResponse
    {
        $userId       = (int) session('user_id');
        $consentModel = model(UserConsentModel::class);
        $termModel    = model(ConsentTermModel::class);
        $employee     = model(EmployeeModel::class)->find($userId);

        foreach ($this->getPendingTypes($userId, $consentModel) as $type) {
            $meta        = self::REQUIRED_TYPES[$type];
            $term        = $termModel->getActiveTerm($type);
            $consentText = sp_apply_consent_variables($term?->body ?? $meta['description'], false, $employee);
            $version     = $term?->version ?? '1.0';
            $legalBasis  = $term?->legal_basis ?? $meta['legal_basis'];

            $consentModel->grant($userId, $type, $meta['description'], $consentText, $legalBasis, $version);
        }

        return redirect()->back()->with('success', 'Termos aceitos. Obrigado!');
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
