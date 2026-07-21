<?php

namespace App\Controllers\Employees;

use App\Controllers\BaseController;
use App\Enums\DocumentType;
use App\Models\EmployeeDocumentModel;
use App\Models\EmployeeModel;
use App\Services\Employees\EmployeeControllerActionService;
use App\Services\Upload\SafeUploadService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Aba "Upload de Documentos" do cadastro de colaborador (RG, CPF, CNH,
 * certificados e outros complementares). Mesma checagem self-or-manager já
 * usada em EmployeeController::uploadPhoto()/photo(), e mesmo padrão de
 * upload/download com posse por registro de WarningController::downloadEvidence().
 */
class EmployeeDocumentController extends BaseController
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    protected EmployeeDocumentModel $documentModel;
    protected EmployeeModel $employeeModel;
    protected EmployeeControllerActionService $employeeControllerActionService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->documentModel = new EmployeeDocumentModel();
        $this->employeeModel = new EmployeeModel();
        $this->employeeControllerActionService = new EmployeeControllerActionService();
    }

    public function wizardStep(int $employeeId)
    {
        $this->requireAuth();

        $employee = $this->resolveViewableEmployee($employeeId);
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        return view('employees/documents_step', [
            'employee' => $employee,
            'documentsByType' => $this->documentModel->listByEmployee($employeeId),
            'documentTypes' => DocumentType::cases(),
        ]);
    }

    public function store(int $employeeId)
    {
        $access = $this->requireManageableEmployee($employeeId);
        if ($access instanceof ResponseInterface) {
            return $access;
        }

        $documentType = (string) $this->request->getPost('document_type');
        if (! in_array($documentType, DocumentType::values(), true)) {
            return $this->respondError('Tipo de documento inválido.', null, 422);
        }

        $files = $this->request->getFiles();
        $uploaded = $files['documents'] ?? [];
        if (! is_array($uploaded)) {
            $uploaded = [$uploaded];
        }

        $uploadService = new SafeUploadService();
        $allowedMimes = $uploadService->allowedMimesForGroups(['image_private', 'document_private']);
        $stored = [];
        $errors = [];

        foreach ($uploaded as $file) {
            if (! $file || ! $file->isValid() || $file->hasMoved()) {
                continue;
            }

            $result = $uploadService->storePrivate(
                $file,
                'employees/documents/' . $employeeId,
                self::ALLOWED_EXTENSIONS,
                $allowedMimes,
                self::MAX_FILE_BYTES
            );

            if (! ($result['success'] ?? false)) {
                $errors[] = $result['message'];
                continue;
            }

            $documentId = $this->documentModel->insert([
                'employee_id' => $employeeId,
                'document_type' => $documentType,
                'original_filename' => $result['file_name'],
                'stored_path' => $result['stored_path'],
                'mime_type' => $result['mime_type'],
                'file_size' => $result['file_size'],
                'uploaded_by' => (int) ($this->currentUser->id ?? 0),
            ]);

            if ($documentId === false) {
                $errors[] = 'Falha ao registrar o documento enviado.';
                continue;
            }

            $stored[] = $documentId;
        }

        if ($stored === [] && $errors !== []) {
            return $this->respondError(implode(' ', $errors), null, 422);
        }

        return $this->respondSuccess(
            ['stored' => count($stored), 'errors' => $errors],
            count($stored) . ' documento(s) enviado(s) com sucesso.'
        );
    }

    public function download(int $employeeId, int $documentId)
    {
        $access = $this->resolveViewableEmployee($employeeId);
        if ($access instanceof ResponseInterface) {
            return $access;
        }

        $document = $this->documentModel->where('employee_id', $employeeId)->find($documentId);
        if (! $document) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $uploadService = new SafeUploadService();
        $absolute = $uploadService->safeDownloadPath(
            WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, ltrim((string) $document->stored_path, '/\\'))
        );

        if ($absolute === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($absolute, null)->setFileName($document->original_filename);
    }

    public function delete(int $employeeId, int $documentId)
    {
        $access = $this->requireManageableEmployee($employeeId);
        if ($access instanceof ResponseInterface) {
            return $access;
        }

        $document = $this->documentModel->where('employee_id', $employeeId)->find($documentId);
        if (! $document) {
            return $this->respondError('Documento não encontrado.', null, 404);
        }

        // Soft delete apenas -- o arquivo físico permanece em disco por
        // retenção/auditoria, mesmo padrão já usado em employees/warnings.
        $this->documentModel->delete($documentId);

        return $this->respondSuccess([], 'Documento excluído com sucesso.');
    }

    /** Leitura: dono do registro ou manager com acesso ao departamento. */
    private function resolveViewableEmployee(int $employeeId): mixed
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (! $this->canAccessEmployeeRecord($employee)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $employee;
    }

    /** Escrita: dono do registro ou manager com escopo de departamento (uploadPhoto() usa o mesmo). */
    private function requireManageableEmployee(int $employeeId): mixed
    {
        $isSelf = isset($this->currentUser) && (int) ($this->currentUser->id ?? 0) === $employeeId;
        if (! $isSelf) {
            $this->requireManager();

            $access = $this->employeeControllerActionService->resolveManagerAccess($this->currentUser, $employeeId);
            if (! ($access['success'] ?? false)) {
                return $this->respondError($access['message'] ?? 'Você não tem permissão para gerenciar este colaborador.', null, 403);
            }

            return $access['employee'];
        }

        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return $this->respondError('Colaborador não encontrado.', null, 404);
        }

        return $employee;
    }
}
