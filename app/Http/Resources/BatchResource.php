<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cnpj' => $this->cnpj,
            'im' => $this->im,
            'numero_lote' => $this->numero_lote,
            'rps_count' => $this->rps_count,
            'xml_file' => $this->xml_file,
            'protocolo' => $this->protocolo,
            'ambiente' => $this->ambiente,
            'status' => $this->status,
            'situacao_code' => $this->situacao_code,
            'errors' => $this->errors,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
