<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Emitida = 'emitida';
    case Cancelada = 'cancelada';
    case Erro = 'erro';
}
