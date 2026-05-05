<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'numero_nfse' => $this->numero_nfse,
            'codigo_verificacao' => $this->codigo_verificacao,
            'data_emissao' => $this->data_emissao?->toIso8601String(),
            'tomador_nome' => $this->tomador_nome,
            'valor_servicos' => $this->valor_servicos,
            'cnpj' => $this->cnpj,
            'im' => $this->im,
            'status' => $this->status,
            'motivo_cancelamento' => $this->motivo_cancelamento,
            'pdf_baixado' => (bool) $this->pdf_baixado,
            'pdf_baixado_em' => $this->pdf_baixado_em?->toIso8601String(),
            'pdf_caminho' => $this->pdf_caminho,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
