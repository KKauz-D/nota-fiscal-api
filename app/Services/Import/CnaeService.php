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
}
