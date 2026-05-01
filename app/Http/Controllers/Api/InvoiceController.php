<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\Ginfes\BatchSyncService;
use App\Services\Ginfes\CancelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CancelService $cancelService,
        private BatchSyncService $batchSyncService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with('batch')->orderByDesc('created_at');

        if ($cnpj = $request->query('cnpj')) {
            $query->where('cnpj', $cnpj);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $invoices = $query->paginate($request->query('per_page', 20));

        return $this->success(InvoiceResource::collection($invoices)->response()->getData(true));
    }

    public function cancelar(CancelInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $certs = $this->batchSyncService->resolveCerts($invoice->cnpj);
        $ambiente = $invoice->batch->ambiente;

        $result = $this->cancelService->cancelarNota(
            $invoice->cnpj,
            $invoice->im,
            $invoice->numero_nfse,
            $certs,
            $ambiente,
        );

        if ($result['success']) {
            $invoice->update([
                'status' => 'cancelada',
                'motivo_cancelamento' => $request->input('motivo', $result['message']),
            ]);

            AuditLog::log(
                action: 'cancelar_nota',
                details: "NFS-e {$invoice->numero_nfse} cancelada: {$result['message']}",
                ipAddress: $request->ip(),
            );
        } elseif (($result['code'] ?? null) === 'E79') {
            // Já cancelada no GINFES — sincroniza o banco local
            $invoice->update(['status' => 'cancelada']);
        }

        return $result['success']
            ? $this->success(new InvoiceResource($invoice->fresh()), $result['message'])
            : $this->error($result['message'], 422, ['data' => ['code' => $result['code']]]);
    }
}
