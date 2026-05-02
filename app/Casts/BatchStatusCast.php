<?php

namespace App\Casts;

use App\Enums\BatchStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class BatchStatusCast implements CastsAttributes
{
    /**
     * Uses tryFrom so unrecognised DB values never throw.
     * Falls back to BatchStatus::Transmitido if the stored value is invalid.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): BatchStatus
    {
        return BatchStatus::tryFrom((string) $value) ?? BatchStatus::Transmitido;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof BatchStatus) {
            return $value->value;
        }

        return BatchStatus::tryFrom((string) $value)?->value ?? BatchStatus::Transmitido->value;
    }
}
