<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'batch_id',
        'numero_nfse',
        'codigo_verificacao',
        'data_emissao',
        'tomador_nome',
        'valor_servicos',
        'cnpj',
        'im',
        'status',
        'motivo_cancelamento',
    ];

    protected function casts(): array
    {
        return [
            'data_emissao' => 'datetime',
            'valor_servicos' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function scopeByStatus($query, InvoiceStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCnpj($query, string $cnpj)
    {
        return $query->where('cnpj', $cnpj);
    }
}
