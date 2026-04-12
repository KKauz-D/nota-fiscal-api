<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Batch;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $cnpj = $request->query('cnpj');

        $batchesQuery = Batch::query();
        $invoicesQuery = Invoice::query();

        if ($cnpj) {
            $batchesQuery->where('cnpj', $cnpj);
            $invoicesQuery->where('cnpj', $cnpj);
        }

        $totalLotes = (clone $batchesQuery)->count();
        $totalNotas = (clone $invoicesQuery)->count();
        $notasEmitidas = (clone $invoicesQuery)->where('status', 'Emitida')->count();
        $notasCanceladas = (clone $invoicesQuery)->where('status', 'Cancelada')->count();
        $valorTotal = (clone $invoicesQuery)->where('status', 'Emitida')->sum('valor_servicos');

        // Últimos 10 lotes
        $ultimosLotes = (clone $batchesQuery)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'numero_lote', 'status', 'rps_count', 'protocolo', 'created_at']);

        return $this->success([
            'total_lotes' => $totalLotes,
            'total_notas' => $totalNotas,
            'notas_emitidas' => $notasEmitidas,
            'notas_canceladas' => $notasCanceladas,
            'valor_total' => number_format($valorTotal, 2, '.', ''),
            'ultimos_lotes' => $ultimosLotes,
        ]);
    }
}
