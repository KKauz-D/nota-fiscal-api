<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Services\Certificate\CertificateService;
use App\Services\Certificate\CertificateStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CertificateStorageService $certificateStorage,
        private CertificateService $certificateService,
    ) {}

    public function index(): JsonResponse
    {
        $companies = $this->certificateStorage->listAll();

        return $this->success(CompanyResource::collection($companies));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $cnpj = preg_replace('/\D/', '', $request->input('cnpj'));
        $im = $request->input('im', '');
        $password = $request->input('pfx_password');
        $file = $request->file('pfx_file');

        // Validate the PFX
        $pfxContent = file_get_contents($file->getRealPath());
        $this->certificateService->extractCerts($pfxContent, $password);

        // Store certificate and config
        $this->certificateStorage->storeCertificate($cnpj, $pfxContent, $password, $im);

        AuditLog::log(
            action: 'salvar_certificado',
            details: "Certificado salvo para CNPJ {$cnpj}",
            ipAddress: $request->ip(),
        );

        return $this->success(
            $this->certificateStorage->getConfig($cnpj),
            'Empresa/certificado salvo com sucesso.',
            201,
        );
    }

    public function destroy(Request $request, string $cnpj): JsonResponse
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        $this->certificateStorage->deleteCertificate($cnpj);

        AuditLog::log(
            action: 'excluir_certificado',
            details: "Certificado removido para CNPJ {$cnpj}",
            ipAddress: $request->ip(),
        );

        return $this->success(message: 'Empresa/certificado removido com sucesso.');
    }
}
