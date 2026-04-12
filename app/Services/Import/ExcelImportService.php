<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Services\External\BrasilApiService;
use App\Services\External\ViaCepService;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExcelImportService
{
    private const HEADER_MAP = [
        'rps_numero'               => ['RPS Número', 'Número RPS', 'rps_numero'],
        'rps_serie'                => ['RPS Série', 'Série RPS', 'rps_serie'],
        'rps_tipo'                 => ['RPS Tipo', 'Tipo RPS', 'rps_tipo'],
        'data_emissao'             => ['Data de Emissão', 'Data Emissão', 'Data', 'data_emissao'],
        'natureza_operacao'        => ['Natureza da Operação', 'Natureza Operação', 'Natureza', 'natureza_operacao'],
        'optante_simples'          => ['Optante Simples', 'Simples Nacional', 'optante_simples'],
        'incentivador_cultural'    => ['Incentivador Cultural', 'incentivador_cultural'],
        'status'                   => ['Status', 'status'],
        'item_lista_servico'       => ['Item Lista Serviço', 'Item Lista', 'item_lista_servico'],
        'cod_tribut_mun'           => ['Cod. Tribut. Município', 'Cod. Tribut. Munic.', 'Cód. Tribut. Munic.', 'Código Tributação', 'cod_tribut_mun'],
        'cod_mun_prestacao'        => ['Cod. Município Prestação', 'Cód. Munic. Prestação', 'Município Prestação', 'cod_mun_prestacao'],
        'valor_servicos'           => ['Valor Serviços', 'Valor', 'valor_servicos'],
        'iss_retido'               => ['ISS Retido', 'Retido', 'iss_retido'],
        'aliquota'                 => ['Alíquota', 'aliquota'],
        'tomador_cpf_cnpj'         => ['Tomador CPF/CNPJ', 'CPF/CNPJ Tomador', 'tomador_cpf_cnpj'],
        'tomador_razao'            => ['Tomador Razão', 'Tomador Razão Social', 'Razão Social Tomador', 'tomador_razao'],
        'tomador_email'            => ['Tomador E-mail', 'E-mail Tomador', 'tomador_email'],
        'tomador_cep'              => ['Tomador CEP', 'CEP Tomador', 'tomador_cep'],
        'tomador_endereco'         => ['Tomador Endereço', 'Endereço Tomador', 'tomador_endereco'],
        'tomador_numero'           => ['Tomador Número', 'Número Tomador', 'tomador_numero'],
        'tomador_bairro'           => ['Tomador Bairro', 'Bairro Tomador', 'tomador_bairro'],
        'tomador_uf'               => ['Tomador UF', 'UF Tomador', 'tomador_uf'],
        'tomador_cod_mun'          => ['Tomador Cod. Município', 'Tomador Cód. Munic.', 'Município Tomador', 'tomador_cod_mun'],
        'discriminacao'            => ['Discriminação', 'Descrição', 'discriminacao'],
        'cod_nbs'                  => ['Código NBS', 'Cód. NBS', 'NBS', 'cod_nbs'],
        'ind_operacao'             => ['IBS Indicador Operacao', 'Ind. Operação', 'INDOP', 'ind_operacao'],
        'cst_ibs'                  => ['IBS CST', 'CST IBS', 'CST', 'cst_ibs'],
        'class_trib'               => ['IBS Class. Trib.', 'Class. Trib.', 'Classificação Tributária', 'class_trib'],
        'ind_finalidade_nfse'      => ['Ind. FInan. Nfse', 'Ind. Finalidade NFSe', 'ind_finalidade_nfse'],
        'ind_uso_consumo_pessoal'  => ['ind. uso. pessoal', 'Ind. Uso Consumo Pessoal', 'ind_uso_consumo_pessoal'],
        'tomador_telefone'         => ['Tomador TElefone', 'Tomador Telefone', 'Telefone Tomador', 'tomador_telefone'],
        'retencao_federal'         => ['Retenção Federal', 'Retenção', 'retencao_federal'],
        'cnae'                     => ['CNAE', 'CNAE/Serviço', 'cnae'],
    ];

    private const LEGACY_COLUMN_MAP = [
        'A' => 'rps_numero', 'B' => 'rps_serie', 'C' => 'rps_tipo', 'D' => 'data_emissao',
        'E' => 'natureza_operacao', 'F' => 'optante_simples', 'G' => 'incentivador_cultural', 'H' => 'status',
        'I' => 'item_lista_servico', 'J' => 'cod_tribut_mun', 'K' => 'cod_mun_prestacao', 'L' => 'valor_servicos',
        'M' => 'iss_retido', 'N' => 'aliquota', 'O' => 'tomador_cpf_cnpj', 'P' => 'tomador_razao',
        'Q' => 'tomador_email', 'R' => 'tomador_cep', 'S' => 'tomador_endereco', 'T' => 'tomador_numero',
        'U' => 'tomador_bairro', 'V' => 'tomador_uf', 'W' => 'tomador_cod_mun', 'X' => 'discriminacao',
        'Y' => 'cod_nbs', 'Z' => 'ind_operacao', 'AA' => 'cst_ibs', 'AB' => 'class_trib',
        'AC' => 'ind_finalidade_nfse', 'AD' => 'ind_uso_consumo_pessoal', 'AE' => 'tomador_telefone', 'AF' => 'retencao_federal',
        'AG' => 'cnae',
    ];

    public function __construct(
        private CnaeService $cnaeService,
        private BrasilApiService $brasilApiService,
        private ViaCepService $viaCepService,
    ) {}

    /**
     * Lê um arquivo Excel e retorna array de RPS estruturados.
     */
    public function read(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new Exception("Arquivo não encontrado: {$filePath}");
        }

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        // Detecta mapeamento por cabeçalho
        $actualMap = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $headerValueRaw = (string) $worksheet->getCell([$col, 1])->getValue();
            $headerValue = $this->normalizeHeader($headerValueRaw);
            $colLetter = Coordinate::stringFromColumnIndex($col);

            foreach (self::HEADER_MAP as $key => $aliases) {
                foreach ($aliases as $alias) {
                    if ($headerValue === $this->normalizeHeader($alias)) {
                        $actualMap[$colLetter] = $key;
                        break 2;
                    }
                }
            }
        }

        if (count($actualMap) < 5) {
            $actualMap = self::LEGACY_COLUMN_MAP;
        }

        $data = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $item = [];
            $isEmptyRow = true;

            foreach ($actualMap as $col => $key) {
                $cellValue = $worksheet->getCell($col . $row)->getCalculatedValue();
                if ($cellValue !== null && $cellValue !== '') {
                    $isEmptyRow = false;
                }
                $item[$key] = $this->sanitize($key, $cellValue);
            }

            if (! $isEmptyRow) {
                $data[] = $this->structureRps($item);
            }
        }

        return $data;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower($header, 'UTF-8');
        return preg_replace('/[^a-z0-9]/', '', $this->removeAccents($header));
    }

    private function removeAccents(string $string): string
    {
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i',
            'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o',
            'õ' => 'o', 'ö' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'Ä' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O',
            'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ù' => 'U', 'Ú' => 'U',
            'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
        ];

        return strtr($string, $map);
    }

    private function sanitize(string $key, mixed $value): mixed
    {
        if ($value === null) return '';

        $value = trim((string) $value);

        if (in_array($key, ['tomador_cpf_cnpj', 'tomador_cep', 'cod_mun_prestacao', 'tomador_cod_mun', 'tomador_telefone', 'cod_nbs'])) {
            return preg_replace('/\D/', '', $value);
        }

        if (in_array($key, ['valor_servicos', 'aliquota'])) {
            return (float) str_replace(',', '.', str_replace('%', '', $value));
        }

        if ($key === 'data_emissao') {
            if (empty($value)) {
                return date('Y-m-d\TH:i:s');
            }
            if (is_numeric($value)) {
                return Date::excelToDateTimeObject($value)->format('Y-m-d\TH:i:s');
            }
            $timestamp = strtotime(str_replace('/', '-', $value));
            return $timestamp ? date('Y-m-d\TH:i:s', $timestamp) : date('Y-m-d\TH:i:s');
        }

        return $value;
    }

    private function structureRps(array $flatData): array
    {
        // === TOMADOR AUTOMATION ===
        $cpfCnpj = preg_replace('/\D/', '', $flatData['tomador_cpf_cnpj'] ?? '');

        if (! empty($cpfCnpj)) {
            $this->enrichFromDatabase($flatData, $cpfCnpj);
            $this->enrichFromExternalApis($flatData, $cpfCnpj);
            $this->upsertCustomer($flatData, $cpfCnpj);
        }

        // CNAE lookup
        $cnaeOrService = $flatData['cnae'] ?? $flatData['cod_tribut_mun'] ?? '';
        if (! empty($cnaeOrService)) {
            $taxData = $this->cnaeService->lookup($cnaeOrService);
            if ($taxData) {
                foreach (['item_lista_servico', 'cod_tribut_mun', 'aliquota', 'cod_nbs', 'ind_operacao', 'cst_ibs', 'class_trib'] as $field) {
                    if (empty($flatData[$field]) && ! empty($taxData[$field])) {
                        $flatData[$field] = $taxData[$field];
                    }
                }
            }
        }

        // Cálculos financeiros
        $valorServicos = (float) ($flatData['valor_servicos'] ?? 0);
        $issRetido = ($flatData['iss_retido'] ?? '') ?: '2';
        $aliquota = (float) ($flatData['aliquota'] ?? 0);
        $valorIss = $valorServicos * ($aliquota / 100);

        $aplicaRetencao = strtolower(trim($flatData['retencao_federal'] ?? '')) === 'sim';
        $valorPis = $aplicaRetencao ? $valorServicos * 0.0065 : 0;
        $valorCofins = $aplicaRetencao ? $valorServicos * 0.03 : 0;
        $valorCsll = $aplicaRetencao ? $valorServicos * 0.01 : 0;
        $valorIr = $aplicaRetencao ? $valorServicos * 0.015 : 0;

        $totalRetencoesFederais = $valorPis + $valorCofins + $valorCsll + $valorIr;
        $descontoIss = ($issRetido == '1') ? $valorIss : 0;
        $valorLiquido = $valorServicos - $descontoIss - $totalRetencoesFederais;

        $codMunicipio = config('ginfes.cod_municipio');

        return [
            'InfRps' => [
                'IdentificacaoRps' => [
                    'Numero' => ($flatData['rps_numero'] ?? '') ?: '0',
                    'Serie' => ($flatData['rps_serie'] ?? '') ?: 'A',
                    'Tipo' => ($flatData['rps_tipo'] ?? '') ?: '1',
                ],
                'DataEmissao' => ($flatData['data_emissao'] ?? '') ?: date('Y-m-d\TH:i:s'),
                'NaturezaOperacao' => ($flatData['natureza_operacao'] ?? '') ?: '1',
                'OptanteSimplesNacional' => ($flatData['optante_simples'] ?? '') ?: '2',
                'IncentivadorCultural' => ($flatData['incentivador_cultural'] ?? '') ?: '2',
                'Status' => ($flatData['status'] ?? '') ?: '1',
                'Servico' => [
                    'Valores' => [
                        'ValorServicos' => $valorServicos,
                        'ValorPis' => $valorPis > 0 ? $valorPis : null,
                        'ValorCofins' => $valorCofins > 0 ? $valorCofins : null,
                        'ValorIr' => $valorIr > 0 ? $valorIr : null,
                        'ValorCsll' => $valorCsll > 0 ? $valorCsll : null,
                        'IssRetido' => $issRetido,
                        'ValorIss' => $valorIss,
                        'Aliquota' => $aliquota,
                        'ValorLiquidoNfse' => $valorLiquido,
                    ],
                    'ItemListaServico' => ($flatData['item_lista_servico'] ?? '') ?: '1.01',
                    'CodigoTributacaoMunicipio' => $flatData['cod_tribut_mun'] ?? '',
                    'Discriminacao' => ($flatData['discriminacao'] ?? '') ?: 'Prestação de serviço',
                    'CodigoMunicipio' => ($flatData['cod_mun_prestacao'] ?? '') ?: $codMunicipio,
                    'CodigoNbs' => $flatData['cod_nbs'] ?? '',
                ],
                'IbsCbs' => [
                    'CodigoIndicadorFinalidadeNFSe' => ($flatData['ind_finalidade_nfse'] ?? '') ?: '0',
                    'CodigoIndicadorOperacaoUsoConsumoPessoal' => ($flatData['ind_uso_consumo_pessoal'] ?? '') ?: '0',
                    'CodigoIndicadorOperacao' => ($flatData['ind_operacao'] ?? '') ?: '000001',
                    'IndDest' => '0',
                    'CST' => ($flatData['cst_ibs'] ?? '') ?: '000',
                    'CodigoClassTrib' => ($flatData['class_trib'] ?? '') ?: '000001',
                ],
                'Tomador' => [
                    'IdentificacaoTomador' => [
                        'CpfCnpj' => $this->formatCpfCnpj(($flatData['tomador_cpf_cnpj'] ?? '') ?: '00000000000'),
                    ],
                    'RazaoSocial' => ($flatData['tomador_razao'] ?? '') ?: 'CLIENTE AVULSO',
                    'Endereco' => [
                        'Endereco' => $flatData['tomador_endereco'] ?? '',
                        'Numero' => $flatData['tomador_numero'] ?? '',
                        'Bairro' => $flatData['tomador_bairro'] ?? '',
                        'CodigoMunicipio' => ($flatData['tomador_cod_mun'] ?? '') ?: $codMunicipio,
                        'Uf' => ($flatData['tomador_uf'] ?? '') ?: 'CE',
                        'Cep' => $flatData['tomador_cep'] ?? '',
                    ],
                    'Contato' => [
                        'Telefone' => $flatData['tomador_telefone'] ?? '',
                        'Email' => $flatData['tomador_email'] ?? '',
                    ],
                ],
            ],
        ];
    }

    private function enrichFromDatabase(array &$flatData, string $cpfCnpj): void
    {
        $customer = Customer::where('cpf_cnpj', $cpfCnpj)->first();
        if (! $customer) return;

        $fieldsToFill = [
            'tomador_razao' => 'razao_social',
            'tomador_endereco' => 'endereco',
            'tomador_numero' => 'numero',
            'tomador_bairro' => 'bairro',
            'tomador_uf' => 'uf',
            'tomador_cod_mun' => 'cod_mun',
            'tomador_cep' => 'cep',
            'tomador_email' => 'email',
            'tomador_telefone' => 'telefone',
        ];

        foreach ($fieldsToFill as $csvField => $dbField) {
            if (empty($flatData[$csvField]) && ! empty($customer->{$dbField})) {
                $flatData[$csvField] = $customer->{$dbField};
            }
        }
    }

    private function enrichFromExternalApis(array &$flatData, string $cpfCnpj): void
    {
        if (! empty($flatData['tomador_cod_mun']) && ! empty($flatData['tomador_razao']) && ! empty($flatData['tomador_endereco'])) {
            return;
        }

        if (strlen($cpfCnpj) === 14) {
            $apiData = $this->brasilApiService->consultarCnpj($cpfCnpj);
            if ($apiData) {
                $map = [
                    'tomador_razao' => 'razao_social',
                    'tomador_endereco' => 'logradouro',
                    'tomador_numero' => 'numero',
                    'tomador_bairro' => 'bairro',
                    'tomador_uf' => 'uf',
                    'tomador_cod_mun' => 'codigo_municipio',
                    'tomador_cep' => 'cep',
                ];
                foreach ($map as $csvField => $apiField) {
                    if (empty($flatData[$csvField]) && ! empty($apiData[$apiField])) {
                        $val = $apiData[$apiField];
                        if ($csvField === 'tomador_cep') $val = preg_replace('/\D/', '', $val);
                        $flatData[$csvField] = $val;
                    }
                }
            }
        } elseif (strlen($cpfCnpj) === 11 && ! empty($flatData['tomador_cep'])) {
            $cep = preg_replace('/\D/', '', $flatData['tomador_cep']);
            if (strlen($cep) === 8) {
                $apiData = $this->viaCepService->consultar($cep);
                if ($apiData) {
                    $map = [
                        'tomador_endereco' => 'logradouro',
                        'tomador_bairro' => 'bairro',
                        'tomador_uf' => 'uf',
                        'tomador_cod_mun' => 'ibge',
                    ];
                    foreach ($map as $csvField => $apiField) {
                        if (empty($flatData[$csvField]) && ! empty($apiData[$apiField])) {
                            $flatData[$csvField] = $apiData[$apiField];
                        }
                    }
                }
            }
        }
    }

    private function upsertCustomer(array $flatData, string $cpfCnpj): void
    {
        $data = array_filter([
            'razao_social' => $flatData['tomador_razao'] ?? null,
            'endereco' => $flatData['tomador_endereco'] ?? null,
            'numero' => $flatData['tomador_numero'] ?? null,
            'bairro' => $flatData['tomador_bairro'] ?? null,
            'cod_mun' => $flatData['tomador_cod_mun'] ?? null,
            'uf' => $flatData['tomador_uf'] ?? null,
            'cep' => $flatData['tomador_cep'] ?? null,
            'email' => $flatData['tomador_email'] ?? null,
            'telefone' => $flatData['tomador_telefone'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($data)) {
            Customer::updateOrCreate(['cpf_cnpj' => $cpfCnpj], $data);
        }
    }

    private function formatCpfCnpj(string $value): array
    {
        $value = preg_replace('/\D/', '', $value);
        return strlen($value) === 11 ? ['Cpf' => $value] : ['Cnpj' => $value];
    }
}
