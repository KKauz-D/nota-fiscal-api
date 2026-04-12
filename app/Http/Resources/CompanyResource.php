<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cnpj' => $this->resource['cnpj'] ?? null,
            'cert_file' => $this->resource['cert_file'] ?? null,
            'saved_at' => $this->resource['saved_at'] ?? null,
        ];
    }
}
