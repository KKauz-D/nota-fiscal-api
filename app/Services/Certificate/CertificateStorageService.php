<?php

namespace App\Services\Certificate;

use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class CertificateStorageService
{
    private string $disk = 'certificates';

    public function storeCertificate(string $cnpj, string $pfxContent, string $password, string $im = ''): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        $filename = "cert_{$cnpj}.pfx";

        Storage::disk($this->disk)->put($filename, $pfxContent);

        $config = [
            'cert_file' => $filename,
            'cert_password' => Crypt::encryptString($password),
            'im' => $im,
            'saved_at' => now()->format('Y-m-d H:i:s'),
        ];

        Storage::disk($this->disk)->put("{$cnpj}.json", json_encode($config, JSON_PRETTY_PRINT));

        return $config;
    }

    public function getConfig(string $cnpj): ?array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        $path = "{$cnpj}.json";

        if (!Storage::disk($this->disk)->exists($path)) {
            return null;
        }

        return json_decode(Storage::disk($this->disk)->get($path), true) ?: null;
    }

    public function listAll(): array
    {
        $empresas = [];
        $files = Storage::disk($this->disk)->files();

        foreach ($files as $file) {
            if (!str_ends_with($file, '.json')) continue;
            $cnpj = basename($file, '.json');
            if (!ctype_digit($cnpj)) continue;

            $data = json_decode(Storage::disk($this->disk)->get($file), true) ?: [];
            $empresas[] = [
                'cnpj' => $cnpj,
                'cert_file' => $data['cert_file'] ?? '-',
                'saved_at' => $data['saved_at'] ?? '-',
            ];
        }

        return $empresas;
    }

    public function deleteCertificate(string $cnpj): void
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        $config = $this->getConfig($cnpj);

        if ($config && !empty($config['cert_file'])) {
            Storage::disk($this->disk)->delete($config['cert_file']);
        }

        Storage::disk($this->disk)->delete("{$cnpj}.json");
    }

    public function getCertPath(string $cnpj): ?string
    {
        $config = $this->getConfig($cnpj);
        if (empty($config['cert_file'])) return null;

        $disk = Storage::disk($this->disk);
        if (!$disk->exists($config['cert_file'])) return null;

        return $disk->path($config['cert_file']);
    }

    public function getCertContent(string $cnpj): ?string
    {
        $config = $this->getConfig($cnpj);
        if (empty($config['cert_file'])) return null;

        $disk = Storage::disk($this->disk);
        if (!$disk->exists($config['cert_file'])) return null;

        return $disk->get($config['cert_file']);
    }

    public function getPassword(string $cnpj): ?string
    {
        $config = $this->getConfig($cnpj);
        if (empty($config['cert_password'])) return null;

        return Crypt::decryptString($config['cert_password']);
    }
}
