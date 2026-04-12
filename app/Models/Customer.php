<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'cpf_cnpj',
        'razao_social',
        'endereco',
        'numero',
        'bairro',
        'cod_mun',
        'uf',
        'cep',
        'email',
        'telefone',
    ];

    public function getFormattedCpfCnpjAttribute(): string
    {
        $value = $this->cpf_cnpj;

        if (strlen($value) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $value);
        }

        if (strlen($value) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $value);
        }

        return $value;
    }
}
