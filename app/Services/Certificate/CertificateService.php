<?php

namespace App\Services\Certificate;

use DOMDocument;
use DOMXPath;
use Exception;

class CertificateService
{
    /**
     * Extrai a chave privada e o certificado de um arquivo PFX.
     *
     * @param string $pfxContent Conteúdo binário do arquivo .pfx
     * @param string $password   Senha do certificado
     * @return array ['pkey' => string, 'cert' => string]
     */
    public function extractCerts(string $pfxContent, string $password): array
    {
        $certs = [];

        if (!openssl_pkcs12_read($pfxContent, $certs, $password)) {
            throw new Exception('Falha ao extrair certificado. Verifique a senha. Detalhe: ' . openssl_error_string());
        }

        return [
            'pkey' => $certs['pkey'],
            'cert' => $certs['cert'],
        ];
    }

    /**
     * Assina digitalmente um XML no padrão XMLDSig (SHA1).
     * Preserva a lógica exata do sistema legado para compatibilidade com GINFES.
     */
    public function signXml(string $xml, array $certs, ?string $id = null, ?string $rootTag = null): string
    {
        $privateKey = $certs['pkey'];
        $publicCert = $certs['cert'];
        $x509Cert = str_replace(["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n", " "], '', $publicCert);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $nsDSIG = 'http://www.w3.org/2000/09/xmldsig#';

        if ($id) {
            $nodeToSign = $xpath->query("//*[@Id='$id']")->item(0);
            $referenceURI = "#$id";
            if (!$nodeToSign) throw new Exception("Elemento com Id='$id' não encontrado para assinatura.");
        } elseif ($rootTag) {
            $xpath->registerNamespace('ds', $nsDSIG);
            $oldSig = $xpath->query('//ds:Signature')->item(0);
            if ($oldSig) {
                $oldSig->parentNode->removeChild($oldSig);
            }
            $nodeToSign = $dom->documentElement;
            $referenceURI = '';
        } else {
            $nodeToSign = $dom->documentElement;
            $referenceURI = '';
        }

        $canonicalized = $nodeToSign->C14N(true, false);
        $digestValue = base64_encode(hash('sha1', $canonicalized, true));

        $signature = $dom->createElementNS($nsDSIG, 'Signature');
        $signedInfo = $dom->createElementNS($nsDSIG, 'SignedInfo');

        $canonMethod = $dom->createElementNS($nsDSIG, 'CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');

        $sigMethod = $dom->createElementNS($nsDSIG, 'SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');

        $reference = $dom->createElementNS($nsDSIG, 'Reference');
        $reference->setAttribute('URI', $referenceURI);

        $transforms = $dom->createElementNS($nsDSIG, 'Transforms');
        $t1 = $dom->createElementNS($nsDSIG, 'Transform');
        $t1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $t2 = $dom->createElementNS($nsDSIG, 'Transform');
        $t2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($t1);
        $transforms->appendChild($t2);

        $digestMethod = $dom->createElementNS($nsDSIG, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/sha1');

        $reference->appendChild($transforms);
        $reference->appendChild($digestMethod);
        $reference->appendChild($dom->createElementNS($nsDSIG, 'DigestValue', $digestValue));

        $signedInfo->appendChild($canonMethod);
        $signedInfo->appendChild($sigMethod);
        $signedInfo->appendChild($reference);

        $signature->appendChild($signedInfo);

        $privateKeyRes = openssl_pkey_get_private($privateKey);
        if (!$privateKeyRes) throw new Exception('Falha ao carregar a chave privada para assinatura.');

        openssl_sign($signedInfo->C14N(true, false), $signatureValue, $privateKeyRes, OPENSSL_ALGO_SHA1);

        $signature->appendChild($dom->createElementNS($nsDSIG, 'SignatureValue', base64_encode($signatureValue)));

        $keyInfo = $dom->createElementNS($nsDSIG, 'KeyInfo');
        $x509Data = $dom->createElementNS($nsDSIG, 'X509Data');
        $x509Data->appendChild($dom->createElementNS($nsDSIG, 'X509Certificate', $x509Cert));
        $keyInfo->appendChild($x509Data);
        $signature->appendChild($keyInfo);

        $dom->documentElement->appendChild($signature);

        return $dom->saveXML();
    }
}
