<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string'],
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório.',
            'excel_file.required' => 'O arquivo Excel é obrigatório.',
            'excel_file.file' => 'O upload deve ser um arquivo válido.',
            'excel_file.mimes' => 'O arquivo deve ser do tipo .xlsx ou .xls.',
        ];
    }
}
