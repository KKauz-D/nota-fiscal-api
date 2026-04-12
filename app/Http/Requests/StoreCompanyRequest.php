<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', 'size:14'],
            'pfx_file' => ['required', 'file'],
            'pfx_password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório.',
            'cnpj.size' => 'O CNPJ deve ter exatamente 14 dígitos.',
            'pfx_file.required' => 'O arquivo do certificado (.pfx) é obrigatório.',
            'pfx_file.file' => 'O certificado deve ser um arquivo válido.',
            'pfx_password.required' => 'A senha do certificado é obrigatória.',
        ];
    }
}
