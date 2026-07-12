<?php

namespace App\Services\Timesheet\Endpoint;

use App\Services\Timesheet\TimePunchFlowService;
use CodeIgniter\HTTP\RequestInterface;

class TimePunchEndpointPunchProcessor
{
    public function __construct(
        private readonly TimePunchFlowService $flowService,
        private readonly TimePunchEndpointInputResolver $inputResolver = new TimePunchEndpointInputResolver(),
        private readonly TimePunchEndpointResultFactory $resultFactory = new TimePunchEndpointResultFactory(),
        private readonly ?TimePunchEndpointFaceGuard $faceGuard = null,
    ) {
    }

    public function dispatchPunch(RequestInterface $request, string $uri): array
    {
        $method = $this->inputResolver->resolvePunchMethod($request);

        if ($this->inputResolver->input($request, 'unique_code') !== null) {
            return $this->handleCodePunch($request);
        }

        if ($this->inputResolver->input($request, 'cpf') !== null) {
            return $this->handleCpfPunch($request);
        }

        if ($this->inputResolver->input($request, 'qr_data', 'token') !== null) {
            return $this->handleQrPunch($request);
        }

        if ($request->getFile('face_image') || $method === 'facial') {
            return $this->handleFacePunch($request, $uri);
        }

        if ($method === 'biometria') {
            return $this->handleFingerprintPunch($request);
        }

        return $this->resultFactory->error('Método de registro inválido. Utilize os endpoints específicos.', 400);
    }

    public function handleCodePunch(RequestInterface $request): array
    {
        return $this->flowService->handleCodePunch(
            $request,
            (string) ($this->inputResolver->input($request, 'unique_code') ?? ''),
            $this->inputResolver->resolvePunchType($request)
        );
    }

    public function handleCpfPunch(RequestInterface $request): array
    {
        return $this->flowService->handleCpfPunch(
            $request,
            (string) ($this->inputResolver->input($request, 'cpf') ?? ''),
            $this->inputResolver->resolvePunchType($request)
        );
    }

    public function handleQrPunch(RequestInterface $request): array
    {
        return $this->flowService->handleQrPunch(
            $request,
            (string) ($this->inputResolver->input($request, 'qr_data', 'token') ?? ''),
            $this->inputResolver->resolvePunchType($request)
        );
    }

    public function handleFacePunch(RequestInterface $request, string $uri): array
    {
        $guardError = $this->resolveFaceGuard()->validate($request, $uri);
        if ($guardError !== null) {
            return $guardError;
        }

        return $this->flowService->handleFacialPunch(
            $request,
            (string) ($this->inputResolver->input($request, 'photo', 'face_image') ?? ''),
            $this->inputResolver->resolvePunchType($request),
            [
                'latitude' => $this->inputResolver->input($request, 'latitude', 'location_lat'),
                'longitude' => $this->inputResolver->input($request, 'longitude', 'location_lng'),
            ]
        );
    }

    public function handleFingerprintPunch(RequestInterface $request): array
    {
        return $this->flowService->handleFingerprintPunch(
            $request,
            (string) ($this->inputResolver->input($request, 'template') ?? ''),
            $this->inputResolver->resolvePunchType($request)
        );
    }

    private function resolveFaceGuard(): TimePunchEndpointFaceGuard
    {
        return $this->faceGuard ?? new TimePunchEndpointFaceGuard($this->flowService, $this->resultFactory);
    }
}
