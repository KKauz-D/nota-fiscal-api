<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RpsControl extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $table = 'rps_controls';

    protected $fillable = [
        'cnpj',
        'serie',
        'ultimo_numero',
    ];

    protected function casts(): array
    {
        return [
            'ultimo_numero' => 'integer',
        ];
    }

    public static function getNextAndIncrement(string $cnpj, string $serie = 'A'): int
    {
        return DB::transaction(function () use ($cnpj, $serie) {
            $control = static::where('cnpj', $cnpj)
                ->where('serie', $serie)
                ->lockForUpdate()
                ->first();

            if (!$control) {
                $control = static::create([
                    'cnpj' => $cnpj,
                    'serie' => $serie,
                    'ultimo_numero' => 0,
                ]);
            }

            $next = $control->ultimo_numero + 1;

            static::where('cnpj', $cnpj)
                ->where('serie', $serie)
                ->update(['ultimo_numero' => $next]);

            return $next;
        });
    }

    /**
     * Retorna o próximo número sem incrementar (para preview).
     */
    public static function getNext(string $cnpj, string $serie = 'A'): int
    {
        $control = static::where('cnpj', $cnpj)->where('serie', $serie)->first();
        return $control ? $control->ultimo_numero + 1 : 1;
    }

    /**
     * Garante que a sequência está pelo menos no valor informado.
     */
    public static function ensureSequence(string $cnpj, string $serie, int $currentNumero): void
    {
        $control = static::where('cnpj', $cnpj)->where('serie', $serie)->first();
        $last = $control ? $control->ultimo_numero : 0;

        if ($currentNumero > $last) {
            static::updateOrCreate(
                ['cnpj' => $cnpj, 'serie' => $serie],
                ['ultimo_numero' => $currentNumero],
            );
        }
    }
}
