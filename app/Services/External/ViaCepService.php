<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;

class ViaCepService
{
    public function consultar(string $cep): ?array
    {
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            return null;
        }

        $response = Http::timeout(10)
            ->withoutVerifying()
            ->get("https://viacep.com.br/ws/{$cep}/json/");

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['erro'])) {
                return null;
            }
            return $data;
        }

        return null;
    }
}
