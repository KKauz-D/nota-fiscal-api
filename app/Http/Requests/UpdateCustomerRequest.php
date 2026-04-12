<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cpf_cnpj' => ['sometimes', 'string', 'min:11', 'max:14'],
            'razao_social' => ['sometimes', 'string', 'max:115'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'cod_mun' => ['nullable', 'string', 'max:10'],
            'uf' => ['nullable', 'string', 'size:2'],
            'cep' => ['nullable', 'string', 'max:8'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'cpf_cnpj.min' => 'O CPF/CNPJ deve ter no mínimo 11 caracteres.',
            'cpf_cnpj.max' => 'O CPF/CNPJ deve ter no máximo 14 caracteres.',
            'razao_social.max' => 'A razão social deve ter no máximo 115 caracteres.',
            'uf.size' => 'A UF deve ter exatamente 2 caracteres.',
            'cep.max' => 'O CEP deve ter no máximo 8 caracteres.',
            'email.email' => 'O e-mail informado não é válido.',
        ];
    }
}
