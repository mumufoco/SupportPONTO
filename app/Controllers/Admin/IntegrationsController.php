<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Integracoes
 *
 * Reproduz o catalogo de integracoes do SupportCHECK (nome + campos tipados +
 * marcacao do que e sensivel), mas populado apenas com as integracoes REAIS
 * do SupportPONTO -- confirmado por grep em todo o app/ antes de escrever
 * esta tela:
 *
 * - DeepFace (biometria facial): deepface_api_url/timeout/retry_attempts/
 *   api_key/internal_token/model/threshold, todos lidos de verdade em
 *   DeepFaceApiClient/DeepFaceService; e facial_recognition_threshold, lido
 *   em FaceRecognitionService/TimePunchFlowService (limiar de aceitacao do
 *   PONTO, DIFERENTE do deepface_threshold que e enviado como parametro ao
 *   microservico DeepFace).
 * - FCM (notificacoes push): fcm_server_key.
 *
 * Removidos os campos que a tela antiga tinha mas nunca eram lidos em
 * nenhum lugar do codigo real (confirmado por grep): biometry_enabled,
 * facial_recognition_enabled, fingerprint_enabled, geolocation_enabled/
 * radius/strict, googlemaps_*, mapbox_*, backup_s3_*, backup_gcs_*,
 * backup_onedrive_*, api_facial_key/api_biometry_key/api_geolocation_key/
 * api_fcm_key.
 *
 * SupportCheck (sincronizacao com o SupportCHECK) fica de fora -- ja tem
 * tela propria em Settings\SupportCheckSettingsController.
 */
class IntegrationsController extends BaseController
{
    /**
     * @var list<string>
     */
    private const DEEPFACE_FIELDS = [
        'deepface_api_url', 'deepface_timeout', 'deepface_retry_attempts',
        'deepface_api_key', 'deepface_internal_token', 'deepface_model',
        'deepface_threshold', 'facial_recognition_threshold',
    ];

    private const DEEPFACE_SENSITIVE = ['deepface_api_key', 'deepface_internal_token'];

    private const FCM_FIELDS = ['fcm_server_key'];

    private const FCM_SENSITIVE = ['fcm_server_key'];

    protected SettingModel $settingModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->settingModel = model(SettingModel::class);
    }

    public function index()
    {
        $this->requireRole('admin');

        $settings = [];
        foreach (array_merge(self::DEEPFACE_FIELDS, self::FCM_FIELDS) as $field) {
            $settings[$field] = $this->settingModel->get($field);
        }

        return view('admin/settings/integrations', [
            'title'       => 'Integrações',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'Integrações',   'url' => ''],
            ],
            'settings' => $settings,
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $data = security_sanitize($this->request->getPost() ?? []);

        try {
            $this->saveGroup($data, self::DEEPFACE_FIELDS, self::DEEPFACE_SENSITIVE, 'biometria');
            $this->saveGroup($data, self::FCM_FIELDS, self::FCM_SENSITIVE, 'integrations');

            $this->settingModel->clearCache();

            return redirect()->back()->with('success', 'Integrações salvas com sucesso.');
        } catch (\Throwable $e) {
            log_message('error', 'IntegrationsController::update ' . $e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Erro ao salvar.');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $fields
     * @param list<string> $sensitiveFields
     */
    private function saveGroup(array $data, array $fields, array $sensitiveFields, string $group): void
    {
        $save = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            // Campo sensivel em branco = manter valor ja salvo (nao sobrescrever
            // com vazio so porque o admin nao redigitou o segredo).
            if (in_array($field, $sensitiveFields, true) && trim((string) $data[$field]) === '') {
                continue;
            }
            $save[$field] = $data[$field];
        }

        if ($save !== []) {
            $this->settingModel->setMultiple($save, $group);
        }
    }
}
