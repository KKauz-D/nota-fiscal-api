<?php

namespace App\Http\Requests;

use App\Enums\Environment;
use Illuminate\Foundation\Http\FormRequest;

class TransmitBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string'],
            'im' => ['required', 'string'],
            'ambiente' => ['required', 'in:prod,homolog'],
            'edited_rps' => ['sometimes', 'array', 'min:1'],
            'excel_file' => ['required_without:edited_rps', 'file', 'mimes:xlsx,xls'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório.',
            'im.required' => 'A Inscrição Municipal é obrigatória.',
            'ambiente.required' => 'O ambiente é obrigatório (prod ou homolog).',
            'ambiente.in' => 'O ambiente deve ser "prod" ou "homolog".',
            'edited_rps.array' => 'Os RPS editados devem ser um array.',
            'edited_rps.min' => 'É necessário ao menos 1 RPS.',
            'excel_file.required_without' => 'O arquivo Excel é obrigatório quando não há RPS editados.',
            'excel_file.file' => 'O upload deve ser um arquivo válido.',
            'excel_file.mimes' => 'O arquivo deve ser do tipo .xlsx ou .xls.',
        ];
    }

    public function getEnvironment(): Environment
    {
        return $this->input('ambiente') === 'prod'
            ? Environment::Prod
            : Environment::Homolog;
    }
}
