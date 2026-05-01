<?php

namespace App\Enums;

enum BatchStatus: string
{
    case Transmitido = 'Transmitido';
    case ProcessadoSucesso = 'Processado com Sucesso';
    case ErroProcessamento = 'Erro de Processamento';
    case NaoProcessado = 'Nao Processado';
    case NfseGerada = 'NFSe Gerada';
}
