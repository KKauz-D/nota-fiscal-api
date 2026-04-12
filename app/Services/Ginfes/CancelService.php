<?php

namespace App\Services\Ginfes;

use App\Enums\Environment;
use App\Services\Certificate\CertificateService;
use DOMDocument;
use Exception;

class CancelService
{
    public function __construct(
        private SoapService $soapService,
        private XmlService $xmlService,
        private CertificateService $certificateService,
    ) {}

    /**
     * Gera o XML bruto de cancelamento (namespace exato do GINFES).
     */
    public function gerarXmlCancelamento(string $cnpj, string $im, string $numeroNfse): string
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;

        $nsGinfes = 'http://www.ginfes.com.br/servico_cancelar_nfse_envio';
        $nsTipos  = 'http://www.ginfes.com.br/tipos';
        $nsDsig   = 'http://www.w3.org/2000/09/xmldsig#';

        $root = $dom->createElementNS($nsGinfes, 'CancelarNfseEnvio');
        $dom->appendChild($root);

        $prestador = $dom->createElement('Prestador');
        $prestador->appendChild($dom->createElementNS($nsTipos, 'Cnpj', preg_replace('/\D/', '', $cnpj)));
        $prestador->appendChild($dom->createElementNS($nsTipos, 'InscricaoMunicipal', preg_replace('/\D/', '', $im)));
        $root->appendChild($prestador);

        $root->appendChild($dom->createElement('NumeroNfse', $numeroNfse));

        // Placeholder da assinatura — será substituído por signXml
        $root->appendChild($dom->createElementNS($nsDsig, 'Signature'));

        return $dom->saveXML($root);
    }

    /**
     * Gera o XML, assina, valida XSD e envia via SOAP.
     *
     * @return array{success: bool, message: string, code: string, soap_response: ?string}
     */
    public function cancelarNota(
        string $cnpj,
        string $im,
        string $numeroNfse,
        array $certs,
        Environment $ambiente = Environment::Homolog,
    ): array {
        try {
            // 1. Gera XML base
            $xmlBruto = $this->gerarXmlCancelamento($cnpj, $im, $numeroNfse);

            // 2. Assina (sem Id, removendo placeholder)
            $xmlAssinado = $this->certificateService->signXml($xmlBruto, $certs, null, 'CancelarNfseEnvio');

            // 3. Validação XSD
            $xsdPath = config('ginfes.xsd_path') . '/servico_cancelar_nfse_envio_v02.xsd';
            if (file_exists($xsdPath)) {
                $vResult = $this->xmlService->validarXmlContraXsd($xmlAssinado, $xsdPath);
                if ($vResult !== true) {
                    throw new Exception('Erro na validação XSD do Cancelamento.');
                }
            }

            // 4. Envia via SOAP
            $resultStr = $this->soapService->cancelarNfse($xmlAssinado, $ambiente);

            // 5. Analisa a resposta
            return $this->parseResponse($resultStr);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'SYS_ERR',
                'soap_response' => null,
            ];
        }
    }

    private function parseResponse(mixed $resultStr): array
    {
        $success = false;
        $msgRetorno = 'Resposta da prefeitura recebida.';
        $codigo = '';

        if ($resultStr) {
            $dom = new DOMDocument();
            $dom->loadXML((string) $resultStr);

            $cancelNodes = $dom->getElementsByTagName('Sucesso');
            if ($cancelNodes->length > 0 && strtolower($cancelNodes->item(0)->nodeValue) === 'true') {
                $success = true;
                $msgRetorno = 'Nota fiscal cancelada com sucesso no GINFES.';
            }

            $mensagens = $dom->getElementsByTagName('MensagemRetorno');
            if ($mensagens->length > 0) {
                $codNode = $mensagens->item(0)->getElementsByTagName('Codigo')->item(0);
                $msgNode = $mensagens->item(0)->getElementsByTagName('Mensagem')->item(0);
                if ($codNode) $codigo = $codNode->nodeValue;
                if ($msgNode) {
                    $msgRetorno = $msgNode->nodeValue;
                    if ($codigo === 'E43') { // E43 = Nota já está cancelada
                        $success = true;
                    }
                }
            }
        }

        return [
            'success' => $success,
            'message' => $msgRetorno,
            'code' => $codigo,
            'soap_response' => $resultStr,
        ];
    }
}
