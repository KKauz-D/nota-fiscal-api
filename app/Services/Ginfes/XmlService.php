<?php

namespace App\Services\Ginfes;

use DOMDocument;

class XmlService
{
    /**
     * Gera o XML de EnviarLoteRpsEnvio conforme padrão GINFES v3/v4.
     * Lógica preservada do sistema legado para compatibilidade total.
     */
    public function generateLoteRps(array $lote, array $listaRps): string
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $nsServico = config('ginfes.namespaces.enviar_lote');
        $nsTipos = config('ginfes.namespaces.tipos');

        $root = $dom->createElementNS($nsServico, 'EnviarLoteRpsEnvio');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ns3', $nsTipos);
        $dom->appendChild($root);

        $loteRps = $dom->createElement('LoteRps');
        $loteRps->setAttribute('Id', $lote['id']);
        $root->appendChild($loteRps);

        $loteRps->appendChild($dom->createElement('ns3:NumeroLote', $lote['numeroLote']));
        $loteRps->appendChild($dom->createElement('ns3:Cnpj', preg_replace('/\D/', '', $lote['cnpj'])));
        $loteRps->appendChild($dom->createElement('ns3:InscricaoMunicipal', preg_replace('/\D/', '', $lote['inscricaoMunicipal'])));
        $loteRps->appendChild($dom->createElement('ns3:QuantidadeRps', count($listaRps)));

        $lista = $dom->createElement('ns3:ListaRps');
        $loteRps->appendChild($lista);

        foreach ($listaRps as $rps) {
            $rpsEl = $dom->createElement('ns3:Rps');
            $lista->appendChild($rpsEl);

            $inf = $dom->createElement('ns3:InfRps');
            $inf->setAttribute('Id', 'infRps_' . $rps['InfRps']['IdentificacaoRps']['Numero']);
            $rpsEl->appendChild($inf);

            // IdentificacaoRps
            $idRps = $dom->createElement('ns3:IdentificacaoRps');
            $idRps->appendChild($dom->createElement('ns3:Numero', $rps['InfRps']['IdentificacaoRps']['Numero']));
            $idRps->appendChild($dom->createElement('ns3:Serie', $rps['InfRps']['IdentificacaoRps']['Serie']));
            $idRps->appendChild($dom->createElement('ns3:Tipo', $rps['InfRps']['IdentificacaoRps']['Tipo']));
            $inf->appendChild($idRps);

            $inf->appendChild($dom->createElement('ns3:DataEmissao', $rps['InfRps']['DataEmissao']));
            $inf->appendChild($dom->createElement('ns3:NaturezaOperacao', $rps['InfRps']['NaturezaOperacao']));

            if (!empty($rps['InfRps']['RegimeEspecialTributacao'])) {
                $inf->appendChild($dom->createElement('ns3:RegimeEspecialTributacao', $rps['InfRps']['RegimeEspecialTributacao']));
            }

            $inf->appendChild($dom->createElement('ns3:OptanteSimplesNacional', $rps['InfRps']['OptanteSimplesNacional']));
            $inf->appendChild($dom->createElement('ns3:IncentivadorCultural', $rps['InfRps']['IncentivadorCultural']));
            $inf->appendChild($dom->createElement('ns3:Status', $rps['InfRps']['Status']));

            // Servico
            $svc = $dom->createElement('ns3:Servico');
            $inf->appendChild($svc);

            $vals = $dom->createElement('ns3:Valores');
            $svc->appendChild($vals);

            $vals->appendChild($dom->createElement('ns3:ValorServicos', $this->formatDecimal($rps['InfRps']['Servico']['Valores']['ValorServicos'])));

            if (!empty($rps['InfRps']['Servico']['Valores']['ValorPis'])) {
                $vals->appendChild($dom->createElement('ns3:ValorPis', $this->formatDecimal($rps['InfRps']['Servico']['Valores']['ValorPis'])));
            }
            if (!empty($rps['InfRps']['Servico']['Valores']['ValorCofins'])) {
                $vals->appendChild($dom->createElement('ns3:ValorCofins', $this->formatDecimal($rps['InfRps']['Servico']['Valores']['ValorCofins'])));
            }
            if (!empty($rps['InfRps']['Servico']['Valores']['ValorInss'])) {
                $vals->appendChild($dom->createElement('ns3:ValorInss', $this->formatDecimal($rps['InfRps']['Servico']['Valores']['ValorInss'])));
            }
            if (!empty($rps['InfRps']['Servico']['Valores']['ValorIr'])) {
                $vals->appendChild($dom->createElement('ns3:ValorIr', $this->formatDecimal($rps['InfRps']['Servico']['Valores']['ValorIr'])));
            }
            if (!empty($rps['InfRps']['Servico']['Valores']['ValorCsll'])) {
                $vals->appendChild($dom->createElement('ns3:ValorCsll', $this->formatDecimal($rps['InfRps']['Servico']['Valores']['ValorCsll'])));
            }

            $vals->appendChild($dom->createElement('ns3:IssRetido', $rps['InfRps']['Servico']['Valores']['IssRetido']));

            if (!empty($rps['InfRps']['Servico']['Valores']['ValorIss'])) {
                $vals->appendChild($dom->createElement('ns3:ValorIss', $this->formatDecimal($rps['InfRps']['Servico']['Valores']['ValorIss'])));
            }

            $vals->appendChild($dom->createElement('ns3:BaseCalculo', $this->formatDecimal($rps['InfRps']['Servico']['Valores']['ValorServicos'])));

            if (!empty($rps['InfRps']['Servico']['Valores']['Aliquota'])) {
                $aliquota = (float) $rps['InfRps']['Servico']['Valores']['Aliquota'];
                $valorDecimal = $aliquota > 1 ? $aliquota / 100 : $aliquota;
                $vals->appendChild($dom->createElement('ns3:Aliquota', $this->formatDecimal($valorDecimal, 4)));
            }

            if (!empty($rps['InfRps']['Servico']['Valores']['ValorLiquidoNfse'])) {
                $vals->appendChild($dom->createElement('ns3:ValorLiquidoNfse', $this->formatDecimal($rps['InfRps']['Servico']['Valores']['ValorLiquidoNfse'])));
            }

            // Detalhes do Serviço
            $svc->appendChild($dom->createElement('ns3:ItemListaServico', $rps['InfRps']['Servico']['ItemListaServico']));

            if (!empty($rps['InfRps']['Servico']['CodigoTributacaoMunicipio'])) {
                $svc->appendChild($dom->createElement('ns3:CodigoTributacaoMunicipio', $rps['InfRps']['Servico']['CodigoTributacaoMunicipio']));
            }

            $svc->appendChild($dom->createElement('ns3:Discriminacao', substr($rps['InfRps']['Servico']['Discriminacao'], 0, 2000)));
            $svc->appendChild($dom->createElement('ns3:CodigoMunicipio', $rps['InfRps']['Servico']['CodigoMunicipio']));

            if (!empty($rps['InfRps']['Servico']['CodigoNbs'])) {
                $svc->appendChild($dom->createElement('ns3:CodigoNbs', $rps['InfRps']['Servico']['CodigoNbs']));
            }

            // Prestador
            $prest = $dom->createElement('ns3:Prestador');
            $prest->appendChild($dom->createElement('ns3:Cnpj', preg_replace('/\D/', '', $lote['cnpj'])));
            $prest->appendChild($dom->createElement('ns3:InscricaoMunicipal', preg_replace('/\D/', '', $lote['inscricaoMunicipal'])));
            $inf->appendChild($prest);

            // Tomador
            if (isset($rps['InfRps']['Tomador'])) {
                $tom = $dom->createElement('ns3:Tomador');
                $inf->appendChild($tom);

                $it = $dom->createElement('ns3:IdentificacaoTomador');
                $cpfCnpjData = $rps['InfRps']['Tomador']['IdentificacaoTomador']['CpfCnpj'];
                $cpfCnpjNode = $dom->createElement('ns3:CpfCnpj');
                if (isset($cpfCnpjData['Cnpj'])) {
                    $cpfCnpjNode->appendChild($dom->createElement('ns3:Cnpj', preg_replace('/\D/', '', $cpfCnpjData['Cnpj'])));
                } else {
                    $cpfCnpjNode->appendChild($dom->createElement('ns3:Cpf', preg_replace('/\D/', '', $cpfCnpjData['Cpf'])));
                }
                $it->appendChild($cpfCnpjNode);
                $tom->appendChild($it);

                $tom->appendChild($dom->createElement('ns3:RazaoSocial', substr($rps['InfRps']['Tomador']['RazaoSocial'], 0, 115)));

                $end = $dom->createElement('ns3:Endereco');
                $endMap = $rps['InfRps']['Tomador']['Endereco'];
                $end->appendChild($dom->createElement('ns3:Endereco', substr($endMap['Endereco'] ?? '', 0, 125)));
                $end->appendChild($dom->createElement('ns3:Numero', substr($endMap['Numero'] ?? '', 0, 10)));
                $end->appendChild($dom->createElement('ns3:Bairro', substr($endMap['Bairro'] ?? '', 0, 60)));
                $end->appendChild($dom->createElement('ns3:CodigoMunicipio', $endMap['CodigoMunicipio']));
                $end->appendChild($dom->createElement('ns3:Uf', $endMap['Uf']));
                $end->appendChild($dom->createElement('ns3:Cep', preg_replace('/\D/', '', $endMap['Cep'])));
                $tom->appendChild($end);

                $telefone = $rps['InfRps']['Tomador']['Contato']['Telefone'] ?? '';
                $email = $rps['InfRps']['Tomador']['Contato']['Email'] ?? '';

                if (!empty($telefone) || !empty($email)) {
                    $cont = $dom->createElement('ns3:Contato');
                    if (!empty($telefone)) {
                        $cont->appendChild($dom->createElement('ns3:Telefone', substr($telefone, 0, 11)));
                    }
                    if (!empty($email)) {
                        $cont->appendChild($dom->createElement('ns3:Email', substr($email, 0, 80)));
                    }
                    $tom->appendChild($cont);
                }
            }

            // IbsCbs
            if (isset($rps['InfRps']['IbsCbs'])) {
                $ibsCbs = $dom->createElement('ns3:IbsCbs');
                $inf->appendChild($ibsCbs);

                $ibsCbs->appendChild($dom->createElement('ns3:CodigoIndicadorFinalidadeNFSe', $rps['InfRps']['IbsCbs']['CodigoIndicadorFinalidadeNFSe']));
                $ibsCbs->appendChild($dom->createElement('ns3:CodigoIndicadorOperacaoUsoConsumoPessoal', $rps['InfRps']['IbsCbs']['CodigoIndicadorOperacaoUsoConsumoPessoal']));
                $ibsCbs->appendChild($dom->createElement('ns3:CodigoIndicadorOperacao', $rps['InfRps']['IbsCbs']['CodigoIndicadorOperacao']));
                $ibsCbs->appendChild($dom->createElement('ns3:IndDest', $rps['InfRps']['IbsCbs']['IndDest']));

                $ibsValores = $dom->createElement('ns3:Valores');
                $ibsCbs->appendChild($ibsValores);

                $tributos = $dom->createElement('ns3:TributosIbsCbs');
                $ibsValores->appendChild($tributos);

                $grupoIbs = $dom->createElement('ns3:GrupoIbsCbs');
                $tributos->appendChild($grupoIbs);

                $grupoIbs->appendChild($dom->createElement('ns3:CST', $rps['InfRps']['IbsCbs']['CST']));
                $grupoIbs->appendChild($dom->createElement('ns3:CodigoClassTrib', $rps['InfRps']['IbsCbs']['CodigoClassTrib']));
            }
        }

        return $dom->saveXML();
    }

    /**
     * Valida uma string XML contra um arquivo de schema XSD.
     */
    public function validarXmlContraXsd(string $xmlString, string $xsdPath): bool|array
    {
        if (!file_exists($xsdPath)) {
            return ['Erro: Arquivo de schema (XSD) não encontrado em: ' . $xsdPath];
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'utf-8');
        if (!$dom->loadXML($xmlString)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = [];
            foreach ($errors as $e) $msg[] = "XML Malformado: " . $e->message;
            return $msg;
        }

        if (!$dom->schemaValidate($xsdPath)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors(false);

            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = "Erro de validação na linha {$error->line}: {$error->message}";
            }
            return $errorMessages;
        }

        libxml_use_internal_errors(false);
        return true;
    }

    /**
     * Gera o XML de ConsultarSituacaoLoteRps.
     */
    public function generateConsultarSituacao(string $cnpj, string $inscricaoMunicipal, string $protocolo): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $nsServico = config('ginfes.namespaces.consultar_situacao');
        $nsTipos = config('ginfes.namespaces.tipos');

        $root = $dom->createElementNS($nsServico, 'ns2:ConsultarSituacaoLoteRpsEnvio');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:tipos', $nsTipos);
        $dom->appendChild($root);

        $prestador = $dom->createElement('ns2:Prestador');
        $prestador->appendChild($dom->createElement('tipos:Cnpj', preg_replace('/\D/', '', $cnpj)));
        $prestador->appendChild($dom->createElement('tipos:InscricaoMunicipal', preg_replace('/\D/', '', $inscricaoMunicipal)));
        $root->appendChild($prestador);

        $root->appendChild($dom->createElement('ns2:Protocolo', $protocolo));

        return $dom->saveXML();
    }

    /**
     * Gera o XML de ConsultarLoteRps.
     */
    public function generateConsultarLoteRps(string $cnpj, string $inscricaoMunicipal, string $protocolo): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $nsServico = config('ginfes.namespaces.consultar_lote');
        $nsTipos = config('ginfes.namespaces.tipos');

        $root = $dom->createElementNS($nsServico, 'ns2:ConsultarLoteRpsEnvio');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:tipos', $nsTipos);
        $dom->appendChild($root);

        $prestador = $dom->createElement('ns2:Prestador');
        $prestador->appendChild($dom->createElement('tipos:Cnpj', preg_replace('/\D/', '', $cnpj)));
        $prestador->appendChild($dom->createElement('tipos:InscricaoMunicipal', preg_replace('/\D/', '', $inscricaoMunicipal)));
        $root->appendChild($prestador);

        $root->appendChild($dom->createElement('ns2:Protocolo', $protocolo));

        return $dom->saveXML();
    }

    private function formatDecimal($value, int $decimals = 2): string
    {
        if ($value === '' || $value === null) return '0.' . str_repeat('0', $decimals);
        return number_format((float) $value, $decimals, '.', '');
    }
}
