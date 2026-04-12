<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;

class BrasilApiService
{
    public function consultarCnpj(string $cnpj): ?array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        $response = Http::timeout(10)
            ->withoutVerifying()
            ->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");

        return $response->successful() ? $response->json() : null;
    }
}
