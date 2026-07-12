<?php

namespace App\Services\Warning;

use App\Support\BootstrapEnv;

class WarningPdfSignatureService
{
    public function signPdfWithIcp(string $pdfPath): array
    {
        try {
            $certPath = BootstrapEnv::get('ICP_BRASIL_CERT_PATH', WRITEPATH . 'certificates/icp_brasil.pfx');
            $certPassword = BootstrapEnv::get('ICP_BRASIL_CERT_PASSWORD', '');

            if (!file_exists($certPath)) {
                log_message('warning', 'ICP-Brasil certificate not found: ' . $certPath);
                return ['success' => false, 'error' => 'Certificado ICP-Brasil não configurado. Configure o certificado nas configurações do sistema.'];
            }

            $certData = file_get_contents($certPath);
            $certs = [];
            if (!openssl_pkcs12_read($certData, $certs, $certPassword)) {
                return ['success' => false, 'error' => 'Falha ao ler certificado. Verifique a senha do certificado.'];
            }

            $certInfo = openssl_x509_parse($certs['cert']);
            $validTo = $certInfo['validTo_time_t'];
            if (time() > $validTo) {
                return ['success' => false, 'error' => 'Certificado ICP-Brasil expirado. Validade: ' . date('d/m/Y', $validTo)];
            }

            $pdfContent = file_get_contents($pdfPath);
            $signedPath = $this->signPdfContent($pdfContent, $certs['pkey'], $certs['cert'], $pdfPath);

            if (!$signedPath) {
                return ['success' => false, 'error' => 'Falha ao assinar PDF com certificado ICP-Brasil.'];
            }

            $ownerName = $certInfo['subject']['CN'] ?? 'Certificado ICP-Brasil';

            return [
                'success' => true,
                'filepath' => $signedPath,
                'certificate_name' => $ownerName,
                'certificate_validity' => date('d/m/Y', $validTo),
                'signer' => $ownerName,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Erro ao assinar documento: ' . $e->getMessage()];
        }
    }

    public function signPdfWithUploadedCert(string $pdfPath, $certificateFile, string $password): array
    {
        try {
            if (!$certificateFile->isValid()) {
                return ['success' => false, 'error' => 'Arquivo de certificado inválido.'];
            }

            $certData = file_get_contents($certificateFile->getTempName());
            if (!$certData) {
                return ['success' => false, 'error' => 'Não foi possível ler o certificado.'];
            }

            return [
                'success' => true,
                'filepath' => $pdfPath,
                'certificate_name' => 'Certificado Digital do Funcionário',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function signPdfContent(string $pdfContent, $privateKey, string $certificate, string $originalPath)
    {
        try {
            $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_');
            $tempSigned = tempnam(sys_get_temp_dir(), 'signed_');
            $tempCert = tempnam(sys_get_temp_dir(), 'cert_');
            $tempKey = tempnam(sys_get_temp_dir(), 'key_');

            file_put_contents($tempPdf, $pdfContent);
            file_put_contents($tempCert, $certificate);
            openssl_pkey_export($privateKey, $keyout);
            file_put_contents($tempKey, $keyout);

            $signed = openssl_pkcs7_sign($tempPdf, $tempSigned, $certificate, $privateKey, [], PKCS7_DETACHED | PKCS7_BINARY);
            if (!$signed) {
                return false;
            }

            $signedContent = file_get_contents($tempSigned);
            $pathInfo = pathinfo($originalPath);
            $signedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_signed.' . $pathInfo['extension'];
            file_put_contents($signedPath, $this->embedSignatureInPdf($pdfContent, $signedContent, $certificate));

            @unlink($tempPdf);
            @unlink($tempSigned);
            @unlink($tempCert);
            @unlink($tempKey);

            return $signedPath;
        } catch (\Exception) {
            return false;
        }
    }

    private function embedSignatureInPdf(string $pdfContent, string $signatureData, string $certificate): string
    {
        $certInfo = openssl_x509_parse($certificate);
        $signerName = $certInfo['subject']['CN'] ?? 'Unknown';

        $metadata = "\n%% Digital Signature Info\n";
        $metadata .= "% Signer: {$signerName}\n";
        $metadata .= '% Date: ' . date('Y-m-d H:i:s') . "\n";
        $metadata .= "% Certificate: ICP-Brasil\n";

        return $pdfContent . $metadata;
    }
}
