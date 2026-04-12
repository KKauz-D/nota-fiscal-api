<?php

namespace App\Services\Ginfes;

use App\Enums\BatchStatus;
use App\Enums\Environment;
use App\Enums\InvoiceStatus;
use App\Models\Batch;
use App\Models\Invoice;
use App\Models\RpsControl;
use App\Services\Certificate\CertificateService;
use App\Services\Certificate\CertificateStorageService;
use App\Services\Import\CnaeService;
use DOMDocument;
use DOMElement;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BatchSyncService
{
    public function __construct(
        private XmlService $xmlService,
        private SoapService $soapService,
        private CertificateService $certificateService,
        private CertificateStorageService $certificateStorage,
        private CnaeService $cnaeService,
    ) {}

    /**
     * Transmite um lote de RPS para o GINFES.
     *
     * @param  array  $listaRps  Array de RPS já formatados
     * @param  string $cnpj
     * @param  string $im        Inscrição Municipal
     * @param  array  $certs     Array ['privKey' => ..., 'pubKey' => ..., 'certContent' => ...]
     * @param  Environment $ambiente
     * @return Batch  O lote salvo no banco
     */
    public function transmitir(array $listaRps, string $cnpj, string $im, array $certs, Environment $ambiente): Batch
    {
        // Enrich RPS with tax data from CSV if missing
        $listaRps = $this->enrichRpsWithTaxData($listaRps);
        $loteId = 'Lote_' . date('YmdHis');
        $loteData = [
            'id' => $loteId,
            'numeroLote' => substr((string) time(), -10),
            'cnpj' => $cnpj,
            'inscricaoMunicipal' => $im,
        ];

        // Garantir sequência de RPS
        foreach ($listaRps as $item) {
            $rpsNum = $item['InfRps']['IdentificacaoRps']['Numero'] ?? null;
            $rpsSerie = $item['InfRps']['IdentificacaoRps']['Serie'] ?: 'A';
            if ($rpsNum) {
                RpsControl::ensureSequence($cnpj, $rpsSerie, (int) $rpsNum);
            }
        }

        // Gerar e assinar XML
        $xmlBruto = $this->xmlService->generateLoteRps($loteData, $listaRps);
        $xmlAssinado = $this->certificateService->signXml($xmlBruto, $certs, $loteId);

        // Validar XSD
        $xsdPath = config('ginfes.xsd_path') . '/servico_enviar_lote_rps_envio_v04.xsd';
        if (file_exists($xsdPath)) {
            $vResult = $this->xmlService->validarXmlContraXsd($xmlAssinado, $xsdPath);
            if ($vResult !== true) {
                throw new Exception('Erro de validação XSD: ' . implode('; ', $vResult));
            }
        }

        // Enviar via SOAP
        $response = $this->soapService->sendLoteRps($xmlAssinado, $ambiente);
        $protocolo = $this->extractProtocolo($response);

        // Salvar XML no disco
        $xmlFileName = "lote_rps_{$loteId}.xml";
        Storage::disk('xml')->put($xmlFileName, $xmlAssinado);

        // Inserir lote no banco
        return Batch::create([
            'cnpj' => $cnpj,
            'im' => $im,
            'numero_lote' => $loteData['numeroLote'],
            'rps_count' => count($listaRps),
            'xml_file' => $xmlFileName,
            'protocolo' => $protocolo,
            'ambiente' => $ambiente,
            'status' => BatchStatus::Transmitido,
            'dados_originais' => $listaRps,
        ]);
    }

    /**
     * Sincroniza o status de um lote com o GINFES.
     * 1) ConsultarSituacaoLoteRps
     * 2) Se processado (4), ConsultarLoteRps e salva notas
     *
     * @return array{status: string, situacao_code: int, invoices_count: int}
     */
    public function sincronizar(Batch $batch, array $certs): array
    {
        $cnpj = $batch->cnpj;
        $im = $batch->im;
        $protocolo = $batch->protocolo;
        $ambiente = $batch->ambiente;

        // 1. Consultar situação
        $xmlSituacao = $this->certificateService->signXml(
            $this->xmlService->generateConsultarSituacao($cnpj, $im, $protocolo),
            $certs,
        );

        $resSitStr = (string) $this->soapService->consultarSituacaoLoteRps($xmlSituacao, $ambiente);

        $situacaoCode = 0;
        $statusText = 'Consultado';

        if (preg_match('/<(?:\w+:)?Situacao>(\d+)<\/(?:\w+:)?Situacao>/', $resSitStr, $m)) {
            $situacaoCode = (int) $m[1];
            $statusText = match ($situacaoCode) {
                1 => 'Não Recebido',
                2 => 'Não Processado',
                3 => 'Erro de Processamento',
                4 => 'Processado com Sucesso',
                default => "Situação {$situacaoCode}",
            };
        }

        $invoicesCount = 0;
        $errors = [];

        // Verificar erros na resposta de situação (strip namespaces para SimpleXML)
        $cleanXml = preg_replace('/(<\/?)([a-zA-Z0-9]+):/', '$1', $resSitStr);
        $xml = @simplexml_load_string($cleanXml);

        if ($xml && isset($xml->ListaMensagemRetorno->MensagemRetorno)) {
            foreach ($xml->ListaMensagemRetorno->MensagemRetorno as $msg) {
                $error = [
                    'codigo' => trim((string) $msg->Codigo),
                    'mensagem' => trim((string) $msg->Mensagem),
                ];
                if (isset($msg->Correcao)) {
                    $error['correcao'] = html_entity_decode(trim((string) $msg->Correcao));
                }
                $errors[] = $error;
            }
        }

        // 2. Se processado com sucesso, buscar notas
        if ($situacaoCode === 4) {
            $xmlLote = $this->certificateService->signXml(
                $this->xmlService->generateConsultarLoteRps($cnpj, $im, $protocolo),
                $certs,
            );

            $resLoteStr = (string) $this->soapService->consultarLoteRps($xmlLote, $ambiente);
            $statusText = 'NFSe Gerada';

            $invoicesCount = $this->parseAndSaveInvoices($batch, $cnpj, $im, $resLoteStr);
        }

        // 3. Atualizar lote
        $batch->update([
            'status' => $this->mapSituacaoToStatus($situacaoCode),
            'situacao_code' => $situacaoCode,
            'errors' => !empty($errors) ? $errors : null,
        ]);

        return [
            'status' => $statusText,
            'situacao_code' => $situacaoCode,
            'invoices_count' => $invoicesCount,
        ];
    }

    /**
     * Prepara RPS com numeração automática (para preview).
     * NÃO persiste a numeração no BD.
     */
    public function prepareRpsNumbering(array $rawListaRps, string $cnpj): array
    {
        $listaRps = [];
        $tempCounters = [];

        foreach ($rawListaRps as $item) {
            $rpsNum = $item['InfRps']['IdentificacaoRps']['Numero'] ?? null;
            $rpsSerie = $item['InfRps']['IdentificacaoRps']['Serie'] ?: 'A';

            if (empty($rpsNum)) {
                $key = $cnpj . '_' . $rpsSerie;
                if (! isset($tempCounters[$key])) {
                    $tempCounters[$key] = RpsControl::getNext($cnpj, $rpsSerie);
                }
                $rpsNum = $tempCounters[$key];
                $tempCounters[$key]++;
                $item['InfRps']['IdentificacaoRps']['Numero'] = $rpsNum;
            }

            $listaRps[] = $item;
        }

        return $listaRps;
    }

    /**
     * Resolve os certificados a partir do CNPJ (certificado salvo).
     */
    public function resolveCerts(string $cnpj): array
    {
        $config = $this->certificateStorage->getConfig($cnpj);

        if (! $config || empty($config['cert_file'])) {
            throw new Exception("Nenhum certificado salvo para o CNPJ {$cnpj}.");
        }

        $pfxContent = $this->certificateStorage->getCertContent($cnpj);
        $password = $this->certificateStorage->getPassword($cnpj);

        return $this->certificateService->extractCerts($pfxContent, $password);
    }

    private function extractProtocolo(mixed $response): string
    {
        if (is_string($response)) {
            if (preg_match('/<Protocolo>(.*?)<\/Protocolo>/', $response, $m)) {
                return $m[1];
            }
            return 'Não transmitido';
        }

        if (is_object($response) && isset($response->Protocolo)) {
            return (string) $response->Protocolo;
        }

        return 'Não transmitido';
    }

    private function parseAndSaveInvoices(Batch $batch, string $cnpj, string $im, string $xmlResponse): int
    {
        // Limpa notas anteriores deste lote (re-sync)
        $batch->invoices()->delete();

        $dom = new DOMDocument();
        if (! @$dom->loadXML($xmlResponse)) {
            return 0;
        }

        $count = 0;
        $nfseList = $dom->getElementsByTagName('InfNfse');

        foreach ($nfseList as $inf) {
            if (! ($inf instanceof DOMElement)) continue;

            $num = $this->getTagValue($inf, 'Numero');
            if (empty($num)) continue;

            $tomador = $inf->getElementsByTagName('TomadorServico')->item(0);
            $razao = ($tomador instanceof DOMElement)
                ? $this->getTagValue($tomador, 'RazaoSocial')
                : '';

            $servico = $inf->getElementsByTagName('Servico')->item(0);
            $valores = ($servico instanceof DOMElement)
                ? $servico->getElementsByTagName('Valores')->item(0)
                : null;
            $valor = ($valores instanceof DOMElement)
                ? ($this->getTagValue($valores, 'ValorServicos') ?: 0)
                : 0;

            Invoice::create([
                'batch_id' => $batch->id,
                'numero_nfse' => $num,
                'codigo_verificacao' => $this->getTagValue($inf, 'CodigoVerificacao'),
                'data_emissao' => $this->getTagValue($inf, 'DataEmissao'),
                'tomador_nome' => $razao,
                'valor_servicos' => $valor,
                'cnpj' => $cnpj,
                'im' => $im,
                'status' => InvoiceStatus::Emitida,
            ]);

            $count++;
        }

        return $count;
    }

    private function mapSituacaoToStatus(int $code): BatchStatus
    {
        return match ($code) {
            4 => BatchStatus::ProcessadoSucesso,
            3 => BatchStatus::ErroProcessamento,
            2 => BatchStatus::NaoProcessado,
            default => BatchStatus::Transmitido,
        };
    }

    private function getTagValue(DOMElement $element, string $tag): string
    {
        $node = $element->getElementsByTagName($tag)->item(0);
        return $node ? $node->nodeValue : '';
    }

    /**
     * Enrich RPS items with tax data (NBS, IbsCbs) from CSV lookup.
     */
    private function enrichRpsWithTaxData(array $listaRps): array
    {
        foreach ($listaRps as &$item) {
            $codTribMun = $item['InfRps']['Servico']['CodigoTributacaoMunicipio'] ?? '';
            if (empty($codTribMun)) {
                continue;
            }

            $taxData = $this->cnaeService->lookup($codTribMun);
            if (! $taxData) {
                continue;
            }

            // Fill CodigoNbs if empty
            if (empty($item['InfRps']['Servico']['CodigoNbs']) && ! empty($taxData['cod_nbs'])) {
                $item['InfRps']['Servico']['CodigoNbs'] = $taxData['cod_nbs'];
            }

            // Fill IbsCbs fields if missing or default
            $ibsCbs = $item['InfRps']['IbsCbs'] ?? [];

            if (empty($ibsCbs['CodigoIndicadorOperacao']) || $ibsCbs['CodigoIndicadorOperacao'] === '000001') {
                if (! empty($taxData['ind_operacao'])) {
                    $ibsCbs['CodigoIndicadorOperacao'] = $taxData['ind_operacao'];
                }
            }

            if (empty($ibsCbs['CST']) || $ibsCbs['CST'] === '000') {
                if (! empty($taxData['cst_ibs'])) {
                    $ibsCbs['CST'] = $taxData['cst_ibs'];
                }
            }

            if (empty($ibsCbs['CodigoClassTrib']) || $ibsCbs['CodigoClassTrib'] === '000001') {
                if (! empty($taxData['class_trib'])) {
                    $ibsCbs['CodigoClassTrib'] = $taxData['class_trib'];
                }
            }

            $item['InfRps']['IbsCbs'] = $ibsCbs;
        }
        unset($item);

        return $listaRps;
    }
}
