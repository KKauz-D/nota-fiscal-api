<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\TransmitBatchRequest;
use App\Http\Resources\BatchResource;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Services\Ginfes\BatchSyncService;
use App\Services\Import\ExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BatchSyncService $batchSyncService,
        private ExcelImportService $excelImportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Batch::with('invoices')->orderByDesc('created_at');

        if ($cnpj = $request->query('cnpj')) {
            $query->where('cnpj', $cnpj);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $batches = $query->paginate($request->query('per_page', 20));

        return $this->success(BatchResource::collection($batches)->response()->getData(true));
    }

    /**
     * Preview: lê Excel, monta RPS com numeração temporária (não persiste).
     */
    public function preview(StoreBatchRequest $request): JsonResponse
    {
        $file = $request->file('excel_file');
        $cnpj = preg_replace('/\D/', '', $request->input('cnpj'));

        $tempPath = $file->store('temp', 'local');
        $fullPath = storage_path('app/private/' . $tempPath);

        try {
            $rawListaRps = $this->excelImportService->read($fullPath);
            $listaRps = $this->batchSyncService->prepareRpsNumbering($rawListaRps, $cnpj);

            return $this->success($listaRps, 'Preview gerado com sucesso.');
        } finally {
            @unlink($fullPath);
        }
    }

    /**
     * Transmite o lote de RPS (editado ou direto do Excel).
     */
    public function transmitir(TransmitBatchRequest $request): JsonResponse
    {
        $cnpj = preg_replace('/\D/', '', $request->input('cnpj'));
        $im = $request->input('im');
        $ambiente = $request->getEnvironment();

        // Resolve certificates
        $certs = $this->batchSyncService->resolveCerts($cnpj);

        // Get RPS list — either from edited JSON or from Excel file
        if ($request->has('edited_rps')) {
            $listaRps = $request->input('edited_rps');
        } else {
            $file = $request->file('excel_file');
            $tempPath = $file->store('temp', 'local');
            $fullPath = storage_path('app/private/' . $tempPath);

            try {
                $listaRps = $this->excelImportService->read($fullPath);
            } finally {
                @unlink($fullPath);
            }
        }

        $batch = $this->batchSyncService->transmitir($listaRps, $cnpj, $im, $certs, $ambiente);

        AuditLog::log(
            action: 'transmitir_lote',
            details: "Lote {$batch->numero_lote} transmitido com protocolo {$batch->protocolo}",
            ipAddress: $request->ip(),
        );

        return $this->success(new BatchResource($batch->load('invoices')), 'Lote transmitido com sucesso.', 201);
    }

    /**
     * Sincroniza status do lote com o GINFES.
     */
    public function sincronizar(Request $request, Batch $batch): JsonResponse
    {
        $certs = $this->batchSyncService->resolveCerts($batch->cnpj);
        $result = $this->batchSyncService->sincronizar($batch, $certs);

        AuditLog::log(
            action: 'sincronizar_lote',
            details: "Lote {$batch->numero_lote} sincronizado: {$result['status']}",
            ipAddress: $request->ip(),
        );

        return $this->success([
            'status' => $result['status'],
            'situacao_code' => $result['situacao_code'],
            'invoices_count' => $result['invoices_count'],
            'batch' => new BatchResource($batch->fresh()->load('invoices')),
        ], $result['status']);
    }

    /**
     * Reenvia um lote existente (retransmite os dados originais).
     */
    public function show(Request $request, Batch $batch): JsonResponse
    {
        return $this->success(array_merge(
            (new BatchResource($batch->load('invoices')))->toArray($request),
            ['dados_originais' => $batch->dados_originais ?? []]
        ));
    }

    /**
     * Reenvia um lote existente (retransmite os dados originais ou editados).
     */
    public function reenviar(Request $request, Batch $batch): JsonResponse
    {
        $dadosOriginais = $request->has('edited_rps')
            ? (is_array($request->input('edited_rps'))
                ? $request->input('edited_rps')
                : json_decode($request->input('edited_rps'), true))
            : ($batch->dados_originais ?? null);

        if (empty($dadosOriginais)) {
            return $this->error('Lote não possui dados originais para reenvio.', 422);
        }

        $certs = $this->batchSyncService->resolveCerts($batch->cnpj);
        $ambiente = $batch->ambiente;

        $newBatch = $this->batchSyncService->transmitir(
            $dadosOriginais,
            $batch->cnpj,
            $batch->im,
            $certs,
            $ambiente,
        );

        AuditLog::log(
            action: 'reenviar_lote',
            details: "Lote {$batch->numero_lote} reenviado como {$newBatch->numero_lote}, protocolo {$newBatch->protocolo}",
            ipAddress: $request->ip(),
        );

        return $this->success(new BatchResource($newBatch->load('invoices')), 'Lote reenviado com sucesso.', 201);
    }

    /**
     * Exclui um lote e suas notas.
     */
    public function destroy(Request $request, Batch $batch): JsonResponse
    {
        AuditLog::log(
            action: 'excluir_lote',
            details: "Lote {$batch->numero_lote} (protocolo {$batch->protocolo}) excluído",
            ipAddress: $request->ip(),
        );

        $batch->invoices()->delete();
        $batch->delete();

        return $this->success(message: 'Lote excluído com sucesso.');
    }
}
