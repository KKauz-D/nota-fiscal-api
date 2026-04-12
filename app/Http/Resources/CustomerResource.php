<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cpf_cnpj' => $this->cpf_cnpj,
            'cpf_cnpj_formatado' => $this->formatted_cpf_cnpj,
            'razao_social' => $this->razao_social,
            'endereco' => $this->endereco,
            'numero' => $this->numero,
            'bairro' => $this->bairro,
            'cod_mun' => $this->cod_mun,
            'uf' => $this->uf,
            'cep' => $this->cep,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
