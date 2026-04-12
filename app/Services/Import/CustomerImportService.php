<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Services\External\ViaCepService;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CustomerImportService
{
    public function __construct(
        private ViaCepService $viaCepService,
    ) {}

    /**
     * Importa tomadores (clientes) a partir de um arquivo Excel.
     * Colunas esperadas: A=cpf_cnpj, B=razao_social, C=endereco, D=numero,
     *                    E=bairro, F=uf, G=cep, H=cod_mun, I=email, J=telefone
     *
     * @return int Quantidade de registros importados
     */
    public function import(string $filePath): int
    {
        if (! file_exists($filePath)) {
            throw new Exception("Arquivo não encontrado: {$filePath}");
        }

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();

        $headerMap = [
            'A' => 'cpf_cnpj', 'B' => 'razao_social', 'C' => 'endereco', 'D' => 'numero',
            'E' => 'bairro', 'F' => 'uf', 'G' => 'cep', 'H' => 'cod_mun',
            'I' => 'email', 'J' => 'telefone',
        ];

        $count = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $data = [];
            foreach ($headerMap as $col => $key) {
                $data[$key] = trim((string) $worksheet->getCell($col . $row)->getCalculatedValue());
            }

            $cpfCnpj = preg_replace('/\D/', '', $data['cpf_cnpj']);
            if (empty($cpfCnpj)) continue;

            $data['cpf_cnpj'] = $cpfCnpj;

            // Auto-fill from ViaCEP if municipality code is missing
            $cep = preg_replace('/\D/', '', $data['cep']);
            if (empty($data['cod_mun']) && strlen($cep) === 8) {
                $viaCepData = $this->viaCepService->consultar($cep);
                if ($viaCepData) {
                    if (empty($data['cod_mun']) && ! empty($viaCepData['ibge'])) {
                        $data['cod_mun'] = $viaCepData['ibge'];
                    }
                    if (empty($data['endereco']) && ! empty($viaCepData['logradouro'])) {
                        $data['endereco'] = $viaCepData['logradouro'];
                    }
                    if (empty($data['bairro']) && ! empty($viaCepData['bairro'])) {
                        $data['bairro'] = $viaCepData['bairro'];
                    }
                    if (empty($data['uf']) && ! empty($viaCepData['uf'])) {
                        $data['uf'] = $viaCepData['uf'];
                    }
                }
            }

            // Upsert
            $updateData = array_filter($data, fn ($v) => $v !== null && $v !== '');
            unset($updateData['cpf_cnpj']);

            Customer::updateOrCreate(
                ['cpf_cnpj' => $cpfCnpj],
                $updateData,
            );

            $count++;
        }

        return $count;
    }
}
