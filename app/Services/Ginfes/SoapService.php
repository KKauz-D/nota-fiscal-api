<?php

namespace App\Services\Ginfes;

use App\Enums\Environment;
use Exception;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

class SoapService
{
    private function getWsdl(Environment $ambiente): string
    {
        return $ambiente === Environment::Homolog
            ? config('ginfes.homolog_url')
            : config('ginfes.prod_url');
    }

    private function getSoapOptions(): array
    {
        return [
            'trace' => 1,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]),
        ];
    }

    private function getCabecalho(): string
    {
        $versao = config('ginfes.cabecalho_versao');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<ns2:cabecalho xmlns:ns2="http://www.ginfes.com.br/cabecalho_v03.xsd" versao="' . $versao . '">'
            . '<versaoDados>' . $versao . '</versaoDados>'
            . '</ns2:cabecalho>';
    }

    /**
     * Envia o lote de RPS (RecepcionarLoteRpsV4).
     */
    public function sendLoteRps(string $xmlAssinado, Environment $ambiente): mixed
    {
        return $this->call('RecepcionarLoteRpsV4', $xmlAssinado, $ambiente, withCabecalho: true);
    }

    /**
     * Consulta a situação de um lote (ConsultarSituacaoLoteRpsV3).
     */
    public function consultarSituacaoLoteRps(string $xmlConsulta, Environment $ambiente): mixed
    {
        return $this->call('ConsultarSituacaoLoteRpsV3', $xmlConsulta, $ambiente, withCabecalho: true);
    }

    /**
     * Consulta as notas geradas em um lote (ConsultarLoteRpsV3).
     */
    public function consultarLoteRps(string $xmlConsulta, Environment $ambiente): mixed
    {
        return $this->call('ConsultarLoteRpsV3', $xmlConsulta, $ambiente, withCabecalho: true);
    }

    /**
     * Cancela uma NFS-e (CancelarNfse — sem cabeçalho).
     */
    public function cancelarNfse(string $xmlAssinado, Environment $ambiente): mixed
    {
        return $this->call('CancelarNfse', $xmlAssinado, $ambiente, withCabecalho: false);
    }

    /**
     * Executa a chamada SOAP genérica.
     */
    private function call(string $method, string $xml, Environment $ambiente, bool $withCabecalho): mixed
    {
        $wsdl = $this->getWsdl($ambiente);

        try {
            $client = new SoapClient($wsdl, $this->getSoapOptions());

            if ($withCabecalho) {
                return $client->$method($this->getCabecalho(), $xml);
            }

            return $client->$method($xml);
        } catch (SoapFault $e) {
            Log::error("SOAP {$method} fault", ['message' => $e->getMessage(), 'ambiente' => $ambiente->value]);
            throw new Exception("Erro SOAP ao executar {$method}: " . $e->getMessage(), 0, $e);
        }
    }
}
