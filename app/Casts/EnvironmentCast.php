<?php

namespace App\Casts;

use App\Enums\Environment;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class EnvironmentCast implements CastsAttributes
{
    /**
     * Cast the given value using tryFrom so invalid DB values never throw.
     * Falls back to Environment::Homolog if the stored value is unrecognised.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Environment
    {
        return Environment::tryFrom((string) $value) ?? Environment::Homolog;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof Environment) {
            return $value->value;
        }

        return Environment::tryFrom((string) $value)?->value ?? Environment::Homolog->value;
    }
}
