<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Cache;

class CnaeService
{
    private ?array $data = null;
    private string $csvPath;

    public function __construct()
    {
        $this->csvPath = database_path('data/Base_Master_Tributaria.csv');
    }

    public function lookup(string $input): ?array
    {
        $inputLimpo = preg_replace('/\D/', '', $input);
        if (empty($inputLimpo)) {
            return null;
        }

        return Cache::remember("cnae_lookup_{$inputLimpo}", 3600, function () use ($inputLimpo) {
            $cache = $this->loadData();
            return $cache['cnae_' . $inputLimpo] ?? $cache['serv_' . $inputLimpo] ?? null;
        });
    }

    private function loadData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $this->data = [];

        if (!file_exists($this->csvPath)) {
            return $this->data;
        }

        $handle = fopen($this->csvPath, 'r');
        if (!$handle) {
            return $this->data;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $this->data;
        }

        $colMap = array_flip($header);
        $indices = [
            'CNAE_Limpo'  => $colMap['CNAE_Limpo'] ?? null,
            'COD_SERVICO' => $colMap['CÓDIGO DO SERVIÇO'] ?? null,
            'ItemLista'   => $colMap['Item_Lista_Limpo'] ?? null,
            'Aliquota'    => $colMap['ALÍQUOTA'] ?? null,
            'NBS'         => $colMap['NBS'] ?? null,
            'INDOP'       => $colMap['INDOP'] ?? null,
            'CST'         => $colMap['CST'] ?? null,
            'cClassTrib'  => $colMap['cClassTrib'] ?? null,
            'Incidencia'  => $colMap['Incidencia_Fortaleza'] ?? null,
            'Retencao'    => $colMap['Retencao_Fortaleza'] ?? null,
        ];

        while (($row = fgetcsv($handle)) !== false) {
            $cnaeKey = null;
            $servicoKey = null;

            if ($indices['CNAE_Limpo'] !== null && isset($row[$indices['CNAE_Limpo']])) {
                $cnaeKey = preg_replace('/\.0$/', '', (string)$row[$indices['CNAE_Limpo']]);
            }
            if ($indices['COD_SERVICO'] !== null && isset($row[$indices['COD_SERVICO']])) {
                $servicoKey = preg_replace('/\.0$/', '', (string)$row[$indices['COD_SERVICO']]);
            }

            $mappedRow = [
                'item_lista_servico' => $indices['ItemLista'] !== null ? ($row[$indices['ItemLista']] ?? '') : '',
                'cod_tribut_mun'     => $indices['COD_SERVICO'] !== null ? ($row[$indices['COD_SERVICO']] ?? '') : '',
                'aliquota'           => $indices['Aliquota'] !== null ? str_replace(',', '.', str_replace('%', '', $row[$indices['Aliquota']] ?? '')) : '',
                'cod_nbs'            => $indices['NBS'] !== null ? preg_replace('/\D/', '', $row[$indices['NBS']] ?? '') : '',
                'ind_operacao'       => $indices['INDOP'] !== null ? str_pad(preg_replace('/\D/', '', $row[$indices['INDOP']] ?? '1'), 6, '0', STR_PAD_LEFT) : '000001',
                'cst_ibs'            => $indices['CST'] !== null ? str_pad(preg_replace('/\D/', '', $row[$indices['CST']] ?? '0'), 3, '0', STR_PAD_LEFT) : '000',
                'class_trib'         => $indices['cClassTrib'] !== null ? str_pad(preg_replace('/\D/', '', $row[$indices['cClassTrib']] ?? '1'), 6, '0', STR_PAD_LEFT) : '000001',
                'incidencia_fortal'  => $indices['Incidencia'] !== null ? ($row[$indices['Incidencia']] ?? '') : '',
                'retencao_fortal'    => $indices['Retencao'] !== null ? ($row[$indices['Retencao']] ?? '') : '',
            ];

            if ($cnaeKey) $this->data['cnae_' . $cnaeKey] = $mappedRow;
            if ($servicoKey) $this->data['serv_' . $servicoKey] = $mappedRow;
        }

        fclose($handle);
        return $this->data;
    }

    /**
     * Busca atividades por código ou descrição (para autocomplete).
     *
     * @param  string  $query   Texto de busca (código ou descrição parcial)
     * @param  int     $limit   Máximo de resultados
     * @return array   Lista de atividades encontradas
     */
    public function search(string $query, int $limit = 15): array
    {
        $query = mb_strtolower(trim($query), 'UTF-8');
        if (empty($query)) {
            return [];
        }

        $results = [];
        $searchData = $this->loadSearchData();

        foreach ($searchData as $entry) {
            $codeMatch = str_contains(mb_strtolower($entry['cod_servico'] ?? '', 'UTF-8'), $query)
                      || str_contains(mb_strtolower($entry['cnae'] ?? '', 'UTF-8'), $query)
                      || str_contains(mb_strtolower($entry['item_lista_servico'] ?? '', 'UTF-8'), $query);

            $descMatch = str_contains(mb_strtolower($entry['descricao'] ?? '', 'UTF-8'), $query);

            if ($codeMatch || $descMatch) {
                $results[] = $entry;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Carrega dados do CSV para busca, incluindo descrições.
     * Usa cache separado pois inclui campos extras não usados no lookup.
     */
    private function loadSearchData(): array
    {
        static $searchCache = null;
        if ($searchCache !== null) {
            return $searchCache;
        }

        $searchCache = [];

        if (!file_exists($this->csvPath)) {
            return $searchCache;
        }

        $handle = fopen($this->csvPath, 'r');
        if (!$handle) {
            return $searchCache;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $searchCache;
        }

        $colMap = array_flip($header);
        $indices = [
            'CNAE_Limpo'   => $colMap['CNAE_Limpo'] ?? null,
            'COD_SERVICO'  => $colMap['CÓDIGO DO SERVIÇO'] ?? null,
            'Descricao'    => $colMap['Descrição'] ?? null,
            'ItemLista'    => $colMap['Item_Lista_Limpo'] ?? null,
            'Aliquota'     => $colMap['ALÍQUOTA'] ?? null,
            'NBS'          => $colMap['NBS'] ?? null,
            'INDOP'        => $colMap['INDOP'] ?? null,
            'CST'          => $colMap['CST'] ?? null,
            'cClassTrib'   => $colMap['cClassTrib'] ?? null,
            'Incidencia'   => $colMap['Incidencia_Fortaleza'] ?? null,
            'Retencao'     => $colMap['Retencao_Fortaleza'] ?? null,
        ];

        $seen = [];
        while (($row = fgetcsv($handle)) !== false) {
            $codServico = $indices['COD_SERVICO'] !== null ? ($row[$indices['COD_SERVICO']] ?? '') : '';
            $cnae = $indices['CNAE_Limpo'] !== null ? preg_replace('/\.0$/', '', (string)($row[$indices['CNAE_Limpo']] ?? '')) : '';
            $descricao = $indices['Descricao'] !== null ? ($row[$indices['Descricao']] ?? '') : '';

            // Deduplicar por código de serviço
            $key = $codServico ?: $cnae;
            if (empty($key) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $searchCache[] = [
                'cod_servico'        => $codServico,
                'cnae'               => $cnae,
                'descricao'          => $descricao,
                'item_lista_servico' => $indices['ItemLista'] !== null ? ($row[$indices['ItemLista']] ?? '') : '',
                'cod_tribut_mun'     => $codServico,
                'aliquota'           => $indices['Aliquota'] !== null ? str_replace(',', '.', str_replace('%', '', $row[$indices['Aliquota']] ?? '')) : '',
                'cod_nbs'            => $indices['NBS'] !== null ? preg_replace('/\D/', '', $row[$indices['NBS']] ?? '') : '',
                'ind_operacao'       => $indices['INDOP'] !== null ? str_pad(preg_replace('/\D/', '', $row[$indices['INDOP']] ?? '1'), 6, '0', STR_PAD_LEFT) : '000001',
                'cst_ibs'            => $indices['CST'] !== null ? str_pad(preg_replace('/\D/', '', $row[$indices['CST']] ?? '0'), 3, '0', STR_PAD_LEFT) : '000',
                'class_trib'         => $indices['cClassTrib'] !== null ? str_pad(preg_replace('/\D/', '', $row[$indices['cClassTrib']] ?? '1'), 6, '0', STR_PAD_LEFT) : '000001',
                'incidencia_fortal'  => $indices['Incidencia'] !== null ? ($row[$indices['Incidencia']] ?? '') : '',
                'retencao_fortal'    => $indices['Retencao'] !== null ? ($row[$indices['Retencao']] ?? '') : '',
            ];
        }

        fclose($handle);
        return $searchCache;
    }
}
