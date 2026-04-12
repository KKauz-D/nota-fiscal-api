<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Enums\Environment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = [
        'cnpj',
        'im',
        'numero_lote',
        'rps_count',
        'xml_file',
        'protocolo',
        'ambiente',
        'status',
        'dados_originais',
        'situacao_code',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'dados_originais' => 'array',
            'errors' => 'array',
            'rps_count' => 'integer',
            'situacao_code' => 'integer',
            'status' => BatchStatus::class,
            'ambiente' => Environment::class,
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'batch_id');
    }

    public function scopeByStatus($query, BatchStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCnpj($query, string $cnpj)
    {
        return $query->where('cnpj', $cnpj);
    }
}
