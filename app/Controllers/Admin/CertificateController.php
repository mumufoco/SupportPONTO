<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Upload\SafeUploadService;

use App\Controllers\BaseController;
use App\Services\Auth\SessionSecurityService;
use App\Models\SettingModel;
use Config\Services;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Certificate Settings Controller
 *
 * Manages digital certificates A1/A3 for document signing
 */
class CertificateController extends BaseController
{
    protected SettingModel $settingModel;
    protected SessionSecurityService $sessionSecurityService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        
        $this->settingModel = Services::settings(false);
        $this->sessionSecurityService = Services::sessionSecurityService();
    }

    /**
     * Certificate settings page
     */
    public function index()
    {
        $settings = $this->settingModel->getByGroupMap('certificate');

        $data = [
            'title' => 'Configurações de Certificado Digital',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => 'settings'],
                ['label' => 'Certificado Digital', 'url' => '']
            ],
            'settings' => $settings,
            'certificate_info' => $this->getCertificateInfo()
        ];

        return view('admin/settings/certificate', $data);
    }

    /**
     * Update certificate settings
     */
    public function update()
    {
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, $this->session);
        if (! ($guard['success'] ?? false)) {
            return redirect()->back()->withInput()->with('error', $guard['message'] ?? 'Confirme sua senha para atualizar o certificado.');
        }

        try {
            $data = security_sanitize($this->request->getPost() ?? []);
            
            // Start database transaction
            $db = \Config\Database::connect();
            $db->transStart();
            
            $file = $this->request->getFile('certificate_file');
            
            if ($file && $file->isValid() && !$file->hasMoved()) {
                $uploadSecurity = new SafeUploadService();
                $validExtensions = ['pfx', 'p12'];
                $extension = strtolower((string) ($file->getClientExtension() ?: $file->getExtension()));

                if (!in_array($extension, $validExtensions, true) || in_array($extension, $uploadSecurity->blockedExtensions(), true)) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Tipo de arquivo inválido. Use .pfx ou .p12');
                }

                if ($file->getSize() > 2097152) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Arquivo muito grande. Máximo 2MB.');
                }

                $realMime = $uploadSecurity->detectRealMime((string) $file->getTempName());
                $acceptedCertMimes = ['application/x-pkcs12', 'application/pkcs12', 'application/octet-stream'];
                if ($realMime === null || !in_array($realMime, $acceptedCertMimes, true)) {
                    $uploadSecurity->audit('certificate_upload_blocked_mime', ['mime_type' => $realMime, 'extension' => $extension]);
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Tipo MIME inválido para certificado digital.');
                }

                $certPassword = $data['certificate_password'] ?? '';

                $certContent = file_get_contents($file->getTempName());
                $certInfo = [];
                if (!openssl_pkcs12_read($certContent, $certInfo, $certPassword)) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Não foi possível ler o certificado. Verifique a senha.');
                }
                
                $oldCertPath = $this->settingModel->get('certificate_path');
                if ($oldCertPath && file_exists($oldCertPath)) {
                    unlink($oldCertPath);
                }
                
                $newName = $uploadSecurity->randomFilename($extension);
                $uploadPath = WRITEPATH . 'certificates';

                if (!is_dir($uploadPath) && !mkdir($uploadPath, 0700, true) && !is_dir($uploadPath)) {
                    throw new \Exception('Failed to prepare certificate directory');
                }

                if (!$file->move($uploadPath, $newName)) {
                    throw new \Exception('Failed to save certificate file');
                }
                @chmod($uploadPath . '/' . $newName, 0600);
                $uploadSecurity->audit('certificate_stored_private', ['path' => 'certificates/' . $newName, 'mime_type' => $realMime]);

                if (!$this->settingModel->setSetting('certificate_path', $uploadPath . '/' . $newName, 'file', 'certificate')) {
                    throw new \Exception('Failed to save certificate path');
                }
                if (!$this->settingModel->setSetting('certificate_password', $certPassword, 'string', 'certificate', true)) {
                    throw new \Exception('Failed to save certificate password');
                }
            }
            
            unset($data['certificate_file'], $data['certificate_password']);
            
            if (!empty($data)) {
                if (!$this->settingModel->setMultiple($data, 'certificate')) {
                    throw new \Exception('Failed to save certificate settings');
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Database transaction failed');
            }

            $this->settingModel->clearCache();

            log_message('info', 'Certificate settings updated successfully', ['user' => session()->get('user_id')]);

            return redirect()->back()->with('success', 'Certificado salvo com sucesso.');

        } catch (\Exception $e) {
            log_message('error', 'Error updating certificate settings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user' => session()->get('user_id')
            ]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao atualizar configurações. Por favor, tente novamente.');
        }
    }

    /**
     * Test certificate
     */
    public function test()
    {
        if (!$this->request->is('post')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Método inválido'
            ]);
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, $this->session);
        if (! ($guard['success'] ?? false)) {
            return $this->response->setJSON($guard)->setStatusCode(422);
        }

        try {
            $certPath = $this->settingModel->get('certificate_path');
            $certPassword = $this->settingModel->get('certificate_password');

            if (!$certPath || !file_exists($certPath)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Nenhum certificado configurado'
                ]);
            }

            $certContent = file_get_contents($certPath);
            $certInfo = [];

            if (openssl_pkcs12_read($certContent, $certInfo, $certPassword)) {
                $certData = openssl_x509_parse($certInfo['cert']);

                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Certificado válido',
                    'info' => [
                        'subject' => $certData['subject']['CN'] ?? 'N/A',
                        'issuer' => $certData['issuer']['CN'] ?? 'N/A',
                        'valid_from' => date('d/m/Y H:i:s', $certData['validFrom_time_t']),
                        'valid_to' => date('d/m/Y H:i:s', $certData['validTo_time_t']),
                        'is_valid' => time() < $certData['validTo_time_t']
                    ]
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Não foi possível validar o certificado.'
                ]);
            }

        } catch (\Exception $e) {
            log_message('error', 'Certificate test error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao testar certificado.'
            ]);
        }
    }

    /**
     * Remove certificate
     */
    public function remove()
    {
        if (!$this->request->is('post')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Método inválido'
            ]);
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, $this->session);
        if (! ($guard['success'] ?? false)) {
            return $this->response->setJSON($guard)->setStatusCode(422);
        }

        try {
            $certPath = $this->settingModel->get('certificate_path');

            if ($certPath && file_exists($certPath)) {
                unlink($certPath);
            }

            $this->settingModel->deleteSetting('certificate_path');
            $this->settingModel->deleteSetting('certificate_password');

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Certificado removido com sucesso'
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao remover certificado: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Reset certificate settings to defaults
     */
    public function reset()
    {
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, $this->session);
        if (! ($guard['success'] ?? false)) {
            return redirect()->back()->with('error', $guard['message'] ?? 'Confirme sua senha para resetar o certificado.');
        }

        try {
            $certPath = $this->settingModel->get('certificate_path');
            if ($certPath && file_exists($certPath)) {
                unlink($certPath);
            }

            $this->settingModel->deleteGroup('certificate');

            $this->settingModel->clearCache();

            return redirect()->back()->with('success', 'Configurações de certificado resetadas');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao resetar: ' . $e->getMessage());
        }
    }

    /**
     * Get current certificate info
     */
    protected function getCertificateInfo(): ?array
    {
        try {
            $certPath = $this->settingModel->get('certificate_path');
            $certPassword = $this->settingModel->get('certificate_password');

            if (!$certPath || !file_exists($certPath)) {
                return null;
            }

            $certContent = file_get_contents($certPath);
            if ($certContent === false) {
                return null;
            }

            $certInfo = [];

            if (openssl_pkcs12_read($certContent, $certInfo, $certPassword)) {
                $certData = openssl_x509_parse($certInfo['cert']);

                return [
                    'subject' => $certData['subject']['CN'] ?? 'N/A',
                    'issuer' => $certData['issuer']['CN'] ?? 'N/A',
                    'valid_from' => date('d/m/Y', $certData['validFrom_time_t']),
                    'valid_to' => date('d/m/Y', $certData['validTo_time_t']),
                    'is_valid' => time() < $certData['validTo_time_t'],
                    'days_remaining' => max(0, floor(($certData['validTo_time_t'] - time()) / 86400))
                ];
            }

            return null;

        } catch (\Exception $e) {
            log_message('error', 'Error reading certificate info');
            return null;
        }
    }
}
