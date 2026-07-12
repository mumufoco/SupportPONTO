<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class IntegrationsController extends BaseController
{
    protected SettingModel $settingModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->settingModel = model(SettingModel::class);
    }

    public function index()
    {
        $this->requireRole('admin');

        $apis    = $this->settingModel->getByGroupMap('apis')          ?? [];
        $bio     = $this->settingModel->getByGroupMap('biometry')      ?? [];
        $geo     = $this->settingModel->getByGroupMap('geolocation')   ?? [];
        $intg    = $this->settingModel->getByGroupMap('integrations')  ?? [];

        return view('admin/settings/integrations', [
            'title'       => 'Integracoes',
            'breadcrumbs' => [
                ['label' => 'Configuracoes', 'url' => sp_admin_settings_index_url()],
                ['label' => 'Integracoes',   'url' => ''],
            ],
            'settings' => array_merge($geo, $bio, $apis, $intg),
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Metodo invalido');
        }

        $data            = security_sanitize($this->request->getPost() ?? []);
        $biometryFields  = ['biometry_enabled', 'facial_recognition_enabled', 'facial_recognition_threshold', 'fingerprint_enabled'];
        $geolFields      = ['geolocation_enabled', 'geolocation_radius', 'geolocation_strict'];
        $apisEncrypted   = ['api_facial_key', 'api_biometry_key', 'api_geolocation_key', 'api_fcm_key'];

        // Normalize unchecked checkboxes to '0' (HTML doesn't POST unchecked checkboxes)
        $allBoolFields = [
            'biometry_enabled', 'facial_recognition_enabled', 'fingerprint_enabled',
            'geolocation_enabled', 'geolocation_strict',
            'googlemaps_enabled', 'mapbox_enabled',
            'backup_s3_enabled', 'backup_gcs_enabled', 'backup_onedrive_enabled',
        ];
        foreach ($allBoolFields as $bf) {
            if (!array_key_exists($bf, $data)) {
                $data[$bf] = '0';
            }
        };

        // New: geo provider fields
        $geoProviderFields = [
            'googlemaps_enabled', 'googlemaps_api_key', 'googlemaps_endpoint',
            'mapbox_enabled', 'mapbox_api_key', 'mapbox_endpoint',
        ];

        // New: backup provider fields
        $backupFields = [
            'backup_s3_enabled', 'backup_s3_key', 'backup_s3_secret', 'backup_s3_bucket', 'backup_s3_region', 'backup_s3_retention', 'backup_s3_frequency',
            'backup_gcs_enabled', 'backup_gcs_key', 'backup_gcs_bucket', 'backup_gcs_folder', 'backup_gcs_retention', 'backup_gcs_frequency',
            'backup_onedrive_enabled', 'backup_onedrive_client_id', 'backup_onedrive_client_secret', 'backup_onedrive_folder', 'backup_onedrive_retention', 'backup_onedrive_frequency',
        ];

        try {
            $bSave = [];
            foreach ($biometryFields as $f) {
                if (array_key_exists($f, $data)) $bSave[$f] = $data[$f];
            }
            if ($bSave) $this->settingModel->setMultiple($bSave, 'biometry');

            $gSave = [];
            foreach ($geolFields as $f) {
                if (array_key_exists($f, $data)) $gSave[$f] = $data[$f];
            }
            if ($gSave) $this->settingModel->setMultiple($gSave, 'geolocation');

            foreach ($apisEncrypted as $f) {
                if (! empty($data[$f])) {
                    $this->settingModel->setSetting($f, $data[$f], 'string', 'apis', true);
                }
            }

            // Save geo provider + backup fields to integrations group
            // Sensitive credential fields: only save if non-empty (preserve existing keys)
            $sensitiveFields = [
                'googlemaps_api_key', 'mapbox_api_key',
                'backup_s3_key', 'backup_s3_secret',
                'backup_gcs_key',
                'backup_onedrive_client_id', 'backup_onedrive_client_secret',
            ];
            $intgSave = [];
            foreach (array_merge($geoProviderFields, $backupFields) as $f) {
                if (!array_key_exists($f, $data)) continue;
                if (in_array($f, $sensitiveFields, true) && $data[$f] === '') continue; // preserve existing
                $intgSave[$f] = $data[$f];
            }
            if ($intgSave) $this->settingModel->setMultiple($intgSave, 'integrations');

            $this->settingModel->clearCache();

            return redirect()->back()->with('success', 'Integracoes salvas com sucesso.');
        } catch (\Throwable $e) {
            log_message('error', 'IntegrationsController::update ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Erro ao salvar.');
        }
    }
}
