<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'details',
        'ip_address',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function log(
        string $action,
        string $details = '',
        string $result = 'ok',
        ?int $userId = null,
        ?string $userName = null,
        ?string $ipAddress = null,
    ): static {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'user_name' => $userName ?? auth()->user()?->username ?? 'system',
            'action' => $action,
            'details' => $details,
            'ip_address' => $ipAddress ?? request()->ip(),
            'result' => $result,
        ]);
    }
}
