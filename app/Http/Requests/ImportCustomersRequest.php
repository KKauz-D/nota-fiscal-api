<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ];
    }

    public function messages(): array
    {
        return [
            'excel_file.required' => 'O arquivo de importação é obrigatório.',
            'excel_file.file' => 'O upload deve ser um arquivo válido.',
            'excel_file.mimes' => 'O arquivo deve ser do tipo .xlsx, .xls ou .csv.',
        ];
    }
}
